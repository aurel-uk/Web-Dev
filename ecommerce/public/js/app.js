/**
 * ============================================
 * MAIN APPLICATION JAVASCRIPT
 * ============================================
 *
 * This file contains all the JavaScript functionality
 * for the e-commerce platform.
 *
 * DEPENDENCIES:
 * - jQuery 3.7.1
 * - Bootstrap 5.3.2
 *
 * ORGANIZATION:
 * 1. Configuration & Setup
 * 2. AJAX Helper Functions
 * 3. Form Validation
 * 4. Cart Functions
 * 5. Product Functions
 * 6. User Functions
 * 7. Admin Functions
 * 8. Utility Functions
 * 9. Event Handlers
 * ============================================
 */

// ============================================
// 1. CONFIGURATION & SETUP
// ============================================

/**
 * Application configuration
 */
const App = {
    // Base URL for API calls (adjust if needed)
    baseUrl: '/ecommerce/public',

    // API endpoint prefix
    apiUrl: '/ecommerce/api',

    // CSRF token from meta tag
    csrfToken: $('meta[name="csrf-token"]').attr('content'),

    // Notification settings
    notificationDuration: 3000,

    // Debug mode
    debug: true
};

/**
 * Log messages in debug mode
 * @param {string} message - Message to log
 * @param {any} data - Optional data to log
 */
function debugLog(message, data = null) {
    if (App.debug) {
        console.log(`[App] ${message}`, data || '');
    }
}

// ============================================
// 2. AJAX HELPER FUNCTIONS
// ============================================

/**
 * Make an AJAX request to the API
 *
 * This is the main function for communicating with the backend.
 * All API calls should use this function for consistency.
 *
 * @param {string} endpoint - API endpoint (e.g., '/api/cart.php')
 * @param {string} method - HTTP method (GET, POST, PUT, DELETE)
 * @param {object} data - Data to send
 * @returns {Promise} jQuery promise
 *
 * USAGE EXAMPLE:
 * apiRequest('/api/cart.php', 'POST', { product_id: 1, quantity: 2 })
 *     .then(response => {
 *         console.log('Success:', response);
 *     })
 *     .catch(error => {
 *         console.log('Error:', error);
 *     });
 */
function apiRequest(endpoint, method = 'GET', data = {}) {
    // Always include CSRF token for POST requests
    if (method !== 'GET') {
        data.csrf_token = App.csrfToken;
    }

    debugLog(`API Request: ${method} ${endpoint}`, data);

    return $.ajax({
        url: endpoint,
        method: method,
        data: data,
        dataType: 'json',
        // Tell the server this is an AJAX request
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    }).then(response => {
        debugLog('API Response:', response);
        return response;
    }).catch(error => {
        debugLog('API Error:', error);
        throw error;
    });
}

/**
 * Shorthand for POST requests
 */
function apiPost(endpoint, data = {}) {
    return apiRequest(endpoint, 'POST', data);
}

/**
 * Shorthand for GET requests
 */
function apiGet(endpoint, data = {}) {
    return apiRequest(endpoint, 'GET', data);
}

// ============================================
// 3. FORM VALIDATION
// ============================================

/**
 * Form validation rules
 *
 * Each field type has its own validation function
 * that returns true if valid, or an error message if invalid.
 */
const Validators = {
    /**
     * Validate email format
     */
    email: function(value) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!value.trim()) return 'Email is required';
        if (!emailRegex.test(value)) return 'Please enter a valid email address';
        return true;
    },

    /**
     * Validate password strength
     */
    password: function(value) {
        if (!value) return 'Password is required';
        if (value.length < 8) return 'Password must be at least 8 characters';
        if (!/[A-Z]/.test(value)) return 'Password must contain an uppercase letter';
        if (!/[a-z]/.test(value)) return 'Password must contain a lowercase letter';
        if (!/[0-9]/.test(value)) return 'Password must contain a number';
        return true;
    },

    /**
     * Validate password confirmation
     */
    confirmPassword: function(value, password) {
        if (!value) return 'Please confirm your password';
        if (value !== password) return 'Passwords do not match';
        return true;
    },

    /**
     * Validate required field
     */
    required: function(value, fieldName = 'This field') {
        if (!value || !value.trim()) return `${fieldName} is required`;
        return true;
    },

    /**
     * Validate minimum length
     */
    minLength: function(value, min, fieldName = 'This field') {
        if (value.length < min) return `${fieldName} must be at least ${min} characters`;
        return true;
    },

    /**
     * Validate phone number
     */
    phone: function(value) {
        if (!value) return true; // Optional field
        const phoneRegex = /^[\d\s\-\+\(\)]{10,20}$/;
        if (!phoneRegex.test(value)) return 'Please enter a valid phone number';
        return true;
    },

    /**
     * Validate postal code
     */
    postalCode: function(value) {
        if (!value) return 'Postal code is required';
        if (value.length < 3) return 'Please enter a valid postal code';
        return true;
    }
};

