<?php
require_once 'db.php';
require_once 'config.php';

// Initialize database connection
$pdo = getDBConnection();

// Handle form submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $settings = [
        'company_name' => trim($_POST['company_name']),
        'company_address' => trim($_POST['company_address']),
        'company_email' => trim($_POST['company_email']),
        'company_phone' => trim($_POST['company_phone']),
        'default_tax_rate' => floatval($_POST['default_tax_rate']),
        'default_currency' => trim($_POST['default_currency']),
        'invoice_prefix' => trim($_POST['invoice_prefix']),
        'smtp_host' => trim($_POST['smtp_host']),
        'smtp_port' => intval($_POST['smtp_port']),
        'smtp_username' => trim($_POST['smtp_username']),
        'smtp_password' => trim($_POST['smtp_password']),
        'smtp_secure' => trim($_POST['smtp_secure']),
        'from_email' => trim($_POST['from_email']),
        'from_name' => trim($_POST['from_name']),
        'invoice_footer' => trim($_POST['invoice_footer'])
    ];
    
    // Handle logo upload
    $logo_path = '';
    if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml'];
        $file_type = $_FILES['company_logo']['type'];
        
        if (in_array($file_type, $allowed_types)) {
            $file_extension = pathinfo($_FILES['company_logo']['name'], PATHINFO_EXTENSION);
            $new_filename = 'company_logo_' . time() . '.' . $file_extension;
            $upload_path = 'uploads/' . $new_filename;
            
            if (move_uploaded_file($_FILES['company_logo']['tmp_name'], $upload_path)) {
                $logo_path = $upload_path;
                $settings['company_logo'] = $logo_path;
                
                // Delete old logo if exists
                $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
                $stmt->execute(['company_logo']);
                $old_logo = $stmt->fetchColumn();
                if ($old_logo && file_exists($old_logo)) {
                    unlink($old_logo);
                }
            }
        } else {
            $message = 'Invalid file type. Please upload JPG, PNG, GIF, or SVG images only.';
            $message_type = 'error';
        }
    } elseif (isset($_POST['remove_logo']) && $_POST['remove_logo'] == '1') {
        // Remove logo
        $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
        $stmt->execute(['company_logo']);
        $old_logo = $stmt->fetchColumn();
        if ($old_logo && file_exists($old_logo)) {
            unlink($old_logo);
        }
        $settings['company_logo'] = '';
    }
    
    if (empty($message)) {
        try {
            foreach ($settings as $key => $value) {
                $stmt = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?');
                $stmt->execute([$key, $value, $value]);
            }
            
            // Update config.php file with safe constant definitions
            $config_content = "<?php\n";
            $config_content .= "// Database configuration\n";
            $config_content .= "if (!defined('DB_HOST')) define('DB_HOST', '" . DB_HOST . "');\n";
            $config_content .= "if (!defined('DB_NAME')) define('DB_NAME', '" . DB_NAME . "');\n";
            $config_content .= "if (!defined('DB_USER')) define('DB_USER', '" . DB_USER . "');\n";
            $config_content .= "if (!defined('DB_PASS')) define('DB_PASS', '" . DB_PASS . "');\n\n";
            
            $config_content .= "// Company Information\n";
            $config_content .= "if (!defined('COMPANY_NAME')) define('COMPANY_NAME', '" . addslashes($settings['company_name']) . "');\n";
            $config_content .= "if (!defined('COMPANY_ADDRESS')) define('COMPANY_ADDRESS', '" . addslashes($settings['company_address']) . "');\n";
            $config_content .= "if (!defined('COMPANY_EMAIL')) define('COMPANY_EMAIL', '" . addslashes($settings['company_email']) . "');\n";
            $config_content .= "if (!defined('COMPANY_PHONE')) define('COMPANY_PHONE', '" . addslashes($settings['company_phone']) . "');\n";
            if (!empty($settings['company_logo'])) {
                $config_content .= "if (!defined('COMPANY_LOGO')) define('COMPANY_LOGO', '" . addslashes($settings['company_logo']) . "');\n";
            }
            $config_content .= "\n";
            
            $config_content .= "// Application Settings\n";
            $config_content .= "if (!defined('APP_NAME')) define('APP_NAME', 'GeekMobile Invoice System');\n";
            $config_content .= "if (!defined('DEFAULT_TAX_RATE')) define('DEFAULT_TAX_RATE', " . $settings['default_tax_rate'] . ");\n";
            $config_content .= "if (!defined('DEFAULT_CURRENCY')) define('DEFAULT_CURRENCY', '" . $settings['default_currency'] . "');\n";
            $config_content .= "if (!defined('CURRENCY_SYMBOL')) define('CURRENCY_SYMBOL', '$');\n\n";
            
            $config_content .= "// Email Settings (SMTP)\n";
            $config_content .= "if (!defined('SMTP_HOST')) define('SMTP_HOST', '" . addslashes($settings['smtp_host']) . "');\n";
            $config_content .= "if (!defined('SMTP_USER')) define('SMTP_USER', '" . addslashes($settings['smtp_username']) . "');\n";
            $config_content .= "if (!defined('SMTP_PASS')) define('SMTP_PASS', '" . addslashes($settings['smtp_password']) . "');\n";
            $config_content .= "if (!defined('SMTP_AUTH')) define('SMTP_AUTH', true);\n";
            $config_content .= "if (!defined('SMTP_SECURE')) define('SMTP_SECURE', '" . $settings['smtp_secure'] . "');\n";
            $config_content .= "if (!defined('SMTP_PORT')) define('SMTP_PORT', " . $settings['smtp_port'] . ");\n";
            $config_content .= "if (!defined('SMTP_USERNAME')) define('SMTP_USERNAME', '" . addslashes($settings['smtp_username']) . "');\n";
            $config_content .= "if (!defined('SMTP_PASSWORD')) define('SMTP_PASSWORD', '" . addslashes($settings['smtp_password']) . "');\n";
            $config_content .= "if (!defined('FROM_EMAIL')) define('FROM_EMAIL', '" . addslashes($settings['from_email']) . "');\n";
            $config_content .= "if (!defined('FROM_NAME')) define('FROM_NAME', '" . addslashes($settings['from_name']) . "');\n";
            $config_content .= "?>";
            
            file_put_contents('config.php', $config_content);
            
            $message = 'Settings updated successfully! Please refresh the page to see changes.';
            $message_type = 'success';
        } catch (Exception $e) {
            $message = 'Error updating settings: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Fetch current settings from database
try {
    $stmt = $pdo->query('SELECT setting_key, setting_value FROM settings');
    $db_settings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $db_settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    $db_settings = [];
}

// Use constants as fallback
$current_settings = [
    'company_name' => $db_settings['company_name'] ?? (defined('COMPANY_NAME') ? COMPANY_NAME : 'GeekMobile IT Services'),
    'company_address' => $db_settings['company_address'] ?? (defined('COMPANY_ADDRESS') ? COMPANY_ADDRESS : '123 Tech Street, Silicon Valley, CA 94000'),
    'company_email' => $db_settings['company_email'] ?? (defined('COMPANY_EMAIL') ? COMPANY_EMAIL : 'billing@geekmobile.com'),
    'company_phone' => $db_settings['company_phone'] ?? (defined('COMPANY_PHONE') ? COMPANY_PHONE : '+1 (555) 123-4567'),
    'company_logo' => $db_settings['company_logo'] ?? (defined('COMPANY_LOGO') ? COMPANY_LOGO : ''),
    'default_tax_rate' => $db_settings['default_tax_rate'] ?? (defined('DEFAULT_TAX_RATE') ? DEFAULT_TAX_RATE * 100 : 10),
    'default_currency' => $db_settings['default_currency'] ?? (defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : 'USD'),
    'invoice_prefix' => $db_settings['invoice_prefix'] ?? 'INV-',
    'smtp_host' => $db_settings['smtp_host'] ?? (defined('SMTP_HOST') ? SMTP_HOST : 'smtp.gmail.com'),
    'smtp_port' => $db_settings['smtp_port'] ?? (defined('SMTP_PORT') ? SMTP_PORT : 587),
    'smtp_username' => $db_settings['smtp_username'] ?? (defined('SMTP_USERNAME') ? SMTP_USERNAME : ''),
    'smtp_password' => $db_settings['smtp_password'] ?? (defined('SMTP_PASSWORD') ? SMTP_PASSWORD : ''),
    'smtp_secure' => $db_settings['smtp_secure'] ?? (defined('SMTP_SECURE') ? SMTP_SECURE : 'tls'),
    'from_email' => $db_settings['from_email'] ?? (defined('FROM_EMAIL') ? FROM_EMAIL : 'noreply@geekmobile.com'),
    'from_name' => $db_settings['from_name'] ?? (defined('FROM_NAME') ? FROM_NAME : 'GeekMobile Invoices'),
    'invoice_footer' => $db_settings['invoice_footer'] ?? ''
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - <?php echo APP_NAME; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .header h1 {
            color: #333;
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .header p {
            color: #666;
            font-size: 14px;
        }
        
        .message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .message.success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }
        
        .message.error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 20px;
        }
        
        .card h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 24px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            color: #555;
            font-weight: 500;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .form-group small {
            color: #666;
            font-size: 12px;
            margin-top: 4px;
        }
        
        .logo-upload {
            border: 2px dashed #d1d5db;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            background: #f9fafb;
        }
        
        .logo-preview {
            margin-bottom: 15px;
        }
        
        .logo-preview img {
            max-width: 200px;
            max-height: 100px;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            padding: 10px;
            background: white;
        }
        
        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
        }
        
        .file-input-wrapper input[type=file] {
            position: absolute;
            left: -9999px;
        }
        
        .file-input-label {
            display: inline-block;
            padding: 10px 20px;
            background: #667eea;
            color: white;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .file-input-label:hover {
            background: #5568d3;
        }
        
        .remove-logo-btn {
            display: inline-block;
            padding: 8px 16px;
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            margin-left: 10px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196F3;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .info-box h3 {
            color: #1565C0;
            margin-bottom: 8px;
            font-size: 16px;
        }
        
        .info-box p {
            color: #424242;
            font-size: 14px;
            line-height: 1.5;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>⚙️ System Settings</h1>
            <p>Customize your company information and system preferences</p>
        </div>

        <!-- Message -->
        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php if ($message_type === 'success'): ?>✓<?php else: ?>✗<?php endif; ?>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">
            <!-- Company Information -->
            <div class="card">
                <h2>Company Information</h2>
                <div class="info-box">
                    <h3>📋 About This Section</h3>
                    <p>This information will appear on all your invoices, emails, and PDF documents. Make sure it's accurate and professional.</p>
                </div>
                
                <!-- Logo Upload -->
                <div class="form-group">
                    <label>Company Logo</label>
                    <div class="logo-upload">
                        <?php if (!empty($current_settings['company_logo']) && file_exists($current_settings['company_logo'])): ?>
                            <div class="logo-preview">
                                <img src="<?php echo htmlspecialchars($current_settings['company_logo']); ?>" alt="Company Logo">
                            </div>
                            <input type="hidden" name="remove_logo" value="0" id="remove_logo_input">
                            <button type="button" class="remove-logo-btn" onclick="removeLogo()">🗑️ Remove Logo</button>
                        <?php else: ?>
                            <p style="color: #666; margin-bottom: 10px;">📷 Upload your company logo</p>
                        <?php endif; ?>
                        <div class="file-input-wrapper">
                            <label for="company_logo" class="file-input-label">
                                📁 Choose Logo File
                            </label>
                            <input type="file" name="company_logo" id="company_logo" accept="image/*" onchange="previewLogo(this)">
                        </div>
                        <small style="display: block; margin-top: 10px;">Supported formats: JPG, PNG, GIF, SVG (Max 2MB)</small>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Company Name *</label>
                        <input type="text" name="company_name" value="<?php echo htmlspecialchars($current_settings['company_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Company Email *</label>
                        <input type="email" name="company_email" value="<?php echo htmlspecialchars($current_settings['company_email']); ?>" required>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Company Phone</label>
                        <input type="tel" name="company_phone" value="<?php echo htmlspecialchars($current_settings['company_phone']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Company Address</label>
                        <input type="text" name="company_address" value="<?php echo htmlspecialchars($current_settings['company_address']); ?>">
                        <small>Full address including city, state, and ZIP code</small>
                    </div>
                </div>
            </div>

            <!-- Invoice Settings -->
            <div class="card">
                <h2>Invoice Settings</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Default Tax Rate (%)</label>
                        <input type="number" step="0.01" name="default_tax_rate" value="<?php echo htmlspecialchars($current_settings['default_tax_rate']); ?>">
                        <small>Default tax percentage applied to invoices</small>
                    </div>
                    <div class="form-group">
                        <label>Default Currency</label>
                        <select name="default_currency">
                            <option value="NZD" <?php echo $current_settings['default_currency'] == 'NZD' ? 'selected' : ''; ?>>NZD - NZ Dollar</option>
                            <option value="EUR" <?php echo $current_settings['default_currency'] == 'EUR' ? 'selected' : ''; ?>>EUR - Euro</option>
                            <option value="GBP" <?php echo $current_settings['default_currency'] == 'GBP' ? 'selected' : ''; ?>>GBP - British Pound</option>
                            <option value="CAD" <?php echo $current_settings['default_currency'] == 'CAD' ? 'selected' : ''; ?>>CAD - Canadian Dollar</option>
                            <option value="AUD" <?php echo $current_settings['default_currency'] == 'AUD' ? 'selected' : ''; ?>>AUD - Australian Dollar</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Invoice Number Prefix</label>
                    <input type="text" name="invoice_prefix" value="<?php echo htmlspecialchars($current_settings['invoice_prefix']); ?>">
                    <small>Prefix for invoice numbers (e.g., INV-, GM-, etc.)</small>
                </div>

                <div class="form-group">
                    <label>Invoice Footer Text</label>
                    <textarea name="invoice_footer" rows="4" placeholder="Enter footer text that will appear at the bottom of invoices (e.g., GST number, payment details, terms, etc.)"><?php echo htmlspecialchars($current_settings['invoice_footer']); ?></textarea>
                    <small>Footer information that appears at the bottom of all invoices and emails</small>
                </div>
            </div>

            <!-- Email Settings -->
            <div class="card">
                <h2>Email Settings (SMTP)</h2>
                <div class="info-box">
                    <h3>📧 SMTP Configuration</h3>
                    <p>Configure your SMTP settings to send invoices via email. For Gmail, use smtp.gmail.com with port 587 (TLS) or 465 (SSL). You may need to enable "Less secure app access" or use an App Password.</p>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>SMTP Host</label>
                        <input type="text" name="smtp_host" value="<?php echo htmlspecialchars($current_settings['smtp_host']); ?>" placeholder="smtp.gmail.com">
                    </div>
                    <div class="form-group">
                        <label>SMTP Port</label>
                        <input type="number" name="smtp_port" value="<?php echo htmlspecialchars($current_settings['smtp_port']); ?>" placeholder="587">
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>SMTP Username</label>
                        <input type="text" name="smtp_username" value="<?php echo htmlspecialchars($current_settings['smtp_username']); ?>" placeholder="your-email@gmail.com">
                    </div>
                    <div class="form-group">
                        <label>SMTP Password</label>
                        <input type="password" name="smtp_password" value="<?php echo htmlspecialchars($current_settings['smtp_password']); ?>" placeholder="Your password or app password">
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>SMTP Security</label>
                        <select name="smtp_secure">
                            <option value="tls" <?php echo $current_settings['smtp_secure'] == 'tls' ? 'selected' : ''; ?>>TLS (Port 587)</option>
                            <option value="ssl" <?php echo $current_settings['smtp_secure'] == 'ssl' ? 'selected' : ''; ?>>SSL (Port 465)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>From Email</label>
                        <input type="email" name="from_email" value="<?php echo htmlspecialchars($current_settings['from_email']); ?>" placeholder="noreply@yourcompany.com">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>From Name</label>
                    <input type="text" name="from_name" value="<?php echo htmlspecialchars($current_settings['from_name']); ?>" placeholder="Your Company Name">
                    <small>Name that appears in the "From" field of emails</small>
                </div>
            </div>

            <!-- Action Buttons -->
            <div style="display: flex; gap: 10px;">
                <button type="submit" name="update_settings" class="btn btn-primary">💾 Save Settings</button>
                <a href="index.php" class="btn btn-secondary">← Back to Dashboard</a>
            </div>
        </form>
    </div>

    <script>
        function previewLogo(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.querySelector('.logo-preview');
                    if (!preview) {
                        const uploadDiv = document.querySelector('.logo-upload');
                        const previewDiv = document.createElement('div');
                        previewDiv.className = 'logo-preview';
                        previewDiv.innerHTML = '<img src="' + e.target.result + '" alt="Logo Preview">';
                        uploadDiv.insertBefore(previewDiv, uploadDiv.firstChild);
                    } else {
                        preview.querySelector('img').src = e.target.result;
                    }
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        function removeLogo() {
            if (confirm('Are you sure you want to remove the logo?')) {
                document.getElementById('remove_logo_input').value = '1';
                document.querySelector('form').submit();
            }
        }
    </script>
</body>
</html>
