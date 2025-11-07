-- Migration: Add reporter_contact_number field to incident_reports table
-- This allows storing the reporter's phone number for SMS notifications

ALTER TABLE `incident_reports` 
ADD COLUMN `reporter_contact_number` VARCHAR(20) NULL DEFAULT NULL AFTER `reported_by`,
ADD KEY `idx_incident_reports_reporter_contact` (`reporter_contact_number`);

