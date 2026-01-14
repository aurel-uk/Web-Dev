# Complete E-Commerce Platform - Setup Guide

## University Course Project - Web Development

This comprehensive guide will walk you through setting up and running the e-commerce platform step by step.

---

## Table of Contents

1. [Requirements](#1-requirements)
2. [Installing XAMPP](#2-installing-xampp)
3. [Setting Up the Project](#3-setting-up-the-project)
4. [Database Setup](#4-database-setup)
5. [Configuration](#5-configuration)
6. [Running the Project](#6-running-the-project)
7. [Testing Each Feature](#7-testing-each-feature)
8. [Understanding the Code](#8-understanding-the-code)
9. [Common Mistakes & Fixes](#9-common-mistakes--fixes)
10. [Security Explanation](#10-security-explanation)
11. [University Defense Notes](#11-university-defense-notes)

---

## 1. Requirements

Before starting, ensure you have:

- **Operating System:** Windows 10/11, macOS, or Linux
- **XAMPP/WAMP/MAMP:** Local server environment
- **Web Browser:** Chrome, Firefox, or Edge (latest version)
- **Text Editor:** VS Code, Sublime Text, or any code editor

### Minimum Specifications:
- RAM: 4GB (8GB recommended)
- Storage: 500MB free space
- PHP: 7.4+ (included in XAMPP)
- MySQL: 5.7+ (included in XAMPP)

---

## 2. Installing XAMPP

### Windows Installation

1. **Download XAMPP:**
   - Visit: https://www.apachefriends.org/download.html
   - Download the Windows version (PHP 8.x recommended)

2. **Install XAMPP:**
   - Run the installer as Administrator
   - Install to `C:\xampp` (default location)
   - Select components: Apache, MySQL, PHP, phpMyAdmin

3. **Start Services:**
   - Open XAMPP Control Panel
   - Click "Start" next to Apache
   - Click "Start" next to MySQL
   - Both should show green "Running" status

### macOS Installation

1. **Download XAMPP:**
   - Visit: https://www.apachefriends.org/download.html
   - Download the macOS version

2. **Install:**
   - Open the .dmg file
   - Drag XAMPP to Applications folder

3. **Start Services:**
   - Open XAMPP from Applications
   - Start Apache and MySQL servers

### Linux Installation

```bash
# Download (replace version as needed)
wget https://sourceforge.net/projects/xampp/files/XAMPP%20Linux/8.2.4/xampp-linux-x64-8.2.4-0-installer.run

# Make executable
chmod +x xampp-linux-x64-8.2.4-0-installer.run

# Install
sudo ./xampp-linux-x64-8.2.4-0-installer.run

# Start services
sudo /opt/lampp/lampp start
```

---

## 3. Setting Up the Project

### Step 1: Copy Project Files

1. **Locate your web server directory:**
   - Windows: `C:\xampp\htdocs\`
   - macOS: `/Applications/XAMPP/htdocs/`
   - Linux: `/opt/lampp/htdocs/`

2. **Copy the project:**
   - Copy the entire `ecommerce` folder to the htdocs directory
   - Final path should be: `htdocs/ecommerce/`

### Step 2: Verify Folder Structure

Your folder should look like this:

```
htdocs/
└── ecommerce/
    ├── admin/
    ├── api/
    ├── auth/
    ├── config/
    ├── includes/
    ├── pages/
    ├── public/
    │   ├── css/
    │   ├── js/
    │   └── images/
    ├── uploads/
    │   ├── products/
    │   └── users/
    ├── logs/
    ├── database.sql
    └── README.md
```

### Step 3: Set Folder Permissions

For uploads to work, you need write permissions:

**Windows:** Usually works by default

**macOS/Linux:**
```bash
chmod 755 /path/to/ecommerce/uploads
chmod 755 /path/to/ecommerce/uploads/products
chmod 755 /path/to/ecommerce/uploads/users
chmod 755 /path/to/ecommerce/logs
```

---

## 4. Database Setup

### Step 1: Open phpMyAdmin

1. Make sure Apache and MySQL are running in XAMPP
2. Open browser and go to: `http://localhost/phpmyadmin`

### Step 2: Create the Database

**Method 1: Using Import (Recommended)**

1. Click "New" in the left sidebar
2. Enter database name: `ecommerce_db`
3. Select collation: `utf8mb4_unicode_ci`
4. Click "Create"
5. Select the new `ecommerce_db` database
6. Click "Import" tab at the top
7. Click "Choose File" and select `database.sql`
8. Click "Go" at the bottom

**Method 2: Using SQL Tab**

1. Click "New" and create `ecommerce_db`
2. Click on the database
3. Click "SQL" tab
4. Copy all contents from `database.sql`
5. Paste into the SQL box
6. Click "Go"

### Step 3: Verify Tables

After import, you should see these tables:
- users
- email_verifications
- login_attempts
- remember_tokens
- password_resets
- categories
- products
- cart_items
- orders
- order_items
- payment_logs
- system_logs

### Default Admin Account

- **Email:** admin@example.com
- **Password:** password

⚠️ **Important:** Change this password immediately in production!

---

## 5. Configuration

### Step 1: Edit Database Configuration

Open `config/database.php` and verify:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'ecommerce_db');
define('DB_USER', 'root');
define('DB_PASS', '');  // Default XAMPP has no password
```

### Step 2: Edit Constants (Optional)

Open `config/constants.php` to customize:

```php
define('SITE_NAME', 'Your Store Name');
define('SITE_EMAIL', 'your@email.com');

// Payment (for Stripe sandbox)
define('STRIPE_PUBLISHABLE_KEY', 'pk_test_YOUR_KEY');
define('STRIPE_SECRET_KEY', 'sk_test_YOUR_KEY');
```

### Step 3: Configure Base URL

In `config/constants.php`, if you installed in a different folder:

```php
define('BASE_URL', '/ecommerce/public');
```

---

## 6. Running the Project

### Step 1: Start Services

1. Open XAMPP Control Panel
2. Start Apache (click "Start")
3. Start MySQL (click "Start")

### Step 2: Access the Website

Open your browser and visit:

- **Homepage:** http://localhost/ecommerce/public/
- **Login:** http://localhost/ecommerce/public/auth/login.php
- **Admin:** http://localhost/ecommerce/public/admin/

### Troubleshooting

**"Page not found" error:**
- Check if Apache is running
- Verify the folder is in htdocs
- Check the URL path

**"Database connection failed":**
- Verify MySQL is running
- Check database credentials in config/database.php
- Ensure database was created

**"Permission denied":**
- Check folder permissions for uploads/

---

## 7. Testing Each Feature

### Test 1: User Registration

1. Go to: http://localhost/ecommerce/public/auth/register.php
2. Fill in all fields
3. Submit the form
4. Verify the verification code is displayed
5. Enter the code to verify email

**What to observe:**
- Form validation works
- Error messages appear for invalid input
- User is created in database (check phpMyAdmin)
- Verification code is generated

### Test 2: User Login

1. Go to: http://localhost/ecommerce/public/auth/login.php
2. Enter valid credentials
3. Check "Remember me"
4. Submit

**What to observe:**
- Successful login redirects to homepage
- Username appears in navigation
- Session is created
- Remember me creates a cookie

### Test 3: Login Security

1. Try wrong password 7 times
2. Observe the account blocking message
3. Wait 30 minutes (or clear login_attempts table)

### Test 4: Product Browsing

1. Visit products page
2. Test category filters
3. Test search functionality
4. View a single product

### Test 5: Shopping Cart

1. Add items to cart
2. Update quantities
3. Remove items
4. View cart totals

### Test 6: Checkout

1. Proceed to checkout
2. Fill shipping details
3. Select payment method
4. Complete order

### Test 7: Admin Panel

1. Login as admin (admin@example.com / password)
2. Navigate to Admin Panel
3. Test: Add/Edit/Delete products
4. Test: Manage users
5. Test: View orders
6. Test: Manage categories

---

## 8. Understanding the Code

### MVC-Like Architecture

While not a full MVC framework, the code follows a similar structure:

```
┌─────────────────────────────────────────────────┐
│                  BROWSER                        │
└─────────────────┬───────────────────────────────┘
                  │ HTTP Request
                  ▼
┌─────────────────────────────────────────────────┐
│              public/index.php                   │
│         (Entry point / Router)                  │
└─────────────────┬───────────────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────────────────┐
│              config/init.php                    │
│    (Loads config, starts session, checks auth)  │
└─────────────────┬───────────────────────────────┘
                  │
          ┌───────┴───────┐
          ▼               ▼
┌─────────────────┐ ┌─────────────────┐
│   Page Files    │ │   API Files     │
│  (pages/*.php)  │ │  (api/*.php)    │
│  Returns HTML   │ │  Returns JSON   │
└─────────────────┘ └─────────────────┘
          │               │
          └───────┬───────┘
                  ▼
┌─────────────────────────────────────────────────┐
│              Database (MySQL)                   │
│           via PDO connection                    │
└─────────────────────────────────────────────────┘
```

### Key Files Explained

| File | Purpose |
|------|---------|
| `config/init.php` | Initializes everything: session, database, functions |
| `config/database.php` | Database connection using PDO |
| `config/constants.php` | Application settings and constants |
| `includes/functions.php` | Helper functions used everywhere |
| `includes/header.php` | HTML header with navigation |
| `includes/footer.php` | HTML footer with scripts |
| `includes/auth_check.php` | Protects pages requiring login |
| `includes/admin_check.php` | Protects admin-only pages |

### Database Relationships

```
users ─────┬──── email_verifications (1:N)
           ├──── login_attempts (1:N)
           ├──── remember_tokens (1:N)
           ├──── orders (1:N)
           │        └──── order_items (1:N)
           │                  └──── products (N:1)
           └──── cart_items (1:N)
                      └──── products (N:1)

categories ──── products (1:N)

orders ──── payment_logs (1:N)
```

---

## 9. Common Mistakes & Fixes

### Mistake 1: Database Connection Error

**Symptom:** "Connection failed" error

**Fixes:**
1. Verify MySQL is running
2. Check username/password in database.php
3. Ensure database name matches

### Mistake 2: Blank Page

**Symptom:** Empty page, no error

**Fixes:**
1. Enable error display:
   - Edit `config/constants.php`
   - Set `DEBUG_MODE` to `true`
2. Check PHP error log in XAMPP

### Mistake 3: CSS/JS Not Loading

**Symptom:** Unstyled page

**Fixes:**
1. Verify BASE_URL in constants.php
2. Check browser console for 404 errors
3. Ensure files exist in public/css and public/js

### Mistake 4: Images Not Uploading

**Symptom:** Upload fails silently

**Fixes:**
1. Check folder permissions
2. Verify php.ini settings:
   - `file_uploads = On`
   - `upload_max_filesize = 5M`
   - `post_max_size = 8M`

### Mistake 5: Session Issues

**Symptom:** Logged out unexpectedly

**Fixes:**
1. Check session_start() is called
2. Verify session save path is writable
3. Check browser cookies are enabled

### Mistake 6: CSRF Token Error

**Symptom:** "Invalid form submission"

**Fixes:**
1. Ensure form includes csrf_token hidden field
2. Don't open site in multiple tabs during login
3. Check session hasn't expired

---

## 10. Security Explanation

### Password Security

```php
// How passwords are hashed (register.php)
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// How passwords are verified (login.php)
if (password_verify($inputPassword, $storedHash)) {
    // Login success
}
```

**Why this is secure:**
- Bcrypt algorithm (default)
- Automatic salting
- Computationally expensive (slow to brute force)

### SQL Injection Prevention

```php
// WRONG (vulnerable):
$query = "SELECT * FROM users WHERE email = '$email'";

// CORRECT (safe):
$stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
```

**Why prepared statements work:**
- Input is treated as data, not code
- Special characters are escaped automatically

### XSS Prevention

```php
// WRONG (vulnerable):
echo $_POST['name'];

// CORRECT (safe):
echo htmlspecialchars($_POST['name'], ENT_QUOTES, 'UTF-8');

// Using our helper:
echo sanitize($_POST['name']);
```

### CSRF Protection

```php
// In form:
<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

// On submission:
if (!validateCSRFToken($_POST['csrf_token'])) {
    die('Invalid request');
}
```

### Login Attempt Limiting

```php
// After 7 failed attempts:
// - Account locked for 30 minutes
// - Prevents brute force attacks
```

---

## 11. University Defense Notes

### Key Points to Explain

1. **Architecture:**
   - PHP handles server-side logic
   - MySQL stores data
   - Bootstrap provides responsive UI
   - jQuery handles AJAX requests

2. **Security Measures:**
   - Password hashing (bcrypt)
   - SQL injection prevention (PDO prepared statements)
   - XSS prevention (output sanitization)
   - CSRF tokens for form protection
   - Session security (regeneration, timeout)
   - Login attempt limiting

3. **Database Design:**
   - 12 normalized tables
   - Foreign key relationships
   - Proper indexes for performance

4. **User Roles:**
   - Regular users: shop, order, manage profile
   - Admins: full management access

5. **API Design:**
   - RESTful-like endpoints
   - JSON responses
   - AJAX communication

### Questions You Might Be Asked

**Q: Why use PDO instead of mysqli?**
A: PDO supports multiple databases, has cleaner syntax for prepared statements, and provides better exception handling.

**Q: How does session timeout work?**
A: We store `last_activity` timestamp in session. On each request, we check if it exceeds 15 minutes. If so, we destroy the session.

**Q: Why hash passwords instead of encrypting?**
A: Hashing is one-way (can't reverse), while encryption is two-way. If database is breached, hashed passwords can't be decrypted.

**Q: How does remember me work?**
A: We generate a selector:validator token pair. Selector finds the token, validator confirms it. This prevents timing attacks.

**Q: Why use transactions for orders?**
A: Transactions ensure atomicity - if any part fails (order, items, stock update), everything rolls back to prevent inconsistent data.

---

## Quick Reference Commands

### XAMPP Control

```bash
# Linux start/stop
sudo /opt/lampp/lampp start
sudo /opt/lampp/lampp stop

# Check if running
sudo /opt/lampp/lampp status
```

### MySQL Commands

```sql
-- Login to MySQL
mysql -u root -p

-- Show databases
SHOW DATABASES;

-- Use database
USE ecommerce_db;

-- Show tables
SHOW TABLES;

-- Check table structure
DESCRIBE users;

-- Reset admin password
UPDATE users SET password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' WHERE email = 'admin@example.com';
-- (This sets password to 'password')
```

### Useful URLs

| Page | URL |
|------|-----|
| Homepage | http://localhost/ecommerce/public/ |
| Products | http://localhost/ecommerce/public/pages/products.php |
| Login | http://localhost/ecommerce/public/auth/login.php |
| Register | http://localhost/ecommerce/public/auth/register.php |
| Admin | http://localhost/ecommerce/public/admin/ |
| phpMyAdmin | http://localhost/phpmyadmin |

---

## Support

For issues:
1. Check the Common Mistakes section
2. Enable DEBUG_MODE for error details
3. Check XAMPP error logs
4. Review database structure

---

**Good luck with your project!** 🎓
