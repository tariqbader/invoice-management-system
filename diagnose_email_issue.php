<?php
/**
 * Diagnostic Script for Email/Link Sending Issues
 * This script checks database schema, SMTP configuration, and identifies problems
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Email/Link Diagnostic Tool</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 40px;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .section {
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        .section h2 {
            color: #333;
            margin-bottom: 15px;
            font-size: 18px;
        }
        .check-item {
            padding: 12px;
            margin-bottom: 10px;
            background: white;
            border-radius: 6px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .status {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
        }
        .status.success {
            background: #d1fae5;
            color: #065f46;
        }
        .status.error {
            background: #fee2e2;
            color: #991b1b;
        }
        .status.warning {
            background: #fef3c7;
            color: #92400e;
        }
        .details {
            flex: 1;
        }
        .label {
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
        }
        .value {
            color: #666;
            font-size: 13px;
        }
        .code {
            background: #1f2937;
            color: #10b981;
            padding: 15px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            margin-top: 10px;
            overflow-x: auto;
        }
        .fix-button {
            display: inline-block;
            padding: 12px 24px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            margin-top: 15px;
            cursor: pointer;
            border: none;
            font-size: 14px;
        }
        .fix-button:hover {
            background: #5568d3;
        }
        .error-box {
            background: #fee2e2;
            border: 1px solid #fecaca;
            border-left: 4px solid #ef4444;
            padding: 15px;
            border-radius: 6px;
            margin-top: 10px;
        }
        .success-box {
            background: #d1fae5;
            border: 1px solid #a7f3d0;
            border-left: 4px solid #10b981;
            padding: 15px;
            border-radius: 6px;
            margin-top: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        th {
            background: #f3f4f6;
            font-weight: 600;
            color: #374151;
        }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔍 Email/Link Diagnostic Tool</h1>
        <p class='subtitle'>Checking database schema, SMTP configuration, and identifying issues...</p>
";

$issues = [];
$fixes = [];

// 1. Check if config files exist and load them
echo "<div class='section'>
    <h2>1. Configuration Files</h2>";

$config_loaded = false;
$config_file_used = '';
if (file_exists('config.php')) {
    require_once 'config.php';
    $config_file_used = 'config.php';
    echo "<div class='check-item'>
        <div class='status success'>✓</div>
        <div class='details'>
            <div class='label'>config.php loaded</div>
            <div class='value'>Configuration file found and loaded successfully</div>
        </div>
    </div>";
    $config_loaded = true;
} elseif (file_exists('config_hosting.php')) {
    require_once 'config_hosting.php';
    $config_file_used = 'config_hosting.php';
    echo "<div class='check-item'>
        <div class='status success'>✓</div>
        <div class='details'>
            <div class='label'>config_hosting.php loaded</div>
            <div class='value'>Configuration file found and loaded successfully</div>
        </div>
    </div>";
    $config_loaded = true;
} else {
    echo "<div class='check-item'>
        <div class='status error'>✗</div>
        <div class='details'>
            <div class='label'>Configuration file missing</div>
            <div class='value'>Neither config.php nor config_hosting.php found</div>
        </div>
    </div>";
    $issues[] = "Configuration file missing";
}

if ($config_loaded) {
    require_once 'db.php';
}

echo "</div>";

// 2. Check Database Connection
echo "<div class='section'>
    <h2>2. Database Connection</h2>";

$db_connected = false;
$pdo = null;

if ($config_loaded) {
    try {
        $pdo = getDBConnection();
        echo "<div class='check-item'>
            <div class='status success'>✓</div>
            <div class='details'>
                <div class='label'>Database connection successful</div>
                <div class='value'>Connected to: " . htmlspecialchars(DB_NAME) . " on " . htmlspecialchars(DB_HOST) . "</div>
            </div>
        </div>";
        $db_connected = true;
    } catch (Exception $e) {
        echo "<div class='check-item'>
            <div class='status error'>✗</div>
            <div class='details'>
                <div class='label'>Database connection failed</div>
                <div class='value'>" . htmlspecialchars($e->getMessage()) . "</div>
            </div>
        </div>";
        $issues[] = "Database connection failed: " . $e->getMessage();
    }
} else {
    echo "<div class='check-item'>
        <div class='status error'>✗</div>
        <div class='details'>
            <div class='label'>Cannot check database</div>
            <div class='value'>Configuration not loaded</div>
        </div>
    </div>";
}

echo "</div>";

// 3. Check Database Schema
echo "<div class='section'>
    <h2>3. Database Schema Check</h2>";

if ($db_connected && $pdo) {
    try {
        // Check if invoices table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'invoices'");
        if ($stmt->rowCount() > 0) {
            echo "<div class='check-item'>
                <div class='status success'>✓</div>
                <div class='details'>
                    <div class='label'>Invoices table exists</div>
                </div>
            </div>";
            
            // Check for required columns
            $stmt = $pdo->query("DESCRIBE invoices");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $required_columns = [
                'share_token' => 'Required for shareable links',
                'share_token_created_at' => 'Tracks when token was created',
                'share_token_expires_at' => 'Tracks token expiration',
                'viewed_at' => 'Tracks first view time',
                'view_count' => 'Counts invoice views',
                'last_viewed_at' => 'Tracks last view time',
                'last_viewed_ip' => 'Tracks viewer IP'
            ];
            
            $missing_columns = [];
            
            echo "<table>
                <thead>
                    <tr>
                        <th>Column</th>
                        <th>Status</th>
                        <th>Purpose</th>
                    </tr>
                </thead>
                <tbody>";
            
            foreach ($required_columns as $col => $purpose) {
                $exists = in_array($col, $columns);
                if ($exists) {
                    echo "<tr>
                        <td><code>$col</code></td>
                        <td><span style='color: #065f46; font-weight: 600;'>✓ Exists</span></td>
                        <td>$purpose</td>
                    </tr>";
                } else {
                    echo "<tr style='background: #fee2e2;'>
                        <td><code>$col</code></td>
                        <td><span style='color: #991b1b; font-weight: 600;'>✗ Missing</span></td>
                        <td>$purpose</td>
                    </tr>";
                    $missing_columns[] = $col;
                }
            }
            
            echo "</tbody></table>";
            
            if (!empty($missing_columns)) {
                $issues[] = "Missing database columns: " . implode(', ', $missing_columns);
                echo "<div class='error-box'>
                    <strong>⚠️ Missing Columns Detected</strong>
                    <p>The following columns are missing from the invoices table: <strong>" . implode(', ', $missing_columns) . "</strong></p>
                    <p>These columns are required for the email/link sending functionality to work.</p>
                    <form method='post' style='margin-top: 15px;'>
                        <button type='submit' name='run_migration' class='fix-button'>🔧 Run Database Migration</button>
                    </form>
                </div>";
            } else {
                echo "<div class='success-box'>
                    <strong>✓ All Required Columns Present</strong>
                    <p>The database schema is correct and all required columns exist.</p>
                </div>";
            }
            
        } else {
            echo "<div class='check-item'>
                <div class='status error'>✗</div>
                <div class='details'>
                    <div class='label'>Invoices table missing</div>
                    <div class='value'>The invoices table does not exist in the database</div>
                </div>
            </div>";
            $issues[] = "Invoices table missing";
        }
    } catch (Exception $e) {
        echo "<div class='check-item'>
            <div class='status error'>✗</div>
            <div class='details'>
                <div class='label'>Schema check failed</div>
                <div class='value'>" . htmlspecialchars($e->getMessage()) . "</div>
            </div>
        </div>";
        $issues[] = "Schema check failed: " . $e->getMessage();
    }
}

echo "</div>";

// 4. Check SMTP Configuration
echo "<div class='section'>
    <h2>4. SMTP Configuration</h2>";

if ($config_loaded) {
    $smtp_checks = [
        'SMTP_HOST' => 'SMTP server address',
        'SMTP_PORT' => 'SMTP port number',
        'SMTP_USERNAME' => 'SMTP username',
        'SMTP_PASSWORD' => 'SMTP password',
        'SMTP_SECURE' => 'Encryption method',
        'COMPANY_EMAIL' => 'Sender email address',
        'COMPANY_NAME' => 'Sender name'
    ];
    
    foreach ($smtp_checks as $const => $desc) {
        if (defined($const) && !empty(constant($const))) {
            $value = constant($const);
            // Mask password
            if ($const === 'SMTP_PASSWORD') {
                $display_value = str_repeat('*', strlen($value));
            } else {
                $display_value = htmlspecialchars($value);
            }
            
            echo "<div class='check-item'>
                <div class='status success'>✓</div>
                <div class='details'>
                    <div class='label'>$const</div>
                    <div class='value'>$desc: <code>$display_value</code></div>
                </div>
            </div>";
        } else {
            echo "<div class='check-item'>
                <div class='status error'>✗</div>
                <div class='details'>
                    <div class='label'>$const missing</div>
                    <div class='value'>$desc is not configured</div>
                </div>
            </div>";
            $issues[] = "$const not configured";
        }
    }
} else {
    echo "<div class='check-item'>
        <div class='status error'>✗</div>
        <div class='details'>
            <div class='label'>Cannot check SMTP</div>
            <div class='value'>Configuration not loaded</div>
        </div>
    </div>";
}

echo "</div>";

// 5. Check PHPMailer
echo "<div class='section'>
    <h2>5. PHPMailer Library</h2>";

$phpmailer_files = [
    'lib/phpmailer/src/PHPMailer.php' => 'Main PHPMailer class',
    'lib/phpmailer/src/SMTP.php' => 'SMTP protocol handler',
    'lib/phpmailer/src/Exception.php' => 'Exception handler'
];

foreach ($phpmailer_files as $file => $desc) {
    if (file_exists($file)) {
        echo "<div class='check-item'>
            <div class='status success'>✓</div>
            <div class='details'>
                <div class='label'>$file</div>
                <div class='value'>$desc found</div>
            </div>
        </div>";
    } else {
        echo "<div class='check-item'>
            <div class='status error'>✗</div>
            <div class='details'>
                <div class='label'>$file missing</div>
                <div class='value'>$desc not found</div>
            </div>
        </div>";
        $issues[] = "PHPMailer file missing: $file";
    }
}

echo "</div>";

// 6. Check TCPDF
echo "<div class='section'>
    <h2>6. TCPDF Library (for PDF generation)</h2>";

if (file_exists('lib/tcpdf/tcpdf.php')) {
    echo "<div class='check-item'>
        <div class='status success'>✓</div>
        <div class='details'>
            <div class='label'>TCPDF library found</div>
            <div class='value'>PDF generation library is available</div>
        </div>
    </div>";
} else {
    echo "<div class='check-item'>
        <div class='status error'>✗</div>
        <div class='details'>
            <div class='label'>TCPDF library missing</div>
            <div class='value'>lib/tcpdf/tcpdf.php not found</div>
        </div>
    </div>";
    $issues[] = "TCPDF library missing";
}

echo "</div>";

// Handle migration
if (isset($_POST['run_migration']) && $db_connected && $pdo) {
    echo "<div class='section'>
        <h2>7. Running Database Migration</h2>";
    
    try {
        $migration_sql = file_get_contents('sql/add_invoice_tracking.sql');
        
        // Split by semicolon and execute each statement
        $statements = array_filter(array_map('trim', explode(';', $migration_sql)));
        
        foreach ($statements as $statement) {
            if (!empty($statement) && !preg_match('/^--/', $statement)) {
                try {
                    $pdo->exec($statement);
                } catch (Exception $e) {
                    // Ignore errors for statements that might already exist
                    if (!strpos($e->getMessage(), 'Duplicate column name')) {
                        throw $e;
                    }
                }
            }
        }
        
        echo "<div class='success-box'>
            <strong>✓ Migration Completed Successfully</strong>
            <p>All required database columns have been added to the invoices table.</p>
            <p><a href='diagnose_email_issue.php' class='fix-button'>🔄 Re-run Diagnostic</a></p>
        </div>";
        
    } catch (Exception $e) {
        echo "<div class='error-box'>
            <strong>✗ Migration Failed</strong>
            <p>" . htmlspecialchars($e->getMessage()) . "</p>
        </div>";
    }
    
    echo "</div>";
}

// Summary
echo "<div class='section'>
    <h2>Summary</h2>";

if (empty($issues)) {
    echo "<div class='success-box'>
        <strong>✓ All Checks Passed!</strong>
        <p>Your system is properly configured for email and link sending functionality.</p>
        <p>If you're still experiencing issues, they may be related to:</p>
        <ul style='margin-top: 10px; margin-left: 20px;'>
            <li>SMTP server connectivity (firewall, network issues)</li>
            <li>Email authentication (incorrect credentials)</li>
            <li>Email being marked as spam</li>
        </ul>
        <p style='margin-top: 15px;'>
            <a href='view_invoices.php' class='fix-button'>← Back to Invoices</a>
            <a href='test_email.php' class='fix-button' style='background: #10b981; margin-left: 10px;'>📧 Test Email Sending</a>
        </p>
    </div>";
} else {
    echo "<div class='error-box'>
        <strong>⚠️ Issues Found</strong>
        <p>The following issues were detected:</p>
        <ul style='margin-top: 10px; margin-left: 20px;'>";
    foreach ($issues as $issue) {
        echo "<li>" . htmlspecialchars($issue) . "</li>";
    }
    echo "</ul>
        <p style='margin-top: 15px;'>Please fix these issues before attempting to send emails or links.</p>
    </div>";
}

echo "</div>";

echo "    </div>
</body>
</html>";
?>
