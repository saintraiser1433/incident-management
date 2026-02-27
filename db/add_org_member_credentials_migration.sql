-- Migration: Add login_username and login_password fields to organization_members
-- These are for future login use; currently stored as plain text for reference

ALTER TABLE `organization_members`
ADD COLUMN `login_password` VARCHAR(255) DEFAULT NULL AFTER `login_username`;

