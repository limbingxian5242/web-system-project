<?php
session_start();
require __DIR__ . '/vendor/autoload.php';

$config = parse_ini_file('/var/www/private/db-config.ini');
$dbSchema = 'project';
$pdo = new \PDO("mysql:dbname={$config['dbname']};host={$config['servername']};charset=utf8", "{$config['username']}", "{$config['password']}");
$db = \Delight\Db\PdoDatabase::fromPdo($pdo);
$auth = new \Delight\Auth\Auth($pdo, null, null, null, null, $dbSchema);


if (!$auth->isLoggedIn()) {
header('Location: login.php');
exit;
}

$username = $auth->getUsername();
$userId = $auth->getUserId();

// Process logout
if (isset($_POST['logout'])) {
    try {
        $auth->logOutEverywhereElse();
        $auth->logOut();
        header('Location: login.php');
        exit;
    } catch (\Delight\Auth\NotLoggedInException $e) {
        die('Not logged in');
    }
}

// Process address form submission
if (isset($_POST['action']) && $_POST['action'] === 'save_address') {
    saveAddress($pdo, $username, $userId);
}

// Process payment form submission
if (isset($_POST['action']) && $_POST['action'] === 'save_payment') {
    savePayment($pdo, $username, $userId);
}

$address = getUserAddress($pdo, $userId);
$payment_methods = getUserPaymentMethods($pdo, $userId);
$orders = getUserOrders($pdo, $userId);

function logout($auth)
{
    try {
        $auth->logOutEverywhereElse();
    } catch (\Delight\Auth\NotLoggedInException $e) {
        die('Not logged in');
    }
    $auth->logOut();
    header('Location: login.php');
    exit;
}

