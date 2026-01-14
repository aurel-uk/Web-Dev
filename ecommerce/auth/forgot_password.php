<?php
/**
 * ============================================
 * FORGOT PASSWORD PAGE
 * ============================================
 *
 * This page allows users to request a password reset.
 * A reset token is generated and stored in the database.
 *
 * FLOW:
 * 1. User enters their email
 * 2. System checks if email exists
 * 3. Generates a reset token
 * 4. In production: sends email with reset link
 * 5. For demo: displays token on screen
 *
 * SECURITY:
 * - Token expires after 1 hour
 * - Token is hashed before storing
 * - Same message shown whether email exists or not (prevents enumeration)
 * ============================================
 */

// Load application initialization
require_once __DIR__ . '/../config/init.php';

// Redirect if already logged in
if ($isLoggedIn) {
    redirect(BASE_URL . '/index.php');
}

// Initialize variables
$errors = [];
$success = false;
$email = '';

// For demo: store reset token
$demoToken = null;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission. Please try again.';
    } else {
        $email = sanitizeEmail($_POST['email'] ?? '');

        if (empty($email)) {
            $errors[] = 'Please enter your email address.';
        } elseif (!isValidEmail($email)) {
            $errors[] = 'Please enter a valid email address.';
        } else {
            // Check if email exists
            $db = getDB();
            $stmt = $db->prepare("SELECT id, first_name FROM users WHERE email = ? AND status = 'active'");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            // Always show success message (prevent email enumeration)
            $success = true;

            if ($user) {
                try {
                    // Generate reset token
                    $token = generateToken(32);
                    $hashedToken = hash('sha256', $token);
                    $expiresAt = date('Y-m-d H:i:s', strtotime('+' . PASSWORD_RESET_EXPIRY . ' hour'));

                    // Delete any existing reset tokens for this email
                    $deleteStmt = $db->prepare("DELETE FROM password_resets WHERE email = ?");
                    $deleteStmt->execute([$email]);

                    // Insert new reset token
                    $insertStmt = $db->prepare("
                        INSERT INTO password_resets (email, token, expires_at)
                        VALUES (?, ?, ?)
                    ");
                    $insertStmt->execute([$email, $hashedToken, $expiresAt]);

                    // Log the password reset request
                    logActivity('password_reset_request', 'Password reset requested', 'user', $user['id']);

                    // In production, send email here
                    // mail($email, 'Password Reset', 'Click here to reset: ...');

                    // For demo: store token in session
                    $demoToken = $token;
                    $_SESSION['demo_reset_email'] = $email;

                } catch (Exception $e) {
                    writeLog('ERROR', 'Password reset failed: ' . $e->getMessage());
                    // Still show success to prevent enumeration
                }
            }
        }
    }
}

// Set page title
$pageTitle = 'Forgot Password';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-sm my-5">
                <div class="card-body p-4 p-md-5">
                    <?php if ($success): ?>
                        <!-- Success State -->
                        <div class="text-center mb-4">
                            <div class="mb-3">
                                <i class="bi bi-envelope-check text-success" style="font-size: 3rem;"></i>
                            </div>
                            <h3 class="fw-bold">Check Your Email</h3>
                            <p class="text-muted">
                                If an account exists with <strong><?= sanitize($email) ?></strong>,
                                you will receive a password reset link shortly.
                            </p>
                        </div>

                        <!-- Demo: Show reset link (Remove in production) -->
                        <?php if ($demoToken): ?>
                            <div class="alert alert-info">
                                <strong>Demo Mode:</strong> Click the link below to reset your password:<br>
                                <a href="<?= BASE_URL ?>/auth/reset_password.php?token=<?= $demoToken ?>">
                                    Reset Password Link
                                </a>
                            </div>
                        <?php endif; ?>

                        <div class="text-center">
                            <p class="text-muted small mb-3">
                                The link will expire in <?= PASSWORD_RESET_EXPIRY ?> hour(s).
                            </p>
                            <a href="<?= BASE_URL ?>/auth/login.php" class="btn btn-outline-primary">
                                <i class="bi bi-arrow-left"></i> Back to Login
                            </a>
                        </div>
                    <?php else: ?>
                        <!-- Request Form -->
                        <div class="text-center mb-4">
                            <div class="mb-3">
                                <i class="bi bi-key text-primary" style="font-size: 3rem;"></i>
                            </div>
                            <h3 class="fw-bold">Forgot Password?</h3>
                            <p class="text-muted">
                                Enter your email address and we'll send you a link to reset your password.
                            </p>
                        </div>

                        <!-- Error Messages -->
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= sanitize($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                            <div class="mb-4">
                                <label for="email" class="form-label">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-envelope"></i>
                                    </span>
                                    <input type="email"
                                           class="form-control"
                                           id="email"
                                           name="email"
                                           value="<?= sanitize($email) ?>"
                                           placeholder="Enter your email"
                                           required
                                           autofocus>
                                </div>
                            </div>

                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-envelope"></i> Send Reset Link
                                </button>
                            </div>

                            <div class="text-center">
                                <a href="<?= BASE_URL ?>/auth/login.php" class="text-decoration-none">
                                    <i class="bi bi-arrow-left"></i> Back to Login
                                </a>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
