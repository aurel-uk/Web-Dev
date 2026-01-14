<?php
/**
 * ============================================
 * PRODUCT DETAIL PAGE
 * ============================================
 *
 * Displays detailed information about a single product.
 * Features:
 * - Product images
 * - Description
 * - Price and sale price
 * - Stock status
 * - Add to cart
 * - Related products
 *
 * ============================================
 */

require_once __DIR__ . '/../config/init.php';

$db = getDB();
$slug = sanitize($_GET['slug'] ?? '');

if (empty($slug)) {
    setFlashMessage('error', 'Product not found.');
    redirect(BASE_URL . '/pages/products.php');
}

// Fetch product
$stmt = $db->prepare("
    SELECT p.*, c.name as category_name, c.slug as category_slug
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.slug = ? AND p.status = 'active'
");
$stmt->execute([$slug]);
$product = $stmt->fetch();

if (!$product) {
    setFlashMessage('error', 'Product not found.');
    redirect(BASE_URL . '/pages/products.php');
}

// Fetch related products (same category)
$relatedProducts = [];
if ($product['category_id']) {
    $relatedStmt = $db->prepare("
        SELECT p.*, c.name as category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.category_id = ? AND p.id != ? AND p.status = 'active'
        ORDER BY RAND()
        LIMIT 4
    ");
    $relatedStmt->execute([$product['category_id'], $product['id']]);
    $relatedProducts = $relatedStmt->fetchAll();
}

$pageTitle = $product['name'];
$pageDescription = $product['short_description'] ?? $product['name'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/index.php">Home</a></li>
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/pages/products.php">Products</a></li>
            <?php if ($product['category_name']): ?>
                <li class="breadcrumb-item">
                    <a href="<?= BASE_URL ?>/pages/products.php?category=<?= sanitize($product['category_slug']) ?>">
                        <?= sanitize($product['category_name']) ?>
                    </a>
                </li>
            <?php endif; ?>
            <li class="breadcrumb-item active"><?= sanitize($product['name']) ?></li>
        </ol>
    </nav>

    <div class="row">
        <!-- Product Image -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-body p-0">
                    <?php if ($product['sale_price']): ?>
                        <span class="product-badge badge bg-danger m-3">Sale</span>
                    <?php endif; ?>
                    <?php if ($product['main_image']): ?>
                        <img src="<?= BASE_URL ?>/../uploads/products/<?= sanitize($product['main_image']) ?>"
                             class="img-fluid w-100 rounded"
                             alt="<?= sanitize($product['name']) ?>"
                             id="mainImage">
                    <?php else: ?>
                        <div class="img-placeholder d-flex align-items-center justify-content-center"
                             style="height: 400px; background: #f8f9fa;">
                            <i class="bi bi-image" style="font-size: 5rem;"></i>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Gallery Thumbnails (if available) -->
            <?php if ($product['gallery_images']): ?>
                <?php $gallery = json_decode($product['gallery_images'], true); ?>
                <?php if ($gallery): ?>
                    <div class="row g-2 mt-2">
                        <?php if ($product['main_image']): ?>
                            <div class="col-3">
                                <img src="<?= BASE_URL ?>/../uploads/products/<?= sanitize($product['main_image']) ?>"
                                     class="img-fluid rounded cursor-pointer gallery-thumb active"
                                     onclick="changeImage(this.src)">
                            </div>
                        <?php endif; ?>
                        <?php foreach ($gallery as $img): ?>
                            <div class="col-3">
                                <img src="<?= BASE_URL ?>/../uploads/products/<?= sanitize($img) ?>"
                                     class="img-fluid rounded cursor-pointer gallery-thumb"
                                     onclick="changeImage(this.src)">
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Product Details -->
        <div class="col-lg-6">
            <div class="mb-2">
                <a href="<?= BASE_URL ?>/pages/products.php?category=<?= sanitize($product['category_slug'] ?? '') ?>"
                   class="text-muted text-decoration-none">
                    <?= sanitize($product['category_name'] ?? 'Uncategorized') ?>
                </a>
            </div>

            <h1 class="h2 fw-bold mb-3"><?= sanitize($product['name']) ?></h1>

            <!-- Price -->
            <div class="mb-4">
                <?php if ($product['sale_price']): ?>
                    <span class="text-muted text-decoration-line-through fs-4">
                        <?= formatPrice($product['price']) ?>
                    </span>
                    <span class="text-danger fs-2 fw-bold ms-2">
                        <?= formatPrice($product['sale_price']) ?>
                    </span>
                    <?php
                    $discount = round((($product['price'] - $product['sale_price']) / $product['price']) * 100);
                    ?>
                    <span class="badge bg-danger ms-2">Save <?= $discount ?>%</span>
                <?php else: ?>
                    <span class="fs-2 fw-bold"><?= formatPrice($product['price']) ?></span>
                <?php endif; ?>
            </div>

            <!-- Short Description -->
            <?php if ($product['short_description']): ?>
                <p class="lead text-muted mb-4"><?= sanitize($product['short_description']) ?></p>
            <?php endif; ?>

            <!-- Stock Status -->
            <div class="mb-4">
                <?php if ($product['stock_quantity'] <= 0): ?>
                    <span class="badge bg-danger"><i class="bi bi-x-circle"></i> Out of Stock</span>
                <?php elseif ($product['stock_quantity'] <= 5): ?>
                    <span class="badge bg-warning text-dark">
                        <i class="bi bi-exclamation-triangle"></i>
                        Only <?= $product['stock_quantity'] ?> left in stock
                    </span>
                <?php else: ?>
                    <span class="badge bg-success"><i class="bi bi-check-circle"></i> In Stock</span>
                <?php endif; ?>
            </div>

            <!-- Add to Cart -->
            <?php if ($product['stock_quantity'] > 0): ?>
                <div class="d-flex align-items-center gap-3 mb-4">
                    <div class="quantity-input input-group" style="width: 130px;">
                        <button class="btn btn-outline-secondary quantity-btn decrement" type="button">-</button>
                        <input type="number" class="form-control text-center" id="quantity"
                               value="1" min="1" max="<?= $product['stock_quantity'] ?>">
                        <button class="btn btn-outline-secondary quantity-btn increment" type="button">+</button>
                    </div>
                    <button class="btn btn-primary btn-lg flex-grow-1 btn-add-cart"
                            data-product-id="<?= $product['id'] ?>">
                        <i class="bi bi-cart-plus"></i> Add to Cart
                    </button>
                </div>
            <?php else: ?>
                <button class="btn btn-secondary btn-lg w-100 mb-4" disabled>
                    <i class="bi bi-x-circle"></i> Out of Stock
                </button>
            <?php endif; ?>

            <!-- SKU -->
            <?php if ($product['sku']): ?>
                <p class="text-muted small mb-2">
                    <strong>SKU:</strong> <?= sanitize($product['sku']) ?>
                </p>
            <?php endif; ?>

            <!-- Share -->
            <div class="mt-4">
                <span class="text-muted me-2">Share:</span>
                <a href="#" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-facebook"></i>
                </a>
                <a href="#" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-twitter"></i>
                </a>
                <a href="#" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-pinterest"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Product Description -->
    <?php if ($product['description']): ?>
        <div class="row mt-5">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Product Description</h5>
                    </div>
                    <div class="card-body">
                        <?= nl2br(sanitize($product['description'])) ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Related Products -->
    <?php if (!empty($relatedProducts)): ?>
        <section class="mt-5">
            <h3 class="fw-bold mb-4">Related Products</h3>
            <div class="row g-4">
                <?php foreach ($relatedProducts as $related): ?>
                    <div class="col-6 col-md-3">
                        <div class="card product-card h-100">
                            <div class="card-img-wrapper">
                                <?php if ($related['sale_price']): ?>
                                    <span class="product-badge badge bg-danger">Sale</span>
                                <?php endif; ?>
                                <?php if ($related['main_image']): ?>
                                    <a href="<?= BASE_URL ?>/pages/product_detail.php?slug=<?= sanitize($related['slug']) ?>">
                                        <img src="<?= BASE_URL ?>/../uploads/products/<?= sanitize($related['main_image']) ?>"
                                             class="card-img-top" alt="<?= sanitize($related['name']) ?>">
                                    </a>
                                <?php else: ?>
                                    <a href="<?= BASE_URL ?>/pages/product_detail.php?slug=<?= sanitize($related['slug']) ?>">
                                        <div class="card-img-top img-placeholder d-flex align-items-center justify-content-center"
                                             style="height: 150px;">
                                            <i class="bi bi-image fs-1"></i>
                                        </div>
                                    </a>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <h6 class="card-title text-truncate">
                                    <a href="<?= BASE_URL ?>/pages/product_detail.php?slug=<?= sanitize($related['slug']) ?>"
                                       class="text-dark text-decoration-none">
                                        <?= sanitize($related['name']) ?>
                                    </a>
                                </h6>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <?php if ($related['sale_price']): ?>
                                            <span class="price-original small"><?= formatPrice($related['price']) ?></span>
                                            <span class="price-sale"><?= formatPrice($related['sale_price']) ?></span>
                                        <?php else: ?>
                                            <span class="fw-bold"><?= formatPrice($related['price']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</div>

<script>
// Gallery image change
function changeImage(src) {
    document.getElementById('mainImage').src = src;
    document.querySelectorAll('.gallery-thumb').forEach(img => {
        img.classList.remove('active');
        if (img.src === src) {
            img.classList.add('active');
        }
    });
}

// Override add to cart to use custom quantity
document.querySelector('.btn-add-cart').addEventListener('click', function(e) {
    e.preventDefault();
    const productId = this.dataset.productId;
    const quantity = document.getElementById('quantity').value;
    addToCart(productId, quantity);
});
</script>

<style>
.gallery-thumb {
    cursor: pointer;
    opacity: 0.7;
    transition: opacity 0.3s;
}
.gallery-thumb:hover,
.gallery-thumb.active {
    opacity: 1;
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
