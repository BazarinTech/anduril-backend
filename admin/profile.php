<?php 
include 'includes/main.php';
$error = '';
$msg = '';
if (isset($_POST['update-details'])) {
    $name= $_POST['name'];
    $uname = $_POST['username'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];

    /**
     * Phase 4.15. This wrote every field to `admins` keyed on
     * `['ID' => $adminID]` -- but $adminID is `$_SESSION['userID']`, a
     * users.ID. It only ever hit the right row when admins.ID and users.ID
     * happened to coincide, and edited an unrelated administrator otherwise.
     *
     * Name, email and phone belong on `users` (that is where login and
     * includes/main.php read them from); only the username lives on `admins`.
     */
    $query->update('users',  ['name' => $name, 'email' => $email, 'phone' => $phone], ['ID' => $adminID]);
    $query->update('admins', ['username' => $uname], ['userID' => $adminID]);

    $msg = 'Details updated successfully!';
}
if (isset($_POST['update-password'])) {
    $old_pass= $_POST['old-pass'];
    $new_pass = $_POST['new-pass'];
    $con_pass = $_POST['con-pass'];
    // Phase 2.1/2.2. Two things were wrong here: the comparison was plaintext,
    // and the new password was written to `admins.passwrd` while the old one
    // was read from `users.passwrd`. Since admin/login.php authenticates
    // against `users`, changing your admin password had no effect at all.
    // `admins.passwrd` is vestigial -- nothing authenticates against it.
    if (password_verify($old_pass, $admin_pass)) {
        if ($new_pass == $con_pass) {
            if (strlen($new_pass) > 7) {
                $query->update('users', ['passwrd' => password_hash($new_pass, PASSWORD_DEFAULT)], ['ID' => $adminID]);
                session_regenerate_id(true);
                $msg = 'Password updated successfully!';
            }else{
                $error = 'Password must be at least 8 characters long';
            }
        }else{
            $error = 'Passwords do not match';
        }
    }else{
        $error = 'Old password is incorrect';
    }
    
}
?>
<!DOCTYPE html>
<html lang="en"  :dir="$store.app.direction" x-data="{ direction: $store.app.direction || 'ltr' }" x-bind:dir="direction" class="group/item" :data-mode="$store.app.mode" :data-sidebar="$store.app.sidebarMode">


