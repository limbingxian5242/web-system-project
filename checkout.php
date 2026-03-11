<!DOCTYPE html>
<html lang="en">
<head>
    <title>Checkout</title>
    <?php include "inc/head.inc.php"; ?>
    <style>
        /* Custom styles for checkout page */
        .card-format {
            font-family: monospace;
            letter-spacing: 0.5px;
        }
        .card-format span.fw-medium {
            color: #495057;
            background-color: #f8f9fa;
            padding: 2px 5px;
            border-radius: 3px;
            border: 1px solid #e9ecef;
        }
        /* Card number input styling */
        #card-number {
            letter-spacing: 1px;
            font-family: monospace;
            font-size: 1.1em;
        }
        /* Improve form spacing */
        .checkout-page .form-control:focus {
            box-shadow: 0 0 0 0.2rem rgba(108, 117, 125, 0.15);
            border-color: #6c757d;
        }
    </style>
</head>
<body class="checkout-page">
    <?php 
    include "inc/navbar.inc.php";
    session_start();
    require __DIR__ . '/vendor/autoload.php';
    
    // Debug parameter - remove in production
    $debug = isset($_GET['debug']) && $_GET['debug'] == 1;
    
    // Use the same authentication approach as profile.php
    $config = parse_ini_file('/var/www/private/db-config.ini');
    $dbSchema = 'project';
    $pdo = new \PDO("mysql:dbname={$config['dbname']};host={$config['servername']};charset=utf8", "{$config['username']}", "{$config['password']}");
    $db = \Delight\Db\PdoDatabase::fromPdo($pdo);
    $auth = new \Delight\Auth\Auth($pdo, null, null, null, null, $dbSchema);
    
    // Start output buffering to ensure headers can be sent
    ob_start();
    
    if (!$auth->isLoggedIn()) {
        // Start session to store redirect information
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        // Store the redirect destination in session
        $_SESSION['redirect'] = 'checkout';
        
        // Store the cart from localStorage in session storage (if available)
        echo "<script>
            if (typeof localStorage !== 'undefined' && localStorage.getItem('cart')) {
                sessionStorage.setItem('pending_cart', localStorage.getItem('cart'));
                console.log('Cart saved to sessionStorage before redirect');
            }
            sessionStorage.setItem('login_message', 'Please log in to complete your checkout');
        </script>";
        
        // Redirect to login with checkout as the return destination
        ob_end_clean(); // Clear the buffer before sending headers
        header("Location: login.php?redirect=checkout");
        exit;
    }
    
    // Continue with normal output
    ob_end_flush();
    
    // User is logged in, get their ID
    $user_id = $auth->getUserId();
    
    // Check for pending cart data in sessionStorage
    echo "<script>
        // Check if there's pending cart data in sessionStorage
        var pendingCart = sessionStorage.getItem('pending_cart');
        if (pendingCart) {
            console.log('Found pending cart data, restoring to localStorage');
            localStorage.setItem('cart', pendingCart);
            // Clear the pending cart
            sessionStorage.removeItem('pending_cart');
        }
    </script>";
    
    // Create a database connection for cart handling
    include "inc/db.inc.php";
    $conn = getDBConnection();
    if (!$conn) {
        die("Database connection failed. Please try again later.");
    }
    
    // Fetch user details with error handling
    try {
        // Fetch basic user info from users table
        $user_sql = "SELECT u.id, u.username, u.email FROM users u WHERE u.id = ?";
        $user_stmt = $conn->prepare($user_sql);
        $user_stmt->bind_param("i", $user_id);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        $user = $user_result->fetch_assoc();
        $user_stmt->close();
        
        if (!$user) {
            throw new Exception("User data not found");
        }
        
        // Now fetch user's payment profile
        $payment_sql = "SELECT card_name, card_last_four, expiry_date, address, street, city, postal_code, country 
                       FROM user_profiles 
                       WHERE user_id = ? AND profile_types = 'payment' AND is_default = 1 
                       LIMIT 1";
        $payment_stmt = $conn->prepare($payment_sql);
        $payment_stmt->bind_param("i", $user_id);
        $payment_stmt->execute();
        $payment_result = $payment_stmt->get_result();
        $payment_profile = $payment_result->fetch_assoc();
        $payment_stmt->close();
        
        // Fetch user's shipping profile
        $shipping_sql = "SELECT address, street, city, postal_code, country 
                        FROM user_profiles 
                        WHERE user_id = ? AND profile_types = 'shipping' AND is_default = 1 
                        LIMIT 1";
        $shipping_stmt = $conn->prepare($shipping_sql);
        $shipping_stmt->bind_param("i", $user_id);
        $shipping_stmt->execute();
        $shipping_result = $shipping_stmt->get_result();
        $shipping_profile = $shipping_result->fetch_assoc();
        $shipping_stmt->close();
        
        // Combine all profiles into user data
        $user['name'] = $user['username'];
        $user['phone'] = ''; // Add placeholder if phone field needed
        
        // Format billing/payment address
        if ($payment_profile) {
            $billing_parts = [];
            if (!empty($payment_profile['street'])) $billing_parts[] = $payment_profile['street'];
            if (!empty($payment_profile['address'])) $billing_parts[] = $payment_profile['address'];
            if (!empty($payment_profile['city'])) $billing_parts[] = $payment_profile['city'];
            if (!empty($payment_profile['postal_code'])) $billing_parts[] = $payment_profile['postal_code'];
            if (!empty($payment_profile['country'])) $billing_parts[] = $payment_profile['country'];
            
            $user['billing_address'] = implode(', ', $billing_parts);
            $user['card_last_four'] = $payment_profile['card_last_four'] ?? '';
            $user['expiry_date'] = $payment_profile['expiry_date'] ?? '';
        } else {
            $user['billing_address'] = '';
            $user['card_last_four'] = '';
            $user['expiry_date'] = '';
        }
        
        // Format shipping address
        if ($shipping_profile) {
            $shipping_parts = [];
            if (!empty($shipping_profile['street'])) $shipping_parts[] = $shipping_profile['street'];
            if (!empty($shipping_profile['address'])) $shipping_parts[] = $shipping_profile['address'];
            if (!empty($shipping_profile['city'])) $shipping_parts[] = $shipping_profile['city'];
            if (!empty($shipping_profile['postal_code'])) $shipping_parts[] = $shipping_profile['postal_code'];
            if (!empty($shipping_profile['country'])) $shipping_parts[] = $shipping_profile['country'];
            
            $user['shipping_address'] = implode(', ', $shipping_parts);
            $user['has_shipping_profile'] = true;
        } else {
            $user['shipping_address'] = '';
            $user['has_shipping_profile'] = false;
        }
        
    } catch (Exception $e) {
        if ($debug) {
            echo "Error fetching user data: " . $e->getMessage();
        }
        $user = [
            'name' => $auth->getUsername(),
            'email' => '',
            'address' => '',
            'phone' => '',
            'billing_address' => '',
            'shipping_address' => '',
            'has_shipping_profile' => false,
            'card_last_four' => '',
            'expiry_date' => ''
        ];
    }
    
    // Fetch cart items with error handling
    try {
        $cart_sql = "SELECT p.id, p.name, p.price, p.image as image_url, p.stock, c.quantity 
                    FROM cart_items c 
                    JOIN products p ON c.product_id = p.id 
                    WHERE c.user_id = ?";
        $cart_stmt = $conn->prepare($cart_sql);
        $cart_stmt->bind_param("i", $user_id);
        $cart_stmt->execute();
        $cart_result = $cart_stmt->get_result();
        $cart_items = [];
        while ($row = $cart_result->fetch_assoc()) {
            $cart_items[] = $row;
        }
        $cart_stmt->close();
    } catch (Exception $e) {
        if ($debug) {
            echo "Error fetching cart items: " . $e->getMessage();
        }
        $cart_items = [];
    }
    
    // If cart is empty, check for localStorage cart and save it
    if (empty($cart_items)) {
        // Check if we should use the local cart as fallback
        echo "<script>
            // Check for cart data in localStorage
            var localCart = localStorage.getItem('cart');
            if (localCart && JSON.parse(localCart).length > 0) {
                // Send cart to server
                fetch('api/save_cart.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        cart: JSON.parse(localCart)
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        // Reload the page to show the updated cart
                        window.location.reload();
                    }
                })
                .catch(error => {
                    console.error('Error syncing cart:', error);
                });
            }
        </script>";
        
        // if (!isset($_GET['fallback'])) {
        //     echo '<div class="container mt-5">
        //         <div class="alert alert-warning">
        //             <h4>Your cart is empty</h4>
        //             <p>Please add items to your cart before proceeding to checkout.</p>
        //             <a href="allProducts.php" class="btn btn-primary">Browse Products</a>
        //         </div>
        //     </div>';
        //     include "inc/footer.inc.php";
        //     exit;
        // }
    }
    
    $total_price = array_reduce($cart_items, fn($sum, $item) => $sum + ($item['price'] * $item['quantity']), 0);
    ?>
    
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-8">
                <div class="card p-4">
                    <h2 class="mb-4">Checkout</h2>

                    <!-- Start Main Checkout Form -->
                    <form id="checkout-form" method="POST" action="api/process_checkout.php">
                        
                        <!-- User Information Section -->
                        <div class="mb-4">
                            <h5 class="fw-bold">1. Your Information</h5>
                            <div class="mt-3">
                                <div class="mb-3">
                                    <label for="full-name" class="form-label">Full Name</label>
                                    <input type="text" class="form-control" id="full-name" name="full_name" value="<?= htmlspecialchars($user['name'] ?? '') ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone Number (optional)</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                                </div>
                                
                                <div class="mb-4">
                                    <label for="billing-address" class="form-label">Billing Address</label>
                                    <textarea class="form-control" id="billing-address" name="billing_address" rows="3" required><?= htmlspecialchars($user['billing_address'] ?? '') ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="same-address" <?= empty($user['has_shipping_profile']) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="same-address">
                                            Shipping address same as billing
                                        </label>
                                    </div>
                                </div>
                                
                                <div id="shipping-address-container" class="mb-4" <?= empty($user['has_shipping_profile']) ? 'style="display: none;"' : '' ?>>
                                    <label for="shipping-address" class="form-label">Shipping Address</label>
                                    <textarea class="form-control" id="shipping-address" name="shipping_address" rows="3"><?= htmlspecialchars($user['shipping_address'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($cart_items)): ?>
                        <!-- Payment Section -->
                        <div>
                            <h5 class="fw-bold">2. Payment Details</h5>
                            <div class="mt-3">
                                <div class="mb-3">
                                    <label for="card-number" class="form-label">Card Number</label>
                                    <input type="text" class="form-control" id="card-number" name="card_number" 
                                           placeholder="1234 5678 9012 3456" maxlength="19" required
                                           <?php if (!empty($user['card_last_four'])): ?>
                                           data-last-four="<?= htmlspecialchars($user['card_last_four']) ?>"
                                           <?php endif; ?>>
                                    <div class="form-text text-muted">
                                        <span class="card-format">
                                            <i class="bi bi-credit-card me-1"></i>
                                            Format: <span class="fw-medium">0000 0000 0000 0000</span>
                                        </span>
                                        <?php if (!empty($user['card_last_four'])): ?>
                                        <span class="text-info ms-2">
                                            <i class="bi bi-info-circle"></i>
                                            Card ending in <?= htmlspecialchars($user['card_last_four']) ?> on file
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="expiry" class="form-label">Expiry Date (MM/YY)</label>
                                        <input type="text" class="form-control" id="expiry" name="expiry" 
                                               placeholder="MM/YY" maxlength="5" required
                                               <?php if (!empty($user['expiry_date'])): ?>
                                               value="<?= date('m/y', strtotime($user['expiry_date'])) ?>"
                                               <?php endif; ?>>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="cvv" class="form-label">CVV</label>
                                        <input type="text" class="form-control" id="cvv" name="cvv" 
                                               placeholder="123" maxlength="3" required>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-dark w-100">🔒 Pay</button>
                        </div>
                        <!-- <?php else: ?>
                            <div class="alert alert-warning">
                                <p>Your cart appears to be empty. Please add items to proceed.</p>
                                <a href="allProducts.php" class="btn btn-sm btn-primary">Browse Products</a>
                            </div>
                        <?php endif; ?> -->

                    </form>
                </div>
            </div>

            <?php if (!empty($cart_items)): ?>
            <!-- Order Summary Sidebar -->
            <div class="col-md-4">
                <div class="card p-4">
                    <h5 class="fw-bold">Order Summary</h5>
                    <ul class="list-group mb-3" id="checkout-items">
                        <?php foreach ($cart_items as $item): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center checkout-item-row" 
                                data-product-id="<?= $item['id'] ?>" 
                                data-stock="<?= $item['stock'] ?>">
                                <div class="d-flex align-items-center flex-grow-1">
                                    <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="checkout-product-img me-2">
                                    <div class="flex-grow-1">
                                        <span class="fw-bold"><?= htmlspecialchars($item['name']) ?></span>
                                        <div class="d-flex align-items-center mt-1">
                                            <button class="btn btn-sm btn-outline-secondary quantity-btn minus-btn" 
                                                   data-product-id="<?= $item['id'] ?>" 
                                                   aria-label="Decrease quantity of <?= htmlspecialchars($item['name']) ?>">-</button>
                                            <label for="quantity-input-<?= $item['id'] ?>" class="visually-hidden">Quantity for <?= htmlspecialchars($item['name']) ?></label>
                                            <input type="number" 
                                                   id="quantity-input-<?= $item['id'] ?>"
                                                   class="form-control form-control-sm mx-1 quantity-input" 
                                                   style="width: 60px; text-align: center;" 
                                                   value="<?= $item['quantity'] ?>" 
                                                   min="1" 
                                                   max="<?= $item['stock'] ?>" 
                                                   data-product-id="<?= $item['id'] ?>"
                                                   aria-label="Quantity for <?= htmlspecialchars($item['name']) ?>">
                                            <button class="btn btn-sm btn-outline-secondary quantity-btn plus-btn" 
                                                   data-product-id="<?= $item['id'] ?>" 
                                                   aria-label="Increase quantity of <?= htmlspecialchars($item['name']) ?>">+</button>
                                            <span class="stock-info small text-muted ms-2">(Stock: <?= $item['stock'] ?>)</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-end ms-2">
                                    <span>$<?= number_format($item['price'] * $item['quantity'], 2) ?></span>
                                    <button class="btn btn-sm btn-link text-danger remove-item" 
                                           data-product-id="<?= $item['id'] ?>"
                                           aria-label="Remove <?= htmlspecialchars($item['name']) ?> from cart">
                                        <i class="bi bi-trash" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="border-top pt-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal</span>
                            <span id="checkout-subtotal">$<?= number_format($total_price, 2) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Shipping</span>
                            <span id="checkout-shipping">$5.00</span>
                        </div>
                        <div class="d-flex justify-content-between fw-bold mt-2">
                            <span>Total</span>
                            <span id="checkout-total">$<?= number_format($total_price + 5.00, 2) ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Make sure the main script is included -->
    <script src="script.js"></script> 
    
    <!-- Custom checkout form handling -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Get form elements
        const billingAddressField = document.getElementById('billing-address');
        const shippingAddressField = document.getElementById('shipping-address');
        const sameAddressCheckbox = document.getElementById('same-address');
        const shippingContainer = document.getElementById('shipping-address-container');
        
        // Function to copy billing address to shipping
        function copyBillingToShipping() {
            if (billingAddressField && shippingAddressField) {
                shippingAddressField.value = billingAddressField.value;
            }
        }
        
        // Initialize shipping address directly from PHP values if checkbox is checked
        if (sameAddressCheckbox && sameAddressCheckbox.checked) {
            // First attempt - immediate
            copyBillingToShipping();
            
            // Hide shipping container
            if (shippingContainer) {
                shippingContainer.style.display = 'none';
            }
            
            // Second attempt - slight delay to ensure DOM is fully loaded
            setTimeout(copyBillingToShipping, 100);
        }
        
        // Handle "Same as billing" checkbox change events
        if (sameAddressCheckbox) {
            sameAddressCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    // Copy billing address to shipping address
                    copyBillingToShipping();
                    if (shippingContainer) {
                        shippingContainer.style.display = 'none';
                    }
                } else {
                    // Only show the shipping container, don't clear the value
                    if (shippingContainer) {
                        shippingContainer.style.display = 'block';
                    }
                }
            });
        }
        
        // Update shipping when billing changes (if "same as billing" is checked)
        if (billingAddressField) {
            billingAddressField.addEventListener('input', function() {
                if (sameAddressCheckbox && sameAddressCheckbox.checked) {
                    copyBillingToShipping();
                }
            });
        }
        
        // Trigger the change event on the checkbox to ensure proper initial state
        if (sameAddressCheckbox) {
            // Force a change event
            const event = new Event('change');
            sameAddressCheckbox.dispatchEvent(event);
        }
        
        // Credit card formatting with saved info indication
        const cardNumberInput = document.getElementById('card-number');
        if (cardNumberInput) {
            const lastFour = cardNumberInput.getAttribute('data-last-four');
            
            // Show a visual indicator if using saved card
            if (lastFour) {
                // Add placeholder text suggesting using the card on file
                cardNumberInput.placeholder = `Card ending in ${lastFour}`;
                
                // Add a helper button to auto-fill card number field
                const cardFieldContainer = cardNumberInput.closest('.mb-3');
                if (cardFieldContainer) {
                    const autofillBtn = document.createElement('button');
                    autofillBtn.type = 'button';
                    autofillBtn.className = 'btn btn-sm btn-outline-secondary mt-2';
                    autofillBtn.textContent = 'Use card on file (ending in ' + lastFour + ')';
                    autofillBtn.setAttribute('aria-label', 'Use saved card ending in ' + lastFour);
                    autofillBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        // Simulate a card number with proper formatting
                        cardNumberInput.value = 'XXXX XXXX XXXX ' + lastFour;
                        // Indicate in a hidden field that user wants to reuse card
                        if (!document.getElementById('use-existing-card')) {
                            const hiddenField = document.createElement('input');
                            hiddenField.type = 'hidden';
                            hiddenField.id = 'use-existing-card';
                            hiddenField.name = 'use_existing_card';
                            hiddenField.value = '1';
                            cardFieldContainer.appendChild(hiddenField);
                        }
                        
                        // Focus on the next field
                        if (document.getElementById('cvv')) {
                            document.getElementById('cvv').focus();
                        }
                    });
                    cardFieldContainer.appendChild(autofillBtn);
                }
            }
            
            cardNumberInput.addEventListener('input', function(e) {
                // Remove all non-digits
                let value = e.target.value.replace(/\D/g, '');
                
                // Add spaces after every 4 digits
                let formattedValue = '';
                for (let i = 0; i < value.length; i++) {
                    if (i > 0 && i % 4 === 0) {
                        formattedValue += ' ';
                    }
                    formattedValue += value[i];
                }
                
                // Update the input value
                e.target.value = formattedValue;
                
                // If value is empty, reset placeholder
                if (value.length === 0 && lastFour) {
                    cardNumberInput.placeholder = `Card ending in ${lastFour}`;
                }
                
                // If there's a hidden field for reusing card, remove it when user enters new card
                const useExistingCard = document.getElementById('use-existing-card');
                if (useExistingCard && value.length > 0 && !e.target.value.startsWith('XXXX')) {
                    useExistingCard.remove();
                }
            });
        }
        
        // Format expiry date as MM/YY
        const expiryInput = document.getElementById('expiry');
        if (expiryInput) {
            expiryInput.addEventListener('input', function(e) {
                // Remove all non-digits
                let value = e.target.value.replace(/\D/g, '');
                
                // Format as MM/YY
                if (value.length > 2) {
                    value = value.substring(0, 2) + '/' + value.substring(2, 4);
                }
                
                // Update the input value
                e.target.value = value;
            });
        }
        
        // Restrict CVV to 3 digits only
        const cvvInput = document.getElementById('cvv');
        if (cvvInput) {
            cvvInput.addEventListener('input', function(e) {
                e.target.value = e.target.value.replace(/\D/g, '').substring(0, 3);
            });
        }
        
        // Save form data to localStorage when entered
        const formInputs = document.querySelectorAll('#checkout-form input:not([type="checkbox"]), #checkout-form textarea');
        formInputs.forEach(input => {
            // For non-sensitive fields, save to localStorage for persistence
            if (!['card-number', 'expiry', 'cvv'].includes(input.id)) {
                // Restore from localStorage if empty
                const savedValue = localStorage.getItem('checkout_' + input.id);
                if (savedValue && input.value === '') {
                    input.value = savedValue;
                }
                
                // Save to localStorage as user types
                input.addEventListener('input', function() {
                    localStorage.setItem('checkout_' + this.id, this.value);
                });
            }
        });
        
        // Form validation before submission
        const checkoutForm = document.getElementById('checkout-form');
        if (checkoutForm) {
            checkoutForm.addEventListener('submit', function(e) {
                let hasErrors = false;
                
                // Check for use of existing card via the hidden field
                const useExistingCard = document.getElementById('use-existing-card');
                const isUsingExistingCard = useExistingCard && useExistingCard.value === '1';
                
                // Validate card number (basic check - should be 16 digits without spaces)
                if (cardNumberInput) {
                    const cardNumber = cardNumberInput.value.replace(/\s/g, '');
                    if (!isUsingExistingCard && (cardNumber.length !== 16 || !/^\d+$/.test(cardNumber)) && !cardNumberInput.value.startsWith('XXXX')) {
                        alert('Please enter a valid 16-digit card number');
                        cardNumberInput.focus();
                        hasErrors = true;
                    }
                }
                
                // Validate expiry date
                if (expiryInput && !hasErrors && !isUsingExistingCard) {
                    const expiry = expiryInput.value;
                    if (!/^\d{2}\/\d{2}$/.test(expiry)) {
                        alert('Please enter a valid expiry date in MM/YY format');
                        expiryInput.focus();
                        hasErrors = true;
                    } else {
                        // Check if card is expired
                        const [month, year] = expiry.split('/');
                        const expiryDate = new Date(2000 + parseInt(year, 10), parseInt(month, 10) - 1);
                        const today = new Date();
                        
                        if (expiryDate < today) {
                            alert('The card expiry date has passed');
                            expiryInput.focus();
                            hasErrors = true;
                        }
                    }
                }
                
                // Validate CVV
                if (cvvInput && !hasErrors && !isUsingExistingCard) {
                    if (!/^\d{3}$/.test(cvvInput.value)) {
                        alert('Please enter a valid 3-digit CVV code');
                        cvvInput.focus();
                        hasErrors = true;
                    }
                }
                
                if (hasErrors) {
                    e.preventDefault();
                    return false;
                }
            });
        }
    });
    </script>
</body>
</html>