<?php
/**
 * ============================================
 * FOOTER TEMPLATE
 * ============================================
 *
 * This file contains the HTML footer.
 * Include it at the bottom of every public page.
 *
 * USAGE:
 * require_once __DIR__ . '/../includes/footer.php';
 * ============================================
 */
?>
    </main>

    <!-- ============================================
         FOOTER
         ============================================ -->
    <footer class="bg-dark text-light py-5 mt-5">
        <div class="container">
            <div class="row">
                <!-- About column -->
                <div class="col-md-4 mb-4">
                    <h5 class="fw-bold mb-3">
                        <i class="bi bi-shop"></i> <?= SITE_NAME ?>
                    </h5>
                    <p class="text-muted">
                        Your one-stop shop for quality products at great prices.
                        We offer fast shipping and excellent customer service.
                    </p>
                    <div class="social-links">
                        <a href="#" class="text-light me-3"><i class="bi bi-facebook fs-5"></i></a>
                        <a href="#" class="text-light me-3"><i class="bi bi-twitter fs-5"></i></a>
                        <a href="#" class="text-light me-3"><i class="bi bi-instagram fs-5"></i></a>
                        <a href="#" class="text-light"><i class="bi bi-linkedin fs-5"></i></a>
                    </div>
                </div>

                <!-- Quick Links column -->
                <div class="col-md-2 mb-4">
                    <h6 class="fw-bold mb-3">Quick Links</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <a href="<?= BASE_URL ?>/index.php" class="text-muted text-decoration-none">Home</a>
                        </li>
                        <li class="mb-2">
                            <a href="<?= BASE_URL ?>/pages/products.php" class="text-muted text-decoration-none">Products</a>
                        </li>
                        <li class="mb-2">
                            <a href="<?= BASE_URL ?>/pages/cart.php" class="text-muted text-decoration-none">Cart</a>
                        </li>
                        <li class="mb-2">
                            <a href="<?= BASE_URL ?>/pages/about.php" class="text-muted text-decoration-none">About Us</a>
                        </li>
                    </ul>
                </div>

                <!-- Customer Service column -->
                <div class="col-md-3 mb-4">
                    <h6 class="fw-bold mb-3">Customer Service</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <a href="<?= BASE_URL ?>/pages/contact.php" class="text-muted text-decoration-none">Contact Us</a>
                        </li>
                        <li class="mb-2">
                            <a href="<?= BASE_URL ?>/pages/faq.php" class="text-muted text-decoration-none">FAQ</a>
                        </li>
                        <li class="mb-2">
                            <a href="<?= BASE_URL ?>/pages/shipping.php" class="text-muted text-decoration-none">Shipping Info</a>
                        </li>
                        <li class="mb-2">
                            <a href="<?= BASE_URL ?>/pages/returns.php" class="text-muted text-decoration-none">Returns Policy</a>
                        </li>
                    </ul>
                </div>

                <!-- Contact column -->
                <div class="col-md-3 mb-4">
                    <h6 class="fw-bold mb-3">Contact Info</h6>
                    <ul class="list-unstyled text-muted">
                        <li class="mb-2">
                            <i class="bi bi-geo-alt"></i> 123 Commerce Street, City
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-telephone"></i> +1 (555) 123-4567
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-envelope"></i> <?= SITE_EMAIL ?>
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-clock"></i> Mon-Fri: 9AM - 6PM
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Copyright -->
            <hr class="my-4">
            <div class="row">
                <div class="col-md-6 text-center text-md-start">
                    <p class="mb-0 text-muted">
                        &copy; <?= date('Y') ?> <?= SITE_NAME ?>. All rights reserved.
                    </p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <p class="mb-0 text-muted">
                        University Web Development Project
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <!-- ============================================
         JAVASCRIPT FILES
         ============================================ -->

    <!-- Bootstrap 5 JS Bundle (includes Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom JavaScript -->
    <script src="<?= BASE_URL ?>/js/app.js"></script>

    <!-- Page-specific scripts can be added here -->
    <?php if (isset($pageScripts)): ?>
        <?php foreach ($pageScripts as $script): ?>
            <script src="<?= $script ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