<!-- Mirrored from srbthemes.kcubeinfotech.com/sliced-pro/html/blank.html by HTTrack Website Copier/3.x [XR&CO'2014], Mon, 10 Mar 2025 01:28:49 GMT -->
<head>

    <meta charset="utf-8">
    <title>Profile</title>
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
<div class="bg-slate-50 dark:bg-dark">

    <!-- Start detached bg -->
    <div class="bg-[url('../images/bg-main.png')] bg-slate-800 group-data-[sidebar=dark]/item:bg-darklight group-data-[sidebar=brand]/item:bg-sky-500 min-h-[220px] sm:min-h-[250px] bg-bottom fixed hidden w-full -z-50 detached-img"></div>
    <!-- End detached bg -->

    <!-- Start Menu Sidebar Olverlay -->
    <div x-cloak class="fixed inset-0 z-10 bg-black/60 dark:bg-dark/90 lg:hidden" :class="{'hidden' : !$store.app.sidebar}" @click="$store.app.toggleSidebar()"></div>
    <!-- End Menu Sidebar Olverlay -->

    <!-- Start Main Content -->
    <div class="flex mx-auto main-container">

        <!-- Start Sidebar -->
        <?php require('includes/sidebar.php')?>
        <!-- End sidebar -->

        <!-- Start Content Area -->
        <div class="flex-1 main-content">

            <!-- Start Topbar -->
            <?php require('includes/topbar.php')?>
            <!-- End Topbar --> 

            <!-- Start Content -->
            <div class="h-[calc(100vh-60px)] relative overflow-y-auto overflow-x-hidden p-4 space-y-4 detached-content">
                <nav class="w-full">
                    <ul class="space-y-2 detached-breadcrumb">
                        <li class="text-xs dark:text-white/80">Users</li>
                        <li class="text-xl font-semibold text-slate-800 dark:text-slate-100">Account Settings</li>
                    </ul>
                </nav>                
                <!-- Start All Card -->
                <div class="flex flex-col gap-4 min-h-[calc(100vh-212px)]">
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <div class="card">
                            <h2 class="mb-4 text-base font-semibold capitalize text-slate-800 dark:text-slate-100">Profile Details</h2>
                            <form action='profile' method='post' class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div class="space-y-2 md:col-span-2">
                                    <label>Full Name</label>
                                    <input type="text" name='name' class="form-input" placeholder="Name" value="<?=$admin_name?>" required>
                                </div>
                                <div class="space-y-2 md:col-span-2">
                                    <label>Username</label>
                                    <input type="text" name='username' class="form-input" placeholder="username" value="<?=$admin_uname?>" required>
                                </div>
                                <div class="space-y-2 md:col-span-2">
                                    <label>Your email</label>
                                    <input type="email" name='email' class="form-input" placeholder="Email" value="<?=$admin_email?>" required>
                                </div>
                                <div class="space-y-2 md:col-span-2">
                                    <label>Role</label>
                                    <input type="text" class="form-input" name='role' placeholder="Profession" value="<?=$admin_role?>" readonly>
                                </div>
                                <div class="space-y-2 md:col-span-2">
                                    <label>Location</label>
                                    <input type="text" class="form-input" placeholder="Location" value="Kenya" required>
                                </div>
                                <div class="space-y-2 md:col-span-2">
                                    <label>Phone</label>
                                    <input type="tel" class="form-input" name='phone' placeholder="Phone" value="<?=$admin_phone?>" required>
                                </div>
                                <div class="flex flex-wrap gap-3">
                                    <button name="update-details" class="btn bg-purple border-purple text-white hover:bg-purple/[0.85] hover:border-purple/[0.85]">Save</button>
                                </div>
                            </form>
                        </div>
                        <div class="card">
                            <h2 class="mb-4 text-base font-semibold capitalize text-slate-800 dark:text-slate-100">Your Photo</h2>
                            <form action='profile' method='post' class="grid grid-cols-1 gap-4">
                                <div class="flex items-center gap-4">
                                    <img src="assets/images/user.png" class="w-16 h-16 rounded-full" alt="">
                                    <div>
                                        <h5 class="text-lg font-bold dark:text-white"><?=$admin_name?></h5>
                                        <p class="text-muted mt-0.5 dark:text-darkmuted"><?=$admin_role?></p>
                                    </div>
                                </div>
                                <div id="FileUpload" class="relative block w-full p-4 border-2 border-dashed rounded appearance-none cursor-pointer border-primary bg-light/20 dark:bg-white/5 dark:border-darkborder">
                                    <input type="file" accept="images/*" class="absolute inset-0 z-50 w-full h-full p-0 m-0 outline-none opacity-0 cursor-pointer">
                                    <div class="flex flex-col items-center justify-center space-y-3 dark:text-darkmuted">
                                        <span class="flex items-center justify-center rounded-full h-14 w-14 bg-light/50 dark:bg-white/10 dark:text-white">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="inline-block w-5 h-5">
                                                <path fill="currentColor" d="M4 19H20V12H22V20C22 20.5523 21.5523 21 21 21H3C2.44772 21 2 20.5523 2 20V12H4V19ZM13 9V16H11V9H6L12 3L18 9H13Z"></path>
                                            </svg>
                                        </span>
                                        <p><span class="text-purple">Click to upload</span> or drag and drop</p>
                                        <p>SVG, PNG, JPG or GIF</p>
                                        <p>(max, 100 X 100px)</p>
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-3">
                                    <button name="update-profile" class="btn bg-purple border-purple text-white hover:bg-purple/[0.85] hover:border-purple/[0.85]">Save</button>
                                </div>
                            </form>
                        </div>
                        <div class="card">
                            <h2 class="mb-4 text-base font-semibold capitalize text-slate-800 dark:text-slate-100">Change Password</h2>
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
                            <form action='profile' method='post' class="space-y-4">
                                <div class="space-y-2">
                                    <label>Current Password</label>
                                    <input name='old-pass' type="password" class="form-input" placeholder="Current Password" required="">
                                </div>
                                <div class="space-y-2">
                                    <label>New Password</label>
                                    <input name='new-pass' type="password" class="form-input" placeholder="New Password" required="">
                                </div>
                                <div class="space-y-2">
                                    <label>Confirm Password</label>
                                    <input type="password" name='con-pass' class="form-input" placeholder="Confirm Password" required="">
                                </div>
                                <div class="flex flex-wrap gap-3">
                                    <button name="update-password" class="btn bg-purple border-purple text-white hover:bg-purple/[0.85] hover:border-purple/[0.85]">Save</button>
                                </div>
                            </div>
                        </form>
                        <div class="card">
                            <h2 class="mb-4 text-base font-semibold capitalize text-slate-800 dark:text-slate-100">Notifications</h2>
                            <div class="space-y-4">
                                <div class="flex items-center justify-between gap-4">
                                    Email Notifications
                                    <div class="inline-block togglebutton">
                                        <label for="toggleD2" class="flex items-center cursor-pointer">
                                            <div class="relative">
                                                <input type="checkbox" id="toggleD2" class="sr-only">
                                                <div class="block band bg-black/10 w-[54px] h-[29px] rounded-full dark:bg-dark"></div>
                                                <div class="dot absolute left-[3px] top-[2px] bg-white w-6 h-6 rounded-full transition"></div>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between gap-4">
                                    Billing Updates
                                    <div class="inline-block togglebutton">
                                        <label for="toggleD3" class="flex items-center cursor-pointer">
                                            <div class="relative">
                                                <input type="checkbox" id="toggleD3" class="sr-only" checked>
                                                <div class="block band bg-black/10 w-[54px] h-[29px] rounded-full dark:bg-dark"></div>
                                                <div class="dot absolute left-[3px] top-[2px] bg-white w-6 h-6 rounded-full transition"></div>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between gap-4">
                                    API Access
                                    <div class="inline-block togglebutton">
                                        <label for="toggleD4" class="flex items-center cursor-pointer">
                                            <div class="relative">
                                                <input type="checkbox" id="toggleD4" class="sr-only">
                                                <div class="block band bg-black/10 w-[54px] h-[29px] rounded-full dark:bg-dark"></div>
                                                <div class="dot absolute left-[3px] top-[2px] bg-white w-6 h-6 rounded-full transition"></div>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between gap-4">
                                    Newsletters
                                    <div class="inline-block togglebutton">
                                        <label for="toggleD5" class="flex items-center cursor-pointer">
                                            <div class="relative">
                                                <input type="checkbox" id="toggleD5" class="sr-only" checked>
                                                <div class="block band bg-black/10 w-[54px] h-[29px] rounded-full dark:bg-dark"></div>
                                                <div class="dot absolute left-[3px] top-[2px] bg-white w-6 h-6 rounded-full transition"></div>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- End All Card -->

                <!-- Start Footer -->
                <?php require('includes/footer.php')?>
                <!-- End Footer -->  

                </div>
        </div>
    </div>
</div>

<script  src="assets/libs/%40alpinejs/persist/cdn.min.js"></script>
<script  src="assets/libs/%40alpinejs/collapse/cdn.min.js"></script>
<script  src="assets/libs/feather-icons/feather.min.js"></script>


</body>


<!-- Mirrored from srbthemes.kcubeinfotech.com/sliced-pro/html/blank.html by HTTrack Website Copier/3.x [XR&CO'2014], Mon, 10 Mar 2025 01:28:49 GMT -->
</html>