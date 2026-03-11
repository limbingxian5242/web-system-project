<?php
session_start();
include '../inc/db.inc.php';
$conn = getDBConnection();
if (!$conn) { die("DB connection failed."); }

/**
 * Validates a card number
 * @param string $card The card number to validate
 * @return bool True if valid, false otherwise
 */
function isValidCard($card) {
    return preg_match("/^[0-9]{16}$/", $card);
}

/**
 * Validates expiry date in MM/YY format
 * @param string $expiry The expiry date to validate
 * @return bool True if valid, false otherwise
 */
function isValidExpiry($expiry) {
    if (!preg_match("/^(0[1-9]|1[0-2])\/\d{2}$/", $expiry)) {
        return false;
    }
    
    // Check if expired
    list($month, $year) = explode('/', $expiry);
    $expYear = 2000 + (int)$year; // Convert YY to 20YY
    $expMonth = (int)$month;
    
    $currentYear = (int)date('Y');
    $currentMonth = (int)date('m');
    
    // Check if card is expired
    if ($expYear < $currentYear || ($expYear === $currentYear && $expMonth < $currentMonth)) {
        return false;
    }
    
    return true;
}

/**
 * Validates a CVV code
 * @param string $cvv The CVV to validate
 * @return bool True if valid, false otherwise
 */
function isValidCVV($cvv) {
    return preg_match("/^[0-9]{3}$/", $cvv);
}

/**
 * Validates an email address
 * @param string $email The email to validate
 * @return bool True if valid, false otherwise
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($email) <= 100;
}

/**
 * Sends an error response and exits
 * @param string $message The error message
 * @return void
 */
function sendError($message) {
    // Include security functions
    include_once __DIR__ . '/../inc/security.inc.php';
    
    // Log the error
    error_log("Checkout error: $message");
    
    // Display error page with properly escaped message
    die("<div class='alert alert-danger'>" . h($message) . "</div> <a href='../checkout.php' class='btn btn-primary'>Return to Checkout</a>");
}

// Validate user session
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php?message=Login required");
    exit;
}

// Get form data
$user_id = $_SESSION['user_id'];
$full_name = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$billing_address = trim($_POST['billing_address'] ?? '');
$shipping_address = trim($_POST['shipping_address'] ?? $billing_address); // Use billing address if shipping not provided
$card_number = $_POST['card_number'] ?? '';
$expiry = $_POST['expiry'] ?? '';
$cvv = $_POST['cvv'] ?? '';

// Step 1: Validate inputs
$errors = [];

// Validate name
if (empty($full_name)) {
    $errors[] = "Full name is required.";
} elseif (strlen($full_name) > 100) {
    $errors[] = "Name is too long (maximum 100 characters).";
}

// Validate email
if (empty($email)) {
    $errors[] = "Email address is required.";
} elseif (!isValidEmail($email)) {
    $errors[] = "Please enter a valid email address.";
}

// Validate phone (optional)
if (!empty($phone) && !preg_match("/^[\d\s\+\(\)-]{7,20}$/", $phone)) {
    $errors[] = "Please enter a valid phone number.";
}

// Validate addresses
if (empty($billing_address)) {
    $errors[] = "Billing address is required.";
} elseif (strlen($billing_address) < 10) {
    $errors[] = "Please enter a complete billing address.";
} elseif (strlen($billing_address) > 255) {
    $errors[] = "Billing address is too long.";
}

if (empty($shipping_address)) {
    $errors[] = "Shipping address is required.";
} elseif (strlen($shipping_address) < 10) {
    $errors[] = "Please enter a complete shipping address.";
} elseif (strlen($shipping_address) > 255) {
    $errors[] = "Shipping address is too long.";
}

// Validate payment details
if (!isValidCard($card_number)) {
    $errors[] = "Invalid card number. Must be 16 digits.";
}

if (!isValidExpiry($expiry)) {
    $errors[] = "Invalid or expired card. Please check expiry date.";
}

if (!isValidCVV($cvv)) {
    $errors[] = "Invalid CVV. Must be 3 digits.";
}

// If validation errors occurred, display them and stop
if (!empty($errors)) {
    sendError("Please correct the following errors:<br>" . implode("<br>", $errors));
}

