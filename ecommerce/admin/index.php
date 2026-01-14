<?php
/**
 * ============================================
 * ADMIN DASHBOARD
 * ============================================
 *
 * Main admin panel dashboard showing:
 * - Key statistics
 * - Recent orders
 * - Recent users
 * - Quick actions
 *
 * ============================================
 */

require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../includes/admin_check.php';

$db = getDB();

// Get statistics
$stats = [];

// Total users
$stats['total_users'] = $db->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();

// Total orders
$stats['total_orders'] = $db->query("SELECT COUNT(*) FROM orders")->fetchColumn();

// Total revenue
$stats['total_revenue'] = $db->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE payment_status = 'paid'")->fetchColumn();

// Total products
$stats['total_products'] = $db->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn();

// Pending orders
$stats['pending_orders'] = $db->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();

// Low stock products
$stats['low_stock'] = $db->query("SELECT COUNT(*) FROM products WHERE stock_quantity <= 5 AND status = 'active'")->fetchColumn();

// Recent orders
$recentOrders = $db->query("
    SELECT o.*, u.first_name, u.last_name, u.email
    FROM orders o
    JOIN users u ON o.user_id = u.id
    ORDER BY o.created_at DESC
    LIMIT 5
")->fetchAll();

// Recent users
$recentUsers = $db->query("
    SELECT id, first_name, last_name, email, created_at, status
    FROM users
    WHERE role = 'user'
    ORDER BY created_at DESC
    LIMIT 5
")->fetchAll();

$pageTitle = 'Admin Dashboard';
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
        .stat-card { transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-5px); }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="admin-content">
            <!-- Top Bar -->
            <?php include __DIR__ . '/includes/topbar.php'; ?>

            <!-- Dashboard Content -->
            <div class="p-4">
                <h4 class="mb-4">Dashboard Overview</h4>

                <!-- Stats Cards -->
                <div class="row g-4 mb-4">
                    <div class="col-md-6 col-xl-3">
                        <div class="card stat-card border-0 shadow-sm">
                            <div class="card-body d-flex align-items-center">
                                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                                    <i class="bi bi-people"></i>
                                </div>
                                <div class="ms-3">
                                    <div class="stat-value"><?= number_format($stats['total_users']) ?></div>
                                    <div class="text-muted small">Total Users</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="card stat-card border-0 shadow-sm">
                            <div class="card-body d-flex align-items-center">
                                <div class="stat-icon bg-success bg-opacity-10 text-success">
                                    <i class="bi bi-bag-check"></i>
                                </div>
                                <div class="ms-3">
                                    <div class="stat-value"><?= number_format($stats['total_orders']) ?></div>
                                    <div class="text-muted small">Total Orders</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="card stat-card border-0 shadow-sm">
                            <div class="card-body d-flex align-items-center">
                                <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                                    <i class="bi bi-currency-dollar"></i>
                                </div>
                                <div class="ms-3">
                                    <div class="stat-value"><?= formatPrice($stats['total_revenue']) ?></div>
                                    <div class="text-muted small">Total Revenue</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="card stat-card border-0 shadow-sm">
                            <div class="card-body d-flex align-items-center">
                                <div class="stat-icon bg-info bg-opacity-10 text-info">
                                    <i class="bi bi-box-seam"></i>
                                </div>
                                <div class="ms-3">
                                    <div class="stat-value"><?= number_format($stats['total_products']) ?></div>
                                    <div class="text-muted small">Active Products</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Alerts -->
                <?php if ($stats['pending_orders'] > 0): ?>
                    <div class="alert alert-warning d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong><?= $stats['pending_orders'] ?></strong>&nbsp;order(s) pending processing.
                        <a href="<?= BASE_URL ?>/admin/orders.php?status=pending" class="ms-auto">View Orders</a>
                    </div>
                <?php endif; ?>

                <?php if ($stats['low_stock'] > 0): ?>
                    <div class="alert alert-danger d-flex align-items-center">
                        <i class="bi bi-exclamation-circle me-2"></i>
                        <strong><?= $stats['low_stock'] ?></strong>&nbsp;product(s) have low stock.
                        <a href="<?= BASE_URL ?>/admin/products.php?filter=low_stock" class="ms-auto">View Products</a>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Recent Orders -->
                    <div class="col-lg-8 mb-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Recent Orders</h5>
                                <a href="<?= BASE_URL ?>/admin/orders.php" class="btn btn-sm btn-primary">View All</a>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Order</th>
                                                <th>Customer</th>
                                                <th>Amount</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recentOrders as $order): ?>
                                                <tr>
                                                    <td>
                                                        <a href="<?= BASE_URL ?>/admin/orders.php?view=<?= $order['id'] ?>">
                                                            <?= sanitize($order['order_number']) ?>
                                                        </a>
                                                    </td>
                                                    <td><?= sanitize($order['first_name'] . ' ' . $order['last_name']) ?></td>
                                                    <td><?= formatPrice($order['total_amount']) ?></td>
                                                    <td>
                                                        <span class="badge bg-<?= $order['status'] === 'delivered' ? 'success' : ($order['status'] === 'pending' ? 'warning' : 'info') ?>">
                                                            <?= ucfirst($order['status']) ?>
                                                        </span>
                                                    </td>
                                                    <td><?= formatDate($order['created_at']) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Users -->
                    <div class="col-lg-4 mb-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">New Users</h5>
                                <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-sm btn-primary">View All</a>
                            </div>
                            <div class="card-body p-0">
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($recentUsers as $user): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?= sanitize($user['first_name'] . ' ' . $user['last_name']) ?></strong>
                                                <div class="text-muted small"><?= sanitize($user['email']) ?></div>
                                            </div>
                                            <span class="badge bg-<?= $user['status'] === 'active' ? 'success' : 'danger' ?>">
                                                <?= ucfirst($user['status']) ?>
                                            </span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
