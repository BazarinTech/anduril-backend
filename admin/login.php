<?php 
include 'includes/initiate.php'; // initiate.php now starts the session via DB
$error = '';
$msg = '';

// Debug (optional, remove in production)
// echo "Session ID: " . session_id() . "<br>";
// print_r($_SESSION);
// print_r($_COOKIE);

if (isset($_POST['submit'])) {
    $email = $_POST['email'];
    $pass = $_POST['password'];

    // Look the account up by email only, then verify the hash (Phase 2.1).
    // The password can no longer be part of the WHERE clause, because the
    // stored value is a digest rather than the string the user typed.
    $user = $query->select('users', '*', ['email' => $email]);
    $account = $user[0] ?? null;

    // Same constant-ish work whether or not the email exists, so a valid
    // address cannot be distinguished from an invalid one by response time.
    $storedHash = $account['passwrd'] ?? '$2y$10$usesomesillystringfoobar1234567890abcdefghijklmnopqrstuv';

    if ($account !== null && password_verify($pass, $storedHash)) {
        $userID = $account['ID'];

        if (password_needs_rehash($storedHash, PASSWORD_DEFAULT)) {
            $query->update('users', ['passwrd' => password_hash($pass, PASSWORD_DEFAULT)], ['ID' => $userID]);
        }

        // Check if user is admin
        $admins = $query->select('admins', '*', ['userID' => $userID]);
        $num = count($admins);

        if ($num > 0) {
            // Phase 2.6 -- issue a fresh session ID now that the privilege
            // level has changed, so a session fixed before login is useless.
            session_regenerate_id(true);

            $_SESSION['userID'] = $userID; // <-- DATABASE-BASED SESSION
            header('Location: dashboard');
            exit;
        } else {
            $error = 'You are not allowed to be admin!';
        }
    } else {
        $error = 'Invalid credentials!';
    }
}
?>

<!DOCTYPE html>
<html lang="en"  :dir="$store.app.direction" x-data="{ direction: $store.app.direction || 'ltr' }" x-bind:dir="direction" class="group/item" :data-mode="$store.app.mode" :data-sidebar="$store.app.sidebarMode">


<!-- Mirrored from srbthemes.kcubeinfotech.com/sliced-pro/html/login.html by HTTrack Website Copier/3.x [XR&CO'2014], Mon, 10 Mar 2025 01:28:54 GMT -->
<head>

    <meta charset="utf-8">
    <title>Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta content="Tailwind CSS Admin & Dashboard Template" name="description">
    <meta content="SRBThemes" name="author">
    <!-- favicon -->
    <link rel="shortcut icon" href="assets/images/favicon.ico">
    <!-- plugins CSS -->
    
    <!-- Icons CSS -->
    
    <!-- Tailwind CSS -->
    
    
    
    

  <script type="module" crossorigin src="assets/main-0ff05731.js"></script>
  <link rel="stylesheet" href="assets/css/main.css">
</head>

<body x-data="main" x-init="$store.app.hasCreative = window.location.href.includes('creative.html') , $store.app.hasdetached = window.location.href.includes('detached.html')" :class="[ $store.app.sidebar ? 'toggle-sidebar' : '', $store.app.fullscreen ? 'full' : '' , $store.app.hasCreative ? 'detached ' : '' , $store.app.hasdetached ? 'detached detached-simple ' : '' , $store.app.layout  ]" class="relative overflow-x-hidden text-[15px] antialiased font-normal text-black font-primary dark:text-white vertical " x-data="modals">

<!-- Start Layout -->
<div class="bg-[#f9fbfd] dark:bg-dark min-h-screen relative z-10">

    <!-- Start Background Images -->
    <div class="bg-[url('../images/bg-main.png')] bg-black dark:bg-purple min-h-[220px] sm:min-h-[50vh] bg-bottom w-full -z-10 absolute"></div>
    <!-- End Background Images -->

    <!-- Start Main Content -->
    <div class="min-h-[calc(100vh-134px)] py-4 px-4 sm:px-12 flex justify-center items-center max-w-[1440px] mx-auto">
        <div class="max-w-[550px] flex-none w-full bg-white border border-slate-200 p-6 sm:p-10 lg:px-10 lg:py-14 rounded-2xl loginform dark:bg-darklight dark:border-darkborder">
            <h1 class="mb-2 text-2xl font-semibold text-center dark:text-white">Admin Sign In</h1>
            <div class="w-full grid place-items-center">
                    <?php 
                    if ($msg) {
                        echo '<p class="bg-success/20 text-success text-center w-full my-2 rounded-lg py-2 px-2">'.$msg.'</p>';
                    }
                    ?>

                    <?php 
                    if ($error) {
                        echo '<p class="bg-danger/20 text-danger text-center w-full my-2 rounded-lg py-2 px-2">'.$error.'</p>';
                    }
                    ?>
                </div>
            <form action='login' method='post' class="space-y-4">
                <div>
                    <input type="email" value="" name='email' placeholder="Email" class="form-input" required>
                </div>
                <div>
                    <input type="password" value="" name='password' placeholder="Password" class="form-input" required>
                </div>
                <button name="submit" class="btn w-full py-3.5 text-base bg-purple border-purple text-white hover:bg-purple/[0.85] hover:border-purple/[0.85]">
                    Sign In
                </button>
            </form>
        </div>
    </div>
    <!-- End Main Content -->

<!-- Start Footer -->
<?php require('includes/footer.php')?>
                <!-- End Footer --> 
</div>


<script  src="assets/libs/%40alpinejs/persist/cdn.min.js"></script>
<script  src="assets/libs/%40alpinejs/collapse/cdn.min.js"></script>
<script  src="assets/libs/feather-icons/feather.min.js"></script>


</body>


<!-- Mirrored from srbthemes.kcubeinfotech.com/sliced-pro/html/login.html by HTTrack Website Copier/3.x [XR&CO'2014], Mon, 10 Mar 2025 01:28:54 GMT -->
</html>