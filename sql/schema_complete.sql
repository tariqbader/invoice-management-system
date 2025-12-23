-- GeekMobile Invoice System - Complete Database Schema
-- MySQL Database Schema for Invoice Management System
-- This schema includes all tables, columns, and default data needed for installation

-- Create database (uncomment if needed)
-- CREATE DATABASE IF NOT EXISTS geekmobile_invoices;
-- USE geekmobile_invoices;

-- ============================================================================
-- CORE TABLES
-- ============================================================================

-- Clients table
CREATE TABLE IF NOT EXISTS clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    company VARCHAR(255),
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Invoices table (with shareable link tracking columns included)
CREATE TABLE IF NOT EXISTS invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    invoice_number VARCHAR(50) UNIQUE NOT NULL,
    invoice_date DATE NOT NULL,
    due_date DATE NOT NULL,
    subtotal DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    tax_rate DECIMAL(5, 2) NOT NULL DEFAULT 0.00,
    tax_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    discount_type ENUM('percentage', 'fixed') DEFAULT NULL,
    discount_value DECIMAL(10, 2) DEFAULT 0.00,
    discount_amount DECIMAL(10, 2) DEFAULT 0.00,
    total DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    status ENUM('unpaid', 'paid', 'overdue', 'partially_paid') DEFAULT 'unpaid',
    notes TEXT,
    is_recurring BOOLEAN DEFAULT FALSE,
    recurring_frequency ENUM('weekly', 'monthly', 'quarterly', 'yearly') DEFAULT NULL,
    next_invoice_date DATE DEFAULT NULL,
    -- Shareable link tracking columns
    share_token VARCHAR(64) UNIQUE NULL COMMENT 'Unique token for shareable public link',
    share_token_created_at TIMESTAMP NULL COMMENT 'When the share token was generated',
    share_token_expires_at TIMESTAMP NULL COMMENT 'When the share token expires (90 days from creation)',
    viewed_at TIMESTAMP NULL COMMENT 'First time the invoice was viewed via public link',
    view_count INT DEFAULT 0 COMMENT 'Number of times invoice was viewed via public link',
    last_viewed_at TIMESTAMP NULL COMMENT 'Last time invoice was viewed via public link',
    last_viewed_ip VARCHAR(45) NULL COMMENT 'IP address of last viewer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    INDEX idx_invoice_number (invoice_number),
    INDEX idx_client_id (client_id),
    INDEX idx_status (status),
    INDEX idx_due_date (due_date),
    INDEX idx_invoice_date (invoice_date),
    INDEX idx_share_token (share_token),
    INDEX idx_viewed_at (viewed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Invoice items table (with repair_details column included)
CREATE TABLE IF NOT EXISTS invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    description TEXT NOT NULL,
    repair_details TEXT NULL COMMENT 'Detailed information about repairs/work done',
    quantity DECIMAL(10, 2) NOT NULL DEFAULT 1.00,
    unit_price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    line_total DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    is_milestone BOOLEAN DEFAULT FALSE,
    milestone_name VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    INDEX idx_invoice_id (invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payments table
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_method ENUM('cash', 'bank_transfer', 'credit_card', 'paypal', 'stripe', 'other') NOT NULL,
    transaction_id VARCHAR(255) DEFAULT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    INDEX idx_invoice_id (invoice_id),
    INDEX idx_payment_date (payment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Users table for admin authentication
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Settings table
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Services table for predefined services/products
CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    unit_price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    category VARCHAR(100) DEFAULT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_category (category),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- DEFAULT DATA
-- ============================================================================

-- Insert default settings
INSERT INTO settings (setting_key, setting_value) VALUES
('company_name', 'GeekMobile IT Services'),
('company_address', '123 Tech Street, Silicon Valley, CA 94000'),
('company_email', 'billing@geekmobile.com'),
('company_phone', '+1 (555) 123-4567'),
('default_tax_rate', '10.00'),
('default_currency', 'USD'),
('invoice_prefix', 'INV-'),
('next_invoice_number', '1001')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);

-- Insert default IT services
INSERT INTO services (name, description, unit_price, category) VALUES
('Website Development', 'Custom website development and design', 2500.00, 'Web Development'),
('Mobile App Development', 'iOS and Android mobile application development', 5000.00, 'Mobile Development'),
('SEO Optimization', 'Search engine optimization services', 800.00, 'Marketing'),
('Server Setup', 'Server configuration and deployment', 500.00, 'Infrastructure'),
('Database Design', 'Database architecture and optimization', 1200.00, 'Database'),
('API Development', 'RESTful API development and integration', 1500.00, 'Backend'),
('UI/UX Design', 'User interface and experience design', 1000.00, 'Design'),
('Technical Support', 'Hourly technical support and maintenance', 100.00, 'Support'),
('Cloud Migration', 'Cloud infrastructure migration services', 3000.00, 'Infrastructure'),
('Security Audit', 'Security assessment and penetration testing', 2000.00, 'Security')
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- ============================================================================
-- VIEWS
-- ============================================================================

-- Create view for invoice summary
CREATE OR REPLACE VIEW invoice_summary AS
SELECT 
    i.id,
    i.invoice_number,
    i.invoice_date,
    i.due_date,
    i.total,
    i.status,
    c.name AS client_name,
    c.email AS client_email,
    COALESCE(SUM(p.amount), 0) AS paid_amount,
    (i.total - COALESCE(SUM(p.amount), 0)) AS balance_due,
    CASE 
        WHEN i.status = 'paid' THEN 'Paid'
        WHEN i.due_date < CURDATE() AND i.status != 'paid' THEN 'Overdue'
        WHEN COALESCE(SUM(p.amount), 0) > 0 AND COALESCE(SUM(p.amount), 0) < i.total THEN 'Partially Paid'
        ELSE 'Unpaid'
    END AS payment_status
FROM invoices i
LEFT JOIN clients c ON i.client_id = c.id
LEFT JOIN payments p ON i.id = p.invoice_id
GROUP BY i.id, i.invoice_number, i.invoice_date, i.due_date, i.total, i.status, c.name, c.email;

-- ============================================================================
-- COMPLETION MESSAGE
-- ============================================================================

SELECT 'Complete database schema created successfully!' as message;
SELECT 'All tables, indexes, default data, and views have been set up.' as status;
