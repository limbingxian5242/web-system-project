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
        $username = sanitize_input($_POST['username'] ?? '');
        $password = $_POST['password'] ?? ''; // Don't sanitize passwords
        $confirmPassword = $_POST['confirm_password'] ?? ''; // Don't sanitize passwords
        
        //check confirm password
        if ($password != $confirmPassword) {
            $errorMsg = "Password and Confirm Password do not match";
            echo '<div class="alert alert-danger">' . h($errorMsg) . '</div>';
            echo '<p><a href="register.php" class="btn btn-primary">Try Again</a></p>';
            exit();
        }
        
        try {
            $userId = $auth->register($email, $password, $username);
        
            echo '<div class="alert alert-success">Registration successful! Welcome to our site.</div>';
            
            //Auto login user
            $auth->login($email, $password);
            
            // Start session if not already started
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            
            // Set the user ID in the session
            $_SESSION['user_id'] = $auth->getUserId();
            error_log("User registered and logged in successfully. Set user_id in session: " . $_SESSION['user_id']);
            
            header('Location: index.php');
        }
        catch (\Delight\Auth\InvalidEmailException $e) {
            $errorMsg = 'Invalid email address';
        }
        catch (\Delight\Auth\InvalidPasswordException $e) {
            $errorMsg = 'Invalid password';
        }
        catch (\Delight\Auth\UserAlreadyExistsException $e) {
            $errorMsg = 'User already exists';
        }
        catch (\Delight\Auth\TooManyRequestsException $e) {
            $errorMsg = 'Too many requests';
        }

        // Display error message safely
        if (!empty($errorMsg)) {
            echo '<div class="alert alert-danger">' . h($errorMsg) . '</div>';
            echo '<p><a href="register.php" class="btn btn-primary">Try Again</a></p>';
        }
        ?>
    </main>
</body>
