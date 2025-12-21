<?php
/**
 * Export Report to CSV
 * Generates CSV exports for revenue and invoice reports
 */

require_once 'config.php';
require_once 'db.php';

// Initialize database connection
$pdo = getDBConnection();

// Get parameters
$type = isset($_GET['type']) ? $_GET['type'] : 'revenue';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Validate dates
if (!strtotime($start_date) || !strtotime($end_date)) {
    die('Invalid date format');
}

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $type . '_report_' . date('Y-m-d') . '.csv"');

// Create output stream
$output = fopen('php://output', 'w');

try {
    if ($type === 'revenue') {
        // Revenue Report
        fputcsv($output, ['Revenue Report']);
        fputcsv($output, ['Period: ' . $start_date . ' to ' . $end_date]);
        fputcsv($output, []);
        fputcsv($output, ['Invoice Number', 'Client', 'Date', 'Amount', 'Status', 'Paid Amount', 'Balance']);
        
        $stmt = $pdo->prepare("
            SELECT 
                i.invoice_number,
                c.name as client_name,
                i.invoice_date,
                i.total,
                i.status,
                COALESCE(SUM(p.amount), 0) as paid_amount,
                (i.total - COALESCE(SUM(p.amount), 0)) as balance
            FROM invoices i
            LEFT JOIN clients c ON i.client_id = c.id
            LEFT JOIN payments p ON i.id = p.invoice_id
            WHERE i.invoice_date BETWEEN ? AND ?
            GROUP BY i.id
            ORDER BY i.invoice_date DESC
        ");
        $stmt->execute([$start_date, $end_date]);
        
        $total_revenue = 0;
        $total_paid = 0;
        $total_balance = 0;
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [
                $row['invoice_number'],
                $row['client_name'],
                $row['invoice_date'],
                number_format($row['total'], 2),
                ucfirst($row['status']),
                number_format($row['paid_amount'], 2),
                number_format($row['balance'], 2)
            ]);
            
            $total_revenue += $row['total'];
            $total_paid += $row['paid_amount'];
            $total_balance += $row['balance'];
        }
        
        fputcsv($output, []);
        fputcsv($output, ['Total Revenue', '', '', number_format($total_revenue, 2), '', number_format($total_paid, 2), number_format($total_balance, 2)]);
        
    } elseif ($type === 'invoices') {
        // Detailed Invoice Report
        fputcsv($output, ['Invoice Report']);
        fputcsv($output, ['Period: ' . $start_date . ' to ' . $end_date]);
        fputcsv($output, []);
        fputcsv($output, ['Invoice Number', 'Client', 'Email', 'Date', 'Due Date', 'Subtotal', 'Tax', 'Total', 'Status']);
        
        $stmt = $pdo->prepare("
            SELECT 
                i.invoice_number,
                c.name as client_name,
                c.email as client_email,
                i.invoice_date,
                i.due_date,
                i.subtotal,
                i.tax_amount,
                i.total,
                i.status
            FROM invoices i
            LEFT JOIN clients c ON i.client_id = c.id
            WHERE i.invoice_date BETWEEN ? AND ?
            ORDER BY i.invoice_date DESC
        ");
        $stmt->execute([$start_date, $end_date]);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [
                $row['invoice_number'],
                $row['client_name'],
                $row['client_email'],
                $row['invoice_date'],
                $row['due_date'],
                number_format($row['subtotal'], 2),
                number_format($row['tax_amount'], 2),
                number_format($row['total'], 2),
                ucfirst($row['status'])
            ]);
        }
        
    } elseif ($type === 'clients') {
        // Client Report
        fputcsv($output, ['Client Report']);
        fputcsv($output, ['Period: ' . $start_date . ' to ' . $end_date]);
        fputcsv($output, []);
        fputcsv($output, ['Client Name', 'Email', 'Phone', 'Total Invoices', 'Total Amount', 'Paid Amount', 'Outstanding']);
        
        $stmt = $pdo->prepare("
            SELECT 
                c.name,
                c.email,
                c.phone,
                COUNT(i.id) as invoice_count,
                COALESCE(SUM(i.total), 0) as total_amount,
                COALESCE(SUM(p.amount), 0) as paid_amount,
                (COALESCE(SUM(i.total), 0) - COALESCE(SUM(p.amount), 0)) as outstanding
            FROM clients c
            LEFT JOIN invoices i ON c.id = i.client_id AND i.invoice_date BETWEEN ? AND ?
            LEFT JOIN payments p ON i.id = p.invoice_id
            GROUP BY c.id
            HAVING invoice_count > 0
            ORDER BY total_amount DESC
        ");
        $stmt->execute([$start_date, $end_date]);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [
                $row['name'],
                $row['email'],
                $row['phone'],
                $row['invoice_count'],
                number_format($row['total_amount'], 2),
                number_format($row['paid_amount'], 2),
                number_format($row['outstanding'], 2)
            ]);
        }
        
    } elseif ($type === 'payments') {
        // Payment Report
        fputcsv($output, ['Payment Report']);
        fputcsv($output, ['Period: ' . $start_date . ' to ' . $end_date]);
        fputcsv($output, []);
        fputcsv($output, ['Date', 'Invoice Number', 'Client', 'Amount', 'Method', 'Transaction ID', 'Notes']);
        
        $stmt = $pdo->prepare("
            SELECT 
                p.payment_date,
                i.invoice_number,
                c.name as client_name,
                p.amount,
                p.payment_method,
                p.transaction_id,
                p.notes
            FROM payments p
            LEFT JOIN invoices i ON p.invoice_id = i.id
            LEFT JOIN clients c ON i.client_id = c.id
            WHERE p.payment_date BETWEEN ? AND ?
            ORDER BY p.payment_date DESC
        ");
        $stmt->execute([$start_date, $end_date]);
        
        $total_payments = 0;
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [
                $row['payment_date'],
                $row['invoice_number'],
                $row['client_name'],
                number_format($row['amount'], 2),
                ucfirst(str_replace('_', ' ', $row['payment_method'])),
                $row['transaction_id'],
                $row['notes']
            ]);
            
            $total_payments += $row['amount'];
        }
        
        fputcsv($output, []);
        fputcsv($output, ['Total Payments', '', '', number_format($total_payments, 2)]);
        
    } else {
        fputcsv($output, ['Error: Invalid report type']);
    }
    
} catch (PDOException $e) {
    fputcsv($output, ['Error: ' . $e->getMessage()]);
}

fclose($output);
exit;
?>
