# 🚀 GeekMobile Invoice System - Quick Start Guide

Get your invoice system up and running in 5 minutes!

## 📋 Prerequisites

Before you begin, ensure you have:

- ✅ Web server (Apache/Nginx) with PHP 7.4+
- ✅ MySQL 5.7+ database server
- ✅ Basic knowledge of web hosting
- ✅ FTP/File manager access to your server

## 🎯 Installation Steps

### Step 1: Upload Files (2 minutes)

1. **Download the system files**
2. **Upload to your web server**
   - Via FTP: Upload to `/public_html/geekmobile-invoice/`
   - Via cPanel: Use File Manager
   - Via SSH: `git clone` or `scp`

### Step 2: Run Installer (3 minutes)

1. **Open your browser**
   ```
   http://yourdomain.com/geekmobile-invoice/install.php
   ```

2. **Follow the 6-step wizard:**

   **Step 1: Database Connection**
   - Host: `localhost` (usually)
   - Database: `geekmobile_invoice`
   - Username: Your MySQL username
   - Password: Your MySQL password
   - Click "Continue"

   **Step 2: Create Tables**
   - Click "Create Tables"
   - Wait for confirmation

   **Step 3: Company Information**
   - Enter your company name
   - Enter your address
   - Enter your email
   - Enter your phone
   - Set default tax rate
   - Click "Continue"

   **Step 4: Create Directories**
   - Click "Create Directories"
   - System creates upload folders

   **Step 5: Generate Configuration**
   - Click "Generate Config"
   - Creates config.php file

   **Step 6: Complete!**
   - Installation finished
   - Click "Go to Dashboard"

3. **Delete install.php**
   ```bash
   # For security, delete the installer
   rm install.php
   ```

### Step 3: Start Using! (Now!)

You're ready to go! 🎉

## 🎨 First Steps After Installation

### 1. Configure Email (Optional but Recommended)

1. Go to **Settings** (⚙️ icon)
2. Scroll to **Email Settings**
3. Enter SMTP details:
   - **Gmail Example:**
     - Host: `smtp.gmail.com`
     - Port: `587`
     - Security: `TLS`
     - Username: `your-email@gmail.com`
     - Password: Your App Password
   - **Other Providers:** Check their SMTP documentation
4. Click "Save Settings"

**Gmail Users:** 
- Enable 2-factor authentication
- Generate an App Password: https://myaccount.google.com/apppasswords

### 2. Upload Your Logo

1. Go to **Settings**
2. Find **Company Logo** section
3. Click "Choose Logo File"
4. Select your logo (JPG, PNG, GIF, SVG)
5. Click "Save Settings"

### 3. Customize Services

1. Go to **Manage Services**
2. Review default IT services
3. Edit prices to match your rates
4. Add your own services
5. Delete services you don't offer

### 4. Create Your First Invoice

1. Click **Create New Invoice**
2. **Add Client:**
   - Choose "New Client"
   - Enter client details
3. **Select Services:**
   - Click service cards to add
   - Or add custom items
4. **Set Details:**
   - Choose due date
   - Adjust tax rate if needed
   - Add notes
5. **Create Invoice**
6. **Download PDF** or **Send Email**

## 📊 Dashboard Overview

Your dashboard shows:

- 📈 **Total Revenue** - All-time earnings
- 📋 **Total Invoices** - Number of invoices
- 👥 **Total Clients** - Number of clients
- 💰 **Paid Invoices** - Successfully paid
- ⏳ **Pending Invoices** - Awaiting payment
- ⚠️ **Overdue Invoices** - Past due date

## 🎯 Common Tasks

### Creating an Invoice

```
Dashboard → Create New Invoice → Fill Form → Create Invoice
```

### Viewing Invoices

```
Dashboard → View All Invoices → Click invoice to view details
```

### Managing Services

```
Dashboard → Manage Services → Add/Edit/Delete services
```

### Generating Reports

```
Dashboard → Reports → Select date range → View statistics
```

### Updating Settings

```
Dashboard → Settings → Update information → Save
```

## 🔧 Configuration Tips

### Setting Up Gmail SMTP

1. **Enable 2-Factor Authentication**
   - Go to Google Account settings
   - Security → 2-Step Verification

