# 🚀 GeekMobile Invoice System

A professional, feature-rich invoice management system for IT services and businesses. Built with PHP, MySQL, and modern web technologies.

![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)
![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-orange.svg)
![License](https://img.shields.io/badge/license-MIT-green.svg)

## ✨ Features

### 📊 Core Functionality
- ✅ **Invoice Management** - Create, view, edit, and manage invoices
- ✅ **Client Management** - Store and manage client information
- ✅ **Service Catalog** - Pre-defined IT services with quick selection
- ✅ **PDF Generation** - Professional PDF invoices using TCPDF
- ✅ **Email Integration** - Send invoices via SMTP
- ✅ **Payment Tracking** - Record and track payments
- ✅ **Reports & Analytics** - Revenue reports and statistics

### 🎨 Modern Interface
- ✅ **Responsive Design** - Works on desktop, tablet, and mobile
- ✅ **Gradient UI** - Beautiful purple gradient theme
- ✅ **Dashboard** - Statistics and quick navigation
- ✅ **Service Cards** - Visual service selection
- ✅ **Real-time Calculations** - Automatic totals and tax calculations

### ⚙️ Customization
- ✅ **Company Settings** - Customize company information
- ✅ **Logo Upload** - Add your company logo
- ✅ **Tax Configuration** - Set default tax rates
- ✅ **Email Settings** - Configure SMTP for email delivery
- ✅ **Invoice Prefix** - Customize invoice numbering

### 🔒 Security
- ✅ **PDO Prepared Statements** - SQL injection protection
- ✅ **Input Validation** - XSS prevention
- ✅ **Secure File Uploads** - Protected upload directory
- ✅ **Session Management** - Secure data handling

## 📋 Requirements

- **PHP** 7.4 or higher
- **MySQL** 5.7 or higher
- **Apache/Nginx** Web server
- **PHP Extensions:**
  - PDO
  - PDO_MySQL
  - GD (for image handling)
  - mbstring

## 🚀 Quick Installation

### Method 1: Web Installer (Recommended)

1. **Upload Files**
   ```bash
   # Upload all files to your web server
   # Example: /var/www/html/geekmobile-invoice/
   ```

2. **Run Installer**
   - Navigate to: `http://yourdomain.com/geekmobile-invoice/install.php`
   - Follow the 6-step installation wizard
   - Delete `install.php` after completion

3. **Start Using**
   - Go to dashboard: `http://yourdomain.com/geekmobile-invoice/`
   - Configure settings
   - Create your first invoice!

### Method 2: Manual Installation

1. **Create Database**
   ```sql
   CREATE DATABASE geekmobile_invoice CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. **Import Schema**
   ```bash
   mysql -u username -p geekmobile_invoice < sql/schema.sql
   mysql -u username -p geekmobile_invoice < sql/services_table.sql
   ```

3. **Configure Database**
   - Edit `config.php` with your database credentials
   - Update company information

4. **Set Permissions**
   ```bash
   chmod 755 uploads/
   ```

## 📁 Project Structure

```
geekmobile-invoice/
├── config.php              # Configuration file
├── db.php                  # Database connection
├── index.php               # Dashboard
├── install.php             # Installation wizard
├── create_invoice.php      # Invoice creation
├── view_invoices.php       # Invoice management
├── manage_services.php     # Service catalog
├── settings.php            # System settings
├── reports.php             # Reports & analytics
├── pdf_invoice.php         # PDF generation
├── send_invoice_email.php  # Email functionality
├── css/
│   └── style.css          # Stylesheet
├── js/
│   └── script.js          # JavaScript
├── lib/
│   ├── tcpdf/             # PDF library
│   └── phpmailer/         # Email library
├── sql/
│   ├── schema.sql         # Database schema
│   └── services_table.sql # Services data
├── uploads/               # Logo uploads
│   ├── .htaccess         # Security
│   └── index.php         # Protection
├── README.md              # This file
└── QUICKSTART.md          # Quick start guide
```

## 🎯 Usage Guide

### Creating an Invoice

1. **Navigate to Dashboard**
   - Click "Create New Invoice"

2. **Select Client**
   - Choose existing client or add new

3. **Add Services**
   - Select from service catalog
   - Or add custom items

4. **Configure Details**
   - Set due date
   - Add tax rate
   - Apply discounts
   - Add notes

5. **Generate Invoice**
   - Preview invoice
   - Download PDF
   - Send via email

### Managing Services

1. **Go to Manage Services**
2. **Add/Edit/Delete Services**
   - Service name
   - Description
   - Default price
   - Category

### Customizing Settings

1. **Navigate to Settings**
2. **Company Information**
   - Name, address, contact
   - Upload logo
3. **Invoice Settings**
   - Tax rate
   - Currency
   - Invoice prefix
4. **Email Configuration**
   - SMTP settings
   - Test email delivery

## 🔧 Configuration

### Database Configuration

Edit `config.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'geekmobile_invoice');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
```

### Email Configuration

Configure SMTP in Settings page or edit `config.php`:

```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
```

**Gmail Users:** Enable 2-factor authentication and use an App Password.

## 📊 Database Schema

### Main Tables

- **clients** - Client information
- **invoices** - Invoice records
- **invoice_items** - Line items
- **payments** - Payment records
- **services** - Service catalog
- **settings** - System configuration

### Relationships

```
clients (1) ──→ (N) invoices
invoices (1) ──→ (N) invoice_items
invoices (1) ──→ (N) payments
```

## 🎨 Customization

### Changing Theme Colors

Edit `css/style.css`:

```css
/* Main gradient */
background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);

