<?php
// Enable error reporting (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

include "inc/head.inc.php";
include "inc/navbar.inc.php";

// Database setup
require __DIR__ . '/vendor/autoload.php';
$config = parse_ini_file('/var/www/private/db-config.ini');
$pdo = new PDO(
    "mysql:dbname={$config['dbname']};host={$config['servername']};charset=utf8",
    $config['username'],
    $config['password']
);
$auth = new Delight\Auth\Auth($pdo);

function isAdmin($auth)
{
    return $auth->hasAnyRole(
        \Delight\Auth\Role::ADMIN
    );
}

if (!isAdmin($auth)) {
    header('Location: index.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isAdmin($auth)) {
    // Input validation
    $name = trim($_POST['name']);
    $price = (float) $_POST['price'];
    $stock = (int) $_POST['stock'];
    $description = trim($_POST['description']);

    // Image upload handling
    $uploadDir = __DIR__ . '/uploads/products/';
    $imagePath = 'default-product.jpg';

    if (!empty($_FILES['product-image']['name'])) {
        // Create directory if needed
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // File info
        $fileName = $_FILES['product-image']['name'];
        $fileTmp = $_FILES['product-image']['tmp_name'];
        $fileSize = $_FILES['product-image']['size'];
        $fileError = $_FILES['product-image']['error'];

        // Generate safe filename
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($fileExt, $allowed)) {
            if ($fileError === 0) {
                if ($fileSize < 5000000) { // 5MB max
                    $newFileName = uniqid('', true) . '.' . $fileExt;
                    $destination = $uploadDir . $newFileName;

                    if (move_uploaded_file($fileTmp, $destination)) {
                        $imagePath = 'uploads/products/' . $newFileName;
                    }
                }
            }
        }
    }

    // Insert into database
    try {
        $db = Delight\Db\PdoDatabase::fromPdo($pdo);
        $db->insert('products', [
            'name' => $name,
            'description' => $description,
            'price' => $price,
            'stock' => $stock,
            'image' => $imagePath,
            'featured' => 0 // Default to not featured
        ]);

        header('Location: admin.php');
        exit;
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <?php
    include "inc/head.inc.php";
    ?>
</head>

<!-- HTML Form -->

<body>
    <main>
        <div class="product-page container">
            <div class="row">
                <section class="col-8">
                    <h2>Create New Product</h2>

                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form method="post" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="name">Product Name</label>
                            <input type="text" id="name" name="name" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label for="price">Price (SGD)</label>
                            <input type="number" id="price" name="price" step="0.01" min="0.01" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label for="stock">Initial Stock</label>
                            <input type="number" id="stock" name="stock" min="0" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label for="description">Description</label>
                            <textarea name="description" id="description" class="form-control" required></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="image">Product Image</label>
                            <input type="file" id="image" name="product-image" class="form-control">
                            <small class="text-muted">Max 5MB (JPG, PNG, GIF)</small>
                        </div>

                        <button type="submit" class="btn">Create Product</button>
                    </form>
                </section>

                <div class="col-4">
                    <div class="card">
                        <img id="image-preview" src="images/default-product.jpg" alt="default-product"
                            class="card-img-top" style="max-width: 250px;">
                        <div class="card-body">
                            <p class="card-text">Image Preview</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </main>
</body>


<script>
    // Live preview script
    document.querySelector('[name="product-image"]').addEventListener('change', function (e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function (event) {
                document.getElementById('image-preview').src = event.target.result;
            };
            reader.readAsDataURL(file);
        }
    });
</script>

<?php include "inc/footer.inc.php"; ?>