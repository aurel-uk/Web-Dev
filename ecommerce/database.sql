-- ============================================
-- E-COMMERCE DATABASE SCHEMA
-- University Project - Complete SQL Script
-- ============================================
--
-- HOW TO USE THIS FILE:
-- 1. Open phpMyAdmin (http://localhost/phpmyadmin)
-- 2. Create a new database called 'ecommerce_db'
-- 3. Select the database
-- 4. Go to "Import" tab
-- 5. Choose this file and click "Go"
--
-- OR use command line:
-- mysql -u root -p < database.sql
-- ============================================

-- Create the database
CREATE DATABASE IF NOT EXISTS ecommerce_db
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

-- Select the database
USE ecommerce_db;

-- ============================================
-- TABLE 1: USERS
-- ============================================
-- Stores all user information (both customers and admins)
-- The 'role' column determines if user is 'user' or 'admin'
-- ============================================

CREATE TABLE users (
    -- Primary key: unique identifier for each user
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- User's first name (required)
    first_name VARCHAR(50) NOT NULL,

    -- User's last name (required)
    last_name VARCHAR(50) NOT NULL,

    -- Email must be unique - used for login
    email VARCHAR(100) NOT NULL UNIQUE,

    -- Password stored as hash (using password_hash in PHP)
    -- VARCHAR(255) to accommodate different hash algorithms
    password VARCHAR(255) NOT NULL,

    -- Phone number (optional)
    phone VARCHAR(20) DEFAULT NULL,

    -- Profile image filename (optional)
    profile_image VARCHAR(255) DEFAULT NULL,

    -- Shipping address (optional)
    address TEXT DEFAULT NULL,

    -- City (optional)
    city VARCHAR(100) DEFAULT NULL,

    -- Postal/ZIP code (optional)
    postal_code VARCHAR(20) DEFAULT NULL,

    -- Country (optional)
    country VARCHAR(100) DEFAULT NULL,

    -- Role: 'user' for customers, 'admin' for administrators
    role ENUM('user', 'admin') DEFAULT 'user',

    -- Email verification status
    email_verified TINYINT(1) DEFAULT 0,

    -- Account status: active or blocked
    status ENUM('active', 'blocked') DEFAULT 'active',

    -- Timestamp when user registered
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Timestamp when user was last updated
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Last login timestamp
    last_login TIMESTAMP NULL DEFAULT NULL,

    -- Index on email for faster login queries
    INDEX idx_email (email),

    -- Index on role for admin queries
    INDEX idx_role (role)
) ENGINE=InnoDB;

-- ============================================
-- TABLE 2: EMAIL_VERIFICATIONS
-- ============================================
-- Stores verification codes sent to users' emails
-- Codes expire after a set time (24 hours)
-- ============================================

CREATE TABLE email_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Foreign key to users table
    user_id INT NOT NULL,

    -- 6-digit verification code
    verification_code VARCHAR(10) NOT NULL,

    -- When the code expires (24 hours from creation)
    expires_at TIMESTAMP NOT NULL,

    -- Whether the code has been used
    used TINYINT(1) DEFAULT 0,

    -- When the verification was created
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Foreign key constraint: if user is deleted, delete their verifications
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,

    -- Index for faster lookups
    INDEX idx_user_code (user_id, verification_code)
) ENGINE=InnoDB;

-- ============================================
-- TABLE 3: LOGIN_ATTEMPTS
-- ============================================
-- Tracks failed login attempts for security
-- Used to block users after too many failed attempts
-- ============================================

CREATE TABLE login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Email used in the attempt (not foreign key because email might not exist)
    email VARCHAR(100) NOT NULL,

    -- IP address of the attempt
    ip_address VARCHAR(45) NOT NULL,

    -- When the attempt occurred
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Whether the attempt was successful
    success TINYINT(1) DEFAULT 0,

    -- Index for counting attempts
    INDEX idx_email_ip (email, ip_address),

    -- Index for cleanup of old records
    INDEX idx_attempted_at (attempted_at)
) ENGINE=InnoDB;

