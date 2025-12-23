<?php
/**
 * Email Invoice Sender
 * Sends invoices via email with PDF attachment using PHPMailer
 */

require_once 'config.php';
require_once 'db.php';
require_once 'lib/phpmailer/src/Exception.php';
require_once 'lib/phpmailer/src/PHPMailer.php';
require_once 'lib/phpmailer/src/SMTP.php';
require_once 'lib/tcpdf/tcpdf.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Initialize database connection
$pdo = getDBConnection();

// Get invoice ID from POST or GET
$invoice_id = isset($_POST['invoice_id']) ? intval($_POST['invoice_id']) : (isset($_GET['id']) ? intval($_GET['id']) : 0);
$recipient_email = isset($_POST['recipient_email']) ? trim($_POST['recipient_email']) : '';

if ($invoice_id <= 0) {
    die(json_encode(['success' => false, 'message' => 'Invalid invoice ID']));
}

try {
    // Fetch invoice details
    $stmt = $pdo->prepare("
        SELECT i.*, c.name as client_name, c.email as client_email, 
               c.phone as client_phone, c.address as client_address
        FROM invoices i
        JOIN clients c ON i.client_id = c.id
        WHERE i.id = ?
    ");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$invoice) {
        die(json_encode(['success' => false, 'message' => 'Invoice not found']));
    }
    
    // Use provided email or client's email
    $to_email = !empty($recipient_email) ? $recipient_email : $invoice['client_email'];
    
    if (empty($to_email)) {
        die(json_encode(['success' => false, 'message' => 'No recipient email address']));
    }
    
    // Fetch invoice items
    $stmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id");
    $stmt->execute([$invoice_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch payments
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE invoice_id = ? ORDER BY payment_date");
    $stmt->execute([$invoice_id]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate paid amount
    $paid_amount = 0;
    foreach ($payments as $payment) {
        $paid_amount += $payment['amount'];
    }
    $balance_due = $invoice['total'] - $paid_amount;

    // Fetch invoice footer setting
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute(['invoice_footer']);
    $invoice_footer = $stmt->fetchColumn();
    
    // Generate PDF in memory
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor(COMPANY_NAME);
    $pdf->SetTitle('Invoice ' . $invoice['invoice_number']);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(TRUE, 15);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 10);
    
    // Company Header
    $pdf->SetFont('helvetica', 'B', 20);
    $pdf->Cell(0, 10, COMPANY_NAME, 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->MultiCell(0, 5, COMPANY_ADDRESS . "\n" . "Email: " . COMPANY_EMAIL . "\n" . "Phone: " . COMPANY_PHONE, 0, 'L');
    $pdf->Ln(5);
    
    // Invoice title
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'INVOICE', 0, 1, 'R');
    
    // Invoice details
    $y_position = $pdf->GetY();
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(95, 6, 'Bill To:', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->MultiCell(95, 5, $invoice['client_name'] . "\n" . $invoice['client_address'] . "\n" . 
                           "Email: " . $invoice['client_email'] . "\n" . "Phone: " . $invoice['client_phone'], 0, 'L');
    
    $pdf->SetXY(110, $y_position);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(40, 5, 'Invoice Number:', 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 5, $invoice['invoice_number'], 0, 1, 'R');
    $pdf->SetX(110);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(40, 5, 'Invoice Date:', 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 5, date('M d, Y', strtotime($invoice['invoice_date'])), 0, 1, 'R');
    $pdf->SetX(110);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(40, 5, 'Due Date:', 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 5, date('M d, Y', strtotime($invoice['due_date'])), 0, 1, 'R');
    
    $pdf->Ln(10);
    
    // Items table
    $pdf->SetFillColor(52, 73, 94);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(80, 7, 'Description', 1, 0, 'L', true);
    $pdf->Cell(30, 7, 'Quantity', 1, 0, 'C', true);
    $pdf->Cell(35, 7, 'Unit Price', 1, 0, 'R', true);
    $pdf->Cell(35, 7, 'Total', 1, 1, 'R', true);
    
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', '', 9);
    
    // Get correct currency symbol
    $currency = get_currency_symbol();
    
    foreach ($items as $item) {
        $description = $item['description'];
        if ($item['is_milestone'] && $item['milestone_name']) {
            $description = '[Milestone: ' . $item['milestone_name'] . '] ' . $description;
        }
        $pdf->MultiCell(80, 6, $description, 1, 'L', false, 0);
        $pdf->Cell(30, 6, number_format($item['quantity'], 2), 1, 0, 'C');
        $pdf->Cell(35, 6, $currency . number_format($item['unit_price'], 2), 1, 0, 'R');
        $pdf->Cell(35, 6, $currency . number_format($item['line_total'], 2), 1, 1, 'R');
    }
    
    // Totals
    $pdf->Ln(5);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(145, 6, 'Subtotal:', 0, 0, 'R');
    $pdf->Cell(35, 6, $currency . number_format($invoice['subtotal'], 2), 0, 1, 'R');
    
    if ($invoice['discount_amount'] > 0) {
        $discount_label = 'Discount';
        if ($invoice['discount_type'] == 'percentage') {
            $discount_label .= ' (' . $invoice['discount_value'] . '%)';
        }
        $pdf->Cell(145, 6, $discount_label . ':', 0, 0, 'R');
        $pdf->Cell(35, 6, '-' . $currency . number_format($invoice['discount_amount'], 2), 0, 1, 'R');
    }
    
    $pdf->Cell(145, 6, 'Tax (' . $invoice['tax_rate'] . '%):', 0, 0, 'R');
    $pdf->Cell(35, 6, $currency . number_format($invoice['tax_amount'], 2), 0, 1, 'R');
    
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(145, 8, 'Total:', 0, 0, 'R');
    $pdf->Cell(35, 8, $currency . number_format($invoice['total'], 2), 0, 1, 'R');
    
    if (!empty($payments)) {
        $pdf->Ln(3);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(145, 6, 'Amount Paid:', 0, 0, 'R');
        $pdf->Cell(35, 6, $currency . number_format($paid_amount, 2), 0, 1, 'R');
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(145, 7, 'Balance Due:', 0, 0, 'R');
        $pdf->Cell(35, 7, $currency . number_format($balance_due, 2), 0, 1, 'R');
    }
    
    if (!empty($invoice['notes'])) {
        $pdf->Ln(10);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 6, 'Notes:', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->MultiCell(0, 5, $invoice['notes'], 0, 'L');
    }
    
    $pdf->SetY(-40);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetTextColor(0, 0, 0);

    // Display invoice footer if set
    if (!empty($invoice_footer)) {
        $pdf->MultiCell(0, 4, $invoice_footer, 0, 'C');
        $pdf->Ln(3);
    }

    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->SetTextColor(128, 128, 128);
    $pdf->Cell(0, 5, 'Thank you for your business!', 0, 1, 'C');
    $pdf->Cell(0, 5, 'For questions about this invoice, please contact ' . COMPANY_EMAIL, 0, 1, 'C');
    
    // Get PDF content as string
    $pdf_content = $pdf->Output('', 'S');
    
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
    
    // Attach PDF
    $mail->addStringAttachment($pdf_content, 'Invoice_' . $invoice['invoice_number'] . '.pdf', 'base64', 'application/pdf');
    
    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Invoice ' . $invoice['invoice_number'] . ' from ' . COMPANY_NAME;
    
    // Email body
    $email_body = '
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #34495e; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background-color: #f9f9f9; }
            .invoice-details { background-color: white; padding: 15px; margin: 15px 0; border-left: 4px solid #3498db; }
            .footer { text-align: center; padding: 20px; font-size: 12px; color: #777; }
            .button { display: inline-block; padding: 10px 20px; background-color: #3498db; color: white; text-decoration: none; border-radius: 5px; margin: 10px 0; }
            table { width: 100%; border-collapse: collapse; margin: 10px 0; }
            th, td { padding: 8px; text-align: left; }
            th { background-color: #34495e; color: white; }
            .amount { font-size: 18px; font-weight: bold; color: #27ae60; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>' . COMPANY_NAME . '</h1>
                <p>Invoice Notification</p>
            </div>
            
            <div class="content">
                <p>Dear ' . htmlspecialchars($invoice['client_name']) . ',</p>
                
                <p>Thank you for your business. Please find attached your invoice details.</p>
                
                <div class="invoice-details">
                    <h3>Invoice Summary</h3>
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
                            <td class="amount">' . $currency . number_format($invoice['total'], 2) . '</td>
                        </tr>
                    </table>
                </div>
                
                <p>The complete invoice is attached as a PDF document. Please review it and process payment by the due date.</p>
                
                ' . ($balance_due = $invoice['total'] - array_sum(array_column($payments, 'amount'))) > 0 ? 
                '<p><strong>Balance Due: ' . $currency . number_format($balance_due, 2) . '</strong></p>' : '' . '
                
                <p>If you have any questions about this invoice, please don\'t hesitate to contact us.</p>

                ' . (!empty($invoice_footer) ? '<div style="margin-top: 20px; padding: 15px; background-color: #f8f9fa; border-left: 4px solid #3498db; font-size: 14px; line-height: 1.4;">' . nl2br(htmlspecialchars($invoice_footer)) . '</div>' : '') . '
            </div>

            <div class="footer">
                <p>' . COMPANY_NAME . '<br>
                ' . COMPANY_ADDRESS . '<br>
                Email: ' . COMPANY_EMAIL . ' | Phone: ' . COMPANY_PHONE . '</p>
                <p style="font-size: 11px; color: #999;">This is an automated email. Please do not reply directly to this message.</p>
            </div>
        </div>
    </body>
    </html>
    ';
    
    $mail->Body = $email_body;
    
    // Plain text version
    $mail->AltBody = "Dear {$invoice['client_name']},\n\n" .
                     "Thank you for your business. Please find attached invoice {$invoice['invoice_number']}.\n\n" .
                     "Invoice Details:\n" .
                     "Invoice Number: {$invoice['invoice_number']}\n" .
                     "Invoice Date: " . date('F d, Y', strtotime($invoice['invoice_date'])) . "\n" .
                     "Due Date: " . date('F d, Y', strtotime($invoice['due_date'])) . "\n" .
                     "Total Amount: " . $currency . number_format($invoice['total'], 2) . "\n\n" .
                     "If you have any questions, please contact us at " . COMPANY_EMAIL . "\n\n" .
                     "Best regards,\n" . COMPANY_NAME;
    
    // Send email
    $mail->send();
    
    // Return success response
    if (isset($_POST['invoice_id'])) {
        echo json_encode([
            'success' => true, 
            'message' => 'Invoice email sent successfully to ' . $to_email
        ]);
    } else {
        echo '<!DOCTYPE html>
        <html>
        <head>
            <title>Email Sent</title>
            <link rel="stylesheet" href="css/style.css">
        </head>
        <body>
            <div class="container">
                <div class="success-message">
                    <h2>✓ Email Sent Successfully</h2>
                    <p>Invoice ' . htmlspecialchars($invoice['invoice_number']) . ' has been sent to ' . htmlspecialchars($to_email) . '</p>
                    <a href="view_invoices.php" class="btn">Back to Invoices</a>
                </div>
            </div>
        </body>
        </html>';
    }
    
} catch (Exception $e) {
    $error_message = 'Email could not be sent. Error: ' . $mail->ErrorInfo;
    if (isset($_POST['invoice_id'])) {
        echo json_encode(['success' => false, 'message' => $error_message]);
    } else {
        echo '<!DOCTYPE html>
        <html>
        <head>
            <title>Email Error</title>
            <link rel="stylesheet" href="css/style.css">
        </head>
        <body>
            <div class="container">
                <div class="error-message">
                    <h2>✗ Email Error</h2>
                    <p>' . htmlspecialchars($error_message) . '</p>
                    <a href="view_invoices.php" class="btn">Back to Invoices</a>
                </div>
            </div>
        </body>
        </html>';
    }
} catch (PDOException $e) {
    $error_message = 'Database error: ' . $e->getMessage();
    if (isset($_POST['invoice_id'])) {
        echo json_encode(['success' => false, 'message' => $error_message]);
    } else {
        die($error_message);
    }
}
?>
