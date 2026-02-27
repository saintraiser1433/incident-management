-- Migration: link organization_members with users and add Organization Member role

ALTER TABLE `users`
MODIFY COLUMN `role` ENUM('Admin','Organization Account','Responder','Organization Member') NOT NULL;

ALTER TABLE `organization_members`
ADD COLUMN `user_id` INT DEFAULT NULL AFTER `organization_id`,
ADD KEY `idx_org_members_user` (`user_id`);

