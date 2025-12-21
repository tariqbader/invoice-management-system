<?php
require_once 'db.php';
require_once 'config.php';

// Function to validate date is future
function isFutureDate($date) {
    return strtotime($date) > time();
}

// Function to validate numeric positive
function isPositiveNumeric($value) {
    return is_numeric($value) && $value > 0;
}

// Initialize database connection
$pdo = getDBConnection();

// Fetch existing clients for dropdown
$stmt = $pdo->query('SELECT id, name, company, email, phone, address FROM clients ORDER BY name ASC');
$existing_clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch active services
try {
    $stmt = $pdo->query('SELECT * FROM services WHERE is_active = 1 ORDER BY category, name');
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $services = [];
}

// Handle AJAX client update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_client') {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];

    $client_id = (int)($_POST['client_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $company = trim($_POST['company'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($client_id <= 0 || empty($name) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'Invalid client data. Name and valid email are required.';
    } else {
        try {
            $stmt = $pdo->prepare('UPDATE clients SET name = ?, company = ?, email = ?, phone = ?, address = ? WHERE id = ?');
            $stmt->execute([$name, $company, $email, $phone, $address, $client_id]);

            if ($stmt->rowCount() > 0) {
                $response['success'] = true;
                $response['message'] = 'Client updated successfully.';
                $response['client'] = [
                    'id' => $client_id,
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'address' => $address
                ];
            } else {
                $response['message'] = 'Client not found or no changes made.';
            }
        } catch (PDOException $e) {
            $response['message'] = 'Database error: ' . $e->getMessage();
        }
    }

    echo json_encode($response);
    exit;
}