/**
 * Show validation error on a field
 * @param {jQuery} $field - The input field
 * @param {string} message - Error message
 */
function showFieldError($field, message) {
    // Remove any existing error
    clearFieldError($field);

    // Add error class
    $field.addClass('is-invalid');

    // Add error message
    $field.after(`<div class="invalid-feedback">${message}</div>`);
}

/**
 * Clear validation error from a field
 * @param {jQuery} $field - The input field
 */
function clearFieldError($field) {
    $field.removeClass('is-invalid is-valid');
    $field.next('.invalid-feedback').remove();
}

/**
 * Show success state on a field
 * @param {jQuery} $field - The input field
 */
function showFieldSuccess($field) {
    clearFieldError($field);
    $field.addClass('is-valid');
}

/**
 * Validate an entire form
 * @param {jQuery} $form - The form element
 * @returns {boolean} True if valid
 */
function validateForm($form) {
    let isValid = true;

    // Validate each field with data-validate attribute
    $form.find('[data-validate]').each(function() {
        const $field = $(this);
        const validationType = $field.data('validate');
        const value = $field.val();

        let result;

        switch (validationType) {
            case 'email':
                result = Validators.email(value);
                break;
            case 'password':
                result = Validators.password(value);
                break;
            case 'confirm-password':
                const password = $form.find('[data-validate="password"]').val();
                result = Validators.confirmPassword(value, password);
                break;
            case 'required':
                result = Validators.required(value, $field.attr('placeholder') || 'This field');
                break;
            case 'phone':
                result = Validators.phone(value);
                break;
            default:
                result = Validators.required(value);
        }

        if (result !== true) {
            showFieldError($field, result);
            isValid = false;
        } else {
            showFieldSuccess($field);
        }
    });

    return isValid;
}

/**
 * Password strength indicator
 * @param {string} password - Password to check
 * @returns {object} Strength info
 */
function getPasswordStrength(password) {
    let strength = 0;
    let label = 'weak';

    if (password.length >= 8) strength++;
    if (password.length >= 12) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/[a-z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^A-Za-z0-9]/.test(password)) strength++;

    if (strength <= 2) label = 'weak';
    else if (strength <= 3) label = 'fair';
    else if (strength <= 4) label = 'good';
    else label = 'strong';

    return { strength, label };
}

// ============================================
// 4. CART FUNCTIONS
// ============================================

/**
 * Add item to cart
 * @param {number} productId - Product ID
 * @param {number} quantity - Quantity to add
 * @returns {Promise}
 */
function addToCart(productId, quantity = 1) {
    return apiPost(`${App.apiUrl}/cart.php`, {
        action: 'add',
        product_id: productId,
        quantity: quantity
    }).then(response => {
        if (response.success) {
            // Update cart count in header
            updateCartCount(response.data.cart_count);

            // Show success notification
            showNotification('success', 'Item added to cart!');
        } else {
            showNotification('error', response.message || 'Failed to add item');
        }
        return response;
    });
}

/**
 * Update cart item quantity
 * @param {number} productId - Product ID
 * @param {number} quantity - New quantity
 * @returns {Promise}
 */
function updateCartItem(productId, quantity) {
    return apiPost(`${App.apiUrl}/cart.php`, {
        action: 'update',
        product_id: productId,
        quantity: quantity
    }).then(response => {
        if (response.success) {
            // Update cart display
            updateCartDisplay(response.data);
        }
        return response;
    });
}

/**
 * Remove item from cart
 * @param {number} productId - Product ID
 * @returns {Promise}
 */
function removeFromCart(productId) {
    return apiPost(`${App.apiUrl}/cart.php`, {
        action: 'remove',
        product_id: productId
    }).then(response => {
        if (response.success) {
            // Remove item from display
            $(`.cart-item[data-product-id="${productId}"]`).fadeOut(300, function() {
                $(this).remove();
                updateCartDisplay(response.data);
            });
            showNotification('success', 'Item removed from cart');
        }
        return response;
    });
}

/**
 * Update cart count badge in header
 * @param {number} count - New cart count
 */
