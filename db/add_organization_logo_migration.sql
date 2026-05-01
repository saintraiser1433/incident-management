-- Per-organization logo (relative path stored, e.g. uploads/org_logos/org_1.png)
ALTER TABLE `organizations`
  ADD COLUMN `logo_path` VARCHAR(512) DEFAULT NULL AFTER `longitude`;
