<?php
/**
 * ============================================
 * HELPER FUNCTIONS
 * ============================================
 *
 * This file contains reusable functions used throughout the application.
 * These functions handle common tasks like:
 * - Input sanitization
 * - Validation
 * - Response formatting
 * - Security operations
 *
 * TIP: Functions are organized by category for easy navigation.
 * ============================================
 */

// ============================================
// INPUT SANITIZATION FUNCTIONS
// ============================================

/**
 * Sanitize a string for safe output in HTML
 *
 * This prevents XSS (Cross-Site Scripting) attacks by converting
 * special characters to HTML entities.
 *
 * EXAMPLE:
 * $name = sanitize($_POST['name']);
 * echo $name; // Safe to output
 *
 * @param string $input The input to sanitize
 * @return string The sanitized string
 */
function sanitize(string $input): string
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize an email address
 *
 * Removes any characters that aren't valid in emails.
 *
 * @param string $email The email to sanitize
 * @return string The sanitized email
 */
function sanitizeEmail(string $email): string
{
    return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
}

/**
 * Sanitize an integer
 *
 * @param mixed $input The input to sanitize
 * @return int The sanitized integer
 */
function sanitizeInt($input): int
{
    return (int) filter_var($input, FILTER_SANITIZE_NUMBER_INT);
}

/**
 * Sanitize a float/decimal number
 *
 * @param mixed $input The input to sanitize
 * @return float The sanitized float
 */
function sanitizeFloat($input): float
{
    return (float) filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
}

// ============================================
// VALIDATION FUNCTIONS
// ============================================

/**
 * Validate an email address
 *
 * @param string $email The email to validate
 * @return bool True if valid, false otherwise
 */
function isValidEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate password strength
 *
 * Password must:
 * - Be at least PASSWORD_MIN_LENGTH characters
 * - Contain at least one uppercase letter
 * - Contain at least one lowercase letter
 * - Contain at least one number
 *
 * @param string $password The password to validate
 * @return array ['valid' => bool, 'errors' => array]
 */
function validatePassword(string $password): array
{
    $errors = [];

    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors[] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter.';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain at least one lowercase letter.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number.';
    }

    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

/**
 * Check if an email is already registered
 *
 * @param string $email The email to check
 * @return bool True if email exists, false otherwise
 */
function emailExists(string $email): bool
{
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    return $stmt->fetch() !== false;
}

/**
 * Validate a CSRF token
 *
 * @param string $token The token from the form
 * @return bool True if valid, false otherwise
 */
function validateCSRFToken(string $token): bool
{
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        return false;
    }
    return hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

// ============================================
// RESPONSE FUNCTIONS
// ============================================

/**
 * Send a JSON response and exit
 *
 * This is used by API endpoints to return data in JSON format.
 *
 * EXAMPLE:
 * jsonResponse(true, 'Success!', ['user' => $userData]);
 * jsonResponse(false, 'Error occurred');
 *
 * @param bool $success Whether the request was successful
 * @param string $message A message to return
 * @param array $data Additional data to return
 * @param int $statusCode HTTP status code
 */
function jsonResponse(bool $success, string $message = '', array $data = [], int $statusCode = 200): void
{
    // Set HTTP status code
    http_response_code($statusCode);

    // Set content type to JSON
    header('Content-Type: application/json');

    // Build response array
    $response = [
        'success' => $success,
        'message' => $message
    ];

    // Add data if provided
    if (!empty($data)) {
        $response['data'] = $data;
    }

    // Output JSON and exit
    echo json_encode($response);
    exit;
}

/**
 * Redirect to another page
 *
 * @param string $url The URL to redirect to
 * @param array $params Optional query parameters
 */
function redirect(string $url, array $params = []): void
{
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    header('Location: ' . $url);
    exit;
}

/**
 * Set a flash message to display on the next page
 *
 * Flash messages are stored in session and displayed once.
 *
 * @param string $type The message type (success, error, warning, info)
 * @param string $message The message to display
 */
