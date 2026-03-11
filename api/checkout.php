<?php
session_start();
include '../inc/db.inc.php';

$conn = getDBConnection();
if (!$conn) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "User not authenticated"]);
    exit;
}

$user_id = $_SESSION['user_id'];

// Start transaction
$conn->begin_transaction();

try {
    // Get cart items with stock information
    $sql = "SELECT c.product_id, c.quantity, p.stock, p.price, p.name 
            FROM cart_items c 
            JOIN products p ON c.product_id = p.id 
            WHERE c.user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $cart_items = [];
    while ($row = $result->fetch_assoc()) {
        $cart_items[] = $row;
    }
    
    if (empty($cart_items)) {
        throw new Exception("Cart is empty");
    }
    
    // Check stock and calculate total
    $total = 0;
    foreach ($cart_items as $item) {
        if ($item['quantity'] > $item['stock']) {
            throw new Exception("Not enough stock for {$item['name']}");
        }
        $total += $item['price'] * $item['quantity'];
    }
    
    // Update stock and create order record
    foreach ($cart_items as $item) {
        // Update stock
        $new_stock = $item['stock'] - $item['quantity'];
        $update_sql = "UPDATE products SET stock = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ii", $new_stock, $item['product_id']);
        $update_stmt->execute();
        $update_stmt->close();
        
        // Create order record
        $order_sql = "INSERT INTO orders (user_id, product_id, quantity, price) VALUES (?, ?, ?, ?)";
        $order_stmt = $conn->prepare($order_sql);
        $order_stmt->bind_param("iiid", $user_id, $item['product_id'], $item['quantity'], $item['price']);
        $order_stmt->execute();
        $order_stmt->close();
    }
    
    // Clear cart
    $clear_sql = "DELETE FROM cart_items WHERE user_id = ?";
    $clear_stmt = $conn->prepare($clear_sql);
    $clear_stmt->bind_param("i", $user_id);
    $clear_stmt->execute();
    $clear_stmt->close();
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        "status" => "success",
        "message" => "Order placed successfully",
        "total" => $total
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
?>