// Handle form submission
$message = '';
$message_type = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if using existing client or creating new
    $client_selection = $_POST['client_selection'] ?? 'new';
    $client_id = null;
    
    if ($client_selection === 'existing') {
        $client_id = (int)($_POST['existing_client_id'] ?? 0);
        if ($client_id <= 0) {
            $message = 'Please select a valid existing client.';
            $message_type = 'error';
        }
    } else {
        // Validate new client details
        $client_name = trim($_POST['client_name'] ?? '');
        $client_company = trim($_POST['client_company'] ?? '');
        $client_address = trim($_POST['client_address'] ?? '');
        $client_email = trim($_POST['client_email'] ?? '');
        $client_phone = trim($_POST['client_phone'] ?? '');

        if (empty($client_name) || empty($client_email) || !filter_var($client_email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Invalid client details. Name and valid email are required.';
            $message_type = 'error';
        }
        if (empty($message)) {
            $stmt = $pdo->prepare('SELECT id FROM clients WHERE email = ?');
            $stmt->execute([$client_email]);
            if ($stmt->fetchColumn()) {
                $message = 'A client with this email already exists. Please select the existing client or use a different email.';
                $message_type = 'error';
            }
        }
    }
    
    if (empty($message)) {
        // Validate invoice details
        $due_date = $_POST['due_date'] ?? '';
        $notes = trim($_POST['notes'] ?? '');
        $tax_rate = isPositiveNumeric($_POST['tax_rate'] ?? '') ? (float)$_POST['tax_rate'] / 100 : DEFAULT_TAX_RATE;
        $discount_type = $_POST['discount_type'] ?? 'none';
        $discount_value = isPositiveNumeric($_POST['discount_value'] ?? '') ? (float)$_POST['discount_value'] : 0;

        if (!isFutureDate($due_date)) {
            $message = 'Due date must be in the future.';
            $message_type = 'error';
        } else {
            // Validate items
            $items = [];
            $subtotal = 0;
            
            // Get the number of items from the form
            $item_count = isset($_POST['item_count']) ? (int)$_POST['item_count'] : 5;
            
            for ($i = 0; $i < $item_count; $i++) {
                $desc = trim($_POST["item_desc_$i"] ?? '');
                $qty = (float)($_POST["item_qty_$i"] ?? 0);
                $unit_price = (float)($_POST["item_price_$i"] ?? 0);
                $repair_details = trim($_POST["item_repair_details_$i"] ?? '');
                if (!empty($desc) && isPositiveNumeric($qty) && isPositiveNumeric($unit_price)) {
                    $line_total = $qty * $unit_price;
                    $items[] = [
                        'description' => $desc, 
                        'quantity' => $qty, 
                        'unit_price' => $unit_price, 
                        'line_total' => $line_total,
                        'repair_details' => $repair_details
                    ];
                    $subtotal += $line_total;
                }
            }

            if (empty($items)) {
                $message = 'At least one valid item is required.';
                $message_type = 'error';
            } else {
                // Calculate totals
                $discount_amount = 0;
                if ($discount_type === 'percentage' && $discount_value > 0) {
                    $discount_amount = $subtotal * ($discount_value / 100);
                } elseif ($discount_type === 'fixed' && $discount_value > 0) {
                    $discount_amount = min($discount_value, $subtotal);
                }
                $taxable_amount = $subtotal - $discount_amount;
                $tax_amount = $taxable_amount * $tax_rate;
                $total = $taxable_amount + $tax_amount;

                // Insert new client if needed
                if ($client_selection === 'new') {
                    // Insert new client
                    $stmt = $pdo->prepare('INSERT INTO clients (name, company, address, email, phone) VALUES (?, ?, ?, ?, ?)');
                    $stmt->execute([$client_name, $client_company, $client_address, $client_email, $client_phone]);
                    $client_id = $pdo->lastInsertId();
                }

                // Generate invoice number
                $invoice_number = 'INV-' . date('Y') . '-' . str_pad($client_id, 4, '0', STR_PAD_LEFT) . '-' . time();

                // Generate unique share token for shareable link
                $share_token = bin2hex(random_bytes(32));

                // Insert invoice with share token
                $stmt = $pdo->prepare('INSERT INTO invoices (client_id, invoice_number, invoice_date, due_date, notes, tax_rate, subtotal, discount_amount, tax_amount, total, discount_type, discount_value, status, share_token, share_token_created_at, share_token_expires_at) VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 90 DAY))');
                $stmt->execute([$client_id, $invoice_number, $due_date, $notes, $tax_rate, $subtotal, $discount_amount, $tax_amount, $total, $discount_type, $discount_value, 'unpaid', $share_token]);
                $invoice_id = $pdo->lastInsertId();

                // Insert items
                foreach ($items as $item) {
                    $stmt = $pdo->prepare('INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, line_total, repair_details) VALUES (?, ?, ?, ?, ?, ?)');
                    $stmt->execute([$invoice_id, $item['description'], $item['quantity'], $item['unit_price'], $item['line_total'], $item['repair_details']]);
                }

                $message = 'Invoice created successfully! Invoice #' . $invoice_number;
                $message_type = 'success';
            }
        }
    }
}
?>

