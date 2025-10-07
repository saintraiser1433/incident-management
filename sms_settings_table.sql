-- SMS Settings Table for MDRRMO-GLAN Incident Reporting System
-- Copy and paste this SQL into phpMyAdmin to create the SMS settings table

CREATE TABLE IF NOT EXISTS `sms_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Insert default SMS settings (empty credentials, disabled by default)
INSERT INTO `sms_settings` (`username`, `password`, `is_active`) VALUES ('', '', 0);
