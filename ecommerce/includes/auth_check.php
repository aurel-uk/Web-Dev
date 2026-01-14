<?php
/**
 * ============================================
 * AUTHENTICATION CHECK
 * ============================================
 *
 * Include this file at the top of any page that requires
 * a logged-in user (customer OR admin).
 *
 * USAGE:
 * require_once __DIR__ . '/../includes/auth_check.php';
 *
 * WHAT IT DOES:
 * 1. Checks if user is logged in
 * 2. Checks if user's account is active
 * 3. Redirects to login page if not authenticated
 *
 * For admin-only pages, use admin_check.php instead.
 * ============================================
 */

// Make sure init.php is loaded
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config/init.php';
}

/**
 * Check if user is logged in
 */
if (!isset($_SESSION['user_id'])) {
    // Store the requested URL so we can redirect back after login
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];

    // Set flash message
    setFlashMessage('warning', 'Please log in to access this page.');

    // Check if this is an AJAX request
    if (isAjaxRequest()) {
        jsonResponse(false, 'Authentication required', [], 401);
    }

    // Redirect to login page
    redirect(BASE_URL . '/auth/login.php');
}

/**
 * Verify the user still exists and is active
 * This handles cases where the user was deleted or blocked after logging in
 */
$db = getDB();
$stmt = $db->prepare("SELECT id, status, email_verified FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// User no longer exists
if (!$user) {
    // Clear session
    session_destroy();

    // Start new session for flash message
    session_start();
    setFlashMessage('error', 'Your account no longer exists.');

    if (isAjaxRequest()) {
        jsonResponse(false, 'Account not found', [], 401);
    }

    redirect(BASE_URL . '/auth/login.php');
}

// User is blocked
if ($user['status'] === STATUS_BLOCKED) {
    // Clear session
    session_destroy();

    // Start new session for flash message
    session_start();
    setFlashMessage('error', 'Your account has been suspended. Please contact support.');

    if (isAjaxRequest()) {
        jsonResponse(false, 'Account suspended', [], 403);
    }

    redirect(BASE_URL . '/auth/login.php');
}

// User hasn't verified email (optional - uncomment to enforce)
/*
if (!$user['email_verified']) {
    setFlashMessage('warning', 'Please verify your email address to access this page.');

    if (isAjaxRequest()) {
        jsonResponse(false, 'Email not verified', [], 403);
    }

    redirect(BASE_URL . '/auth/verify_email.php');
}
*/

// At this point, the user is authenticated and active
// The page can continue loading