<?php if ($message && $message_type === 'error'): ?>
<script>
alert('<?php echo addslashes($message); ?>');
</script>
<?php endif; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Invoice - <?php echo APP_NAME; ?></title>
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
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            padding: 40px;
        }
        
        h1 {
            color: #333;
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        h2 {
            color: #555;
            font-size: 20px;
            margin: 30px 0 20px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
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
        
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="tel"],
        .form-group input[type="date"],
        .form-group input[type="number"],
        .form-group textarea,
        .form-group select {
            width: 100%;
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
        
        .grid-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        .radio-group {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .radio-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
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
        
        .btn-success {
            background: #10b981;
            color: white;
        }
        
        .btn-small {
            padding: 8px 16px;
            font-size: 13px;
        }
        
        .services-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .service-card {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .service-card:hover {
            border-color: #667eea;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
        }
        
        .service-card.selected {
            border-color: #667eea;
            background: #f0f4ff;
        }
        
        .service-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
        }
        
        .service-price {
            color: #10b981;
            font-weight: 600;
            font-size: 16px;
        }
        
        .service-category {
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .items-table th {
            background: #667eea;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: 500;
        }
        
        .items-table td {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .items-table input {
            width: 100%;
            padding: 8px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
        }
        
        .repair-details-row {
            background: #f9fafb;
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
            font-weight: 500;
            margin-bottom: 8px;
        }
        
        .repair-details-textarea {
            width: 100%;
            min-height: 80px;
            padding: 10px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 13px;
            font-family: inherit;
            resize: vertical;
            transition: border-color 0.2s;
        }
        
        .repair-details-textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .repair-details-textarea::placeholder {
            color: #9ca3af;
        }
        
        .remove-item {
            background: #ef4444;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196F3;
            padding: 15px;
            border-radius: 4px;
            margin-top: 10px;
        }
        
        /* Modern Invoice Summary Styles */
        .invoice-summary {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border: 2px solid #e5e7eb;
            border-radius: 16px;
            padding: 32px;
            margin-top: 24px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1), 0 4px 10px rgba(0, 0, 0, 0.05);
            position: relative;
            overflow: hidden;
        }

        .invoice-summary::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 0;
            border-bottom: 1px solid rgba(148, 163, 184, 0.2);
            transition: all 0.2s ease;
        }

        .summary-row:hover {
            background: rgba(255, 255, 255, 0.5);
            border-radius: 8px;
            padding-left: 12px;
            padding-right: 12px;
            margin: 0 -12px;
        }

        .summary-row:last-child {
            border-bottom: none;
        }

        .summary-row.total-row {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 24px;
            margin: 20px -32px -32px -32px;
            border-radius: 0 0 12px 12px;
            font-weight: 700;
            font-size: 18px;
            border-bottom: none;
            box-shadow: 0 -4px 12px rgba(102, 126, 234, 0.3);
        }

        .summary-row.total-row:hover {
            background: linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%);
            margin: 20px -32px -32px -32px;
        }

        .summary-row.balance-row {
            background: linear-gradient(135deg, #fed7d7 0%, #feb2b2 100%);
            border: 1px solid #fc8181;
            border-radius: 8px;
            padding: 16px 20px;
            margin: 16px -20px 0 -20px;
            font-weight: 600;
            color: #c53030;
        }

        .summary-row.balance-row:hover {
            background: linear-gradient(135deg, #fc8181 0%, #f56565 100%);
            margin: 16px -20px 0 -20px;
        }

        .summary-label {
            font-weight: 600;
            color: #374151;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .summary-label::before {
            content: '';
            width: 6px;
            height: 6px;
            background: #667eea;
            border-radius: 50%;
            display: inline-block;
        }

        .summary-value {
            font-weight: 700;
            color: #1f2937;
            font-size: 16px;
            font-family: 'Courier New', monospace;
        }

        .total-row .summary-label,
        .total-row .summary-value {
            color: white;
            font-size: 18px;
        }

        .total-row .summary-label::before {
            background: rgba(255, 255, 255, 0.8);
        }

        .balance-row .summary-label,
        .balance-row .summary-value {
            color: #c53030;
            font-weight: 700;
        }

        .balance-row .summary-label::before {
            background: #c53030;
        }

        /* Special styling for different row types */
        .summary-row:nth-child(2) .summary-label::before {
            background: #10b981;
        }

        .summary-row:nth-child(3) .summary-label::before {
            background: #f59e0b;
        }

        .summary-row:nth-child(5) .summary-label::before {
            background: #8b5cf6;
        }

        @media (max-width: 768px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }

            .services-grid {
                grid-template-columns: 1fr;
            }

            .invoice-summary {
                padding: 24px;
                margin: 20px -10px 0 -10px;
            }

            .summary-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }

            .summary-row.total-row,
            .summary-row.balance-row {
                margin-left: -24px;
                margin-right: -24px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📝 Create New Invoice</h1>
        
        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php if ($message_type === 'success'): ?>✓<?php else: ?>✗<?php endif; ?>
                <?php echo htmlspecialchars($message); ?>
                <?php if ($message_type === 'success' && isset($invoice_id)): ?>
                    <a href="pdf_invoice.php?id=<?php echo $invoice_id; ?>" target="_blank" style="margin-left: auto; color: inherit; font-weight: 600;">View PDF →</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <form method="post" id="invoiceForm">
            <h2>Client Information</h2>
            <div class="radio-group">
                <label>
                    <input type="radio" name="client_selection" value="existing" onchange="toggleClientFields()" <?php echo !empty($existing_clients) ? '' : 'disabled'; ?>>
                    Select Existing Client <?php echo empty($existing_clients) ? '(No clients yet)' : ''; ?>
                </label>
                <label>
                    <input type="radio" name="client_selection" value="new" onchange="toggleClientFields()" checked>
                    Create New Client
                </label>
            </div>
            
            <!-- Existing Client Section -->
            <div id="existing-client-section" style="display: none;">
                <div class="form-group">
                    <label>Select Client: *</label>
                    <select name="existing_client_id" id="existing_client_id" onchange="populateClientDetails()">
                        <option value="">-- Select a Client --</option>
                        <?php foreach ($existing_clients as $client): ?>
                            <option value="<?php echo $client['id']; ?>"
                                    data-company="<?php echo htmlspecialchars($client['company'] ?? ''); ?>"
                                    data-email="<?php echo htmlspecialchars($client['email']); ?>"
                                    data-phone="<?php echo htmlspecialchars($client['phone']); ?>"
                                    data-address="<?php echo htmlspecialchars($client['address']); ?>">
                                <?php echo htmlspecialchars($client['name']); ?> (<?php echo htmlspecialchars($client['email']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="client-preview" class="info-box" style="display: none;">
                    <div id="client-view-mode">
                        <div id="client-details"></div>
                        <div style="margin-top: 10px;">
                            <button type="button" id="edit-client-btn" class="btn btn-secondary btn-small" onclick="enterEditMode()">✏️ Edit Client</button>
                        </div>
                    </div>
                    <div id="client-edit-mode" style="display: none;">
                        <div class="grid-2" style="margin-bottom: 15px;">
                            <div class="form-group" style="margin-bottom: 10px;">
                                <label>Name: *</label>
                                <input type="text" id="edit_client_name">
                            </div>
                            <div class="form-group" style="margin-bottom: 10px;">
                                <label>Company:</label>
                                <input type="text" id="edit_client_company" placeholder="Optional">
                            </div>
                        </div>
                        <div class="grid-2" style="margin-bottom: 15px;">
                            <div class="form-group" style="margin-bottom: 10px;">
                                <label>Email: *</label>
                                <input type="email" id="edit_client_email">
                            </div>
                            <div class="form-group" style="margin-bottom: 10px;">
                                <label>Phone:</label>
                                <input type="tel" id="edit_client_phone">
                            </div>
                        </div>
                        <div class="form-group" style="margin-bottom: 15px;">
                            <label>Address:</label>
                            <input type="text" id="edit_client_address">
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <button type="button" class="btn btn-success btn-small" onclick="saveClientEdit()">✓ Save</button>
                            <button type="button" class="btn btn-secondary btn-small" onclick="cancelClientEdit()">✗ Cancel</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- New Client Section -->
            <div id="new-client-section">
                <div class="grid-2">
                    <div class="form-group">
                        <label>Client Name: *</label>
                        <input type="text" name="client_name" id="new_client_name" required>
                    </div>
                    <div class="form-group">
                        <label>Company:</label>
                        <input type="text" name="client_company" placeholder="Optional">
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label>Email: *</label>
                        <input type="email" name="client_email" id="new_client_email" required>
                    </div>
                    <div class="form-group">
                        <label>Phone:</label>
                        <input type="tel" name="client_phone">
                    </div>
                </div>
                <div class="form-group">
                    <label>Address:</label>
                    <input type="text" name="client_address">
                </div>
            </div>

            <h2>Invoice Details</h2>
            <div class="grid-2">
                <div class="form-group">
                    <label>Due Date: *</label>
                    <input type="date" name="due_date" required min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                </div>
                <div class="form-group">
                    <label>Tax Rate (%):</label>
                    <input type="number" step="0.01" name="tax_rate" value="<?php echo DEFAULT_TAX_RATE; ?>">
                </div>
            </div>
            
            <div class="grid-2">
                <div class="form-group">
                    <label>Discount Type:</label>
                    <select name="discount_type">
                        <option value="none">None</option>
                        <option value="percentage">Percentage (%)</option>
                        <option value="fixed">Fixed Amount ($)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Discount Value:</label>
                    <input type="number" step="0.01" name="discount_value" placeholder="e.g., 10 or 100.00">
                </div>
            </div>
            
            <div class="form-group">
                <label>Notes:</label>
                <textarea name="notes" rows="3" placeholder="Additional notes or payment terms..."></textarea>
            </div>

            <?php if (!empty($services)): ?>
            <h2>Quick Add Services</h2>
            <div class="services-section">
                <p style="color: #666; margin-bottom: 10px;">Click on a service to add it to your invoice</p>
                <div class="services-grid">
                    <?php foreach ($services as $service): ?>
                        <div class="service-card" onclick="addServiceToInvoice('<?php echo htmlspecialchars($service['name']); ?>', <?php echo $service['unit_price']; ?>)">
                            <div class="service-category"><?php echo htmlspecialchars($service['category']); ?></div>
                            <div class="service-name"><?php echo htmlspecialchars($service['name']); ?></div>
                            <div class="service-price">$<?php echo number_format($service['unit_price'], 2); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top: 15px;">
                    <a href="manage_services.php" class="btn btn-secondary btn-small" target="_blank">Manage Services</a>
                </div>
            </div>
            <?php endif; ?>

            <h2>Invoice Items</h2>
            <input type="hidden" name="item_count" id="item_count" value="5">
            <table class="items-table" id="itemsTable">
                <thead>
                    <tr>
                        <th style="width: 50%;">Description</th>
                        <th style="width: 15%;">Quantity</th>
                        <th style="width: 20%;">Unit Price ($)</th>
                        <th style="width: 15%;">Action</th>
                    </tr>
                </thead>
                <tbody id="itemsBody">
                    <tr>
                        <td><input type="text" name="item_desc_0" placeholder="Service description"></td>
                        <td><input type="number" name="item_qty_0" step="0.01" placeholder="1" value="1"></td>
                        <td><input type="number" name="item_price_0" step="0.01" placeholder="0.00"></td>
                        <td><button type="button" class="remove-item" onclick="removeItem(this)">Remove</button></td>
                    </tr>
                    <tr class="repair-details-row">
                        <td colspan="4">
                            <div class="repair-details-container">
                                <label class="repair-details-label">🔧 Repair/Work Details (What was fixed or done):</label>
                                <textarea name="item_repair_details_0" class="repair-details-textarea" placeholder="Describe the work performed, parts replaced, issues fixed, etc..."></textarea>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            <button type="button" onclick="addItemRow()" class="btn btn-secondary btn-small" style="margin-top: 10px;">+ Add Another Item</button>

            <h2>Invoice Summary</h2>
            <div class="invoice-summary" id="invoiceSummary">
                <div class="summary-row">
                    <span class="summary-label">Subtotal</span>
                    <span class="summary-value">NZ$<span id="subtotal">0.00</span></span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Tax breakdown</span>
                    <span class="summary-value">GST (<span id="taxRate">15</span>%)</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Tax total</span>
                    <span class="summary-value">NZ$<span id="taxTotal">0.00</span></span>
                </div>
                <div class="summary-row total-row">
                    <span class="summary-label">Invoice total</span>
                    <span class="summary-value">NZ$<span id="invoiceTotal">0.00</span></span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Amount paid</span>
                    <span class="summary-value">NZ$<span id="amountPaid">0.00</span></span>
                </div>
                <div class="summary-row balance-row">
                    <span class="summary-label">Balance Due</span>
                    <span class="summary-value">NZ$<span id="balanceDue">0.00</span></span>
                </div>
            </div>

            <div style="margin-top: 40px; display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary">Create Invoice</button>
                <a href="index.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>

    <script>
        let itemIndex = 1;
        
        function toggleClientFields() {
            const selection = document.querySelector('input[name="client_selection"]:checked').value;
            const existingClientDiv = document.getElementById('existing-client-section');
            const newClientDiv = document.getElementById('new-client-section');

            if (selection === 'existing') {
                existingClientDiv.style.display = 'block';
                newClientDiv.style.display = 'none';
                document.querySelectorAll('#new-client-section input, #new-client-section textarea').forEach(el => {
                    el.removeAttribute('required');
                });
                document.getElementById('existing_client_id').setAttribute('required', 'required');
                // Remove required from edit mode inputs when switching to existing client
                document.getElementById('edit_client_name').removeAttribute('required');
                document.getElementById('edit_client_email').removeAttribute('required');
            } else {
                existingClientDiv.style.display = 'none';
                newClientDiv.style.display = 'block';
                document.getElementById('existing_client_id').removeAttribute('required');
                document.getElementById('new_client_name').setAttribute('required', 'required');
                document.getElementById('new_client_email').setAttribute('required', 'required');
                // Remove required from edit mode inputs when switching to new client
                document.getElementById('edit_client_name').removeAttribute('required');
                document.getElementById('edit_client_email').removeAttribute('required');
            }
        }
        
        function populateClientDetails() {
            const select = document.getElementById('existing_client_id');
            const selectedOption = select.options[select.selectedIndex];
            const preview = document.getElementById('client-preview');

            if (selectedOption.value) {
                const clientId = selectedOption.value;
                const name = selectedOption.text.split(' (')[0];
                const email = selectedOption.getAttribute('data-email');
                const phone = selectedOption.getAttribute('data-phone');
                const address = selectedOption.getAttribute('data-address');
                const company = selectedOption.getAttribute('data-company') || '';

                // Store current client data
                preview.setAttribute('data-client-id', clientId);
                preview.setAttribute('data-client-name', name);
                preview.setAttribute('data-client-company', company);
                preview.setAttribute('data-client-email', email);
                preview.setAttribute('data-client-phone', phone || '');
                preview.setAttribute('data-client-address', address || '');

                // Update view mode
                const clientDetails = document.getElementById('client-details');
                clientDetails.innerHTML =
                    '<strong>Selected Client:</strong><br>' +
                    'Name: ' + name + '<br>' +
                    (company ? 'Company: ' + company + '<br>' : '') +
                    'Email: ' + email + '<br>' +
                    'Phone: ' + (phone || 'N/A') + '<br>' +
                    'Address: ' + (address || 'N/A');

                // Show view mode, hide edit mode
                document.getElementById('client-view-mode').style.display = 'block';
                document.getElementById('client-edit-mode').style.display = 'none';

                preview.style.display = 'block';
            } else {
                preview.style.display = 'none';
            }
        }

        function enterEditMode() {
            const preview = document.getElementById('client-preview');
            const name = preview.getAttribute('data-client-name');
            const email = preview.getAttribute('data-client-email');
            const phone = preview.getAttribute('data-client-phone');
            const address = preview.getAttribute('data-client-address');

            // Populate edit form
            document.getElementById('edit_client_name').value = name;
            document.getElementById('edit_client_email').value = email;
            document.getElementById('edit_client_phone').value = phone;
            document.getElementById('edit_client_address').value = address;

            // Show edit mode, hide view mode
            document.getElementById('client-view-mode').style.display = 'none';
            document.getElementById('client-edit-mode').style.display = 'block';
        }

        function cancelClientEdit() {
            // Show view mode, hide edit mode
            document.getElementById('client-view-mode').style.display = 'block';
            document.getElementById('client-edit-mode').style.display = 'none';
        }

        function saveClientEdit() {
            const preview = document.getElementById('client-preview');
            const clientId = preview.getAttribute('data-client-id');
            const name = document.getElementById('edit_client_name').value.trim();
            const company = document.getElementById('edit_client_company').value.trim();
            const email = document.getElementById('edit_client_email').value.trim();
            const phone = document.getElementById('edit_client_phone').value.trim();
            const address = document.getElementById('edit_client_address').value.trim();

            // Client-side validation
            if (!name || !email) {
                alert('Name and email are required.');
                return;
            }

            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                alert('Please enter a valid email address.');
                return;
            }

            // Send AJAX request
            const formData = new FormData();
            formData.append('action', 'update_client');
            formData.append('client_id', clientId);
            formData.append('name', name);
            formData.append('company', company);
            formData.append('email', email);
            formData.append('phone', phone);
            formData.append('address', address);

            fetch('create_invoice.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update stored data
                    preview.setAttribute('data-client-name', name);
                    preview.setAttribute('data-client-company', company);
                    preview.setAttribute('data-client-email', email);
                    preview.setAttribute('data-client-phone', phone);
                    preview.setAttribute('data-client-address', address);

                    // Update view mode display
                    const clientDetails = document.getElementById('client-details');
                    clientDetails.innerHTML =
                        '<strong>Selected Client:</strong><br>' +
                        'Name: ' + name + '<br>' +
                        (company ? 'Company: ' + company + '<br>' : '') +
                        'Email: ' + email + '<br>' +
                        'Phone: ' + (phone || 'N/A') + '<br>' +
                        'Address: ' + (address || 'N/A');

                    // Update dropdown option
                    const select = document.getElementById('existing_client_id');
                    const selectedOption = select.options[select.selectedIndex];
                    selectedOption.text = name + ' (' + email + ')';
                    selectedOption.setAttribute('data-company', company);
                    selectedOption.setAttribute('data-email', email);
                    selectedOption.setAttribute('data-phone', phone);
                    selectedOption.setAttribute('data-address', address);

                    // Show view mode, hide edit mode
                    document.getElementById('client-view-mode').style.display = 'block';
                    document.getElementById('client-edit-mode').style.display = 'none';

                    alert('Client updated successfully!');
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating the client.');
            });
        }
        
        function addServiceToInvoice(name, price) {
            const tbody = document.getElementById('itemsBody');

            // Add main item row
            const row = document.createElement('tr');
            row.innerHTML = `
                <td><input type="text" name="item_desc_${itemIndex}" value="${name}"></td>
                <td><input type="number" name="item_qty_${itemIndex}" step="0.01" value="1"></td>
                <td><input type="number" name="item_price_${itemIndex}" step="0.01" value="${price}"></td>
                <td><button type="button" class="remove-item" onclick="removeItem(this)">Remove</button></td>
            `;
            tbody.appendChild(row);

            // Add repair details row
            const detailsRow = document.createElement('tr');
            detailsRow.className = 'repair-details-row';
            detailsRow.innerHTML = `
                <td colspan="4">
                    <div class="repair-details-container">
                        <label class="repair-details-label">🔧 Repair/Work Details (What was fixed or done):</label>
                        <textarea name="item_repair_details_${itemIndex}" class="repair-details-textarea" placeholder="Describe the work performed, parts replaced, issues fixed, etc..."></textarea>
                    </div>
                </td>
            `;
            tbody.appendChild(detailsRow);

            itemIndex++;
            document.getElementById('item_count').value = itemIndex;

            // Attach listeners to new inputs
            const qtyInput = row.querySelector('input[name*="_qty_"]');
            const priceInput = row.querySelector('input[name*="_price_"]');
            qtyInput.addEventListener('input', calculateTotals);
            priceInput.addEventListener('input', calculateTotals);

            // Visual feedback
            row.style.backgroundColor = '#d1fae5';
            setTimeout(() => {
                row.style.backgroundColor = '';
            }, 1000);

            // Recalculate totals
            calculateTotals();
        }
        
        function addItemRow() {
            const tbody = document.getElementById('itemsBody');

            // Add main item row
            const row = document.createElement('tr');
            row.innerHTML = `
                <td><input type="text" name="item_desc_${itemIndex}" placeholder="Service description"></td>
                <td><input type="number" name="item_qty_${itemIndex}" step="0.01" placeholder="1" value="1"></td>
                <td><input type="number" name="item_price_${itemIndex}" step="0.01" placeholder="0.00"></td>
                <td><button type="button" class="remove-item" onclick="removeItem(this)">Remove</button></td>
            `;
            tbody.appendChild(row);

            // Add repair details row
            const detailsRow = document.createElement('tr');
            detailsRow.className = 'repair-details-row';
            detailsRow.innerHTML = `
                <td colspan="4">
                    <div class="repair-details-container">
                        <label class="repair-details-label">🔧 Repair/Work Details (What was fixed or done):</label>
                        <textarea name="item_repair_details_${itemIndex}" class="repair-details-textarea" placeholder="Describe the work performed, parts replaced, issues fixed, etc..."></textarea>
                    </div>
                </td>
            `;
            tbody.appendChild(detailsRow);

            // Attach listeners to new inputs
            const qtyInput = row.querySelector('input[name*="_qty_"]');
            const priceInput = row.querySelector('input[name*="_price_"]');
            qtyInput.addEventListener('input', calculateTotals);
            priceInput.addEventListener('input', calculateTotals);

            itemIndex++;
            document.getElementById('item_count').value = itemIndex;
        }
        
        function calculateTotals() {
            let subtotal = 0;
            const taxRate = parseFloat(document.querySelector('input[name="tax_rate"]').value) || 15;
            const discountType = document.querySelector('select[name="discount_type"]').value;
            const discountValue = parseFloat(document.querySelector('input[name="discount_value"]').value) || 0;

            // Calculate subtotal from all items
            const itemRows = document.querySelectorAll('#itemsBody tr:not(.repair-details-row)');
            itemRows.forEach(row => {
                const qtyInput = row.querySelector('input[name*="_qty_"]');
                const priceInput = row.querySelector('input[name*="_price_"]');
                const qty = parseFloat(qtyInput.value) || 0;
                const price = parseFloat(priceInput.value) || 0;
                if (qty > 0 && price > 0) {
                    subtotal += qty * price;
                }
            });

            // Apply discount
            let discountAmount = 0;
            if (discountType === 'percentage' && discountValue > 0) {
                discountAmount = subtotal * (discountValue / 100);
            } else if (discountType === 'fixed' && discountValue > 0) {
                discountAmount = Math.min(discountValue, subtotal);
            }

            const taxableAmount = subtotal - discountAmount;
            const taxAmount = taxableAmount * (taxRate / 100);
            const total = taxableAmount + taxAmount;

            // Update display
            document.getElementById('subtotal').textContent = subtotal.toFixed(2);
            document.getElementById('taxRate').textContent = taxRate.toFixed(2);
            document.getElementById('taxTotal').textContent = taxAmount.toFixed(2);
            document.getElementById('invoiceTotal').textContent = total.toFixed(2);
            document.getElementById('balanceDue').textContent = total.toFixed(2);
        }

        function attachCalculationListeners() {
            // Attach listeners to existing inputs
            document.querySelectorAll('input[name*="_qty_"], input[name*="_price_"]').forEach(input => {
                input.addEventListener('input', calculateTotals);
            });
            document.querySelector('input[name="tax_rate"]').addEventListener('input', calculateTotals);
            document.querySelector('select[name="discount_type"]').addEventListener('change', calculateTotals);
            document.querySelector('input[name="discount_value"]').addEventListener('input', calculateTotals);
        }

        function removeItem(button) {
            const tbody = document.getElementById('itemsBody');
            const row = button.closest('tr');

            // Count actual item rows (not repair detail rows)
            const itemRows = Array.from(tbody.children).filter(tr => !tr.classList.contains('repair-details-row'));

            if (itemRows.length > 1) {
                // Remove the item row
                const nextRow = row.nextElementSibling;
                row.remove();
                // Remove the associated repair details row if it exists
                if (nextRow && nextRow.classList.contains('repair-details-row')) {
                    nextRow.remove();
                }
                // Recalculate after removal
                calculateTotals();
            } else {
                alert('You must have at least one item in the invoice.');
            }
        }
        
        window.onload = function() {
            toggleClientFields();
            attachCalculationListeners();
        };
    </script>
</body>
</html>
