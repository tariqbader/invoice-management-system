<?php
/**
 * Send Invoice Link via Email
 * Sends a shareable link to the client to view their invoice online
 */

require_once 'config.php';
require_once 'db.php';
require_once 'lib/phpmailer/src/Exception.php';
require_once 'lib/phpmailer/src/PHPMailer.php';
require_once 'lib/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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

// Initialize database connection
$pdo = getDBConnection();

// Get invoice ID from POST or GET
$invoice_id = isset($_POST['invoice_id']) ? intval($_POST['invoice_id']) : (isset($_GET['id']) ? intval($_GET['id']) : 0);
$recipient_email = isset($_POST['recipient_email']) ? trim($_POST['recipient_email']) : '';

// Handle form display
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $invoice_id > 0) {
    try {
        // Fetch invoice details
        $stmt = $pdo->prepare("
            SELECT i.*, c.name as client_name, c.email as client_email
            FROM invoices i
            JOIN clients c ON i.client_id = c.id
            WHERE i.id = ?
        ");
        $stmt->execute([$invoice_id]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$invoice) {
            die('Invoice not found');
        }
        
        // Generate token if it doesn't exist
        if (empty($invoice['share_token'])) {
            $share_token = bin2hex(random_bytes(32));
            $stmt = $pdo->prepare("
                UPDATE invoices 
                SET share_token = ?, 
                    share_token_created_at = NOW(),
                    share_token_expires_at = DATE_ADD(NOW(), INTERVAL 90 DAY)
                WHERE id = ?
            ");
            $stmt->execute([$share_token, $invoice_id]);
            $invoice['share_token'] = $share_token;
        }
        
        // Build the shareable URL
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $base_url = $protocol . '://' . $host . dirname($_SERVER['PHP_SELF']);
        $shareable_url = $base_url . '/public_invoice.php?token=' . $invoice['share_token'];
        
    } catch (PDOException $e) {
        die('Database error: ' . $e->getMessage());
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Send Invoice Link - <?php echo APP_NAME; ?></title>
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
                max-width: 600px;
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
            
            .invoice-info {
                background: #f8f9fa;
                padding: 20px;
                border-radius: 8px;
                margin-bottom: 30px;
                border-left: 4px solid #667eea;
            }
            
            .invoice-info h3 {
                color: #333;
                margin-bottom: 10px;
            }
            
            .invoice-info p {
                color: #666;
                margin-bottom: 5px;
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
            
            .shareable-link {
                background: #e3f2fd;
                padding: 15px;
                border-radius: 8px;
                margin-bottom: 20px;
                border: 1px solid #90caf9;
            }
            
            .shareable-link label {
                display: block;
                color: #1565c0;
                font-weight: 600;
                margin-bottom: 8px;
                font-size: 14px;
            }
            
            .link-display {
                display: flex;
                gap: 10px;
            }
            
            .link-display input {
                flex: 1;
                padding: 10px;
                border: 1px solid #90caf9;
                border-radius: 6px;
                font-size: 13px;
                background: white;
            }
            
            .copy-btn {
                padding: 10px 20px;
                background: #1976d2;
                color: white;
                border: none;
                border-radius: 6px;
                cursor: pointer;
                font-weight: 600;
                transition: all 0.2s;
            }
            
            .copy-btn:hover {
                background: #1565c0;
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
                margin-top: 30px;
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
            
            .expiry-info {
                background: #fff3cd;
                padding: 12px;
                border-radius: 6px;
                margin-top: 10px;
                font-size: 13px;
                color: #856404;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>📤 Send Invoice Link</h1>
            <p class="subtitle">Share a secure link with your client to view their invoice online</p>
            
            <div class="invoice-info">
                <h3>Invoice Details</h3>
                <p><strong>Invoice #:</strong> <?php echo htmlspecialchars($invoice['invoice_number']); ?></p>
                <p><strong>Client:</strong> <?php echo htmlspecialchars($invoice['client_name']); ?></p>
                <p><strong>Amount:</strong> <?php echo get_currency_symbol() . number_format($invoice['total'], 2); ?></p>
            </div>
            
            <div class="shareable-link">
                <label>🔗 Shareable Link (Valid for 90 days)</label>
                <div class="link-display">
                    <input type="text" id="shareableLink" value="<?php echo htmlspecialchars($shareable_url); ?>" readonly>
                    <button type="button" class="copy-btn" onclick="copyLink()">📋 Copy</button>
                </div>
                <div class="expiry-info">
                    ⏰ This link will expire on <?php echo date('F d, Y', strtotime('+90 days')); ?>
                </div>
            </div>
            
            <form method="post" id="sendLinkForm">
                <input type="hidden" name="invoice_id" value="<?php echo $invoice_id; ?>">
                
                <div class="form-group">
                    <label>Recipient Email Address:</label>
                    <input type="email" name="recipient_email" value="<?php echo htmlspecialchars($invoice['client_email']); ?>" required>
                </div>
                
                <div class="actions">
                    <button type="submit" class="btn btn-primary">✉️ Send Email</button>
                    <a href="view_invoices.php" class="btn btn-secondary">← Back to Invoices</a>
                </div>
            </form>
        </div>
        
        <script>
            function copyLink() {
                const linkInput = document.getElementById('shareableLink');
                linkInput.select();
                linkInput.setSelectionRange(0, 99999); // For mobile devices
                
                try {
                    document.execCommand('copy');
                    const btn = event.target;
                    const originalText = btn.textContent;
                    btn.textContent = '✓ Copied!';
                    btn.style.background = '#10b981';
                    
                    setTimeout(() => {
                        btn.textContent = originalText;
                        btn.style.background = '#1976d2';
                    }, 2000);
                } catch (err) {
                    alert('Failed to copy link. Please copy manually.');
                }
            }
        </script>
    </body>
    </html>
    <?php
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $invoice_id > 0) {
    try {
        // Fetch invoice details
        $stmt = $pdo->prepare("
            SELECT i.*, c.name as client_name, c.email as client_email
            FROM invoices i
            JOIN clients c ON i.client_id = c.id
            WHERE i.id = ?
        ");
        $stmt->execute([$invoice_id]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$invoice) {
            echo json_encode(['success' => false, 'message' => 'Invoice not found']);
            exit;
        }
        
        // Generate token if it doesn't exist
        if (empty($invoice['share_token'])) {
            $share_token = bin2hex(random_bytes(32));
            $stmt = $pdo->prepare("
                UPDATE invoices 
                SET share_token = ?, 
                    share_token_created_at = NOW(),
                    share_token_expires_at = DATE_ADD(NOW(), INTERVAL 90 DAY)
                WHERE id = ?
            ");
            $stmt->execute([$share_token, $invoice_id]);
            $invoice['share_token'] = $share_token;
        }
        
        // Use provided email or client's email
        $to_email = !empty($recipient_email) ? $recipient_email : $invoice['client_email'];
        
        if (empty($to_email)) {
            echo json_encode(['success' => false, 'message' => 'No recipient email address']);
            exit;
        }
        
        // Build the shareable URL
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $base_url = $protocol . '://' . $host . dirname($_SERVER['PHP_SELF']);
        $shareable_url = $base_url . '/public_invoice.php?token=' . $invoice['share_token'];
        
        // Create PHPMailer instance
        $mail = new PHPMailer(true);
        
        // Try SMTP first, fall back to mail() if it fails
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = SMTP_AUTH;
            $mail->Username   = SMTP_USERNAME;
            $mail->Password   = SMTP_PASSWORD;
            $mail->SMTPSecure = SMTP_SECURE;
            $mail->Port       = SMTP_PORT;
            $mail->Timeout    = 10; // 10 second timeout
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
        } catch (Exception $smtp_error) {
            // If SMTP fails, fall back to PHP mail()
            $mail->isMail();
        }
        
        // Recipients
        $mail->setFrom(COMPANY_EMAIL, COMPANY_NAME);
        $mail->addAddress($to_email, $invoice['client_name']);
        $mail->addReplyTo(COMPANY_EMAIL, COMPANY_NAME);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'View Your Invoice ' . $invoice['invoice_number'] . ' from ' . COMPANY_NAME;
        
        // Calculate expiry date
        $expiry_date = date('F d, Y', strtotime($invoice['share_token_expires_at'] ?? '+90 days'));
        
        // Email body
        $email_body = '
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { padding: 30px; background-color: #f9f9f9; }
                .invoice-details { background-color: white; padding: 20px; margin: 20px 0; border-left: 4px solid #667eea; border-radius: 8px; }
                .button-container { text-align: center; margin: 30px 0; }
                .button { display: inline-block; padding: 15px 40px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 16px; }
                .footer { text-align: center; padding: 20px; font-size: 12px; color: #777; background: white; border-radius: 0 0 10px 10px; }
                table { width: 100%; border-collapse: collapse; margin: 15px 0; }
                th, td { padding: 10px; text-align: left; }
                th { background-color: #f8f9fa; color: #333; font-weight: 600; }
                .amount { font-size: 20px; font-weight: bold; color: #10b981; }
                .expiry-notice { background: #fff3cd; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #ffc107; color: #856404; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>' . COMPANY_NAME . '</h1>
                    <p style="margin: 10px 0 0 0; font-size: 18px;">Invoice Ready to View</p>
                </div>
                
                <div class="content">
                    <p>Dear ' . htmlspecialchars($invoice['client_name']) . ',</p>
                    
                    <p>Your invoice is ready to view online. Click the button below to access your invoice securely.</p>
                    
                    <div class="invoice-details">
                        <h3 style="margin-top: 0; color: #667eea;">Invoice Summary</h3>
                        <table>
                            <tr>
                                <th>Invoice Number</th>
                                <td>' . htmlspecialchars($invoice['invoice_number']) . '</td>
                            </tr>
                            <tr>
                                <th>Invoice Date</th>
                                <td>' . date('F d, Y', strtotime($invoice['invoice_date'])) . '</td>
                            </tr>
                            <tr>
                                <th>Due Date</th>
                                <td>' . date('F d, Y', strtotime($invoice['due_date'])) . '</td>
                            </tr>
                            <tr>
                                <th>Status</th>
                                <td>' . strtoupper($invoice['status']) . '</td>
                            </tr>
                            <tr>
                                <th>Total Amount</th>
                                <td class="amount">' . get_currency_symbol() . number_format($invoice['total'], 2) . '</td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="button-container">
                        <a href="' . $shareable_url . '" class="button">📄 View Invoice Online</a>
                    </div>
                    
                    <div class="expiry-notice">
                        <strong>⏰ Important:</strong> This link will expire on ' . $expiry_date . '. Please view your invoice before this date.
                    </div>
                    
                    <p style="margin-top: 20px;">You can also copy and paste this link into your browser:</p>
                    <p style="background: #f8f9fa; padding: 10px; border-radius: 6px; word-break: break-all; font-size: 12px; color: #666;">
                        ' . $shareable_url . '
                    </p>
                    
                    <p style="margin-top: 20px;">From the online invoice, you can:</p>
                    <ul style="color: #666;">
                        <li>View all invoice details</li>
                        <li>Download a PDF copy</li>
                        <li>Print the invoice</li>
                    </ul>
                    
                    <p>If you have any questions about this invoice, please don\'t hesitate to contact us.</p>
                </div>

                <div class="footer">
                    <p><strong>' . COMPANY_NAME . '</strong><br>
                    ' . nl2br(COMPANY_ADDRESS) . '<br>
                    Email: ' . COMPANY_EMAIL . ' | Phone: ' . COMPANY_PHONE . '</p>
                    <p style="font-size: 11px; color: #999; margin-top: 10px;">This is an automated email. Please do not reply directly to this message.</p>
                </div>
            </div>
        </body>
        </html>
        ';
        
        $mail->Body = $email_body;
        
        // Plain text version
        $mail->AltBody = "Dear {$invoice['client_name']},\n\n" .
                         "Your invoice is ready to view online.\n\n" .
                         "Invoice Details:\n" .
                         "Invoice Number: {$invoice['invoice_number']}\n" .
                         "Invoice Date: " . date('F d, Y', strtotime($invoice['invoice_date'])) . "\n" .
                         "Due Date: " . date('F d, Y', strtotime($invoice['due_date'])) . "\n" .
                         "Total Amount: " . get_currency_symbol() . number_format($invoice['total'], 2) . "\n\n" .
                         "View your invoice online:\n" .
                         $shareable_url . "\n\n" .
                         "This link will expire on " . $expiry_date . "\n\n" .
                         "If you have any questions, please contact us at " . COMPANY_EMAIL . "\n\n" .
                         "Best regards,\n" . COMPANY_NAME;
        
        // Send email
        $mail->send();
        
        // Return success response
        echo '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Link Sent Successfully</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                }
                .container {
                    max-width: 500px;
                    background: white;
                    padding: 40px;
                    border-radius: 10px;
                    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
                    text-align: center;
                }
                .success-icon {
                    width: 80px;
                    height: 80px;
                    background: #d1fae5;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 0 auto 20px;
                    font-size: 40px;
                }
                h2 { color: #065f46; margin-bottom: 15px; }
                p { color: #666; margin-bottom: 10px; line-height: 1.6; }
                .btn {
                    display: inline-block;
                    padding: 12px 30px;
                    background: #667eea;
                    color: white;
                    text-decoration: none;
                    border-radius: 6px;
                    font-weight: 600;
                    margin-top: 20px;
                    transition: all 0.2s;
                }
                .btn:hover {
                    background: #5568d3;
                    transform: translateY(-2px);
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="success-icon">✓</div>
                <h2>Invoice Link Sent Successfully!</h2>
                <p>The shareable invoice link has been sent to:</p>
                <p><strong>' . htmlspecialchars($to_email) . '</strong></p>
                <p style="margin-top: 15px;">Invoice #' . htmlspecialchars($invoice['invoice_number']) . '</p>
                <a href="view_invoices.php" class="btn">← Back to Invoices</a>
            </div>
        </body>
        </html>';
        
    } catch (Exception $e) {
        $error_message = 'Email could not be sent. Error: ' . $mail->ErrorInfo;
        echo '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Email Error</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                }
                .container {
                    max-width: 500px;
                    background: white;
                    padding: 40px;
                    border-radius: 10px;
                    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
                    text-align: center;
                }
                .error-icon {
                    width: 80px;
                    height: 80px;
                    background: #fee2e2;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 0 auto 20px;
                    font-size: 40px;
                }
                h2 { color: #991b1b; margin-bottom: 15px; }
                p { color: #666; margin-bottom: 10px; line-height: 1.6; }
                .btn {
                    display: inline-block;
                    padding: 12px 30px;
                    background: #667eea;
                    color: white;
                    text-decoration: none;
                    border-radius: 6px;
                    font-weight: 600;
                    margin-top: 20px;
                    transition: all 0.2s;
                }
                .btn:hover {
                    background: #5568d3;
                    transform: translateY(-2px);
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="error-icon">✗</div>
                <h2>Email Error</h2>
                <p>' . htmlspecialchars($error_message) . '</p>
                <a href="view_invoices.php" class="btn">← Back to Invoices</a>
            </div>
        </body>
        </html>';
    } catch (PDOException $e) {
        die('Database error: ' . $e->getMessage());
    }
    exit;
}

// Invalid request
die('Invalid request');
?>
