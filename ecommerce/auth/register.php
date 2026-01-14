<?php
/**
 * ============================================
 * USER REGISTRATION PAGE
 * ============================================
 *
 * This page allows new users to create an account.
 * Features:
 * - Form validation (frontend + backend)
 * - Email uniqueness check
 * - Password hashing
 * - Email verification code generation
 *
 * FLOW:
 * 1. User fills out registration form
 * 2. Frontend validation (JavaScript)
 * 3. Backend validation (PHP)
 * 4. Create user account
 * 5. Generate verification code
 * 6. Redirect to email verification page
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
$formData = [
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'phone' => ''
];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission. Please try again.';
    } else {
        // Sanitize input
        $formData['first_name'] = sanitize($_POST['first_name'] ?? '');
        $formData['last_name'] = sanitize($_POST['last_name'] ?? '');
        $formData['email'] = sanitizeEmail($_POST['email'] ?? '');
        $formData['phone'] = sanitize($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // ----------------------------------------
        // VALIDATION
        // ----------------------------------------

        // First name validation
        if (empty($formData['first_name'])) {
            $errors[] = 'First name is required.';
        } elseif (strlen($formData['first_name']) < 2) {
            $errors[] = 'First name must be at least 2 characters.';
        }

        // Last name validation
        if (empty($formData['last_name'])) {
            $errors[] = 'Last name is required.';
        } elseif (strlen($formData['last_name']) < 2) {
            $errors[] = 'Last name must be at least 2 characters.';
        }

        // Email validation
        if (empty($formData['email'])) {
            $errors[] = ERROR_INVALID_EMAIL;
        } elseif (!isValidEmail($formData['email'])) {
            $errors[] = ERROR_INVALID_EMAIL;
        } elseif (emailExists($formData['email'])) {
            $errors[] = ERROR_EMAIL_EXISTS;
        }

        // Password validation
        $passwordValidation = validatePassword($password);
        if (!$passwordValidation['valid']) {
            $errors = array_merge($errors, $passwordValidation['errors']);
        }

        // Confirm password
        if ($password !== $confirmPassword) {
            $errors[] = ERROR_PASSWORD_MISMATCH;
        }

        // ----------------------------------------
        // CREATE USER
        // ----------------------------------------

        if (empty($errors)) {
            try {
                $db = getDB();

                // Start transaction
                $db->beginTransaction();

                // Hash the password
                $hashedPassword = hashPassword($password);

                // Insert user
                $stmt = $db->prepare("
                    INSERT INTO users (first_name, last_name, email, password, phone, role, email_verified, status)
                    VALUES (?, ?, ?, ?, ?, 'user', 0, 'active')
                ");
                $stmt->execute([
                    $formData['first_name'],
                    $formData['last_name'],
                    $formData['email'],
                    $hashedPassword,
                    $formData['phone'] ?: null
                ]);

                $userId = $db->lastInsertId();

                // Generate verification code
                $verificationCode = generateVerificationCode();
                $expiresAt = date('Y-m-d H:i:s', strtotime('+' . VERIFICATION_CODE_EXPIRY . ' hours'));

                // Insert verification record
                $stmt = $db->prepare("
                    INSERT INTO email_verifications (user_id, verification_code, expires_at)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$userId, $verificationCode, $expiresAt]);

                // Commit transaction
                $db->commit();

                // Log the registration
                logActivity('user_register', 'New user registered', 'user', $userId);

                // Store user ID in session for verification
                $_SESSION['pending_verification_user_id'] = $userId;
                $_SESSION['pending_verification_email'] = $formData['email'];

                // In a real application, you would send an email here
                // For this demo, we'll store the code in session
                $_SESSION['demo_verification_code'] = $verificationCode;

                // Set success message
                setFlashMessage('success', SUCCESS_REGISTER);

                // Redirect to verification page
                redirect(BASE_URL . '/auth/verify_email.php');

            } catch (Exception $e) {
                // Rollback transaction on error
                if ($db->inTransaction()) {
                    $db->rollBack();
                }

                // Log the error
                writeLog('ERROR', 'Registration failed: ' . $e->getMessage());

                $errors[] = 'Registration failed. Please try again later.';
            }
        }
    }
}

// Set page title
$pageTitle = 'Register';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-sm my-5">
                <div class="card-body p-4 p-md-5">
                    <!-- Header -->
                    <div class="text-center mb-4">
                        <h2 class="fw-bold">Create Account</h2>
                        <p class="text-muted">Join us today and start shopping!</p>
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

                    <!-- Registration Form -->
                    <form method="POST" action="" data-validate-form>
                        <!-- CSRF Token -->
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                        <!-- Name Row -->
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="first_name" class="form-label">First Name *</label>
                                <input type="text"
                                       class="form-control"
                                       id="first_name"
                                       name="first_name"
                                       value="<?= sanitize($formData['first_name']) ?>"
                                       placeholder="John"
                                       data-validate="required"
                                       required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="last_name" class="form-label">Last Name *</label>
                                <input type="text"
                                       class="form-control"
                                       id="last_name"
                                       name="last_name"
                                       value="<?= sanitize($formData['last_name']) ?>"
                                       placeholder="Doe"
                                       data-validate="required"
                                       required>
                            </div>
                        </div>

                        <!-- Email -->
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address *</label>
                            <input type="email"
                                   class="form-control"
                                   id="email"
                                   name="email"
                                   value="<?= sanitize($formData['email']) ?>"
                                   placeholder="john.doe@example.com"
                                   data-validate="email"
                                   required>
                            <div class="form-text">We'll send a verification code to this email.</div>
                        </div>

                        <!-- Phone (Optional) -->
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel"
                                   class="form-control"
                                   id="phone"
                                   name="phone"
                                   value="<?= sanitize($formData['phone']) ?>"
                                   placeholder="+1 (555) 123-4567"
                                   data-validate="phone">
                            <div class="form-text">Optional - for order updates.</div>
                        </div>

                        <!-- Password -->
                        <div class="mb-3">
                            <label for="password" class="form-label">Password *</label>
                            <div class="input-group">
                                <input type="password"
                                       class="form-control"
                                       id="password"
                                       name="password"
                                       placeholder="Create a strong password"
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
                            <label for="confirm_password" class="form-label">Confirm Password *</label>
                            <input type="password"
                                   class="form-control"
                                   id="confirm_password"
                                   name="confirm_password"
                                   placeholder="Confirm your password"
                                   data-validate="confirm-password"
                                   required>
                        </div>

                        <!-- Terms Agreement -->
                        <div class="mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="terms" name="terms" required>
                                <label class="form-check-label" for="terms">
                                    I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>
                                </label>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-person-plus"></i> Create Account
                            </button>
                        </div>
                    </form>

                    <!-- Divider -->
                    <div class="divider my-4">
                        <span>or</span>
                    </div>

                    <!-- Login Link -->
                    <p class="text-center mb-0">
                        Already have an account?
                        <a href="<?= BASE_URL ?>/auth/login.php" class="fw-bold">Log In</a>
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
