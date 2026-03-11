<?php
if (isset($_GET['ajax'])) {
    require __DIR__ . '/../vendor/autoload.php';
    $config = parse_ini_file('/var/www/private/db-config.ini');
    $pdo = new \PDO("mysql:dbname={$config['dbname']};host={$config['servername']};charset=utf8", "{$config['username']}", "{$config['password']}");
    $db = \Delight\Db\PdoDatabase::fromPdo($pdo);

    if (isset($_GET['q'])) {
        $search = trim($_GET['q']);

        if (strlen($search) > 39) {
            header('Content-Type: application/json');
            echo json_encode([
                'products' => [],
                'message' => 'Search query is too long. Maximum 40 characters allowed.'
            ]);
            exit;
        }
        $search = '%' . $search . '%';
        $stmt = $pdo->prepare('SELECT id, name, price, stock, image FROM products WHERE LOWER(name) LIKE LOWER(?)');
        $stmt->execute([$search]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $response = [
            'products' => $rows,
            'message' => count($rows) === 0 ? 'No products found matching "' . htmlspecialchars($_GET['q']) . '"' : null
        ];
        
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    if (isset($_GET['stock'])) {
        // Handle stock filter
        $stock_status = $_GET['stock'];
        if ($stock_status === 'in') {
            // Get in-stock products
            $rows = $db->select('SELECT id, name, price, stock, image FROM products WHERE stock > 0');
            // Check if all products are out of stock
            $totalProducts = $db->selectValue('SELECT COUNT(*) FROM products');
            $outOfStockProducts = $db->selectValue('SELECT COUNT(*) FROM products WHERE stock <= 0');
            
            $message = null;
            if ($totalProducts === $outOfStockProducts) {
                $message = 'All products are currently out of stock';
            }
            
            $response = [
                'products' => $rows,
                'message' => $message
            ];
        } else {
            // Get out-of-stock products
            $rows = $db->select('SELECT id, name, price, stock, image FROM products WHERE stock <= 0');
            // Check if all products are in stock
            $totalProducts = $db->selectValue('SELECT COUNT(*) FROM products');
            $inStockProducts = $db->selectValue('SELECT COUNT(*) FROM products WHERE stock > 0');
            
            $message = null;
            if ($totalProducts === $inStockProducts) {
                $message = 'All products are currently in stock';
            }
            
            $response = [
                'products' => $rows,
                'message' => $message
            ];
        }
        
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
}

?>