function updateCartCount(count) {
    const $badge = $('.cart-count');

    if (count > 0) {
        if ($badge.length) {
            $badge.text(count);
        } else {
            // Create badge if it doesn't exist
            $('.bi-cart3').parent().append(
                `<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger cart-count">${count}</span>`
            );
        }
    } else {
        $badge.remove();
    }
}

/**
 * Update cart display (totals, etc.)
 * @param {object} data - Cart data from server
 */
function updateCartDisplay(data) {
    if (data.cart_count !== undefined) {
        updateCartCount(data.cart_count);
    }
    if (data.subtotal !== undefined) {
        $('#cart-subtotal').text('$' + data.subtotal.toFixed(2));
    }
    if (data.tax !== undefined) {
        $('#cart-tax').text('$' + data.tax.toFixed(2));
    }
    if (data.shipping !== undefined) {
        $('#cart-shipping').text(data.shipping > 0 ? '$' + data.shipping.toFixed(2) : 'Free');
    }
    if (data.total !== undefined) {
        $('#cart-total').text('$' + data.total.toFixed(2));
    }

    // Show empty cart message if no items
    if (data.cart_count === 0) {
        $('.cart-items').html(`
            <div class="empty-cart">
                <i class="bi bi-cart-x"></i>
                <h4 class="mt-3">Your cart is empty</h4>
                <p class="text-muted">Browse our products and add items to your cart.</p>
                <a href="${App.baseUrl}/pages/products.php" class="btn btn-primary">
                    <i class="bi bi-shop"></i> Continue Shopping
                </a>
            </div>
        `);
    }
}

// ============================================
// 5. PRODUCT FUNCTIONS
// ============================================

/**
 * Load products with filters
 * @param {object} filters - Filter options
 * @returns {Promise}
 */
function loadProducts(filters = {}) {
    return apiGet(`${App.apiUrl}/products.php`, {
        action: 'list',
        ...filters
    });
}

/**
 * Search products
 * @param {string} query - Search query
 * @returns {Promise}
 */
function searchProducts(query) {
    return apiGet(`${App.apiUrl}/products.php`, {
        action: 'search',
        q: query
    });
}

/**
 * Quick view product details
 * @param {number} productId - Product ID
 */
function quickViewProduct(productId) {
    apiGet(`${App.apiUrl}/products.php`, {
        action: 'get',
        id: productId
    }).then(response => {
        if (response.success) {
            showProductModal(response.data);
        }
    });
}

/**
 * Show product in modal
 * @param {object} product - Product data
 */
function showProductModal(product) {
    const modalHtml = `
        <div class="modal fade" id="quickViewModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">${escapeHtml(product.name)}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <img src="${product.main_image || App.baseUrl + '/images/placeholder.png'}"
                                     class="img-fluid rounded" alt="${escapeHtml(product.name)}">
                            </div>
                            <div class="col-md-6">
                                <p class="text-muted">${escapeHtml(product.short_description || '')}</p>
                                <div class="mb-3">
                                    ${product.sale_price
                                        ? `<span class="price-original">$${product.price}</span>
                                           <span class="price-sale fs-4">$${product.sale_price}</span>`
                                        : `<span class="fs-4 fw-bold">$${product.price}</span>`
                                    }
                                </div>
                                <p class="${product.stock_quantity > 5 ? 'stock-available' : product.stock_quantity > 0 ? 'stock-low' : 'stock-out'}">
                                    ${product.stock_quantity > 5 ? 'In Stock' : product.stock_quantity > 0 ? `Only ${product.stock_quantity} left` : 'Out of Stock'}
                                </p>
                                ${product.stock_quantity > 0 ? `
                                    <div class="d-flex gap-2">
                                        <input type="number" class="form-control" id="modal-quantity" value="1" min="1" max="${product.stock_quantity}" style="width: 80px;">
                                        <button class="btn btn-primary" onclick="addToCart(${product.id}, $('#modal-quantity').val())">
                                            <i class="bi bi-cart-plus"></i> Add to Cart
                                        </button>
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Remove existing modal if any
    $('#quickViewModal').remove();

    // Add and show modal
    $('body').append(modalHtml);
    const modal = new bootstrap.Modal($('#quickViewModal'));
    modal.show();
}

// ============================================
// 6. USER FUNCTIONS
// ============================================

/**
 * Handle user login
 * @param {string} email - User email
 * @param {string} password - User password
 * @param {boolean} rememberMe - Remember me option
 * @returns {Promise}
 */
function loginUser(email, password, rememberMe = false) {
    return apiPost(`${App.apiUrl}/login.php`, {
        email: email,
        password: password,
        remember_me: rememberMe ? 1 : 0
    });
}

