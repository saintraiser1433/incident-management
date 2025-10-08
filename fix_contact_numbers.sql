-- Fix contact numbers for existing users
-- Run this in phpMyAdmin to update contact numbers

-- Update existing contact number to new format
UPDATE `users` SET `contact_number` = '09603171069' WHERE `id` = 8 AND `contact_number` = '9603171069';

-- Add contact numbers for other responders (you can change these to real numbers)
UPDATE `users` SET `contact_number` = '09123456789' WHERE `id` = 6 AND `role` = 'Responder';
UPDATE `users` SET `contact_number` = '09987654321' WHERE `id` = 7 AND `role` = 'Responder';
UPDATE `users` SET `contact_number` = '09555666777' WHERE `id` = 9 AND `role` = 'Responder';
UPDATE `users` SET `contact_number` = '09333444555' WHERE `id` = 11 AND `role` = 'Responder';

-- Verify the updates
SELECT id, name, role, contact_number FROM users WHERE role = 'Responder' AND contact_number IS NOT NULL;
