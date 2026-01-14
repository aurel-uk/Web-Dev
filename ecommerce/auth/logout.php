<?php
/**
 * ============================================
 * USER LOGOUT
 * ============================================
 *
 * This page handles user logout.
 * It performs the following actions:
 * 1. Logs the logout activity
 * 2. Clears remember me token from database
 * 3. Clears remember me cookie
 * 4. Destroys the session
 * 5. Redirects to homepage
 *
 * SECURITY:
 * - Session is completely destroyed
 * - Remember me tokens are removed
 * - Session cookie is cleared
 * ============================================
 */

// Load application initialization
require_once __DIR__ . '/../config/init.php';

// Only process if user is logged in
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];

    // Log the logout activity
    logActivity('logout', 'User logged out', 'user', $userId);

    // Clear remember me token from database
    if (isset($_COOKIE['remember_me'])) {
        $parts = explode(':', $_COOKIE['remember_me']);
        if (count($parts) === 2) {
            $selector = $parts[0];

            try {
                $db = getDB();
                $stmt = $db->prepare("DELETE FROM remember_tokens WHERE selector = ?");
                $stmt->execute([$selector]);
            } catch (Exception $e) {
                // Log but don't fail if token deletion fails
                writeLog('WARNING', 'Failed to delete remember token: ' . $e->getMessage());
            }
        }

        // Clear the cookie
        setcookie(
            'remember_me',
            '',
            time() - 3600,
            '/',
            '',
            false,
            true
        );
    }
}

// Clear all session data
$_SESSION = [];

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Start a new session for flash message
session_start();
setFlashMessage('success', SUCCESS_LOGOUT);

// Redirect to homepage
redirect(BASE_URL . '/index.php');
