<?php
/**
 * ============================================
 * USER PROFILE PAGE
 * ============================================
 *
 * This page allows users to view and edit their profile.
 * Features:
 * - View profile information
 * - Edit name, email, phone
 * - Upload profile picture
 * - Change password
 * - Update shipping address
 *
 * SECURITY:
 * - Users can only see/edit their own profile
 * - Admins can see all profiles (via admin panel)
 * - Password change requires current password
 * ============================================
 */

// Load application initialization
require_once __DIR__ . '/../config/init.php';

// Require authentication
require_once __DIR__ . '/../includes/auth_check.php';

// Get current user data from database
$user = getCurrentUser();
if (!$user) {
    setFlashMessage('error', 'User not found.');
    redirect(BASE_URL . '/auth/login.php');
}

// Initialize variables
$errors = [];
$success = false;
$activeTab = $_GET['tab'] ?? 'profile';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        switch ($action) {
            // ----------------------------------------
            // UPDATE PROFILE INFORMATION
            // ----------------------------------------
            case 'update_profile':
                $firstName = sanitize($_POST['first_name'] ?? '');
                $lastName = sanitize($_POST['last_name'] ?? '');
                $phone = sanitize($_POST['phone'] ?? '');

                // Validation
                if (empty($firstName) || strlen($firstName) < 2) {
                    $errors[] = 'First name must be at least 2 characters.';
                }
                if (empty($lastName) || strlen($lastName) < 2) {
                    $errors[] = 'Last name must be at least 2 characters.';
                }

                if (empty($errors)) {
                    try {
                        $db = getDB();
                        $stmt = $db->prepare("
                            UPDATE users
                            SET first_name = ?, last_name = ?, phone = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([$firstName, $lastName, $phone ?: null, $user['id']]);

                        // Update session name
                        $_SESSION['user_name'] = $firstName . ' ' . $lastName;

                        // Refresh user data
                        $user = getCurrentUser();

                        logActivity('profile_update', 'User updated profile information', 'user', $user['id']);
                        setFlashMessage('success', SUCCESS_PROFILE_UPDATED);
                        $success = true;

                    } catch (Exception $e) {
                        writeLog('ERROR', 'Profile update failed: ' . $e->getMessage());
                        $errors[] = 'Failed to update profile. Please try again.';
                    }
                }
                break;

            // ----------------------------------------
            // UPLOAD PROFILE PICTURE
            // ----------------------------------------
            case 'upload_picture':
                if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                    $result = uploadImage(
                        $_FILES['profile_image'],
                        USER_UPLOAD_PATH,
                        'user_' . $user['id'] . '_'
                    );

                    if ($result['success']) {
                        try {
                            $db = getDB();

                            // Delete old image if exists
                            if ($user['profile_image']) {
                                deleteUploadedFile($user['profile_image'], USER_UPLOAD_PATH);
                            }

                            // Update database with new image
                            $stmt = $db->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
                            $stmt->execute([$result['filename'], $user['id']]);

                            // Refresh user data
                            $user = getCurrentUser();

                            logActivity('profile_picture', 'User updated profile picture', 'user', $user['id']);
                            setFlashMessage('success', 'Profile picture updated successfully!');

                        } catch (Exception $e) {
                            writeLog('ERROR', 'Profile picture update failed: ' . $e->getMessage());
                            $errors[] = 'Failed to update profile picture.';
                        }
                    } else {
                        $errors[] = $result['error'];
                    }
                } else {
                    $errors[] = 'Please select an image to upload.';
                }
                break;

            // ----------------------------------------
            // CHANGE PASSWORD
            // ----------------------------------------
            case 'change_password':
                $currentPassword = $_POST['current_password'] ?? '';
                $newPassword = $_POST['new_password'] ?? '';
                $confirmPassword = $_POST['confirm_password'] ?? '';

                // Verify current password
                if (!verifyPassword($currentPassword, $user['password'] ?? '')) {
                    $errors[] = 'Current password is incorrect.';
                }

                // Validate new password
                $passwordValidation = validatePassword($newPassword);
                if (!$passwordValidation['valid']) {
                    $errors = array_merge($errors, $passwordValidation['errors']);
                }

                // Check confirmation
                if ($newPassword !== $confirmPassword) {
                    $errors[] = ERROR_PASSWORD_MISMATCH;
                }

                if (empty($errors)) {
                    try {
                        $db = getDB();
                        $hashedPassword = hashPassword($newPassword);

                        $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                        $stmt->execute([$hashedPassword, $user['id']]);

                        // Delete remember me tokens for security
                        $tokenStmt = $db->prepare("DELETE FROM remember_tokens WHERE user_id = ?");
                        $tokenStmt->execute([$user['id']]);

                        logActivity('password_change', 'User changed password', 'user', $user['id']);
                        setFlashMessage('success', 'Password changed successfully!');
                        $activeTab = 'security';

                    } catch (Exception $e) {
                        writeLog('ERROR', 'Password change failed: ' . $e->getMessage());
                        $errors[] = 'Failed to change password. Please try again.';
                    }
                }
                $activeTab = 'security';
                break;

            // ----------------------------------------
            // UPDATE SHIPPING ADDRESS
            // ----------------------------------------
            case 'update_address':
                $address = sanitize($_POST['address'] ?? '');
                $city = sanitize($_POST['city'] ?? '');
                $postalCode = sanitize($_POST['postal_code'] ?? '');
                $country = sanitize($_POST['country'] ?? '');

                try {
                    $db = getDB();
                    $stmt = $db->prepare("
                        UPDATE users
                        SET address = ?, city = ?, postal_code = ?, country = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $address ?: null,
                        $city ?: null,
                        $postalCode ?: null,
                        $country ?: null,
                        $user['id']
                    ]);

                    // Refresh user data
                    $user = getCurrentUser();

                    logActivity('address_update', 'User updated shipping address', 'user', $user['id']);
                    setFlashMessage('success', 'Shipping address updated successfully!');
                    $activeTab = 'address';

                } catch (Exception $e) {
                    writeLog('ERROR', 'Address update failed: ' . $e->getMessage());
                    $errors[] = 'Failed to update address. Please try again.';
                }
                $activeTab = 'address';
                break;
        }
    }
}

