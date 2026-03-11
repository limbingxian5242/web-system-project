<!DOCTYPE html>
<html lang="en">

<head>
    <?php
    include "inc/head.inc.php";
    ?>
</head>

<body>
    </div>
    <?php
    include "inc/navbar.inc.php";
    include "inc/security.inc.php";
    ?>
    <hr>
    <main class="container">
        <?php
        $errorMsg = "";

        require __DIR__ . '/vendor/autoload.php';

        $config = parse_ini_file('/var/www/private/db-config.ini');
        $dbSchema = 'project';
        $db = new \PDO("mysql:dbname={$config['dbname']};host={$config['servername']};charset=utf8", "{$config['username']}", "{$config['password']}");

        $auth = new \Delight\Auth\Auth($db, null, null, null, null, $dbSchema);
        
        // Sanitize inputs
        $email = sanitize_input($_POST['email'] ?? '');
        $password = $_POST['password'] ?? ''; // Don't sanitize passwords
        $remember = isset($_POST['remember']) ? (int)$_POST['remember'] : 0;
        
        if ($remember == 1) {
            // keep logged in for one week
            $rememberDuration = (int) (60 * 60 * 24 * 7);
        }
        else {
            $rememberDuration = null;
        }
        
        try {
            $auth->login($email, $password, $rememberDuration);
            
            // Start session if not already started
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            
            // Set the user ID in the session
            $_SESSION['user_id'] = $auth->getUserId();
            error_log("User logged in successfully. Set user_id in session: " . $_SESSION['user_id']);
        
            // Check if there's a redirect parameter in the session
            $redirect = isset($_SESSION['redirect']) ? $_SESSION['redirect'] : null;
            
            // Also check if there was a redirect parameter in the URL when accessing the login page
            if (isset($_POST['redirect'])) {
                $redirect = sanitize_input($_POST['redirect']);
            }
            
            // Redirect based on the parameter
            if ($redirect === 'checkout') {
                header('Location: checkout.php');
            } else {
                header('Location: profile.php');
            }
            exit;
        }
        catch (\Delight\Auth\InvalidEmailException $e) {
            $errorMsg = 'Wrong email address';
        }
        catch (\Delight\Auth\InvalidPasswordException $e) {
            $errorMsg = 'Wrong password';
        }
        catch (\Delight\Auth\EmailNotVerifiedException $e) {
            $errorMsg = 'Email not verified';
        }
        catch (\Delight\Auth\TooManyRequestsException $e) {
            $errorMsg = 'Too many requests';
        }

        // Display error message safely
        if (!empty($errorMsg)) {
            echo '<div class="alert alert-danger">' . h($errorMsg) . '</div>';
            echo '<p><a href="login.php" class="btn btn-primary">Try Again</a></p>';
        }
        ?>
    </main>
</body>