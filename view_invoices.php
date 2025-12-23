<?php
require_once 'db.php';
require_once 'config.php';

// Initialize database connection
$pdo = getDBConnection();

// Handle payment update
$message = '';
$message_type = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payment'])) {
    $invoice_id = (int)$_POST['invoice_id'];
    $payment_status = $_POST['payment_status'];
    $payment_method = trim($_POST['payment_method'] ?? '');
    $payment_date = $_POST['payment_date'] ?? date('Y-m-d');

    if (in_array($payment_status, ['unpaid', 'paid', 'overdue', 'partially_paid'])) {
        $stmt = $pdo->prepare('UPDATE invoices SET status = ? WHERE id = ?');
        $stmt->execute([$payment_status, $invoice_id]);

        // Insert payment record if paid
        if ($payment_status === 'paid' || $payment_status === 'partially_paid') {
            $stmt = $pdo->prepare('SELECT total FROM invoices WHERE id = ?');
            $stmt->execute([$invoice_id]);
            $total = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare('INSERT INTO payments (invoice_id, amount, payment_method, payment_date) VALUES (?, ?, ?, ?)');
            $stmt->execute([$invoice_id, $total, $payment_method, $payment_date]);
        }

        $message = 'Payment status updated successfully!';
        $message_type = 'success';
    } else {
        $message = 'Invalid payment status.';
        $message_type = 'error';
    }
}

// Handle reminder sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reminder'])) {
    $invoice_id = (int)$_POST['invoice_id'];
    $stmt = $pdo->prepare('SELECT i.*, c.email, c.name FROM invoices i JOIN clients c ON i.client_id = c.id WHERE i.id = ?');
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch();

    if ($invoice) {
        // Get correct currency symbol
        $currency = function_exists('get_currency_symbol') ? get_currency_symbol() : '$';
        
        $subject = 'Invoice Reminder: Trash 2 Go Auckland';
        $body = "Dear {$invoice['name']},\n\nThis is a reminder for your outstanding invoice #{$invoice_id} due on {$invoice['due_date']}. Total: " . $currency . number_format($invoice['total'], 2) . "\n\nPlease make payment at your earliest convenience.\n\nRegards,\nTrash 2 Go Invoices\nPhone: " . COMPANY_PHONE . "\nWebsite: https://trash2go.co.nz";
        $headers = "From: " . FROM_EMAIL . "\r\n";

        if (mail($invoice['email'], $subject, $body, $headers)) {
            $message = 'Reminder sent successfully!';
            $message_type = 'success';
        } else {
            $message = 'Failed to send reminder.';
            $message_type = 'error';
        }
    }
}

// Handle invoice deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_invoice'])) {
    $invoice_id = (int)$_POST['invoice_id'];

    // Check if invoice exists
    $stmt = $pdo->prepare('SELECT id FROM invoices WHERE id = ?');
    $stmt->execute([$invoice_id]);
    $invoice_exists = $stmt->fetch();

    if ($invoice_exists) {
        // Delete invoice (cascade will handle related records)
        $stmt = $pdo->prepare('DELETE FROM invoices WHERE id = ?');
        if ($stmt->execute([$invoice_id])) {
            $message = 'Invoice deleted successfully!';
            $message_type = 'success';
        } else {
            $message = 'Failed to delete invoice.';
            $message_type = 'error';
        }
    } else {
        $message = 'Invoice not found.';
        $message_type = 'error';
    }
}

// Fetch invoices with statistics
$stmt = $pdo->query('SELECT i.*, c.name AS client_name, c.email AS client_email FROM invoices i JOIN clients c ON i.client_id = c.id ORDER BY i.created_at DESC');
$invoices = $stmt->fetchAll();

// Fetch invoice items with repair details for modal display
$invoice_items = [];
if (!empty($invoices)) {
    $invoice_ids = array_column($invoices, 'id');
    $placeholders = str_repeat('?,', count($invoice_ids) - 1) . '?';
    $stmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id IN ($placeholders) ORDER BY invoice_id, id");
    $stmt->execute($invoice_ids);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group items by invoice_id
    foreach ($items as $item) {
        $invoice_items[$item['invoice_id']][] = $item;
    }
}

