# GeekMobile Invoice System - SQL Schema Documentation

## Overview

This directory contains all SQL schema files for the GeekMobile Invoice System database.

## Schema Files

### 1. **schema_complete.sql** ⭐ (RECOMMENDED)
**Purpose:** Complete, all-in-one database schema for fresh installations.

**Contains:**
- All core tables (clients, invoices, invoice_items, payments, users, settings, services)
- All additional columns (repair_details, shareable link tracking)
- Default data (settings, default IT services)
- Database views (invoice_summary)
- Proper indexes and foreign keys

**Usage:** This is the primary schema file used by `install.php` for new installations.

**When to use:** 
- ✅ Fresh installations
- ✅ Setting up new development environments
- ✅ Complete database recreation

---

### 2. **schema.sql** (Legacy)
**Purpose:** Original base schema without additional features.

**Contains:**
- Core tables: clients, invoices, invoice_items, payments, users, settings
- Basic indexes and foreign keys
- Default settings
- Invoice summary view

**Missing:**
- Services table
- repair_details column
- Shareable link tracking columns

**When to use:**
- ⚠️ Only if schema_complete.sql is not available
- ⚠️ Legacy installations (requires additional migration files)

---

### 3. **services_table.sql**
**Purpose:** Creates the services table with default IT services.

**Contains:**
- Services table structure
- 10 default IT services (Website Development, Mobile App, SEO, etc.)

**When to use:**
- 🔧 Adding services table to existing installations
- 🔧 Resetting services catalog

---

### 4. **add_repair_details.sql**
**Purpose:** Migration script to add repair_details column to invoice_items table.

**Contains:**
- ALTER TABLE statement to add repair_details TEXT column

**When to use:**
- 🔧 Upgrading existing databases that don't have repair_details column
- 🔧 NOT needed for fresh installations using schema_complete.sql

---

### 5. **add_invoice_tracking.sql**
**Purpose:** Migration script to add shareable link tracking to invoices table.

**Contains:**
- ALTER TABLE statements to add tracking columns:
  - share_token (unique token for public links)
  - share_token_created_at
  - share_token_expires_at
  - viewed_at
  - view_count
  - last_viewed_at
  - last_viewed_ip
- Generates tokens for existing invoices

**When to use:**
- 🔧 Upgrading existing databases to support shareable invoice links
- 🔧 NOT needed for fresh installations using schema_complete.sql

---

### 6. **fix_unit_price.sql**
**Purpose:** Maintenance script to fix corrupted unit_price data.

**Contains:**
- ALTER TABLE statements to ensure correct DECIMAL(10,2) data types
- UPDATE statements to repair corrupted price data

**When to use:**
- 🔧 Fixing data corruption issues in existing databases
- 🔧 Repairing incorrect unit_price values
- ❌ NOT needed for fresh installations

**Note:** This is a repair/maintenance script, not an installation script.

---

## Installation Workflow

### Fresh Installation (Recommended)
```
install.php → schema_complete.sql
```
This creates everything in one go.

### Legacy Installation (Fallback)
```
install.php → schema.sql → services_table.sql → add_repair_details.sql → add_invoice_tracking.sql
```
This is the old method, used only if schema_complete.sql is not available.

---

## Database Structure

### Tables Created:
1. **clients** - Customer information
2. **invoices** - Invoice headers with tracking
3. **invoice_items** - Line items with repair details
4. **payments** - Payment records
5. **users** - Admin/user authentication
6. **settings** - System configuration
7. **services** - Service catalog

### Views Created:
1. **invoice_summary** - Aggregated invoice data with payment status

---

## Maintenance

### To Reset Database:
```sql
DROP DATABASE IF EXISTS geekmobile_invoice;
```
Then run install.php with schema_complete.sql

### To Upgrade Existing Database:
1. Check which columns/tables are missing
2. Run appropriate migration scripts:
   - add_repair_details.sql (if repair_details column missing)
   - add_invoice_tracking.sql (if tracking columns missing)
   - services_table.sql (if services table missing)

### To Fix Data Issues:
- Run fix_unit_price.sql if experiencing price calculation problems

---

## Version History

- **v3.0** - schema_complete.sql (Complete unified schema)
- **v2.2** - Added shareable link tracking (add_invoice_tracking.sql)
- **v2.1** - Added repair details (add_repair_details.sql)
- **v2.0** - Added services table (services_table.sql)
- **v1.0** - Original schema (schema.sql)

---

## Notes

- All tables use `utf8mb4_unicode_ci` collation for proper Unicode support
- InnoDB engine for transaction support and foreign key constraints
- Proper indexing on frequently queried columns
- Cascading deletes for referential integrity
- Default values and constraints for data integrity

---

## Support

For issues or questions about the database schema, please refer to the main project documentation or contact the development team.
