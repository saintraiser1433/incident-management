-- Add contact_number field to users table for responders
-- Run this SQL in phpMyAdmin to add contact number support for users

ALTER TABLE `users` ADD COLUMN `contact_number` varchar(20) DEFAULT NULL AFTER `organization_id`;

-- Add index for contact_number for better performance
ALTER TABLE `users` ADD INDEX `idx_contact_number` (`contact_number`);
