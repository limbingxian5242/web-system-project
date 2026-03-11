<!DOCTYPE html>
<html lang="en">
<head>
    <?php
    // Head includes, metadata, CSS links, etc.
    include "inc/head.inc.php";
    ?>
</head>
<body class="product-body">
<?php include "inc/navbar.inc.php"; ?>

<?php
// Enable error reporting (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Autoload, and database configuration
require __DIR__ . '/vendor/autoload.php';

$config = parse_ini_file('/var/www/private/db-config.ini');
$dbSchema = 'project';
$pdo = new \PDO(
    "mysql:dbname={$config['dbname']};host={$config['servername']};charset=utf8",
    $config['username'],
    $config['password']
);
$db = \Delight\Db\PdoDatabase::fromPdo($pdo);

// Create the Auth object
$auth = new \Delight\Auth\Auth($pdo, null, null, null, null, $dbSchema);

/**
 * Helper function to check admin status
 */
function isAdmin($auth) {
    return $auth->hasAnyRole(\Delight\Auth\Role::ADMIN);
}

// ----------------------------------------------------------------
// 1) Handle form submissions for admin actions (restock, unstock, etc.)
//    This should happen BEFORE we load product details, so that
//    updates occur prior to re-fetching the product info.
// ----------------------------------------------------------------
if (isset($_POST['restock'])) {
    $id = $_GET['id'] ?? 0;
    // Grab the current stock
    $row = $db->selectRow('SELECT stock FROM products WHERE id = ?', [intval($id)]);
    if ($row && isAdmin($auth)) {
        $amount = filter_var($_POST['restock-quantity'], FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1]
        ]);
        if ($amount !== false) {
            $newStock = $row['stock'] + $amount;
            $db->update('products', ['stock' => $newStock], ['id' => intval($id)]);
            header("Location: product.php?id=$id");
            exit;
        }
    }
}

if (isset($_POST['unstock'])) {
    $id = $_GET['id'] ?? 0;
    $row = $db->selectRow('SELECT stock FROM products WHERE id = ?', [intval($id)]);
    if ($row && isAdmin($auth)) {
        $amount = filter_var($_POST['unstock-quantity'], FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => $row['stock']]
        ]);
        if ($amount !== false) {
            $newStock = $row['stock'] - $amount;
            if ($newStock >= 0) {
                $db->update('products', ['stock' => $newStock], ['id' => intval($id)]);
                header("Location: product.php?id=$id");
                exit;
            } else {
                // Handle error - shouldn't happen due to max_range
                $_SESSION['error'] = "Cannot unstock more than available inventory";
                header("Location: product.php?id=$id");
                exit;
            }
        } else {
            $_SESSION['error'] = "Invalid unstock quantity (1–{$row['stock']})";
            header("Location: product.php?id=$id");
            exit;
        }
    }
}

if (isset($_POST['change-price'])) {
    $id = $_GET['id'] ?? 0;
    if (isAdmin($auth)) {
        $newPrice = filter_var($_POST['new-price'], FILTER_VALIDATE_FLOAT, [
            'options' => ['min_range' => 0.01]
        ]);
        if ($newPrice !== false) {
            try {
                $db->update('products', ['price' => $newPrice], ['id' => intval($id)]);
                // Success
                header("Location: product.php?id=$id");
                exit;
            } catch (Exception $e) {
                $_SESSION['error'] = "Price update failed: " . htmlspecialchars($e->getMessage());
                header("Location: product.php?id=$id");
                exit;
            }
        } else {
            $_SESSION['error'] = "Invalid price (must be a positive number)";
            header("Location: product.php?id=$id");
            exit;
        }
    }
}

if (isset($_POST['delete'])) {
    $id = $_GET['id'] ?? 0;
    if (isAdmin($auth)) {
        $confirmDelete = filter_var($_POST['confirm-delete'], FILTER_SANITIZE_STRING);
        if ($confirmDelete === "DELETE") {
            try {
                $db->delete('products', ['id' => intval($id)]);
                // Success
                header("Location: admin.php"); // or wherever you want to redirect
                exit;
            } catch (Exception $e) {
                $_SESSION['error'] = "Delete failed: " . htmlspecialchars($e->getMessage());
                header("Location: product.php?id=$id");
                exit;
            }
        } else {
            $_SESSION['error'] = "Type 'DELETE' to confirm deletion";
            header("Location: product.php?id=$id");
            exit;
        }
    }
}

// ----------------------------------------------------------------
// 2) Now retrieve and display the product details
// ----------------------------------------------------------------

// Build the link for "Back to products"
$current_params = ['id' => $_GET['id'] ?? ''];
$url = 'products.php?' . http_build_query($current_params);
$id = $current_params['id'];

