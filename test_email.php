<?php
/**
 * Email Testing Script
 * Tests SMTP connection and email sending functionality
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Try to load the appropriate config file
if (file_exists('config.php')) {
    require_once 'config.php';
} elseif (file_exists('config_hosting.php')) {
    require_once 'config_hosting.php';
} else {
    die('Configuration file not found. Please ensure config.php or config_hosting.php exists.');
}
require_once 'lib/phpmailer/src/Exception.php';
require_once 'lib/phpmailer/src/PHPMailer.php';
require_once 'lib/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$message = '';
$message_type = '';
$debug_output = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_test'])) {
    $test_email = trim($_POST['test_email']);
    
    if (empty($test_email) || !filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address';
        $message_type = 'error';
    } else {
        try {
            $mail = new PHPMailer(true);
            
            // Enable verbose debug output
            $mail->SMTPDebug = 2;
            $mail->Debugoutput = function($str, $level) {
                global $debug_output;
                $debug_output .= htmlspecialchars($str) . "\n";
            };
            
            // Server settings
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = SMTP_AUTH;
            $mail->Username   = SMTP_USERNAME;
            $mail->Password   = SMTP_PASSWORD;
            $mail->SMTPSecure = SMTP_SECURE;
            $mail->Port       = SMTP_PORT;
            $mail->Timeout    = 10; // Reduce timeout to 10 seconds
            $mail->SMTPKeepAlive = true;
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
            
            // Recipients
            $mail->setFrom(COMPANY_EMAIL, COMPANY_NAME);
            $mail->addAddress($test_email);
            $mail->addReplyTo(COMPANY_EMAIL, COMPANY_NAME);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Test Email from ' . COMPANY_NAME;
            $mail->Body    = '
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                    .content { padding: 30px; background-color: #f9f9f9; border-radius: 0 0 10px 10px; }
                    .success-icon { font-size: 48px; text-align: center; margin: 20px 0; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <h1>' . COMPANY_NAME . '</h1>
                        <p>Email System Test</p>
                    </div>
                    <div class="content">
                        <div class="success-icon">✓</div>
                        <h2 style="text-align: center; color: #10b981;">Email Test Successful!</h2>
                        <p>This is a test email from your invoice system.</p>
                        <p>If you received this email, your SMTP configuration is working correctly.</p>
                        <p><strong>Configuration Details:</strong></p>
                        <ul>
                            <li>SMTP Host: ' . SMTP_HOST . '</li>
                            <li>SMTP Port: ' . SMTP_PORT . '</li>
                            <li>Encryption: ' . SMTP_SECURE . '</li>
                            <li>From: ' . COMPANY_EMAIL . '</li>
                        </ul>
                        <p style="margin-top: 20px;">Sent at: ' . date('Y-m-d H:i:s') . '</p>
                    </div>
                </div>
            </body>
            </html>
            ';
            
            $mail->AltBody = "Email Test Successful!\n\n" .
                           "This is a test email from your invoice system.\n" .
                           "If you received this email, your SMTP configuration is working correctly.\n\n" .
                           "Configuration Details:\n" .
                           "SMTP Host: " . SMTP_HOST . "\n" .
                           "SMTP Port: " . SMTP_PORT . "\n" .
                           "Encryption: " . SMTP_SECURE . "\n" .
                           "From: " . COMPANY_EMAIL . "\n\n" .
                           "Sent at: " . date('Y-m-d H:i:s');
            
            $mail->send();
            $message = 'Test email sent successfully to ' . htmlspecialchars($test_email);
            $message_type = 'success';
            
        } catch (Exception $e) {
            $message = 'Email could not be sent. Error: ' . $mail->ErrorInfo;
            $message_type = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Email - <?php echo COMPANY_NAME; ?></title>
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
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 40px;
        }
        
        h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .subtitle {
            color: #666;
            margin-bottom: 30px;
        }
        
        .config-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border-left: 4px solid #667eea;
        }
        
        .config-info h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 16px;
        }
        
        .config-item {
            display: flex;
            padding: 8px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .config-item:last-child {
            border-bottom: none;
        }
        
        .config-label {
            font-weight: 600;
            color: #555;
            min-width: 150px;
        }
        
        .config-value {
            color: #666;
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            color: #555;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
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
        
        .actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
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
        
        .debug-output {
            background: #1f2937;
            color: #10b981;
            padding: 15px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            margin-top: 20px;
            max-height: 400px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        
        .debug-header {
            color: #333;
            font-weight: 600;
            margin-top: 20px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📧 Email Testing Tool</h1>
        <p class="subtitle">Test your SMTP configuration and email sending functionality</p>
        
        <div class="config-info">
            <h3>Current SMTP Configuration</h3>
            <div class="config-item">
                <div class="config-label">SMTP Host:</div>
                <div class="config-value"><?php echo htmlspecialchars(SMTP_HOST); ?></div>
            </div>
            <div class="config-item">
                <div class="config-label">SMTP Port:</div>
                <div class="config-value"><?php echo htmlspecialchars(SMTP_PORT); ?></div>
            </div>
            <div class="config-item">
                <div class="config-label">Encryption:</div>
                <div class="config-value"><?php echo htmlspecialchars(SMTP_SECURE); ?></div>
            </div>
            <div class="config-item">
                <div class="config-label">Username:</div>
                <div class="config-value"><?php echo htmlspecialchars(SMTP_USERNAME); ?></div>
            </div>
            <div class="config-item">
                <div class="config-label">From Email:</div>
                <div class="config-value"><?php echo htmlspecialchars(COMPANY_EMAIL); ?></div>
            </div>
            <div class="config-item">
                <div class="config-label">From Name:</div>
                <div class="config-value"><?php echo htmlspecialchars(COMPANY_NAME); ?></div>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php if ($message_type === 'success'): ?>
                    ✓
                <?php else: ?>
                    ✗
                <?php endif; ?>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <form method="post">
            <div class="form-group">
                <label>Test Email Address:</label>
                <input type="email" name="test_email" placeholder="Enter email address to send test email" required value="<?php echo isset($_POST['test_email']) ? htmlspecialchars($_POST['test_email']) : ''; ?>">
            </div>
            
            <div class="actions">
                <button type="submit" name="send_test" class="btn btn-primary">📤 Send Test Email</button>
                <a href="diagnose_email_issue.php" class="btn btn-secondary">← Back to Diagnostics</a>
                <a href="view_invoices.php" class="btn btn-secondary">← Back to Invoices</a>
            </div>
        </form>
        
        <?php if (!empty($debug_output)): ?>
            <div class="debug-header">SMTP Debug Output:</div>
            <div class="debug-output"><?php echo $debug_output; ?></div>
        <?php endif; ?>
    </div>
</body>
</html>
