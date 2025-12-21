<?php
/**
 * PDF Invoice Generator
 * Generates professional PDF invoices using TCPDF library
 */

require_once 'config.php';
require_once 'db.php';
require_once 'lib/tcpdf/tcpdf.php';

// Initialize database connection
$pdo = getDBConnection();

// Get invoice ID from URL
$invoice_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($invoice_id <= 0) {
    die('Invalid invoice ID');
}

try {
    // Fetch invoice details with client information
    $stmt = $pdo->prepare("
        SELECT i.*, c.name as client_name, c.company as client_company, c.email as client_email,
               c.phone as client_phone, c.address as client_address
        FROM invoices i
        JOIN clients c ON i.client_id = c.id
        WHERE i.id = ?
    ");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$invoice) {
        die('Invoice not found');
    }
    
    // Fetch invoice items
    $stmt = $pdo->prepare("
        SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id
    ");
    $stmt->execute([$invoice_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch payments
    $stmt = $pdo->prepare("
        SELECT * FROM payments WHERE invoice_id = ? ORDER BY payment_date
    ");
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

    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    // Set document information
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor(COMPANY_NAME);
    $pdf->SetTitle('Invoice ' . $invoice['invoice_number']);
    $pdf->SetSubject('Invoice');

    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);

    // Set margins
    $pdf->SetMargins(15, 15, 15);

    // Set auto page break based on number of items
    // If there are fewer than 10 items, try to fit everything on one page
    $auto_page_break = (count($items) >= 10) ? TRUE : FALSE;
    $pdf->SetAutoPageBreak($auto_page_break, 15);
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('helvetica', '', 10);
    
    // Company Header
    $pdf->SetFont('helvetica', 'B', 20);
    $pdf->Cell(0, 10, COMPANY_NAME, 0, 1, 'L');
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->MultiCell(0, 5, COMPANY_ADDRESS . "\n" . 
                          "Email: " . COMPANY_EMAIL . "\n" . 
                          "Phone: " . COMPANY_PHONE, 0, 'L');
    
    $pdf->Ln(5);
    
    // Invoice title
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'INVOICE', 0, 1, 'R');
    
    // Invoice details box
    $pdf->SetFont('helvetica', '', 10);
    $y_position = $pdf->GetY();
    
    // Bill To (Left side)
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(95, 6, 'Bill To:', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    $bill_to_text = $invoice['client_name'] . "\n";
    if (!empty($invoice['client_company'])) {
        $bill_to_text .= $invoice['client_company'] . "\n";
    }
    $bill_to_text .= $invoice['client_address'] . "\n" .
                     "Email: " . $invoice['client_email'] . "\n" .
                     "Phone: " . $invoice['client_phone'];
    $pdf->MultiCell(95, 5, $bill_to_text, 0, 'L');
    
    // Invoice Info (Right side)
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
    
    $pdf->SetX(110);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(40, 5, 'Status:', 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 10);
    $status_color = ($invoice['status'] == 'paid') ? 'green' : (($invoice['status'] == 'overdue') ? 'red' : 'orange');
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 5, strtoupper($invoice['status']), 0, 1, 'R');
    
    $pdf->Ln(10);
    
    // Items table header
    $pdf->SetFillColor(52, 73, 94);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 10);
    
    $pdf->Cell(80, 7, 'Description', 1, 0, 'L', true);
    $pdf->Cell(30, 7, 'Quantity', 1, 0, 'C', true);
    $pdf->Cell(35, 7, 'Unit Price', 1, 0, 'R', true);
    $pdf->Cell(35, 7, 'Total', 1, 1, 'R', true);
    
    // Items table body
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', '', 9);
    
    foreach ($items as $item) {
        $description = $item['description'];
        if ($item['is_milestone'] && $item['milestone_name']) {
            $description = '[Milestone: ' . $item['milestone_name'] . '] ' . $description;
        }
        
        // Store current Y position
        $start_y = $pdf->GetY();
        
        // Draw the main item row
        $pdf->MultiCell(80, 6, $description, 1, 'L', false, 0);
        $pdf->Cell(30, 6, number_format($item['quantity'], 2), 1, 0, 'C');
        $pdf->Cell(35, 6, CURRENCY_SYMBOL . number_format($item['unit_price'], 2), 1, 0, 'R');
        $pdf->Cell(35, 6, CURRENCY_SYMBOL . number_format($item['line_total'], 2), 1, 1, 'R');
        
        // Display repair details if they exist
        if (!empty($item['repair_details'])) {
            // Set background color for repair details section
            $pdf->SetFillColor(249, 250, 251); // Light gray background
            
            // Set smaller font for repair details
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetTextColor(75, 85, 99); // Darker gray text
            
            // Create a cell spanning all columns for repair details
            $x_start = $pdf->GetX();
            $y_start = $pdf->GetY();
            
            // Draw the repair details section with border
            $pdf->Cell(180, 0, '', 'LR', 1, 'L', true); // Top border continuation
            
            // Add label and content
            $pdf->SetX($x_start + 3); // Indent slightly
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->Cell(25, 5, '🔧 Work Details:', 0, 0, 'L', true);
            
            $pdf->SetFont('helvetica', '', 8);
            // Calculate remaining width for text
            $remaining_width = 180 - 6 - 25; // Total width - padding - label width
            
            // Use MultiCell for repair details to handle wrapping
            $pdf->MultiCell($remaining_width, 5, $item['repair_details'], 0, 'L', true, 1, $pdf->GetX(), $pdf->GetY());
            
            // Draw bottom border
            $pdf->SetX($x_start);
            $pdf->Cell(180, 0, '', 'LRB', 1, 'L', true);
            
            // Reset text color
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('helvetica', '', 9);
        }
    }
    
    // Totals section
    $pdf->Ln(5);
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(145, 6, 'Subtotal:', 0, 0, 'R');
    $pdf->Cell(35, 6, CURRENCY_SYMBOL . number_format($invoice['subtotal'], 2), 0, 1, 'R');
    
    if ($invoice['discount_amount'] > 0) {
        $discount_label = 'Discount';
        if ($invoice['discount_type'] == 'percentage') {
            $discount_label .= ' (' . $invoice['discount_value'] . '%)';
        }
        $pdf->Cell(145, 6, $discount_label . ':', 0, 0, 'R');
        $pdf->Cell(35, 6, '-' . CURRENCY_SYMBOL . number_format($invoice['discount_amount'], 2), 0, 1, 'R');
    }
    
    $pdf->Cell(145, 6, 'Tax (' . $invoice['tax_rate'] . '%):', 0, 0, 'R');
    $pdf->Cell(35, 6, CURRENCY_SYMBOL . number_format($invoice['tax_amount'], 2), 0, 1, 'R');
    
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(145, 8, 'Total:', 0, 0, 'R');
    $pdf->Cell(35, 8, CURRENCY_SYMBOL . number_format($invoice['total'], 2), 0, 1, 'R');
    
    // Payment information
    if (!empty($payments)) {
        $pdf->Ln(5);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 6, 'Payment History:', 0, 1, 'L');
        
        $pdf->SetFont('helvetica', '', 9);
        foreach ($payments as $payment) {
            $payment_info = date('M d, Y', strtotime($payment['payment_date'])) . ' - ' . 
                          ucfirst(str_replace('_', ' ', $payment['payment_method'])) . ': ' . 
                          CURRENCY_SYMBOL . number_format($payment['amount'], 2);
            if ($payment['transaction_id']) {
                $payment_info .= ' (Ref: ' . $payment['transaction_id'] . ')';
            }
            $pdf->Cell(0, 5, $payment_info, 0, 1, 'L');
        }
        
        $pdf->Ln(3);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(145, 6, 'Amount Paid:', 0, 0, 'R');
        $pdf->Cell(35, 6, CURRENCY_SYMBOL . number_format($paid_amount, 2), 0, 1, 'R');
        
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(145, 7, 'Balance Due:', 0, 0, 'R');
        $pdf->Cell(35, 7, CURRENCY_SYMBOL . number_format($balance_due, 2), 0, 1, 'R');
    }
    
    // Notes section
    if (!empty($invoice['notes'])) {
        $pdf->Ln(10);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 6, 'Notes:', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->MultiCell(0, 5, $invoice['notes'], 0, 'L');
    }
    
    // Footer
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
    
    // Output PDF
    $filename = 'Invoice_' . $invoice['invoice_number'] . '.pdf';
    
    // Check if download parameter is set
    if (isset($_GET['download']) && $_GET['download'] == '1') {
        $pdf->Output($filename, 'D'); // Force download
    } else {
        $pdf->Output($filename, 'I'); // Display in browser
    }
    
} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
} catch (Exception $e) {
    die('Error generating PDF: ' . $e->getMessage());
}
?>
