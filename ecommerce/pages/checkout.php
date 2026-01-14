<?php
/**
 * ============================================
 * CHECKOUT PAGE
 * ============================================
 *
 * This page handles the checkout process.
 * Features:
 * - Shipping information form
 * - Payment method selection
 * - Order summary
 * - Stripe payment integration
 *
 * FLOW:
 * 1. User fills shipping details
 * 2. Selects payment method
 * 3. Confirms order
 * 4. Payment is processed
 * 5. Order is created
 * 6. Redirected to confirmation
 *
 * ============================================
 */

require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../includes/auth_check.php';

$db = getDB();

// Get current user
$user = getCurrentUser();

// Check if cart is empty
if (empty($_SESSION['cart'])) {
    setFlashMessage('warning', 'Your cart is empty.');
    redirect(BASE_URL . '/pages/cart.php');
}

// Get cart items
$cartItems = [];
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

    if ($quantity > 0 && $quantity <= $product['stock_quantity']) {
        $cartItems[] = [
            'id' => $product['id'],
            'name' => $product['name'],
            'price' => $price,
            'original_price' => $product['price'],
            'quantity' => $quantity,
            'line_total' => $price * $quantity
        ];
    }
}

if (empty($cartItems)) {
    setFlashMessage('error', 'Some items in your cart are no longer available.');
    redirect(BASE_URL . '/pages/cart.php');
}

// Calculate totals
$totals = calculateCartTotals($cartItems);

// Initialize form data
$formData = [
    'first_name' => $user['first_name'] ?? '',
    'last_name' => $user['last_name'] ?? '',
    'email' => $user['email'] ?? '',
    'phone' => $user['phone'] ?? '',
    'address' => $user['address'] ?? '',
    'city' => $user['city'] ?? '',
    'postal_code' => $user['postal_code'] ?? '',
    'country' => $user['country'] ?? ''
];

$errors = [];

