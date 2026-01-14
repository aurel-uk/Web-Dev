<?php
/**
 * ============================================
 * ADMIN - SYSTEM LOGS
 * ============================================
 */

require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../includes/admin_check.php';

$db = getDB();

// Filters
$actionType = $_GET['type'] ?? '';
$userId = (int)($_GET['user'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;

$where = ['1=1'];
$params = [];

if ($actionType) {
    $where[] = "action_type = ?";
    $params[] = $actionType;
}
if ($userId) {
    $where[] = "user_id = ?";
    $params[] = $userId;
}

$whereClause = implode(' AND ', $where);

// Count total
$countStmt = $db->prepare("SELECT COUNT(*) FROM system_logs WHERE {$whereClause}");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();

$offset = ($page - 1) * $perPage;
$totalPages = ceil($total / $perPage);

// Get logs
$stmt = $db->prepare("
    SELECT l.*, u.first_name, u.last_name, u.email
    FROM system_logs l
    LEFT JOIN users u ON l.user_id = u.id
    WHERE {$whereClause}
    ORDER BY l.created_at DESC
    LIMIT {$perPage} OFFSET {$offset}
");
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Get action types for filter
$actionTypes = $db->query("SELECT DISTINCT action_type FROM system_logs ORDER BY action_type")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'System Logs';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> | <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
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
                <h4 class="mb-4">System Logs</h4>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <select class="form-select" name="type">
                                    <option value="">All Action Types</option>
                                    <?php foreach ($actionTypes as $type): ?>
                                        <option value="<?= $type ?>" <?= $actionType === $type ? 'selected' : '' ?>>
                                            <?= $type ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-secondary">Filter</button>
                                <a href="<?= BASE_URL ?>/admin/logs.php" class="btn btn-outline-secondary">Reset</a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Logs Table -->
                <div class="card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>Date/Time</th>
                                        <th>Action</th>
                                        <th>Description</th>
                                        <th>User</th>
                                        <th>IP Address</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logs as $log): ?>
                                        <tr>
                                            <td class="text-nowrap small">
                                                <?= formatDateTime($log['created_at'], 'M d, H:i:s') ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?= $log['action_type'] ?></span>
                                            </td>
                                            <td><?= sanitize($log['description']) ?></td>
                                            <td>
                                                <?php if ($log['user_id']): ?>
                                                    <?= sanitize($log['first_name'] . ' ' . $log['last_name']) ?>
                                                <?php else: ?>
                                                    <span class="text-muted">System</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="small"><?= $log['ip_address'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer d-flex justify-content-between align-items-center">
                        <span>Showing <?= count($logs) ?> of <?= $total ?> logs</span>

                        <?php if ($totalPages > 1): ?>
                            <nav>
                                <ul class="pagination pagination-sm mb-0">
                                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">Prev</a>
                                    </li>
                                    <li class="page-item disabled">
                                        <span class="page-link"><?= $page ?> / <?= $totalPages ?></span>
                                    </li>
                                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next</a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
