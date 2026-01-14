<?php
/**
 * ============================================
 * APPLICATION CONSTANTS
 * ============================================
 *
 * This file defines all the constants used throughout the application.
 * Constants are values that don't change during execution.
 *
 * WHY USE CONSTANTS?
 * 1. Easy to maintain - change once, applies everywhere
 * 2. Prevents magic numbers/strings in code
 * 3. Makes code more readable
 * ============================================
 */

// ============================================
// PATH CONSTANTS
// ============================================

// Root directory of the application
define('ROOT_PATH', dirname(__DIR__));

// Base URL of the application (change if needed)
// For XAMPP: http://localhost/ecommerce/public
define('BASE_URL', '/ecommerce/public');

// Full base URL with domain
define('FULL_URL', 'http://localhost' . BASE_URL);

// Upload directories
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('PRODUCT_UPLOAD_PATH', UPLOAD_PATH . '/products');
define('USER_UPLOAD_PATH', UPLOAD_PATH . '/users');

// Log directory
define('LOG_PATH', ROOT_PATH . '/logs');

// ============================================
// APPLICATION SETTINGS
// ============================================

// Site name
define('SITE_NAME', 'E-Commerce Store');

// Site email (for sending emails)
define('SITE_EMAIL', 'noreply@example.com');

// Admin email (receives notifications)
define('ADMIN_EMAIL', 'admin@example.com');

// Default timezone
define('TIMEZONE', 'UTC');

// Debug mode (set to false in production!)
define('DEBUG_MODE', true);

// ============================================
// SECURITY SETTINGS
// ============================================

// Maximum login attempts before blocking
define('MAX_LOGIN_ATTEMPTS', 7);

// Block duration in minutes after max attempts
define('LOGIN_BLOCK_DURATION', 30);

// Session timeout in seconds (15 minutes = 900 seconds)
define('SESSION_TIMEOUT', 900);

// Remember me cookie duration in days
define('REMEMBER_ME_DAYS', 30);

// Password minimum length
define('PASSWORD_MIN_LENGTH', 8);

// Email verification code expiry in hours
define('VERIFICATION_CODE_EXPIRY', 24);

// Password reset token expiry in hours
define('PASSWORD_RESET_EXPIRY', 1);

// CSRF token name
define('CSRF_TOKEN_NAME', 'csrf_token');

// ============================================
// PAGINATION SETTINGS
// ============================================

// Default items per page
define('ITEMS_PER_PAGE', 12);

// Maximum items per page
define('MAX_ITEMS_PER_PAGE', 100);

// ============================================
// FILE UPLOAD SETTINGS
// ============================================

// Maximum file size in bytes (5MB)
define('MAX_FILE_SIZE', 5 * 1024 * 1024);

// Allowed image types
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

// Allowed file extensions
define('ALLOWED_IMAGE_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

// ============================================
// ORDER SETTINGS
// ============================================

// Order number prefix
define('ORDER_PREFIX', 'ORD');

// Tax rate (as decimal, e.g., 0.10 = 10%)
define('TAX_RATE', 0.10);

// Free shipping threshold
define('FREE_SHIPPING_THRESHOLD', 100.00);

// Default shipping cost
define('DEFAULT_SHIPPING_COST', 9.99);

// ============================================
// PAYMENT SETTINGS (STRIPE SANDBOX)
// ============================================

// Stripe API keys (SANDBOX/TEST KEYS)
// Get your keys from: https://dashboard.stripe.com/test/apikeys
// IMPORTANT: These are test keys - replace with your own!
define('STRIPE_PUBLISHABLE_KEY', 'pk_test_YOUR_PUBLISHABLE_KEY_HERE');
define('STRIPE_SECRET_KEY', 'sk_test_YOUR_SECRET_KEY_HERE');

// PayPal sandbox settings (alternative payment method)
define('PAYPAL_CLIENT_ID', 'YOUR_PAYPAL_CLIENT_ID');
define('PAYPAL_SECRET', 'YOUR_PAYPAL_SECRET');
define('PAYPAL_MODE', 'sandbox'); // 'sandbox' or 'live'

// Currency
define('CURRENCY', 'USD');
define('CURRENCY_SYMBOL', '$');

// ============================================
// USER ROLES
// ============================================

// Role constants for readability
define('ROLE_USER', 'user');
define('ROLE_ADMIN', 'admin');

// ============================================
// STATUS CONSTANTS
// ============================================

// User status
define('STATUS_ACTIVE', 'active');
define('STATUS_BLOCKED', 'blocked');

// Order status
define('ORDER_PENDING', 'pending');
define('ORDER_PROCESSING', 'processing');
define('ORDER_SHIPPED', 'shipped');
define('ORDER_DELIVERED', 'delivered');
define('ORDER_CANCELLED', 'cancelled');
define('ORDER_REFUNDED', 'refunded');

// Payment status
define('PAYMENT_PENDING', 'pending');
define('PAYMENT_PAID', 'paid');
define('PAYMENT_FAILED', 'failed');
define('PAYMENT_REFUNDED', 'refunded');

// ============================================
// ERROR MESSAGES
// ============================================

// These constants store common error messages
// Makes it easy to maintain consistent messaging

define('ERROR_INVALID_EMAIL', 'Please enter a valid email address.');
define('ERROR_EMAIL_EXISTS', 'This email is already registered.');
define('ERROR_PASSWORD_SHORT', 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.');
define('ERROR_PASSWORD_MISMATCH', 'Passwords do not match.');
define('ERROR_INVALID_CREDENTIALS', 'Invalid email or password.');
define('ERROR_ACCOUNT_BLOCKED', 'Your account has been temporarily blocked. Please try again later.');
define('ERROR_EMAIL_NOT_VERIFIED', 'Please verify your email address before logging in.');
define('ERROR_UNAUTHORIZED', 'You do not have permission to access this resource.');
define('ERROR_NOT_FOUND', 'The requested resource was not found.');
define('ERROR_SERVER', 'An unexpected error occurred. Please try again later.');

// ============================================
// SUCCESS MESSAGES
// ============================================

define('SUCCESS_REGISTER', 'Registration successful! Please check your email to verify your account.');
define('SUCCESS_LOGIN', 'Welcome back!');
define('SUCCESS_LOGOUT', 'You have been successfully logged out.');
define('SUCCESS_PASSWORD_RESET', 'Password reset instructions have been sent to your email.');
define('SUCCESS_PASSWORD_CHANGED', 'Your password has been successfully changed.');
define('SUCCESS_PROFILE_UPDATED', 'Your profile has been updated successfully.');
define('SUCCESS_ORDER_PLACED', 'Your order has been placed successfully!');