// Fetch product details
$row = $db->selectRow(
    'SELECT name, description, price, image, stock
    FROM products
    WHERE id = ?
    LIMIT 1',
    [intval($id)]
);
?>
<main>
<?php
if (empty($row)) {
    // Product not found
    echo '<div class="container">
            <p>Product not found</p>
            <a href="'. $url .'">Back to products</a>
        </div>';
}
else {
    // Destructure product data
    $product_name = $row['name'];
    $description  = $row['description'];
    $price        = $row['price'];
    $image        = $row['image'];
    $stock        = $row['stock'];

    // Display product details
    echo '<div class="product-page container py-4">
            <div class="row">
                <div class="col-md-4 col-lg-4 d-flex justify-content-center mb-4 mb-md-0">
                    <div class="image-wrapper">
                        <img src="'. htmlspecialchars($image) .'" 
                            alt="'. htmlspecialchars($product_name) .'" 
                            class="img-fluid">
                    </div>
                </div>
                <section class="product-desc col-md-8 col-lg-8 product-info text-start">
                    <h1 class="visually-hidden">Product details</h1>
                    <a href="allProducts.php" class="btn btn-outline-dark rounded-pill mb-3">Back</a>
                    <p class="product-name">' . htmlspecialchars($product_name) . '</p>
                    <p class="product-price">SGD ' . number_format($price, 2) . '</p>
                    <p class="desc">' . htmlspecialchars($description) . '</p>';

    // Stock check
    if ($stock > 0) {
        echo '<p class="stock">In Stock</p>
                <div class="quantity">
                    <label for="quantity">Quantity:</label>
                    <input type="number" id="quantity" name="quantity" min="1" max="10" value="1">
                </div>
                <button class="btn btn-outline-dark rounded-pill add-to-cart-btn" onclick="toggleCart()"
                        data-id="'. $id .'" 
                        data-name="'. htmlspecialchars($product_name, ENT_QUOTES) .'" 
                        data-price="'. $price .'" 
                        data-image="'. htmlspecialchars($image, ENT_QUOTES) .'">

                    Add to Cart
                </button>';
    }
    else {
        echo '<p style="margin-top:4px;color:black;font-size:1em;letter-spacing:0.1em;font-weight:500;">
                Sold Out
            </p>';
    }

    echo '</section>
        </div>';

    // ----------------------------------------------------------------
    // 3) If user is Admin, show the admin control forms
    // ----------------------------------------------------------------
    if (isAdmin($auth)) {
        echo '<hr class="border border-2 opacity-100">
            <div class="row">
                <section class="product-desc col-8 col-lg product-info text-start" style="align-item: left;">
                    
                    <!-- Restock form -->
                    <form method="post" class="quantity">
                        <label for="restock-quantity">Restock:</label>
                        <input type="number" id="restock-quantity" name="restock-quantity" 
                            min="1" max="100" value="1" required>
                        <button type="submit" name="restock" 
                                class="btn btn-outline-dark rounded-pill">
                            Restock
                        </button>
                    </form>
                    
                    <!-- Unstock form -->
                    <form method="post" class="quantity">
                        <label for="unstock-quantity">Unstock:</label>
                        <input type="number" id="unstock-quantity" name="unstock-quantity" 
                            min="1" max="100" value="1" required>
                        <button type="submit" name="unstock" 
                                class="btn btn-outline-dark rounded-pill">
                            Unstock
                        </button>
                    </form>
                    
                    <!-- Change Price form -->
                    <form method="post" class="quantity">
                        <label for="new-price">Change Price (SGD):</label>
                        <input type="number" id="new-price" name="new-price" 
                            min="0.01" step="0.01" 
                            value="' . htmlspecialchars($price) . '" required>
                        <button type="submit" name="change-price" 
                                class="btn btn-outline-dark rounded-pill">
                            Update Price
                        </button>
                    </form>
                    
                    <!-- Delete product form -->
                    <form method="post" class="quantity">
                        <div class="mb-3">
                            <label for="confirm-delete" class="form-label">Delete Product</label>
                            <input type="text" class="form-control" id="confirm-delete" 
                                name="confirm-delete" required>
                            <div class="form-text" id="basic-addon4">
                                Type "DELETE" to confirm deletion
                            </div>
                            <button type="submit" name="delete" 
                                    class="btn btn-danger rounded-pill">
                                Delete
                            </button>
                        </div>
                    </form>
                </section>
            </div>';
    }

    // Close main product container
    echo '</div>';
} // end else (product found) 
?>
</main>

<?php
// ----------------------------------------------------------------
// 4) Include cart functionality and footer
// ----------------------------------------------------------------
include 'inc/cart.inc.php';
?>

<script src="script.js"></script>
<?php include "inc/footer.inc.php"; ?>

</body>
</html>
