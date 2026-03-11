<?php
session_start();
include '../inc/db.inc.php';

header('Content-Type: application/json');

// Get connection
$conn = getDBConnection();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Handle the case where a guest is checking out
    $user_id = 0; // Guest user ID
} else {
    $user_id = $_SESSION['user_id'];
}

// Get and validate input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['order_details'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid order data']);
    exit;
}

$orderDetails = $input['order_details'];

// Begin transaction
$conn->begin_transaction();

try {
    // 1. Create order record
    $order_sql = "INSERT INTO orders (user_id, total_amount, order_date, payment_details, status) 
                 VALUES (?, ?, NOW(), ?, 'preparing')";
    $order_stmt = $conn->prepare($order_sql);
    
    // Format customer information
    $customerData = $orderDetails['customer'] ?? [];
    $shippingAddress = $customerData['shippingAddress'] ?? '';
    $billingAddress = $customerData['billingAddress'] ?? '';
    
    // Format payment details - store only last 4 digits for security
    $cardNumber = $orderDetails['payment']['cardNumber'] ?? '';
    $lastFour = substr(str_replace(' ', '', $cardNumber), -4);
    $paymentDetails = json_encode([
        'card_last4' => $lastFour,
        'expiry' => $orderDetails['payment']['expiry'] ?? '',
        'shipping_address' => $shippingAddress,
        'billing_address' => $billingAddress
    ]);
    
    $totalAmount = $orderDetails['total'] ?? 0;
    
    $order_stmt->bind_param("ids", $user_id, $totalAmount, $paymentDetails);
    $order_stmt->execute();
    $order_id = $conn->insert_id;
    $order_stmt->close();
    
    // 2. Create order items
    if (!empty($orderDetails['items'])) {
        $items_sql = "INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)";
        $items_stmt = $conn->prepare($items_sql);
        
        foreach ($orderDetails['items'] as $item) {
            $productId = $item['id'];
            $quantity = $item['quantity'];
            $price = $item['price'];
            
            $items_stmt->bind_param("iiid", $order_id, $productId, $quantity, $price);
            $items_stmt->execute();
            
            // Update product stock
            $update_stock = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
            $update_stock->bind_param("ii", $quantity, $productId);
            $update_stock->execute();
            $update_stock->close();
        }
        $items_stmt->close();
        
        // 3. Clear the user's cart if they are logged in
        if ($user_id > 0) {
            $clear_sql = "DELETE FROM cart_items WHERE user_id = ?";
            $clear_stmt = $conn->prepare($clear_sql);
            $clear_stmt->bind_param("i", $user_id);
            $clear_stmt->execute();
            $clear_stmt->close();
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    // Return success with order ID
    echo json_encode([
        'status' => 'success',
        'message' => 'Order saved successfully',
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
?> 