// Step 2: Simulate payment processing
// In real-world, call Stripe or PayPal here
sleep(1); // Fake delay
$paymentSuccess = true;

if (!$paymentSuccess) {
    sendError("Payment failed. Please try again.");
}

// Step 3: Fetch cart
$sql = "SELECT c.product_id, c.quantity, p.name, p.price, p.image as image_url, p.stock 
        FROM cart_items c 
        JOIN products p ON c.product_id = p.id 
        WHERE c.user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$cart_items = [];

while ($row = $result->fetch_assoc()) {
    $cart_items[] = [
        'id' => $row['product_id'],
        'name' => $row['name'],
        'price' => $row['price'],
        'quantity' => $row['quantity'],
        'image_url' => $row['image_url'],
        'stock' => $row['stock']
    ];
}
$stmt->close();

if (empty($cart_items)) {
    sendError("Your cart is empty. Please add items before checkout.");
}

// Validate stock levels
foreach ($cart_items as $item) {
    if ($item['quantity'] > $item['stock']) {
        sendError("Sorry, we don't have enough stock for " . $item['name'] . ". Available: " . $item['stock']);
    }
}

// Calculate order totals
$subtotal = 0;
foreach ($cart_items as $item) {
    $subtotal += ($item['price'] * $item['quantity']);
}
$shipping = 5.00; // Fixed shipping cost
$total = $subtotal + $shipping;

// Prepare order details
$order_details = [
    'customer' => [
        'name' => $full_name,
        'email' => $email,
        'phone' => $phone,
        'billingAddress' => $billing_address,
        'shippingAddress' => $shipping_address
    ],
    'payment' => [
        'cardNumber' => substr($card_number, -4), // Only store last 4 digits
        'expiry' => $expiry
    ],
    'items' => $cart_items,
    'subtotal' => $subtotal,
    'shipping' => [
        'cost' => $shipping,
        'method' => 'Standard Shipping'
    ],
    'total' => $total,
    'date' => date('Y-m-d H:i:s')
];

// Format payment details for storage
$payment_details = json_encode([
    'card_last4' => substr($card_number, -4),
    'expiry' => $expiry,
    'shipping_address' => $shipping_address,
    'billing_address' => $billing_address
]);

// Begin transaction
$conn->begin_transaction();

try {
    // 1. Create order record
    $order_sql = "INSERT INTO orders (user_id, total_amount, order_date, payment_details, status) 
                 VALUES (?, ?, NOW(), ?, 'preparing')";
    $order_stmt = $conn->prepare($order_sql);
    
    $order_stmt->bind_param("ids", $user_id, $total, $payment_details);
    
    if (!$order_stmt->execute()) {
        throw new Exception("Failed to create order: " . $conn->error);
    }
    
    $order_id = $conn->insert_id;
    $order_stmt->close();
    
    // 2. Insert order items
    $items_sql = "INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)";
    $items_stmt = $conn->prepare($items_sql);
    
    foreach ($cart_items as $item) {
        $product_id = $item['id'];
        $quantity = $item['quantity'];
        $price = $item['price'];
        
        $items_stmt->bind_param("iiid", $order_id, $product_id, $quantity, $price);
        
        if (!$items_stmt->execute()) {
            throw new Exception("Failed to insert order item: " . $conn->error);
        }
        
        // 3. Update product stock
        $update_stock = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
        $update_stock->bind_param("ii", $quantity, $product_id);
        
        if (!$update_stock->execute()) {
            throw new Exception("Failed to update product stock: " . $conn->error);
        }
        
        $update_stock->close();
    }
    
    $items_stmt->close();
    
    // 4. Clear user's cart
    $clear_cart = $conn->prepare("DELETE FROM cart_items WHERE user_id = ?");
    $clear_cart->bind_param("i", $user_id);
    
    if (!$clear_cart->execute()) {
        throw new Exception("Failed to clear cart: " . $conn->error);
    }
    
    $clear_cart->close();
    
    // Commit transaction
    $conn->commit();
    
    // Clear localStorage cart (will be executed before redirect)
    echo "<script>
    localStorage.removeItem('cart');
    window.location.href = '../order_success.php?order_id=" . $order_id . "';
    </script>";
    exit;
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    sendError("Order processing failed: " . $e->getMessage());
}
