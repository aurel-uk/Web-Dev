<?php
/**
 * ============================================
 * ADMIN ACCESS CHECK
 * ============================================
 *
 * Include this file at the top of any page that requires
 * ADMIN privileges.
 *
 * USAGE:
 * require_once __DIR__ . '/../includes/admin_check.php';
 *
 * WHAT IT DOES:
 * 1. First checks if user is logged in (includes auth_check.php)
 * 2. Then checks if user has admin role
 * 3. Redirects to homepage if not an admin
 *
 * ============================================
 */

// First, do the regular authentication check
require_once __DIR__ . '/auth_check.php';

/**
 * Check if user has admin role
 *
 * The role is stored in session during login
 */
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== ROLE_ADMIN) {
    // Log the unauthorized access attempt
    logActivity(
        'unauthorized_access',
        'Non-admin user attempted to access admin area',
        'user',
        $_SESSION['user_id'],
        ['attempted_url' => $_SERVER['REQUEST_URI']]
    );

    // Set flash message
    setFlashMessage('error', 'You do not have permission to access the admin area.');

    // Check if this is an AJAX request
    if (isAjaxRequest()) {
        jsonResponse(false, 'Admin access required', [], 403);
    }

    // Redirect to homepage
    redirect(BASE_URL . '/index.php');
}

/**
 * Double-check admin status from database
 * This handles cases where admin status was revoked after logging in
 */
$db = getDB();
$stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user || $user['role'] !== ROLE_ADMIN) {
    // Update session to reflect actual role
    $_SESSION['user_role'] = $user['role'] ?? ROLE_USER;

    // Log the inconsistency
    logActivity(
        'role_mismatch',
        'User session role did not match database',
        'user',
        $_SESSION['user_id']
    );

    setFlashMessage('error', 'Your admin access has been revoked.');

    if (isAjaxRequest()) {
        jsonResponse(false, 'Admin access revoked', [], 403);
    }

    redirect(BASE_URL . '/index.php');
}

// At this point, the user is confirmed to be an admin
// The admin page can continue loading
