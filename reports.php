<?php
/**
 * Reports Module
 * Provides analytics and reporting for invoices, payments, and clients
 */

require_once 'config.php';
require_once 'db.php';

// Initialize database connection
$pdo = getDBConnection();

// Get report parameters
$report_type = isset($_GET['type']) ? $_GET['type'] : 'revenue';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); // First day of current month
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t'); // Last day of current month

try {
    // Revenue Report
    if ($report_type == 'revenue') {
        $stmt = $pdo->prepare("
            SELECT 
                DATE_FORMAT(invoice_date, '%Y-%m') as month,
                COUNT(*) as invoice_count,
                SUM(subtotal) as total_subtotal,
                SUM(tax_amount) as total_tax,
                SUM(discount_amount) as total_discount,
                SUM(total) as total_revenue
            FROM invoices
            WHERE invoice_date BETWEEN ? AND ?
            GROUP BY DATE_FORMAT(invoice_date, '%Y-%m')
            ORDER BY month DESC
        ");
        $stmt->execute([$start_date, $end_date]);
        $revenue_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get total revenue
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_invoices,
                SUM(total) as total_revenue,
                AVG(total) as avg_invoice_value
            FROM invoices
            WHERE invoice_date BETWEEN ? AND ?
        ");
        $stmt->execute([$start_date, $end_date]);
        $revenue_summary = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Outstanding Payments Report
    if ($report_type == 'outstanding') {
        $stmt = $pdo->prepare("
            SELECT 
                i.id,
                i.invoice_number,
                i.invoice_date,
                i.due_date,
                i.total,
                i.status,
                c.name as client_name,
                c.email as client_email,
                COALESCE(SUM(p.amount), 0) as paid_amount,
                (i.total - COALESCE(SUM(p.amount), 0)) as balance_due,
                DATEDIFF(CURDATE(), i.due_date) as days_overdue
            FROM invoices i
            JOIN clients c ON i.client_id = c.id
            LEFT JOIN payments p ON i.id = p.invoice_id
            WHERE i.status IN ('unpaid', 'overdue', 'partially_paid')
            AND i.invoice_date BETWEEN ? AND ?
            GROUP BY i.id
            HAVING balance_due > 0
            ORDER BY i.due_date ASC
        ");
        $stmt->execute([$start_date, $end_date]);
        $outstanding_invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate totals
        $total_outstanding = 0;
        $total_overdue = 0;
        foreach ($outstanding_invoices as $inv) {
            $total_outstanding += $inv['balance_due'];
            if ($inv['days_overdue'] > 0) {
                $total_overdue += $inv['balance_due'];
            }
        }
    }
    
    // Client History Report
    if ($report_type == 'clients') {
        $stmt = $pdo->prepare("
            SELECT 
                c.id,
                c.name,
                c.email,
                c.phone,
                COUNT(i.id) as total_invoices,
                SUM(i.total) as total_billed,
                COALESCE(SUM(p.amount), 0) as total_paid,
                (SUM(i.total) - COALESCE(SUM(p.amount), 0)) as outstanding_balance,
                MAX(i.invoice_date) as last_invoice_date
            FROM clients c
            LEFT JOIN invoices i ON c.id = i.client_id AND i.invoice_date BETWEEN ? AND ?
            LEFT JOIN payments p ON i.id = p.invoice_id
            GROUP BY c.id
            HAVING total_invoices > 0
            ORDER BY total_billed DESC
        ");
        $stmt->execute([$start_date, $end_date]);
        $client_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Payment History Report
    if ($report_type == 'payments') {
        $stmt = $pdo->prepare("
            SELECT 
                p.id,
                p.payment_date,
                p.amount,
                p.payment_method,
                p.transaction_id,
                i.invoice_number,
                c.name as client_name
            FROM payments p
            JOIN invoices i ON p.invoice_id = i.id
            JOIN clients c ON i.client_id = c.id
            WHERE p.payment_date BETWEEN ? AND ?
            ORDER BY p.payment_date DESC
        ");
        $stmt->execute([$start_date, $end_date]);
        $payment_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Payment method breakdown
        $stmt = $pdo->prepare("
            SELECT 
                payment_method,
                COUNT(*) as transaction_count,
                SUM(amount) as total_amount
            FROM payments
            WHERE payment_date BETWEEN ? AND ?
            GROUP BY payment_method
            ORDER BY total_amount DESC
        ");
        $stmt->execute([$start_date, $end_date]);
        $payment_methods = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Total payments
        $total_payments = array_sum(array_column($payment_history, 'amount'));
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
    <title>Reports - GeekMobile Invoice System</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .report-filters {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .report-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #dee2e6;
        }
        .report-tab {
            padding: 10px 20px;
            background: #f8f9fa;
            border: none;
            cursor: pointer;
            text-decoration: none;
            color: #495057;
            border-radius: 5px 5px 0 0;
        }
        .report-tab.active {
            background: #007bff;
            color: white;
        }
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .summary-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-left: 4px solid #007bff;
        }
        .summary-card h3 {
            margin: 0 0 10px 0;
            color: #6c757d;
            font-size: 14px;
            text-transform: uppercase;
        }
        .summary-card .value {
            font-size: 28px;
            font-weight: bold;
            color: #212529;
        }
        .report-table {
            width: 100%;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .report-table th {
            background: #343a40;
            color: white;
            padding: 12px;
            text-align: left;
        }
        .report-table td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
        }
        .report-table tr:hover {
            background: #f8f9fa;
        }
        .export-btn {
            background: #28a745;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-paid { background: #d4edda; color: #155724; }
        .status-unpaid { background: #fff3cd; color: #856404; }
        .status-overdue { background: #f8d7da; color: #721c24; }
        .status-partially_paid { background: #d1ecf1; color: #0c5460; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Reports & Analytics</h1>
        
        <!-- Report Filters -->
        <div class="report-filters">
            <form method="GET" action="reports.php">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end;">
                    <div>
                        <label>Report Type:</label>
                        <select name="type" class="form-control">
                            <option value="revenue" <?php echo $report_type == 'revenue' ? 'selected' : ''; ?>>Revenue Report</option>
                            <option value="outstanding" <?php echo $report_type == 'outstanding' ? 'selected' : ''; ?>>Outstanding Payments</option>
                            <option value="clients" <?php echo $report_type == 'clients' ? 'selected' : ''; ?>>Client History</option>
                            <option value="payments" <?php echo $report_type == 'payments' ? 'selected' : ''; ?>>Payment History</option>
                        </select>
                    </div>
                    <div>
                        <label>Start Date:</label>
                        <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" class="form-control">
                    </div>
                    <div>
                        <label>End Date:</label>
                        <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" class="form-control">
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary">Generate Report</button>
                        <a href="export_report.php?type=<?php echo $report_type; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="export-btn">Export CSV</a>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Revenue Report -->
        <?php if ($report_type == 'revenue'): ?>
            <h2>Revenue Report</h2>
            <p>Period: <?php echo date('M d, Y', strtotime($start_date)); ?> to <?php echo date('M d, Y', strtotime($end_date)); ?></p>
            
            <div class="summary-cards">
                <div class="summary-card">
                    <h3>Total Revenue</h3>
                    <div class="value"><?php echo CURRENCY_SYMBOL . number_format($revenue_summary['total_revenue'] ?? 0, 2); ?></div>
                </div>
                <div class="summary-card">
                    <h3>Total Invoices</h3>
                    <div class="value"><?php echo number_format($revenue_summary['total_invoices'] ?? 0); ?></div>
                </div>
                <div class="summary-card">
                    <h3>Average Invoice</h3>
                    <div class="value"><?php echo CURRENCY_SYMBOL . number_format($revenue_summary['avg_invoice_value'] ?? 0, 2); ?></div>
                </div>
            </div>
            
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Invoices</th>
                        <th>Subtotal</th>
                        <th>Tax</th>
                        <th>Discount</th>
                        <th>Total Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($revenue_data)): ?>
                        <?php foreach ($revenue_data as $row): ?>
                            <tr>
                                <td><?php echo date('F Y', strtotime($row['month'] . '-01')); ?></td>
                                <td><?php echo number_format($row['invoice_count']); ?></td>
                                <td><?php echo CURRENCY_SYMBOL . number_format($row['total_subtotal'], 2); ?></td>
                                <td><?php echo CURRENCY_SYMBOL . number_format($row['total_tax'], 2); ?></td>
                                <td><?php echo CURRENCY_SYMBOL . number_format($row['total_discount'], 2); ?></td>
                                <td><strong><?php echo CURRENCY_SYMBOL . number_format($row['total_revenue'], 2); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 20px;">No revenue data for selected period</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <!-- Outstanding Payments Report -->
        <?php if ($report_type == 'outstanding'): ?>
            <h2>Outstanding Payments</h2>
            <p>Period: <?php echo date('M d, Y', strtotime($start_date)); ?> to <?php echo date('M d, Y', strtotime($end_date)); ?></p>
            
            <div class="summary-cards">
                <div class="summary-card">
                    <h3>Total Outstanding</h3>
                    <div class="value"><?php echo CURRENCY_SYMBOL . number_format($total_outstanding ?? 0, 2); ?></div>
                </div>
                <div class="summary-card">
                    <h3>Overdue Amount</h3>
                    <div class="value" style="color: #dc3545;"><?php echo CURRENCY_SYMBOL . number_format($total_overdue ?? 0, 2); ?></div>
                </div>
                <div class="summary-card">
                    <h3>Unpaid Invoices</h3>
                    <div class="value"><?php echo count($outstanding_invoices ?? []); ?></div>
                </div>
            </div>
            
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Client</th>
                        <th>Invoice Date</th>
                        <th>Due Date</th>
                        <th>Total</th>
                        <th>Paid</th>
                        <th>Balance Due</th>
                        <th>Days Overdue</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($outstanding_invoices)): ?>
                        <?php foreach ($outstanding_invoices as $inv): ?>
                            <tr>
                                <td><a href="view_invoices.php?id=<?php echo $inv['id']; ?>"><?php echo htmlspecialchars($inv['invoice_number']); ?></a></td>
                                <td><?php echo htmlspecialchars($inv['client_name']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($inv['invoice_date'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($inv['due_date'])); ?></td>
                                <td><?php echo CURRENCY_SYMBOL . number_format($inv['total'], 2); ?></td>
                                <td><?php echo CURRENCY_SYMBOL . number_format($inv['paid_amount'], 2); ?></td>
                                <td><strong><?php echo CURRENCY_SYMBOL . number_format($inv['balance_due'], 2); ?></strong></td>
                                <td><?php echo $inv['days_overdue'] > 0 ? '<span style="color: red;">' . $inv['days_overdue'] . ' days</span>' : '-'; ?></td>
                                <td><span class="status-badge status-<?php echo $inv['status']; ?>"><?php echo strtoupper($inv['status']); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 20px;">No outstanding invoices for selected period</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <!-- Client History Report -->
        <?php if ($report_type == 'clients'): ?>
            <h2>Client History Report</h2>
            <p>Period: <?php echo date('M d, Y', strtotime($start_date)); ?> to <?php echo date('M d, Y', strtotime($end_date)); ?></p>
            
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Client Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Total Invoices</th>
                        <th>Total Billed</th>
                        <th>Total Paid</th>
                        <th>Outstanding</th>
                        <th>Last Invoice</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($client_data)): ?>
                        <?php foreach ($client_data as $client): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($client['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($client['email']); ?></td>
                                <td><?php echo htmlspecialchars($client['phone']); ?></td>
                                <td><?php echo number_format($client['total_invoices']); ?></td>
                                <td><?php echo CURRENCY_SYMBOL . number_format($client['total_billed'], 2); ?></td>
                                <td><?php echo CURRENCY_SYMBOL . number_format($client['total_paid'], 2); ?></td>
                                <td><?php echo CURRENCY_SYMBOL . number_format($client['outstanding_balance'], 2); ?></td>
                                <td><?php echo $client['last_invoice_date'] ? date('M d, Y', strtotime($client['last_invoice_date'])) : '-'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 20px;">No client data for selected period</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <!-- Payment History Report -->
        <?php if ($report_type == 'payments'): ?>
            <h2>Payment History Report</h2>
            <p>Period: <?php echo date('M d, Y', strtotime($start_date)); ?> to <?php echo date('M d, Y', strtotime($end_date)); ?></p>
            
            <div class="summary-cards">
                <div class="summary-card">
                    <h3>Total Payments Received</h3>
                    <div class="value"><?php echo CURRENCY_SYMBOL . number_format($total_payments ?? 0, 2); ?></div>
                </div>
                <div class="summary-card">
                    <h3>Number of Transactions</h3>
                    <div class="value"><?php echo count($payment_history ?? []); ?></div>
                </div>
            </div>
            
            <!-- Payment Methods Breakdown -->
            <?php if (!empty($payment_methods)): ?>
                <h3>Payment Methods Breakdown</h3>
                <table class="report-table" style="margin-bottom: 30px;">
                    <thead>
                        <tr>
                            <th>Payment Method</th>
                            <th>Transactions</th>
                            <th>Total Amount</th>
                            <th>Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payment_methods as $method): ?>
                            <tr>
                                <td><?php echo ucfirst(str_replace('_', ' ', $method['payment_method'])); ?></td>
                                <td><?php echo number_format($method['transaction_count']); ?></td>
                                <td><?php echo CURRENCY_SYMBOL . number_format($method['total_amount'], 2); ?></td>
                                <td><?php echo number_format(($method['total_amount'] / $total_payments) * 100, 1); ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
            <!-- Payment History Table -->
            <h3>Payment Transactions</h3>
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Invoice #</th>
                        <th>Client</th>
                        <th>Amount</th>
                        <th>Payment Method</th>
                        <th>Transaction ID</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($payment_history)): ?>
                        <?php foreach ($payment_history as $payment): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                                <td><?php echo htmlspecialchars($payment['invoice_number']); ?></td>
                                <td><?php echo htmlspecialchars($payment['client_name']); ?></td>
                                <td><strong><?php echo CURRENCY_SYMBOL . number_format($payment['amount'], 2); ?></strong></td>
                                <td><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></td>
                                <td><?php echo htmlspecialchars($payment['transaction_id'] ?? '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 20px;">No payment transactions for selected period</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <div style="margin-top: 30px;">
            <a href="index.php" class="btn">← Back to Dashboard</a>
        </div>
    </div>
</body>
</html>
