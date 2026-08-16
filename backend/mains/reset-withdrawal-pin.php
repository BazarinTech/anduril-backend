<?php
include '../includes/initiate.php';
include 'sms-code.php';
include 'code-helpers.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;

/**
 * WITHDRAWAL PIN RESET
 * ====================
 * For a user who has forgotten the PIN that guards their payouts. Two steps:
 *
 *   type: 'request'  -> SMS a code to the number on the account
 *   type: 'reset'    -> spend that code and write the new PIN
 *
 * WHY THIS IS NOT SHAPED LIKE forgot-password.php
 * -----------------------------------------------
 * Password reset has to accept a phone number from an unauthenticated caller,
 * because someone locked out of their account has nothing else to identify
 * themselves with. That is unavoidable there. It is not the situation here:
 * a user resetting their withdrawal PIN is already signed in, so this endpoint
 * requires a valid token and takes the phone number *off the account*.
 *
 * That distinction is the whole security of this flow. If the number came from
 * the request body, anyone holding a stolen token could have the code sent to
 * their own handset and take over the payout PIN in two calls. Reading it from
 * the database means the code always lands on the registered phone, so an
 * attacker needs the session *and* the SIM.
 *
 * WHY VERIFY AND SET ARE ONE CALL
 * -------------------------------
 * verification.php's flow is Pending -> Verified -> Used, with the reset a
 * separate request. That leaves a window where a Verified code is sitting in
 * the table, and any endpoint that accepts "a Verified code for this phone"
 * can spend it. Here the code goes straight from Pending to Used inside the
 * same transaction that writes the PIN, so there is no interval in which a
 * verified-but-unspent code exists.
 *
 * The reset deliberately does NOT touch withdrawal_account or withdrawal_name.
 * Changing where money goes is a separate operation that still demands the
 * current PIN.
 */

$data = $fileGetContent->get_content();

$type = $data['type'] ?? '';

if (!isset($data['userID'])) {
    $fileGetContent->send_content([
        'status'  => 'Error',
        'message' => 'Some fields are empty',
    ]);
    exit;
}

try {
    $decoded = JWT::decode($data['userID'], new Key(JWT_SECRET, JWT_ALGO));
    $userID  = $decoded->userID ?? $decoded->sub ?? null;

    if (!$userID) {
        $fileGetContent->send_content(['status' => 'Error', 'message' => 'Invalid token']);
        exit;
    }
} catch (ExpiredException $e) {
    $fileGetContent->send_content(['status' => 'Error', 'message' => 'Token expired']);
    exit;
} catch (SignatureInvalidException $e) {
    $fileGetContent->send_content(['status' => 'Error', 'message' => 'Invalid token signature']);
    exit;
} catch (\Exception $e) {
    $fileGetContent->send_content(['status' => 'Error', 'message' => 'Invalid token']);
    exit;
}

$users = $query->select('users', '*', ['ID' => $userID]);
$user  = $users[0] ?? null;

if ($user === null) {
    $fileGetContent->send_content([
        'status'  => 'Failed',
        'message' => 'Account not found.',
    ]);
    exit;
}

$wallets = $query->select('wallets', '*', ['userID' => $userID]);
$wallet  = $wallets[0] ?? null;

if ($wallet === null) {
    $fileGetContent->send_content([
        'status'  => 'Failed',
        'message' => 'No wallet found for this account.',
    ]);
    exit;
}

// The account's own number. Never $data['phone'] -- see the note above.
$phone = (string) ($user['phone'] ?? '');

if ($phone === '') {
    $fileGetContent->send_content([
        'status'  => 'Failed',
        'message' => 'No phone number is registered on this account. Please contact support.',
    ]);
    exit;
}

/**
 * Shows enough of the number to confirm which handset to check, and not
 * enough to be worth reading over a shoulder.
 */
function mask_phone($phone)
{
    $length = strlen($phone);

    if ($length <= 7) {
        return str_repeat('*', max(0, $length - 2)) . substr($phone, -2);
    }

    return substr($phone, 0, 4) . str_repeat('*', $length - 7) . substr($phone, -3);
}

