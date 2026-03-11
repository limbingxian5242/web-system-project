<!DOCTYPE html>
<html lang="en">

<head>
    <?php
    include "inc/head.inc.php";
    ?>
</head>

<body>
    <?php include "inc/navbar.inc.php"; 
        require __DIR__ . '/vendor/autoload.php';
        $config = parse_ini_file('/var/www/private/db-config.ini');
        $dbSchema = 'project';
        $pdo = new \PDO("mysql:dbname={$config['dbname']};host={$config['servername']};charset=utf8", "{$config['username']}", "{$config['password']}");
        $db = \Delight\Db\PdoDatabase::fromPdo($pdo);
    
        $auth = new \Delight\Auth\Auth($pdo, null, null, null, null, $dbSchema);
    
        function isAdmin($auth)
        {
            return $auth->hasAnyRole(
                \Delight\Auth\Role::ADMIN
            );
        }
    ?>
    
    <main>
    <div class="container-fluid">
        <hr class="border border-3 opacity-100">
        <figure class="text-center py-3">
            <h1>All Products</h1>
        </figure>

        <div class="row">
            <!-- Filter Sidebar -->
            <div class="col-md-2 filter-section">
                <?php
                if (isAdmin($auth)) {
                    echo '
                <div class="mb-4">
                    <a href="create_product.php" class="btn">Add New Product</a>
                </div>
                <div class="mb-4">
                    <a href="admin.php" class="btn">Admin Page</a>
                </div>';
                }
                ?>
                <div class="filter-header" onclick="toggleFilter()">
                    Filter <span class="toggle-icon">+</span>
                </div>
                <div class="filter-content" style="display: none;">
                    <div class="availability-section">
                        <div class="availability-header" onclick="toggleAvailability()">
                            Availability <span class="availability-toggle">+</span>
                        </div>
                        <div class="availability-content" style="display: none;">
                            <div class="stock-options">
                                <div class="stock-option" onclick="filterByStock('in')">In Stock</div>
                                <div class="stock-option" onclick="filterByStock('out')">Out of Stock</div>
                            </div>
                        </div>
                    </div>
                    <div class="search-container">
                        <input type="text" class="search-input" placeholder="Search" onkeyup="searchProducts(this.value)" maxlength="40">
                    </div>
                </div>
            </div>

            <!-- Modify your existing products container -->
            <div class="col-md-10 products-container">
                <?php
                //connect to database
                require __DIR__ . '/vendor/autoload.php';
                $config = parse_ini_file('/var/www/private/db-config.ini');
                $dbSchema = 'project';
                $pdo = new \PDO("mysql:dbname={$config['dbname']};host={$config['servername']};charset=utf8", "{$config['username']}", "{$config['password']}");
                $db = \Delight\Db\PdoDatabase::fromPdo($pdo);
                //query to fetch the products from the database
                $rows = $db->select('SELECT id, name, price, stock, image FROM products;');

                echo '<div class="container text-center">';
                echo '<div class="row row-cols-3">';
                
                // print the whole data from the products table
                foreach ($rows as $row) {
                    echo '<div class="col-12 col-md-6 col-lg-4">
                        <a href="product.php?id='. $row['id'] .'" class="product-card-link">
                            <div class="product-card position-relative text-center">
                                <img src="'. $row['image'] .'" alt="'. $row['name'] .'">
                                <div class="product-info text-center" style="margin-top: 60px; min-height: 120px;">
                                    <h2 class="product-name" style="white-space: nowrap; height: 1.5em; margin-bottom: 8px;">'. $row['name'] .'</h2>
                                    <p class="product-price" style="margin-bottom: 0;">from SGD'. $row['price'] .'</p>';
                                    if ($row['stock'] <= 0){
                                        echo '<p style="margin-top: 4px; color: black; font-size: 1em;letter-spacing: 0.1em;font-weight: 500;">Sold Out</p>';
                                    }
                    echo '</div>  
                            </div>
                        </a>
                    </div>';
                }
                echo '</div>';
                echo '</div>';
                        ?>
            </div>
        </div>
    </div>
    </main>
    
    <?php include 'inc/cart.inc.php'; ?>
    <script src="./product.js"></script>
    <script src="script.js"></script>
    <link rel="stylesheet" href="css/products.css">
    <?php include "inc/footer.inc.php"; ?>
</body>

</html>