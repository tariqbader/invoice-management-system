<?php
/**
 * Add Default Admin User
 * Creates a default admin account with username: admin, password: admin123
 * Run this file once, then delete it for security
 */

require_once 'config.php';
require_once 'db.php';

try {
    $pdo = getDBConnection();
    
    // Check if admin user already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = 'admin'");
    $stmt->execute();
    
    if ($stmt->fetchColumn() > 0) {
        echo "❌ Admin user already exists!<br>";
        echo "If you want to reset the password, please delete the existing admin user first.";
        exit;
    }
    
    // Create admin user
    $username = 'admin';
    $email = 'admin@geekmobile.com';
    $password = 'admin123';
    $full_name = 'Administrator';
    $role = 'admin';
    
    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert admin user
    $stmt = $pdo->prepare("
        INSERT INTO users (username, email, password, full_name, role, is_active) 
        VALUES (?, ?, ?, ?, ?, 1)
    ");
    
    $stmt->execute([$username, $email, $hashed_password, $full_name, $role]);
    
    echo "✅ <strong>Admin user created successfully!</strong><br><br>";
    echo "<div style='background: #f0f9ff; border-left: 4px solid #3b82f6; padding: 15px; margin: 20px 0;'>";
    echo "<h3 style='margin: 0 0 10px 0; color: #1e40af;'>🔐 Login Credentials</h3>";
    echo "<p style='margin: 5px 0;'><strong>Username:</strong> admin</p>";
    echo "<p style='margin: 5px 0;'><strong>Password:</strong> admin123</p>";
    echo "<p style='margin: 5px 0;'><strong>Email:</strong> admin@geekmobile.com</p>";
    echo "</div>";
    
    echo "<div style='background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0;'>";
    echo "<h3 style='margin: 0 0 10px 0; color: #856404;'>⚠️ Important Security Notice</h3>";
    echo "<p style='margin: 5px 0;'>1. <strong>Delete this file (add_admin_user.php)</strong> immediately after use for security!</p>";
    echo "<p style='margin: 5px 0;'>2. <strong>Change the default password</strong> after first login.</p>";
    echo "<p style='margin: 5px 0;'>3. Use a strong password in production environments.</p>";
    echo "</div>";
    
    echo "<div style='margin: 20px 0;'>";
    echo "<a href='login.php' style='display: inline-block; background: #10b981; color: white; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: 500;'>Go to Login Page →</a>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "❌ <strong>Error:</strong> " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Admin User - GeekMobile Invoice System</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            margin: 0;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 600px;
            width: 100%;
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 Add Default Admin User</h1>
    </div>
</body>
</html>
