<?php
/**
 * ============================================
 * SHOPPING CART PAGE
 * ============================================
 *
 * Displays the user's shopping cart.
 * Features:
 * - View cart items
 * - Update quantities
 * - Remove items
 * - Cart totals
 * - Proceed to checkout
 *
 * Cart is stored in session for simplicity.
 * ============================================
 */

require_once __DIR__ . '/../config/init.php';

$db = getDB();

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Handle cart actions (non-AJAX fallback)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'update':
            $productId = (int)($_POST['product_id'] ?? 0);
            $quantity = (int)($_POST['quantity'] ?? 1);

            if ($productId && $quantity > 0) {
                if (isset($_SESSION['cart'][$productId])) {
                    $_SESSION['cart'][$productId]['quantity'] = $quantity;
                }
            }
            break;

        case 'remove':
            $productId = (int)($_POST['product_id'] ?? 0);
            if ($productId && isset($_SESSION['cart'][$productId])) {
                unset($_SESSION['cart'][$productId]);
            }
            break;

        case 'clear':
            $_SESSION['cart'] = [];
            break;
    }

    redirect(BASE_URL . '/pages/cart.php');
}

// Get cart items with product details
$cartItems = [];
$cartTotal = 0;

if (!empty($_SESSION['cart'])) {
    $productIds = array_keys($_SESSION['cart']);
    $placeholders = str_repeat('?,', count($productIds) - 1) . '?';

    $stmt = $db->prepare("
        SELECT id, name, slug, price, sale_price, stock_quantity, main_image
        FROM products
        WHERE id IN ({$placeholders}) AND status = 'active'
    ");
    $stmt->execute($productIds);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($products as $product) {
        $quantity = $_SESSION['cart'][$product['id']]['quantity'] ?? 1;
        $price = $product['sale_price'] ?? $product['price'];

        // Check stock and adjust if needed
        if ($quantity > $product['stock_quantity']) {
            $quantity = $product['stock_quantity'];
            $_SESSION['cart'][$product['id']]['quantity'] = $quantity;
        }

        if ($quantity > 0) {
            $cartItems[] = [
                'id' => $product['id'],
                'name' => $product['name'],
                'slug' => $product['slug'],
                'price' => $product['price'],
                'sale_price' => $product['sale_price'],
                'quantity' => $quantity,
                'stock_quantity' => $product['stock_quantity'],
                'main_image' => $product['main_image'],
                'line_total' => $price * $quantity
            ];
            $cartTotal += $price * $quantity;
        }
    }
}

// Calculate totals
$totals = calculateCartTotals($cartItems);

$pageTitle = 'Shopping Cart';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/index.php">Home</a></li>
            <li class="breadcrumb-item active">Shopping Cart</li>
        </ol>
    </nav>

    <h2 class="fw-bold mb-4">
        <i class="bi bi-cart3"></i> Shopping Cart
        <?php if (count($cartItems) > 0): ?>
            <span class="text-muted fs-5">(<?= count($cartItems) ?> items)</span>
        <?php endif; ?>
    </h2>

    <?php if (empty($cartItems)): ?>
        <!-- Empty Cart -->
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bi bi-cart-x text-muted" style="font-size: 5rem;"></i>
                <h4 class="mt-4">Your cart is empty</h4>
                <p class="text-muted mb-4">
                    Looks like you haven't added any items to your cart yet.
                </p>
                <a href="<?= BASE_URL ?>/pages/products.php" class="btn btn-primary btn-lg">
                    <i class="bi bi-shop"></i> Start Shopping
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="row">
            <!-- Cart Items -->
            <div class="col-lg-8 mb-4">
                <div class="card">
                    <div class="card-body p-0">
                        <div class="cart-items">
                            <?php foreach ($cartItems as $item): ?>
                                <div class="cart-item d-flex align-items-center p-3"
                                     data-product-id="<?= $item['id'] ?>">
                                    <!-- Product Image -->
                                    <div class="me-3">
                                        <?php if ($item['main_image']): ?>
                                            <img src="<?= BASE_URL ?>/../uploads/products/<?= sanitize($item['main_image']) ?>"
                                                 class="cart-item-image"
                                                 alt="<?= sanitize($item['name']) ?>">
                                        <?php else: ?>
                                            <div class="cart-item-image bg-light d-flex align-items-center justify-content-center">
                                                <i class="bi bi-image text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Product Details -->
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1">
                                            <a href="<?= BASE_URL ?>/pages/product_detail.php?slug=<?= sanitize($item['slug']) ?>"
                                               class="text-dark text-decoration-none">
                                                <?= sanitize($item['name']) ?>
                                            </a>
                                        </h6>
                                        <div class="text-muted small">
                                            <?php if ($item['sale_price']): ?>
                                                <span class="text-decoration-line-through"><?= formatPrice($item['price']) ?></span>
                                                <span class="text-danger"><?= formatPrice($item['sale_price']) ?></span>
                                            <?php else: ?>
                                                <?= formatPrice($item['price']) ?>
                                            <?php endif; ?>
                                            each
                                        </div>
                                    </div>

                                    <!-- Quantity -->
                                    <div class="mx-3">
                                        <form method="POST" class="quantity-form d-flex align-items-center">
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="product_id" value="<?= $item['id'] ?>">
                                            <div class="quantity-input input-group" style="width: 120px;">
                                                <button class="btn btn-outline-secondary btn-sm quantity-btn decrement"
                                                        type="button">-</button>
                                                <input type="number" class="form-control form-control-sm text-center"
                                                       name="quantity" value="<?= $item['quantity'] ?>"
                                                       min="1" max="<?= $item['stock_quantity'] ?>">
                                                <button class="btn btn-outline-secondary btn-sm quantity-btn increment"
                                                        type="button">+</button>
                                            </div>
                                        </form>
                                    </div>

                                    <!-- Line Total -->
                                    <div class="text-end mx-3" style="min-width: 80px;">
                                        <strong><?= formatPrice($item['line_total']) ?></strong>
                                    </div>

                                    <!-- Remove Button -->
                                    <form method="POST">
                                        <input type="hidden" name="action" value="remove">
                                        <input type="hidden" name="product_id" value="<?= $item['id'] ?>">
                                        <button type="submit" class="btn btn-link text-danger p-0"
                                                title="Remove item">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Cart Actions -->
                    <div class="card-footer bg-white d-flex justify-content-between">
                        <a href="<?= BASE_URL ?>/pages/products.php" class="btn btn-outline-primary">
                            <i class="bi bi-arrow-left"></i> Continue Shopping
                        </a>
                        <form method="POST">
                            <input type="hidden" name="action" value="clear">
                            <button type="submit" class="btn btn-outline-danger"
                                    onclick="return confirm('Are you sure you want to clear your cart?')">
                                <i class="bi bi-trash"></i> Clear Cart
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Cart Summary -->
            <div class="col-lg-4">
                <div class="card cart-summary">
                    <div class="card-header">
                        <h5 class="mb-0">Order Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal</span>
                            <span id="cart-subtotal"><?= formatPrice($totals['subtotal']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Tax (<?= TAX_RATE * 100 ?>%)</span>
                            <span id="cart-tax"><?= formatPrice($totals['tax']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span>Shipping</span>
                            <span id="cart-shipping">
                                <?php if ($totals['shipping'] > 0): ?>
                                    <?= formatPrice($totals['shipping']) ?>
                                <?php else: ?>
                                    <span class="text-success">Free</span>
                                <?php endif; ?>
                            </span>
                        </div>

                        <?php if ($totals['shipping'] > 0): ?>
                            <div class="alert alert-info small mb-3">
                                <i class="bi bi-info-circle"></i>
                                Add <?= formatPrice(FREE_SHIPPING_THRESHOLD - $totals['subtotal']) ?> more
                                for free shipping!
                            </div>
                        <?php endif; ?>

                        <hr>

                        <div class="d-flex justify-content-between mb-3">
                            <strong>Total</strong>
                            <strong class="fs-4" id="cart-total"><?= formatPrice($totals['total']) ?></strong>
                        </div>

                        <div class="d-grid">
                            <a href="<?= BASE_URL ?>/pages/checkout.php" class="btn btn-primary btn-lg">
                                <i class="bi bi-credit-card"></i> Proceed to Checkout
                            </a>
                        </div>

                        <!-- Secure Checkout Badge -->
                        <div class="text-center mt-3">
                            <small class="text-muted">
                                <i class="bi bi-shield-check text-success"></i>
                                Secure checkout with SSL encryption
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Promo Code -->
                <div class="card mt-3">
                    <div class="card-body">
                        <h6 class="mb-3">Have a Promo Code?</h6>
                        <div class="input-group">
                            <input type="text" class="form-control" placeholder="Enter code">
                            <button class="btn btn-outline-secondary">Apply</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