-- ============================================
-- TABLE 4: REMEMBER_TOKENS
-- ============================================
-- Stores "remember me" tokens for persistent login
-- Tokens are stored hashed for security
-- ============================================

CREATE TABLE remember_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Foreign key to users table
    user_id INT NOT NULL,

    -- Token selector (used to find the token)
    selector VARCHAR(64) NOT NULL,

    -- Hashed token validator (compared with cookie value)
    hashed_validator VARCHAR(255) NOT NULL,

    -- When the token expires (30 days from creation)
    expires_at TIMESTAMP NOT NULL,

    -- When the token was created
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Foreign key constraint
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,

    -- Unique index on selector for fast lookups
    UNIQUE INDEX idx_selector (selector)
) ENGINE=InnoDB;

-- ============================================
-- TABLE 5: PASSWORD_RESETS
-- ============================================
-- Stores password reset tokens
-- ============================================

CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Email of the user requesting reset
    email VARCHAR(100) NOT NULL,

    -- Reset token (hashed)
    token VARCHAR(255) NOT NULL,

    -- When the token expires (1 hour)
    expires_at TIMESTAMP NOT NULL,

    -- When the request was made
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Index for lookups
    INDEX idx_email (email),
    INDEX idx_token (token)
) ENGINE=InnoDB;

-- ============================================
-- TABLE 6: CATEGORIES
-- ============================================
-- Product categories for organizing the catalog
-- ============================================

CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Category name
    name VARCHAR(100) NOT NULL,

    -- URL-friendly slug (e.g., "electronics", "clothing")
    slug VARCHAR(100) NOT NULL UNIQUE,

    -- Category description
    description TEXT DEFAULT NULL,

    -- Category image
    image VARCHAR(255) DEFAULT NULL,

    -- Parent category for subcategories (NULL = top-level)
    parent_id INT DEFAULT NULL,

    -- Display order
    sort_order INT DEFAULT 0,

    -- Whether category is active
    status ENUM('active', 'inactive') DEFAULT 'active',

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Self-referencing foreign key for subcategories
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL,

    -- Index for slug lookups
    INDEX idx_slug (slug)
) ENGINE=InnoDB;

-- ============================================
-- TABLE 7: PRODUCTS
-- ============================================
-- Product catalog with all product information
-- ============================================

CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Product name
    name VARCHAR(255) NOT NULL,

    -- URL-friendly slug
    slug VARCHAR(255) NOT NULL UNIQUE,

    -- Short description for listings
    short_description VARCHAR(500) DEFAULT NULL,

    -- Full description for product page
    description TEXT DEFAULT NULL,

    -- Current price
    price DECIMAL(10, 2) NOT NULL,

    -- Sale price (NULL if not on sale)
    sale_price DECIMAL(10, 2) DEFAULT NULL,

    -- Stock keeping unit (product code)
    sku VARCHAR(50) DEFAULT NULL,

    -- Available stock quantity
    stock_quantity INT DEFAULT 0,

    -- Category this product belongs to
    category_id INT DEFAULT NULL,

    -- Main product image
    main_image VARCHAR(255) DEFAULT NULL,

    -- Additional images (stored as JSON array)
    gallery_images TEXT DEFAULT NULL,

    -- Whether product is featured on homepage
    is_featured TINYINT(1) DEFAULT 0,

    -- Product status
    status ENUM('active', 'inactive', 'out_of_stock') DEFAULT 'active',

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Foreign key to categories
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,

    -- Indexes for common queries
    INDEX idx_category (category_id),
    INDEX idx_status (status),
    INDEX idx_featured (is_featured),
    INDEX idx_slug (slug)
) ENGINE=InnoDB;

-- ============================================
-- TABLE 8: CART_ITEMS
-- ============================================
-- Shopping cart items (persisted in database)
-- ============================================