// Set page title
$pageTitle = 'My Profile';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3 mb-4">
            <div class="card">
                <div class="card-body text-center">
                    <!-- Profile Picture -->
                    <?php if ($user['profile_image']): ?>
                        <img src="<?= BASE_URL ?>/../uploads/users/<?= sanitize($user['profile_image']) ?>"
                             class="rounded-circle mb-3"
                             style="width: 120px; height: 120px; object-fit: cover;"
                             alt="Profile Picture">
                    <?php else: ?>
                        <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center mb-3"
                             style="width: 120px; height: 120px; font-size: 3rem;">
                            <?= strtoupper(substr($user['first_name'], 0, 1)) ?>
                        </div>
                    <?php endif; ?>

                    <h5 class="mb-1"><?= sanitize($user['first_name'] . ' ' . $user['last_name']) ?></h5>
                    <p class="text-muted mb-0"><?= sanitize($user['email']) ?></p>
                    <span class="badge bg-<?= $user['role'] === ROLE_ADMIN ? 'danger' : 'primary' ?> mt-2">
                        <?= ucfirst($user['role']) ?>
                    </span>
                </div>
                <div class="list-group list-group-flush">
                    <a href="?tab=profile" class="list-group-item list-group-item-action <?= $activeTab === 'profile' ? 'active' : '' ?>">
                        <i class="bi bi-person me-2"></i> Profile
                    </a>
                    <a href="?tab=security" class="list-group-item list-group-item-action <?= $activeTab === 'security' ? 'active' : '' ?>">
                        <i class="bi bi-shield-lock me-2"></i> Security
                    </a>
                    <a href="?tab=address" class="list-group-item list-group-item-action <?= $activeTab === 'address' ? 'active' : '' ?>">
                        <i class="bi bi-geo-alt me-2"></i> Address
                    </a>
                    <a href="<?= BASE_URL ?>/pages/orders.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-bag me-2"></i> My Orders
                    </a>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-9">
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

            <!-- Profile Tab -->
            <?php if ($activeTab === 'profile'): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-person"></i> Profile Information</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="action" value="update_profile">

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="first_name" class="form-label">First Name</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name"
                                           value="<?= sanitize($user['first_name']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="last_name" class="form-label">Last Name</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name"
                                           value="<?= sanitize($user['last_name']) ?>" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email"
                                       value="<?= sanitize($user['email']) ?>" disabled>
                                <div class="form-text">Email cannot be changed.</div>
                            </div>

                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone"
                                       value="<?= sanitize($user['phone'] ?? '') ?>">
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg"></i> Save Changes
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Profile Picture -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-image"></i> Profile Picture</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="action" value="upload_picture">

                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <?php if ($user['profile_image']): ?>
                                        <img src="<?= BASE_URL ?>/../uploads/users/<?= sanitize($user['profile_image']) ?>"
                                             id="imagePreview"
                                             class="rounded"
                                             style="width: 80px; height: 80px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="bg-light rounded d-flex align-items-center justify-content-center"
                                             style="width: 80px; height: 80px;">
                                            <i class="bi bi-person fs-1 text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col">
                                    <input type="file" class="form-control" id="profile_image" name="profile_image"
                                           accept="image/*" data-preview="imagePreview">
                                    <div class="form-text">Max 5MB. JPG, PNG, GIF, or WebP.</div>
                                </div>
                                <div class="col-auto">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-upload"></i> Upload
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Security Tab -->
            <?php if ($activeTab === 'security'): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-shield-lock"></i> Change Password</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" data-validate-form>
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="action" value="change_password">

                            <div class="mb-3">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password" class="form-control" id="current_password"
                                       name="current_password" required>
                            </div>

                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password"
                                       name="new_password" data-validate="password" required>
                                <div class="password-strength"></div>
                                <div class="form-text">
                                    At least 8 characters with uppercase, lowercase, and a number.
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password"
                                       name="confirm_password" data-validate="confirm-password" required>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg"></i> Change Password
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Account Info -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-info-circle"></i> Account Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <strong>Account Created:</strong><br>
                                <?= formatDateTime($user['created_at']) ?>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Last Login:</strong><br>
                                <?= $user['last_login'] ? formatDateTime($user['last_login']) : 'Never' ?>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Email Verified:</strong><br>
                                <?php if ($user['email_verified']): ?>
                                    <span class="text-success"><i class="bi bi-check-circle"></i> Yes</span>
                                <?php else: ?>
                                    <span class="text-warning"><i class="bi bi-exclamation-circle"></i> No</span>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Account Status:</strong><br>
                                <span class="badge bg-<?= $user['status'] === 'active' ? 'success' : 'danger' ?>">
                                    <?= ucfirst($user['status']) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Address Tab -->
            <?php if ($activeTab === 'address'): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-geo-alt"></i> Shipping Address</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="action" value="update_address">

                            <div class="mb-3">
                                <label for="address" class="form-label">Street Address</label>
                                <textarea class="form-control" id="address" name="address" rows="2"
                                    ><?= sanitize($user['address'] ?? '') ?></textarea>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="city" class="form-label">City</label>
                                    <input type="text" class="form-control" id="city" name="city"
                                           value="<?= sanitize($user['city'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="postal_code" class="form-label">Postal Code</label>
                                    <input type="text" class="form-control" id="postal_code" name="postal_code"
                                           value="<?= sanitize($user['postal_code'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="country" class="form-label">Country</label>
                                <select class="form-select" id="country" name="country">
                                    <option value="">Select a country</option>
                                    <option value="United States" <?= ($user['country'] ?? '') === 'United States' ? 'selected' : '' ?>>United States</option>
                                    <option value="Canada" <?= ($user['country'] ?? '') === 'Canada' ? 'selected' : '' ?>>Canada</option>
                                    <option value="United Kingdom" <?= ($user['country'] ?? '') === 'United Kingdom' ? 'selected' : '' ?>>United Kingdom</option>
                                    <option value="Australia" <?= ($user['country'] ?? '') === 'Australia' ? 'selected' : '' ?>>Australia</option>
                                    <option value="Germany" <?= ($user['country'] ?? '') === 'Germany' ? 'selected' : '' ?>>Germany</option>
                                    <option value="France" <?= ($user['country'] ?? '') === 'France' ? 'selected' : '' ?>>France</option>
                                    <!-- Add more countries as needed -->
                                </select>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg"></i> Save Address
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
