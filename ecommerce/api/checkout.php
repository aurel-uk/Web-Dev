<?php
/**
 * ============================================
 * CHECKOUT API ENDPOINT
 * ============================================
 *
 * Handles checkout process via AJAX.
 *
 * Actions:
 * - validate: Validate cart and shipping info
 * - calculate: Calculate order totals
 * - place_order: Create order (for non-Stripe payments)
 *
 * ============================================
 */

require_once __DIR__ . '/../config/init.php';

header('Content-Type: application/json');

// Require authentication
if (!isset($_SESSION['user_id'])) {
    jsonResponse(false, 'Authentication required', [], 401);
}

$db = getDB();
$action = $_REQUEST['action'] ?? '';

switch ($action) {
    // ----------------------------------------
    // VALIDATE CHECKOUT
    // ----------------------------------------
    case 'validate':
        // Check cart is not empty
        if (empty($_SESSION['cart'])) {
            jsonResponse(false, 'Cart is empty', [], 400);
        }

        // Validate all products are available
        $productIds = array_keys($_SESSION['cart']);
        $placeholders = str_repeat('?,', count($productIds) - 1) . '?';

        $stmt = $db->prepare("
            SELECT id, name, stock_quantity, status
            FROM products WHERE id IN ({$placeholders})
        ");
        $stmt->execute($productIds);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $errors = [];
        $validProducts = [];

        foreach ($products as $product) {
            $requestedQty = $_SESSION['cart'][$product['id']]['quantity'] ?? 0;

            if ($product['status'] !== 'active') {
                $errors[] = "{$product['name']} is no longer available.";
            } elseif ($product['stock_quantity'] < $requestedQty) {
                if ($product['stock_quantity'] <= 0) {
                    $errors[] = "{$product['name']} is out of stock.";
                } else {
                    $errors[] = "{$product['name']} only has {$product['stock_quantity']} in stock.";
                }
            } else {
                $validProducts[] = $product['id'];
            }
        }

        if (!empty($errors)) {
            jsonResponse(false, 'Some items are unavailable', ['errors' => $errors], 400);
        }

        jsonResponse(true, 'Cart validated successfully');
        break;

    // ----------------------------------------
    // CALCULATE TOTALS
    // ----------------------------------------
    case 'calculate':
        if (empty($_SESSION['cart'])) {
            jsonResponse(false, 'Cart is empty', [], 400);
        }

        $productIds = array_keys($_SESSION['cart']);
        $placeholders = str_repeat('?,', count($productIds) - 1) . '?';

        $stmt = $db->prepare("
            SELECT id, name, price, sale_price
            FROM products WHERE id IN ({$placeholders}) AND status = 'active'
        ");
        $stmt->execute($productIds);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $items = [];
        $subtotal = 0;

        foreach ($products as $product) {
            $qty = $_SESSION['cart'][$product['id']]['quantity'] ?? 1;
            $price = $product['sale_price'] ?? $product['price'];
            $lineTotal = $price * $qty;

            $items[] = [
                'id' => $product['id'],
                'name' => $product['name'],
                'price' => $price,
                'quantity' => $qty,
                'line_total' => $lineTotal
            ];

            $subtotal += $lineTotal;
        }

        $tax = round($subtotal * TAX_RATE, 2);
        $shipping = $subtotal >= FREE_SHIPPING_THRESHOLD ? 0 : DEFAULT_SHIPPING_COST;
        $total = round($subtotal + $tax + $shipping, 2);

        jsonResponse(true, 'Totals calculated', [
            'items' => $items,
            'subtotal' => $subtotal,
            'tax' => $tax,
            'tax_rate' => TAX_RATE,
            'shipping' => $shipping,
            'free_shipping_threshold' => FREE_SHIPPING_THRESHOLD,
            'total' => $total,
            'currency' => CURRENCY,
            'currency_symbol' => CURRENCY_SYMBOL
        ]);
        break;

    // ----------------------------------------
    // PLACE ORDER (Direct/COD)
    // ----------------------------------------
    case 'place_order':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(false, 'Method not allowed', [], 405);
        }

        if (empty($_SESSION['cart'])) {
            jsonResponse(false, 'Cart is empty', [], 400);
        }

        // Get shipping info
        $shipping = [
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

        $paymentMethod = $_POST['payment_method'] ?? 'cod';

        // Validate
        $required = ['first_name', 'last_name', 'email', 'address', 'city', 'postal_code', 'country'];
        foreach ($required as $field) {
            if (empty($shipping[$field])) {
                jsonResponse(false, ucfirst(str_replace('_', ' ', $field)) . ' is required', [], 400);
            }
        }

        try {
            $db->beginTransaction();

            // Get cart items
            $productIds = array_keys($_SESSION['cart']);
            $placeholders = str_repeat('?,', count($productIds) - 1) . '?';

            $stmt = $db->prepare("
                SELECT id, name, price, sale_price, stock_quantity
                FROM products WHERE id IN ({$placeholders}) AND status = 'active'
            ");
            $stmt->execute($productIds);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate totals
            $items = [];
            $subtotal = 0;

            foreach ($products as $product) {
                $qty = $_SESSION['cart'][$product['id']]['quantity'] ?? 1;

                // Final stock check
                if ($qty > $product['stock_quantity']) {
                    throw new Exception("{$product['name']} doesn't have enough stock.");
                }

                $price = $product['sale_price'] ?? $product['price'];
                $lineTotal = $price * $qty;

                $items[] = [
                    'id' => $product['id'],
                    'name' => $product['name'],
                    'price' => $price,
                    'quantity' => $qty,
                    'total' => $lineTotal
                ];

                $subtotal += $lineTotal;
            }

            $taxAmount = round($subtotal * TAX_RATE, 2);
            $shippingAmount = $subtotal >= FREE_SHIPPING_THRESHOLD ? 0 : DEFAULT_SHIPPING_COST;
            $totalAmount = $subtotal + $taxAmount + $shippingAmount;

            // Generate order number
            $orderNumber = generateOrderNumber();

            // Determine payment status
            $paymentStatus = in_array($paymentMethod, ['stripe', 'paypal']) ? 'pending' : 'pending';

            // Create order
            $orderStmt = $db->prepare("
                INSERT INTO orders (
                    order_number, user_id, status, payment_status, payment_method,
                    subtotal, tax_amount, shipping_amount, total_amount,
                    shipping_first_name, shipping_last_name, shipping_email, shipping_phone,
                    shipping_address, shipping_city, shipping_postal_code, shipping_country, notes
                ) VALUES (?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $orderStmt->execute([
                $orderNumber, $_SESSION['user_id'], $paymentStatus, $paymentMethod,
                $subtotal, $taxAmount, $shippingAmount, $totalAmount,
                $shipping['first_name'], $shipping['last_name'], $shipping['email'],
                $shipping['phone'], $shipping['address'], $shipping['city'],
                $shipping['postal_code'], $shipping['country'], $shipping['notes']
            ]);
            $orderId = $db->lastInsertId();

            // Create order items and update stock
            $itemStmt = $db->prepare("
                INSERT INTO order_items (order_id, product_id, product_name, product_price, quantity, total)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stockStmt = $db->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");

            foreach ($items as $item) {
                $itemStmt->execute([$orderId, $item['id'], $item['name'], $item['price'], $item['quantity'], $item['total']]);
                $stockStmt->execute([$item['quantity'], $item['id']]);
            }

            $db->commit();

            // Clear cart
            $_SESSION['cart'] = [];

            // Store for confirmation
            $_SESSION['last_order_id'] = $orderId;
            $_SESSION['last_order_number'] = $orderNumber;

            logActivity('order_placed', "Order {$orderNumber} placed", 'order', $orderId);

            jsonResponse(true, SUCCESS_ORDER_PLACED, [
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'total' => $totalAmount,
                'redirect' => BASE_URL . '/pages/order_confirmation.php'
            ]);

        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            writeLog('ERROR', 'Order placement failed: ' . $e->getMessage());
            jsonResponse(false, $e->getMessage(), [], 500);
        }
        break;

    default:
        jsonResponse(false, 'Invalid action', [], 400);
}
