-- Add invoice tracking and shareable link columns
-- Run this migration to add shareable link functionality with view tracking

ALTER TABLE invoices
ADD COLUMN share_token VARCHAR(64) UNIQUE NULL COMMENT 'Unique token for shareable public link',
ADD COLUMN share_token_created_at TIMESTAMP NULL COMMENT 'When the share token was generated',
ADD COLUMN share_token_expires_at TIMESTAMP NULL COMMENT 'When the share token expires (90 days from creation)',
ADD COLUMN viewed_at TIMESTAMP NULL COMMENT 'First time the invoice was viewed via public link',
ADD COLUMN view_count INT DEFAULT 0 COMMENT 'Number of times invoice was viewed via public link',
ADD COLUMN last_viewed_at TIMESTAMP NULL COMMENT 'Last time invoice was viewed via public link',
ADD COLUMN last_viewed_ip VARCHAR(45) NULL COMMENT 'IP address of last viewer',
ADD INDEX idx_share_token (share_token),
ADD INDEX idx_viewed_at (viewed_at);

-- Generate tokens for existing invoices
UPDATE invoices 
SET 
    share_token = MD5(CONCAT(id, invoice_number, UNIX_TIMESTAMP(), RAND())),
    share_token_created_at = NOW(),
    share_token_expires_at = DATE_ADD(NOW(), INTERVAL 90 DAY)
WHERE share_token IS NULL;

-- Success message
SELECT 'Invoice tracking columns added successfully!' as message;
SELECT COUNT(*) as invoices_with_tokens FROM invoices WHERE share_token IS NOT NULL;
