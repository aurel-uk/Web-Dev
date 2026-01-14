<?php
/**
 * ============================================
 * USER ORDERS PAGE
 * ============================================
 *
 * Displays the user's order history.
 * Features:
 * - List all orders
 * - View order details
 * - Track order status
 *
 * ============================================
 */

require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../includes/auth_check.php';

$db = getDB();

// Get user's orders
$stmt = $db->prepare("
    SELECT o.*, COUNT(oi.id) as item_count
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.user_id = ?
    GROUP BY o.id
    ORDER BY o.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$orders = $stmt->fetchAll();

// View single order details
$viewOrder = null;
$orderItems = [];
if (isset($_GET['view'])) {
    $orderNumber = sanitize($_GET['view']);
    $viewStmt = $db->prepare("SELECT * FROM orders WHERE order_number = ? AND user_id = ?");
    $viewStmt->execute([$orderNumber, $_SESSION['user_id']]);
    $viewOrder = $viewStmt->fetch();

    if ($viewOrder) {
        $itemsStmt = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $itemsStmt->execute([$viewOrder['id']]);
        $orderItems = $itemsStmt->fetchAll();
    }
}

$pageTitle = $viewOrder ? 'Order Details' : 'My Orders';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/index.php">Home</a></li>
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/pages/profile.php">My Account</a></li>
            <?php if ($viewOrder): ?>
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/pages/orders.php">Orders</a></li>
                <li class="breadcrumb-item active"><?= sanitize($viewOrder['order_number']) ?></li>
            <?php else: ?>
                <li class="breadcrumb-item active">Orders</li>
            <?php endif; ?>
        </ol>
    </nav>

    <?php if ($viewOrder): ?>
        <!-- Single Order Details -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold mb-0">
                <i class="bi bi-receipt"></i> Order <?= sanitize($viewOrder['order_number']) ?>
            </h2>
            <a href="<?= BASE_URL ?>/pages/orders.php" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left"></i> Back to Orders
            </a>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <!-- Order Status -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row text-center">
                            <?php
                            $statuses = ['pending', 'processing', 'shipped', 'delivered'];
                            $currentStatusIndex = array_search($viewOrder['status'], $statuses);
                            if ($currentStatusIndex === false) $currentStatusIndex = -1;
                            ?>
                            <?php foreach ($statuses as $i => $status): ?>
                                <div class="col">
                                    <div class="position-relative">
                                        <div class="rounded-circle d-inline-flex align-items-center justify-content-center
                                                    <?= $i <= $currentStatusIndex ? 'bg-success text-white' : 'bg-light' ?>"
                                             style="width: 50px; height: 50px;">
                                            <?php if ($i < $currentStatusIndex): ?>
                                                <i class="bi bi-check-lg fs-4"></i>
                                            <?php elseif ($i == $currentStatusIndex): ?>
                                                <i class="bi bi-<?php
                                                    echo $status === 'pending' ? 'clock' :
                                                        ($status === 'processing' ? 'gear' :
                                                        ($status === 'shipped' ? 'truck' : 'check-circle'));
                                                ?> fs-5"></i>
                                            <?php else: ?>
                                                <i class="bi bi-circle fs-5 text-muted"></i>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="small mt-2 <?= $i <= $currentStatusIndex ? 'fw-bold' : 'text-muted' ?>">
                                        <?= ucfirst($status) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Order Items -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Order Items</h5>
                    </div>
                    <div class="card-body p-0">
                        <table class="table mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Product</th>
                                    <th class="text-center">Quantity</th>
                                    <th class="text-end">Price</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orderItems as $item): ?>
                                    <tr>
                                        <td><?= sanitize($item['product_name']) ?></td>
                                        <td class="text-center"><?= $item['quantity'] ?></td>
                                        <td class="text-end"><?= formatPrice($item['product_price']) ?></td>
                                        <td class="text-end"><?= formatPrice($item['total']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Order Summary -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Order Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal:</span>
                            <span><?= formatPrice($viewOrder['subtotal']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Tax:</span>
                            <span><?= formatPrice($viewOrder['tax_amount']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Shipping:</span>
                            <span><?= formatPrice($viewOrder['shipping_amount']) ?></span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between">
                            <strong>Total:</strong>
                            <strong class="fs-5"><?= formatPrice($viewOrder['total_amount']) ?></strong>
                        </div>

                        <hr>

                        <div class="mb-2">
                            <strong>Payment Method:</strong><br>
                            <?= ucfirst($viewOrder['payment_method']) ?>
                        </div>
                        <div>
                            <strong>Payment Status:</strong><br>
                            <span class="badge bg-<?= $viewOrder['payment_status'] === 'paid' ? 'success' : 'warning' ?>">
                                <?= ucfirst($viewOrder['payment_status']) ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Shipping Address -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Shipping Address</h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-0">
                            <?= sanitize($viewOrder['shipping_first_name'] . ' ' . $viewOrder['shipping_last_name']) ?><br>
                            <?= sanitize($viewOrder['shipping_address']) ?><br>
                            <?= sanitize($viewOrder['shipping_city']) ?>, <?= sanitize($viewOrder['shipping_postal_code']) ?><br>
                            <?= sanitize($viewOrder['shipping_country']) ?><br>
                            <?php if ($viewOrder['shipping_phone']): ?>
                                <i class="bi bi-telephone"></i> <?= sanitize($viewOrder['shipping_phone']) ?>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- Orders List -->
        <h2 class="fw-bold mb-4"><i class="bi bi-bag"></i> My Orders</h2>

        <?php if (empty($orders)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="bi bi-bag-x text-muted" style="font-size: 5rem;"></i>
                    <h4 class="mt-4">No orders yet</h4>
                    <p class="text-muted mb-4">
                        You haven't placed any orders yet. Start shopping!
                    </p>
                    <a href="<?= BASE_URL ?>/pages/products.php" class="btn btn-primary btn-lg">
                        <i class="bi bi-shop"></i> Browse Products
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Order</th>
                                    <th>Date</th>
                                    <th>Items</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Payment</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td>
                                            <strong><?= sanitize($order['order_number']) ?></strong>
                                        </td>
                                        <td><?= formatDate($order['created_at']) ?></td>
                                        <td><?= $order['item_count'] ?> item(s)</td>
                                        <td><?= formatPrice($order['total_amount']) ?></td>
                                        <td>
                                            <?php
                                            $statusColors = [
                                                'pending' => 'warning',
                                                'processing' => 'info',
                                                'shipped' => 'primary',
                                                'delivered' => 'success',
                                                'cancelled' => 'danger',
                                                'refunded' => 'secondary'
                                            ];
                                            ?>
                                            <span class="badge bg-<?= $statusColors[$order['status']] ?? 'secondary' ?>">
                                                <?= ucfirst($order['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $order['payment_status'] === 'paid' ? 'success' : 'warning' ?>">
                                                <?= ucfirst($order['payment_status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="?view=<?= sanitize($order['order_number']) ?>"
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
