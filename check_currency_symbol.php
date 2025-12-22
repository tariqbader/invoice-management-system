<?php
/**
 * Check CURRENCY_SYMBOL Definition Order
 */

echo "<h1>CURRENCY_SYMBOL Debug</h1>";
echo "<style>body { font-family: monospace; padding: 20px; } .box { background: #f0f0f0; padding: 15px; margin: 10px 0; border-left: 4px solid #667eea; }</style>";

echo "<div class='box'>";
echo "<h2>Step 1: Before any includes</h2>";
echo "CURRENCY_SYMBOL defined: " . (defined('CURRENCY_SYMBOL') ? 'YES' : 'NO') . "<br>";
if (defined('CURRENCY_SYMBOL')) {
    echo "Value: " . CURRENCY_SYMBOL . "<br>";
}
echo "</div>";

require_once 'config.php';

echo "<div class='box'>";
echo "<h2>Step 2: After config.php</h2>";
echo "CURRENCY_SYMBOL defined: " . (defined('CURRENCY_SYMBOL') ? 'YES' : 'NO') . "<br>";
if (defined('CURRENCY_SYMBOL')) {
    echo "Value: " . CURRENCY_SYMBOL . "<br>";
}
echo "</div>";

require_once 'db.php';

echo "<div class='box'>";
echo "<h2>Step 3: After db.php</h2>";
echo "CURRENCY_SYMBOL defined: " . (defined('CURRENCY_SYMBOL') ? 'YES' : 'NO') . "<br>";
if (defined('CURRENCY_SYMBOL')) {
    echo "Value: " . CURRENCY_SYMBOL . "<br>";
}
echo "</div>";

// Check settings table
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE '%currency%'");
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<div class='box'>";
    echo "<h2>Step 4: Settings from database</h2>";
    if (empty($settings)) {
        echo "No currency-related settings found in database<br>";
    } else {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Key</th><th>Value</th></tr>";
        foreach ($settings as $setting) {
            echo "<tr><td>" . htmlspecialchars($setting['setting_key']) . "</td><td>" . htmlspecialchars($setting['setting_value']) . "</td></tr>";
        }
        echo "</table>";
    }
    echo "</div>";
    
    // Check all constants
    echo "<div class='box'>";
    echo "<h2>Step 5: All defined constants containing 'CURRENCY'</h2>";
    $constants = get_defined_constants(true);
    $user_constants = $constants['user'];
    foreach ($user_constants as $name => $value) {
        if (stripos($name, 'CURRENCY') !== false) {
            echo "$name = " . var_export($value, true) . "<br>";
        }
    }
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='box' style='border-left-color: red;'>";
    echo "<h2>Error</h2>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

// Check what pdf_invoice.php sees
echo "<div class='box'>";
echo "<h2>Step 6: Simulating pdf_invoice.php</h2>";
echo "This is what pdf_invoice.php would see:<br>";
echo "CURRENCY_SYMBOL = " . (defined('CURRENCY_SYMBOL') ? CURRENCY_SYMBOL : 'NOT DEFINED') . "<br>";
echo "</div>";
?>
