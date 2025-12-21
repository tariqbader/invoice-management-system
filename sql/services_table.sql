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

-- Insert some default IT services
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
