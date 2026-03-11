<?php
// session_start();
// include "db.inc.php"; // Ensure you have a database connection file

// function addToCart($product_id, $quantity) {
//     $user_id = $_SESSION['user_id']; // Assuming user is logged in

//     $query = "INSERT INTO cart (user_id, product_id, quantity) 
//               VALUES ($user_id, $product_id, $quantity)
//               ON DUPLICATE KEY UPDATE quantity = quantity + $quantity";

//     $result = mysqli_query($conn, $query);
//     return $result ? "Item added to cart" : "Failed to add item";
// }

// function getCartItems($user_id) {
//     global $conn;
//     $query = "SELECT * FROM cart WHERE user_id = $user_id";
//     $result = mysqli_query($conn, $query);
//     return mysqli_fetch_all($result, MYSQLI_ASSOC);
// }

// Get the current page to check if we should display the cart
$current_page = basename($_SERVER['PHP_SELF']);
$restricted_pages = ['login.php', 'checkout.php', 'process_login.php', 'register.php'];

// Make sure profile.php is not in the restricted pages list
// Only display the cart if not on a restricted page
if (!in_array($current_page, $restricted_pages)):
?>
 <!-- Shopping Cart Sidebar -->
 <aside id="cart-sidebar" class="cart-sidebar" aria-labelledby="cart-heading">
    <div class="cart-header">
    <h2 id="cart-heading">Your Cart (<span id="cart-count-header">0</span>)</h2>
        <button class="close-cart" onclick="toggleCart()" aria-label="Close shopping cart">✖</button>
    </div>
    <div id="cart-items" class="cart-items"></div>
    

    <!-- Sticky Footer with Subtotal and Checkout Button -->
    <div class="cart-footer">
        <!-- Subtotal Section -->
        <div class="cart-subtotal">
            <span>Subtotal</span>
            <span id="cart-subtotal">$0.00</span>
        </div>

        <!-- Checkout Button -->
        <button id="checkout-btn" class="checkout-btn" onclick="redirectToCheckout()" aria-label="Proceed to checkout">Check Out</button>
    </div>
</aside>

<!-- Background Overlay (Blur Effect) -->
<div id="cart-overlay" class="cart-overlay" onclick="toggleCart()" aria-hidden="true"></div>
<?php endif; ?>