CREATE TABLE cart_items (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Session ID for guest users
    session_id VARCHAR(255) DEFAULT NULL,

    -- User ID for logged-in users (NULL for guests)
    user_id INT DEFAULT NULL,

    -- Product in the cart
    product_id INT NOT NULL,

    -- Quantity
    quantity INT NOT NULL DEFAULT 1,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Foreign keys
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,

    -- Indexes
    INDEX idx_session (session_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB;

-- ============================================
-- TABLE 9: ORDERS
-- ============================================
-- Customer orders with shipping and billing info
-- ============================================

CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Order number (human-readable)
    order_number VARCHAR(50) NOT NULL UNIQUE,

    -- Customer who placed the order
    user_id INT NOT NULL,

    -- Order status
    status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded') DEFAULT 'pending',

    -- Payment status
    payment_status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',

    -- Payment method used
    payment_method VARCHAR(50) DEFAULT NULL,

    -- Subtotal before tax and shipping
    subtotal DECIMAL(10, 2) NOT NULL,

    -- Tax amount
    tax_amount DECIMAL(10, 2) DEFAULT 0.00,

    -- Shipping cost
    shipping_amount DECIMAL(10, 2) DEFAULT 0.00,

    -- Discount amount
    discount_amount DECIMAL(10, 2) DEFAULT 0.00,

    -- Grand total
    total_amount DECIMAL(10, 2) NOT NULL,

    -- Shipping information
    shipping_first_name VARCHAR(50) NOT NULL,
    shipping_last_name VARCHAR(50) NOT NULL,
    shipping_email VARCHAR(100) NOT NULL,
    shipping_phone VARCHAR(20) DEFAULT NULL,
    shipping_address TEXT NOT NULL,
    shipping_city VARCHAR(100) NOT NULL,
    shipping_postal_code VARCHAR(20) NOT NULL,
    shipping_country VARCHAR(100) NOT NULL,

    -- Order notes from customer
    notes TEXT DEFAULT NULL,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Foreign key
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,

    -- Indexes
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_order_number (order_number)
) ENGINE=InnoDB;

-- ============================================
-- TABLE 10: ORDER_ITEMS
-- ============================================
-- Individual items in each order
-- ============================================

CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Order this item belongs to
    order_id INT NOT NULL,

    -- Product (kept for reference even if product is deleted)
    product_id INT DEFAULT NULL,

    -- Product name at time of order (in case product changes)
    product_name VARCHAR(255) NOT NULL,

    -- Product price at time of order
    product_price DECIMAL(10, 2) NOT NULL,

    -- Quantity ordered
    quantity INT NOT NULL,

    -- Line total (price * quantity)
    total DECIMAL(10, 2) NOT NULL,

    -- Timestamp
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Foreign keys
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,

    -- Index
    INDEX idx_order (order_id)
) ENGINE=InnoDB;

-- ============================================
-- TABLE 11: PAYMENT_LOGS
-- ============================================
-- All payment transaction records
-- ============================================

CREATE TABLE payment_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Order this payment is for
    order_id INT NOT NULL,

    -- User who made the payment
    user_id INT NOT NULL,

    -- Payment gateway (stripe, paypal)
    payment_gateway VARCHAR(50) NOT NULL,

    -- Transaction ID from payment gateway
    transaction_id VARCHAR(255) DEFAULT NULL,

    -- Payment intent ID (for Stripe)
    payment_intent_id VARCHAR(255) DEFAULT NULL,

    -- Amount paid
    amount DECIMAL(10, 2) NOT NULL,

    -- Currency (USD, EUR, etc.)
    currency VARCHAR(10) DEFAULT 'USD',

    -- Payment status
    status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',

    -- Error message if payment failed
    error_message TEXT DEFAULT NULL,

    -- Raw response from payment gateway (JSON)
    gateway_response TEXT DEFAULT NULL,

    -- IP address of payer
    ip_address VARCHAR(45) DEFAULT NULL,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Foreign keys
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE RESTRICT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,

    -- Indexes
    INDEX idx_order (order_id),
    INDEX idx_transaction (transaction_id)
) ENGINE=InnoDB;

