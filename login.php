<!DOCTYPE html>
<html lang="en">

<head>
    <?php
    include "inc/head.inc.php";
    ?>
</head>

<body class="login-page">
<header class="site-header">
    <div class="header-bg">
        <img src="images/placeholder.jpg" alt="Header background" class="header-bg-img">
    </div>
    <?php
    include "inc/navbar.inc.php";
    ?>
    <div class="container d-flex justify-content-center align-items-center" style="height: 75svh;">
        <div class="card p-4" id="login-form" style="width: 400px;">
            <h3 class="text-center mb-4">Login</h3>
            <?php
            // Check if a redirect parameter was passed
            $redirect = isset($_GET['redirect']) ? $_GET['redirect'] : '';
            if ($redirect === 'checkout') {
                echo '<div class="alert alert-info">Please log in to complete your checkout.</div>';
            }
            ?>
            <form action="process_login.php" method="POST">
                <div class="form-group mb-3">
                    <label for="email">Email address</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
                <div class="form-group mb-3">
                    <label for="password">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <div class="form-check">
                <input class="form-check-input" type="checkbox" value="remember" id="remember" checked>
                <label class="form-check-label" for="remember">
                    Remember me
                </label>
                </div>
                <?php
                // Include the redirect parameter as a hidden field if it exists
                if (!empty($redirect)) {
                    echo '<input type="hidden" name="redirect" value="'. htmlspecialchars($redirect) .'">';
                }
                ?>
                <button type="submit" class="btn btn-dark w-100">Login</button>
            </form>
            <div class="d-flex justify-content-center mt-3">
                <a href="forgot_password.php" class="text-dark mx-2">Forgot Password?</a>
                <a href="register.php" class="text-dark mx-2">Register</a>
            </div>
        </div>
    </div>
</header>
<?php
include "inc/footer.inc.php";
?>
</body>