<?php
/**
 * ============================================
 * PAYMENT API ENDPOINT
 * ============================================
 *
 * Handles payment processing via Stripe (sandbox).
 *
 * Actions:
 * - create_intent: Create a Stripe payment intent
 * - confirm: Confirm payment and create order
 * - webhook: Handle Stripe webhooks
 *
 * NOTE: This is a DEMO implementation.
 * In production, you would integrate the actual Stripe PHP SDK.
 *
 * ============================================
 */

require_once __DIR__ . '/../config/init.php';

header('Content-Type: application/json');

$db = getDB();
$action = $_REQUEST['action'] ?? '';

switch ($action) {
    // ----------------------------------------
    // CREATE PAYMENT INTENT
    // ----------------------------------------
    case 'create_intent':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(false, 'Method not allowed', [], 405);
        }

        // Require authentication
        if (!isset($_SESSION['user_id'])) {
            jsonResponse(false, 'Authentication required', [], 401);
        }

        // Check cart
        if (empty($_SESSION['cart'])) {
            jsonResponse(false, 'Cart is empty', [], 400);
        }

        // Calculate total
        $cartItems = [];
        $productIds = array_keys($_SESSION['cart']);
        $placeholders = str_repeat('?,', count($productIds) - 1) . '?';

        $stmt = $db->prepare("
            SELECT id, name, price, sale_price, stock_quantity
            FROM products WHERE id IN ({$placeholders}) AND status = 'active'
        ");
        $stmt->execute($productIds);
        $products = $stmt->fetchAll();

        $subtotal = 0;
        foreach ($products as $product) {
            $qty = $_SESSION['cart'][$product['id']]['quantity'] ?? 1;
            $price = $product['sale_price'] ?? $product['price'];
            $subtotal += $price * $qty;

            $cartItems[] = [
                'id' => $product['id'],
                'name' => $product['name'],
                'price' => $price,
                'quantity' => $qty
            ];
        }

        $tax = $subtotal * TAX_RATE;
        $shipping = $subtotal >= FREE_SHIPPING_THRESHOLD ? 0 : DEFAULT_SHIPPING_COST;
        $total = $subtotal + $tax + $shipping;

        // In a real implementation, you would:
        // 1. Include the Stripe PHP SDK
        // 2. Create a PaymentIntent:
        //
        // \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);
        // $paymentIntent = \Stripe\PaymentIntent::create([
        //     'amount' => $total * 100, // Stripe uses cents
        //     'currency' => strtolower(CURRENCY),
        //     'automatic_payment_methods' => ['enabled' => true],
        // ]);

        // For demo purposes, we simulate a payment intent
        $demoPaymentIntentId = 'pi_demo_' . bin2hex(random_bytes(16));
        $demoClientSecret = $demoPaymentIntentId . '_secret_' . bin2hex(random_bytes(12));

        // Store in session for verification
        $_SESSION['payment_intent'] = [
            'id' => $demoPaymentIntentId,
            'amount' => $total,
            'items' => $cartItems,
            'created' => time()
        ];

        jsonResponse(true, 'Payment intent created', [
            'clientSecret' => $demoClientSecret,
            'paymentIntentId' => $demoPaymentIntentId,
            'amount' => $total,
            'currency' => CURRENCY,
            'publishableKey' => STRIPE_PUBLISHABLE_KEY
        ]);
        break;

    // ----------------------------------------
    // CONFIRM PAYMENT (Demo mode)
    // ----------------------------------------
    case 'confirm':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(false, 'Method not allowed', [], 405);
        }

        if (!isset($_SESSION['user_id'])) {
            jsonResponse(false, 'Authentication required', [], 401);
        }

        $paymentIntentId = $_POST['payment_intent_id'] ?? '';
        $paymentMethod = $_POST['payment_method'] ?? 'stripe';

        // Verify payment intent exists in session
        if (!isset($_SESSION['payment_intent']) || $_SESSION['payment_intent']['id'] !== $paymentIntentId) {
            jsonResponse(false, 'Invalid payment session', [], 400);
        }

        $paymentData = $_SESSION['payment_intent'];

        // Get shipping details
        $shipping = [
            'first_name' => sanitize($_POST['first_name'] ?? ''),
            'last_name' => sanitize($_POST['last_name'] ?? ''),
            'email' => sanitizeEmail($_POST['email'] ?? ''),
            'phone' => sanitize($_POST['phone'] ?? ''),
            'address' => sanitize($_POST['address'] ?? ''),
            'city' => sanitize($_POST['city'] ?? ''),
            'postal_code' => sanitize($_POST['postal_code'] ?? ''),
            'country' => sanitize($_POST['country'] ?? '')
        ];

        // Validate shipping
        if (empty($shipping['first_name']) || empty($shipping['last_name']) ||
            empty($shipping['email']) || empty($shipping['address']) ||
            empty($shipping['city']) || empty($shipping['postal_code']) ||
            empty($shipping['country'])) {
            jsonResponse(false, 'Shipping information is incomplete', [], 400);
        }

        try {
            $db->beginTransaction();

            // Calculate totals
            $subtotal = 0;
            foreach ($paymentData['items'] as $item) {
                $subtotal += $item['price'] * $item['quantity'];
            }
            $taxAmount = $subtotal * TAX_RATE;
            $shippingAmount = $subtotal >= FREE_SHIPPING_THRESHOLD ? 0 : DEFAULT_SHIPPING_COST;
            $totalAmount = $subtotal + $taxAmount + $shippingAmount;

            // Generate order number
            $orderNumber = generateOrderNumber();

            // Create order
            $orderStmt = $db->prepare("
                INSERT INTO orders (
                    order_number, user_id, status, payment_status, payment_method,
                    subtotal, tax_amount, shipping_amount, total_amount,
                    shipping_first_name, shipping_last_name, shipping_email, shipping_phone,
                    shipping_address, shipping_city, shipping_postal_code, shipping_country
                ) VALUES (?, ?, 'processing', 'paid', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $orderStmt->execute([
                $orderNumber, $_SESSION['user_id'], $paymentMethod,
                $subtotal, $taxAmount, $shippingAmount, $totalAmount,
                $shipping['first_name'], $shipping['last_name'], $shipping['email'],
                $shipping['phone'], $shipping['address'], $shipping['city'],
                $shipping['postal_code'], $shipping['country']
            ]);
            $orderId = $db->lastInsertId();

            // Create order items and update stock
            $itemStmt = $db->prepare("
                INSERT INTO order_items (order_id, product_id, product_name, product_price, quantity, total)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stockStmt = $db->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");

            foreach ($paymentData['items'] as $item) {
                $lineTotal = $item['price'] * $item['quantity'];
                $itemStmt->execute([$orderId, $item['id'], $item['name'], $item['price'], $item['quantity'], $lineTotal]);
                $stockStmt->execute([$item['quantity'], $item['id']]);
            }

            // Create payment log
            $transactionId = 'txn_demo_' . strtoupper(bin2hex(random_bytes(8)));
            $paymentLogStmt = $db->prepare("
                INSERT INTO payment_logs (
                    order_id, user_id, payment_gateway, transaction_id, payment_intent_id,
                    amount, currency, status, ip_address
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', ?)
            ");
            $paymentLogStmt->execute([
                $orderId, $_SESSION['user_id'], $paymentMethod, $transactionId,
                $paymentIntentId, $totalAmount, CURRENCY, getClientIP()
            ]);

            $db->commit();

            // Clear cart and payment session
            $_SESSION['cart'] = [];
            unset($_SESSION['payment_intent']);

            // Store for confirmation page
            $_SESSION['last_order_id'] = $orderId;
            $_SESSION['last_order_number'] = $orderNumber;

            // Log activity
            logActivity('order_placed', "Order {$orderNumber} placed via payment API", 'order', $orderId);

            jsonResponse(true, SUCCESS_ORDER_PLACED, [
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'transaction_id' => $transactionId,
                'redirect' => BASE_URL . '/pages/order_confirmation.php'
            ]);

        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            writeLog('ERROR', 'Payment confirmation failed: ' . $e->getMessage());
            jsonResponse(false, 'Payment processing failed', [], 500);
        }
        break;

    // ----------------------------------------
    // GET STRIPE PUBLISHABLE KEY
    // ----------------------------------------
    case 'get_key':
        jsonResponse(true, 'Key retrieved', [
            'publishableKey' => STRIPE_PUBLISHABLE_KEY
        ]);
        break;

    // ----------------------------------------
    // STRIPE WEBHOOK (placeholder)
    // ----------------------------------------
    case 'webhook':
        // In production, you would:
        // 1. Verify the webhook signature
        // 2. Handle different event types (payment_intent.succeeded, etc.)
        // 3. Update order status accordingly

        $payload = file_get_contents('php://input');
        $event = json_decode($payload, true);

        if ($event && isset($event['type'])) {
            writeLog('INFO', 'Stripe webhook received: ' . $event['type']);

            // Handle event types
            switch ($event['type']) {
                case 'payment_intent.succeeded':
                    // Update order to paid
                    break;
                case 'payment_intent.payment_failed':
                    // Update order to failed
                    break;
            }
        }

        jsonResponse(true, 'Webhook received');
        break;

    default:
        jsonResponse(false, 'Invalid action', [], 400);
}
