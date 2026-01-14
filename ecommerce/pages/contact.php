<?php
/**
 * ============================================
 * CONTACT US PAGE
 * ============================================
 *
 * Contact form and company contact information.
 *
 * ============================================
 */

require_once __DIR__ . '/../config/init.php';

$pageTitle = 'Contact Us';
$success = false;
$errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken($_POST['csrf_token'] ?? '')) {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitizeEmail($_POST['email'] ?? '');
    $subject = sanitize($_POST['subject'] ?? '');
    $message = sanitize($_POST['message'] ?? '');

    // Validation
    if (empty($name)) {
        $errors[] = 'Name is required.';
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required.';
    }
    if (empty($subject)) {
        $errors[] = 'Subject is required.';
    }
    if (empty($message)) {
        $errors[] = 'Message is required.';
    }

    if (empty($errors)) {
        // In a real application, you would:
        // 1. Send an email to the admin
        // 2. Store the message in a database
        // 3. Send a confirmation email to the user

        // Log the contact form submission
        logActivity('contact_form', "Contact form submitted by {$name} ({$email})", 'contact');

        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> | <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <!-- Hero Section -->
    <section class="bg-primary text-white py-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold">Contact Us</h1>
                    <p class="lead">Have questions? We'd love to hear from you. Send us a message!</p>
                </div>
                <div class="col-lg-6 text-center">
                    <i class="bi bi-envelope-paper display-1"></i>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Content -->
    <section class="py-5">
        <div class="container">
            <div class="row g-5">
                <!-- Contact Form -->
                <div class="col-lg-8">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white">
                            <h4 class="mb-0"><i class="bi bi-send me-2"></i>Send us a Message</h4>
                        </div>
                        <div class="card-body p-4">
                            <?php if ($success): ?>
                                <div class="alert alert-success">
                                    <i class="bi bi-check-circle me-2"></i>
                                    <strong>Thank you!</strong> Your message has been sent successfully.
                                    We'll get back to you as soon as possible.
                                </div>
                            <?php else: ?>
                                <?php if (!empty($errors)): ?>
                                    <div class="alert alert-danger">
                                        <ul class="mb-0">
                                            <?php foreach ($errors as $error): ?>
                                                <li><?= $error ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>

                                <form method="POST" id="contactForm">
                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="name" class="form-label">Your Name *</label>
                                            <input type="text" class="form-control" id="name" name="name"
                                                   value="<?= sanitize($_POST['name'] ?? '') ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="email" class="form-label">Email Address *</label>
                                            <input type="email" class="form-control" id="email" name="email"
                                                   value="<?= sanitize($_POST['email'] ?? '') ?>" required>
                                        </div>
                                        <div class="col-12">
                                            <label for="subject" class="form-label">Subject *</label>
                                            <select class="form-select" id="subject" name="subject" required>
                                                <option value="">Select a subject...</option>
                                                <option value="General Inquiry" <?= ($_POST['subject'] ?? '') === 'General Inquiry' ? 'selected' : '' ?>>General Inquiry</option>
                                                <option value="Order Support" <?= ($_POST['subject'] ?? '') === 'Order Support' ? 'selected' : '' ?>>Order Support</option>
                                                <option value="Product Question" <?= ($_POST['subject'] ?? '') === 'Product Question' ? 'selected' : '' ?>>Product Question</option>
                                                <option value="Returns & Refunds" <?= ($_POST['subject'] ?? '') === 'Returns & Refunds' ? 'selected' : '' ?>>Returns & Refunds</option>
                                                <option value="Technical Issue" <?= ($_POST['subject'] ?? '') === 'Technical Issue' ? 'selected' : '' ?>>Technical Issue</option>
                                                <option value="Feedback" <?= ($_POST['subject'] ?? '') === 'Feedback' ? 'selected' : '' ?>>Feedback</option>
                                                <option value="Other" <?= ($_POST['subject'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                                            </select>
                                        </div>
                                        <div class="col-12">
                                            <label for="message" class="form-label">Message *</label>
                                            <textarea class="form-control" id="message" name="message" rows="6"
                                                      placeholder="How can we help you?" required><?= sanitize($_POST['message'] ?? '') ?></textarea>
                                        </div>
                                        <div class="col-12">
                                            <button type="submit" class="btn btn-primary btn-lg">
                                                <i class="bi bi-send me-2"></i>Send Message
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Contact Info -->
                <div class="col-lg-4">
                    <!-- Contact Details -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Contact Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex mb-3">
                                <div class="bg-primary bg-opacity-10 rounded p-2 me-3">
                                    <i class="bi bi-geo-alt text-primary"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">Address</h6>
                                    <p class="text-muted mb-0 small">
                                        123 Commerce Street<br>
                                        Business District<br>
                                        New York, NY 10001
                                    </p>
                                </div>
                            </div>
                            <div class="d-flex mb-3">
                                <div class="bg-primary bg-opacity-10 rounded p-2 me-3">
                                    <i class="bi bi-envelope text-primary"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">Email</h6>
                                    <p class="text-muted mb-0 small">
                                        support@shopease.com<br>
                                        info@shopease.com
                                    </p>
                                </div>
                            </div>
                            <div class="d-flex mb-3">
                                <div class="bg-primary bg-opacity-10 rounded p-2 me-3">
                                    <i class="bi bi-telephone text-primary"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">Phone</h6>
                                    <p class="text-muted mb-0 small">
                                        +1 (555) 123-4567<br>
                                        +1 (555) 987-6543
                                    </p>
                                </div>
                            </div>
                            <div class="d-flex">
                                <div class="bg-primary bg-opacity-10 rounded p-2 me-3">
                                    <i class="bi bi-clock text-primary"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">Business Hours</h6>
                                    <p class="text-muted mb-0 small">
                                        Mon - Fri: 9:00 AM - 6:00 PM<br>
                                        Sat - Sun: 10:00 AM - 4:00 PM
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Social Links -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="bi bi-share me-2"></i>Follow Us</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex gap-2">
                                <a href="#" class="btn btn-outline-primary">
                                    <i class="bi bi-facebook"></i>
                                </a>
                                <a href="#" class="btn btn-outline-info">
                                    <i class="bi bi-twitter"></i>
                                </a>
                                <a href="#" class="btn btn-outline-danger">
                                    <i class="bi bi-instagram"></i>
                                </a>
                                <a href="#" class="btn btn-outline-primary">
                                    <i class="bi bi-linkedin"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- FAQ Teaser -->
                    <div class="card shadow-sm bg-light border-0">
                        <div class="card-body text-center p-4">
                            <i class="bi bi-question-circle display-4 text-primary mb-3"></i>
                            <h5>Have Questions?</h5>
                            <p class="text-muted small mb-3">
                                Check our FAQ section for quick answers to common questions.
                            </p>
                            <a href="#" class="btn btn-outline-primary btn-sm">
                                View FAQ
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Map Placeholder -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-4">
                <h3>Find Us</h3>
                <p class="text-muted">Visit our store or office</p>
            </div>
            <div class="bg-secondary bg-opacity-25 rounded d-flex align-items-center justify-content-center" style="height: 300px;">
                <div class="text-center">
                    <i class="bi bi-map display-3 text-muted"></i>
                    <p class="text-muted mt-2">Map integration would go here</p>
                    <small class="text-muted">(Google Maps API or similar)</small>
                </div>
            </div>
        </div>
    </section>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="<?= BASE_URL ?>/js/app.js"></script>
</body>
</html>
