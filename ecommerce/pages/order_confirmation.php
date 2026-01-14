<?php
/**
 * ============================================
 * ORDER CONFIRMATION PAGE
 * ============================================
 *
 * Displayed after successful order placement.
 * Shows order details and confirmation message.
 * ============================================
 */

require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../includes/auth_check.php';

$db = getDB();

// Get order from session or URL
$orderId = $_SESSION['last_order_id'] ?? null;
$orderNumber = $_SESSION['last_order_number'] ?? null;

// Clear from session
unset($_SESSION['last_order_id'], $_SESSION['last_order_number']);

if (!$orderId && isset($_GET['order'])) {
    // Verify order belongs to user
    $stmt = $db->prepare("SELECT id, order_number FROM orders WHERE order_number = ? AND user_id = ?");
    $stmt->execute([$_GET['order'], $_SESSION['user_id']]);
    $result = $stmt->fetch();
    if ($result) {
        $orderId = $result['id'];
        $orderNumber = $result['order_number'];
    }
}

if (!$orderId) {
    setFlashMessage('error', 'Order not found.');
    redirect(BASE_URL . '/pages/orders.php');
}

// Fetch order details
$stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

// Fetch order items
$itemsStmt = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
$itemsStmt->execute([$orderId]);
$orderItems = $itemsStmt->fetchAll();

$pageTitle = 'Order Confirmation';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- Success Message -->
            <div class="text-center mb-5">
                <div class="mb-4">
                    <i class="bi bi-check-circle-fill text-success" style="font-size: 5rem;"></i>
                </div>
                <h1 class="fw-bold text-success">Order Confirmed!</h1>
                <p class="lead text-muted">
                    Thank you for your purchase. Your order has been received.
                </p>
            </div>

            <!-- Order Details Card -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Order Details</h5>
                    <span class="badge bg-primary fs-6"><?= sanitize($order['order_number']) ?></span>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6 class="text-muted">Order Date</h6>
                            <p><?= formatDateTime($order['created_at']) ?></p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted">Payment Method</h6>
                            <p><?= ucfirst($order['payment_method']) ?></p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted">Order Status</h6>
                            <span class="badge bg-info"><?= ucfirst($order['status']) ?></span>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted">Payment Status</h6>
                            <span class="badge bg-success"><?= ucfirst($order['payment_status']) ?></span>
                        </div>
                    </div>

                    <hr>

                    <h6 class="mb-3">Shipping Address</h6>
                    <p class="mb-0">
                        <?= sanitize($order['shipping_first_name'] . ' ' . $order['shipping_last_name']) ?><br>
                        <?= sanitize($order['shipping_address']) ?><br>
                        <?= sanitize($order['shipping_city']) ?>, <?= sanitize($order['shipping_postal_code']) ?><br>
                        <?= sanitize($order['shipping_country']) ?>
                    </p>
                </div>
            </div>

            <!-- Order Items -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Order Items</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-striped mb-0">
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
                    </table>
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-md-6 offset-md-6">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Subtotal:</span>
                                <span><?= formatPrice($order['subtotal']) ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Tax:</span>
                                <span><?= formatPrice($order['tax_amount']) ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Shipping:</span>
                                <span><?= formatPrice($order['shipping_amount']) ?></span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between">
                                <strong>Total:</strong>
                                <strong class="fs-5"><?= formatPrice($order['total_amount']) ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="d-flex justify-content-between">
                <a href="<?= BASE_URL ?>/pages/products.php" class="btn btn-outline-primary">
                    <i class="bi bi-shop"></i> Continue Shopping
                </a>
                <a href="<?= BASE_URL ?>/pages/orders.php" class="btn btn-primary">
                    <i class="bi bi-bag"></i> View All Orders
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
