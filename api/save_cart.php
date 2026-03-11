<?php
session_start();
include '../inc/db.inc.php';
$conn = getDBConnection();
if (!$conn) {
    error_log("Database connection failed in save_cart.php");
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit;
}

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log("User not logged in when accessing save_cart.php");
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "User not authenticated"]);
    exit;
}

$user_id = $_SESSION['user_id'];
error_log("Saving cart for user: " . $user_id);

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['cart']) || !is_array($input['cart'])) {
    error_log("Invalid cart data received: " . file_get_contents('php://input'));
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid cart data"]);
    exit;
}

$cart = $input['cart'];
error_log("Cart items count: " . count($cart));

// Start transaction
$conn->begin_transaction();

try {
    // First, delete all existing cart items for this user
    $delete_sql = "DELETE FROM cart_items WHERE user_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $user_id);
    $delete_stmt->execute();
    $delete_stmt->close();

    if (!empty($cart)) {
        // Prepare insert statement for new items
        $insert_sql = "INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);

        // Insert all items in a single transaction
        foreach ($cart as $item) {
            if (!isset($item['id']) || !isset($item['quantity'])) {
                throw new Exception("Invalid item data");
            }
            
            $product_id = intval($item['id']);
            $quantity = intval($item['quantity']);
            
            if ($quantity <= 0) {
                continue; // Skip items with zero or negative quantity
            }
            
            $insert_stmt->bind_param("iii", $user_id, $product_id, $quantity);
            if (!$insert_stmt->execute()) {
                throw new Exception("Failed to insert cart item");
            }
        }
        $insert_stmt->close();
    }

    $conn->commit();
    echo json_encode(["status" => "success", "message" => "Cart saved successfully"]);
    
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
