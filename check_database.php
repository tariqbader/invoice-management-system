<?php
/**
 * Database Schema Checker
 * This script checks the actual database schema and data types
 */

require_once 'config.php';
require_once 'db.php';

echo "<h1>Database Schema Checker</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
    th { background-color: #4CAF50; color: white; }
    tr:nth-child(even) { background-color: #f2f2f2; }
    .error { color: red; font-weight: bold; }
    .success { color: green; font-weight: bold; }
    .warning { color: orange; font-weight: bold; }
</style>";

try {
    $pdo = getDBConnection();
    
    // Check invoice_items table structure
    echo "<h2>Invoice Items Table Structure</h2>";
    $stmt = $pdo->query("DESCRIBE invoice_items");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    $unit_price_type = '';
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Default']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Extra']) . "</td>";
        echo "</tr>";
        
        if ($column['Field'] === 'unit_price') {
            $unit_price_type = $column['Type'];
        }
    }
    echo "</table>";
    
    // Check if unit_price is correct type
    echo "<h2>Unit Price Column Check</h2>";
    if (stripos($unit_price_type, 'decimal') !== false) {
        echo "<p class='success'>✓ unit_price column type is correct: $unit_price_type</p>";
    } else {
        echo "<p class='error'>✗ unit_price column type is INCORRECT: $unit_price_type</p>";
        echo "<p class='warning'>Expected: decimal(10,2) or similar</p>";
        echo "<p><strong>Fix SQL:</strong></p>";
        echo "<pre>ALTER TABLE invoice_items MODIFY COLUMN unit_price DECIMAL(10, 2) NOT NULL DEFAULT 0.00;</pre>";
    }
    
    // Check recent invoice items
    echo "<h2>Recent Invoice Items (Last 5)</h2>";
    $stmt = $pdo->query("
        SELECT ii.id, ii.description, ii.quantity, ii.unit_price, ii.line_total, i.invoice_number
        FROM invoice_items ii
        JOIN invoices i ON ii.invoice_id = i.id
        ORDER BY ii.id DESC
        LIMIT 5
    ");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($items)) {
        echo "<p>No invoice items found.</p>";
    } else {
        echo "<table>";
        echo "<tr><th>ID</th><th>Invoice</th><th>Description</th><th>Quantity</th><th>Unit Price</th><th>Line Total</th><th>Status</th></tr>";
        
        foreach ($items as $item) {
            $status = '';
            $status_class = '';
            
            // Check if unit price looks corrupted
            if ($item['unit_price'] > 1000000) {
                $status = '⚠️ CORRUPTED';
                $status_class = 'error';
            } elseif ($item['unit_price'] != ($item['line_total'] / $item['quantity'])) {
                $status = '⚠️ MISMATCH';
                $status_class = 'warning';
            } else {
                $status = '✓ OK';
                $status_class = 'success';
            }
            
            echo "<tr>";
            echo "<td>" . htmlspecialchars($item['id']) . "</td>";
            echo "<td>" . htmlspecialchars($item['invoice_number']) . "</td>";
            echo "<td>" . htmlspecialchars($item['description']) . "</td>";
            echo "<td>" . number_format($item['quantity'], 2) . "</td>";
            echo "<td>" . number_format($item['unit_price'], 2) . "</td>";
            echo "<td>" . number_format($item['line_total'], 2) . "</td>";
            echo "<td class='$status_class'>$status</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Check for corrupted data
    echo "<h2>Corrupted Data Check</h2>";
    $stmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM invoice_items
        WHERE unit_price > 1000000 OR unit_price < 0
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] > 0) {
        echo "<p class='error'>Found " . $result['count'] . " corrupted invoice items with suspicious unit prices!</p>";
        echo "<p><strong>To fix corrupted data, you can run:</strong></p>";
        echo "<pre>-- This will need manual review and correction for each item</pre>";
    } else {
        echo "<p class='success'>✓ No obviously corrupted data found.</p>";
    }
    
    echo "<h2>Database Connection Info</h2>";
    echo "<p>Database: " . DB_NAME . "</p>";
    echo "<p>Host: " . DB_HOST . "</p>";
    
} catch (PDOException $e) {
    echo "<p class='error'>Database Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
