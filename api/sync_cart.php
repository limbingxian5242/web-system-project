<?php
session_start();
include '../inc/db.inc.php';
$conn = getDBConnection();
if (!$conn) {
    error_log("Database connection failed in sync_cart.php");
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "DB connection failed"]);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    error_log("User not logged in when accessing sync_cart.php");
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Not logged in"]);
    exit;
}

$user_id = $_SESSION['user_id'];
error_log("Syncing cart for user: " . $user_id);

$sql = "SELECT p.id, p.name, p.price, p.image as image_url, c.quantity
        FROM cart_items c
        JOIN products p ON c.product_id = p.id
        WHERE c.user_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$cart = [];
while ($row = $result->fetch_assoc()) {
    $cart[] = $row;
}

echo json_encode(["status" => "success", "cart" => $cart]);
