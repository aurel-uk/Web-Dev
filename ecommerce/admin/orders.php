<?php
/**
 * ============================================
 * ADMIN - ORDER MANAGEMENT
 * ============================================
 */

require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../includes/admin_check.php';

$db = getDB();
$viewId = (int)($_GET['view'] ?? 0);

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken($_POST['csrf_token'] ?? '')) {
    $orderId = (int)$_POST['order_id'];
    $status = $_POST['status'] ?? '';

    $validStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
    if (in_array($status, $validStatuses)) {
        $stmt = $db->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$status, $orderId]);
        logActivity('order_status_update', "Order status changed to {$status}", 'order', $orderId);
        setFlashMessage('success', 'Order status updated.');
    }
    redirect(BASE_URL . '/admin/orders.php' . ($viewId ? '?view=' . $viewId : ''));
}

// View single order
$order = null;
$orderItems = [];
if ($viewId) {
    $stmt = $db->prepare("
        SELECT o.*, u.first_name as user_first, u.last_name as user_last, u.email as user_email
        FROM orders o
        JOIN users u ON o.user_id = u.id
        WHERE o.id = ?
    ");
    $stmt->execute([$viewId]);
    $order = $stmt->fetch();

    if ($order) {
        $itemsStmt = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $itemsStmt->execute([$viewId]);
        $orderItems = $itemsStmt->fetchAll();
    }
}

// Get orders list
$statusFilter = $_GET['status'] ?? '';
$search = sanitize($_GET['search'] ?? '');

$where = ['1=1'];
$params = [];

if ($statusFilter) {
    $where[] = "o.status = ?";
    $params[] = $statusFilter;
}
if ($search) {
    $where[] = "(o.order_number LIKE ? OR u.email LIKE ?)";
    $params = array_merge($params, ["%{$search}%", "%{$search}%"]);
}

$whereClause = implode(' AND ', $where);

$orders = $db->prepare("
    SELECT o.*, u.first_name, u.last_name, u.email,
           (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
    FROM orders o
    JOIN users u ON o.user_id = u.id
    WHERE {$whereClause}
    ORDER BY o.created_at DESC
");
$orders->execute($params);
$orders = $orders->fetchAll();

$pageTitle = 'Manage Orders';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> | <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/css/style.css" rel="stylesheet">
    <style>
        .admin-wrapper { display: flex; min-height: 100vh; }
        .admin-sidebar { width: 250px; background: #212529; }
        .admin-content { flex: 1; background: #f8f9fa; }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <div class="admin-content">
            <?php include __DIR__ . '/includes/topbar.php'; ?>

            <div class="p-4">
                <?php $flash = getFlashMessage(); ?>
                <?php if ($flash): ?>
                    <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?> alert-dismissible fade show">
                        <?= sanitize($flash['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($order): ?>
                    <!-- Order Details -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="mb-0">Order <?= sanitize($order['order_number']) ?></h4>
                        <a href="<?= BASE_URL ?>/admin/orders.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Back
                        </a>
                    </div>

                    <div class="row">
                        <div class="col-lg-8">
                            <!-- Order Items -->
                            <div class="card mb-4">
                                <div class="card-header">Order Items</div>
                                <div class="card-body p-0">
                                    <table class="table mb-0">
                                        <thead>
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
                                        <tfoot>
                                            <tr>
                                                <td colspan="3" class="text-end">Subtotal:</td>
                                                <td class="text-end"><?= formatPrice($order['subtotal']) ?></td>
                                            </tr>
                                            <tr>
                                                <td colspan="3" class="text-end">Tax:</td>
                                                <td class="text-end"><?= formatPrice($order['tax_amount']) ?></td>
                                            </tr>
                                            <tr>
                                                <td colspan="3" class="text-end">Shipping:</td>
                                                <td class="text-end"><?= formatPrice($order['shipping_amount']) ?></td>
                                            </tr>
                                            <tr class="table-light">
                                                <td colspan="3" class="text-end"><strong>Total:</strong></td>
                                                <td class="text-end"><strong><?= formatPrice($order['total_amount']) ?></strong></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>

                            <!-- Shipping Address -->
                            <div class="card">
                                <div class="card-header">Shipping Address</div>
                                <div class="card-body">
                                    <p class="mb-0">
                                        <strong><?= sanitize($order['shipping_first_name'] . ' ' . $order['shipping_last_name']) ?></strong><br>
                                        <?= sanitize($order['shipping_address']) ?><br>
                                        <?= sanitize($order['shipping_city']) ?>, <?= sanitize($order['shipping_postal_code']) ?><br>
                                        <?= sanitize($order['shipping_country']) ?><br>
                                        <i class="bi bi-envelope"></i> <?= sanitize($order['shipping_email']) ?><br>
                                        <?php if ($order['shipping_phone']): ?>
                                            <i class="bi bi-telephone"></i> <?= sanitize($order['shipping_phone']) ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <!-- Order Info -->
                            <div class="card mb-4">
                                <div class="card-header">Order Information</div>
                                <div class="card-body">
                                    <table class="table table-sm">
                                        <tr>
                                            <th>Customer:</th>
                                            <td><?= sanitize($order['user_first'] . ' ' . $order['user_last']) ?></td>
                                        </tr>
                                        <tr>
                                            <th>Email:</th>
                                            <td><?= sanitize($order['user_email']) ?></td>
                                        </tr>
                                        <tr>
                                            <th>Date:</th>
                                            <td><?= formatDateTime($order['created_at']) ?></td>
                                        </tr>
                                        <tr>
                                            <th>Payment:</th>
                                            <td>
                                                <?= ucfirst($order['payment_method']) ?>
                                                <span class="badge bg-<?= $order['payment_status'] === 'paid' ? 'success' : 'warning' ?>">
                                                    <?= ucfirst($order['payment_status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <!-- Update Status -->
                            <div class="card">
                                <div class="card-header">Update Status</div>
                                <div class="card-body">
                                    <form method="POST">
                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                        <div class="mb-3">
                                            <select class="form-select" name="status">
                                                <?php foreach (['pending', 'processing', 'shipped', 'delivered', 'cancelled'] as $s): ?>
                                                    <option value="<?= $s ?>" <?= $order['status'] === $s ? 'selected' : '' ?>>
                                                        <?= ucfirst($s) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <button type="submit" class="btn btn-primary w-100">
                                            <i class="bi bi-check-lg"></i> Update Status
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- Orders List -->
                    <h4 class="mb-4">Manage Orders</h4>

                    <!-- Filters -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <div class="col-md-4">
                                    <input type="text" class="form-control" name="search"
                                           placeholder="Order # or email..." value="<?= sanitize($search) ?>">
                                </div>
                                <div class="col-md-3">
                                    <select class="form-select" name="status">
                                        <option value="">All Status</option>
                                        <?php foreach (['pending', 'processing', 'shipped', 'delivered', 'cancelled'] as $s): ?>
                                            <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>>
                                                <?= ucfirst($s) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-secondary w-100">
                                        <i class="bi bi-funnel"></i> Filter
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Orders Table -->
                    <div class="card">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover admin-table mb-0">
                                    <thead>
                                        <tr>
                                            <th>Order #</th>
                                            <th>Customer</th>
                                            <th>Items</th>
                                            <th>Total</th>
                                            <th>Status</th>
                                            <th>Payment</th>
                                            <th>Date</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($orders as $o): ?>
                                            <?php
                                            $statusColors = [
                                                'pending' => 'warning',
                                                'processing' => 'info',
                                                'shipped' => 'primary',
                                                'delivered' => 'success',
                                                'cancelled' => 'danger'
                                            ];
                                            ?>
                                            <tr>
                                                <td><strong><?= sanitize($o['order_number']) ?></strong></td>
                                                <td>
                                                    <?= sanitize($o['first_name'] . ' ' . $o['last_name']) ?>
                                                    <div class="text-muted small"><?= sanitize($o['email']) ?></div>
                                                </td>
                                                <td><?= $o['item_count'] ?></td>
                                                <td><?= formatPrice($o['total_amount']) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $statusColors[$o['status']] ?? 'secondary' ?>">
                                                        <?= ucfirst($o['status']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= $o['payment_status'] === 'paid' ? 'success' : 'warning' ?>">
                                                        <?= ucfirst($o['payment_status']) ?>
                                                    </span>
                                                </td>
                                                <td><?= formatDate($o['created_at']) ?></td>
                                                <td>
                                                    <a href="?view=<?= $o['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-eye"></i> View
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer">Total: <?= count($orders) ?> orders</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
