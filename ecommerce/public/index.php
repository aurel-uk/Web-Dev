<?php
/**
 * ============================================
 * HOMEPAGE
 * ============================================
 *
 * The main landing page of the e-commerce store.
 * Features:
 * - Hero banner
 * - Featured products
 * - Category showcase
 * - Special offers
 *
 * This is the first page users see when visiting the store.
 * ============================================
 */

// Load application initialization
require_once __DIR__ . '/../config/init.php';

// Fetch featured products
$db = getDB();
$featuredProducts = $db->query("
    SELECT p.*, c.name as category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.status = 'active' AND p.is_featured = 1
    ORDER BY p.created_at DESC
    LIMIT 8
")->fetchAll();

// Fetch categories
$categories = $db->query("
    SELECT * FROM categories
    WHERE status = 'active'
    ORDER BY sort_order
    LIMIT 6
")->fetchAll();

// Fetch new arrivals
$newArrivals = $db->query("
    SELECT p.*, c.name as category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.status = 'active'
    ORDER BY p.created_at DESC
    LIMIT 4
")->fetchAll();

// Set page variables
$pageTitle = 'Home';
$pageDescription = 'Welcome to ' . SITE_NAME . ' - Your one-stop shop for quality products!';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Hero Section -->
<section class="hero-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h1 class="display-4 fw-bold mb-4">
                    Welcome to <?= SITE_NAME ?>
                </h1>
                <p class="lead mb-4">
                    Discover amazing products at great prices. Shop now and enjoy
                    fast shipping, secure payments, and excellent customer service.
                </p>
                <div class="d-flex gap-3">
                    <a href="<?= BASE_URL ?>/pages/products.php" class="btn btn-light btn-lg">
                        <i class="bi bi-shop"></i> Shop Now
                    </a>
                    <a href="<?= BASE_URL ?>/pages/products.php?sale=1" class="btn btn-outline-light btn-lg">
                        <i class="bi bi-tag"></i> View Deals
                    </a>
                </div>
            </div>
            <div class="col-lg-6 d-none d-lg-block text-center">
                <i class="bi bi-cart-check" style="font-size: 15rem; opacity: 0.8;"></i>
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="py-4 bg-white">
    <div class="container">
        <div class="row text-center g-4">
            <div class="col-md-3">
                <div class="p-3">
                    <i class="bi bi-truck text-primary fs-1 mb-2"></i>
                    <h6 class="fw-bold">Free Shipping</h6>
                    <small class="text-muted">On orders over $<?= FREE_SHIPPING_THRESHOLD ?></small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-3">
                    <i class="bi bi-shield-check text-primary fs-1 mb-2"></i>
                    <h6 class="fw-bold">Secure Payment</h6>
                    <small class="text-muted">100% secure checkout</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-3">
                    <i class="bi bi-arrow-repeat text-primary fs-1 mb-2"></i>
                    <h6 class="fw-bold">Easy Returns</h6>
                    <small class="text-muted">30-day return policy</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-3">
                    <i class="bi bi-headset text-primary fs-1 mb-2"></i>
                    <h6 class="fw-bold">24/7 Support</h6>
                    <small class="text-muted">Dedicated support team</small>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Categories Section -->
<?php if (!empty($categories)): ?>
<section class="py-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold mb-0">Shop by Category</h2>
            <a href="<?= BASE_URL ?>/pages/products.php" class="btn btn-outline-primary">
                View All <i class="bi bi-arrow-right"></i>
            </a>
        </div>
        <div class="row g-4">
            <?php foreach ($categories as $category): ?>
                <div class="col-6 col-md-4 col-lg-2">
                    <a href="<?= BASE_URL ?>/pages/products.php?category=<?= sanitize($category['slug']) ?>"
                       class="text-decoration-none">
                        <div class="card category-card h-100 text-center py-4">
                            <div class="card-body">
                                <i class="bi bi-grid-3x3-gap fs-1 text-primary mb-2"></i>
                                <h6 class="card-title mb-0"><?= sanitize($category['name']) ?></h6>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Featured Products -->
<?php if (!empty($featuredProducts)): ?>
<section class="py-5 bg-light">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold mb-0">Featured Products</h2>
            <a href="<?= BASE_URL ?>/pages/products.php?featured=1" class="btn btn-outline-primary">
                View All <i class="bi bi-arrow-right"></i>
            </a>
        </div>
        <div class="row g-4">
            <?php foreach ($featuredProducts as $product): ?>
                <div class="col-6 col-md-4 col-lg-3">
                    <div class="card product-card h-100">
                        <div class="card-img-wrapper">
                            <?php if ($product['sale_price']): ?>
                                <span class="product-badge badge bg-danger">Sale</span>
                            <?php endif; ?>
                            <?php if ($product['main_image']): ?>
                                <img src="<?= BASE_URL ?>/../uploads/products/<?= sanitize($product['main_image']) ?>"
                                     class="card-img-top" alt="<?= sanitize($product['name']) ?>">
                            <?php else: ?>
                                <div class="card-img-top img-placeholder d-flex align-items-center justify-content-center"
                                     style="height: 200px;">
                                    <i class="bi bi-image fs-1"></i>
                                </div>
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
                            <div class="d-flex justify-content-between align-items-center mt-2">
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
    </div>
</section>
<?php endif; ?>

<!-- Promotional Banner -->
<section class="py-5 bg-primary text-white">
    <div class="container text-center">
        <h2 class="fw-bold mb-3">Get 10% Off Your First Order!</h2>
        <p class="lead mb-4">
            Sign up for our newsletter and receive exclusive deals and updates.
        </p>
        <form class="row g-2 justify-content-center">
            <div class="col-auto">
                <input type="email" class="form-control form-control-lg" placeholder="Enter your email">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-light btn-lg">
                    <i class="bi bi-envelope"></i> Subscribe
                </button>
            </div>
        </form>
    </div>
</section>

<!-- New Arrivals -->
<?php if (!empty($newArrivals)): ?>
<section class="py-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold mb-0">New Arrivals</h2>
            <a href="<?= BASE_URL ?>/pages/products.php?sort=newest" class="btn btn-outline-primary">
                View All <i class="bi bi-arrow-right"></i>
            </a>
        </div>
        <div class="row g-4">
            <?php foreach ($newArrivals as $product): ?>
                <div class="col-6 col-md-4 col-lg-3">
                    <div class="card product-card h-100">
                        <div class="card-img-wrapper">
                            <span class="product-badge badge bg-success">New</span>
                            <?php if ($product['main_image']): ?>
                                <img src="<?= BASE_URL ?>/../uploads/products/<?= sanitize($product['main_image']) ?>"
                                     class="card-img-top" alt="<?= sanitize($product['name']) ?>">
                            <?php else: ?>
                                <div class="card-img-top img-placeholder d-flex align-items-center justify-content-center"
                                     style="height: 200px;">
                                    <i class="bi bi-image fs-1"></i>
                                </div>
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
                            <div class="d-flex justify-content-between align-items-center mt-2">
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
    </div>
</section>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