function getUserOrders($pdo, $userId) {
    $stmt = $pdo->prepare("
        SELECT * FROM orders 
        WHERE user_id = ? 
        ORDER BY order_date DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


function saveAddress($pdo, $username, $userId) {
    $debug_log = '/var/www/html/dev1/address_debug.log';

    try {
        file_put_contents($debug_log, "POST Data: " . print_r($_POST, true) . "\n", FILE_APPEND);
        
        // Validate input
        $required_fields = ['address', 'street', 'city', 'postal_code', 'country'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Field '$field' is required");
            }
        }

        // Define variables AFTER validation loop is complete
        $address = $_POST['address'];
        $street = $_POST['street'];
        $city = $_POST['city'];
        $postal_code = $_POST['postal_code'];

        if (!preg_match('/^\d{6}$/', $postal_code)) {
            throw new Exception("Postal code must be exactly 6 digits");
        }

        $country = $_POST['country'];
        $is_default = isset($_POST['default_address']) ? 1 : 0;
        
        // Set as default if requested
        if ($is_default) {
            $stmt = $pdo->prepare("UPDATE profiles SET is_default = 0 WHERE user_id = ? AND profile_types = 'shipping'");
            $stmt->execute([$userId]);
        }
        
        // Add new address
        $stmt = $pdo->prepare("INSERT INTO profiles (
            username,
            user_id, 
            profile_types, 
            address, 
            street, 
            city, 
            postal_code, 
            country, 
            is_default
        ) VALUES (?, ?, 'shipping', ?, ?, ?, ?, ?, ?)");

        // Execute the statement
        $result = $stmt->execute([
            $username,
            $userId, 
            $address, 
            $street, 
            $city, 
            $postal_code, 
            $country, 
            $is_default
        ]);

        // Check if insertion was successful
        if (!$result) {
            // Get detailed error information
            $errorInfo = $stmt->errorInfo();
            file_put_contents($debug_log, "Insertion Error: " . print_r($errorInfo, true) . "\n", FILE_APPEND);
            throw new Exception("Database insertion failed");
        }
        
        // Log successful insertion
        file_put_contents($debug_log, "Address insertion successful\n", FILE_APPEND);
        
        // Success message
        $_SESSION['message'] = 'Address added successfully!';
        $_SESSION['message_type'] = 'success';
    } catch (Exception $e) {
        file_put_contents($debug_log, "Exception: " . $e->getMessage() . "\n", FILE_APPEND);
        $_SESSION['message'] = 'Error adding address: ' . $e->getMessage();
        $_SESSION['message_type'] = 'danger';
    }
    
    header('Location: profile.php');
    exit;
}


function savePayment($pdo, $username, $userId) {
    try {

        $card_name = $_POST['card_name'];

        if (!preg_match('/^[a-zA-Z \'\-\.]+$/', $card_name)) {
            throw new Exception("Card name should contain only letters and spaces");
        }

        $card_number = $_POST['card_number'];

        $card_number_clean = preg_replace('/\s+/', '', $card_number);
        if (!preg_match('/^\d+$/', $card_number_clean)) {
            throw new Exception("Card number should contain only digits");
        }

        // Store only the last 4 digits for security
        $last_four = substr(str_replace(' ', '', $card_number), -4);
        $expiry_date = $_POST['expiry_date'];

        if (!preg_match('/^(0[1-9]|1[0-2])([0-9]{2})$/', $expiry_date)) {
            throw new Exception("Expiry date should be in MMYY format");
        }

        $is_default = isset($_POST['default_payment']) ? 1 : 0;
        
        // If this is set as default, unset any existing defaults
        if ($is_default) {
            $stmt = $pdo->prepare("UPDATE profiles SET is_default = 0 WHERE user_id = ? AND profile_types = 'payment'");
            $stmt->execute([$userId]);
        }
        
        // Insert the new payment method
        $stmt = $pdo->prepare("INSERT INTO profiles (username, user_id, profile_types, card_name, card_last_four, expiry_date, is_default) VALUES (?, ?, 'payment', ?, ?, ?, ?)");
        $stmt->execute([$username, $userId, $card_name, $last_four, $expiry_date, $is_default]);
        
        // Set success message
        $_SESSION['message'] = 'Payment method added successfully!';
        $_SESSION['message_type'] = 'success';
    } catch (Exception $e) {
        // Set error message
        $_SESSION['message'] = 'Error adding payment method: ' . $e->getMessage();
        $_SESSION['message_type'] = 'danger';
    }
    
    // Redirect to refresh the page
    header('Location: profile.php');
    exit;
}

function getUserAddress($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT * FROM profiles WHERE user_id = ? AND profile_types = 'shipping' ORDER BY is_default DESC");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getUserPaymentMethods($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT * FROM profiles WHERE user_id = ? AND profile_types = 'payment' ORDER BY is_default DESC");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

    // Process address deletion
if (isset($_POST['action']) && $_POST['action'] === 'delete_address') {
    deleteAddress($pdo, $username, $userId, $_POST['address_id']);
}

// Process payment method deletion
if (isset($_POST['action']) && $_POST['action'] === 'delete_payment') {
    deletePayment($pdo, $username, $userId, $_POST['payment_id']);
}

// Function to delete an address
function deleteAddress($pdo, $username, $userId, $address_id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM profiles WHERE id = ? AND user_id = ? AND profile_types = 'shipping'");
        $stmt->execute([$address_id, $userId]);
        
        $_SESSION['message'] = 'Address removed successfully!';
        $_SESSION['message_type'] = 'success';
    } catch (Exception $e) {
        $_SESSION['message'] = 'Error removing address: ' . $e->getMessage();
        $_SESSION['message_type'] = 'danger';
    }
    
    header('Location: profile.php');
    exit;
}

// Function to delete a payment method
function deletePayment($pdo, $username, $userId, $payment_id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM profiles WHERE id = ? AND user_id = ? AND profile_types = 'payment'");
        $stmt->execute([$payment_id, $userId]);
        
        $_SESSION['message'] = 'Payment method removed successfully!';
        $_SESSION['message_type'] = 'success';
    } catch (Exception $e) {
        $_SESSION['message'] = 'Error removing payment method: ' . $e->getMessage();
        $_SESSION['message_type'] = 'danger';
    }
    
    header('Location: profile.php');
    exit;
}

