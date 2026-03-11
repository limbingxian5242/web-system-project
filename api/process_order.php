<?php
session_start();
include '../inc/db.inc.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'User not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$conn = getDBConnection();

if (!$conn) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

// Get and validate input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['payment_details'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request data']);
    exit;
}

// Get cart items from database
$cart_sql = "SELECT p.id, p.name, p.price, c.quantity 
             FROM cart_items c 
             JOIN products p ON c.product_id = p.id 
             WHERE c.user_id = ?";
$cart_stmt = $conn->prepare($cart_sql);
$cart_stmt->bind_param("i", $user_id);
$cart_stmt->execute();
$cart_result = $cart_stmt->get_result();

$cart_items = [];
$total_amount = 0;

while ($item = $cart_result->fetch_assoc()) {
    $cart_items[] = $item;
    $total_amount += ($item['price'] * $item['quantity']);
}

if (empty($cart_items)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Cart is empty']);
    exit;
}

// Begin transaction
$conn->begin_transaction();

try {
    // 1. Create order record
    $order_sql = "INSERT INTO orders (user_id, total_amount, order_date, payment_details, status) 
                  VALUES (?, ?, NOW(), ?, 'preparing')";
    $order_stmt = $conn->prepare($order_sql);
    
    // Store only last 4 digits of card
    $payment_details = json_encode([
        'card_last4' => $input['payment_details']['card_number'],
        'expiry' => $input['payment_details']['expiry']
    ]);
    
    $order_stmt->bind_param("ids", $user_id, $total_amount, $payment_details);
    $order_stmt->execute();
    $order_id = $conn->insert_id;
    $order_stmt->close();
    
    // 2. Create order items
    $items_sql = "INSERT INTO order_items (order_id, product_id, quantity, price) 
                  VALUES (?, ?, ?, ?)";
    $items_stmt = $conn->prepare($items_sql);
    
    foreach ($cart_items as $item) {
        $items_stmt->bind_param("iiid", $order_id, $item['id'], $item['quantity'], $item['price']);
        $items_stmt->execute();
    }
    $items_stmt->close();
    
    // 3. Clear the user's cart
    $clear_sql = "DELETE FROM cart_items WHERE user_id = ?";
    $clear_stmt = $conn->prepare($clear_sql);
    $clear_stmt->bind_param("i", $user_id);
    $clear_stmt->execute();
    $clear_stmt->close();
    
    // Commit transaction
    $conn->commit();
    
    // Return success
    echo json_encode([
        'status' => 'success', 
        'message' => 'Order processed successfully',
        'order_id' => $order_id
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Order processing failed: ' . $e->getMessage()
    ]);
} 