// Calculate statistics
$total_invoices = count($invoices);
$total_amount = array_sum(array_column($invoices, 'total'));
$paid_invoices = count(array_filter($invoices, fn($inv) => $inv['status'] === 'paid'));
$unpaid_amount = array_sum(array_map(fn($inv) => $inv['status'] !== 'paid' ? $inv['total'] : 0, $invoices));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Invoices - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css">
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
            max-width: 1400px;
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
        
        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            border-left: 4px solid;
        }
        
        .stat-card.total { border-color: #667eea; }
        .stat-card.paid { border-color: #10b981; }
        .stat-card.unpaid { border-color: #ef4444; }
        .stat-card.amount { border-color: #f59e0b; }
        
        .stat-card h3 {
            color: #666;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        
        .stat-card .value {
            color: #333;
            font-size: 32px;
            font-weight: bold;
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
        
        .invoices-table {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .table-header {
            padding: 20px 30px;
            background: #f8f9fa;
            border-bottom: 2px solid #e9ecef;
        }
        
        .table-header h2 {
            color: #333;
            font-size: 20px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background: #667eea;
            color: white;
        }
        
        th {
            padding: 15px 20px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        tbody tr {
            border-bottom: 1px solid #e9ecef;
            transition: background 0.2s;
        }
        
        tbody tr:hover {
            background: #f8f9fa;
        }
        
        td {
            padding: 20px;
            color: #333;
        }
        
        .invoice-id {
            font-weight: 600;
            color: #667eea;
        }
        
        .client-info {
            display: flex;
            flex-direction: column;
        }
        
        .client-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
        }
        
        .client-email {
            font-size: 12px;
            color: #666;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: capitalize;
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
        
        .amount {
            font-weight: 600;
            font-size: 16px;
            color: #10b981;
        }
        
        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
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
        
        .btn-success {
            background: #10b981;
            color: white;
        }
        
        .btn-info {
            background: #3b82f6;
            color: white;
        }
        
        .btn-warning {
            background: #f59e0b;
            color: white;
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-outline {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
        }
        
        .update-form {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .update-form select,
        .update-form input {
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 13px;
        }
        
        .update-form select {
            min-width: 140px;
        }
        
        .update-form input[type="text"] {
            width: 120px;
        }
        
        .update-form input[type="date"] {
            width: 140px;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            max-width: 900px;
            width: 95%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .invoice-details-header {
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }

        .invoice-details-header h2 {
            color: #333;
            margin-bottom: 10px;
        }

        .invoice-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .meta-item {
            display: flex;
            flex-direction: column;
        }

        .meta-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .meta-value {
            font-size: 14px;
            color: #333;
            font-weight: 500;
        }

        .items-table-modal {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .items-table-modal th {
            background: #667eea;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
        }

        .items-table-modal td {
            padding: 12px;
            border-bottom: 1px solid #e9ecef;
        }

        .repair-details-section {
            background: #f9fafb;
            border-left: 3px solid #667eea;
            padding: 12px;
            margin-top: 8px;
            border-radius: 4px;
        }

        .repair-details-label {
            font-size: 12px;
            color: #6b7280;
            font-weight: 600;
            margin-bottom: 4px;
            text-transform: uppercase;
        }

        .repair-details-text {
            font-size: 13px;
            color: #374151;
            line-height: 1.4;
        }

        .totals-section {
            margin-top: 30px;
            border-top: 2px solid #e9ecef;
            padding-top: 20px;
        }

        .totals-row {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 8px;
        }

        .totals-label {
            font-weight: 500;
            color: #666;
            min-width: 120px;
        }

        .totals-amount {
            font-weight: 600;
            color: #10b981;
        }

        .total-row {
            border-top: 1px solid #e9ecef;
            padding-top: 10px;
        }

        .total-row .totals-amount {
            font-size: 18px;
            color: #333;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .empty-state svg {
            width: 100px;
            height: 100px;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .header-actions {
                flex-direction: column;
                align-items: stretch;
            }
            
            table {
                font-size: 12px;
            }
            
            th, td {
                padding: 10px;
            }
            
            .actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>📊 Invoice Management</h1>
            <div class="header-actions">
                <div>
                    <a href="index.php" class="btn btn-secondary">← Back to Dashboard</a>
                    <a href="create_invoice.php" class="btn btn-primary">+ Create New Invoice</a>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card total">
                <h3>Total Invoices</h3>
                <div class="value"><?php echo $total_invoices; ?></div>
            </div>
            <div class="stat-card paid">
                <h3>Paid Invoices</h3>
                <div class="value"><?php echo $paid_invoices; ?></div>
            </div>
            <div class="stat-card unpaid">
                <h3>Unpaid Amount</h3>
                <div class="value">$<?php echo number_format($unpaid_amount, 2); ?></div>
            </div>
            <div class="stat-card amount">
                <h3>Total Amount</h3>
                <div class="value">$<?php echo number_format($total_amount, 2); ?></div>
            </div>
        </div>

        <!-- Message -->
        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php if ($message_type === 'success'): ?>
                    ✓
                <?php else: ?>
                    ✗
                <?php endif; ?>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Invoices Table -->
        <div class="invoices-table">
            <div class="table-header">
                <h2>All Invoices</h2>
            </div>
            
            <?php if (empty($invoices)): ?>
                <div class="empty-state">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <h3>No invoices yet</h3>
                    <p>Create your first invoice to get started</p>
                    <a href="create_invoice.php" class="btn btn-primary" style="margin-top: 20px;">Create Invoice</a>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Client</th>
                            <th>Due Date</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>View Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoices as $invoice): ?>
                            <tr>
                                <td>
                                    <span class="invoice-id">#<?php echo htmlspecialchars($invoice['invoice_number'] ?? $invoice['id']); ?></span>
                                </td>
                                <td>
                                    <div class="client-info">
                                        <span class="client-name"><?php echo htmlspecialchars($invoice['client_name']); ?></span>
                                        <span class="client-email"><?php echo htmlspecialchars($invoice['client_email']); ?></span>
                                    </div>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($invoice['due_date'])); ?></td>
                                <td>
                                    <span class="amount">$<?php echo number_format($invoice['total'], 2); ?></span>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo strtolower($invoice['status']); ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $invoice['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($invoice['viewed_at'])): ?>
                                        <div style="display: flex; flex-direction: column; gap: 4px;">
                                            <span class="status-badge" style="background: #d1fae5; color: #065f46;">
                                                👁️ Viewed <?php echo $invoice['view_count']; ?> time<?php echo $invoice['view_count'] != 1 ? 's' : ''; ?>
                                            </span>
                                            <span style="font-size: 11px; color: #666;">
                                                First: <?php echo date('M d, Y', strtotime($invoice['viewed_at'])); ?>
                                            </span>
                                            <?php if (!empty($invoice['last_viewed_at'])): ?>
                                                <span style="font-size: 11px; color: #666;">
                                                    Last: <?php echo date('M d, g:i A', strtotime($invoice['last_viewed_at'])); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="status-badge" style="background: #f3f4f6; color: #6b7280;">
                                            ⏳ Not Viewed
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a href="edit_invoice.php?id=<?php echo $invoice['id']; ?>" class="btn btn-warning" title="Edit Invoice">
                                            ✏️ Edit
                                        </a>
                                        <button type="button" class="btn btn-info" onclick="viewInvoiceDetails(<?php echo $invoice['id']; ?>)" title="View Details">
                                            📋 Details
                                        </button>
                                        <a href="pdf_invoice.php?id=<?php echo $invoice['id']; ?>" target="_blank" class="btn btn-info" title="View PDF">
                                            📄 View
                                        </a>
                                        <a href="pdf_invoice.php?id=<?php echo $invoice['id']; ?>&download=1" class="btn btn-success" title="Download PDF">
                                            ⬇️ Download
                                        </a>
                                        <a href="send_invoice_email.php?id=<?php echo $invoice['id']; ?>" class="btn btn-primary" title="Send Email">
                                            ✉️ Email
                                        </a>
                                        <a href="send_invoice_link.php?id=<?php echo $invoice['id']; ?>" class="btn btn-primary" title="Send Shareable Link" style="background: #8b5cf6;">
                                            📤 Send Link
                                        </a>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="invoice_id" value="<?php echo $invoice['id']; ?>">
                                            <button type="submit" name="send_reminder" class="btn btn-warning" title="Send Reminder">
                                                🔔 Remind
                                            </button>
                                        </form>
                                        <form method="post" style="display:inline;" onsubmit="return confirmDelete()">
                                            <input type="hidden" name="invoice_id" value="<?php echo $invoice['id']; ?>">
                                            <button type="submit" name="delete_invoice" class="btn btn-danger" title="Delete Invoice">
                                                🗑️ Delete
                                            </button>
                                        </form>
                                    </div>
                                    <div class="update-form" style="margin-top: 10px;">
                                        <form method="post" style="display: flex; gap: 8px; flex-wrap: wrap;">
                                            <input type="hidden" name="invoice_id" value="<?php echo $invoice['id']; ?>">
                                            <select name="payment_status">
                                                <option value="unpaid" <?php if ($invoice['status'] === 'unpaid') echo 'selected'; ?>>Unpaid</option>
                                                <option value="paid" <?php if ($invoice['status'] === 'paid') echo 'selected'; ?>>Paid</option>
                                                <option value="overdue" <?php if ($invoice['status'] === 'overdue') echo 'selected'; ?>>Overdue</option>
                                                <option value="partially_paid" <?php if ($invoice['status'] === 'partially_paid') echo 'selected'; ?>>Partially Paid</option>
                                            </select>
                                            <input type="text" name="payment_method" placeholder="Method" value="Bank Transfer">
                                            <input type="date" name="payment_date" value="<?php echo date('Y-m-d'); ?>">
                                            <button type="submit" name="update_payment" class="btn btn-outline">Update Status</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Invoice Details Modal -->
    <div id="invoiceModal" class="modal">
        <div class="modal-content">
            <div id="modalContent">
                <!-- Content will be populated by JavaScript -->
            </div>
            <div style="text-align: right; margin-top: 20px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Close</button>
            </div>
        </div>
    </div>

    <script>
        function confirmDelete() {
            return confirm('Are you sure you want to delete this invoice? This action cannot be undone.');
        }

        function viewInvoiceDetails(invoiceId) {
            // Find the invoice data
            const invoices = <?php echo json_encode($invoices); ?>;
            const invoiceItems = <?php echo json_encode($invoice_items); ?>;

            const invoice = invoices.find(inv => inv.id == invoiceId);
            const items = invoiceItems[invoiceId] || [];

            if (!invoice) {
                alert('Invoice not found');
                return;
            }

            // Build modal content
            let modalContent = `
                <div class="invoice-details-header">
                    <h2>Invoice Details</h2>
                    <div class="invoice-meta">
                        <div class="meta-item">
                            <div class="meta-label">Invoice Number</div>
                            <div class="meta-value">#${invoice.invoice_number || invoice.id}</div>
                        </div>
                        <div class="meta-item">
                            <div class="meta-label">Client</div>
                            <div class="meta-value">${invoice.client_name}</div>
                        </div>
                        <div class="meta-item">
                            <div class="meta-label">Invoice Date</div>
                            <div class="meta-value">${new Date(invoice.invoice_date).toLocaleDateString()}</div>
                        </div>
                        <div class="meta-item">
                            <div class="meta-label">Due Date</div>
                            <div class="meta-value">${new Date(invoice.due_date).toLocaleDateString()}</div>
                        </div>
                        <div class="meta-item">
                            <div class="meta-label">Status</div>
                            <div class="meta-value">
                                <span class="status-badge ${invoice.status.toLowerCase()}">
                                    ${invoice.status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <table class="items-table-modal">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th>Quantity</th>
                            <th>Unit Price</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>`;

            items.forEach(item => {
                modalContent += `
                    <tr>
                        <td>${item.description}</td>
                        <td>${parseFloat(item.quantity).toFixed(2)}</td>
                        <td>$${parseFloat(item.unit_price).toFixed(2)}</td>
                        <td>$${parseFloat(item.line_total).toFixed(2)}</td>
                    </tr>`;

                // Add repair details if they exist
                if (item.repair_details && item.repair_details.trim()) {
                    modalContent += `
                    <tr>
                        <td colspan="4">
                            <div class="repair-details-section">
                                <div class="repair-details-label">🔧 Work Details:</div>
                                <div class="repair-details-text">${item.repair_details.replace(/\n/g, '<br>')}</div>
                            </div>
                        </td>
                    </tr>`;
                }
            });

            modalContent += `
                    </tbody>
                </table>

                <div class="totals-section">
                    <div class="totals-row">
                        <span class="totals-label">Subtotal:</span>
                        <span class="totals-amount">$${parseFloat(invoice.subtotal).toFixed(2)}</span>
                    </div>`;

            if (parseFloat(invoice.discount_amount) > 0) {
                const discountLabel = invoice.discount_type === 'percentage' ?
                    `Discount (${invoice.discount_value}%):` : 'Discount:';
                modalContent += `
                    <div class="totals-row">
                        <span class="totals-label">${discountLabel}</span>
                        <span class="totals-amount">$${parseFloat(invoice.discount_amount).toFixed(2)}</span>
                    </div>`;
            }

            modalContent += `
                    <div class="totals-row">
                        <span class="totals-label">Tax (${parseFloat(invoice.tax_rate * 100).toFixed(2)}%):</span>
                        <span class="totals-amount">$${parseFloat(invoice.tax_amount).toFixed(2)}</span>
                    </div>
                    <div class="totals-row total-row">
                        <span class="totals-label">Total:</span>
                        <span class="totals-amount">$${parseFloat(invoice.total).toFixed(2)}</span>
                    </div>
                </div>`;

            document.getElementById('modalContent').innerHTML = modalContent;
            document.getElementById('invoiceModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('invoiceModal').classList.remove('active');
        }

        // Close modal when clicking outside
        document.getElementById('invoiceModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && document.getElementById('invoiceModal').classList.contains('active')) {
                closeModal();
            }
        });
    </script>
</body>
</html>
