<?php
/**
 * Admin Sidebar Navigation
 */
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<nav class="admin-sidebar d-flex flex-column">
    <!-- Brand -->
    <div class="p-3 border-bottom border-secondary">
        <a href="<?= BASE_URL ?>/admin/index.php" class="text-white text-decoration-none d-flex align-items-center">
            <i class="bi bi-speedometer2 fs-4 me-2"></i>
            <span class="fw-bold">Admin Panel</span>
        </a>
    </div>

    <!-- Navigation -->
    <ul class="nav flex-column p-3">
        <li class="nav-item">
            <a href="<?= BASE_URL ?>/admin/index.php"
               class="nav-link <?= $currentPage === 'index' ? 'active' : '' ?>">
                <i class="bi bi-house"></i> Dashboard
            </a>
        </li>

        <li class="nav-item mt-2">
            <span class="text-muted small text-uppercase px-3">Management</span>
        </li>

        <li class="nav-item">
            <a href="<?= BASE_URL ?>/admin/users.php"
               class="nav-link <?= $currentPage === 'users' ? 'active' : '' ?>">
                <i class="bi bi-people"></i> Users
            </a>
        </li>

        <li class="nav-item">
            <a href="<?= BASE_URL ?>/admin/products.php"
               class="nav-link <?= $currentPage === 'products' ? 'active' : '' ?>">
                <i class="bi bi-box-seam"></i> Products
            </a>
        </li>

        <li class="nav-item">
            <a href="<?= BASE_URL ?>/admin/categories.php"
               class="nav-link <?= $currentPage === 'categories' ? 'active' : '' ?>">
                <i class="bi bi-tags"></i> Categories
            </a>
        </li>

        <li class="nav-item">
            <a href="<?= BASE_URL ?>/admin/orders.php"
               class="nav-link <?= $currentPage === 'orders' ? 'active' : '' ?>">
                <i class="bi bi-bag"></i> Orders
            </a>
        </li>

        <li class="nav-item mt-2">
            <span class="text-muted small text-uppercase px-3">Reports</span>
        </li>

        <li class="nav-item">
            <a href="<?= BASE_URL ?>/admin/logs.php"
               class="nav-link <?= $currentPage === 'logs' ? 'active' : '' ?>">
                <i class="bi bi-journal-text"></i> System Logs
            </a>
        </li>
    </ul>

    <!-- Footer -->
    <div class="mt-auto p-3 border-top border-secondary">
        <a href="<?= BASE_URL ?>/index.php" class="text-muted text-decoration-none small">
            <i class="bi bi-arrow-left"></i> Back to Store
        </a>
    </div>
</nav>