// Function to change username
function changeUsername($pdo, $auth, $userId, $newUsername) {
    try {
        // Validate username
        if (empty($newUsername)) {
            throw new Exception("Username cannot be empty");
        }
        
        if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $newUsername)) {
            throw new Exception("Username must be 3-50 characters and contain only letters, numbers, and underscores");
        }
        
        $pdo->beginTransaction();
        
        // Update username in users table
        try {
            // Check if this is a Delight Auth method
            if (method_exists($auth, 'changeUsername')) {
                $auth->changeUsername($newUsername);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET username = ? WHERE id = ?");
                $success = $stmt->execute([$newUsername, $userId]);
                
                if (!$success) {
                    throw new Exception("Failed to update username in users table");
                }
            }
        } catch (Exception $e) {
            throw new Exception("Error updating username: " . $e->getMessage());
        }
        
        // Update username in profiles table
        $stmt = $pdo->prepare("UPDATE profiles SET username = ? WHERE user_id = ?");
        $success = $stmt->execute([$newUsername, $userId]);
        
        if (!$success) {
            throw new Exception("Failed to update username in profiles table");
        }
        
        $pdo->commit();
        
        $_SESSION['message'] = 'Username updated successfully!';
        $_SESSION['message_type'] = 'success';
        
        return true;
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        $_SESSION['message'] = 'Error updating username: ' . $e->getMessage();
        $_SESSION['message_type'] = 'danger';
        
        return false;
    }
}


if (isset($_POST['action']) && $_POST['action'] === 'change_username') {
    if (changeUsername($pdo, $auth, $userId, $_POST['new_username'])) {
        $username = $_POST['new_username'];
    }
}

// Process order details request
if (isset($_POST['action']) && $_POST['action'] === 'get_order_details') {
    $orderId = intval($_POST['order_id']);
    $orderDetails = getOrderDetailsById($pdo, $orderId, $userId);
    
    // Return the details as JSON
    header('Content-Type: application/json');
    echo json_encode($orderDetails);
    exit;
}

// Function to get order details by ID
function getOrderDetailsById($pdo, $orderId, $userId) {
    // First verify this order belongs to the user
    $orderStmt = $pdo->prepare("
        SELECT o.*, DATE_FORMAT(o.order_date, '%M %d, %Y') as formatted_date 
        FROM orders o 
        WHERE o.id = ? AND o.user_id = ?
    ");
    $orderStmt->execute([$orderId, $userId]);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        return null; // Order not found or doesn't belong to this user
    }
    
    // Get order items with product details
    $itemsStmt = $pdo->prepare("
        SELECT oi.*, p.name, p.price as unit_price, p.image as image_url
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
    $itemsStmt->execute([$orderId]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'order' => $order,
        'items' => $items
    ];
}



?>

<!DOCTYPE html>
<html lang="en">

<head>
    <?php
    include "inc/head.inc.php";
    ?>
    <script src="script.js"></script>
    <script>
    // Debug script to check cart status on load
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Cart contents:', localStorage.getItem('cart'));
        // Force update cart UI on profile page load
        setTimeout(function() {
            if (typeof updateCartUI === 'function') {
                updateCartUI();
                console.log('Cart UI updated');
            }
        }, 500);
        const editButton = document.getElementById('edit-username-btn');
        if (editButton) {
            editButton.addEventListener('click', function() {
            const displayElement = document.getElementById('username-display');
            const formElement = document.getElementById('username-edit-form');
            
            if (formElement.style.display === 'none') {
                displayElement.style.display = 'none';
                formElement.style.display = 'block';
            } else {
                displayElement.style.display = 'inline';
                formElement.style.display = 'none';
            }
        });
    }
    
    // Also add cancel button functionality
    const cancelButton = document.querySelector('#username-edit-form button[type="button"]');
    if (cancelButton) {
        cancelButton.addEventListener('click', function() {
            document.getElementById('username-display').style.display = 'inline';
            document.getElementById('username-edit-form').style.display = 'none';
        });
    }
   
    });
    </script>
</head>


