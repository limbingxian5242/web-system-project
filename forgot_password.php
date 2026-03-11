<!DOCTYPE html>
<html lang="en">

<head>
    <?php
    include "inc/head.inc.php";
    ?>
</head>

<body>
<header class="site-header">
    <div class="header-bg">
        <img src="images/placeholder.jpg" alt="Header background" class="header-bg-img">
    </div>
    <?php
    include "inc/navbar.inc.php";
    ?>
    <div class="container d-flex justify-content-center align-items-center" style="height: 80svh;">
        <div class="card p-4" id="login-form" style="width: 400px;">
            <h3 class="text-center mb-4">Reset Password</h3>
            <form action="process_forgot_password.php" method="POST">
                <div class="form-group mb-3">
                    <label for="username">Username</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
                <div class="form-group mb-3">
                    <label for="email">Email address</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
                <div class="form-group mb-3">
                    <label for="password">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <div class="form-group mb-3">
                    <label for="password">Confirm Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-dark w-100">Reset Password</button>
            </form>
            <div class="d-flex justify-content-center mt-3">
                <a href="login.php" class="text-dark mx-2">Already have an account? Sign in</a>
            </div>
        </div>
    </div>
</header>
    
</body>