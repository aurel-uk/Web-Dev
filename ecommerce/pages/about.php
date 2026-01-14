<?php
/**
 * ============================================
 * ABOUT US PAGE
 * ============================================
 *
 * Static page displaying company information.
 *
 * ============================================
 */

require_once __DIR__ . '/../config/init.php';

$pageTitle = 'About Us';
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
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <!-- Hero Section -->
    <section class="bg-primary text-white py-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold">About <?= SITE_NAME ?></h1>
                    <p class="lead">Your trusted destination for quality products and exceptional service.</p>
                </div>
                <div class="col-lg-6 text-center">
                    <i class="bi bi-shop display-1"></i>
                </div>
            </div>
        </div>
    </section>

    <!-- Our Story -->
    <section class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <h2 class="text-center mb-4">Our Story</h2>
                    <p class="lead text-center text-muted mb-5">
                        Founded with a passion for delivering quality products to our customers.
                    </p>

                    <p>
                        Welcome to <?= SITE_NAME ?>! We started our journey with a simple mission:
                        to provide customers with high-quality products at competitive prices,
                        backed by exceptional customer service.
                    </p>
                    <p>
                        Our team is dedicated to sourcing the best products from trusted suppliers
                        and manufacturers. We believe that everyone deserves access to quality items
                        without breaking the bank.
                    </p>
                    <p>
                        Whether you're shopping for electronics, clothing, home goods, or anything
                        in between, we've got you covered. Our carefully curated selection ensures
                        that you'll find exactly what you're looking for.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Values -->
    <section class="py-5 bg-light">
        <div class="container">
            <h2 class="text-center mb-5">Our Values</h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center p-4">
                            <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex p-3 mb-3">
                                <i class="bi bi-star text-primary fs-2"></i>
                            </div>
                            <h4>Quality First</h4>
                            <p class="text-muted mb-0">
                                We never compromise on quality. Every product in our store meets
                                our high standards.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center p-4">
                            <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex p-3 mb-3">
                                <i class="bi bi-heart text-success fs-2"></i>
                            </div>
                            <h4>Customer Care</h4>
                            <p class="text-muted mb-0">
                                Our customers are at the heart of everything we do. Your satisfaction
                                is our priority.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center p-4">
                            <div class="bg-warning bg-opacity-10 rounded-circle d-inline-flex p-3 mb-3">
                                <i class="bi bi-shield-check text-warning fs-2"></i>
                            </div>
                            <h4>Trust & Security</h4>
                            <p class="text-muted mb-0">
                                Shop with confidence knowing your data is secure and your transactions
                                are protected.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats -->
    <section class="py-5">
        <div class="container">
            <div class="row text-center g-4">
                <div class="col-md-3 col-6">
                    <div class="display-4 fw-bold text-primary">1000+</div>
                    <p class="text-muted mb-0">Products</p>
                </div>
                <div class="col-md-3 col-6">
                    <div class="display-4 fw-bold text-primary">5000+</div>
                    <p class="text-muted mb-0">Happy Customers</p>
                </div>
                <div class="col-md-3 col-6">
                    <div class="display-4 fw-bold text-primary">50+</div>
                    <p class="text-muted mb-0">Categories</p>
                </div>
                <div class="col-md-3 col-6">
                    <div class="display-4 fw-bold text-primary">24/7</div>
                    <p class="text-muted mb-0">Support</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="py-5 bg-primary text-white">
        <div class="container text-center">
            <h2 class="mb-3">Ready to Start Shopping?</h2>
            <p class="lead mb-4">Browse our collection and find exactly what you need.</p>
            <a href="<?= BASE_URL ?>/pages/products.php" class="btn btn-light btn-lg px-5">
                <i class="bi bi-bag me-2"></i>Shop Now
            </a>
        </div>
    </section>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="<?= BASE_URL ?>/js/app.js"></script>
</body>
</html>
