<?php
/**
 * ============================================
 * HEADER TEMPLATE
 * ============================================
 *
 * This file contains the HTML header and navigation.
 * Include it at the top of every public page.
 *
 * USAGE:
 * require_once __DIR__ . '/../config/init.php';
 * $pageTitle = 'Page Title';
 * require_once __DIR__ . '/../includes/header.php';
 *
 * AVAILABLE VARIABLES:
 * $pageTitle - The page title (required)
 * $pageDescription - Meta description (optional)
 * $currentUser - Current logged-in user data (from init.php)
 * $isLoggedIn - Boolean, true if user is logged in
 * $isAdmin - Boolean, true if user is admin
 * ============================================
 */

// Default page title if not set
$pageTitle = $pageTitle ?? SITE_NAME;
$pageDescription = $pageDescription ?? 'Welcome to our e-commerce store';

// Get cart count for header
$cartCount = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $cartCount = array_sum(array_column($_SESSION['cart'], 'quantity'));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Required meta tags -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= sanitize($pageDescription) ?>">

    <!-- Page title -->
    <title><?= sanitize($pageTitle) ?> | <?= SITE_NAME ?></title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Custom CSS -->
    <link href="<?= BASE_URL ?>/css/style.css" rel="stylesheet">

    <!-- jQuery (required for our AJAX calls) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <!-- CSRF Token for AJAX requests -->
    <meta name="csrf-token" content="<?= $csrfToken ?>">
</head>
<body>
    <!-- ============================================
         NAVIGATION BAR
         ============================================ -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
        <div class="container">
            <!-- Brand/Logo -->
            <a class="navbar-brand fw-bold" href="<?= BASE_URL ?>/index.php">
                <i class="bi bi-shop"></i> <?= SITE_NAME ?>
            </a>

            <!-- Mobile toggle button -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Navigation links -->
            <div class="collapse navbar-collapse" id="navbarMain">
                <!-- Left side navigation -->
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/index.php">
                            <i class="bi bi-house"></i> Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/pages/products.php">
                            <i class="bi bi-grid"></i> Products
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="bi bi-tag"></i> Categories
                        </a>
                        <ul class="dropdown-menu">
                            <?php
                            // Fetch categories for dropdown
                            try {
                                $catStmt = getDB()->query("SELECT id, name, slug FROM categories WHERE status = 'active' ORDER BY sort_order");
                                while ($cat = $catStmt->fetch()) {
                                    echo '<li><a class="dropdown-item" href="' . BASE_URL . '/pages/products.php?category=' . $cat['slug'] . '">' . sanitize($cat['name']) . '</a></li>';
                                }
                            } catch (Exception $e) {
                                // Silently fail if categories can't be loaded
                            }
                            ?>
                        </ul>
                    </li>
                </ul>

                <!-- Search form -->
                <form class="d-flex me-3" action="<?= BASE_URL ?>/pages/products.php" method="GET">
                    <div class="input-group">
                        <input type="search" class="form-control" name="search" placeholder="Search products...">
                        <button class="btn btn-light" type="submit">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                </form>

                <!-- Right side navigation -->
                <ul class="navbar-nav">
                    <!-- Shopping Cart -->
                    <li class="nav-item">
                        <a class="nav-link position-relative" href="<?= BASE_URL ?>/pages/cart.php">
                            <i class="bi bi-cart3"></i> Cart
                            <?php if ($cartCount > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger cart-count">
                                    <?= $cartCount ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    </li>

                    <?php if ($isLoggedIn): ?>
                        <!-- Logged-in user menu -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle"></i>
                                <?= sanitize($_SESSION['user_name'] ?? 'Account') ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item" href="<?= BASE_URL ?>/pages/profile.php">
                                        <i class="bi bi-person"></i> My Profile
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?= BASE_URL ?>/pages/orders.php">
                                        <i class="bi bi-bag"></i> My Orders
                                    </a>
                                </li>
                                <?php if ($isAdmin): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item text-primary" href="<?= BASE_URL ?>/admin/index.php">
                                            <i class="bi bi-speedometer2"></i> Admin Panel
                                        </a>
                                    </li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item text-danger" href="<?= BASE_URL ?>/auth/logout.php">
                                        <i class="bi bi-box-arrow-right"></i> Logout
                                    </a>
                                </li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <!-- Guest menu -->
                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>/auth/login.php">
                                <i class="bi bi-box-arrow-in-right"></i> Login
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>/auth/register.php">
                                <i class="bi bi-person-plus"></i> Register
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- ============================================
         FLASH MESSAGES
         ============================================ -->
    <?php $flashMessage = getFlashMessage(); ?>
    <?php if ($flashMessage): ?>
        <div class="container mt-3">
            <div class="alert alert-<?= $flashMessage['type'] === 'error' ? 'danger' : $flashMessage['type'] ?> alert-dismissible fade show" role="alert">
                <?= sanitize($flashMessage['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    <?php endif; ?>

    <!-- ============================================
         MAIN CONTENT CONTAINER
         ============================================ -->
    <main class="py-4">