/**
 * Handle user registration
 * @param {object} userData - User registration data
 * @returns {Promise}
 */
function registerUser(userData) {
    return apiPost(`${App.apiUrl}/register.php`, userData);
}

/**
 * Handle user logout
 * @returns {Promise}
 */
function logoutUser() {
    return apiPost(`${App.apiUrl}/logout.php`);
}

/**
 * Update user profile
 * @param {object} profileData - Profile data
 * @returns {Promise}
 */
function updateProfile(profileData) {
    return apiPost(`${App.apiUrl}/profile.php`, {
        action: 'update',
        ...profileData
    });
}

// ============================================
// 7. ADMIN FUNCTIONS
// ============================================

/**
 * Delete a record
 * @param {string} entity - Entity type (user, product, order, etc.)
 * @param {number} id - Record ID
 * @returns {Promise}
 */
function adminDelete(entity, id) {
    if (!confirm('Are you sure you want to delete this item?')) {
        return Promise.reject('Cancelled');
    }

    return apiPost(`${App.apiUrl}/admin/${entity}.php`, {
        action: 'delete',
        id: id
    }).then(response => {
        if (response.success) {
            // Remove row from table
            $(`#${entity}-${id}`).fadeOut(300, function() {
                $(this).remove();
            });
            showNotification('success', 'Item deleted successfully');
        }
        return response;
    });
}

/**
 * Toggle status (active/inactive)
 * @param {string} entity - Entity type
 * @param {number} id - Record ID
 * @returns {Promise}
 */
function toggleStatus(entity, id) {
    return apiPost(`${App.apiUrl}/admin/${entity}.php`, {
        action: 'toggle_status',
        id: id
    }).then(response => {
        if (response.success) {
            // Update status badge
            const $badge = $(`#${entity}-${id} .status-badge`);
            if (response.data.status === 'active') {
                $badge.removeClass('bg-secondary').addClass('bg-success').text('Active');
            } else {
                $badge.removeClass('bg-success').addClass('bg-secondary').text('Inactive');
            }
            showNotification('success', 'Status updated');
        }
        return response;
    });
}

// ============================================
// 8. UTILITY FUNCTIONS
// ============================================

/**
 * Show notification toast
 * @param {string} type - Notification type (success, error, warning, info)
 * @param {string} message - Notification message
 */
