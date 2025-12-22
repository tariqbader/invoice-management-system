<?php
// Database configuration
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'geekmobile_invoice');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');

// Company Information
if (!defined('COMPANY_NAME')) define('COMPANY_NAME', 'GeekMobile IT Services');
if (!defined('COMPANY_ADDRESS')) define('COMPANY_ADDRESS', '46 Columbia Crescent Beachlands');
if (!defined('COMPANY_EMAIL')) define('COMPANY_EMAIL', 'badert@gmail.com');
if (!defined('COMPANY_PHONE')) define('COMPANY_PHONE', '021511145');

// Application Settings
if (!defined('APP_NAME')) define('APP_NAME', 'GeekMobile Invoice System');
if (!defined('DEFAULT_TAX_RATE')) define('DEFAULT_TAX_RATE', 15);
if (!defined('DEFAULT_CURRENCY')) define('DEFAULT_CURRENCY', 'NZD');
if (!defined('CURRENCY_SYMBOL')) define('CURRENCY_SYMBOL', '$');

// Email Settings (SMTP)
if (!defined('SMTP_HOST')) define('SMTP_HOST', 'smtp.example.com');
if (!defined('SMTP_USER')) define('SMTP_USER', 'your-email@example.com');
if (!defined('SMTP_PASS')) define('SMTP_PASS', 'your-password');
if (!defined('SMTP_AUTH')) define('SMTP_AUTH', true);
if (!defined('SMTP_SECURE')) define('SMTP_SECURE', 'tls');
if (!defined('SMTP_PORT')) define('SMTP_PORT', 587);
if (!defined('SMTP_USERNAME')) define('SMTP_USERNAME', 'your-email@example.com');
if (!defined('SMTP_PASSWORD')) define('SMTP_PASSWORD', 'your-password');
if (!defined('FROM_EMAIL')) define('FROM_EMAIL', 'noreply@geekmobile.com');
if (!defined('FROM_NAME')) define('FROM_NAME', 'GeekMobile Invoices');
?>