<body>
<a href="#main-content" class="visually-hidden-focusable">Skip to main content</a>
    <?php include "inc/navbar.inc.php"; ?>
    <div id="main-content" class="container py-5 profile-page" role="main">
        <!-- Profile Header Section -->
        <div class="profile-section">
            <div class="profile-header">
                <div class="profile-avatar">
                    <i class="bi bi-person"></i>
                </div>
                <div class="profile-welcome">
                <h1>
                    Welcome, 
                    <span id="username-display"><?php echo htmlspecialchars($username); ?></span>
                    <button type="button" class="btn btn-sm btn-link" id="edit-username-btn" aria-label="Edit username">
                        <i class="bi bi-pencil-square" aria-hidden="true" ></i>
                    </button>
                </h1>
                <form id="username-edit-form" style="display: none;" method="POST" action="profile.php" class="mt-2">
                    <input type="hidden" name="action" value="change_username">
                    <div class="input-group">
                        <input type="text" class="form-control" name="new_username" value="<?php echo htmlspecialchars($username); ?>" required>
                        <button class="btn btn-primary" type="submit">Save</button>
                        <button class="btn btn-secondary" type="button" id="cancel-edit-btn">Cancel</button>
                    </div>
                </form>
                <p class="text-muted">Manage your account details and orders</p>
            </div>



            </div>
            <div class="text-end">
                <form method="post" action="profile.php" class="d-inline">
                    <button type="submit" class="btn btn-outline-danger logout-btn" name="logout" value="logout">
                        <i class="bi bi-box-arrow-right me-1"></i> Sign Out
                    </button>
                </form>
            </div>
        </div>
        <!-- Orders Section -->
        <div class="profile-section">
            <div class="section-title">
                <h2><i class="bi bi-bag me-2"></i>Your Orders</h2>
            </div>
            
            <?php if (empty($orders)): ?>
                <div class="empty-state">
                    <i class="bi bi-bag" style="font-size: 2rem;"></i>
                    <p class="mt-3">You haven't placed any orders yet</p>
                </div>
            <?php else: ?>
                <div class="order-list">
                    <?php foreach ($orders as $order): ?>
                        <div class="card mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h3 class="card-title">Order #<?php echo $order['id']; ?></h3>
                                        <p class="card-text">
                                            <small class="text-muted">
                                                <?php echo date('M j, Y', strtotime($order['order_date'])); ?>
                                            </small>
                                        </p>
                                        <p>
                                            <span class="badge bg-<?php echo $order['status'] == 'completed' ? 'success' : 'primary'; ?>">
                                                <?php echo $order['status']; ?>
                                            </span>
                                        </p>
                                    </div>
                                    <div class="text-end">
                                        <p class="h3" style="font-size: 1.25rem;">$<?php echo number_format($order['total_amount'], 2); ?></p>
                                        <a href="#" class="btn btn-sm btn-outline-primary view-order-details" 
                                        data-order-id="<?php echo $order['id']; ?>"
                                        aria-label="View details for order #<?php echo $order['id']; ?>">View Details</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Payment Methods Section -->
        <div class="row">
            <div class="col-md-6">
                <div class="profile-section">
                    <div class="section-title">
                        <h2><i class="bi bi-credit-card me-2"></i>Payment Methods</h2>
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#paymentModal">Add Payment</button>
                    </div>
                    <?php if (empty($payment_methods)): ?>
    <div class="empty-state">
        <i class="bi bi-credit-card" style="font-size: 2rem;"></i>
        <p class="mt-3">No payment methods saved</p>
    </div>
<?php else: ?>
    <?php foreach ($payment_methods as $payment): ?>
    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between">
                <div>
                    <h3 class="card-title"><?php echo htmlspecialchars($payment['card_name']); ?></h3>
                    <p class="card-text text-muted">Card ending in <?php echo htmlspecialchars($payment['card_last_four']); ?></p>
                    <p class="card-text text-muted">Expires <?php echo htmlspecialchars($payment['expiry_date']); ?></p>
                    <?php if ($payment['is_default']): ?>
                        <span class="badge bg-primary">Default</span>
                    <?php endif; ?>
                </div>
                <div>
                    <form method="POST" action="profile.php" class="d-inline">
                        <input type="hidden" name="action" value="delete_payment">
                        <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-link text-danger">Remove</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>
                    
                    
                </div>
            </div>
            
            <!-- address Section -->
            <div class="col-md-6">
                <div class="profile-section">
                    <div class="section-title">
                        <h2><i class="bi bi-geo-alt me-2"></i>Address</h2>
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addressModal">Add Address</button>
                    </div>
                    
                    <!-- Display saved address or empty state -->
                    <?php if (empty($address)): ?>
                        <div class="empty-state">
                            <i class="bi bi-house" ></i>
                            <p class="mt-3">No address saved</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($address as $address): ?>
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h3 class="card-title"><?php echo htmlspecialchars($address['address']); ?></h3>
                                        <p class="card-text"><?php echo htmlspecialchars($address['street']); ?></p>
                                        <p class="card-text"><?php echo htmlspecialchars($address['city']) . ', ' . htmlspecialchars($address['postal_code']); ?></p>
                                        <p class="card-text"><?php echo htmlspecialchars($address['country']); ?></p>
                                        <?php if ($address['is_default']): ?>
                                            <span class="badge bg-primary">Default</span>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <form method="POST" action="profile.php" class="d-inline">
                                            <input type="hidden" name="action" value="delete_address">
                                            <input type="hidden" name="address_id" value="<?php echo $address['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-link text-danger">Remove</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                                        

                                    </div>
                                </div>
                            </div>
                        </div>

