<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    echo json_encode([
        'status' => 'success',
        'authenticated' => true,
        'user_id' => $_SESSION['user_id']
    ]);
} else {
    echo json_encode([
        'status' => 'success',
        'authenticated' => false
    ]);
}
?> 