<?php
session_start();
require "inc/db.inc.php";

// Check if we have an order ID in the URL
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
$order_from_db = null;

// If we have an order ID and user is logged in, try to get order details from database
if ($order_id > 0 && isset($_SESSION['user_id'])) {
    $conn = getDBConnection();
    if ($conn) {
        try {
            // Get order details
            $sql = "SELECT o.*, u.name as user_name, u.email 
                    FROM orders o 
                    JOIN users u ON o.user_id = u.id 
                    WHERE o.id = ? AND o.user_id = ?";
            $stmt = $conn->prepare($sql);
            $user_id = $_SESSION['user_id'];
            $stmt->bind_param("ii", $order_id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $order_from_db = $result->fetch_assoc();
                
                // Get order items
                $items_sql = "SELECT oi.*, p.name, p.image as image_url 
                             FROM order_items oi 
                             JOIN products p ON oi.product_id = p.id 
                             WHERE oi.order_id = ?";
                $items_stmt = $conn->prepare($items_sql);
                $items_stmt->bind_param("i", $order_id);
                $items_stmt->execute();
                $items_result = $items_stmt->get_result();
                
                $order_items = [];
                while ($item = $items_result->fetch_assoc()) {
                    $order_items[] = $item;
                }
                
                // Create order details format compatible with our JavaScript
                $payment_details = json_decode($order_from_db['payment_details'], true);
                
                $order_from_db['items'] = $order_items;
                $order_from_db['customer'] = [
                    'name' => $order_from_db['user_name'],
                    'email' => $order_from_db['email'],
                    'billingAddress' => $payment_details['billing_address'] ?? 'No billing address provided',
                    'shippingAddress' => $payment_details['shipping_address'] ?? 'No shipping address provided'
                ];
                $order_from_db['payment'] = [
                    'cardNumber' => $payment_details['card_last4'] ?? '',
                    'expiry' => $payment_details['expiry'] ?? ''
                ];
                $order_from_db['shipping'] = [
                    'cost' => 5.00,
                    'method' => 'Standard Shipping'
                ];
                $order_from_db['orderId'] = $order_id;
                $order_from_db['subtotal'] = $order_from_db['total_amount'] - 5.00; // Assuming $5 shipping
                $order_from_db['total'] = $order_from_db['total_amount'];
                $order_from_db['date'] = $order_from_db['order_date'];
            }
        } catch (Exception $e) {
            // Silently fail, we'll use session data as fallback
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    
    <?php 
    $pageTitle = "Order Successful";
    include "inc/head.inc.php"; ?>
    <style>
        .order-success-page {
            background-color: #f9f6f1;
            padding: 40px 0;
        }
        .order-success-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .order-success-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .order-success-icon {
            font-size: 60px;
            color: #28a745;
            margin-bottom: 20px;
        }
        .order-items {
            margin-top: 30px;
        }
        .order-item {
            display: flex;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }
        .order-item-image {
            width: 60px;
            height: 60px;
            object-fit: contain;
            margin-right: 15px;
        }
        .order-item-details {
            flex-grow: 1;
        }
        .order-summary {
            background-color: #f1f1f1;
            padding: 20px;
            border-radius: 5px;
            margin-top: 30px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .summary-row.total {
            font-weight: bold;
            font-size: 18px;
            border-top: 1px solid #ddd;
            padding-top: 10px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <?php include "inc/navbar.inc.php"; ?>
    
    <div class="order-success-page">
        <div class="container order-success-container">
            <div class="card shadow-sm">
                <div class="card-body p-5">
                    <div class="order-success-header">
                        <div class="order-success-icon">
                            <i class="bi bi-check-circle-fill"></i>
                        </div>
                        <h1 class="display-5">Thank You for Your Order!</h1>
                        <p class="lead">Your order has been received and is being processed.</p>
                        <div id="order-id" class="mt-3"></div>
                        <div id="order-date" class="text-muted"></div>
                    </div>
                    
                    <div class="row mt-5">
                        <div class="col-md-7">
                            <h3>Order Details</h3>
                            <div id="order-items" class="order-items">
                                <!-- Order items will be inserted here by JavaScript -->
                            </div>
                        </div>
                        
                        <div class="col-md-5">
                            <div class="order-summary">
                                <h4 class="mb-3">Order Summary</h4>
                                <div id="order-summary-content">
                                    <!-- Order summary will be inserted here by JavaScript -->
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <h4>Shipping Information</h4>
                                <div id="shipping-address" class="mb-3"></div>
                            </div>
                            
                            <div class="d-grid gap-2 mt-4">
                                <a href="index.php" class="btn btn-dark">Continue Shopping</a>
                                <a href="profile.php" class="btn btn-outline-dark">View Order History</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    <?php if ($order_from_db): ?>
        // Use order data from database
        const orderDetails = <?= json_encode($order_from_db) ?>;
        renderOrderDetails(orderDetails);
    <?php else: ?>
        // Use order data from sessionStorage as fallback
        document.addEventListener('DOMContentLoaded', function() {
            // Retrieve order details from sessionStorage
            const orderDetailsString = sessionStorage.getItem('order_details');
            if (!orderDetailsString) {
                window.location.href = 'index.php';
                return;
            }
            
            const orderDetails = JSON.parse(orderDetailsString);
            renderOrderDetails(orderDetails);
            
            // Clear order details from sessionStorage after displaying
            // This prevents showing stale order data if the user refreshes
            setTimeout(() => {
                sessionStorage.removeItem('order_details');
            }, 3000);
        });
    <?php endif; ?>
        
        // Function to render order details
        function renderOrderDetails(orderDetails) {
            // Display order ID and date
            document.getElementById('order-id').innerHTML = `<strong>Order ID:</strong> ${orderDetails.orderId}`;
            
            // Format date nicely
            const orderDate = new Date(orderDetails.date);
            const formattedDate = orderDate.toLocaleString('en-US', { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            document.getElementById('order-date').textContent = formattedDate;
            
            // Display order items
            const orderItemsContainer = document.getElementById('order-items');
            let itemsHtml = '';
            
            orderDetails.items.forEach(item => {
                const price = parseFloat(item.price);
                const quantity = parseInt(item.quantity);
                const itemName = item.name;
                const imageUrl = item.image_url;
                
                itemsHtml += `
                    <div class="order-item">
                        <img src="${imageUrl}" alt="${itemName}" class="order-item-image">
                        <div class="order-item-details">
                            <div class="fw-bold">${itemName}</div>
                            <div class="text-muted">Quantity: ${quantity}</div>
                        </div>
                        <div class="order-item-price">
                            $${(price * quantity).toFixed(2)}
                        </div>
                    </div>
                `;
            });
            
            orderItemsContainer.innerHTML = itemsHtml;
            
            // Display order summary
            const orderSummaryContainer = document.getElementById('order-summary-content');
            const subtotal = parseFloat(orderDetails.subtotal);
            const shippingCost = parseFloat(orderDetails.shipping.cost);
            const total = parseFloat(orderDetails.total);
            
            let summaryHtml = `
                <div class="summary-row">
                    <span>Subtotal:</span>
                    <span>$${subtotal.toFixed(2)}</span>
                </div>
                <div class="summary-row">
                    <span>Shipping:</span>
                    <span>$${shippingCost.toFixed(2)}</span>
                </div>
                <div class="summary-row total">
                    <span>Total:</span>
                    <span>$${total.toFixed(2)}</span>
                </div>
            `;
            
            orderSummaryContainer.innerHTML = summaryHtml;
            
            // Display shipping information
            const customerShippingAddress = orderDetails.customer && orderDetails.customer.shippingAddress 
                ? orderDetails.customer.shippingAddress.replace(/\n/g, '<br>') 
                : '123 Plant Street, Apt 4<br>Greenville, CA 90210<br>United States';
                
            document.getElementById('shipping-address').innerHTML = `
                <strong>Shipping Method:</strong> ${orderDetails.shipping.method}<br><br>
                <strong>Address:</strong> <br>
                ${customerShippingAddress}
            `;
        }
    </script>
    
    <?php include "inc/footer.inc.php"; ?>
</body>
</html>
