<?php
/**
 * Configuration Template
 * 
 * IMPORTANT: Copy this file to config.php and update with your actual values
 * Never commit config.php to version control - it contains sensitive credentials
 */

// CRITICAL FIX: Force correct CURRENCY_SYMBOL value
// Some PHP extension is pre-defining CURRENCY_SYMBOL as 262145
// We need to use runkit to redefine it, or use a workaround
if (defined('CURRENCY_SYMBOL')) {
    // Store the correct value in a different constant
    if (!defined('INVOICE_CURRENCY')) define('INVOICE_CURRENCY', '$');
    // Try to undefine if runkit is available
    if (function_exists('runkit_constant_redefine')) {
        runkit_constant_redefine('CURRENCY_SYMBOL', '$');
    } else {
        // Workaround: redefine using a wrapper constant
        define('CURRENCY_SYMBOL_OVERRIDE', '$');
    }
} else {
    define('CURRENCY_SYMBOL', '$');
}

// Database configuration
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'your_database_name');
if (!defined('DB_USER')) define('DB_USER', 'your_database_user');
if (!defined('DB_PASS')) define('DB_PASS', 'your_database_password');

// Company Information
if (!defined('COMPANY_NAME')) define('COMPANY_NAME', 'Your Company Name');
if (!defined('COMPANY_ADDRESS')) define('COMPANY_ADDRESS', 'Your Company Address');
if (!defined('COMPANY_EMAIL')) define('COMPANY_EMAIL', 'your-email@example.com');
if (!defined('COMPANY_PHONE')) define('COMPANY_PHONE', 'Your Phone Number');
if (!defined('COMPANY_LOGO')) define('COMPANY_LOGO', 'uploads/company_logo.png');

// Application Settings
if (!defined('APP_NAME')) define('APP_NAME', 'Invoice System');
if (!defined('DEFAULT_TAX_RATE')) define('DEFAULT_TAX_RATE', 15);
if (!defined('DEFAULT_CURRENCY')) define('DEFAULT_CURRENCY', 'USD');

// Helper function to get the correct currency symbol
if (!function_exists('get_currency_symbol')) {
    function get_currency_symbol() {
        if (defined('CURRENCY_SYMBOL_OVERRIDE')) {
            return CURRENCY_SYMBOL_OVERRIDE;
        }
        if (defined('INVOICE_CURRENCY')) {
            return INVOICE_CURRENCY;
        }
        if (defined('CURRENCY_SYMBOL') && CURRENCY_SYMBOL === '$') {
            return CURRENCY_SYMBOL;
        }
        return '$'; // Fallback
    }
}

// Email Settings (SMTP)
// IMPORTANT: Replace these with your actual SMTP credentials
if (!defined('SMTP_HOST')) define('SMTP_HOST', 'smtp.example.com');
if (!defined('SMTP_USER')) define('SMTP_USER', 'your-email@example.com');
if (!defined('SMTP_PASS')) define('SMTP_PASS', 'your-smtp-password');
if (!defined('SMTP_AUTH')) define('SMTP_AUTH', true);
if (!defined('SMTP_SECURE')) define('SMTP_SECURE', 'tls');
if (!defined('SMTP_PORT')) define('SMTP_PORT', 587);
if (!defined('SMTP_USERNAME')) define('SMTP_USERNAME', 'your-email@example.com');
if (!defined('SMTP_PASSWORD')) define('SMTP_PASSWORD', 'your-smtp-password');
if (!defined('FROM_EMAIL')) define('FROM_EMAIL', 'noreply@example.com');
if (!defined('FROM_NAME')) define('FROM_NAME', 'Your Company Invoices');
?>
