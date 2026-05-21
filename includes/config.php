<?php
// ============================================================
// Luna Glow — Application Configuration
// ============================================================

define('APP_NAME', 'Luna Glow');
define('APP_TAGLINE', 'Premium Beauty Boutique');
define('APP_URL', 'http://localhost/xampp/Project/LunaGlow');
define('APP_VERSION', '1.0.0');

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'lunaglow');
define('DB_CHARSET', 'utf8mb4');

// Currency
define('CURRENCY_SYMBOL', '&#8369;');
define('CURRENCY_CODE', 'PHP');

// Shipping
define('FREE_SHIPPING_THRESHOLD', 2000);
define('SHIPPING_FEE', 150);
define('STANDARD_DELIVERY_DAYS', '3-5 business days');

// Admin credentials (set via setup.php)
define('DEFAULT_ADMIN_EMAIL', 'admin@lunaglow.com');

// Session
define('SESSION_LIFETIME', 86400); // 24 hours

// Pagination
define('PRODUCTS_PER_PAGE', 12);
define('ADMIN_PER_PAGE', 15);

// File uploads
define('UPLOAD_PATH', __DIR__ . '/../assets/images/uploads/');
define('UPLOAD_URL', APP_URL . '/assets/images/uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/webp']);

// Social Media
define('SOCIAL_INSTAGRAM', '#');
define('SOCIAL_FACEBOOK', '#');
define('SOCIAL_TIKTOK', '#');
define('SOCIAL_YOUTUBE', '#');

// Contact
define('CONTACT_EMAIL', 'hello@lunaglow.ph');
define('CONTACT_PHONE', '+63 917 123 4567');
define('CONTACT_ADDRESS', 'Manila, Philippines');

// Error reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
