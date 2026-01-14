<?php
/**
 * ============================================
 * USER LOGIN PAGE
 * ============================================
 *
 * This page allows users to log into their account.
 * Features:
 * - Login attempt tracking (max 7 attempts)
 * - Account blocking (30 minutes after max attempts)
 * - Remember me functionality
 * - Session creation
 * - Login logging
 *
 * SECURITY MEASURES:
 * - Password hashing verification
 * - CSRF protection
 * - Brute force protection
 * - Session regeneration
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
$email = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission. Please try again.';
    } else {
        // Get form data
        $email = sanitizeEmail($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $rememberMe = isset($_POST['remember_me']);

        // ----------------------------------------
        // CHECK LOGIN BLOCKING
        // ----------------------------------------

        $blockStatus = isLoginBlocked($email);

        if ($blockStatus['blocked']) {
            $remainingMinutes = ceil($blockStatus['remaining_time'] / 60);
            $errors[] = "Too many failed attempts. Please try again in {$remainingMinutes} minute(s).";

            // Log the blocked attempt
            logActivity('login_blocked', 'Login blocked due to too many attempts', 'user', null, [
                'email' => $email,
                'remaining_time' => $blockStatus['remaining_time']
            ]);
        } else {
            // ----------------------------------------
            // VALIDATE CREDENTIALS
            // ----------------------------------------

            if (empty($email) || empty($password)) {
                $errors[] = 'Please enter both email and password.';
            } else {
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
                if ($user && verifyPassword($password, $user['password'])) {
                    // ----------------------------------------
                    // CHECK ACCOUNT STATUS
                    // ----------------------------------------

                    if ($user['status'] === STATUS_BLOCKED) {
                        $errors[] = ERROR_ACCOUNT_BLOCKED;
                        recordLoginAttempt($email, false);
                    } elseif (!$user['email_verified']) {
                        // Allow login but show warning
                        // You can change this to block login until verified
                        // $errors[] = ERROR_EMAIL_NOT_VERIFIED;

                        // For this demo, we'll allow login with a warning
                        setFlashMessage('warning', 'Please verify your email address to access all features.');
                    }

                    // ----------------------------------------
                    // SUCCESSFUL LOGIN
                    // ----------------------------------------

                    if (empty($errors)) {
                        // Clear failed login attempts
                        clearLoginAttempts($email);

                        // Record successful login
                        recordLoginAttempt($email, true);

                        // Regenerate session ID for security
                        session_regenerate_id(true);

                        // Set session variables
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['user_role'] = $user['role'];
                        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                        $_SESSION['last_activity'] = time();
                        $_SESSION['created'] = time();

                        // Update last login time
                        $updateStmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                        $updateStmt->execute([$user['id']]);

                        // ----------------------------------------
                        // REMEMBER ME FUNCTIONALITY
                        // ----------------------------------------

                        if ($rememberMe) {
                            // Generate remember me token
                            $selector = bin2hex(random_bytes(16));
                            $validator = bin2hex(random_bytes(32));
                            $hashedValidator = hash('sha256', $validator);
                            $expiresAt = date('Y-m-d H:i:s', strtotime('+' . REMEMBER_ME_DAYS . ' days'));

                            // Store token in database
                            $tokenStmt = $db->prepare("
                                INSERT INTO remember_tokens (user_id, selector, hashed_validator, expires_at)
                                VALUES (?, ?, ?, ?)
                            ");
                            $tokenStmt->execute([$user['id'], $selector, $hashedValidator, $expiresAt]);

                            // Set cookie
                            $cookieValue = $selector . ':' . $validator;
                            setcookie(
                                'remember_me',
                                $cookieValue,
                                time() + (REMEMBER_ME_DAYS * 24 * 60 * 60), // 30 days
                                '/',
                                '',
                                false, // Set to true if using HTTPS
                                true   // HTTP only
                            );
                        }

                        // Log the successful login
                        logActivity('login', 'User logged in', 'user', $user['id']);

                        // Check for redirect URL
                        $redirectUrl = $_SESSION['redirect_after_login'] ?? BASE_URL . '/index.php';
                        unset($_SESSION['redirect_after_login']);

                        // Redirect based on role
                        if ($user['role'] === ROLE_ADMIN) {
                            setFlashMessage('success', 'Welcome back, Admin!');
                            redirect(BASE_URL . '/admin/index.php');
                        } else {
                            setFlashMessage('success', SUCCESS_LOGIN);
                            redirect($redirectUrl);
                        }
                    }
                } else {
                    // Invalid credentials
                    $errors[] = ERROR_INVALID_CREDENTIALS;

                    // Record failed attempt
                    recordLoginAttempt($email, false);

                    // Log the failed attempt
                    logActivity('login_failed', 'Failed login attempt', 'user', null, [
                        'email' => $email
                    ]);

                    // Check if account is now blocked
                    $newBlockStatus = isLoginBlocked($email);
                    if ($newBlockStatus['blocked']) {
                        $errors[] = 'Your account has been temporarily blocked due to too many failed attempts.';
                    } else {
                        $attemptsLeft = MAX_LOGIN_ATTEMPTS - $newBlockStatus['attempts'];
                        if ($attemptsLeft <= 3) {
                            $errors[] = "Warning: {$attemptsLeft} attempt(s) remaining before your account is temporarily blocked.";
                        }
                    }
                }
            }
        }
    }
}

// Set page title
$pageTitle = 'Login';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-sm my-5">
                <div class="card-body p-4 p-md-5">
                    <!-- Header -->
                    <div class="text-center mb-4">
                        <h2 class="fw-bold">Welcome Back</h2>
                        <p class="text-muted">Log in to your account</p>
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

                    <!-- Login Form -->
                    <form method="POST" action="" data-validate-form>
                        <!-- CSRF Token -->
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                        <!-- Email -->
                        <div class="mb-3">
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
                                       placeholder="john.doe@example.com"
                                       data-validate="email"
                                       required
                                       autofocus>
                            </div>
                        </div>

                        <!-- Password -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <label for="password" class="form-label">Password</label>
                                <a href="<?= BASE_URL ?>/auth/forgot_password.php" class="small text-decoration-none">
                                    Forgot Password?
                                </a>
                            </div>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-lock"></i>
                                </span>
                                <input type="password"
                                       class="form-control"
                                       id="password"
                                       name="password"
                                       placeholder="Enter your password"
                                       required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Remember Me -->
                        <div class="mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="remember_me" name="remember_me">
                                <label class="form-check-label" for="remember_me">
                                    Remember me for <?= REMEMBER_ME_DAYS ?> days
                                </label>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-box-arrow-in-right"></i> Log In
                            </button>
                        </div>
                    </form>

                    <!-- Divider -->
                    <div class="divider my-4">
                        <span>or</span>
                    </div>

                    <!-- Register Link -->
                    <p class="text-center mb-0">
                        Don't have an account?
                        <a href="<?= BASE_URL ?>/auth/register.php" class="fw-bold">Sign Up</a>
                    </p>
                </div>
            </div>

            <!-- Demo Credentials (Remove in production) -->
            <div class="card border-info mb-5">
                <div class="card-header bg-info text-white">
                    <i class="bi bi-info-circle"></i> Demo Credentials
                </div>
                <div class="card-body">
                    <p class="card-text small mb-2">
                        <strong>Admin:</strong> admin@example.com / password
                    </p>
                    <p class="card-text small mb-0">
                        <strong>User:</strong> Register a new account
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Toggle Password Visibility Script -->
<script>
document.getElementById('togglePassword').addEventListener('click', function() {
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
