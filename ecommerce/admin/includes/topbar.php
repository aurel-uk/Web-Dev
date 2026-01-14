<?php
/**
 * Admin Top Bar
 */
?>
<nav class="navbar navbar-expand navbar-light bg-white border-bottom">
    <div class="container-fluid">
        <span class="navbar-text">
            <?= date('l, F j, Y') ?>
        </span>

        <ul class="navbar-nav ms-auto">
            <!-- Notifications (placeholder) -->
            <li class="nav-item dropdown">
                <a class="nav-link" href="#" data-bs-toggle="dropdown">
                    <i class="bi bi-bell"></i>
                </a>
                <div class="dropdown-menu dropdown-menu-end">
                    <h6 class="dropdown-header">Notifications</h6>
                    <a class="dropdown-item small" href="#">No new notifications</a>
                </div>
            </li>

            <!-- User Menu -->
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle me-1"></i>
                    <?= sanitize($_SESSION['user_name'] ?? 'Admin') ?>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <a class="dropdown-item" href="<?= BASE_URL ?>/pages/profile.php">
                            <i class="bi bi-person me-2"></i> My Profile
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item text-danger" href="<?= BASE_URL ?>/auth/logout.php">
                            <i class="bi bi-box-arrow-right me-2"></i> Logout
                        </a>
                    </li>
                </ul>
            </li>
        </ul>
    </div>
</nav>