/* Primary color */
.btn-primary {
    background: #667eea;
}
```

### Adding Custom Fields

1. Modify database schema
2. Update PHP forms
3. Adjust PDF template

## 🔐 Security Best Practices

1. **Delete install.php** after installation
2. **Use strong database passwords**
3. **Enable HTTPS** for production
4. **Regular backups** of database
5. **Keep PHP and MySQL updated**
6. **Restrict file permissions**
   ```bash
   chmod 644 *.php
   chmod 755 uploads/
   ```

## 🐛 Troubleshooting

### Database Connection Error

- Verify credentials in `config.php`
- Check MySQL service is running
- Ensure database exists

### PDF Generation Issues

- Check TCPDF library is present in `lib/tcpdf/`
- Verify PHP memory limit (128MB+)
- Check file permissions

### Email Not Sending

- Verify SMTP credentials
- Check firewall/port settings
- Test with Gmail App Password
- Review PHP error logs

### Upload Directory Issues

- Check directory exists: `uploads/`
- Verify permissions: `chmod 755 uploads/`
- Ensure `.htaccess` is present

## 📈 Performance Tips

1. **Enable PHP OPcache**
2. **Use MySQL query caching**
3. **Optimize images** before upload
4. **Regular database maintenance**
5. **Enable gzip compression**

## 🤝 Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request

## 📝 License

This project is licensed under the MIT License.

## 👨‍💻 Support

For issues and questions:

- 📧 Email: support@geekmobile.com
- 🐛 Issues: GitHub Issues
- 📖 Documentation: QUICKSTART.md

## 🎉 Credits

**Libraries Used:**
- [TCPDF](https://tcpdf.org/) - PDF generation
- [PHPMailer](https://github.com/PHPMailer/PHPMailer) - Email functionality

**Developed by:** GeekMobile Team

## 📅 Version History

### Version 1.0.0 (Current)
- ✅ Complete invoice management system
- ✅ Service catalog with 10 default IT services
- ✅ PDF generation and email integration
- ✅ Modern responsive UI
- ✅ Settings management with logo upload
- ✅ Reports and analytics
- ✅ One-click installation wizard

---

**Made with ❤️ for small businesses and freelancers**

For detailed setup instructions, see [QUICKSTART.md](QUICKSTART.md)
