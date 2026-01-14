<?php
/**
 * ============================================
 * REGISTRATION API ENDPOINT
 * ============================================
 *
 * Handles user registration via AJAX.
 *
 * POST Parameters:
 * - first_name: User's first name
 * - last_name: User's last name
 * - email: User's email address
 * - password: User's password
 * - phone: (optional) Phone number
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
$firstName = sanitize($_POST['first_name'] ?? '');
$lastName = sanitize($_POST['last_name'] ?? '');
$email = sanitizeEmail($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$phone = sanitize($_POST['phone'] ?? '');

// Validation
$errors = [];

if (empty($firstName) || strlen($firstName) < 2) {
    $errors[] = 'First name must be at least 2 characters.';
}

if (empty($lastName) || strlen($lastName) < 2) {
    $errors[] = 'Last name must be at least 2 characters.';
}

if (empty($email) || !isValidEmail($email)) {
    $errors[] = ERROR_INVALID_EMAIL;
}

if (emailExists($email)) {
    $errors[] = ERROR_EMAIL_EXISTS;
}

$passwordValidation = validatePassword($password);
if (!$passwordValidation['valid']) {
    $errors = array_merge($errors, $passwordValidation['errors']);
}

if (!empty($errors)) {
    jsonResponse(false, 'Validation failed', ['errors' => $errors], 400);
}

// Create user
try {
    $db = getDB();
    $db->beginTransaction();

    // Hash password
    $hashedPassword = hashPassword($password);

    // Insert user
    $stmt = $db->prepare("
        INSERT INTO users (first_name, last_name, email, password, phone, role, email_verified, status)
        VALUES (?, ?, ?, ?, ?, 'user', 0, 'active')
    ");
    $stmt->execute([$firstName, $lastName, $email, $hashedPassword, $phone ?: null]);
    $userId = $db->lastInsertId();

    // Generate verification code
    $verificationCode = generateVerificationCode();
    $expiresAt = date('Y-m-d H:i:s', strtotime('+' . VERIFICATION_CODE_EXPIRY . ' hours'));

    $verifyStmt = $db->prepare("
        INSERT INTO email_verifications (user_id, verification_code, expires_at)
        VALUES (?, ?, ?)
    ");
    $verifyStmt->execute([$userId, $verificationCode, $expiresAt]);

    $db->commit();

    // Store for verification page
    $_SESSION['pending_verification_user_id'] = $userId;
    $_SESSION['pending_verification_email'] = $email;
    $_SESSION['demo_verification_code'] = $verificationCode;

    // Log registration
    logActivity('user_register', 'New user registered via API', 'user', $userId);

    jsonResponse(true, SUCCESS_REGISTER, [
        'user_id' => $userId,
        'email' => $email,
        'verification_code' => $verificationCode, // For demo only - remove in production
        'redirect' => BASE_URL . '/auth/verify_email.php'
    ]);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    writeLog('ERROR', 'Registration API failed: ' . $e->getMessage());
    jsonResponse(false, 'Registration failed. Please try again.', [], 500);
}
