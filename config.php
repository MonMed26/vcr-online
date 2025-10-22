<?php
/**
 * Configuration File for WiFi Voucher System
 * Contains database, MikroTik API, and QRIS Payment Gateway settings
 */

// Error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Base configuration
define('APP_NAME', 'WiFi Voucher System');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'https://yourdomain.com'); // Update with your domain
define('APP_ENV', 'development'); // development or production

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'wifi_voucher_system');
define('DB_USER', 'your_db_username'); // Update with your database username
define('DB_PASS', 'your_db_password'); // Update with your database password
define('DB_CHARSET', 'utf8mb4');

// MikroTik API Configuration
define('MIKROTIK_HOST', '192.168.1.1'); // Update with your MikroTik IP
define('MIKROTIK_PORT', 8728);
define('MIKROTIK_USERNAME', 'admin'); // Update with your MikroTik username
define('MIKROTIK_PASSWORD', 'your_mikrotik_password'); // Update with your MikroTik password
define('MIKROTIK_TIMEOUT', 10); // seconds

// QRIS Payment Gateway Configuration
define('QrisGateway', [
    'api_url' => 'https://your-payment-gateway.com/api', // Update with your gateway URL
    'api_key' => 'your_payment_gateway_api_key', // Update with your API key
    'merchant_id' => 'your_merchant_id', // Update with your merchant ID
    'webhook_secret' => 'your_webhook_secret_key', // Update with your webhook secret
    'timeout' => 30, // seconds
    'expiry_minutes' => 30 // QR code expiry time in minutes
]);

// Security Configuration
define('JWT_SECRET', 'your_jwt_secret_key_here'); // Update with secure random string
define('ENCRYPTION_KEY', 'your_32_character_encryption_key'); // Update with secure key
define('HASH_ALGO', 'sha256');

// Session Configuration
define('SESSION_LIFETIME', 7200); // 2 hours in seconds
define('COOKIE_SECURE', false); // Set to true for HTTPS
define('COOKIE_HTTPONLY', true);

// Rate Limiting
define('RATE_LIMIT_REQUESTS', 10); // requests per minute
define('RATE_LIMIT_WINDOW', 60); // seconds

// Logging Configuration
define('LOG_ENABLED', true);
define('LOG_FILE', __DIR__ . '/logs/app.log');
define('LOG_MAX_SIZE', 10485760); // 10MB
define('LOG_FILES_TO_KEEP', 5);

// Email Configuration (for notifications)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your_email@gmail.com');
define('SMTP_PASSWORD', 'your_email_password');
define('SMTP_FROM_EMAIL', 'noreply@yourdomain.com');
define('SMTP_FROM_NAME', APP_NAME);

// Voucher Configuration
define('VOUCHER_USERNAME_PREFIX', 'user');
define('VOUCHER_PASSWORD_LENGTH', 8);
define('VOUCHER_USERNAME_LENGTH', 10);
define('TRANSACTION_ID_PREFIX', 'TRX');

// Helper functions
if (!function_exists('config')) {
    /**
     * Get configuration value
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function config($key, $default = null) {
        static $config = null;

        if ($config === null) {
            $config = [
                'database' => [
                    'host' => DB_HOST,
                    'name' => DB_NAME,
                    'user' => DB_USER,
                    'pass' => DB_PASS,
                    'charset' => DB_CHARSET
                ],
                'mikrotik' => [
                    'host' => MIKROTIK_HOST,
                    'port' => MIKROTIK_PORT,
                    'username' => MIKROTIK_USERNAME,
                    'password' => MIKROTIK_PASSWORD,
                    'timeout' => MIKROTIK_TIMEOUT
                ],
                'qris' => QrisGateway,
                'security' => [
                    'jwt_secret' => JWT_SECRET,
                    'encryption_key' => ENCRYPTION_KEY,
                    'hash_algo' => HASH_ALGO
                ],
                'app' => [
                    'name' => APP_NAME,
                    'version' => APP_VERSION,
                    'base_url' => BASE_URL,
                    'env' => APP_ENV
                ]
            ];
        }

        $keys = explode('.', $key);
        $value = $config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }
}

if (!function_exists('is_production')) {
    /**
     * Check if application is in production mode
     * @return bool
     */
    function is_production() {
        return APP_ENV === 'production';
    }
}

if (!function_exists('generate_webhook_signature')) {
    /**
     * Generate webhook signature for QRIS gateway
     * @param array $payload
     * @return string
     */
    function generate_webhook_signature($payload) {
        return hash_hmac(HASH_ALGO, json_encode($payload), QrisGateway['webhook_secret']);
    }
}

if (!function_exists('verify_webhook_signature')) {
    /**
     * Verify webhook signature from QRIS gateway
     * @param array $payload
     * @param string $signature
     * @return bool
     */
    function verify_webhook_signature($payload, $signature) {
        $expected_signature = generate_webhook_signature($payload);
        return hash_equals($expected_signature, $signature);
    }
}

// Auto-load helper classes
spl_autoload_register(function ($class) {
    // Convert namespace to file path
    $file = __DIR__ . '/lib/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Include required files
require_once __DIR__ . '/lib/Database.php';
require_once __DIR__ . '/lib/MikroTik.php';
require_once __DIR__ . '/lib/PaymentGateway.php';
require_once __DIR__ . '/lib/Logger.php';
require_once __DIR__ . '/lib/Validator.php';

// Set default timezone
date_default_timezone_set('Asia/Jakarta');

// Security headers
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');

    if (is_production()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

?>