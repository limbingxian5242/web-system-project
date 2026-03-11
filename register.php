<!DOCTYPE html>
<html lang="en">

<head>
    <?php
    include "inc/head.inc.php";
    ?>
</head>

<body class="register-page">
    <header class="site-header">
        <div class="header-bg">
            <img src="images/placeholder.jpg" alt="Header background" class="header-bg-img">
        </div>
        <?php
        include "inc/navbar.inc.php";
        ?>
        <div class="container d-flex justify-content-center align-items-center" style="height: 80svh;">
            <div class="card p-4" id="login-form" style="width: 400px;">
                <h3 class="text-center mb-4">Register</h3>
                <form action="process_register.php" method="POST">
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
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                            required>
                        <div id="password-match-feedback" class="invalid-feedback" style="display: none;">
                            Passwords do not match
                        </div>
                    </div>

                    <?php if (isset($_GET['error']) && $_GET['error'] === 'password_mismatch'): ?>
                        <div class="alert alert-danger mb-3">
                            Passwords did not match. Please try again.
                        </div>
                    <?php endif; ?>

                    <button type="submit" class="btn btn-dark w-100">Register</button>
                </form>
                <div class="d-flex justify-content-center mt-3">
                    <a href="login.php" class="text-dark mx-2">Already have an account? Sign in</a>
                </div>
            </div>
        </div>
    </header>


<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm_password');
    const feedback = document.getElementById('password-match-feedback');
    
    function validatePassword() {
        if (password.value !== confirmPassword.value) {
            confirmPassword.classList.add('is-invalid');
            feedback.style.display = 'block';
            return false;
        } else {
            confirmPassword.classList.remove('is-invalid');
            feedback.style.display = 'none';
            return true;
        }
    }
    
    form.addEventListener('submit', function(e) {
        if (!validatePassword()) {
            e.preventDefault();
        }
    });
    
    confirmPassword.addEventListener('input', validatePassword);
    password.addEventListener('input', validatePassword);
});
</script>
</body>