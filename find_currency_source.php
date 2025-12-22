<?php
/**
 * Find where CURRENCY_SYMBOL is being defined
 */

echo "<h1>Finding CURRENCY_SYMBOL Source</h1>";
echo "<style>body { font-family: monospace; padding: 20px; } .box { background: #f0f0f0; padding: 15px; margin: 10px 0; border-left: 4px solid #667eea; } .error { border-left-color: red; }</style>";

// Check if it's already defined (it is!)
echo "<div class='box'>";
echo "<h2>Current State</h2>";
echo "CURRENCY_SYMBOL is defined: " . (defined('CURRENCY_SYMBOL') ? 'YES' : 'NO') . "<br>";
if (defined('CURRENCY_SYMBOL')) {
    echo "Current value: <strong>" . CURRENCY_SYMBOL . "</strong><br>";
}
echo "</div>";

// Check PHP configuration
echo "<div class='box'>";
echo "<h2>PHP Configuration</h2>";
echo "auto_prepend_file: " . ini_get('auto_prepend_file') . "<br>";
echo "auto_append_file: " . ini_get('auto_append_file') . "<br>";
echo "</div>";

// Check included files
echo "<div class='box'>";
echo "<h2>Included Files (before any require)</h2>";
$included = get_included_files();
echo "Number of files: " . count($included) . "<br>";
foreach ($included as $file) {
    echo htmlspecialchars($file) . "<br>";
}
echo "</div>";

// Check for .user.ini or .htaccess
echo "<div class='box'>";
echo "<h2>Configuration Files</h2>";
$current_dir = __DIR__;
echo "Current directory: $current_dir<br><br>";

$config_files = ['.user.ini', '.htaccess', 'php.ini'];
foreach ($config_files as $config_file) {
    $path = $current_dir . '/' . $config_file;
    if (file_exists($path)) {
        echo "<strong>Found: $config_file</strong><br>";
        echo "<pre>" . htmlspecialchars(file_get_contents($path)) . "</pre>";
    } else {
        echo "$config_file: Not found<br>";
    }
}
echo "</div>";

// Search for files that might define CURRENCY_SYMBOL
echo "<div class='box'>";
echo "<h2>Searching for CURRENCY_SYMBOL in PHP files</h2>";
$files_to_check = glob($current_dir . '/*.php');
$found_in = [];

foreach ($files_to_check as $file) {
    $content = file_get_contents($file);
    if (preg_match('/define\s*\(\s*[\'"]CURRENCY_SYMBOL[\'"]/i', $content)) {
        $found_in[] = basename($file);
    }
}

if (empty($found_in)) {
    echo "No files found defining CURRENCY_SYMBOL in current directory<br>";
} else {
    echo "Files defining CURRENCY_SYMBOL:<br>";
    foreach ($found_in as $file) {
        echo "- $file<br>";
    }
}
echo "</div>";

// Check parent directory for auto-prepend
echo "<div class='box'>";
echo "<h2>Parent Directory Check</h2>";
$parent_dir = dirname($current_dir);
echo "Parent directory: $parent_dir<br>";

$parent_configs = [
    $parent_dir . '/.user.ini',
    $parent_dir . '/.htaccess',
    $parent_dir . '/php.ini'
];

foreach ($parent_configs as $config_path) {
    if (file_exists($config_path)) {
        echo "<strong>Found: " . basename($config_path) . " in parent</strong><br>";
        echo "<pre>" . htmlspecialchars(file_get_contents($config_path)) . "</pre>";
    }
}
echo "</div>";

// Solution
echo "<div class='box' style='background: #fffacd; border-left-color: #ffc107;'>";
echo "<h2>🔧 Solution</h2>";
echo "<p><strong>The problem:</strong> CURRENCY_SYMBOL is being defined somewhere BEFORE your code runs.</p>";
echo "<p><strong>Most likely cause:</strong> An auto_prepend_file or .user.ini setting in your hosting.</p>";
echo "<p><strong>Fix:</strong> Contact 1stdomains support or check your hosting control panel for:</p>";
echo "<ul>";
echo "<li>PHP Settings / PHP Configuration</li>";
echo "<li>Look for 'auto_prepend_file' setting</li>";
echo "<li>Or check for .user.ini files in parent directories</li>";
echo "</ul>";
echo "<p><strong>Temporary workaround:</strong> Use a different constant name like 'INVOICE_CURRENCY_SYMBOL' instead of 'CURRENCY_SYMBOL'</p>";
echo "</div>";
?>