// Process checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission.';
    } else {
        // Get form data
        $formData = [
            'first_name' => sanitize($_POST['first_name'] ?? ''),
            'last_name' => sanitize($_POST['last_name'] ?? ''),
            'email' => sanitizeEmail($_POST['email'] ?? ''),
            'phone' => sanitize($_POST['phone'] ?? ''),
            'address' => sanitize($_POST['address'] ?? ''),
            'city' => sanitize($_POST['city'] ?? ''),
            'postal_code' => sanitize($_POST['postal_code'] ?? ''),
            'country' => sanitize($_POST['country'] ?? ''),
            'notes' => sanitize($_POST['notes'] ?? '')
        ];

        $paymentMethod = $_POST['payment_method'] ?? 'stripe';

        // Validation
        if (empty($formData['first_name'])) $errors[] = 'First name is required.';
        if (empty($formData['last_name'])) $errors[] = 'Last name is required.';
        if (empty($formData['email'])) $errors[] = 'Email is required.';
        if (empty($formData['address'])) $errors[] = 'Address is required.';
        if (empty($formData['city'])) $errors[] = 'City is required.';
        if (empty($formData['postal_code'])) $errors[] = 'Postal code is required.';
        if (empty($formData['country'])) $errors[] = 'Country is required.';

        if (empty($errors)) {
            try {
                $db->beginTransaction();

                // Generate order number
                $orderNumber = generateOrderNumber();

                // Create order
                $orderStmt = $db->prepare("
                    INSERT INTO orders (
                        order_number, user_id, status, payment_status, payment_method,
                        subtotal, tax_amount, shipping_amount, total_amount,
                        shipping_first_name, shipping_last_name, shipping_email, shipping_phone,
                        shipping_address, shipping_city, shipping_postal_code, shipping_country,
                        notes
                    ) VALUES (?, ?, 'pending', 'pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $orderStmt->execute([
                    $orderNumber,
                    $user['id'],
                    $paymentMethod,
                    $totals['subtotal'],
                    $totals['tax'],
                    $totals['shipping'],
                    $totals['total'],
                    $formData['first_name'],
                    $formData['last_name'],
                    $formData['email'],
                    $formData['phone'],
                    $formData['address'],
                    $formData['city'],
                    $formData['postal_code'],
                    $formData['country'],
                    $formData['notes']
                ]);

                $orderId = $db->lastInsertId();

                // Insert order items
                $itemStmt = $db->prepare("
                    INSERT INTO order_items (order_id, product_id, product_name, product_price, quantity, total)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");

                foreach ($cartItems as $item) {
                    $itemStmt->execute([
                        $orderId,
                        $item['id'],
                        $item['name'],
                        $item['price'],
                        $item['quantity'],
                        $item['line_total']
                    ]);

                    // Update stock
                    $stockStmt = $db->prepare("
                        UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?
                    ");
                    $stockStmt->execute([$item['quantity'], $item['id']]);
                }

                // For demo: simulate successful payment
                // In production, you would process payment with Stripe here
                if ($paymentMethod === 'stripe' || $paymentMethod === 'paypal') {
                    // Create payment log
                    $paymentStmt = $db->prepare("
                        INSERT INTO payment_logs (
                            order_id, user_id, payment_gateway, transaction_id,
                            amount, currency, status, ip_address
                        ) VALUES (?, ?, ?, ?, ?, ?, 'completed', ?)
                    ");
                    $paymentStmt->execute([
                        $orderId,
                        $user['id'],
                        $paymentMethod,
                        'DEMO_' . strtoupper(bin2hex(random_bytes(8))),
                        $totals['total'],
                        CURRENCY,
                        getClientIP()
                    ]);

                    // Update order as paid
                    $updateOrder = $db->prepare("
                        UPDATE orders SET payment_status = 'paid', status = 'processing' WHERE id = ?
                    ");
                    $updateOrder->execute([$orderId]);
                }

                $db->commit();

                // Clear cart
                $_SESSION['cart'] = [];

                // Log order
                logActivity('order_placed', 'Order placed: ' . $orderNumber, 'order', $orderId);

                // Store order ID for confirmation page
                $_SESSION['last_order_id'] = $orderId;
                $_SESSION['last_order_number'] = $orderNumber;

                setFlashMessage('success', SUCCESS_ORDER_PLACED);
                redirect(BASE_URL . '/pages/order_confirmation.php');

            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                writeLog('ERROR', 'Checkout failed: ' . $e->getMessage());
                $errors[] = 'Checkout failed. Please try again.';
            }
        }
    }
}

$pageTitle = 'Checkout';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/index.php">Home</a></li>
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/pages/cart.php">Cart</a></li>
            <li class="breadcrumb-item active">Checkout</li>
        </ol>
    </nav>

    <h2 class="fw-bold mb-4"><i class="bi bi-credit-card"></i> Checkout</h2>

    <!-- Error Messages -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= sanitize($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" action="" id="checkoutForm">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

        <div class="row">
            <!-- Shipping Form -->
            <div class="col-lg-7 mb-4">
                <!-- Shipping Details -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-truck"></i> Shipping Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="first_name" class="form-label">First Name *</label>
                                <input type="text" class="form-control" id="first_name" name="first_name"
                                       value="<?= sanitize($formData['first_name']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="last_name" class="form-label">Last Name *</label>
                                <input type="text" class="form-control" id="last_name" name="last_name"
                                       value="<?= sanitize($formData['last_name']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="email" name="email"
                                       value="<?= sanitize($formData['email']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="tel" class="form-control" id="phone" name="phone"
                                       value="<?= sanitize($formData['phone']) ?>">
                            </div>
                            <div class="col-12">
                                <label for="address" class="form-label">Street Address *</label>
                                <textarea class="form-control" id="address" name="address" rows="2" required
                                    ><?= sanitize($formData['address']) ?></textarea>
                            </div>
                            <div class="col-md-5">
                                <label for="city" class="form-label">City *</label>
                                <input type="text" class="form-control" id="city" name="city"
                                       value="<?= sanitize($formData['city']) ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label for="postal_code" class="form-label">Postal Code *</label>
                                <input type="text" class="form-control" id="postal_code" name="postal_code"
                                       value="<?= sanitize($formData['postal_code']) ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="country" class="form-label">Country *</label>
                                <select class="form-select" id="country" name="country" required>
                                    <option value="">Select Country</option>
                                    <option value="United States" <?= $formData['country'] === 'United States' ? 'selected' : '' ?>>United States</option>
                                    <option value="Canada" <?= $formData['country'] === 'Canada' ? 'selected' : '' ?>>Canada</option>
                                    <option value="United Kingdom" <?= $formData['country'] === 'United Kingdom' ? 'selected' : '' ?>>United Kingdom</option>
                                    <option value="Australia" <?= $formData['country'] === 'Australia' ? 'selected' : '' ?>>Australia</option>
                                    <option value="Germany" <?= $formData['country'] === 'Germany' ? 'selected' : '' ?>>Germany</option>
                                    <option value="France" <?= $formData['country'] === 'France' ? 'selected' : '' ?>>France</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label for="notes" class="form-label">Order Notes (Optional)</label>
                                <textarea class="form-control" id="notes" name="notes" rows="2"
                                          placeholder="Any special instructions for your order..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Method -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-wallet2"></i> Payment Method</h5>
                    </div>
                    <div class="card-body">
                        <div class="form-check mb-3 p-3 border rounded">
                            <input class="form-check-input" type="radio" name="payment_method"
                                   id="payment_stripe" value="stripe" checked>
                            <label class="form-check-label d-flex align-items-center" for="payment_stripe">
                                <i class="bi bi-credit-card fs-4 me-2 text-primary"></i>
                                <div>
                                    <strong>Credit/Debit Card</strong>
                                    <div class="text-muted small">Pay securely with Stripe</div>
                                </div>
                            </label>
                        </div>

                        <div class="form-check mb-3 p-3 border rounded">
                            <input class="form-check-input" type="radio" name="payment_method"
                                   id="payment_paypal" value="paypal">
                            <label class="form-check-label d-flex align-items-center" for="payment_paypal">
                                <i class="bi bi-paypal fs-4 me-2 text-info"></i>
                                <div>
                                    <strong>PayPal</strong>
                                    <div class="text-muted small">Pay with your PayPal account</div>
                                </div>
                            </label>
                        </div>

                        <!-- Demo Notice -->
                        <div class="alert alert-info small mb-0">
                            <i class="bi bi-info-circle"></i>
                            <strong>Demo Mode:</strong> This is a demonstration. No real payment will be processed.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="col-lg-5">
                <div class="card cart-summary sticky-top" style="top: 100px;">
                    <div class="card-header">
                        <h5 class="mb-0">Order Summary</h5>
                    </div>
                    <div class="card-body">
                        <!-- Order Items -->
                        <div class="order-items mb-3" style="max-height: 300px; overflow-y: auto;">
                            <?php foreach ($cartItems as $item): ?>
                                <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                                    <div>
                                        <span><?= sanitize($item['name']) ?></span>
                                        <span class="text-muted small">x<?= $item['quantity'] ?></span>
                                    </div>
                                    <span><?= formatPrice($item['line_total']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal</span>
                            <span><?= formatPrice($totals['subtotal']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Tax (<?= TAX_RATE * 100 ?>%)</span>
                            <span><?= formatPrice($totals['tax']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span>Shipping</span>
                            <span>
                                <?= $totals['shipping'] > 0 ? formatPrice($totals['shipping']) : '<span class="text-success">Free</span>' ?>
                            </span>
                        </div>

                        <hr>

                        <div class="d-flex justify-content-between mb-4">
                            <strong class="fs-5">Total</strong>
                            <strong class="fs-4 text-primary"><?= formatPrice($totals['total']) ?></strong>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-lock"></i> Place Order
                            </button>
                        </div>

                        <div class="text-center mt-3">
                            <small class="text-muted">
                                <i class="bi bi-shield-check text-success"></i>
                                Your payment is secure and encrypted
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
