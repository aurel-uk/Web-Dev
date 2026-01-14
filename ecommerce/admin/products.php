<?php
/**
 * ============================================
 * ADMIN - PRODUCT MANAGEMENT
 * ============================================
 *
 * Full CRUD for products:
 * - List products with filters
 * - Create new products
 * - Edit products
 * - Delete products
 * - Manage stock
 *
 * ============================================
 */

require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../includes/admin_check.php';

$db = getDB();
$action = $_GET['action'] ?? 'list';
$productId = (int)($_GET['id'] ?? 0);

$errors = [];

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
                    'name' => sanitize($_POST['name'] ?? ''),
                    'slug' => generateSlug($_POST['name'] ?? ''),
                    'short_description' => sanitize($_POST['short_description'] ?? ''),
                    'description' => sanitize($_POST['description'] ?? ''),
                    'price' => sanitizeFloat($_POST['price'] ?? 0),
                    'sale_price' => !empty($_POST['sale_price']) ? sanitizeFloat($_POST['sale_price']) : null,
                    'sku' => sanitize($_POST['sku'] ?? ''),
                    'stock_quantity' => sanitizeInt($_POST['stock_quantity'] ?? 0),
                    'category_id' => !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null,
                    'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
                    'status' => $_POST['status'] ?? 'active'
                ];

                // Validation
                if (empty($data['name'])) $errors[] = 'Product name is required.';
                if ($data['price'] <= 0) $errors[] = 'Price must be greater than 0.';

                // Handle image upload
                $imageName = null;
                if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] === UPLOAD_ERR_OK) {
                    $result = uploadImage($_FILES['main_image'], PRODUCT_UPLOAD_PATH, 'prod_');
                    if ($result['success']) {
                        $imageName = $result['filename'];
                    } else {
                        $errors[] = $result['error'];
                    }
                }

                if (empty($errors)) {
                    try {
                        if ($postAction === 'create') {
                            $stmt = $db->prepare("
                                INSERT INTO products (name, slug, short_description, description, price, sale_price,
                                    sku, stock_quantity, category_id, main_image, is_featured, status)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                            ");
                            $stmt->execute([
                                $data['name'], $data['slug'], $data['short_description'], $data['description'],
                                $data['price'], $data['sale_price'], $data['sku'], $data['stock_quantity'],
                                $data['category_id'], $imageName, $data['is_featured'], $data['status']
                            ]);
                            logActivity('product_create', 'Product created: ' . $data['name'], 'product', $db->lastInsertId());
                            setFlashMessage('success', 'Product created successfully.');
                        } else {
                            $productId = (int)$_POST['product_id'];

                            // Get current image
                            $currentStmt = $db->prepare("SELECT main_image FROM products WHERE id = ?");
                            $currentStmt->execute([$productId]);
                            $current = $currentStmt->fetch();

                            // Use new image or keep current
                            $finalImage = $imageName ?? $current['main_image'];

                            $stmt = $db->prepare("
                                UPDATE products
                                SET name = ?, slug = ?, short_description = ?, description = ?, price = ?,
                                    sale_price = ?, sku = ?, stock_quantity = ?, category_id = ?,
                                    main_image = ?, is_featured = ?, status = ?
                                WHERE id = ?
                            ");
                            $stmt->execute([
                                $data['name'], $data['slug'], $data['short_description'], $data['description'],
                                $data['price'], $data['sale_price'], $data['sku'], $data['stock_quantity'],
                                $data['category_id'], $finalImage, $data['is_featured'], $data['status'],
                                $productId
                            ]);
                            logActivity('product_update', 'Product updated: ' . $data['name'], 'product', $productId);
                            setFlashMessage('success', 'Product updated successfully.');
                        }
                        redirect(BASE_URL . '/admin/products.php');
                    } catch (Exception $e) {
                        writeLog('ERROR', 'Product save failed: ' . $e->getMessage());
                        $errors[] = 'Failed to save product.';
                    }
                }
                break;

            case 'delete':
                $productId = (int)$_POST['product_id'];
                try {
                    $stmt = $db->prepare("DELETE FROM products WHERE id = ?");
                    $stmt->execute([$productId]);
                    logActivity('product_delete', 'Product deleted', 'product', $productId);
                    setFlashMessage('success', 'Product deleted successfully.');
                    redirect(BASE_URL . '/admin/products.php');
                } catch (Exception $e) {
                    $errors[] = 'Failed to delete product.';
                }
                break;
        }
    }
}

