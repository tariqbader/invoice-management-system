<?php
/**
 * GeekMobile Invoice System - Complete Installation Script
 * This script will set up the entire system including database, tables, and default data
 */

session_start();

// Installation steps
$steps = [
    1 => 'Database Connection',
    2 => 'Create Tables',
    3 => 'Company Information',
    4 => 'Admin Account',
    5 => 'Create Directories',
    6 => 'Configuration File',
    7 => 'Complete'
];

$current_step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$errors = [];
$success_messages = [];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($current_step === 1) {
        // Step 1: Database Connection
        $_SESSION['db_host'] = trim($_POST['db_host']);
        $_SESSION['db_name'] = trim($_POST['db_name']);
        $_SESSION['db_user'] = trim($_POST['db_user']);
        $_SESSION['db_pass'] = trim($_POST['db_pass']);
        
        // Test connection
        try {
            $pdo = new PDO(
                'mysql:host=' . $_SESSION['db_host'],
                $_SESSION['db_user'],
                $_SESSION['db_pass']
            );
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Create database if it doesn't exist
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . $_SESSION['db_name'] . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `" . $_SESSION['db_name'] . "`");
            
            $success_messages[] = 'Database connection successful!';
            $current_step = 2;
        } catch (PDOException $e) {
            $errors[] = 'Database connection failed: ' . $e->getMessage();
        }
    } elseif ($current_step === 2) {
        // Step 2: Create Tables
        try {
            $pdo = new PDO(
                'mysql:host=' . $_SESSION['db_host'] . ';dbname=' . $_SESSION['db_name'],
                $_SESSION['db_user'],
                $_SESSION['db_pass']
            );
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Check if complete schema exists, otherwise fall back to individual files
            $schema_file = file_exists('sql/schema_complete.sql') ? 'sql/schema_complete.sql' : 'sql/schema.sql';
            
            if ($schema_file === 'sql/schema_complete.sql') {
                // Use the complete schema file (recommended)
                $schema = file_get_contents($schema_file);
                $statements = array_filter(
                    array_map('trim', explode(';', $schema)),
                    function($stmt) { 
                        $stmt = trim($stmt);
                        return !empty($stmt) && 
                               strpos($stmt, '--') !== 0 && 
                               stripos($stmt, 'CREATE OR REPLACE VIEW') === false &&
                               stripos($stmt, 'SELECT ') !== 0;
                    }
                );
                
                foreach ($statements as $statement) {
                    if (!empty(trim($statement))) {
                        try {
                            $pdo->exec($statement);
                        } catch (PDOException $e) {
                            // Ignore duplicate entry errors for default data
                            if (strpos($e->getMessage(), 'Duplicate entry') === false && 
                                strpos($e->getMessage(), 'Duplicate column') === false) {
                                throw $e;
                            }
                        }
                    }
                }
                
                // Create the view separately (views need special handling)
                try {
                    $view_sql = "CREATE OR REPLACE VIEW invoice_summary AS
                    SELECT 
                        i.id,
                        i.invoice_number,
                        i.invoice_date,
                        i.due_date,
                        i.total,
                        i.status,
                        c.name AS client_name,
                        c.email AS client_email,
                        COALESCE(SUM(p.amount), 0) AS paid_amount,
                        (i.total - COALESCE(SUM(p.amount), 0)) AS balance_due,
                        CASE 
                            WHEN i.status = 'paid' THEN 'Paid'
                            WHEN i.due_date < CURDATE() AND i.status != 'paid' THEN 'Overdue'
                            WHEN COALESCE(SUM(p.amount), 0) > 0 AND COALESCE(SUM(p.amount), 0) < i.total THEN 'Partially Paid'
                            ELSE 'Unpaid'
                        END AS payment_status
                    FROM invoices i
                    LEFT JOIN clients c ON i.client_id = c.id
                    LEFT JOIN payments p ON i.id = p.invoice_id
                    GROUP BY i.id, i.invoice_number, i.invoice_date, i.due_date, i.total, i.status, c.name, c.email";
                    $pdo->exec($view_sql);
                } catch (PDOException $e) {
                    // View creation is optional, continue if it fails
                }
                
                $success_messages[] = 'All database tables created successfully using complete schema!';
            } else {
                // Fall back to individual schema files (legacy method)
                // Read and execute main schema
                $schema = file_get_contents('sql/schema.sql');
                $statements = array_filter(
                    array_map('trim', explode(';', $schema)),
                    function($stmt) { return !empty($stmt) && strpos($stmt, '--') !== 0; }
                );
                
                foreach ($statements as $statement) {
                    if (!empty(trim($statement))) {
                        $pdo->exec($statement);
                    }
                }
                
                // Create services table
                if (file_exists('sql/services_table.sql')) {
                    $services_schema = file_get_contents('sql/services_table.sql');
                    $statements = array_filter(
                        array_map('trim', explode(';', $services_schema)),
                        function($stmt) { return !empty($stmt) && strpos($stmt, '--') !== 0; }
                    );
                    
                    foreach ($statements as $statement) {
                        if (!empty(trim($statement))) {
                            try {
                                $pdo->exec($statement);
                            } catch (PDOException $e) {
                                // Ignore duplicate entry errors for default data
                                if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                                    throw $e;
                                }
                            }
                        }
                    }
                }
                
                // Add repair_details column to invoice_items table
                if (file_exists('sql/add_repair_details.sql')) {
                    try {
                        $repair_details_schema = file_get_contents('sql/add_repair_details.sql');
                        $statements = array_filter(
                            array_map('trim', explode(';', $repair_details_schema)),
                            function($stmt) { return !empty($stmt) && strpos($stmt, '--') !== 0; }
                        );
                        
                        foreach ($statements as $statement) {
                            if (!empty(trim($statement))) {
                                try {
                                    $pdo->exec($statement);
                                } catch (PDOException $e) {
                                    // Ignore if column already exists
                                    if (strpos($e->getMessage(), 'Duplicate column') === false) {
                                        throw $e;
                                    }
                                }
                            }
                        }
                    } catch (Exception $e) {
                        // If file doesn't exist or other error, continue (column might already exist)
                    }
                }
                
                // Add invoice tracking columns for shareable links
                if (file_exists('sql/add_invoice_tracking.sql')) {
                    try {
                        $tracking_schema = file_get_contents('sql/add_invoice_tracking.sql');
                        $statements = array_filter(
                            array_map('trim', explode(';', $tracking_schema)),
                            function($stmt) { return !empty($stmt) && strpos($stmt, '--') !== 0; }
                        );
                        
                        foreach ($statements as $statement) {
                            if (!empty(trim($statement))) {
                                try {
                                    $pdo->exec($statement);
                                } catch (PDOException $e) {
                                    // Ignore if column already exists
                                    if (strpos($e->getMessage(), 'Duplicate column') === false) {
                                        throw $e;
                                    }
                                }
                            }
                        }
                    } catch (Exception $e) {
                        // If file doesn't exist or other error, continue (columns might already exist)
                    }
                }
                
                $success_messages[] = 'All database tables created successfully!';
            }
            
            $current_step = 3;
        } catch (Exception $e) {
            $errors[] = 'Error creating tables: ' . $e->getMessage();
        }
    } elseif ($current_step === 3) {
        // Step 3: Company Information
        $_SESSION['company_name'] = trim($_POST['company_name']);
        $_SESSION['company_address'] = trim($_POST['company_address']);
        $_SESSION['company_email'] = trim($_POST['company_email']);
        $_SESSION['company_phone'] = trim($_POST['company_phone']);
        $_SESSION['default_tax_rate'] = floatval($_POST['default_tax_rate']);
        
        try {
            $pdo = new PDO(
                'mysql:host=' . $_SESSION['db_host'] . ';dbname=' . $_SESSION['db_name'],
                $_SESSION['db_user'],
                $_SESSION['db_pass']
            );
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Insert company settings
            $settings = [
                'company_name' => $_SESSION['company_name'],
                'company_address' => $_SESSION['company_address'],
                'company_email' => $_SESSION['company_email'],
                'company_phone' => $_SESSION['company_phone'],
                'default_tax_rate' => $_SESSION['default_tax_rate'],
                'default_currency' => 'USD',
                'invoice_prefix' => 'INV-'
            ];
            
            foreach ($settings as $key => $value) {
                $stmt = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?');
                $stmt->execute([$key, $value, $value]);
            }
            
            $success_messages[] = 'Company information saved successfully!';
            $current_step = 4;
        } catch (Exception $e) {
            $errors[] = 'Error saving company information: ' . $e->getMessage();
        }
    } elseif ($current_step === 4) {
        // Step 4: Create Admin Account
        $_SESSION['admin_username'] = trim($_POST['admin_username']);
        $_SESSION['admin_email'] = trim($_POST['admin_email']);
        $_SESSION['admin_password'] = $_POST['admin_password'];
        $_SESSION['admin_fullname'] = trim($_POST['admin_fullname']);
        
        // Validate inputs
        if (empty($_SESSION['admin_username']) || empty($_SESSION['admin_email']) || empty($_SESSION['admin_password'])) {
            $errors[] = 'All admin fields are required.';
        } elseif (!filter_var($_SESSION['admin_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email address.';
        } elseif (strlen($_SESSION['admin_password']) < 6) {
            $errors[] = 'Password must be at least 6 characters long.';
        } else {
            try {
                $pdo = new PDO(
                    'mysql:host=' . $_SESSION['db_host'] . ';dbname=' . $_SESSION['db_name'],
                    $_SESSION['db_user'],
                    $_SESSION['db_pass']
                );
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Check if username or email already exists
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ? OR email = ?');
                $stmt->execute([$_SESSION['admin_username'], $_SESSION['admin_email']]);
                
                if ($stmt->fetchColumn() > 0) {
                    $errors[] = 'Username or email already exists.';
                } else {
                    // Create admin user
                    $hashed_password = password_hash($_SESSION['admin_password'], PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare('INSERT INTO users (username, email, password, full_name, role, is_active) VALUES (?, ?, ?, ?, ?, ?)');
                    $stmt->execute([
                        $_SESSION['admin_username'],
                        $_SESSION['admin_email'],
                        $hashed_password,
                        $_SESSION['admin_fullname'],
                        'admin',
                        1
                    ]);
                    
                    $success_messages[] = 'Admin account created successfully!';
                    $current_step = 5;
                }
            } catch (Exception $e) {
                $errors[] = 'Error creating admin account: ' . $e->getMessage();
            }
        }
    } elseif ($current_step === 5) {
        // Step 5: Create Directories
        $directories = ['uploads'];
        $created = [];
        
        foreach ($directories as $dir) {
            if (!file_exists($dir)) {
                if (mkdir($dir, 0755, true)) {
                    $created[] = $dir;
                }
            }
        }
        
        if (!empty($created)) {
            $success_messages[] = 'Created directories: ' . implode(', ', $created);
        }
        
        $current_step = 6;
    } elseif ($current_step === 6) {
        // Step 6: Create Configuration File
        try {
            $config_content = "<?php\n";
            $config_content .= "// Database configuration\n";
            $config_content .= "define('DB_HOST', '" . $_SESSION['db_host'] . "');\n";
            $config_content .= "define('DB_NAME', '" . $_SESSION['db_name'] . "');\n";
            $config_content .= "define('DB_USER', '" . $_SESSION['db_user'] . "');\n";
            $config_content .= "define('DB_PASS', '" . addslashes($_SESSION['db_pass']) . "');\n\n";
            
            $config_content .= "// Company Information\n";
            $config_content .= "define('COMPANY_NAME', '" . addslashes($_SESSION['company_name']) . "');\n";
            $config_content .= "define('COMPANY_ADDRESS', '" . addslashes($_SESSION['company_address']) . "');\n";
            $config_content .= "define('COMPANY_EMAIL', '" . addslashes($_SESSION['company_email']) . "');\n";
            $config_content .= "define('COMPANY_PHONE', '" . addslashes($_SESSION['company_phone']) . "');\n\n";
            
            $config_content .= "// Application Settings\n";
            $config_content .= "define('APP_NAME', 'GeekMobile Invoice System');\n";
            $config_content .= "define('DEFAULT_TAX_RATE', " . ($_SESSION['default_tax_rate'] / 100) . ");\n";
            $config_content .= "define('DEFAULT_CURRENCY', 'USD');\n";
            $config_content .= "if (!defined('CURRENCY_SYMBOL')) define('CURRENCY_SYMBOL', '$');\n\n";
            
            $config_content .= "// Email Settings (SMTP)\n";
            $config_content .= "define('SMTP_HOST', 'smtp.gmail.com');\n";
            $config_content .= "define('SMTP_USER', '');\n";
            $config_content .= "define('SMTP_PASS', '');\n";
            $config_content .= "define('SMTP_AUTH', true);\n";
            $config_content .= "define('SMTP_SECURE', 'tls');\n";
            $config_content .= "define('SMTP_PORT', 587);\n";
            $config_content .= "define('SMTP_USERNAME', '');\n";
            $config_content .= "define('SMTP_PASSWORD', '');\n";
            $config_content .= "define('FROM_EMAIL', '" . addslashes($_SESSION['company_email']) . "');\n";
            $config_content .= "define('FROM_NAME', '" . addslashes($_SESSION['company_name']) . "');\n";
            $config_content .= "?>";
            
            file_put_contents('config.php', $config_content);
            
            $success_messages[] = 'Configuration file created successfully!';
            $current_step = 7;
            
            // Clear session
            session_destroy();
        } catch (Exception $e) {
            $errors[] = 'Error creating configuration file: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GeekMobile Invoice System - Installation</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .container {
            max-width: 800px;
            width: 100%;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .header p {
            font-size: 16px;
            opacity: 0.9;
        }
        
        .progress-bar {
            display: flex;
            background: #f8f9fa;
            padding: 20px;
            justify-content: space-between;
        }
        
        .progress-step {
            flex: 1;
            text-align: center;
            padding: 10px;
            position: relative;
        }
        
        .progress-step::after {
            content: '';
            position: absolute;
            top: 20px;
            right: -50%;
            width: 100%;
            height: 2px;
            background: #dee2e6;
            z-index: 0;
        }
        
        .progress-step:last-child::after {
            display: none;
        }
        
        .progress-step.active {
            color: #667eea;
            font-weight: 600;
        }
        
        .progress-step.completed {
            color: #10b981;
        }
        
        .progress-step.completed::after {
            background: #10b981;
        }
        
        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #dee2e6;
            color: white;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 8px;
            position: relative;
            z-index: 1;
        }
        
        .progress-step.active .step-number {
            background: #667eea;
        }
        
        .progress-step.completed .step-number {
            background: #10b981;
        }
        
        .content {
            padding: 40px;
        }
        
        .message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
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
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            color: #555;
            font-weight: 500;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
        }
        
        .form-group small {
            color: #666;
            font-size: 12px;
            display: block;
            margin-top: 4px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .btn-success {
            background: #10b981;
            color: white;
        }
        
        .btn-success:hover {
            background: #059669;
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
        
        .checklist {
            list-style: none;
            padding: 0;
        }
        
        .checklist li {
            padding: 10px;
            margin-bottom: 8px;
            background: #f8f9fa;
            border-radius: 4px;
            display: flex;
            align-items: center;
        }
        
        .checklist li::before {
            content: '✓';
            color: #10b981;
            font-weight: bold;
            margin-right: 10px;
            font-size: 18px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <h1>🚀 GeekMobile Invoice System</h1>
                <p>Complete Installation Wizard</p>
            </div>
            
            <div class="progress-bar">
                <?php foreach ($steps as $num => $name): ?>
                    <div class="progress-step <?php echo $num < $current_step ? 'completed' : ($num == $current_step ? 'active' : ''); ?>">
                        <div class="step-number"><?php echo $num; ?></div>
                        <div><?php echo $name; ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="content">
                <?php if (!empty($errors)): ?>
                    <?php foreach ($errors as $error): ?>
                        <div class="message error">✗ <?php echo htmlspecialchars($error); ?></div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <?php if (!empty($success_messages)): ?>
                    <?php foreach ($success_messages as $msg): ?>
                        <div class="message success">✓ <?php echo htmlspecialchars($msg); ?></div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <?php if ($current_step === 1): ?>
                    <h2>Step 1: Database Connection</h2>
                    <div class="info-box">
                        <h3>📊 Database Setup</h3>
                        <p>Enter your MySQL database credentials. The installer will create the database if it doesn't exist.</p>
                    </div>
                    
                    <form method="post">
                        <div class="form-group">
                            <label>Database Host *</label>
                            <input type="text" name="db_host" value="localhost" required>
                            <small>Usually 'localhost' for local installations</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Database Name *</label>
                            <input type="text" name="db_name" value="geekmobile_invoice" required>
                            <small>Will be created if it doesn't exist</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Database Username *</label>
                            <input type="text" name="db_user" value="root" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Database Password</label>
                            <input type="password" name="db_pass">
                            <small>Leave empty if no password (not recommended for production)</small>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Continue →</button>
                    </form>
                    
                <?php elseif ($current_step === 2): ?>
                    <h2>Step 2: Create Database Tables</h2>
                    <div class="info-box">
                        <h3>🗄️ Database Structure</h3>
                        <p>This will create all necessary tables for the invoice system including:</p>
                        <ul style="margin-top: 10px; margin-left: 20px;">
                            <li>Clients table</li>
                            <li>Invoices table (with shareable link tracking)</li>
                            <li>Invoice items table (with repair details)</li>
                            <li>Payments table</li>
                            <li>Services table (with default IT services)</li>
                            <li>Users table (for authentication)</li>
                            <li>Settings table</li>
                            <li>Invoice summary view</li>
                        </ul>
                    </div>
                    
                    <form method="post">
                        <button type="submit" class="btn btn-primary">Create Tables →</button>
                    </form>
                    
                <?php elseif ($current_step === 3): ?>
                    <h2>Step 3: Company Information</h2>
                    <div class="info-box">
                        <h3>🏢 Your Business Details</h3>
                        <p>Enter your company information. This will appear on all invoices and documents.</p>
                    </div>
                    
                    <form method="post">
                        <div class="form-group">
                            <label>Company Name *</label>
                            <input type="text" name="company_name" value="GeekMobile IT Services" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Company Address *</label>
                            <input type="text" name="company_address" value="123 Tech Street, Silicon Valley, CA 94000" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Company Email *</label>
                            <input type="email" name="company_email" value="billing@geekmobile.com" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Company Phone</label>
                            <input type="tel" name="company_phone" value="+1 (555) 123-4567">
                        </div>
                        
                        <div class="form-group">
                            <label>Default Tax Rate (%)</label>
                            <input type="number" step="0.01" name="default_tax_rate" value="10">
                            <small>Default tax percentage for invoices</small>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Continue →</button>
                    </form>
                    
                <?php elseif ($current_step === 4): ?>
                    <h2>Step 4: Create Admin Account</h2>
                    <div class="info-box">
                        <h3>👤 Administrator Account</h3>
                        <p>Create your admin account to access the invoice system. This account will have full access to all features.</p>
                    </div>
                    
                    <form method="post">
                        <div class="form-group">
                            <label>Username *</label>
                            <input type="text" name="admin_username" value="<?php echo htmlspecialchars($_POST['admin_username'] ?? 'admin'); ?>" required>
                            <small>Choose a unique username for login</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Full Name *</label>
                            <input type="text" name="admin_fullname" value="<?php echo htmlspecialchars($_POST['admin_fullname'] ?? 'Administrator'); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Email Address *</label>
                            <input type="email" name="admin_email" value="<?php echo htmlspecialchars($_POST['admin_email'] ?? ''); ?>" required>
                            <small>Used for login and notifications</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Password *</label>
                            <input type="password" name="admin_password" required minlength="6">
                            <small>Minimum 6 characters</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Confirm Password *</label>
                            <input type="password" name="admin_password_confirm" required minlength="6">
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Create Admin Account →</button>
                    </form>
                    
                <?php elseif ($current_step === 5): ?>
                    <h2>Step 5: Create Directories</h2>
                    <div class="info-box">
                        <h3>📁 File Structure</h3>
                        <p>Creating necessary directories for file uploads and storage.</p>
                    </div>
                    
                    <form method="post">
                        <button type="submit" class="btn btn-primary">Create Directories →</button>
                    </form>
                    
                <?php elseif ($current_step === 6): ?>
                    <h2>Step 6: Generate Configuration</h2>
                    <div class="info-box">
                        <h3>⚙️ Configuration File</h3>
                        <p>Creating config.php with your database and company settings.</p>
                    </div>
                    
                    <form method="post">
                        <button type="submit" class="btn btn-primary">Generate Config →</button>
                    </form>
                    
                <?php elseif ($current_step === 7): ?>
                    <h2>🎉 Installation Complete!</h2>
                    <div class="message success">
                        ✓ GeekMobile Invoice System has been successfully installed!
                    </div>
                    
                    <div class="info-box">
                        <h3>✅ What's Been Set Up</h3>
                        <ul class="checklist">
                            <li>Database connection configured</li>
                            <li>All tables created with complete schema</li>
                            <li>Clients, invoices, invoice items, payments tables</li>
                            <li>Services table with 10 default IT services</li>
                            <li>Users table for authentication</li>
                            <li>Settings table with company defaults</li>
                            <li>Shareable link tracking columns</li>
                            <li>Repair details column for invoice items</li>
                            <li>Invoice summary view for reporting</li>
                            <li>Admin account created</li>
                            <li>Company information saved</li>
                            <li>Upload directories created</li>
                            <li>Configuration file generated</li>
                        </ul>
                    </div>
                    
                    <div class="info-box">
                        <h3>🚀 Next Steps</h3>
                        <p><strong>1. Delete install.php</strong> - For security, delete this installation file from your server.</p>
                        <p><strong>2. Configure Email</strong> - Go to Settings to configure SMTP for sending invoices via email.</p>
                        <p><strong>3. Upload Logo</strong> - Add your company logo in Settings for professional branding.</p>
                        <p><strong>4. Add Services</strong> - Customize your service catalog in Manage Services.</p>
                        <p><strong>5. Create Invoices</strong> - Start creating invoices for your clients!</p>
                    </div>
                    
                    <div class="info-box" style="background: #fff3cd; border-left-color: #ffc107;">
                        <h3>🔐 Your Login Credentials</h3>
                        <p><strong>Username:</strong> <?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'admin'); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($_SESSION['admin_email'] ?? ''); ?></p>
                        <p style="margin-top: 10px;"><em>Please save these credentials in a secure location!</em></p>
                    </div>
                    
                    <div style="margin-top: 30px;">
                        <a href="login.php" class="btn btn-success">Go to Login Page →</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
