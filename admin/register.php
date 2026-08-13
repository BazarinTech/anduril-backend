<?php 
include 'includes/initiate.php';
$error = '';
$msg = '';
if (isset($_POST['submit'])) {
    $name= $_POST['name'];
    $email = $_POST['email'];
    $uname = $_POST['uname'];
    $pass = $_POST['password'];
    $con_pass = $_POST['con-password'];

    //check if username exists
    $admins = $query->select('admins', '*', ['username' => $uname]);
    $num = count($admins);
    if ($num > 0) {
        if ($con_pass === $pass) {
          if (strlen($pass) > 7) {
            $update_admin = $query->update('admins', ['name' => $name, 'email' => $email, 'passwrd' => $pass], ['username' => $uname]);
            header('Location: login');
          }else{
            $error = "Password must be at least 8 characters long.";
          }
        }else{
            $error = "Passwords do not match";
        }
    }else{
        $error = 'Invalid username for admin invite!';
    }
}
?>
<!DOCTYPE html>
<html lang="en"  :dir="$store.app.direction" x-data="{ direction: $store.app.direction || 'ltr' }" x-bind:dir="direction" class="group/item" :data-mode="$store.app.mode" :data-sidebar="$store.app.sidebarMode">


<!-- Mirrored from srbthemes.kcubeinfotech.com/sliced-pro/html/signup.html by HTTrack Website Copier/3.x [XR&CO'2014], Mon, 10 Mar 2025 01:28:54 GMT -->
<head>

    <meta charset="utf-8">
    <title>Signup </title>
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
        <div class="max-w-[550px] flex-none w-full bg-white border border-slate-200 p-6 sm:p-10 lg:px-10 lg:py-14 rounded-2xl dark:bg-darklight dark:border-darkborder">
            <h1 class="mb-2 text-2xl font-semibold text-center dark:text-white">Sign Up</h1>
            <div class="flex items-center mb-7">
            </div>
            <form action='register' method='post' class="grid grid-cols-1 gap-4 sm:grid-cols-2">
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
                <div class="sm:col-span-2">
                    <input type="text" name='name' value="" placeholder="Full Name" class="form-input" required>
                </div>
                <div class="sm:col-span-2">
                    <input type="text" name='uname' value="" placeholder="Username" class="form-input" required>
                </div>
                <div class="sm:col-span-2">
                    <input type="email" name='email' value="" placeholder="Email" class="form-input" required>
                </div>
                <div class="sm:col-span-2">
                    <input type="password" name='password' value="" placeholder="Password" class="form-input" required>
                </div>
                <div class="sm:col-span-2">
                    <input type="password" name='con-password' value="" placeholder="Confirm Password" class="form-input" required>
                </div>
                <div class="sm:col-span-2">
                    <label class="inline-flex">
                        <input type="checkbox" class="form-checkbox outborder-purple" required>
                        <span class="text-muted">I Accept the <a href="javaScript:;" class="text-black dark:text-white">Terms and Conditions</a>.</span>
                    </label>
                </div>
                <button name="submit" class="btn sm:col-span-2 w-full py-3.5 text-base bg-purple border-purple text-white hover:bg-purple/[0.85] hover:border-purple/[0.85]">
                    Create an account
                </button>
            </form>
            <p class="mt-5 text-center text-muted">Already a member? <a href="login" class="text-black dark:text-white">Sign In</a></p>
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


<!-- Mirrored from srbthemes.kcubeinfotech.com/sliced-pro/html/signup.html by HTTrack Website Copier/3.x [XR&CO'2014], Mon, 10 Mar 2025 01:28:54 GMT -->
</html>