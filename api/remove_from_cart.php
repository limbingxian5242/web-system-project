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

// Validate input
if (!isset($_POST['product_id'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing product_id parameter"]);
    exit;
}

$user_id = $_SESSION['user_id'];
$product_id = intval($_POST['product_id']);

// Delete specific product from cart
$stmt = $conn->prepare("DELETE FROM cart_items WHERE user_id = ? AND product_id = ?");
$stmt->bind_param("ii", $user_id, $product_id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode(["status" => "success", "message" => "Item removed from cart"]);
} else {
    echo json_encode(["status" => "error", "message" => "Item not found in cart"]);
}

$stmt->close();
?>
