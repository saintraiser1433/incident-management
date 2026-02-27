-- Migration: Add location coordinates, family contact, and resolution notes to incident_reports and organizations
-- This supports map pinning, family notifications, and detailed resolution reports

-- Step 1: Add latitude/longitude and family contact + resolution fields to incident_reports
ALTER TABLE `incident_reports`
ADD COLUMN `latitude` DECIMAL(10,8) NULL DEFAULT NULL AFTER `location`,
ADD COLUMN `longitude` DECIMAL(11,8) NULL DEFAULT NULL AFTER `latitude`,
ADD COLUMN `family_contact_name` VARCHAR(255) NULL DEFAULT NULL AFTER `reporter_contact_number`,
ADD COLUMN `family_contact_number` VARCHAR(20) NULL DEFAULT NULL AFTER `family_contact_name`,
ADD COLUMN `resolution_notes` TEXT NULL DEFAULT NULL AFTER `status`;

-- Step 2: Add latitude/longitude to organizations for map pinning
ALTER TABLE `organizations`
ADD COLUMN `latitude` DECIMAL(10,8) NULL DEFAULT NULL AFTER `address`,
ADD COLUMN `longitude` DECIMAL(11,8) NULL DEFAULT NULL AFTER `latitude`;