function showNotification(type, message) {
    // Map type to Bootstrap class
    const bgClass = {
        success: 'bg-success',
        error: 'bg-danger',
        warning: 'bg-warning',
        info: 'bg-info'
    }[type] || 'bg-primary';

    // Create toast HTML
    const toastHtml = `
        <div class="toast align-items-center text-white ${bgClass} border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body">
                    ${escapeHtml(message)}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;

    // Get or create toast container
    let $container = $('#toast-container');
    if (!$container.length) {
        $('body').append('<div id="toast-container" class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;"></div>');
        $container = $('#toast-container');
    }

    // Add and show toast
    const $toast = $(toastHtml);
    $container.append($toast);

    const toast = new bootstrap.Toast($toast, {
        delay: App.notificationDuration
    });
    toast.show();

    // Remove toast element after hidden
    $toast.on('hidden.bs.toast', function() {
        $(this).remove();
    });
}

/**
 * Escape HTML to prevent XSS
 * @param {string} text - Text to escape
 * @returns {string} Escaped text
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Format currency
 * @param {number} amount - Amount to format
 * @returns {string} Formatted amount
 */
function formatCurrency(amount) {
    return '$' + parseFloat(amount).toFixed(2);
}

/**
 * Format date
 * @param {string} dateStr - Date string
 * @returns {string} Formatted date
 */
function formatDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

/**
 * Set loading state on button
 * @param {jQuery} $btn - Button element
 * @param {boolean} loading - Loading state
 */
function setButtonLoading($btn, loading) {
    if (loading) {
        $btn.prop('disabled', true);
        $btn.data('original-text', $btn.html());
        $btn.html('<span class="spinner-border spinner-border-sm" role="status"></span> Loading...');
    } else {
        $btn.prop('disabled', false);
        $btn.html($btn.data('original-text'));
    }
}

/**
 * Debounce function for search
 * @param {function} func - Function to debounce
 * @param {number} wait - Wait time in ms
 * @returns {function} Debounced function
 */
function debounce(func, wait = 300) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// ============================================
// 9. EVENT HANDLERS
// ============================================

/**
 * Initialize all event handlers when document is ready
 */
$(document).ready(function() {
    debugLog('App initialized');

    // ----------------------------------------
    // Form Validation
    // ----------------------------------------

    // Real-time validation on blur
    $('[data-validate]').on('blur', function() {
        const $field = $(this);
        const validationType = $field.data('validate');
        const value = $field.val();

        let result;
        switch (validationType) {
            case 'email':
                result = Validators.email(value);
                break;
            case 'password':
                result = Validators.password(value);
                break;
            default:
                result = Validators.required(value);
        }

        if (result !== true) {
            showFieldError($field, result);
        } else {
            showFieldSuccess($field);
        }
    });

    // Password strength indicator
    $('input[type="password"][data-validate="password"]').on('input', function() {
        const $field = $(this);
        const password = $field.val();
        const strength = getPasswordStrength(password);

        let $indicator = $field.siblings('.password-strength');
        if (!$indicator.length) {
            $field.after('<div class="password-strength"></div>');
            $indicator = $field.siblings('.password-strength');
        }

        $indicator.removeClass('weak fair good strong').addClass(strength.label);
    });

    // Form submission with validation
    $('form[data-validate-form]').on('submit', function(e) {
        if (!validateForm($(this))) {
            e.preventDefault();
            showNotification('error', 'Please fix the errors in the form');
        }
    });

    // ----------------------------------------
    // Cart Functionality
    // ----------------------------------------

    // Add to cart button
    $(document).on('click', '.btn-add-cart', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const productId = $btn.data('product-id');
        const quantity = $btn.siblings('.quantity-input input').val() || 1;

        setButtonLoading($btn, true);

        addToCart(productId, quantity).finally(() => {
            setButtonLoading($btn, false);
            $btn.addClass('added');
            setTimeout(() => $btn.removeClass('added'), 2000);
        });
    });

    // Cart quantity change
    $(document).on('change', '.cart-item .quantity-input input', function() {
        const $input = $(this);
        const productId = $input.closest('.cart-item').data('product-id');
        const quantity = parseInt($input.val());

        if (quantity < 1) {
            $input.val(1);
            return;
        }

        updateCartItem(productId, quantity);
    });

    // Quantity increment/decrement buttons
    $(document).on('click', '.quantity-btn', function() {
        const $btn = $(this);
        const $input = $btn.siblings('input');
        const currentVal = parseInt($input.val());
        const isIncrement = $btn.hasClass('increment');

        if (isIncrement) {
            $input.val(currentVal + 1).trigger('change');
        } else if (currentVal > 1) {
            $input.val(currentVal - 1).trigger('change');
        }
    });

    // Remove from cart
    $(document).on('click', '.btn-remove-cart', function() {
        const productId = $(this).closest('.cart-item').data('product-id');
        removeFromCart(productId);
    });

    // ----------------------------------------
    // Product Search
    // ----------------------------------------

    // Live search with debounce
    const $searchInput = $('input[name="search"]');
    const debouncedSearch = debounce(function(query) {
        if (query.length < 2) return;

        searchProducts(query).then(response => {
            // Handle search results
            // This could populate a dropdown or update the product list
            debugLog('Search results:', response);
        });
    }, 300);

    $searchInput.on('input', function() {
        const query = $(this).val();
        debouncedSearch(query);
    });

    // ----------------------------------------
    // Quick View
    // ----------------------------------------

    $(document).on('click', '.btn-quick-view', function(e) {
        e.preventDefault();
        const productId = $(this).data('product-id');
        quickViewProduct(productId);
    });

    // ----------------------------------------
    // Image Preview for File Uploads
    // ----------------------------------------

    $('input[type="file"][data-preview]').on('change', function() {
        const $input = $(this);
        const previewId = $input.data('preview');
        const $preview = $(`#${previewId}`);

        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                $preview.attr('src', e.target.result).show();
            };
            reader.readAsDataURL(this.files[0]);
        }
    });

    // ----------------------------------------
    // Delete Confirmation
    // ----------------------------------------

    $(document).on('click', '[data-confirm-delete]', function(e) {
        e.preventDefault();
        const message = $(this).data('confirm-delete') || 'Are you sure you want to delete this item?';
        if (confirm(message)) {
            window.location.href = $(this).attr('href');
        }
    });

    // ----------------------------------------
    // Auto-hide alerts
    // ----------------------------------------

    setTimeout(function() {
        $('.alert.alert-dismissible').fadeOut(500);
    }, 5000);

    // ----------------------------------------
    // Bootstrap Tooltips Initialization
    // ----------------------------------------

    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // ----------------------------------------
    // Print Order/Invoice
    // ----------------------------------------

    $(document).on('click', '.btn-print', function() {
        window.print();
    });
});
