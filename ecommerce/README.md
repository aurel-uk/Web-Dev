# 🛒 E-Commerce Platform - University Project

## Complete E-Commerce Web Application
**Technologies:** PHP (Pure), MySQL, HTML, CSS, Bootstrap 5, JavaScript, jQuery

---

## 📁 PROJECT STRUCTURE

```
ecommerce/
│
├── public/                 # Publicly accessible files (CSS, JS, images)
│   ├── css/               # Stylesheets
│   │   └── style.css      # Main custom styles
│   ├── js/                # JavaScript files
│   │   └── app.js         # Main application JavaScript
│   ├── images/            # Static images
│   │   ├── products/      # Product images
│   │   └── users/         # User profile images
│   └── index.php          # Main entry point (homepage)
│
├── config/                 # Configuration files
│   ├── database.php       # Database connection settings
│   ├── constants.php      # Application constants
│   └── init.php           # Initialization file (loads everything)
│
├── includes/              # Reusable PHP components
│   ├── header.php         # HTML header with navigation
│   ├── footer.php         # HTML footer
│   ├── functions.php      # Helper functions
│   ├── auth_check.php     # Authentication verification
│   └── admin_check.php    # Admin role verification
│
├── auth/                  # Authentication pages
│   ├── register.php       # User registration
│   ├── login.php          # User login
│   ├── logout.php         # User logout
│   ├── verify_email.php   # Email verification
│   ├── forgot_password.php # Password reset request
│   └── reset_password.php # Password reset form
│
├── admin/                 # Admin panel pages
│   ├── index.php          # Admin dashboard
│   ├── users.php          # User management
│   ├── products.php       # Product management
│   ├── categories.php     # Category management
│   ├── orders.php         # Order management
│   └── logs.php           # System logs viewer
│
├── api/                   # JSON API endpoints
│   ├── register.php       # Registration API
│   ├── login.php          # Login API
│   ├── logout.php         # Logout API
│   ├── cart.php           # Cart operations API
│   ├── checkout.php       # Checkout API
│   ├── profile.php        # Profile update API
│   ├── products.php       # Products API
│   └── payment.php        # Payment processing API
│
├── uploads/               # User uploaded files
│   ├── products/          # Product images
│   └── users/             # User profile pictures
│
├── logs/                  # Application logs
│   └── app.log           # General application log
│
├── pages/                 # User-facing pages
│   ├── products.php       # Product listing
│   ├── product_detail.php # Single product view
│   ├── cart.php           # Shopping cart
│   ├── checkout.php       # Checkout page
│   ├── orders.php         # Order history
│   └── profile.php        # User profile
│
├── database.sql           # Database schema
└── README.md              # This file
```

---

## 🗃️ DATABASE ARCHITECTURE

### Entity-Relationship Description

```
USERS ─────────────────┬──────────────────┬─────────────────┐
  │                    │                  │                 │
  │ 1:N                │ 1:N              │ 1:N             │ 1:N
  ▼                    ▼                  ▼                 ▼
EMAIL_VERIFICATIONS  LOGIN_ATTEMPTS  REMEMBER_TOKENS    ORDERS
                                                           │
                                                           │ 1:N
                                                           ▼
                                                      ORDER_ITEMS
                                                           │
                                                           │ N:1
                                                           ▼
CATEGORIES ───1:N───► PRODUCTS ◄────────────────────────────┘
                         │
                         │ 1:N
                         ▼
                    CART_ITEMS
                         │
                         │ N:1
                         ▼
                       USERS

PAYMENT_LOGS ◄──N:1── ORDERS

SYSTEM_LOGS (independent logging table)
```

### Tables Overview:

1. **users** - Stores user information (customers and admins)
2. **email_verifications** - Stores verification codes for email confirmation
3. **login_attempts** - Tracks failed login attempts for security
4. **remember_tokens** - Stores "remember me" tokens for persistent login
5. **categories** - Product categories
6. **products** - Product catalog
7. **cart_items** - Shopping cart items (session-based but backed by DB)
8. **orders** - Customer orders
9. **order_items** - Individual items in each order
10. **payment_logs** - Payment transaction records
11. **system_logs** - General application activity logs

---

## 🔐 SECURITY FEATURES

1. **Password Hashing** - Uses `password_hash()` with BCRYPT
2. **SQL Injection Prevention** - Prepared statements with PDO
3. **XSS Prevention** - `htmlspecialchars()` on all output
4. **CSRF Protection** - Token-based form protection
5. **Login Attempt Limiting** - 7 attempts max, 30-minute block
6. **Session Security** - Regenerated IDs, timeout after 15 minutes
7. **Input Validation** - Both frontend and backend validation

---

## 🚀 QUICK START

1. Install XAMPP/WAMP/Docker
2. Import `database.sql` into MySQL
3. Configure `config/database.php`
4. Access `http://localhost/ecommerce/public/`

See detailed instructions below.

---

## 👨‍🎓 AUTHOR

University E-Commerce Project
Course: Web Development
