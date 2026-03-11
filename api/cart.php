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

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Validate input
    if (!isset($_POST["product_id"]) || !isset($_POST["quantity"])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Missing required parameters"]);
        exit;
    }

    $product_id = intval($_POST["product_id"]);
    $quantity = intval($_POST["quantity"]);

    if ($quantity <= 0) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid quantity"]);
        exit;
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // Check if item already exists in cart
        $check_sql = "SELECT quantity FROM cart_items WHERE user_id = ? AND product_id = ? FOR UPDATE";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $user_id, $product_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Update existing item
            $row = $result->fetch_assoc();
            $new_quantity = $row['quantity'] + $quantity;
            $sql = "UPDATE cart_items SET quantity = ? WHERE user_id = ? AND product_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iii", $new_quantity, $user_id, $product_id);
        } else {
            // Insert new item
            $sql = "INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iii", $user_id, $product_id, $quantity);
        }

        if (!$stmt->execute()) {
            throw new Exception("Failed to update cart");
        }

        $conn->commit();
        echo json_encode(["status" => "success", "message" => "Item added to cart"]);
        
    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
    
    $stmt->close();
    $check_stmt->close();
} else {
    // GET request - fetch cart items with optimized query
    $sql = "SELECT p.id, p.name, p.price, p.image as image_url, c.quantity 
            FROM cart_items c 
            JOIN products p ON c.product_id = p.id 
            WHERE c.user_id = ?
            ORDER BY c.product_id";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $cart = [];
    while ($row = $result->fetch_assoc()) {
        $cart[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'price' => (float)$row['price'],
            'image_url' => $row['image_url'],
            'quantity' => (int)$row['quantity']
        ];
    }
    
    echo json_encode($cart);
    $stmt->close();
}
?>
