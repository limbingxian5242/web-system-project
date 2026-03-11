<?php
session_start();
include '../inc/db.inc.php';
$conn = getDBConnection();
if (!$conn) { die("DB connection failed."); }




$user_id = $_SESSION['user_id'];
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

echo json_encode($cart);
