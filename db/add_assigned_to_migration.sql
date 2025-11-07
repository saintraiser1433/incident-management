-- Migration: Add organization_members table and assigned_to field to report_queue table
-- This allows organization heads to assign reports to specific members (tags only, no login)

-- Step 1: Create organization_members table for non-login members
CREATE TABLE IF NOT EXISTS `organization_members` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `organization_id` INT NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `contact_number` VARCHAR(20) DEFAULT NULL,
  `email` VARCHAR(191) DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_org_members_org` (`organization_id`),
  KEY `idx_org_members_name` (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Step 2: Add assigned_to field to report_queue table (references organization_members.id)
ALTER TABLE `report_queue` 
ADD COLUMN `assigned_to` INT NULL DEFAULT NULL AFTER `priority_number`,
ADD KEY `idx_report_queue_assigned` (`assigned_to`);

-- Add foreign key constraint (optional, but recommended)
-- ALTER TABLE `report_queue` 
-- ADD CONSTRAINT `fk_report_queue_assigned_member` 
-- FOREIGN KEY (`assigned_to`) REFERENCES `organization_members` (`id`) ON DELETE SET NULL;

