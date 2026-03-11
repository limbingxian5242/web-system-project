<?php
session_start();

include '../inc/db.inc.php';
$conn = getDBConnection();
if (!$conn) {
    error_log("Database connection failed in update_quantity.php");
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    error_log("User not authenticated in update_quantity.php");
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "User not authenticated"]);
    exit;
}

$user_id = $_SESSION['user_id'];
error_log("Processing quantity update for user: " . $user_id);

// Validate input
if (!isset($_POST['product_id']) || !isset($_POST['quantity'])) {
    error_log("Missing required parameters in update_quantity.php");
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing required parameters"]);
    exit;
}

$product_id = intval($_POST['product_id']);
$quantity = intval($_POST['quantity']);
error_log("Updating product_id: " . $product_id . " to quantity: " . $quantity);

if ($quantity <= 0) {
    error_log("Invalid quantity: " . $quantity);
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid quantity"]);
    exit;
}

// Start transaction
$conn->begin_transaction();

try {
    // Check stock availability
    $stock_sql = "SELECT stock FROM products WHERE id = ? FOR UPDATE";
    $stock_stmt = $conn->prepare($stock_sql);
    $stock_stmt->bind_param("i", $product_id);
    $stock_stmt->execute();
    $stock_result = $stock_stmt->get_result();
    
    if ($stock_result->num_rows === 0) {
        throw new Exception("Product not found");
    }
    
    $stock = $stock_result->fetch_assoc()['stock'];
    if ($quantity > $stock) {
        throw new Exception("Not enough stock available", $stock);
    }
    
    // Update or insert cart item
    $check_sql = "SELECT id FROM cart_items WHERE user_id = ? AND product_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $user_id, $product_id);
    $check_stmt->execute();
    $exists = $check_stmt->get_result()->num_rows > 0;
    
    if ($exists) {
        $update_sql = "UPDATE cart_items SET quantity = ? WHERE user_id = ? AND product_id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("iii", $quantity, $user_id, $product_id);
    } else {
        $insert_sql = "INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("iii", $user_id, $product_id, $quantity);
    }
    
    if (!$stmt->execute()) {
        error_log("Failed to execute statement in update_quantity.php: " . $stmt->error);
        throw new Exception("Failed to update cart");
    }
    
    $conn->commit();
    error_log("Successfully updated quantity for user " . $user_id . ", product " . $product_id . " to " . $quantity);
    echo json_encode(["status" => "success"]);
    
} catch (Exception $e) {
    error_log("Exception in update_quantity.php: " . $e->getMessage());
    $conn->rollback();
    http_response_code(400);
    
    $response = ["status" => "error", "message" => $e->getMessage()];
    if ($e->getMessage() === "Not enough stock available") {
        $response['available_stock'] = $e->getCode();
    }
    echo json_encode($response);
}

$stmt->close();
$check_stmt->close();
$stock_stmt->close();
?>
