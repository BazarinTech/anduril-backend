<?php
include '../includes/initiate.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
// Get posted json data
$data = $fileGetContent->get_content();

// Process bonus 
if (isset($data)) {
    try {
        $decoded = JWT::decode($data['userID'], new Key(JWT_SECRET, JWT_ALGO));
        $userID = $decoded->userID ?? $decoded->sub ?? null;

        if (!$userID) {
            $fileGetContent->send_content([
                'status' => 'Error',
                'message' => 'Invalid token'
            ]);
            exit;
        }
        $bonusID = $data['bonusID'];
        $bonus = $query->select('bonus', '*', ['ID' => $bonusID]);
        $bonus = $bonus[0] ?? null;

        if ($bonus === null || $bonus['status'] !== 'Active') {
            $fileGetContent->send_content(['status' => 'Failed', 'message' => 'This bonus is not available.']);
            exit;
        }

        $reward = money($bonus['reward']);
        $target = (int) $bonus['target'];
        $bonus_type = $bonus['type'];
        $reward_type = $bonus['reward_type'];

        /**
         * Phase 3.2 -- the "already claimed?" check and the credit that follows
         * it were two separate statements with no lock between them, so firing
         * this endpoint twice at once let both requests see zero prior claims
         * and pay the bonus twice.
         *
         * Locking the wallet serialises claims per user, which is enough: the
         * claim marker is an `orders` row keyed to this user and bonus, and no
         * other user can create it.
         *
         * $response is initialised because several branches below fall through
         * without setting it -- that used to emit `null` as the whole body.
         */
        $response = ['status' => 'Failed', 'message' => 'You are not eligible for this bonus yet.'];

        $pdo->beginTransaction();

        try {
            $wallet = wallet_for_update($pdo, $userID);

            if ($wallet === null) {
                $pdo->rollBack();
                $fileGetContent->send_content(['status' => 'Failed', 'message' => 'Wallet not found.']);
                exit;
            }

            $balance = money($wallet['balance']);

            // Check is bonus order exists
            $bonus_order = $query->select('orders', '*', ['type' => 'bonus', 'userID' => $userID, 'prodID' => $bonusID]);

            if(count($bonus_order) == 0){
                // Phase 4.2 -- the same count the dashboard shows as progress.
                // See referral_progress() for the definitions.
                $downlines = bonus_progress($pdo, $userID, $bonus_type);

                if ($downlines >= $target && $reward_type == 'money') {
                    $balance += $reward;
                    $update_wallet = $query->update('wallets', ['balance' => money_str($balance)], ['userID' => $userID]);
                    $insert_order = $query->insert('orders', ['userID' => $userID, 'prodID' => $bonusID, 'type' => 'bonus', 'amount' => money_str($reward)]);

                    // Both branches now write a transaction row. The 'users'
                    // branch used to credit the wallet without recording one,
                    // so those bonuses never appeared in the user's history.
                    $insert_transaction = $query->insert('transactions', [
                        'userID'      => $userID,
                        'type'        => 'Bonus',
                        'amount'      => money_str($reward),
                        'description' => 'Bonus credited successfully to your wallet.',
                        'status'      => 'Completed'
                    ]);

                    $response = ['status' => 'Success', 'message' => 'Bonus credited successfully'];
                } elseif ($reward_type != 'money') {
                    $response = ['status' => 'Failed', 'message' => 'This bonus type cannot be claimed here.'];
                }
            }else{
                $response = ['status' => 'Failed', 'message' => 'You have already claimed this bonus.'];
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('[bonus] ' . $e->getMessage());

            $response = ['status' => 'Failed', 'message' => 'Could not claim the bonus. Please try again.'];
        }

        $fileGetContent->send_content($response);
} catch (ExpiredException $e) {
        $fileGetContent->send_content([
            'status' => 'Error',
            'message' => 'Token expired'
        ]);

    } catch (SignatureInvalidException $e) {
        $fileGetContent->send_content([
            'status' => 'Error',
            'message' => 'Invalid token signature'
        ]);
    } catch (\Exception $e) {
        $fileGetContent->send_content([
            'status' => 'Error',
            'message' => 'Invalid token'
        ]);
    }


}else{
    $response = [
            'status' => 'Failed',
            'message' => 'No token provided'
        ];
    $fileGetContent->send_content($response);
}