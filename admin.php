<!DOCTYPE html>
<html lang="en">

<head>
    <?php
    include "inc/head.inc.php";
    ?>
</head>

<body>
    <?php
    include "inc/navbar.inc.php";

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

    if (!isAdmin($auth)) {
        echo "Unauthorized.";
        header('Location: index.php');
        exit;
    }

    // Handle form submission
    if (isset($_POST['complete_order'])) {
        $order_id = $_POST['order_id'];

        $row = $db->selectRow('SELECT status FROM orders WHERE id = ?', [intval($order_id)]);
        if ($row && $row['status'] != 'completed' && isAdmin($auth)) {
            // Update order status to completed
            $db->update('orders', ['status' => 'completed'], ['id' => $order_id]);
            echo '<div class="alert alert-success">Order completed successfully.</div>';
        } else {
            echo '<div class="alert alert-danger">Order not found or already completed.</div>';
        }

        header('Location: admin.php');
        exit();
    }
    ?>
    <main>
        <div class="container py-5 profile-page">
            <h1 class="text-center mb-4">Admin Dashboard</h1>
            <p class="text-center mb-4">Manage orders here.</p>
            <!-- Orders Section -->
            <div class="profile-section">
                <div class="section-title">
                    <h2><i class="bi bi-bag me-2"></i>Customer Orders</h2>
                </div>

                <?php
                if (isAdmin($auth)) {
                    $orders = $db->select(query: 'SELECT * FROM orders;');

                    if (empty($orders)) {
                        echo '<div class="empty-state">
                    <i class="bi bi-bag" style="font-size: 2rem;"></i>
                    <p class="mt-3">No Orders Yet</p>
                </div>';
                    } else {
                        echo '<ul class="list-group">';
                        foreach ($orders as $order) {
                            echo '
                <li class="list-group-item">
        <div class="d-flex justify-content-between align-items-center">
            <div class="col-3">
                <p class="fs-4">Order #' . $order['id'] . '</p>
            </div>
            <div class="col-3">
                <p class="mb-0">' . $order['customer_name'] . '</p>
            </div>
            <div class="col-2">
                <p class="mb-0">SGD ' . $order['total_amount'] . '</p>
            </div>
            <div class="col-2">
                <p class="mb-0">' . $order['status'] . '</p>
            </div>
            <div class="col-2 text-end">
                ' . ($order['status'] != 'completed' ?
                                '<form method="post" action="admin.php" class="d-inline">
                    <input type="hidden" name="order_id" value="' . $order['id'] . '">
                    <button type="submit" name="complete_order" class="btn btn-success btn-sm">
                        Complete Order
                    </button>
                </form>'
                                : '') . '
            </div>
        </div>
    </li>';
                        }
                        echo '</ul>';
                    }


                }
                ?>

            </div>
        </div>
    </main>
    <?php
    include "inc/footer.inc.php";
    include 'inc/cart.inc.php';
    ?>
    <script src="script.js"></script>
</body>