// Get categories for dropdown
$categories = $db->query("SELECT id, name FROM categories WHERE status = 'active' ORDER BY name")->fetchAll();

// Get products for listing
$search = sanitize($_GET['search'] ?? '');
$categoryFilter = $_GET['category'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$stockFilter = $_GET['filter'] ?? '';

$where = ['1=1'];
$params = [];

if ($search) {
    $where[] = "(p.name LIKE ? OR p.sku LIKE ?)";
    $searchTerm = "%{$search}%";
    $params = array_merge($params, [$searchTerm, $searchTerm]);
}
if ($categoryFilter) {
    $where[] = "p.category_id = ?";
    $params[] = $categoryFilter;
}
if ($statusFilter) {
    $where[] = "p.status = ?";
    $params[] = $statusFilter;
}
if ($stockFilter === 'low_stock') {
    $where[] = "p.stock_quantity <= 5";
}

$whereClause = implode(' AND ', $where);

$stmt = $db->prepare("
    SELECT p.*, c.name as category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE {$whereClause}
    ORDER BY p.created_at DESC
");
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get single product for edit
$editProduct = null;
if ($action === 'edit' && $productId) {
    $editStmt = $db->prepare("SELECT * FROM products WHERE id = ?");
    $editStmt->execute([$productId]);
    $editProduct = $editStmt->fetch();
}

$pageTitle = 'Manage Products';
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
        .product-thumb { width: 50px; height: 50px; object-fit: cover; }
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

                <?php if ($action === 'create' || $action === 'edit'): ?>
                    <!-- Create/Edit Form -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="mb-0"><?= $action === 'create' ? 'Add New Product' : 'Edit Product' ?></h4>
                        <a href="<?= BASE_URL ?>/admin/products.php" class="btn btn-outline-secondary">
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

                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="action" value="<?= $action ?>">
                        <?php if ($editProduct): ?>
                            <input type="hidden" name="product_id" value="<?= $editProduct['id'] ?>">
                        <?php endif; ?>

                        <div class="row">
                            <div class="col-lg-8">
                                <div class="card mb-4">
                                    <div class="card-header">Product Information</div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">Product Name *</label>
                                            <input type="text" class="form-control" name="name"
                                                   value="<?= sanitize($editProduct['name'] ?? '') ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Short Description</label>
                                            <textarea class="form-control" name="short_description" rows="2"
                                                ><?= sanitize($editProduct['short_description'] ?? '') ?></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Full Description</label>
                                            <textarea class="form-control" name="description" rows="5"
                                                ><?= sanitize($editProduct['description'] ?? '') ?></textarea>
                                        </div>
                                    </div>
                                </div>

                                <div class="card mb-4">
                                    <div class="card-header">Pricing & Inventory</div>
                                    <div class="card-body">
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <label class="form-label">Price *</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">$</span>
                                                    <input type="number" class="form-control" name="price" step="0.01" min="0"
                                                           value="<?= $editProduct['price'] ?? '' ?>" required>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Sale Price</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">$</span>
                                                    <input type="number" class="form-control" name="sale_price" step="0.01" min="0"
                                                           value="<?= $editProduct['sale_price'] ?? '' ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Stock Quantity</label>
                                                <input type="number" class="form-control" name="stock_quantity" min="0"
                                                       value="<?= $editProduct['stock_quantity'] ?? 0 ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">SKU</label>
                                                <input type="text" class="form-control" name="sku"
                                                       value="<?= sanitize($editProduct['sku'] ?? '') ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-4">
                                <div class="card mb-4">
                                    <div class="card-header">Product Image</div>
                                    <div class="card-body">
                                        <?php if ($editProduct && $editProduct['main_image']): ?>
                                            <img src="<?= BASE_URL ?>/../uploads/products/<?= sanitize($editProduct['main_image']) ?>"
                                                 class="img-fluid rounded mb-3">
                                        <?php endif; ?>
                                        <input type="file" class="form-control" name="main_image" accept="image/*">
                                        <div class="form-text">Max 5MB. JPG, PNG, GIF, WebP.</div>
                                    </div>
                                </div>

                                <div class="card mb-4">
                                    <div class="card-header">Organization</div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">Category</label>
                                            <select class="form-select" name="category_id">
                                                <option value="">No Category</option>
                                                <?php foreach ($categories as $cat): ?>
                                                    <option value="<?= $cat['id'] ?>"
                                                        <?= ($editProduct['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                                                        <?= sanitize($cat['name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Status</label>
                                            <select class="form-select" name="status">
                                                <option value="active" <?= ($editProduct['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                                                <option value="inactive" <?= ($editProduct['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                            </select>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="is_featured" id="is_featured"
                                                <?= ($editProduct['is_featured'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="is_featured">Featured Product</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-lg"></i> <?= $action === 'create' ? 'Create Product' : 'Update Product' ?>
                                    </button>
                                    <a href="<?= BASE_URL ?>/admin/products.php" class="btn btn-outline-secondary">Cancel</a>
                                </div>
                            </div>
                        </div>
                    </form>

                <?php else: ?>
                    <!-- Products List -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="mb-0">Manage Products</h4>
                        <a href="?action=create" class="btn btn-primary">
                            <i class="bi bi-plus-lg"></i> Add Product
                        </a>
                    </div>

                    <!-- Filters -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <div class="col-md-3">
                                    <input type="text" class="form-control" name="search"
                                           placeholder="Search..." value="<?= sanitize($search) ?>">
                                </div>
                                <div class="col-md-2">
                                    <select class="form-select" name="category">
                                        <option value="">All Categories</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?= $cat['id'] ?>" <?= $categoryFilter == $cat['id'] ? 'selected' : '' ?>>
                                                <?= sanitize($cat['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <select class="form-select" name="status">
                                        <option value="">All Status</option>
                                        <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                                        <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
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

                    <!-- Products Table -->
                    <div class="card">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover admin-table mb-0">
                                    <thead>
                                        <tr>
                                            <th width="60">Image</th>
                                            <th>Name</th>
                                            <th>Category</th>
                                            <th>Price</th>
                                            <th>Stock</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($products as $product): ?>
                                            <tr>
                                                <td>
                                                    <?php if ($product['main_image']): ?>
                                                        <img src="<?= BASE_URL ?>/../uploads/products/<?= sanitize($product['main_image']) ?>"
                                                             class="product-thumb rounded">
                                                    <?php else: ?>
                                                        <div class="product-thumb bg-light d-flex align-items-center justify-content-center rounded">
                                                            <i class="bi bi-image text-muted"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <strong><?= sanitize($product['name']) ?></strong>
                                                    <?php if ($product['is_featured']): ?>
                                                        <span class="badge bg-warning ms-1">Featured</span>
                                                    <?php endif; ?>
                                                    <?php if ($product['sku']): ?>
                                                        <div class="text-muted small">SKU: <?= sanitize($product['sku']) ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= sanitize($product['category_name'] ?? '-') ?></td>
                                                <td>
                                                    <?php if ($product['sale_price']): ?>
                                                        <span class="text-decoration-line-through text-muted"><?= formatPrice($product['price']) ?></span>
                                                        <span class="text-danger"><?= formatPrice($product['sale_price']) ?></span>
                                                    <?php else: ?>
                                                        <?= formatPrice($product['price']) ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= $product['stock_quantity'] <= 0 ? 'danger' : ($product['stock_quantity'] <= 5 ? 'warning' : 'success') ?>">
                                                        <?= $product['stock_quantity'] ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= $product['status'] === 'active' ? 'success' : 'secondary' ?>">
                                                        <?= ucfirst($product['status']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="?action=edit&id=<?= $product['id'] ?>" class="btn btn-outline-primary">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this product?')">
                                                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                                            <button type="submit" class="btn btn-outline-danger">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer">Total: <?= count($products) ?> products</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
