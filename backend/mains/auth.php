<?php
include '../includes/initiate.php';
include 'send-email.php';

// Get posted json data
$data = $fileGetContent->get_content();

//process authentication
if (isset($data)) {
    $phone = $data['phone'];
    $type = $data['type'];
    $password = $data['password'];

    // Check if type of auth request is either login or register
    if ($type == 'register') {
        $con_password = $data['confirmPassword'];
        $email = $data['email'];
        $upline = $data['upline'];
        $name = $data['name'];
        // $country = $data['country'];
        $country = '254'; // Default country code Kenya

        // Check if password matches with confirm
        if ($con_password === $password) {

            // Check if password is greater than or equal to 6 characters
            if (strlen($password) >= 6) {
                $count = count($query->selectOR('users', '*', ['phone' => $phone]));

                // Check if the account exists
                if ($count === 0) {

                    // Insert into users tables.
                    // The password is hashed here and never stored in the clear
                    // (Phase 2.1); password_verify below is the only way back.
                    $insert_users = $query->insert('users', ['email' => $email, 'passwrd' => password_hash($password, PASSWORD_DEFAULT), 'phone' => $phone, 'upline' => $upline, 'name' => $name, 'country' => $country]);

                    // insert() returns lastInsertId(), so the new ID is already
                    // in hand. This used to sleep(2) and re-query by phone,
                    // which cost every registration two seconds and would pick
                    // the wrong row if two people ever shared a number.
                    $userID = $insert_users;
                    $insert_wallet = $query->insert('wallets', ['userID' => $userID]);
                    $body = registration_template($name);
                    $email_res = send_email($email, 'Welcome To Sanderson Farm', $body);
                    $response = [
                        'status' => 'Success',
                        'message' => 'Registration successfull, please wait for redirection',
                        'userID' => $userID,
                        'error' => []
                    ];
                }else{
                    $response = [
                        'status' => 'Failed',
                        'message' => 'User seems to exist. Kindly use diffrent phone number!',
                        'error' => [
                            [
                                'input' => 'email'
                            ],
                            [
                                'input' => 'phone'
                            ]
                        ]
                    ];
                }
            }else{
                $response = [
                    'status' => 'Failed',
                    'message' => 'Password characters should not be less than 6 characters',
                    'error' => [
                        [
                            'input' => 'password'
                        ]
                    ]
                ];
            }
        }else{
            $response = [
                'status' => 'Failed',
                'message' => 'Password and confirmation mismatch',
                'error' => [
                    [
                        'input' => 'password'
                    ],
                    [
                        'input' => 'con_password'
                    ]
                ]
            ];
        }
    }elseif ($type === 'login') {
        // Authentication lives here rather than in QueryBuilder::auth(), which
        // compared plaintext with == . That method is now unused by this
        // project; see docs/AUTH.md for why the vendored copies differ.
        $users = $query->select('users', '*', ['phone' => $phone]);
        $user  = $users[0] ?? null;

        // Hash an empty candidate when the account does not exist, so a missing
        // phone number and a wrong password take the same time to answer and
        // cannot be told apart by timing.
        $storedHash = $user['passwrd'] ?? '$2y$10$usesomesillystringfoobar1234567890abcdefghijklmnopqrstuv';

        if ($user !== null && password_verify($password, $storedHash)) {

            // Opportunistically upgrade the stored digest if PHP's default
            // cost or algorithm has moved on since it was written.
            if (password_needs_rehash($storedHash, PASSWORD_DEFAULT)) {
                $query->update('users', ['passwrd' => password_hash($password, PASSWORD_DEFAULT)], ['ID' => $user['ID']]);
            }

            $response = [
                'status' => 'Success',
                'message' => 'Authentication Successful. Please wait for redirection',
                'userID' => $user['ID'],
                'error' => [],
            ];
        }else{
            $response = [
                'status' => 'Failed',
                'message' => 'Invalid Credentials',
                'userID' => 0,
                'error' => []
            ];
        }
    }else{
        $response = [
            'status' => 'Failed',
            'message' => 'Unknown authentication type',
            'userID' => 0,
            'error' => []
        ];
    }
    $fileGetContent->send_content($response);
}