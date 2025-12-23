<?php
/**
 * Debug Invoice Data
 * This script shows the raw data being read from the database
 */

require_once 'config.php';
require_once 'db.php';

$invoice_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($invoice_id <= 0) {
    die('Invalid invoice ID. Usage: debug_invoice.php?id=13');
}

echo "<h1>Invoice Debug Information</h1>";
echo "<style>
    body { font-family: monospace; padding: 20px; background: #f5f5f5; }
    .section { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    h2 { color: #333; border-bottom: 2px solid #667eea; padding-bottom: 10px; }
    table { border-collapse: collapse; width: 100%; }
    th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
    th { background-color: #667eea; color: white; }
    .raw { background: #f0f0f0; padding: 10px; border-left: 4px solid #667eea; margin: 10px 0; }
    .error { color: red; font-weight: bold; }
    .success { color: green; font-weight: bold; }
</style>";

try {
    $pdo = getDBConnection();
    
    // Fetch invoice
    $stmt = $pdo->prepare("
        SELECT i.*, c.name as client_name, c.email as client_email
        FROM invoices i
        JOIN clients c ON i.client_id = c.id
        WHERE i.id = ?
    ");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$invoice) {
        die("<p class='error'>Invoice not found!</p>");
    }
    
    echo "<div class='section'>";
    echo "<h2>Invoice Information</h2>";
    echo "<table>";
    echo "<tr><th>Field</th><th>Value</th><th>Type</th><th>Raw Bytes</th></tr>";
    foreach ($invoice as $key => $value) {
        $type = gettype($value);
        $bytes = '';
        if (is_string($value)) {
            for ($i = 0; $i < min(strlen($value), 20); $i++) {
                $bytes .= sprintf("%02X ", ord($value[$i]));
            }
        }
        echo "<tr>";
        echo "<td><strong>" . htmlspecialchars($key) . "</strong></td>";
        echo "<td>" . htmlspecialchars($value) . "</td>";
        echo "<td>" . $type . "</td>";
        echo "<td>" . $bytes . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</div>";
    
    // Fetch invoice items
    $stmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id");
    $stmt->execute([$invoice_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<div class='section'>";
    echo "<h2>Invoice Items (Raw Data)</h2>";
    
    foreach ($items as $index => $item) {
        echo "<h3>Item #" . ($index + 1) . " (ID: " . $item['id'] . ")</h3>";
        echo "<table>";
        echo "<tr><th>Field</th><th>Display Value</th><th>Type</th><th>Raw Value</th><th>Hex Bytes</th></tr>";
        
        foreach ($item as $key => $value) {
            $type = gettype($value);
            $raw_value = var_export($value, true);
            $bytes = '';
            
            if (is_string($value)) {
                for ($i = 0; $i < min(strlen($value), 50); $i++) {
                    $bytes .= sprintf("%02X ", ord($value[$i]));
                }
            }
            
            $display_value = htmlspecialchars($value);
            
            // Highlight numeric fields
            if (in_array($key, ['quantity', 'unit_price', 'line_total'])) {
                echo "<tr style='background: #fffacd;'>";
            } else {
                echo "<tr>";
            }
            
            echo "<td><strong>" . htmlspecialchars($key) . "</strong></td>";
            echo "<td>" . $display_value . "</td>";
            echo "<td>" . $type . "</td>";
            echo "<td><code>" . htmlspecialchars($raw_value) . "</code></td>";
            echo "<td><code>" . $bytes . "</code></td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Test number formatting
        echo "<div class='raw'>";
        echo "<strong>Number Formatting Tests:</strong><br>";
        echo "unit_price raw: " . var_export($item['unit_price'], true) . "<br>";
        echo "unit_price (float): " . (float)$item['unit_price'] . "<br>";
        echo "unit_price (double): " . (double)$item['unit_price'] . "<br>";
        echo "unit_price number_format: " . number_format($item['unit_price'], 2) . "<br>";
        echo "unit_price number_format (float cast): " . number_format((float)$item['unit_price'], 2) . "<br>";
        echo "unit_price sprintf: " . sprintf("%.2f", $item['unit_price']) . "<br>";
        echo "</div>";
    }
    echo "</div>";
    
    // Test CURRENCY_SYMBOL
    echo "<div class='section'>";
    echo "<h2>Currency Symbol Test</h2>";
    echo "<p>CURRENCY_SYMBOL constant: '" . CURRENCY_SYMBOL . "'</p>";
    echo "<p>CURRENCY_SYMBOL bytes: ";
    for ($i = 0; $i < strlen(CURRENCY_SYMBOL); $i++) {
        echo sprintf("%02X ", ord(CURRENCY_SYMBOL[$i]));
    }
    echo "</p>";
    echo "</div>";
    
    // Database connection info
    echo "<div class='section'>";
    echo "<h2>Database Connection Info</h2>";
    echo "<p>PDO Driver: " . $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) . "</p>";
    echo "<p>Server Version: " . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . "</p>";
    echo "<p>Client Version: " . $pdo->getAttribute(PDO::ATTR_CLIENT_VERSION) . "</p>";
    echo "<p>Connection Status: " . $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS) . "</p>";
    
    // Check character set
    $stmt = $pdo->query("SHOW VARIABLES LIKE 'character_set%'");
    $charset_vars = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<h3>Character Set Variables:</h3>";
    echo "<table>";
    foreach ($charset_vars as $var) {
        echo "<tr><td>" . $var['Variable_name'] . "</td><td>" . $var['Value'] . "</td></tr>";
    }
    echo "</table>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<p class='error'>Database Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
