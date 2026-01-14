<?php
/**
 * ============================================
 * ADMIN - CATEGORY MANAGEMENT
 * ============================================
 */

require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../includes/admin_check.php';

$db = getDB();
$action = $_GET['action'] ?? 'list';
$categoryId = (int)($_GET['id'] ?? 0);
$errors = [];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken($_POST['csrf_token'] ?? '')) {
    $postAction = $_POST['action'] ?? '';

    switch ($postAction) {
        case 'create':
        case 'update':
            $name = sanitize($_POST['name'] ?? '');
            $description = sanitize($_POST['description'] ?? '');
            $status = $_POST['status'] ?? 'active';
            $sortOrder = (int)($_POST['sort_order'] ?? 0);

            if (empty($name)) {
                $errors[] = 'Category name is required.';
            }

            if (empty($errors)) {
                $slug = generateSlug($name);

                if ($postAction === 'create') {
                    $stmt = $db->prepare("INSERT INTO categories (name, slug, description, status, sort_order) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $slug, $description, $status, $sortOrder]);
                    setFlashMessage('success', 'Category created.');
                } else {
                    $catId = (int)$_POST['category_id'];
                    $stmt = $db->prepare("UPDATE categories SET name = ?, slug = ?, description = ?, status = ?, sort_order = ? WHERE id = ?");
                    $stmt->execute([$name, $slug, $description, $status, $sortOrder, $catId]);
                    setFlashMessage('success', 'Category updated.');
                }
                redirect(BASE_URL . '/admin/categories.php');
            }
            break;

        case 'delete':
            $catId = (int)$_POST['category_id'];
            // Check if category has products
            $productCount = $db->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
            $productCount->execute([$catId]);
            if ($productCount->fetchColumn() > 0) {
                setFlashMessage('error', 'Cannot delete category with products.');
            } else {
                $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
                $stmt->execute([$catId]);
                setFlashMessage('success', 'Category deleted.');
            }
            redirect(BASE_URL . '/admin/categories.php');
            break;
    }
}

// Get categories
$categories = $db->query("
    SELECT c.*, COUNT(p.id) as product_count
    FROM categories c
    LEFT JOIN products p ON c.id = p.category_id
    GROUP BY c.id
    ORDER BY c.sort_order, c.name
")->fetchAll();

// Get single category for edit
$editCategory = null;
if ($action === 'edit' && $categoryId) {
    $stmt = $db->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$categoryId]);
    $editCategory = $stmt->fetch();
}

$pageTitle = 'Manage Categories';
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
                <?php $flash = getFlashMessage(); ?>
                <?php if ($flash): ?>
                    <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?> alert-dismissible">
                        <?= sanitize($flash['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Form -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <?= $editCategory ? 'Edit Category' : 'Add Category' ?>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($errors)): ?>
                                    <div class="alert alert-danger">
                                        <?php foreach ($errors as $e): ?>
                                            <div><?= $e ?></div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                    <input type="hidden" name="action" value="<?= $editCategory ? 'update' : 'create' ?>">
                                    <?php if ($editCategory): ?>
                                        <input type="hidden" name="category_id" value="<?= $editCategory['id'] ?>">
                                    <?php endif; ?>

                                    <div class="mb-3">
                                        <label class="form-label">Name *</label>
                                        <input type="text" class="form-control" name="name"
                                               value="<?= sanitize($editCategory['name'] ?? '') ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Description</label>
                                        <textarea class="form-control" name="description" rows="3"
                                            ><?= sanitize($editCategory['description'] ?? '') ?></textarea>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-6">
                                            <label class="form-label">Status</label>
                                            <select class="form-select" name="status">
                                                <option value="active" <?= ($editCategory['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                                                <option value="inactive" <?= ($editCategory['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                            </select>
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label">Sort Order</label>
                                            <input type="number" class="form-control" name="sort_order"
                                                   value="<?= $editCategory['sort_order'] ?? 0 ?>">
                                        </div>
                                    </div>
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <?= $editCategory ? 'Update' : 'Create' ?> Category
                                        </button>
                                        <?php if ($editCategory): ?>
                                            <a href="<?= BASE_URL ?>/admin/categories.php" class="btn btn-outline-secondary">Cancel</a>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- List -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">Categories</div>
                            <div class="card-body p-0">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Products</th>
                                            <th>Status</th>
                                            <th>Order</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($categories as $cat): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= sanitize($cat['name']) ?></strong>
                                                    <div class="text-muted small"><?= sanitize($cat['slug']) ?></div>
                                                </td>
                                                <td><?= $cat['product_count'] ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $cat['status'] === 'active' ? 'success' : 'secondary' ?>">
                                                        <?= ucfirst($cat['status']) ?>
                                                    </span>
                                                </td>
                                                <td><?= $cat['sort_order'] ?></td>
                                                <td>
                                                    <a href="?action=edit&id=<?= $cat['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete?')">
                                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
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
