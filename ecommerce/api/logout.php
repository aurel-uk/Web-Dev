<?php
/**
 * ============================================
 * LOGOUT API ENDPOINT
 * ============================================
 *
 * Handles user logout via AJAX.
 * Clears session and remember me tokens.
 *
 * ============================================
 */

require_once __DIR__ . '/../config/init.php';

header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed', [], 405);
}

$userId = $_SESSION['user_id'] ?? null;

// Log the logout if user was logged in
if ($userId) {
    logActivity('logout', 'User logged out via API', 'user', $userId);

    // Clear remember me token
    if (isset($_COOKIE['remember_me'])) {
        $parts = explode(':', $_COOKIE['remember_me']);
        if (count($parts) === 2) {
            try {
                $db = getDB();
                $stmt = $db->prepare("DELETE FROM remember_tokens WHERE selector = ?");
                $stmt->execute([$parts[0]]);
            } catch (Exception $e) {
                // Silently fail
            }
        }

        // Clear cookie
        setcookie('remember_me', '', time() - 3600, '/', '', false, true);
    }
}

// Clear session
$_SESSION = [];

// Destroy session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
}

// Destroy session
session_destroy();

jsonResponse(true, SUCCESS_LOGOUT, [
    'redirect' => BASE_URL . '/index.php'
]);
