<?php
include '../includes/initiate.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;

// Get posted json data
$data = $fileGetContent->get_content();

// Process coupon
if (isset($data['userID']) && isset($data['code'])) {
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
        // Compute coupon
        $coupon_code = $data['code'];
        $coupon = $query->select('coupons', '*', ['code' => $coupon_code]);
        if(count($coupon) == 0){
            $response = [
                'status' => 'Failed',
                'message' => 'Invalid coupon code'
            ];
        }else{
            $coupon = $coupon[0];
            $orders = $query->select('orders', '*', ['type' => 'coupon', 'userID' => $userID, 'prodID' => $coupon['ID']]);
            $expiry = $coupon['expiry'] * 60;
            if(count($orders) > 0){
                $response = [
                    'status' => 'Failed',
                    'message' => 'Coupon already used by this user'
                ];
            }elseif ($expiry + strtotime($coupon['time_created']) < time()){
                $response = [
                    'status' => 'Failed',
                    'message' => 'Coupon has expired'
                ];
                $update_coupon = $query->update('coupons', ['status' => 'Expired'], ['ID' => $coupon['ID']]);
            }else{
                /**
                 * Phase 3.2 -- redemption is locked per user. The "has this
                 * user already used this coupon?" check above and the credit
                 * here were separate statements, so two simultaneous redeems
                 * of the same code both saw no prior use and both paid out.
                 */
                $pdo->beginTransaction();

                try {
                    $wallet = wallet_for_update($pdo, $userID);

                    if ($wallet === null) {
                        $pdo->rollBack();
                        $fileGetContent->send_content(['status' => 'Failed', 'message' => 'Wallet not found.']);
                        exit;
                    }

                    // Re-check inside the lock: the first request to arrive
                    // commits its orders row, and the second now sees it.
                    $alreadyUsed = $query->select('orders', '*', ['type' => 'coupon', 'userID' => $userID, 'prodID' => $coupon['ID']]);

                    if (count($alreadyUsed) > 0) {
                        $pdo->rollBack();

                        $response = [
                            'status' => 'Failed',
                            'message' => 'Coupon already used by this user'
                        ];
                    } else {
                        $balance = money($wallet['balance']);
                        $reward = money($coupon['amount']);
                        $balance += $reward;
                        $update_wallet = $query->update('wallets', ['balance' => money_str($balance)], ['userID' => $userID]);
                        $insert_order = $query->insert('orders', ['userID' => $userID, 'prodID' => $coupon['ID'], 'type' => 'coupon', 'amount' => money_str($reward)]);
                        $insert_transaction = $query->insert('transactions', [
                        'userID'      => $userID,
                        'type'        => 'Bonus',
                        'amount'      => money_str($reward),
                        'description' => 'Coupon code ' . $coupon_code . ' applied. Bonus of ' . money_str($reward) . ' has been credited to your wallet.',
                        'status'      => 'Completed'
                        ]);

                        $pdo->commit();

                        $response = [
                            'status' => 'Success',
                            'message' => 'Coupon applied successfully',
                            'balance' => money_str($balance)
                        ];
                    }
                } catch (\Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    error_log('[coupon] ' . $e->getMessage());

                    $response = [
                        'status' => 'Failed',
                        'message' => 'Could not apply the coupon. Please try again.'
                    ];
                }
            }
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
            'message' => 'Some parameters are missing'
        ];
    $fileGetContent->send_content($response);
}