<!-- Address Modal -->
<div class="modal fade" id="addressModal" tabindex="-1" aria-labelledby="addressModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h3 class="modal-title" id="addressModalLabel">Add New Address</h3>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="profile.php">
        <div class="modal-body">
          <input type="hidden" name="action" value="save_address">
          
          <div class="mb-3">
            <label for="address" class="form-label">Address Name</label>
            <input type="text" class="form-control" id="address" name="address" placeholder="Home, Work, etc." required>
          </div>
          
          <div class="mb-3">
            <label for="street" class="form-label">Street Address</label>
            <input type="text" class="form-control" id="street" name="street" required>
          </div>
          
          <div class="row mb-3">
            <div class="col">
              <label for="city" class="form-label">City</label>
              <input type="text" class="form-control" id="city" name="city" required>
            </div>
            <div class="col">
              <label for="postal_code" class="form-label">Postal Code</label>
              <input type="text" class="form-control" id="postal_code" name="postal_code" 
              pattern="\d{6}" title="Postal code must be 6 digits" required>
            </div>
          </div>
          
          <div class="mb-3">
            <label for="country" class="form-label">Country</label>
            <input type="text" class="form-control" id="country" name="country" required>
          </div>
          
          <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="default_address" name="default_address" value="1">
            <label class="form-check-label" for="default_address">Set as default address</label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Address</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h3 class="modal-title" id="paymentModalLabel">Add Payment Method</h3>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="profile.php">
        <div class="modal-body">
          <input type="hidden" name="action" value="save_payment">
          
          <div class="mb-3">
            <label for="card_name" class="form-label">Name on Card</label>
            <input type="text" class="form-control" id="card_name" name="card_name" 
            pattern="[a-zA-Z \'\-\.]*" title="Name should contain only letters and spaces"
            required>
          </div>
          
          <div class="mb-3">
            <label for="card_number" class="form-label">Card Number</label>
            <input type="text" class="form-control" id="card_number" name="card_number" 
                pattern="\d*[\s\d]*" title="Card number should contain only digits"   
                placeholder="XXXX XXXX XXXX XXXX" maxlength="19" required>
          </div>
          
          <div class="row mb-3">
            <div class="col">
              <label for="expiry_date" class="form-label">Expiry Date</label>
              <input type="text" class="form-control" id="expiry_date" name="expiry_date"
              pattern="(0[1-9]|1[0-2])([0-9]{2})" title="Format MMYY" 
                     placeholder="MMYY" maxlength="4" required>
            </div>
            <div class="col">
              <label for="cvv" class="form-label">CVV</label>
              <input type="text" class="form-control" id="cvv" name="cvv" 
                     placeholder="XXX" maxlength="3" required>
            </div>
          </div>
          
          <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="default_payment" name="default_payment" value="1">
            <label class="form-check-label" for="default_payment">Set as default payment method</label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Payment Method</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Order Details Modal -->
<div class="modal fade" id="orderDetailsModal" tabindex="-1" aria-labelledby="orderDetailsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h3 class="modal-title" id="orderDetailsModalLabel">Order Details</h3>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="order-details-content">
        <!-- Order details will be loaded here -->
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>


    <?php include "inc/footer.inc.php"; ?>
    <?php include 'inc/cart.inc.php'; ?>

</body>
</html>