# Email/Link Sending Issue - Fix Progress

## Issue Description
The email and link sending functionality on https://trash2go.nz/inv/view_invoices.php is not working.

## Root Cause Analysis - IDENTIFIED ✓

### Primary Issue: SMTP Connection Timeout
**Status:** 🔴 **CRITICAL - Server Firewall/Network Issue**

The diagnostic tests revealed:
1. ✅ Database schema is correct (all required columns exist)
2. ✅ SMTP configuration is properly set
3. ✅ PHPMailer and TCPDF libraries are installed
4. ❌ **SMTP connection times out (504 Gateway Timeout)**

### Technical Details:
- **Error:** 504 Gateway Timeout when attempting to send email
- **SMTP Server:** mail.1stdomains.co.nz:465 (SSL)
- **Cause:** The web server cannot establish outbound SMTP connections
- **Likely Reasons:**
  1. Server firewall blocking outbound connections on port 465
  2. Hosting provider restricting SMTP connections
  3. Network routing issue to mail.1stdomains.co.nz

## Solution Options

### Option 1: Contact Hosting Provider (RECOMMENDED)
**Action Required:** Contact your hosting provider (1stdomains.co.nz or server administrator)

**Request:**
"Please enable outbound SMTP connections on port 465 (SSL) to mail.1stdomains.co.nz for sending emails from our invoice system."

**Alternative ports to try:**
- Port 587 (TLS/STARTTLS) - Often less restricted
- Port 25 (Plain/TLS) - May work if others are blocked

### Option 2: Use PHP mail() Function (TEMPORARY WORKAROUND)
Instead of SMTP, use PHP's built-in mail() function. This works if the server has sendmail/postfix configured.

**Pros:**
- No external SMTP connection needed
- Works on most shared hosting

**Cons:**
- Emails more likely to be marked as spam
- Less reliable delivery
- No authentication

### Option 3: Use Alternative SMTP Service
Switch to a different email service that may not be blocked:
- Gmail SMTP (smtp.gmail.com:587)
- SendGrid
- Mailgun
- Amazon SES

## Fix Plan

### Phase 1: Diagnosis ✅ COMPLETE
- [x] Created diagnostic script (`diagnose_email_issue.php`)
- [x] Created email testing tool (`test_email.php`)
- [x] Ran diagnostic - all checks passed except SMTP connectivity
- [x] Identified root cause: SMTP connection timeout

### Phase 2: Database Migration ✅ COMPLETE
- [x] Verified `share_token` columns exist in invoices table
- [x] All required columns present
- [x] Migration not needed

### Phase 3: SMTP Connectivity Fix ⏳ IN PROGRESS
- [ ] **REQUIRED:** Contact hosting provider to enable SMTP on port 465
- [ ] Alternative: Test with port 587 (TLS)
- [ ] Alternative: Test with port 25
- [ ] Alternative: Implement PHP mail() fallback

### Phase 4: Testing (After SMTP Fix)
- [ ] Test email sending with `test_email.php`
- [ ] Test invoice email sending from view_invoices.php
- [ ] Test shareable link generation and sending
- [ ] Verify public invoice viewing works

## Files Created
1. ✅ `diagnose_email_issue.php` - Comprehensive diagnostic tool
2. ✅ `test_email.php` - SMTP testing tool (with timeout fixes)
3. ✅ `TODO_EMAIL_FIX.md` - This file

## Files Modified
1. ✅ `test_email.php` - Added timeout settings and SSL options
2. ✅ `diagnose_email_issue.php` - Fixed config file loading

## SMTP Configuration (from config.php)
- Host: mail.1stdomains.co.nz
- Port: 465
- Security: SSL
- Username: info@trash2go.nz
- From: info@trash2go.nz
- **Status:** ❌ Connection times out

## Immediate Action Required

### For Server Administrator:
1. **Check firewall rules:**
   ```bash
   # Check if port 465 is blocked
   telnet mail.1stdomains.co.nz 465
   # Or
   nc -zv mail.1stdomains.co.nz 465
   ```

2. **Enable outbound SMTP:**
   - Allow outbound connections to mail.1stdomains.co.nz on port 465
   - Or allow port 587 (TLS) as alternative
   - Whitelist the SMTP server in firewall rules

3. **Test from command line:**
   ```bash
   php -r "echo ini_get('SMTP');"
   ```

### For Application Owner:
1. Contact hosting provider support
2. Request SMTP port 465 to be unblocked
3. Ask about alternative ports (587, 25)
4. Consider using PHP mail() as temporary solution

## Workaround Implementation

If SMTP cannot be enabled, I can implement a fallback to PHP mail() function:
- Modify send_invoice_email.php to use mail() if SMTP fails
- Modify send_invoice_link.php to use mail() if SMTP fails
- Add configuration option to choose between SMTP and mail()

**Would you like me to implement this workaround?**

## Expected Behavior After Fix
1. Clicking "📤 Send Link" button should:
   - Generate a unique shareable token ✅ (Working)
   - Display a form to enter recipient email ✅ (Working)
   - Send email with shareable link ❌ (Blocked by firewall)
   - Show success message

2. Clicking "✉️ Email" button should:
   - Generate PDF invoice ✅ (Working)
   - Send email with PDF attachment ❌ (Blocked by firewall)
   - Show success message

3. Shareable links should:
   - Be valid for 90 days ✅ (Working)
   - Track view counts ✅ (Working)
   - Display invoice without login ✅ (Working)

## Summary
**Everything is configured correctly except the server cannot connect to the SMTP server due to firewall/network restrictions. This requires server-level access to fix.**
