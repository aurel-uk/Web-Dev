<?php
/**
 * ============================================
 * EMAIL VERIFICATION PAGE
 * ============================================
 *
 * This page allows users to verify their email address
 * using the code sent to their email.
 *
 * FLOW:
 * 1. User receives verification code (in email or displayed for demo)
 * 2. User enters the 6-digit code
 * 3. System verifies the code
 * 4. User is marked as verified
 * 5. User can now log in
 *
 * NOTE: In a real application, you would send the code via email.
 * For this demo, the code is displayed on screen.
 * ============================================
 */

// Load application initialization
require_once __DIR__ . '/../config/init.php';

// Check if we have a pending verification
$userId = $_SESSION['pending_verification_user_id'] ?? null;
$email = $_SESSION['pending_verification_email'] ?? null;

// For demo purposes, get the code from session
$demoCode = $_SESSION['demo_verification_code'] ?? null;

// Initialize variables
$errors = [];
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission. Please try again.';
    } else {
        $enteredCode = sanitize($_POST['verification_code'] ?? '');

        if (empty($enteredCode)) {
            $errors[] = 'Please enter the verification code.';
        } elseif (!$userId) {
            $errors[] = 'No pending verification found. Please register again.';
        } else {
            // Check the verification code in database
            $db = getDB();
            $stmt = $db->prepare("
                SELECT id, verification_code, expires_at, used
                FROM email_verifications
                WHERE user_id = ?
                AND verification_code = ?
                AND used = 0
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$userId, $enteredCode]);
            $verification = $stmt->fetch();

            if (!$verification) {
                $errors[] = 'Invalid verification code. Please try again.';
            } elseif (strtotime($verification['expires_at']) < time()) {
                $errors[] = 'This verification code has expired. Please request a new one.';
            } else {
                // Code is valid - update user as verified
                try {
                    $db->beginTransaction();

                    // Mark user as verified
                    $updateUser = $db->prepare("UPDATE users SET email_verified = 1 WHERE id = ?");
                    $updateUser->execute([$userId]);

                    // Mark verification code as used
                    $updateCode = $db->prepare("UPDATE email_verifications SET used = 1 WHERE id = ?");
                    $updateCode->execute([$verification['id']]);

                    $db->commit();

                    // Log the verification
                    logActivity('email_verified', 'User verified email address', 'user', $userId);

                    // Clear session variables
                    unset($_SESSION['pending_verification_user_id']);
                    unset($_SESSION['pending_verification_email']);
                    unset($_SESSION['demo_verification_code']);

                    // Set success flag
                    $success = true;

                    setFlashMessage('success', 'Your email has been verified! You can now log in.');

                } catch (Exception $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    writeLog('ERROR', 'Email verification failed: ' . $e->getMessage());
                    $errors[] = 'Verification failed. Please try again.';
                }
            }
        }
    }
}

// Handle resend request
if (isset($_GET['resend']) && $userId) {
    try {
        $db = getDB();

        // Generate new verification code
        $newCode = generateVerificationCode();
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . VERIFICATION_CODE_EXPIRY . ' hours'));

        // Insert new verification record
        $stmt = $db->prepare("
            INSERT INTO email_verifications (user_id, verification_code, expires_at)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$userId, $newCode, $expiresAt]);

        // Update demo code in session
        $_SESSION['demo_verification_code'] = $newCode;
        $demoCode = $newCode;

        setFlashMessage('success', 'A new verification code has been sent!');

    } catch (Exception $e) {
        writeLog('ERROR', 'Resend verification failed: ' . $e->getMessage());
        setFlashMessage('error', 'Failed to send new code. Please try again.');
    }

    redirect(BASE_URL . '/auth/verify_email.php');
}

// Set page title
$pageTitle = 'Verify Email';
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
                            <h3 class="fw-bold text-success">Email Verified!</h3>
                            <p class="text-muted mb-4">
                                Your email address has been successfully verified.
                                You can now log in to your account.
                            </p>
                            <a href="<?= BASE_URL ?>/auth/login.php" class="btn btn-primary btn-lg">
                                <i class="bi bi-box-arrow-in-right"></i> Log In Now
                            </a>
                        </div>
                    <?php elseif (!$userId): ?>
                        <!-- No Pending Verification -->
                        <div class="text-center">
                            <div class="mb-4">
                                <i class="bi bi-exclamation-triangle-fill text-warning" style="font-size: 4rem;"></i>
                            </div>
                            <h3 class="fw-bold">No Pending Verification</h3>
                            <p class="text-muted mb-4">
                                There is no pending email verification. If you need to verify your email,
                                please register or log in to request a new verification code.
                            </p>
                            <div class="d-grid gap-2">
                                <a href="<?= BASE_URL ?>/auth/register.php" class="btn btn-primary">
                                    <i class="bi bi-person-plus"></i> Register
                                </a>
                                <a href="<?= BASE_URL ?>/auth/login.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-box-arrow-in-right"></i> Log In
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Verification Form -->
                        <div class="text-center mb-4">
                            <div class="mb-3">
                                <i class="bi bi-envelope-check text-primary" style="font-size: 3rem;"></i>
                            </div>
                            <h3 class="fw-bold">Verify Your Email</h3>
                            <p class="text-muted">
                                We've sent a verification code to<br>
                                <strong><?= sanitize($email) ?></strong>
                            </p>
                        </div>

                        <!-- Demo: Show the code (Remove in production) -->
                        <?php if ($demoCode): ?>
                            <div class="alert alert-info text-center">
                                <strong>Demo Mode:</strong> Your verification code is<br>
                                <span class="fs-3 fw-bold"><?= $demoCode ?></span>
                            </div>
                        <?php endif; ?>

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

                        <!-- Verification Form -->
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                            <div class="mb-4">
                                <label for="verification_code" class="form-label">Verification Code</label>
                                <input type="text"
                                       class="form-control form-control-lg text-center"
                                       id="verification_code"
                                       name="verification_code"
                                       placeholder="Enter 6-digit code"
                                       maxlength="6"
                                       pattern="[0-9]{6}"
                                       required
                                       autofocus>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-check-lg"></i> Verify Email
                                </button>
                            </div>
                        </form>

                        <!-- Resend Link -->
                        <div class="text-center mt-4">
                            <p class="text-muted mb-2">Didn't receive the code?</p>
                            <a href="<?= BASE_URL ?>/auth/verify_email.php?resend=1" class="btn btn-link">
                                Resend verification code
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
