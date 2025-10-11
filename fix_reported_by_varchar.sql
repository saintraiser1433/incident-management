-- Fix reported_by column to use VARCHAR for text names instead of INT for user IDs
-- This allows guest users to enter their names directly

-- First, let's check the current structure
DESCRIBE incident_reports;

-- Update the reported_by column to VARCHAR(255) to store text names
ALTER TABLE incident_reports MODIFY COLUMN reported_by VARCHAR(255) NOT NULL;

-- Verify the change
DESCRIBE incident_reports;

-- Show the updated structure
SELECT COLUMN_NAME, IS_NULLABLE, DATA_TYPE, COLUMN_DEFAULT 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'incident_reports' 
AND COLUMN_NAME = 'reported_by';

