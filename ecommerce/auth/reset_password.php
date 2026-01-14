<?php
/**
 * ============================================
 * RESET PASSWORD PAGE
 * ============================================
 *
 * This page allows users to set a new password
 * using a valid reset token from the forgot password email.
 *
 * FLOW:
 * 1. User clicks reset link with token
 * 2. System validates the token
 * 3. User enters new password
 * 4. Password is updated
 * 5. Token is deleted
 * 6. User can log in with new password
 *
 * SECURITY:
 * - Token is hashed before comparison
 * - Token expires after 1 hour
 * - Token is deleted after use
 * - Password validation same as registration
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
$validToken = false;
$token = $_GET['token'] ?? '';
$email = '';

// Validate token
if (!empty($token)) {
    $hashedToken = hash('sha256', $token);

    $db = getDB();
    $stmt = $db->prepare("
        SELECT email, expires_at
        FROM password_resets
        WHERE token = ?
    ");
    $stmt->execute([$hashedToken]);
    $reset = $stmt->fetch();

    if ($reset) {
        if (strtotime($reset['expires_at']) > time()) {
            $validToken = true;
            $email = $reset['email'];
        } else {
            $errors[] = 'This reset link has expired. Please request a new one.';
        }
    } else {
        $errors[] = 'Invalid or expired reset link. Please request a new one.';
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    // Verify CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission. Please try again.';
    } else {
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Validate password
        $passwordValidation = validatePassword($password);
        if (!$passwordValidation['valid']) {
            $errors = array_merge($errors, $passwordValidation['errors']);
        }

        // Check password match
        if ($password !== $confirmPassword) {
            $errors[] = ERROR_PASSWORD_MISMATCH;
        }

        if (empty($errors)) {
            try {
                $db = getDB();
                $db->beginTransaction();

                // Hash new password
                $hashedPassword = hashPassword($password);

                // Update user password
                $updateStmt = $db->prepare("UPDATE users SET password = ? WHERE email = ?");
                $updateStmt->execute([$hashedPassword, $email]);

                // Delete the reset token
                $deleteStmt = $db->prepare("DELETE FROM password_resets WHERE email = ?");
                $deleteStmt->execute([$email]);

                // Also delete any remember me tokens for security
                $userStmt = $db->prepare("SELECT id FROM users WHERE email = ?");
                $userStmt->execute([$email]);
                $user = $userStmt->fetch();

                if ($user) {
                    $tokenStmt = $db->prepare("DELETE FROM remember_tokens WHERE user_id = ?");
                    $tokenStmt->execute([$user['id']]);

                    // Log the password change
                    logActivity('password_reset_complete', 'Password was reset', 'user', $user['id']);
                }

                $db->commit();

                $success = true;
                setFlashMessage('success', SUCCESS_PASSWORD_CHANGED);

            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                writeLog('ERROR', 'Password reset failed: ' . $e->getMessage());
                $errors[] = 'Failed to reset password. Please try again.';
            }
        }
    }
}

// Set page title
$pageTitle = 'Reset Password';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-sm my-5">
                <div class="card-body p-4 p-md-5">
                    <?php if ($success): ?>
                        <!-- Success State -->
                        <div class="text-center">
                            <div class="mb-4">
                                <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                            </div>
                            <h3 class="fw-bold text-success">Password Reset!</h3>
                            <p class="text-muted mb-4">
                                Your password has been successfully reset.
                                You can now log in with your new password.
                            </p>
                            <a href="<?= BASE_URL ?>/auth/login.php" class="btn btn-primary btn-lg">
                                <i class="bi bi-box-arrow-in-right"></i> Log In Now
                            </a>
                        </div>
                    <?php elseif (!$validToken): ?>
                        <!-- Invalid Token State -->
                        <div class="text-center">
                            <div class="mb-4">
                                <i class="bi bi-exclamation-triangle-fill text-danger" style="font-size: 4rem;"></i>
                            </div>
                            <h3 class="fw-bold text-danger">Invalid Link</h3>

                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger text-start">
                                    <ul class="mb-0">
                                        <?php foreach ($errors as $error): ?>
                                            <li><?= sanitize($error) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <p class="text-muted mb-4">
                                Please request a new password reset link.
                            </p>
                            <div class="d-grid gap-2">
                                <a href="<?= BASE_URL ?>/auth/forgot_password.php" class="btn btn-primary">
                                    <i class="bi bi-key"></i> Request New Link
                                </a>
                                <a href="<?= BASE_URL ?>/auth/login.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left"></i> Back to Login
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Reset Form -->
                        <div class="text-center mb-4">
                            <div class="mb-3">
                                <i class="bi bi-shield-lock text-primary" style="font-size: 3rem;"></i>
                            </div>
                            <h3 class="fw-bold">Set New Password</h3>
                            <p class="text-muted">
                                Create a new password for<br>
                                <strong><?= sanitize($email) ?></strong>
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

                        <form method="POST" action="" data-validate-form>
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                            <!-- New Password -->
                            <div class="mb-3">
                                <label for="password" class="form-label">New Password</label>
                                <div class="input-group">
                                    <input type="password"
                                           class="form-control"
                                           id="password"
                                           name="password"
                                           placeholder="Enter new password"
                                           data-validate="password"
                                           required>
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <div class="password-strength"></div>
                                <div class="form-text">
                                    Must be at least 8 characters with uppercase, lowercase, and a number.
                                </div>
                            </div>

                            <!-- Confirm Password -->
                            <div class="mb-4">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password"
                                       class="form-control"
                                       id="confirm_password"
                                       name="confirm_password"
                                       placeholder="Confirm new password"
                                       data-validate="confirm-password"
                                       required>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-check-lg"></i> Reset Password
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Toggle Password Visibility Script -->
<script>
document.getElementById('togglePassword')?.addEventListener('click', function() {
    const passwordInput = document.getElementById('password');
    const icon = this.querySelector('i');

    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
    } else {
        passwordInput.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
