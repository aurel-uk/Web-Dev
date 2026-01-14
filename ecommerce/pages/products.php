<?php
/**
 * ============================================
 * PRODUCTS LISTING PAGE
 * ============================================
 *
 * This page displays all products with filtering and sorting.
 * Features:
 * - Category filtering
 * - Search functionality
 * - Price range filter
 * - Sort by options
 * - Pagination
 *
 * ============================================
 */

require_once __DIR__ . '/../config/init.php';

$db = getDB();

// Get filter parameters
$categorySlug = $_GET['category'] ?? '';
$search = sanitize($_GET['search'] ?? '');
$sort = $_GET['sort'] ?? 'newest';
$featured = isset($_GET['featured']) ? 1 : null;
$sale = isset($_GET['sale']) ? 1 : null;
$page = max(1, (int)($_GET['page'] ?? 1));

// Build WHERE clause
$where = ["p.status = 'active'"];
$params = [];

// Category filter
if ($categorySlug) {
    $where[] = "c.slug = ?";
    $params[] = $categorySlug;
}

// Search filter
if ($search) {
    $where[] = "(p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

// Featured filter
if ($featured) {
    $where[] = "p.is_featured = 1";
}

// Sale filter
if ($sale) {
    $where[] = "p.sale_price IS NOT NULL";
}

$whereClause = implode(' AND ', $where);

// Sort options
$sortOptions = [
    'newest' => 'p.created_at DESC',
    'oldest' => 'p.created_at ASC',
    'price_low' => 'COALESCE(p.sale_price, p.price) ASC',
    'price_high' => 'COALESCE(p.sale_price, p.price) DESC',
    'name_asc' => 'p.name ASC',
    'name_desc' => 'p.name DESC'
];
$orderBy = $sortOptions[$sort] ?? $sortOptions['newest'];

// Count total products
$countStmt = $db->prepare("
    SELECT COUNT(*) FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE {$whereClause}
");
$countStmt->execute($params);
$totalProducts = $countStmt->fetchColumn();

// Pagination
$pagination = getPagination($totalProducts, $page);

// Fetch products
$stmt = $db->prepare("
    SELECT p.*, c.name as category_name, c.slug as category_slug
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE {$whereClause}
    ORDER BY {$orderBy}
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}
");
$stmt->execute($params);
$products = $stmt->fetchAll();

// Fetch all categories for filter
$categories = $db->query("
    SELECT c.*, COUNT(p.id) as product_count
    FROM categories c
    LEFT JOIN products p ON c.id = p.category_id AND p.status = 'active'
    WHERE c.status = 'active'
    GROUP BY c.id
    ORDER BY c.sort_order
")->fetchAll();

// Get current category info
$currentCategory = null;
if ($categorySlug) {
    foreach ($categories as $cat) {
        if ($cat['slug'] === $categorySlug) {
            $currentCategory = $cat;
            break;
        }
    }
}

$pageTitle = $currentCategory ? $currentCategory['name'] : 'All Products';
if ($search) {
    $pageTitle = 'Search: ' . $search;
}
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/index.php">Home</a></li>
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/pages/products.php">Products</a></li>
            <?php if ($currentCategory): ?>
                <li class="breadcrumb-item active"><?= sanitize($currentCategory['name']) ?></li>
            <?php endif; ?>
            <?php if ($search): ?>
                <li class="breadcrumb-item active">Search: "<?= sanitize($search) ?>"</li>
            <?php endif; ?>
        </ol>
    </nav>

    <div class="row">
        <!-- Sidebar Filters -->
        <div class="col-lg-3 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-funnel"></i> Filters</h5>
                </div>
                <div class="card-body">
                    <!-- Categories -->
                    <h6 class="fw-bold mb-3">Categories</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <a href="<?= BASE_URL ?>/pages/products.php"
                               class="text-decoration-none <?= !$categorySlug ? 'fw-bold text-primary' : 'text-dark' ?>">
                                All Products
                            </a>
                        </li>
                        <?php foreach ($categories as $cat): ?>
                            <li class="mb-2">
                                <a href="<?= BASE_URL ?>/pages/products.php?category=<?= sanitize($cat['slug']) ?>"
                                   class="text-decoration-none <?= $categorySlug === $cat['slug'] ? 'fw-bold text-primary' : 'text-dark' ?>">
                                    <?= sanitize($cat['name']) ?>
                                    <span class="text-muted">(<?= $cat['product_count'] ?>)</span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <hr>

                    <!-- Quick Filters -->
                    <h6 class="fw-bold mb-3">Quick Filters</h6>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="filter_sale"
                               <?= $sale ? 'checked' : '' ?>
                               onchange="applyFilter('sale', this.checked)">
                        <label class="form-check-label" for="filter_sale">
                            <i class="bi bi-tag text-danger"></i> On Sale
                        </label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="filter_featured"
                               <?= $featured ? 'checked' : '' ?>
                               onchange="applyFilter('featured', this.checked)">
                        <label class="form-check-label" for="filter_featured">
                            <i class="bi bi-star text-warning"></i> Featured
                        </label>
                    </div>

                    <hr>

                    <!-- Clear Filters -->
                    <a href="<?= BASE_URL ?>/pages/products.php" class="btn btn-outline-secondary btn-sm w-100">
                        <i class="bi bi-x-circle"></i> Clear All Filters
                    </a>
                </div>
            </div>
        </div>

        <!-- Products Grid -->
        <div class="col-lg-9">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1"><?= sanitize($pageTitle) ?></h4>
                    <p class="text-muted mb-0">
                        Showing <?= count($products) ?> of <?= $totalProducts ?> products
                    </p>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <label class="text-muted me-2">Sort by:</label>
                    <select class="form-select form-select-sm" id="sortSelect" style="width: auto;"
                            onchange="updateSort(this.value)">
                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
                        <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest</option>
                        <option value="price_low" <?= $sort === 'price_low' ? 'selected' : '' ?>>Price: Low to High</option>
                        <option value="price_high" <?= $sort === 'price_high' ? 'selected' : '' ?>>Price: High to Low</option>
                        <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name: A-Z</option>
                        <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Name: Z-A</option>
                    </select>
                </div>
            </div>

            <!-- Products -->
            <?php if (empty($products)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-search fs-1 text-muted mb-3"></i>
                    <h5>No products found</h5>
                    <p class="text-muted">Try adjusting your filters or search terms.</p>
                    <a href="<?= BASE_URL ?>/pages/products.php" class="btn btn-primary">
                        View All Products
                    </a>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($products as $product): ?>
                        <div class="col-6 col-md-4">
                            <div class="card product-card h-100">
                                <div class="card-img-wrapper">
                                    <?php if ($product['sale_price']): ?>
                                        <span class="product-badge badge bg-danger">Sale</span>
                                    <?php endif; ?>
                                    <?php if ($product['main_image']): ?>
                                        <a href="<?= BASE_URL ?>/pages/product_detail.php?slug=<?= sanitize($product['slug']) ?>">
                                            <img src="<?= BASE_URL ?>/../uploads/products/<?= sanitize($product['main_image']) ?>"
                                                 class="card-img-top" alt="<?= sanitize($product['name']) ?>">
                                        </a>
                                    <?php else: ?>
                                        <a href="<?= BASE_URL ?>/pages/product_detail.php?slug=<?= sanitize($product['slug']) ?>">
                                            <div class="card-img-top img-placeholder d-flex align-items-center justify-content-center"
                                                 style="height: 200px; background: #f8f9fa;">
                                                <i class="bi bi-image fs-1 text-muted"></i>
                                            </div>
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted small mb-1"><?= sanitize($product['category_name'] ?? 'Uncategorized') ?></p>
                                    <h6 class="card-title text-truncate-2">
                                        <a href="<?= BASE_URL ?>/pages/product_detail.php?slug=<?= sanitize($product['slug']) ?>"
                                           class="text-dark text-decoration-none">
                                            <?= sanitize($product['name']) ?>
                                        </a>
                                    </h6>
                                    <p class="text-muted small text-truncate-2 mb-2">
                                        <?= sanitize($product['short_description'] ?? '') ?>
                                    </p>

                                    <!-- Stock Status -->
                                    <?php if ($product['stock_quantity'] <= 0): ?>
                                        <p class="small stock-out mb-2"><i class="bi bi-x-circle"></i> Out of Stock</p>
                                    <?php elseif ($product['stock_quantity'] <= 5): ?>
                                        <p class="small stock-low mb-2"><i class="bi bi-exclamation-circle"></i> Only <?= $product['stock_quantity'] ?> left</p>
                                    <?php endif; ?>

                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <?php if ($product['sale_price']): ?>
                                                <span class="price-original"><?= formatPrice($product['price']) ?></span>
                                                <span class="price-sale"><?= formatPrice($product['sale_price']) ?></span>
                                            <?php else: ?>
                                                <span class="fw-bold"><?= formatPrice($product['price']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <button class="btn btn-primary btn-sm btn-add-cart"
                                                data-product-id="<?= $product['id'] ?>"
                                                <?= $product['stock_quantity'] <= 0 ? 'disabled' : '' ?>>
                                            <i class="bi bi-cart-plus"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($pagination['total_pages'] > 1): ?>
                    <nav class="mt-5">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?= !$pagination['has_previous'] ? 'disabled' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                    <i class="bi bi-chevron-left"></i> Previous
                                </a>
                            </li>
                            <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                                <?php if ($i == 1 || $i == $pagination['total_pages'] || abs($i - $page) <= 2): ?>
                                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php elseif (abs($i - $page) == 3): ?>
                                    <li class="page-item disabled"><span class="page-link">...</span></li>
                                <?php endif; ?>
                            <?php endfor; ?>
                            <li class="page-item <?= !$pagination['has_next'] ? 'disabled' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                    Next <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function updateSort(value) {
    const url = new URL(window.location);
    url.searchParams.set('sort', value);
    url.searchParams.delete('page');
    window.location = url;
}

function applyFilter(name, checked) {
    const url = new URL(window.location);
    if (checked) {
        url.searchParams.set(name, '1');
    } else {
        url.searchParams.delete(name);
    }
    url.searchParams.delete('page');
    window.location = url;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
