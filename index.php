<?php
require_once 'auth.php';
require_once 'db.php';
require_once 'config.php';

// Require authentication
requireAuth();

// Get current user
$current_user = getCurrentUser();

// Fetch summary stats
$pdo = getDBConnection();
$stmt = $pdo->query('SELECT COUNT(*) AS total_invoices FROM invoices');
$total_invoices = $stmt->fetchColumn();

$stmt = $pdo->query('SELECT SUM(total) AS total_revenue FROM invoices WHERE status = "paid"');
$total_revenue = $stmt->fetchColumn() ?: 0;

$stmt = $pdo->query('SELECT SUM(total) AS outstanding FROM invoices WHERE status IN ("unpaid", "overdue", "partially_paid")');
$outstanding = $stmt->fetchColumn() ?: 0;

$stmt = $pdo->query('SELECT COUNT(*) AS total_clients FROM clients');
$total_clients = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo APP_NAME; ?></title>
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
        }
        
        .header {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .user-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-badge {
            background: #f3f4f6;
            padding: 8px 15px;
            border-radius: 20px;
            color: #666;
            font-size: 14px;
        }
        
        .logout-btn {
            background: #ef4444;
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            text-decoration: none;
            font-size: 14px;
            transition: background 0.2s;
        }
        
        .logout-btn:hover {
            background: #dc2626;
        }
        
        .header-title {
            text-align: center;
        }
        
        .header h1 {
            color: #333;
            font-size: 36px;
            margin-bottom: 10px;
        }
        
        .header p {
            color: #666;
            font-size: 16px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            border-left: 4px solid;
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card.invoices { border-color: #667eea; }
        .stat-card.revenue { border-color: #10b981; }
        .stat-card.outstanding { border-color: #ef4444; }
        .stat-card.clients { border-color: #f59e0b; }
        
        .stat-card h3 {
            color: #666;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        
        .stat-card .value {
            color: #333;
            font-size: 36px;
            font-weight: bold;
        }
        
        .stat-card .icon {
            font-size: 40px;
            margin-bottom: 10px;
        }
        
        .nav-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .nav-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            text-decoration: none;
            color: inherit;
            transition: all 0.2s;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        
        .nav-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
        }
        
        .nav-card .icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        
        .nav-card h3 {
            color: #333;
            font-size: 20px;
            margin-bottom: 10px;
        }
        
        .nav-card p {
            color: #666;
            font-size: 14px;
            line-height: 1.5;
        }
        
        .footer {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            text-align: center;
            color: #666;
            font-size: 14px;
        }
        
        @media (max-width: 768px) {
            .stats-grid,
            .nav-grid {
                grid-template-columns: 1fr;
            }
            
            .user-bar {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="user-bar">
                <div class="user-info">
                    <div class="user-badge">
                        👤 <?php echo htmlspecialchars($current_user['full_name']); ?>
                    </div>
                </div>
                <a href="logout.php" class="logout-btn">🚪 Logout</a>
            </div>
            
            <div class="header-title">
                <h1>📊 <?php echo defined('COMPANY_NAME') ? COMPANY_NAME : APP_NAME; ?></h1>
                <p>Invoice Management Dashboard</p>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card invoices">
                <div class="icon">📄</div>
                <h3>Total Invoices</h3>
                <div class="value"><?php echo number_format($total_invoices); ?></div>
            </div>
            <div class="stat-card revenue">
                <div class="icon">💰</div>
                <h3>Total Revenue</h3>
                <div class="value">$<?php echo number_format($total_revenue, 2); ?></div>
            </div>
            <div class="stat-card outstanding">
                <div class="icon">⚠️</div>
                <h3>Outstanding</h3>
                <div class="value">$<?php echo number_format($outstanding, 2); ?></div>
            </div>
            <div class="stat-card clients">
                <div class="icon">👥</div>
                <h3>Total Clients</h3>
                <div class="value"><?php echo number_format($total_clients); ?></div>
            </div>
        </div>

        <!-- Navigation -->
        <div class="nav-grid">
            <a href="create_invoice.php" class="nav-card">
                <div class="icon">➕</div>
                <h3>Create Invoice</h3>
                <p>Create a new invoice with itemized services and client details</p>
            </a>
            
            <a href="view_invoices.php" class="nav-card">
                <div class="icon">📋</div>
                <h3>View Invoices</h3>
                <p>View, manage, and update all your invoices</p>
            </a>
            
            <a href="manage_services.php" class="nav-card">
                <div class="icon">🛠️</div>
                <h3>Manage Services</h3>
                <p>Add, edit, and organize your service catalog</p>
            </a>
            
            <a href="reports.php" class="nav-card">
                <div class="icon">📈</div>
                <h3>Reports & Analytics</h3>
                <p>View revenue reports, outstanding payments, and client history</p>
            </a>
            
            <a href="settings.php" class="nav-card">
                <div class="icon">⚙️</div>
                <h3>Settings</h3>
                <p>Customize company information and system preferences</p>
            </a>
            
            <?php if (hasRole('admin')): ?>
            <a href="manage_users.php" class="nav-card">
                <div class="icon">👥</div>
                <h3>User Management</h3>
                <p>Add, edit, and manage system users and admin rights</p>
            </a>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>&copy; <?php echo date('Y'); ?> <?php echo defined('COMPANY_NAME') ? COMPANY_NAME : 'GeekMobile Invoice System'; ?>. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