if ($type === 'request') {
    if (($wallet['withdrawal_pin'] ?? '') === '') {
        // Nothing to reset. Sending an SMS here would be a wasted message and
        // would imply a PIN exists.
        $fileGetContent->send_content([
            'status'  => 'Failed',
            'message' => 'No withdrawal PIN is set yet. Set up your withdrawal account first.',
        ]);
        exit;
    }

    // Shared with phone verification and password reset: three codes per
    // number per fifteen minutes. Each message costs real money.
    if (sms_rate_limited($query, $phone)) {
        $fileGetContent->send_content([
            'status'  => 'Failed',
            'message' => 'Too many code requests. Please wait a few minutes and try again.',
        ]);
        exit;
    }

    /**
     * Retire any code still outstanding for this purpose before issuing a new
     * one. Otherwise "resend" leaves several live codes against the same
     * account, and each extra one is another guess an attacker gets for free.
     */
    foreach ($query->select('verification_codes', '*', [
        'phone'  => $phone,
        'type'   => 'WithdrawalPinReset',
        'status' => 'Pending',
    ]) as $stale) {
        $query->update('verification_codes', ['status' => 'Expired'], ['ID' => $stale['ID']]);
    }

    $result = sendSMS($phone, $query, $curl, 'WithdrawalPinReset');

    if (($result['status'] ?? '') === 'Success') {
        $fileGetContent->send_content([
            'status'  => 'Success',
            'message' => 'A verification code has been sent to your phone.',
            'phone'   => mask_phone($phone),
        ]);
        exit;
    }

    // The gateway's own wording carries our credentials and provider detail,
    // so it is logged rather than returned.
    error_log('[reset-withdrawal-pin] SMS failed for user ' . $userID . ': ' . ($result['message'] ?? 'unknown'));

    /**
     * Two failures that need different advice.
     *
     * A rejected *number* will be rejected again in five minutes, so "try
     * again shortly" sends the user round a loop that cannot terminate. It
     * means the number on the account cannot receive SMS -- usually a typo at
     * sign-up -- and the only way out is support.
     */
    $invalidNumber = trim((string) ($result['gateway']['invalidMobile'] ?? '')) !== '';

    if ($invalidNumber) {
        $fileGetContent->send_content([
            'status'  => 'Failed',
            'message' => 'The phone number registered on your account (' . mask_phone($phone)
                . ') cannot receive messages. Please contact support to update it.',
        ]);
        exit;
    }

    $fileGetContent->send_content([
        'status'  => 'Failed',
        'message' => 'Could not send the verification code. Please try again shortly.',
    ]);
    exit;
}

if ($type === 'reset') {
    $code   = trim((string) ($data['code'] ?? ''));
    $newPin = (string) ($data['newPin'] ?? '');

    if ($code === '') {
        $fileGetContent->send_content([
            'status'  => 'Failed',
            'message' => 'Enter the verification code sent to your phone.',
        ]);
        exit;
    }

    // Same rule the rest of the panel applies: digits only, 4 to 6 of them.
    if (!preg_match('/^\d{4,6}$/', $newPin)) {
        $fileGetContent->send_content([
            'status'  => 'Failed',
            'message' => 'PIN must be 4 to 6 digits.',
        ]);
        exit;
    }

    /**
     * The code must be Pending, for this number, and issued for *this*
     * purpose. Scoping by type is what stops a code texted for phone
     * verification from being spent on a payout PIN.
     */
    $rows = $query->select('verification_codes', '*', [
        'code'   => $code,
        'phone'  => $phone,
        'type'   => 'WithdrawalPinReset',
        'status' => 'Pending',
    ]);

    $row = $rows[0] ?? null;

    if ($row === null) {
        $fileGetContent->send_content([
            'status'  => 'Failed',
            'message' => 'Invalid or already used code. Please request a new one.',
        ]);
        exit;
    }

    if (code_expired($row)) {
        $query->update('verification_codes', ['status' => 'Expired'], ['ID' => $row['ID']]);

        $fileGetContent->send_content([
            'status'  => 'Failed',
            'message' => 'That code has expired. Please request a new one.',
        ]);
        exit;
    }

    /**
     * Burn the code and write the PIN together.
     *
     * The code is claimed with a conditional UPDATE first -- the same
     * primitive the money paths use. Two requests carrying the same code
     * cannot both pass, because only one of them can move the row out of
     * 'Pending'. Without that, a double-submitted form is two resets, and the
     * loser's PIN is the one that sticks.
     */
    $pdo->beginTransaction();

    try {
        $claim = $pdo->prepare(
            "UPDATE verification_codes
                SET status = 'Used'
              WHERE ID = :id AND status = 'Pending'"
        );
        $claim->execute([':id' => $row['ID']]);

        if ($claim->rowCount() !== 1) {
            $pdo->rollBack();

            $fileGetContent->send_content([
                'status'  => 'Failed',
                'message' => 'Invalid or already used code. Please request a new one.',
            ]);
            exit;
        }

        $query->update(
            'wallets',
            ['withdrawal_pin' => password_hash($newPin, PASSWORD_DEFAULT)],
            ['userID' => $userID]
        );

        $pdo->commit();
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('[reset-withdrawal-pin] ' . $e->getMessage());

        $fileGetContent->send_content([
            'status'  => 'Failed',
            'message' => 'Could not reset your PIN. Please try again.',
        ]);
        exit;
    }

    $fileGetContent->send_content([
        'status'  => 'Success',
        'message' => 'Your withdrawal PIN has been reset.',
    ]);
    exit;
}

$fileGetContent->send_content([
    'status'  => 'Failed',
    'message' => 'Unknown request type',
]);
