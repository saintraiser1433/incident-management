-- Fix reported_by column to allow NULL values for guest users
-- This allows guest users to create incident reports without authentication

-- First, let's check the current structure
DESCRIBE incident_reports;

-- Update the reported_by column to allow NULL values
ALTER TABLE incident_reports MODIFY COLUMN reported_by INT NULL;

-- Verify the change
DESCRIBE incident_reports;

-- Show the updated structure
SELECT COLUMN_NAME, IS_NULLABLE, DATA_TYPE, COLUMN_DEFAULT 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'incident_reports' 
AND COLUMN_NAME = 'reported_by';