-- ============================================
-- TABLE 12: SYSTEM_LOGS
-- ============================================
-- General application activity logs
-- ============================================

CREATE TABLE system_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- User who performed the action (NULL for system actions)
    user_id INT DEFAULT NULL,

    -- Type of action
    action_type VARCHAR(50) NOT NULL,

    -- Description of what happened
    description TEXT NOT NULL,

    -- Related entity type (user, product, order, etc.)
    entity_type VARCHAR(50) DEFAULT NULL,

    -- Related entity ID
    entity_id INT DEFAULT NULL,

    -- IP address
    ip_address VARCHAR(45) DEFAULT NULL,

    -- User agent string
    user_agent VARCHAR(500) DEFAULT NULL,

    -- Additional data (JSON)
    extra_data TEXT DEFAULT NULL,

    -- Timestamp
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Foreign key (SET NULL if user is deleted)
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,

    -- Indexes
    INDEX idx_user (user_id),
    INDEX idx_action (action_type),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- ============================================
-- INSERT DEFAULT DATA
-- ============================================

-- Insert default admin user
-- Password: Admin123! (hashed with password_hash)
INSERT INTO users (first_name, last_name, email, password, role, email_verified, status) VALUES
('Admin', 'User', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1, 'active');

-- Note: The hash above is for 'password' - change it in production!
-- To generate a new hash, use: echo password_hash('YourPassword', PASSWORD_DEFAULT);

-- Insert sample categories
INSERT INTO categories (name, slug, description, sort_order) VALUES
('Electronics', 'electronics', 'Electronic devices and gadgets', 1),
('Clothing', 'clothing', 'Fashion and apparel', 2),
('Books', 'books', 'Books and educational materials', 3),
('Home & Garden', 'home-garden', 'Home improvement and garden supplies', 4),
('Sports', 'sports', 'Sports equipment and accessories', 5);

-- Insert sample products
INSERT INTO products (name, slug, short_description, description, price, sale_price, stock_quantity, category_id, is_featured, status) VALUES
('Wireless Headphones', 'wireless-headphones', 'High-quality wireless headphones with noise cancellation', 'Experience premium sound quality with our wireless headphones. Features include active noise cancellation, 30-hour battery life, and comfortable over-ear design.', 199.99, 149.99, 50, 1, 1, 'active'),
('Smartphone Case', 'smartphone-case', 'Durable protective case for smartphones', 'Protect your phone with this premium case. Made from high-quality materials with shock absorption technology.', 29.99, NULL, 100, 1, 0, 'active'),
('Running Shoes', 'running-shoes', 'Comfortable running shoes for athletes', 'Lightweight and breathable running shoes designed for maximum performance and comfort during your workouts.', 89.99, 69.99, 30, 5, 1, 'active'),
('Programming Book', 'programming-book', 'Learn web development from scratch', 'Comprehensive guide to web development covering HTML, CSS, JavaScript, PHP, and MySQL. Perfect for beginners.', 49.99, NULL, 25, 3, 0, 'active'),
('Cotton T-Shirt', 'cotton-tshirt', 'Classic cotton t-shirt in multiple colors', 'Soft and comfortable 100% cotton t-shirt. Available in various sizes and colors.', 24.99, 19.99, 200, 2, 1, 'active');

-- ============================================
-- END OF SCHEMA
-- ============================================
--
-- SUMMARY:
-- - 12 tables created
-- - All tables use InnoDB engine for foreign key support
-- - UTF8MB4 character set for full Unicode support
-- - Proper indexes for query optimization
-- - Foreign key constraints for data integrity
-- - Default admin user created (change password!)
-- - Sample categories and products added
-- ============================================
