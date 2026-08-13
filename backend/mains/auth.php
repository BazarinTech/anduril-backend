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

                    // Insert into users tables
                    $insert_users = $query->insert('users', ['email' => $email, 'passwrd' => $password, 'phone' => $phone, 'upline' => $upline, 'name' => $name, 'country' => $country]);

                    // Sleep for 2 seconds then get userID which is used in both wallets insertion
                    sleep(2);
                    $user = $query->select('users', '*', ['phone' => $phone]);
                    $userID = $user[0]['ID'];
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
        $auth = $query->auth('users', $phone, $password);

        if ($auth['Status'] == 'Success') {

            $response = [
                'status' => $auth['Status'],
                'message' => $auth['Message'],
                'userID' => $auth['Data']['ID'],
                'error' => [],
            ];
        }else{
            $response = [
                'status' => $auth['Status'],
                'message' => $auth['Message'],
                'userID' => 0,
                'error' => []
            ];
        }
    }
    $fileGetContent->send_content($response);
}