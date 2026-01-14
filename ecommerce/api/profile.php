<?php
/**
 * ============================================
 * PROFILE API ENDPOINT
 * ============================================
 *
 * Handles user profile operations via AJAX.
 *
 * Actions:
 * - get: Get current user profile
 * - update: Update profile information
 * - change_password: Change password
 * - update_address: Update shipping address
 *
 * ============================================
 */

require_once __DIR__ . '/../config/init.php';

header('Content-Type: application/json');

// Require authentication
if (!isset($_SESSION['user_id'])) {
    jsonResponse(false, 'Authentication required', [], 401);
}

$db = getDB();
$userId = $_SESSION['user_id'];
$action = $_REQUEST['action'] ?? 'get';

switch ($action) {
    // ----------------------------------------
    // GET PROFILE
    // ----------------------------------------
    case 'get':
        $stmt = $db->prepare("
            SELECT id, first_name, last_name, email, phone, profile_image,
                   address, city, postal_code, country, role, email_verified,
                   created_at, last_login
            FROM users WHERE id = ?
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            jsonResponse(false, 'User not found', [], 404);
        }

        jsonResponse(true, 'Profile retrieved', ['user' => $user]);
        break;

    // ----------------------------------------
    // UPDATE PROFILE
    // ----------------------------------------
    case 'update':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(false, 'Method not allowed', [], 405);
        }

        $firstName = sanitize($_POST['first_name'] ?? '');
        $lastName = sanitize($_POST['last_name'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');

        // Validation
        if (empty($firstName) || strlen($firstName) < 2) {
            jsonResponse(false, 'First name must be at least 2 characters', [], 400);
        }
        if (empty($lastName) || strlen($lastName) < 2) {
            jsonResponse(false, 'Last name must be at least 2 characters', [], 400);
        }

        try {
            $stmt = $db->prepare("
                UPDATE users SET first_name = ?, last_name = ?, phone = ?
                WHERE id = ?
            ");
            $stmt->execute([$firstName, $lastName, $phone ?: null, $userId]);

            // Update session
            $_SESSION['user_name'] = $firstName . ' ' . $lastName;

            logActivity('profile_update', 'Profile updated via API', 'user', $userId);

            jsonResponse(true, SUCCESS_PROFILE_UPDATED);

        } catch (Exception $e) {
            jsonResponse(false, 'Failed to update profile', [], 500);
        }
        break;

    // ----------------------------------------
    // CHANGE PASSWORD
    // ----------------------------------------
    case 'change_password':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(false, 'Method not allowed', [], 405);
        }

        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Get current user password
        $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        // Verify current password
        if (!verifyPassword($currentPassword, $user['password'])) {
            jsonResponse(false, 'Current password is incorrect', [], 400);
        }

        // Validate new password
        $validation = validatePassword($newPassword);
        if (!$validation['valid']) {
            jsonResponse(false, implode(' ', $validation['errors']), [], 400);
        }

        if ($newPassword !== $confirmPassword) {
            jsonResponse(false, ERROR_PASSWORD_MISMATCH, [], 400);
        }

        try {
            $hashedPassword = hashPassword($newPassword);
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $userId]);

            // Clear remember tokens for security
            $tokenStmt = $db->prepare("DELETE FROM remember_tokens WHERE user_id = ?");
            $tokenStmt->execute([$userId]);

            logActivity('password_change', 'Password changed via API', 'user', $userId);

            jsonResponse(true, 'Password changed successfully');

        } catch (Exception $e) {
            jsonResponse(false, 'Failed to change password', [], 500);
        }
        break;

    // ----------------------------------------
    // UPDATE ADDRESS
    // ----------------------------------------
    case 'update_address':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(false, 'Method not allowed', [], 405);
        }

        $address = sanitize($_POST['address'] ?? '');
        $city = sanitize($_POST['city'] ?? '');
        $postalCode = sanitize($_POST['postal_code'] ?? '');
        $country = sanitize($_POST['country'] ?? '');

        try {
            $stmt = $db->prepare("
                UPDATE users SET address = ?, city = ?, postal_code = ?, country = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $address ?: null,
                $city ?: null,
                $postalCode ?: null,
                $country ?: null,
                $userId
            ]);

            logActivity('address_update', 'Address updated via API', 'user', $userId);

            jsonResponse(true, 'Address updated successfully');

        } catch (Exception $e) {
            jsonResponse(false, 'Failed to update address', [], 500);
        }
        break;

    default:
        jsonResponse(false, 'Invalid action', [], 400);
}
