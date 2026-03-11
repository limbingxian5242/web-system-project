<section class="featured-products" aria-labelledby="featured-products-heading">
    <div class="container-fluid">
        <div class="row">
            <div class="col">
            <h1 id="featured-products-heading" class="featured-title mb-4">Featured Products</h1>
                <div class="mb-4">
                    <a href="allProducts.php" class="btn btn-outline-dark rounded-pill shop-now-btn">View All</a>
                </div>
            </div>
        </div>

        <div class="row">
            <?php
            require __DIR__ . '/../vendor/autoload.php';
            $config = parse_ini_file('/var/www/private/db-config.ini');
            $dbSchema = 'project';
            $pdo = new \PDO("mysql:dbname={$config['dbname']};host={$config['servername']};charset=utf8", "{$config['username']}", "{$config['password']}");
            $db = \Delight\Db\PdoDatabase::fromPdo($pdo);

            $rows = $db->select('SELECT id, name, price, image, stock FROM products WHERE featured = 1 LIMIT 3;');
            foreach ($rows as $row) {
                echo '<div class="col-12 col-lg-4 d-flex justify-content-center mb-4">
                <a href="product.php?id='. $row['id'] .'" class="product-card-link">
                    <div class="product-card position-relative text-center" style="width: 40%;">
                        <img src="'. $row['image'] .'" alt="'. $row['name'] .'">
                        <div class="product-info text-center" style="margin-top: 60px;">
                            <h2 class="product-name">'. $row['name'] .'</h2>
                            <p class="product-price">from SGD'. $row['price'] .'</p>
                            <div style="height: 24px;">'. ($row['stock'] == 0 ? '<p style="margin: 0; color: black; font-size: 1em; letter-spacing: 0.1em; font-weight: 500;">Sold Out</p>' : '') .'</div>
                        </div>
                    </div>
                </a>
            </div>';
            }
            ?>
        </div>
    </div>
</section>