<?php
/**
 * ============================================
 * ADMIN - USER MANAGEMENT
 * ============================================
 *
 * Features:
 * - List all users
 * - View user details
 * - Create new users
 * - Edit users
 * - Delete users
 * - Toggle user status
 *
 * ============================================
 */

require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../includes/admin_check.php';

$db = getDB();
$action = $_GET['action'] ?? 'list';
$userId = (int)($_GET['id'] ?? 0);

$errors = [];
$success = false;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission.';
    } else {
        $postAction = $_POST['action'] ?? '';

        switch ($postAction) {
            case 'create':
            case 'update':
                $data = [
                    'first_name' => sanitize($_POST['first_name'] ?? ''),
                    'last_name' => sanitize($_POST['last_name'] ?? ''),
                    'email' => sanitizeEmail($_POST['email'] ?? ''),
                    'phone' => sanitize($_POST['phone'] ?? ''),
                    'role' => $_POST['role'] ?? 'user',
                    'status' => $_POST['status'] ?? 'active'
                ];

                // Validation
                if (empty($data['first_name'])) $errors[] = 'First name is required.';
                if (empty($data['last_name'])) $errors[] = 'Last name is required.';
                if (empty($data['email']) || !isValidEmail($data['email'])) $errors[] = 'Valid email is required.';

                if ($postAction === 'create') {
                    $password = $_POST['password'] ?? '';
                    if (empty($password)) $errors[] = 'Password is required.';

                    if (emailExists($data['email'])) {
                        $errors[] = 'Email already exists.';
                    }
                }

                if (empty($errors)) {
                    try {
                        if ($postAction === 'create') {
                            $stmt = $db->prepare("
                                INSERT INTO users (first_name, last_name, email, password, phone, role, status, email_verified)
                                VALUES (?, ?, ?, ?, ?, ?, ?, 1)
                            ");
                            $stmt->execute([
                                $data['first_name'],
                                $data['last_name'],
                                $data['email'],
                                hashPassword($password),
                                $data['phone'] ?: null,
                                $data['role'],
                                $data['status']
                            ]);
                            logActivity('user_create', 'Admin created user', 'user', $db->lastInsertId());
                            setFlashMessage('success', 'User created successfully.');
                        } else {
                            $userId = (int)$_POST['user_id'];

                            // Check if changing email to one that exists
                            $existingStmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                            $existingStmt->execute([$data['email'], $userId]);
                            if ($existingStmt->fetch()) {
                                $errors[] = 'Email already exists.';
                            } else {
                                $stmt = $db->prepare("
                                    UPDATE users
                                    SET first_name = ?, last_name = ?, email = ?, phone = ?, role = ?, status = ?
                                    WHERE id = ?
                                ");
                                $stmt->execute([
                                    $data['first_name'],
                                    $data['last_name'],
                                    $data['email'],
                                    $data['phone'] ?: null,
                                    $data['role'],
                                    $data['status'],
                                    $userId
                                ]);

                                // Update password if provided
                                if (!empty($_POST['password'])) {
                                    $pwdStmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                                    $pwdStmt->execute([hashPassword($_POST['password']), $userId]);
                                }

                                logActivity('user_update', 'Admin updated user', 'user', $userId);
                                setFlashMessage('success', 'User updated successfully.');
                            }
                        }

                        if (empty($errors)) {
                            redirect(BASE_URL . '/admin/users.php');
                        }
                    } catch (Exception $e) {
                        writeLog('ERROR', 'User save failed: ' . $e->getMessage());
                        $errors[] = 'Failed to save user.';
                    }
                }
                break;

            case 'delete':
                $userId = (int)$_POST['user_id'];
                if ($userId === $_SESSION['user_id']) {
                    $errors[] = 'You cannot delete yourself.';
                } else {
                    try {
                        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                        $stmt->execute([$userId]);
                        logActivity('user_delete', 'Admin deleted user', 'user', $userId);
                        setFlashMessage('success', 'User deleted successfully.');
                        redirect(BASE_URL . '/admin/users.php');
                    } catch (Exception $e) {
                        $errors[] = 'Failed to delete user.';
                    }
                }
                break;

            case 'toggle_status':
                $userId = (int)$_POST['user_id'];
                if ($userId === $_SESSION['user_id']) {
                    $errors[] = 'You cannot change your own status.';
                } else {
                    $stmt = $db->prepare("UPDATE users SET status = IF(status = 'active', 'blocked', 'active') WHERE id = ?");
                    $stmt->execute([$userId]);
                    setFlashMessage('success', 'User status updated.');
                    redirect(BASE_URL . '/admin/users.php');
                }
                break;
        }
    }
}

// Get users for listing
$search = sanitize($_GET['search'] ?? '');
$roleFilter = $_GET['role'] ?? '';
$statusFilter = $_GET['status'] ?? '';

$where = ['1=1'];
$params = [];

if ($search) {
    $where[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
    $searchTerm = "%{$search}%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}
if ($roleFilter) {
    $where[] = "role = ?";
    $params[] = $roleFilter;
}
if ($statusFilter) {
    $where[] = "status = ?";
    $params[] = $statusFilter;
}

$whereClause = implode(' AND ', $where);

$stmt = $db->prepare("
    SELECT * FROM users
    WHERE {$whereClause}
    ORDER BY created_at DESC
");
$stmt->execute($params);
$users = $stmt->fetchAll();

// Get single user for edit
$editUser = null;
if ($action === 'edit' && $userId) {
    $editStmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $editStmt->execute([$userId]);
    $editUser = $editStmt->fetch();
}

$pageTitle = 'Manage Users';
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
                <!-- Flash Message -->
                <?php $flash = getFlashMessage(); ?>
                <?php if ($flash): ?>
                    <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?> alert-dismissible fade show">
                        <?= sanitize($flash['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($action === 'create' || $action === 'edit'): ?>
                    <!-- Create/Edit Form -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="mb-0"><?= $action === 'create' ? 'Create New User' : 'Edit User' ?></h4>
                        <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Back
                        </a>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= sanitize($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <div class="card">
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="action" value="<?= $action ?>">
                                <?php if ($editUser): ?>
                                    <input type="hidden" name="user_id" value="<?= $editUser['id'] ?>">
                                <?php endif; ?>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">First Name *</label>
                                        <input type="text" class="form-control" name="first_name"
                                               value="<?= sanitize($editUser['first_name'] ?? '') ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Last Name *</label>
                                        <input type="text" class="form-control" name="last_name"
                                               value="<?= sanitize($editUser['last_name'] ?? '') ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Email *</label>
                                        <input type="email" class="form-control" name="email"
                                               value="<?= sanitize($editUser['email'] ?? '') ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Phone</label>
                                        <input type="tel" class="form-control" name="phone"
                                               value="<?= sanitize($editUser['phone'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Password <?= $action === 'edit' ? '(leave blank to keep current)' : '*' ?></label>
                                        <input type="password" class="form-control" name="password"
                                               <?= $action === 'create' ? 'required' : '' ?>>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Role</label>
                                        <select class="form-select" name="role">
                                            <option value="user" <?= ($editUser['role'] ?? '') === 'user' ? 'selected' : '' ?>>User</option>
                                            <option value="admin" <?= ($editUser['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Status</label>
                                        <select class="form-select" name="status">
                                            <option value="active" <?= ($editUser['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                                            <option value="blocked" <?= ($editUser['status'] ?? '') === 'blocked' ? 'selected' : '' ?>>Blocked</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-lg"></i> <?= $action === 'create' ? 'Create User' : 'Update User' ?>
                                    </button>
                                    <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-outline-secondary">Cancel</a>
                                </div>
                            </form>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- Users List -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="mb-0">Manage Users</h4>
                        <a href="?action=create" class="btn btn-primary">
                            <i class="bi bi-plus-lg"></i> Add User
                        </a>
                    </div>

                    <!-- Filters -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <div class="col-md-4">
                                    <input type="text" class="form-control" name="search"
                                           placeholder="Search by name or email..." value="<?= sanitize($search) ?>">
                                </div>
                                <div class="col-md-2">
                                    <select class="form-select" name="role">
                                        <option value="">All Roles</option>
                                        <option value="user" <?= $roleFilter === 'user' ? 'selected' : '' ?>>User</option>
                                        <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Admin</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <select class="form-select" name="status">
                                        <option value="">All Status</option>
                                        <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                                        <option value="blocked" <?= $statusFilter === 'blocked' ? 'selected' : '' ?>>Blocked</option>
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

                    <!-- Users Table -->
                    <div class="card">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover admin-table mb-0">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Role</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $user): ?>
                                            <tr id="user-<?= $user['id'] ?>">
                                                <td><?= $user['id'] ?></td>
                                                <td><?= sanitize($user['first_name'] . ' ' . $user['last_name']) ?></td>
                                                <td><?= sanitize($user['email']) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $user['role'] === 'admin' ? 'danger' : 'primary' ?>">
                                                        <?= ucfirst($user['role']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge status-badge bg-<?= $user['status'] === 'active' ? 'success' : 'secondary' ?>">
                                                        <?= ucfirst($user['status']) ?>
                                                    </span>
                                                </td>
                                                <td><?= formatDate($user['created_at']) ?></td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="?action=edit&id=<?= $user['id'] ?>" class="btn btn-outline-primary" title="Edit">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                                <input type="hidden" name="action" value="toggle_status">
                                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                                <button type="submit" class="btn btn-outline-warning" title="Toggle Status">
                                                                    <i class="bi bi-toggle-on"></i>
                                                                </button>
                                                            </form>
                                                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this user?')">
                                                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                                <input type="hidden" name="action" value="delete">
                                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                                <button type="submit" class="btn btn-outline-danger" title="Delete">
                                                                    <i class="bi bi-trash"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer">
                            Total: <?= count($users) ?> users
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
