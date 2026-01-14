<?php
/**
 * ============================================
 * PRODUCTS API ENDPOINT
 * ============================================
 *
 * Handles product data retrieval:
 * - list: Get paginated product list
 * - get: Get single product details
 * - search: Search products
 * - categories: Get all categories
 *
 * ============================================
 */

require_once __DIR__ . '/../config/init.php';

header('Content-Type: application/json');

$db = getDB();
$action = $_GET['action'] ?? 'list';

switch ($action) {
    // ----------------------------------------
    // LIST PRODUCTS
    // ----------------------------------------
    case 'list':
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(MAX_ITEMS_PER_PAGE, max(1, (int)($_GET['limit'] ?? ITEMS_PER_PAGE)));
        $category = sanitize($_GET['category'] ?? '');
        $featured = isset($_GET['featured']) ? 1 : null;
        $sale = isset($_GET['sale']) ? 1 : null;
        $sort = $_GET['sort'] ?? 'newest';

        // Build query
        $where = ["p.status = 'active'"];
        $params = [];

        if ($category) {
            $where[] = "c.slug = ?";
            $params[] = $category;
        }
        if ($featured) {
            $where[] = "p.is_featured = 1";
        }
        if ($sale) {
            $where[] = "p.sale_price IS NOT NULL";
        }

        $whereClause = implode(' AND ', $where);

        // Sort
        $sortOptions = [
            'newest' => 'p.created_at DESC',
            'oldest' => 'p.created_at ASC',
            'price_low' => 'COALESCE(p.sale_price, p.price) ASC',
            'price_high' => 'COALESCE(p.sale_price, p.price) DESC',
            'name_asc' => 'p.name ASC'
        ];
        $orderBy = $sortOptions[$sort] ?? $sortOptions['newest'];

        // Count
        $countStmt = $db->prepare("
            SELECT COUNT(*)
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE {$whereClause}
        ");
        $countStmt->execute($params);
        $total = $countStmt->fetchColumn();

        // Fetch products
        $offset = ($page - 1) * $limit;
        $stmt = $db->prepare("
            SELECT p.id, p.name, p.slug, p.short_description, p.price, p.sale_price,
                   p.stock_quantity, p.main_image, p.is_featured,
                   c.name as category_name, c.slug as category_slug
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE {$whereClause}
            ORDER BY {$orderBy}
            LIMIT {$limit} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $products = $stmt->fetchAll();

        jsonResponse(true, 'Products retrieved', [
            'products' => $products,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $total,
                'total_pages' => ceil($total / $limit)
            ]
        ]);
        break;

    // ----------------------------------------
    // GET SINGLE PRODUCT
    // ----------------------------------------
    case 'get':
        $id = (int)($_GET['id'] ?? 0);
        $slug = sanitize($_GET['slug'] ?? '');

        if (!$id && !$slug) {
            jsonResponse(false, 'Product ID or slug required', [], 400);
        }

        if ($id) {
            $stmt = $db->prepare("
                SELECT p.*, c.name as category_name, c.slug as category_slug
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.id = ? AND p.status = 'active'
            ");
            $stmt->execute([$id]);
        } else {
            $stmt = $db->prepare("
                SELECT p.*, c.name as category_name, c.slug as category_slug
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.slug = ? AND p.status = 'active'
            ");
            $stmt->execute([$slug]);
        }

        $product = $stmt->fetch();

        if (!$product) {
            jsonResponse(false, 'Product not found', [], 404);
        }

        // Get related products
        if ($product['category_id']) {
            $relatedStmt = $db->prepare("
                SELECT id, name, slug, price, sale_price, main_image
                FROM products
                WHERE category_id = ? AND id != ? AND status = 'active'
                ORDER BY RAND()
                LIMIT 4
            ");
            $relatedStmt->execute([$product['category_id'], $product['id']]);
            $product['related_products'] = $relatedStmt->fetchAll();
        }

        jsonResponse(true, 'Product retrieved', $product);
        break;

    // ----------------------------------------
    // SEARCH PRODUCTS
    // ----------------------------------------
    case 'search':
        $query = sanitize($_GET['q'] ?? '');

        if (strlen($query) < 2) {
            jsonResponse(false, 'Search query too short', [], 400);
        }

        $stmt = $db->prepare("
            SELECT p.id, p.name, p.slug, p.price, p.sale_price, p.main_image,
                   c.name as category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.status = 'active'
              AND (p.name LIKE ? OR p.short_description LIKE ? OR p.sku LIKE ?)
            ORDER BY
                CASE WHEN p.name LIKE ? THEN 1
                     WHEN p.name LIKE ? THEN 2
                     ELSE 3 END,
                p.name ASC
            LIMIT 20
        ");

        $exactMatch = $query;
        $startsWith = $query . '%';
        $contains = '%' . $query . '%';

        $stmt->execute([$contains, $contains, $contains, $exactMatch, $startsWith]);
        $products = $stmt->fetchAll();

        jsonResponse(true, 'Search results', [
            'query' => $query,
            'count' => count($products),
            'products' => $products
        ]);
        break;

    // ----------------------------------------
    // GET CATEGORIES
    // ----------------------------------------
    case 'categories':
        $stmt = $db->query("
            SELECT c.id, c.name, c.slug, c.description, c.image,
                   COUNT(p.id) as product_count
            FROM categories c
            LEFT JOIN products p ON c.id = p.category_id AND p.status = 'active'
            WHERE c.status = 'active'
            GROUP BY c.id
            ORDER BY c.sort_order
        ");
        $categories = $stmt->fetchAll();

        jsonResponse(true, 'Categories retrieved', ['categories' => $categories]);
        break;

    default:
        jsonResponse(false, 'Invalid action', [], 400);
}
