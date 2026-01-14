<?php
/**
 * ============================================
 * APPLICATION INITIALIZATION
 * ============================================
 *
 * This file initializes the application by:
 * 1. Loading all configuration files
 * 2. Starting the session
 * 3. Setting up error handling
 * 4. Loading helper functions
 *
 * USAGE:
 * Include this file at the top of every PHP page:
 * require_once __DIR__ . '/../config/init.php';
 *
 * Or for pages in public folder:
 * require_once __DIR__ . '/../config/init.php';
 * ============================================
 */

// ============================================
// LOAD CONFIGURATION FILES
// ============================================

// Load application constants first (other files may depend on them)
require_once __DIR__ . '/constants.php';

// Load database configuration
require_once __DIR__ . '/database.php';

// ============================================
// ERROR HANDLING SETUP
// ============================================

// Set the default timezone
date_default_timezone_set(TIMEZONE);

// Configure error reporting based on debug mode
if (DEBUG_MODE) {
    // In development: show all errors
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    // In production: log errors but don't display them
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', LOG_PATH . '/error.log');
}

// ============================================
// SESSION CONFIGURATION
// ============================================

/**
 * Configure session settings for security
 *
 * These settings help prevent session hijacking and fixation attacks.
 */

// Only allow session cookies (not URL parameters)
ini_set('session.use_only_cookies', '1');

// Prevent JavaScript access to session cookie (helps prevent XSS)
ini_set('session.cookie_httponly', '1');

// Use secure cookies if on HTTPS
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', '1');
}

// Set SameSite attribute to prevent CSRF
ini_set('session.cookie_samesite', 'Lax');

// Set session cookie lifetime (0 = until browser closes)
ini_set('session.cookie_lifetime', '0');

// Set session name (change from default 'PHPSESSID' for security)
session_name('ECOMMERCE_SESSION');

// Start the session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// SESSION TIMEOUT CHECK
// ============================================

/**
 * Check if the session has timed out due to inactivity.
 * If so, destroy the session and redirect to login.
 */

// Check if user is logged in and has a last activity timestamp
if (isset($_SESSION['user_id']) && isset($_SESSION['last_activity'])) {
    // Calculate time since last activity
    $inactive_time = time() - $_SESSION['last_activity'];

    // If inactive for too long, log out the user
    if ($inactive_time > SESSION_TIMEOUT) {
        // Store message before destroying session
        $timeout_message = 'Your session has expired due to inactivity. Please log in again.';

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

        // Start a new session for the flash message
        session_start();
        $_SESSION['flash_message'] = [
            'type' => 'warning',
            'message' => $timeout_message
        ];

        // Redirect to login page
        // Note: We only redirect if this is not an API request
        if (!isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Location: ' . BASE_URL . '/auth/login.php');
            exit;
        }
    }
}

// Update last activity timestamp
$_SESSION['last_activity'] = time();

// ============================================
// REGENERATE SESSION ID PERIODICALLY
// ============================================

/**
 * Regenerate session ID every 30 minutes to prevent session fixation.
 */
if (!isset($_SESSION['created'])) {
    $_SESSION['created'] = time();
} elseif (time() - $_SESSION['created'] > 1800) {
    // Session started more than 30 minutes ago
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}

// ============================================
// CSRF TOKEN GENERATION
// ============================================

/**
 * Generate a CSRF token if one doesn't exist.
 *
 * WHAT IS CSRF?
 * Cross-Site Request Forgery is an attack where a malicious site
 * tricks users into performing actions on your site.
 *
 * The CSRF token prevents this by requiring a secret token
 * that only your site knows.
 */
if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
    $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
}

// ============================================
// LOAD HELPER FUNCTIONS
// ============================================

require_once dirname(__DIR__) . '/includes/functions.php';

// ============================================
// CHECK REMEMBER ME COOKIE
// ============================================

/**
 * If user is not logged in but has a "remember me" cookie,
 * try to log them in automatically.
 */
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_me'])) {
    // The cookie contains: selector:validator
    $parts = explode(':', $_COOKIE['remember_me']);

    if (count($parts) === 2) {
        $selector = $parts[0];
        $validator = $parts[1];

        // Look up the token in the database
        $db = getDB();
        $stmt = $db->prepare("
            SELECT rt.*, u.id as user_id, u.email, u.role, u.status,
                   u.first_name, u.last_name
            FROM remember_tokens rt
            JOIN users u ON rt.user_id = u.id
            WHERE rt.selector = ? AND rt.expires_at > NOW()
        ");
        $stmt->execute([$selector]);
        $token = $stmt->fetch();

        if ($token && hash_equals($token['hashed_validator'], hash('sha256', $validator))) {
            // Token is valid, log the user in
            if ($token['status'] === STATUS_ACTIVE) {
                $_SESSION['user_id'] = $token['user_id'];
                $_SESSION['user_email'] = $token['email'];
                $_SESSION['user_role'] = $token['role'];
                $_SESSION['user_name'] = $token['first_name'] . ' ' . $token['last_name'];

                // Update last login time
                $updateStmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $updateStmt->execute([$token['user_id']]);

                // Log the automatic login
                logActivity('auto_login', 'User logged in via remember me token', 'user', $token['user_id']);
            }
        } else {
            // Invalid token, clear the cookie
            setcookie('remember_me', '', time() - 3600, '/', '', false, true);
        }
    }
}

// ============================================
// UTILITY VARIABLES
// ============================================

/**
 * These variables are available in all pages after including init.php
 */

// Current user info (null if not logged in)
$currentUser = null;
if (isset($_SESSION['user_id'])) {
    $currentUser = [
        'id' => $_SESSION['user_id'],
        'email' => $_SESSION['user_email'] ?? '',
        'role' => $_SESSION['user_role'] ?? ROLE_USER,
        'name' => $_SESSION['user_name'] ?? ''
    ];
}

// Is user logged in?
$isLoggedIn = isset($_SESSION['user_id']);

// Is user an admin?
$isAdmin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === ROLE_ADMIN;

// CSRF token for forms
$csrfToken = $_SESSION[CSRF_TOKEN_NAME];
