-- Fix unit_price column type and repair corrupted data
-- Run this script if you're experiencing issues with unit prices showing incorrect values

-- Step 1: Ensure the column type is correct
ALTER TABLE invoice_items 
MODIFY COLUMN unit_price DECIMAL(10, 2) NOT NULL DEFAULT 0.00;

-- Step 2: Ensure line_total is also correct
ALTER TABLE invoice_items 
MODIFY COLUMN line_total DECIMAL(10, 2) NOT NULL DEFAULT 0.00;

-- Step 3: Ensure quantity is correct
ALTER TABLE invoice_items 
MODIFY COLUMN quantity DECIMAL(10, 2) NOT NULL DEFAULT 1.00;

-- Step 4: Fix corrupted data where unit_price seems wrong
-- This attempts to recalculate unit_price from line_total / quantity
-- ONLY for items where unit_price looks corrupted (> 1000000)
UPDATE invoice_items 
SET unit_price = ROUND(line_total / quantity, 2)
WHERE unit_price > 1000000 
  AND quantity > 0
  AND line_total < 1000000;

-- Step 5: Verify the fix
-- Run this to check if there are still issues:
-- SELECT id, description, quantity, unit_price, line_total, 
--        ROUND(line_total / quantity, 2) as calculated_unit_price
-- FROM invoice_items 
-- WHERE unit_price > 1000000 OR unit_price < 0;
