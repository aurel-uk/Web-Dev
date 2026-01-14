<?php
/**
 * ============================================
 * CART API ENDPOINT
 * ============================================
 *
 * Handles cart operations via AJAX:
 * - add: Add item to cart
 * - update: Update item quantity
 * - remove: Remove item from cart
 * - get: Get cart contents
 * - clear: Clear entire cart
 *
 * All responses are in JSON format.
 *
 * ============================================
 */

require_once __DIR__ . '/../config/init.php';

// Set JSON response header
header('Content-Type: application/json');

// Only accept POST requests for modifications
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(false, 'Method not allowed', [], 405);
}

// Initialize cart if needed
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$db = getDB();
$action = $_REQUEST['action'] ?? '';

switch ($action) {
    // ----------------------------------------
    // ADD TO CART
    // ----------------------------------------
    case 'add':
        $productId = (int)($_POST['product_id'] ?? 0);
        $quantity = max(1, (int)($_POST['quantity'] ?? 1));

        if (!$productId) {
            jsonResponse(false, 'Invalid product ID', [], 400);
        }

        // Check if product exists and is available
        $stmt = $db->prepare("
            SELECT id, name, price, sale_price, stock_quantity, status
            FROM products
            WHERE id = ? AND status = 'active'
        ");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();

        if (!$product) {
            jsonResponse(false, 'Product not found or unavailable', [], 404);
        }

        if ($product['stock_quantity'] < 1) {
            jsonResponse(false, 'Product is out of stock', [], 400);
        }

        // Calculate total quantity (existing + new)
        $existingQty = $_SESSION['cart'][$productId]['quantity'] ?? 0;
        $newQuantity = $existingQty + $quantity;

        // Check stock limit
        if ($newQuantity > $product['stock_quantity']) {
            $newQuantity = $product['stock_quantity'];
        }

        // Add/update cart
        $_SESSION['cart'][$productId] = [
            'quantity' => $newQuantity,
            'added_at' => time()
        ];

        // Calculate cart count
        $cartCount = array_sum(array_column($_SESSION['cart'], 'quantity'));

        logActivity('cart_add', 'Added product to cart', 'product', $productId);

        jsonResponse(true, 'Item added to cart', [
            'cart_count' => $cartCount,
            'product_id' => $productId,
            'quantity' => $newQuantity
        ]);
        break;

    // ----------------------------------------
    // UPDATE CART QUANTITY
    // ----------------------------------------
    case 'update':
        $productId = (int)($_POST['product_id'] ?? 0);
        $quantity = (int)($_POST['quantity'] ?? 0);

        if (!$productId) {
            jsonResponse(false, 'Invalid product ID', [], 400);
        }

        if (!isset($_SESSION['cart'][$productId])) {
            jsonResponse(false, 'Product not in cart', [], 404);
        }

        if ($quantity < 1) {
            // Remove if quantity is 0 or less
            unset($_SESSION['cart'][$productId]);
        } else {
            // Check stock
            $stmt = $db->prepare("SELECT stock_quantity FROM products WHERE id = ?");
            $stmt->execute([$productId]);
            $product = $stmt->fetch();

            if ($product && $quantity > $product['stock_quantity']) {
                $quantity = $product['stock_quantity'];
            }

            $_SESSION['cart'][$productId]['quantity'] = $quantity;
        }

        // Recalculate totals
        $totals = getCartTotals($db);

        jsonResponse(true, 'Cart updated', $totals);
        break;

    // ----------------------------------------
    // REMOVE FROM CART
    // ----------------------------------------
    case 'remove':
        $productId = (int)($_POST['product_id'] ?? 0);

        if (!$productId) {
            jsonResponse(false, 'Invalid product ID', [], 400);
        }

        if (isset($_SESSION['cart'][$productId])) {
            unset($_SESSION['cart'][$productId]);
        }

        $totals = getCartTotals($db);

        jsonResponse(true, 'Item removed from cart', $totals);
        break;

    // ----------------------------------------
    // GET CART CONTENTS
    // ----------------------------------------
    case 'get':
        $totals = getCartTotals($db);
        jsonResponse(true, 'Cart retrieved', $totals);
        break;

    // ----------------------------------------
    // CLEAR CART
    // ----------------------------------------
    case 'clear':
        $_SESSION['cart'] = [];
        jsonResponse(true, 'Cart cleared', [
            'cart_count' => 0,
            'subtotal' => 0,
            'tax' => 0,
            'shipping' => 0,
            'total' => 0
        ]);
        break;

    default:
        jsonResponse(false, 'Invalid action', [], 400);
}

/**
 * Helper function to get cart totals
 */
function getCartTotals($db) {
    if (empty($_SESSION['cart'])) {
        return [
            'cart_count' => 0,
            'items' => [],
            'subtotal' => 0,
            'tax' => 0,
            'shipping' => DEFAULT_SHIPPING_COST,
            'total' => 0
        ];
    }

    $productIds = array_keys($_SESSION['cart']);
    $placeholders = str_repeat('?,', count($productIds) - 1) . '?';

    $stmt = $db->prepare("
        SELECT id, name, price, sale_price, stock_quantity
        FROM products
        WHERE id IN ({$placeholders}) AND status = 'active'
    ");
    $stmt->execute($productIds);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $items = [];
    $subtotal = 0;
    $cartCount = 0;

    foreach ($products as $product) {
        $quantity = $_SESSION['cart'][$product['id']]['quantity'] ?? 1;
        $price = $product['sale_price'] ?? $product['price'];
        $lineTotal = $price * $quantity;

        $items[] = [
            'id' => $product['id'],
            'name' => $product['name'],
            'price' => $price,
            'quantity' => $quantity,
            'line_total' => $lineTotal
        ];

        $subtotal += $lineTotal;
        $cartCount += $quantity;
    }

    $tax = $subtotal * TAX_RATE;
    $shipping = $subtotal >= FREE_SHIPPING_THRESHOLD ? 0 : DEFAULT_SHIPPING_COST;
    $total = $subtotal + $tax + $shipping;

    return [
        'cart_count' => $cartCount,
        'items' => $items,
        'subtotal' => round($subtotal, 2),
        'tax' => round($tax, 2),
        'shipping' => round($shipping, 2),
        'total' => round($total, 2)
    ];
}
