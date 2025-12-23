<?php
/**
 * Public Invoice View
 * Allows clients to view invoices via shareable link without authentication
 * Tracks when invoices are viewed
 */

require_once 'config.php';
require_once 'db.php';

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

// Get token from URL
$token = isset($_GET['token']) ? trim($_GET['token']) : '';

if (empty($token)) {
    die('Invalid or missing token');
}

try {
    // Fetch invoice by token
    $stmt = $pdo->prepare("
        SELECT i.*, c.name as client_name, c.company as client_company, 
               c.email as client_email, c.phone as client_phone, c.address as client_address
        FROM invoices i
        JOIN clients c ON i.client_id = c.id
        WHERE i.share_token = ?
    ");
    $stmt->execute([$token]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$invoice) {
        die('Invoice not found or link is invalid');
    }
    
    // Check if token has expired
    if (!empty($invoice['share_token_expires_at'])) {
        $expires_at = strtotime($invoice['share_token_expires_at']);
        if ($expires_at < time()) {
            die('This invoice link has expired. Please contact ' . COMPANY_NAME . ' for a new link.');
        }
    }
    
    // Track the view
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $is_first_view = empty($invoice['viewed_at']);
    
    if ($is_first_view) {
        // First time viewing
        $stmt = $pdo->prepare("
            UPDATE invoices 
            SET viewed_at = NOW(), 
                view_count = 1, 
                last_viewed_at = NOW(), 
                last_viewed_ip = ?
            WHERE id = ?
        ");
        $stmt->execute([$client_ip, $invoice['id']]);
    } else {
        // Subsequent views
        $stmt = $pdo->prepare("
            UPDATE invoices 
            SET view_count = view_count + 1, 
                last_viewed_at = NOW(), 
                last_viewed_ip = ?
            WHERE id = ?
        ");
        $stmt->execute([$client_ip, $invoice['id']]);
    }
    
    // Fetch invoice items
    $stmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id");
    $stmt->execute([$invoice['id']]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch payments
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE invoice_id = ? ORDER BY payment_date");
    $stmt->execute([$invoice['id']]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate paid amount and balance
    $paid_amount = 0;
    foreach ($payments as $payment) {
        $paid_amount += $payment['amount'];
    }
    $balance_due = $invoice['total'] - $paid_amount;
    
    // Fetch invoice footer setting
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute(['invoice_footer']);
    $invoice_footer = $stmt->fetchColumn();
    
    // Fetch company logo setting
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute(['company_logo']);
    $company_logo = $stmt->fetchColumn();
    
    // Use config constant as fallback
    if (empty($company_logo) && defined('COMPANY_LOGO')) {
        $company_logo = COMPANY_LOGO;
    }
    
} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?php echo htmlspecialchars($invoice['invoice_number']); ?> - <?php echo COMPANY_NAME; ?></title>
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
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #34495e 0%, #2c3e50 100%);
            color: white;
            padding: 40px;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
        }
        
        .header-logo {
            max-width: 200px;
            max-height: 80px;
            object-fit: contain;
            background: white;
            padding: 10px;
            border-radius: 8px;
        }
        
        .header h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .header p {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .invoice-content {
            padding: 40px;
        }
        
        .invoice-header {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 40px;
            padding-bottom: 30px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .company-info {
            display: flex;
            align-items: flex-start;
            gap: 20px;
        }
        
        .company-logo-section {
            flex-shrink: 0;
        }
        
        .company-logo-img {
            max-width: 150px;
            max-height: 80px;
            object-fit: contain;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            padding: 8px;
            background: white;
        }
        
        .company-details h2 {
            color: #34495e;
            font-size: 24px;
            margin-bottom: 15px;
        }
        
        .company-details p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 5px;
        }
        
        .invoice-details {
            text-align: right;
        }
        
        .invoice-details h3 {
            color: #34495e;
            font-size: 20px;
            margin-bottom: 15px;
        }
        
        .detail-row {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 8px;
        }
        
        .detail-label {
            font-weight: 600;
            color: #666;
            margin-right: 10px;
        }
        
        .detail-value {
            color: #333;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-badge.paid {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-badge.unpaid {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .status-badge.overdue {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-badge.partially_paid {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .bill-to {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        .bill-to h3 {
            color: #34495e;
            font-size: 16px;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .bill-to p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 5px;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        
        .items-table thead {
            background: #34495e;
            color: white;
        }
        
        .items-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .items-table td {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
            color: #333;
        }
        
        .items-table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .repair-details-row {
            background: #f9fafb !important;
        }
        
        .repair-details-row td {
            padding: 0 !important;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .repair-details-container {
            padding: 15px;
        }
        
        .repair-details-label {
            display: block;
            color: #6b7280;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 8px;
            text-transform: uppercase;
        }
        
        .repair-details-text {
            color: #374151;
            line-height: 1.6;
            white-space: pre-wrap;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .totals-section {
            margin-left: auto;
            width: 350px;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .total-row:last-child {
            border-bottom: none;
        }
        
        .total-label {
            font-weight: 600;
            color: #666;
        }
        
        .total-value {
            font-weight: 600;
            color: #333;
        }
        
        .grand-total {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
        }
        
        .grand-total .total-label,
        .grand-total .total-value {
            color: white;
            font-size: 18px;
        }
        
        .balance-due {
            background: #fee2e2;
            color: #991b1b;
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
            border: 2px solid #fca5a5;
        }
        
        .balance-due .total-label,
        .balance-due .total-value {
            color: #991b1b;
            font-weight: 700;
        }
        
        .payments-section {
            background: #d1fae5;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #10b981;
        }
        
        .payments-section h3 {
            color: #065f46;
            margin-bottom: 15px;
        }
        
        .payment-item {
            padding: 10px 0;
            border-bottom: 1px solid #a7f3d0;
            color: #065f46;
        }
        
        .payment-item:last-child {
            border-bottom: none;
        }
        
        .notes-section {
            background: #fff3cd;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #ffc107;
        }
        
        .notes-section h3 {
            color: #856404;
            margin-bottom: 10px;
        }
        
        .notes-section p {
            color: #856404;
            line-height: 1.6;
        }
        
        .footer-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 30px;
            text-align: center;
        }
        
        .footer-section p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 10px;
        }
        
        .actions {
            text-align: center;
            padding: 30px;
            background: #f8f9fa;
            border-top: 2px solid #e9ecef;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            transition: all 0.3s;
            margin: 5px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
        }
        
        .expiry-notice {
            background: #fef3c7;
            border: 1px solid #fbbf24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .expiry-notice p {
            color: #92400e;
            font-weight: 600;
        }
        
        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .container {
                box-shadow: none;
            }
            
            .actions {
                display: none;
            }
            
            .expiry-notice {
                display: none;
            }
        }
        
        @media (max-width: 768px) {
            .invoice-header {
                grid-template-columns: 1fr;
            }
            
            .invoice-details {
                text-align: left;
            }
            
            .detail-row {
                justify-content: flex-start;
            }
            
            .totals-section {
                width: 100%;
            }
            
            .items-table {
                font-size: 12px;
            }
            
            .items-table th,
            .items-table td {
                padding: 10px 5px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <?php if (!empty($company_logo) && file_exists($company_logo)): ?>
                <img src="<?php echo htmlspecialchars($company_logo); ?>" alt="<?php echo htmlspecialchars(COMPANY_NAME); ?> Logo" class="header-logo">
            <?php endif; ?>
            <h1><?php echo COMPANY_NAME; ?></h1>
            <p>Professional Invoice</p>
        </div>
        
        <div class="invoice-content">
            <?php
            // Show expiry notice if link expires soon (within 7 days)
            if (!empty($invoice['share_token_expires_at'])) {
                $expires_at = strtotime($invoice['share_token_expires_at']);
                $days_until_expiry = floor(($expires_at - time()) / 86400);
                if ($days_until_expiry <= 7 && $days_until_expiry > 0) {
                    echo '<div class="expiry-notice">';
                    echo '<p>⚠️ This invoice link will expire in ' . $days_until_expiry . ' day' . ($days_until_expiry != 1 ? 's' : '') . '</p>';
                    echo '</div>';
                }
            }
            ?>
            
            <div class="invoice-header">
                <div class="company-info">
                    <?php if (!empty($company_logo) && file_exists($company_logo)): ?>
                        <div class="company-logo-section">
                            <img src="<?php echo htmlspecialchars($company_logo); ?>" alt="Company Logo" class="company-logo-img">
                        </div>
                    <?php endif; ?>
                    <div class="company-details">
                        <h2><?php echo COMPANY_NAME; ?></h2>
                        <p><?php echo nl2br(COMPANY_ADDRESS); ?></p>
                        <p>Email: <?php echo COMPANY_EMAIL; ?></p>
                        <p>Phone: <?php echo COMPANY_PHONE; ?></p>
                    </div>
                </div>
                
                <div class="invoice-details">
                    <h3>INVOICE</h3>
                    <div class="detail-row">
                        <span class="detail-label">Invoice #:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($invoice['invoice_number']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Date:</span>
                        <span class="detail-value"><?php echo date('M d, Y', strtotime($invoice['invoice_date'])); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Due Date:</span>
                        <span class="detail-value"><?php echo date('M d, Y', strtotime($invoice['due_date'])); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Status:</span>
                        <span class="detail-value">
                            <span class="status-badge <?php echo strtolower($invoice['status']); ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $invoice['status'])); ?>
                            </span>
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="bill-to">
                <h3>Bill To</h3>
                <p><strong><?php echo htmlspecialchars($invoice['client_name']); ?></strong></p>
                <?php if (!empty($invoice['client_company'])): ?>
                    <p><?php echo htmlspecialchars($invoice['client_company']); ?></p>
                <?php endif; ?>
                <p><?php echo htmlspecialchars($invoice['client_address']); ?></p>
                <p>Email: <?php echo htmlspecialchars($invoice['client_email']); ?></p>
                <p>Phone: <?php echo htmlspecialchars($invoice['client_phone']); ?></p>
            </div>
            
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th class="text-center">Quantity</th>
                        <th class="text-right">Unit Price</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['description']); ?></td>
                            <td class="text-center"><?php echo number_format($item['quantity'], 2); ?></td>
                            <td class="text-right"><?php echo get_currency_symbol() . number_format($item['unit_price'], 2); ?></td>
                            <td class="text-right"><?php echo get_currency_symbol() . number_format($item['line_total'], 2); ?></td>
                        </tr>
                        <?php if (!empty($item['repair_details'])): ?>
                            <tr class="repair-details-row">
                                <td colspan="4">
                                    <div class="repair-details-container">
                                        <span class="repair-details-label">🔧 Work Details:</span>
                                        <div class="repair-details-text"><?php echo nl2br(htmlspecialchars($item['repair_details'])); ?></div>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if (!empty($payments)): ?>
                <div class="payments-section">
                    <h3>💳 Payment History</h3>
                    <?php foreach ($payments as $payment): ?>
                        <div class="payment-item">
                            <strong><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></strong> - 
                            <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?>: 
                            <strong><?php echo get_currency_symbol() . number_format($payment['amount'], 2); ?></strong>
                            <?php if (!empty($payment['transaction_id'])): ?>
                                (Ref: <?php echo htmlspecialchars($payment['transaction_id']); ?>)
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <div class="totals-section">
                <div class="total-row">
                    <span class="total-label">Subtotal:</span>
                    <span class="total-value"><?php echo get_currency_symbol() . number_format($invoice['subtotal'], 2); ?></span>
                </div>
                
                <?php if ($invoice['discount_amount'] > 0): ?>
                    <div class="total-row">
                        <span class="total-label">
                            Discount
                            <?php if ($invoice['discount_type'] == 'percentage'): ?>
                                (<?php echo $invoice['discount_value']; ?>%)
                            <?php endif; ?>:
                        </span>
                        <span class="total-value">-<?php echo get_currency_symbol() . number_format($invoice['discount_amount'], 2); ?></span>
                    </div>
                <?php endif; ?>
                
                <div class="total-row">
                    <span class="total-label">Tax (<?php echo number_format($invoice['tax_rate'] * 100, 2); ?>%):</span>
                    <span class="total-value"><?php echo get_currency_symbol() . number_format($invoice['tax_amount'], 2); ?></span>
                </div>
                
                <div class="total-row grand-total">
                    <span class="total-label">Total:</span>
                    <span class="total-value"><?php echo get_currency_symbol() . number_format($invoice['total'], 2); ?></span>
                </div>
                
                <?php if ($paid_amount > 0): ?>
                    <div class="total-row" style="margin-top: 10px;">
                        <span class="total-label">Amount Paid:</span>
                        <span class="total-value"><?php echo get_currency_symbol() . number_format($paid_amount, 2); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if ($balance_due > 0): ?>
                    <div class="total-row balance-due">
                        <span class="total-label">Balance Due:</span>
                        <span class="total-value"><?php echo get_currency_symbol() . number_format($balance_due, 2); ?></span>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($invoice['notes'])): ?>
                <div class="notes-section">
                    <h3>📝 Notes</h3>
                    <p><?php echo nl2br(htmlspecialchars($invoice['notes'])); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($invoice_footer)): ?>
                <div class="footer-section">
                    <p><?php echo nl2br(htmlspecialchars($invoice_footer)); ?></p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="actions">
            <a href="pdf_invoice.php?id=<?php echo $invoice['id']; ?>" target="_blank" class="btn">
                📄 Download PDF
            </a>
            <button onclick="window.print()" class="btn btn-secondary">
                🖨️ Print Invoice
            </button>
        </div>
    </div>
</body>
</html>
