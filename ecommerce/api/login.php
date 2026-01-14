<?php
/**
 * ============================================
 * LOGIN API ENDPOINT
 * ============================================
 *
 * Handles user login via AJAX.
 * Returns JSON response with success/failure status.
 *
 * POST Parameters:
 * - email: User's email address
 * - password: User's password
 * - remember_me: (optional) 1 to enable remember me
 *
 * ============================================
 */

require_once __DIR__ . '/../config/init.php';

header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed', [], 405);
}

// Get input data
$email = sanitizeEmail($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$rememberMe = isset($_POST['remember_me']) && $_POST['remember_me'] == '1';

// Validate input
if (empty($email) || empty($password)) {
    jsonResponse(false, 'Email and password are required', [], 400);
}

// Check if blocked
$blockStatus = isLoginBlocked($email);
if ($blockStatus['blocked']) {
    $remainingMinutes = ceil($blockStatus['remaining_time'] / 60);
    jsonResponse(false, "Too many failed attempts. Try again in {$remainingMinutes} minute(s).", [], 429);
}

// Get user from database
$db = getDB();
$stmt = $db->prepare("
    SELECT id, first_name, last_name, email, password, role, email_verified, status
    FROM users
    WHERE email = ?
");
$stmt->execute([$email]);
$user = $stmt->fetch();

// Verify credentials
if (!$user || !verifyPassword($password, $user['password'])) {
    recordLoginAttempt($email, false);

    // Check remaining attempts
    $newStatus = isLoginBlocked($email);
    $attemptsLeft = MAX_LOGIN_ATTEMPTS - $newStatus['attempts'];

    $message = ERROR_INVALID_CREDENTIALS;
    if ($attemptsLeft <= 3 && $attemptsLeft > 0) {
        $message .= " {$attemptsLeft} attempt(s) remaining.";
    }

    jsonResponse(false, $message, [], 401);
}

// Check account status
if ($user['status'] === STATUS_BLOCKED) {
    recordLoginAttempt($email, false);
    jsonResponse(false, ERROR_ACCOUNT_BLOCKED, [], 403);
}

// Successful login
clearLoginAttempts($email);
recordLoginAttempt($email, true);

// Regenerate session
session_regenerate_id(true);

// Set session variables
$_SESSION['user_id'] = $user['id'];
$_SESSION['user_email'] = $user['email'];
$_SESSION['user_role'] = $user['role'];
$_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
$_SESSION['last_activity'] = time();
$_SESSION['created'] = time();

// Update last login
$updateStmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
$updateStmt->execute([$user['id']]);

// Handle remember me
if ($rememberMe) {
    $selector = bin2hex(random_bytes(16));
    $validator = bin2hex(random_bytes(32));
    $hashedValidator = hash('sha256', $validator);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+' . REMEMBER_ME_DAYS . ' days'));

    $tokenStmt = $db->prepare("
        INSERT INTO remember_tokens (user_id, selector, hashed_validator, expires_at)
        VALUES (?, ?, ?, ?)
    ");
    $tokenStmt->execute([$user['id'], $selector, $hashedValidator, $expiresAt]);

    setcookie('remember_me', $selector . ':' . $validator,
              time() + (REMEMBER_ME_DAYS * 24 * 60 * 60), '/', '', false, true);
}

// Log the login
logActivity('login', 'User logged in via API', 'user', $user['id']);

// Return success with user data
jsonResponse(true, SUCCESS_LOGIN, [
    'user' => [
        'id' => $user['id'],
        'name' => $user['first_name'] . ' ' . $user['last_name'],
        'email' => $user['email'],
        'role' => $user['role']
    ],
    'redirect' => $user['role'] === ROLE_ADMIN ? BASE_URL . '/admin/' : BASE_URL . '/index.php'
]);