function setFlashMessage(string $type, string $message): void
{
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Get and clear the flash message
 *
 * @return array|null The flash message or null if none
 */
function getFlashMessage(): ?array
{
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

// ============================================
// SECURITY FUNCTIONS
// ============================================

/**
 * Generate a random token
 *
 * Used for email verification, password reset, etc.
 *
 * @param int $length The length of the token in bytes (final string is double)
 * @return string The random token
 */
function generateToken(int $length = 32): string
{
    return bin2hex(random_bytes($length));
}

/**
 * Generate a numeric verification code
 *
 * @param int $length The number of digits
 * @return string The verification code
 */
function generateVerificationCode(int $length = 6): string
{
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= random_int(0, 9);
    }
    return $code;
}

/**
 * Hash a password securely
 *
 * Uses bcrypt algorithm which is currently recommended.
 *
 * @param string $password The plain-text password
 * @return string The hashed password
 */
function hashPassword(string $password): string
{
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verify a password against a hash
 *
 * @param string $password The plain-text password
 * @param string $hash The stored hash
 * @return bool True if password matches
 */
function verifyPassword(string $password, string $hash): bool
{
    return password_verify($password, $hash);
}

/**
 * Get the client's IP address
 *
 * @return string The IP address
 */
function getClientIP(): string
{
    // Check for forwarded IP (if behind proxy)
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Check if user is currently blocked from login attempts
 *
 * @param string $email The email being checked
 * @return array ['blocked' => bool, 'remaining_time' => int]
 */
function isLoginBlocked(string $email): array
{
    $db = getDB();
    $ip = getClientIP();

    // Calculate the time window
    $blockTime = date('Y-m-d H:i:s', strtotime('-' . LOGIN_BLOCK_DURATION . ' minutes'));

    // Count recent failed attempts
    $stmt = $db->prepare("
        SELECT COUNT(*) as attempts
        FROM login_attempts
        WHERE (email = ? OR ip_address = ?)
        AND success = 0
        AND attempted_at > ?
    ");
    $stmt->execute([$email, $ip, $blockTime]);
    $result = $stmt->fetch();

    $isBlocked = $result['attempts'] >= MAX_LOGIN_ATTEMPTS;

    // Calculate remaining time if blocked
    $remainingTime = 0;
    if ($isBlocked) {
        $stmt = $db->prepare("
            SELECT attempted_at
            FROM login_attempts
            WHERE (email = ? OR ip_address = ?)
            AND success = 0
            ORDER BY attempted_at DESC
            LIMIT 1
        ");
        $stmt->execute([$email, $ip]);
        $lastAttempt = $stmt->fetch();

        if ($lastAttempt) {
            $unblockTime = strtotime($lastAttempt['attempted_at']) + (LOGIN_BLOCK_DURATION * 60);
            $remainingTime = max(0, $unblockTime - time());
        }
    }

    return [
        'blocked' => $isBlocked,
        'remaining_time' => $remainingTime,
        'attempts' => $result['attempts']
    ];
}

/**
 * Record a login attempt
 *
 * @param string $email The email used
 * @param bool $success Whether the attempt was successful
 */
function recordLoginAttempt(string $email, bool $success): void
{
    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO login_attempts (email, ip_address, success)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$email, getClientIP(), $success ? 1 : 0]);
}

/**
 * Clear login attempts after successful login
 *
 * @param string $email The email to clear
 */
function clearLoginAttempts(string $email): void
{
    $db = getDB();
    $stmt = $db->prepare("
        DELETE FROM login_attempts
        WHERE email = ? OR ip_address = ?
    ");
    $stmt->execute([$email, getClientIP()]);
}

// ============================================
// LOGGING FUNCTIONS
// ============================================

/**
 * Log an activity to the system_logs table
 *
 * @param string $actionType The type of action
 * @param string $description Description of what happened
 * @param string|null $entityType The type of entity affected
 * @param int|null $entityId The ID of the entity
 * @param array $extraData Additional data to log
 */
function logActivity(
    string $actionType,
    string $description,
    ?string $entityType = null,
    ?int $entityId = null,
    array $extraData = []
): void {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO system_logs (user_id, action_type, description, entity_type, entity_id, ip_address, user_agent, extra_data)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'] ?? null,
            $actionType,
            $description,
            $entityType,
            $entityId,
            getClientIP(),
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            !empty($extraData) ? json_encode($extraData) : null
        ]);
    } catch (Exception $e) {
        // Don't let logging errors break the application
        error_log('Failed to log activity: ' . $e->getMessage());
    }
}

/**
 * Write to the application log file
 *
 * @param string $level Log level (INFO, WARNING, ERROR)
 * @param string $message The message to log
 * @param array $context Additional context data
 */
function writeLog(string $level, string $message, array $context = []): void
{
    $logFile = LOG_PATH . '/app.log';
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
    $logMessage = "[$timestamp] [$level] $message$contextStr" . PHP_EOL;

    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

// ============================================
// FILE UPLOAD FUNCTIONS
// ============================================

/**
 * Handle file upload for images
 *
 * @param array $file The $_FILES array element
 * @param string $destination The upload directory
 * @param string $prefix Optional filename prefix
 * @return array ['success' => bool, 'filename' => string, 'error' => string]
 */
function uploadImage(array $file, string $destination, string $prefix = ''): array
{
    // Check if file was uploaded
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'File upload failed.'];
    }

    // Check file size
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'error' => 'File is too large. Maximum size is ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB.'];
    }

    // Check file type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    if (!in_array($mimeType, ALLOWED_IMAGE_TYPES)) {
        return ['success' => false, 'error' => 'Invalid file type. Allowed: JPG, PNG, GIF, WebP.'];
    }

    // Check extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_IMAGE_EXTENSIONS)) {
        return ['success' => false, 'error' => 'Invalid file extension.'];
    }

    // Generate unique filename
    $filename = $prefix . uniqid() . '_' . time() . '.' . $extension;
    $filepath = $destination . '/' . $filename;

    // Create directory if it doesn't exist
    if (!is_dir($destination)) {
        mkdir($destination, 0755, true);
    }

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'filename' => $filename];
    }

    return ['success' => false, 'error' => 'Failed to save file.'];
}

