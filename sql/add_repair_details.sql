-- Add repair_details column to invoice_items table
-- This column will store detailed information about repairs/work done for each service

ALTER TABLE invoice_items 
ADD COLUMN repair_details TEXT NULL AFTER description;

-- Add index for better query performance if needed
-- ALTER TABLE invoice_items ADD INDEX idx_repair_details (repair_details(100));
