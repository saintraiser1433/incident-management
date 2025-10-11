-- Remove Responder Role Users (Preserve Reports)
-- This script removes all users with 'Responder' role but keeps their incident reports

-- First, let's see what Responder users exist
SELECT id, name, email, role, created_at 
FROM users 
WHERE role = 'Responder';

-- Count incident reports by Responder users
SELECT COUNT(*) as responder_reports 
FROM incident_reports ir 
JOIN users u ON ir.reported_by = u.id 
WHERE u.role = 'Responder';

-- Step 1: Update incident reports to remove user association (set reported_by to NULL)
UPDATE incident_reports 
SET reported_by = NULL 
WHERE reported_by IN (
    SELECT id FROM users WHERE role = 'Responder'
);

-- Step 2: Delete Responder users
DELETE FROM users WHERE role = 'Responder';

-- Verify the removal
SELECT COUNT(*) as remaining_responders FROM users WHERE role = 'Responder';

-- Show remaining users
SELECT id, name, email, role, created_at FROM users ORDER BY role, name;

-- Show orphaned reports (reports without a user)
SELECT COUNT(*) as orphaned_reports FROM incident_reports WHERE reported_by IS NULL;