/**
 * Delete an uploaded file
 *
 * @param string $filename The filename to delete
 * @param string $directory The directory containing the file
 * @return bool True if deleted, false otherwise
 */
function deleteUploadedFile(string $filename, string $directory): bool
{
    $filepath = $directory . '/' . $filename;
    if (file_exists($filepath) && is_file($filepath)) {
        return unlink($filepath);
    }
    return false;
}

// ============================================
// UTILITY FUNCTIONS
// ============================================

/**
 * Generate a URL-friendly slug from a string
 *
 * @param string $string The string to convert
 * @return string The slug
 */
function generateSlug(string $string): string
{
    // Convert to lowercase
    $slug = strtolower($string);
    // Replace non-alphanumeric characters with hyphens
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    // Remove leading/trailing hyphens
    $slug = trim($slug, '-');
    return $slug;
}

/**
 * Generate a unique order number
 *
 * Format: ORD-YYYYMMDD-XXXXXX
 *
 * @return string The order number
 */
function generateOrderNumber(): string
{
    return ORDER_PREFIX . '-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

/**
 * Format a price for display
 *
 * @param float $price The price to format
 * @param bool $showSymbol Whether to include currency symbol
 * @return string The formatted price
 */
function formatPrice(float $price, bool $showSymbol = true): string
{
    $formatted = number_format($price, 2);
    return $showSymbol ? CURRENCY_SYMBOL . $formatted : $formatted;
}

/**
 * Format a date for display
 *
 * @param string $date The date string
 * @param string $format The desired format
 * @return string The formatted date
 */
function formatDate(string $date, string $format = 'M d, Y'): string
{
    return date($format, strtotime($date));
}

/**
 * Format a datetime for display
 *
 * @param string $datetime The datetime string
 * @param string $format The desired format
 * @return string The formatted datetime
 */
function formatDateTime(string $datetime, string $format = 'M d, Y H:i'): string
{
    return date($format, strtotime($datetime));
}

/**
 * Get current user data from database
 *
 * @return array|null User data or null if not logged in
 */
function getCurrentUser(): ?array
{
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    $db = getDB();
    $stmt = $db->prepare("
        SELECT id, first_name, last_name, email, phone, profile_image,
               address, city, postal_code, country, role, email_verified,
               status, created_at, last_login
        FROM users
        WHERE id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

/**
 * Calculate cart totals
 *
 * @param array $cartItems Array of cart items with product data
 * @return array ['subtotal' => float, 'tax' => float, 'shipping' => float, 'total' => float]
 */
function calculateCartTotals(array $cartItems): array
{
    $subtotal = 0;

    foreach ($cartItems as $item) {
        $price = $item['sale_price'] ?? $item['price'];
        $subtotal += $price * $item['quantity'];
    }

    $tax = $subtotal * TAX_RATE;
    $shipping = $subtotal >= FREE_SHIPPING_THRESHOLD ? 0 : DEFAULT_SHIPPING_COST;
    $total = $subtotal + $tax + $shipping;

    return [
        'subtotal' => round($subtotal, 2),
        'tax' => round($tax, 2),
        'shipping' => round($shipping, 2),
        'total' => round($total, 2)
    ];
}

/**
 * Truncate text to a specific length
 *
 * @param string $text The text to truncate
 * @param int $length Maximum length
 * @param string $suffix Suffix to add if truncated
 * @return string The truncated text
 */
function truncateText(string $text, int $length = 100, string $suffix = '...'): string
{
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . $suffix;
}

/**
 * Check if request is AJAX
 *
 * @return bool True if AJAX request
 */
function isAjaxRequest(): bool
{
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Get pagination data
 *
 * @param int $totalItems Total number of items
 * @param int $currentPage Current page number
 * @param int $perPage Items per page
 * @return array Pagination data
 */
function getPagination(int $totalItems, int $currentPage = 1, int $perPage = ITEMS_PER_PAGE): array
{
    $totalPages = ceil($totalItems / $perPage);
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $perPage;

    return [
        'current_page' => $currentPage,
        'per_page' => $perPage,
        'total_items' => $totalItems,
        'total_pages' => $totalPages,
        'offset' => $offset,
        'has_previous' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages
    ];
}