2. **Generate App Password**
   - Security → App passwords
   - Select "Mail" and your device
   - Copy the 16-character password

3. **Configure in Settings**
   ```
   SMTP Host: smtp.gmail.com
   SMTP Port: 587
   Security: TLS
   Username: your-email@gmail.com
   Password: [16-character app password]
   ```

### Customizing Tax Rates

**Default Tax Rate:**
- Settings → Invoice Settings → Default Tax Rate

**Per-Invoice Tax:**
- Create Invoice → Tax Rate field

### Invoice Numbering

**Change Prefix:**
- Settings → Invoice Settings → Invoice Prefix
- Example: `INV-`, `GM-`, `2024-`

**Next Number:**
- Automatically increments
- Stored in database settings

## 🐛 Quick Troubleshooting

### Can't Connect to Database

**Solution:**
1. Check `config.php` credentials
2. Verify MySQL is running
3. Ensure database exists
4. Check user permissions

### PDF Not Generating

**Solution:**
1. Check TCPDF library exists in `lib/tcpdf/`
2. Verify PHP memory limit (128MB+)
3. Check file permissions

### Email Not Sending

**Solution:**
1. Verify SMTP credentials in Settings
2. Check firewall allows port 587/465
3. For Gmail, use App Password
4. Test with a simple email client first

### Logo Not Uploading

**Solution:**
1. Check `uploads/` directory exists
2. Verify permissions: `chmod 755 uploads/`
3. Ensure file is an image (JPG, PNG, GIF, SVG)
4. Check file size (max 2MB)

### Page Shows Errors

**Solution:**
1. Check PHP error logs
2. Verify all files uploaded correctly
3. Ensure database tables created
4. Check file permissions

## 📱 Mobile Access

The system is fully responsive:

- ✅ Works on smartphones
- ✅ Works on tablets
- ✅ Touch-friendly interface
- ✅ Optimized layouts

## 🔐 Security Checklist

After installation:

- ✅ Delete `install.php`
- ✅ Use strong database password
- ✅ Enable HTTPS (SSL certificate)
- ✅ Regular database backups
- ✅ Keep PHP/MySQL updated
- ✅ Restrict file permissions

## 💡 Pro Tips

### Faster Invoice Creation

1. **Use Service Catalog**
   - Pre-configure common services
   - One-click to add to invoice

2. **Save Client Information**
   - Reuse for future invoices
   - No need to re-enter details

3. **Set Default Tax Rate**
   - Automatically applied
   - Override per invoice if needed

### Professional Invoices

1. **Upload Your Logo**
   - Appears on all PDFs
   - Professional branding

2. **Complete Company Info**
   - Full address
   - Contact details
   - Professional email

3. **Add Detailed Notes**
   - Payment terms
   - Thank you message
   - Special instructions

### Efficient Workflow

1. **Dashboard First**
   - Quick overview
   - See what needs attention

2. **Batch Processing**
   - Create multiple invoices
   - Send all at once

3. **Regular Reports**
   - Weekly revenue check
   - Monthly summaries
   - Track growth

## 📞 Getting Help

### Documentation

- 📖 **README.md** - Full documentation
- 🚀 **QUICKSTART.md** - This guide
- 💻 **Code Comments** - In-file documentation

### Support Channels

- 📧 **Email:** support@geekmobile.com
- 🐛 **Issues:** GitHub Issues
- 💬 **Community:** User forums

### Common Questions

**Q: Can I customize the design?**
A: Yes! Edit `css/style.css` for colors and layout.

**Q: Can I add more fields to invoices?**
A: Yes, but requires database and code modifications.

**Q: Is it multi-user?**
A: Current version is single-user. Multi-user coming soon.

**Q: Can I export data?**
A: Yes, use Reports page or export database directly.

**Q: Is it free?**
A: Yes, open-source under MIT license.

## 🎉 You're All Set!

Congratulations! Your invoice system is ready to use.

**Next Steps:**
1. ✅ Create your first invoice
2. ✅ Customize your settings
3. ✅ Upload your logo
4. ✅ Configure email
5. ✅ Start invoicing!

---

**Need more help?** Check the full [README.md](README.md) for detailed documentation.

**Happy Invoicing! 🚀**
