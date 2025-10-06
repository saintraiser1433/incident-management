-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Sep 18, 2025 at 10:14 AM
-- Server version: 8.3.0
-- PHP Version: 7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `incident_management`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `table_name` varchar(100) NOT NULL,
  `record_id` int DEFAULT NULL,
  `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_logs_user` (`user_id`),
  KEY `idx_audit_logs_timestamp` (`timestamp`)
) ENGINE=MyISAM AUTO_INCREMENT=135 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `table_name`, `record_id`, `timestamp`) VALUES
(1, 5, 'CREATE', 'incident_reports', 1, '2025-09-17 05:43:56'),
(2, 6, 'CREATE', 'incident_reports', 2, '2025-09-17 05:43:56'),
(3, 7, 'CREATE', 'incident_reports', 3, '2025-09-17 05:43:56'),
(4, 8, 'CREATE', 'incident_reports', 4, '2025-09-17 05:43:56'),
(5, 5, 'CREATE', 'incident_reports', 5, '2025-09-17 05:43:56'),
(6, 6, 'CREATE', 'incident_reports', 6, '2025-09-17 05:43:56'),
(7, 3, 'UPDATE', 'incident_reports', 1, '2025-09-17 05:43:56'),
(8, 2, 'UPDATE', 'incident_reports', 2, '2025-09-17 05:43:56'),
(9, 1, 'UPDATE', 'incident_reports', 3, '2025-09-17 05:43:56'),
(10, 4, 'UPDATE', 'incident_reports', 4, '2025-09-17 05:43:56'),
(11, 1, 'UPDATE', 'incident_reports', 5, '2025-09-17 05:43:56'),
(12, 2, 'UPDATE', 'incident_reports', 6, '2025-09-17 05:43:56'),
(13, 2, 'LOGIN', 'users', 2, '2025-09-17 06:18:30'),
(14, 1, 'LOGIN', 'users', 1, '2025-09-17 06:24:19'),
(15, 1, 'LOGOUT', 'users', 1, '2025-09-17 06:38:57'),
(16, 6, 'LOGIN', 'users', 6, '2025-09-17 06:39:05'),
(17, 6, 'LOGOUT', 'users', 6, '2025-09-17 06:43:40'),
(18, 1, 'LOGIN', 'users', 1, '2025-09-17 06:43:54'),
(19, 1, 'LOGOUT', 'users', 1, '2025-09-17 06:59:41'),
(20, 6, 'LOGIN', 'users', 6, '2025-09-17 06:59:47'),
(21, 6, 'LOGOUT', 'users', 6, '2025-09-17 07:05:46'),
(22, 1, 'LOGIN', 'users', 1, '2025-09-17 07:05:53'),
(23, 1, 'CREATE', 'organizations', 6, '2025-09-17 07:15:06'),
(24, 1, 'UPDATE', 'organizations', 3, '2025-09-17 07:23:27'),
(25, 1, 'UPDATE', 'organizations', 1, '2025-09-17 07:23:38'),
(26, 1, 'UPDATE', 'organizations', 5, '2025-09-17 07:23:43'),
(27, 1, 'UPDATE', 'organizations', 2, '2025-09-17 07:23:48'),
(28, 1, 'UPDATE', 'organizations', 4, '2025-09-17 07:23:53'),
(29, 1, 'LOGOUT', 'users', 1, '2025-09-17 07:25:07'),
(30, 2, 'LOGIN', 'users', 2, '2025-09-17 07:25:19'),
(31, 2, 'LOGOUT', 'users', 2, '2025-09-17 07:26:30'),
(32, 6, 'LOGIN', 'users', 6, '2025-09-17 07:26:39'),
(33, 6, 'CREATE', 'incident_reports', 7, '2025-09-17 07:34:08'),
(34, 6, 'CREATE', 'incident_reports', 8, '2025-09-17 07:35:34'),
(35, 6, 'LOGOUT', 'users', 6, '2025-09-17 07:45:50'),
(36, 1, 'LOGIN', 'users', 1, '2025-09-17 07:45:57'),
(37, 1, 'LOGOUT', 'users', 1, '2025-09-17 07:49:50'),
(38, 2, 'LOGIN', 'users', 2, '2025-09-17 07:50:26'),
(39, 2, 'LOGOUT', 'users', 2, '2025-09-17 07:51:27'),
(40, 6, 'LOGIN', 'users', 6, '2025-09-17 07:51:34'),
(41, 6, 'LOGOUT', 'users', 6, '2025-09-17 07:57:14'),
(42, 1, 'LOGIN', 'users', 1, '2025-09-17 07:57:22'),
(43, 1, 'LOGOUT', 'users', 1, '2025-09-17 08:04:27'),
(44, 2, 'LOGIN', 'users', 2, '2025-09-17 08:04:33'),
(45, 2, 'LOGOUT', 'users', 2, '2025-09-17 08:05:30'),
(46, 6, 'LOGIN', 'users', 6, '2025-09-17 08:05:38'),
(47, 6, 'LOGOUT', 'users', 6, '2025-09-17 08:06:31'),
(48, 1, 'LOGIN', 'users', 1, '2025-09-17 08:06:38'),
(49, 1, 'LOGOUT', 'users', 1, '2025-09-17 08:10:09'),
(50, 6, 'LOGIN', 'users', 6, '2025-09-17 08:10:15'),
(51, 6, 'LOGOUT', 'users', 6, '2025-09-17 08:15:47'),
(52, 1, 'LOGIN', 'users', 1, '2025-09-17 08:15:52'),
(53, 1, 'CREATE', 'organizations', 7, '2025-09-17 08:16:03'),
(54, 1, 'LOGOUT', 'users', 1, '2025-09-17 08:16:04'),
(55, 6, 'LOGIN', 'users', 6, '2025-09-17 08:16:11'),
(56, 6, 'LOGOUT', 'users', 6, '2025-09-17 08:17:15'),
(57, 1, 'LOGIN', 'users', 1, '2025-09-17 08:37:19'),
(58, 1, 'DELETE', 'organizations', 7, '2025-09-17 09:06:51'),
(59, 1, 'CREATE', 'organizations', 8, '2025-09-17 09:06:57'),
(60, 1, 'LOGOUT', 'users', 1, '2025-09-17 09:09:31'),
(61, 2, 'LOGIN', 'users', 2, '2025-09-17 09:09:39'),
(62, 2, 'UPDATE', 'incident_reports', 3, '2025-09-17 09:09:59'),
(63, 2, 'LOGOUT', 'users', 2, '2025-09-17 09:10:30'),
(64, 1, 'LOGIN', 'users', 1, '2025-09-17 09:10:35'),
(65, 1, 'CREATE', 'incident_updates', 9, '2025-09-17 09:17:44'),
(66, 1, 'CREATE', 'incident_comments', 7, '2025-09-17 09:17:47'),
(67, 1, 'LOGOUT', 'users', 1, '2025-09-17 09:18:17'),
(68, 2, 'LOGIN', 'users', 2, '2025-09-17 09:18:23'),
(69, 2, 'CREATE', 'incident_comments', 8, '2025-09-17 09:18:29'),
(70, 2, 'CREATE', 'incident_updates', 10, '2025-09-17 09:18:32'),
(71, 2, 'LOGOUT', 'users', 2, '2025-09-17 09:19:16'),
(72, 1, 'LOGIN', 'users', 1, '2025-09-17 09:19:25'),
(73, 1, 'UPDATE', 'incident_reports', 8, '2025-09-17 09:21:16'),
(74, 1, 'UPDATE', 'incident_reports', 8, '2025-09-17 09:21:28'),
(75, 1, 'UPDATE', 'incident_reports', 8, '2025-09-17 09:21:36'),
(76, 1, 'UPDATE', 'incident_reports', 8, '2025-09-17 09:27:09'),
(77, 1, 'UPDATE', 'incident_reports', 8, '2025-09-17 09:29:57'),
(78, 1, 'UPDATE', 'incident_reports', 8, '2025-09-17 09:30:02'),
(79, 1, 'UPDATE', 'incident_reports', 8, '2025-09-17 09:30:08'),
(80, 1, 'LOGIN', 'users', 1, '2025-09-17 09:30:42'),
(81, 1, 'LOGOUT', 'users', 1, '2025-09-17 09:34:14'),
(82, 1, 'LOGIN', 'users', 1, '2025-09-17 09:34:21'),
(83, 1, 'LOGOUT', 'users', 1, '2025-09-17 09:34:36'),
(84, 6, 'LOGIN', 'users', 6, '2025-09-17 09:34:42'),
(85, 6, 'LOGOUT', 'users', 6, '2025-09-17 09:34:55'),
(86, 1, 'LOGIN', 'users', 1, '2025-09-17 11:53:01'),
(87, 1, 'LOGIN', 'users', 1, '2025-09-18 09:33:32'),
(88, 1, 'LOGOUT', 'users', 1, '2025-09-18 09:34:18'),
(89, 6, 'LOGIN', 'users', 6, '2025-09-18 09:34:24'),
(90, 6, 'LOGOUT', 'users', 6, '2025-09-18 09:36:08'),
(91, 2, 'LOGIN', 'users', 2, '2025-09-18 09:36:58'),
(92, 2, 'LOGOUT', 'users', 2, '2025-09-18 09:42:07'),
(93, 6, 'LOGIN', 'users', 6, '2025-09-18 09:42:15'),
(94, 6, 'CREATE', 'incident_reports', 9, '2025-09-18 09:42:38'),
(95, 6, 'LOGOUT', 'users', 6, '2025-09-18 09:42:51'),
(96, 2, 'LOGIN', 'users', 2, '2025-09-18 09:42:57'),
(97, 2, 'UPDATE', 'incident_reports', 9, '2025-09-18 09:43:40'),
(98, 2, 'LOGOUT', 'users', 2, '2025-09-18 09:43:48'),
(99, 6, 'LOGIN', 'users', 6, '2025-09-18 09:43:54'),
(100, 6, 'CREATE', 'incident_reports', 10, '2025-09-18 09:44:10'),
(101, 6, 'LOGOUT', 'users', 6, '2025-09-18 09:44:12'),
(102, 2, 'LOGIN', 'users', 2, '2025-09-18 09:44:19'),
(103, 2, 'LOGOUT', 'users', 2, '2025-09-18 09:44:31'),
(104, 1, 'LOGIN', 'users', 1, '2025-09-18 09:44:37'),
(105, 1, 'LOGOUT', 'users', 1, '2025-09-18 09:45:05'),
(106, 6, 'LOGIN', 'users', 6, '2025-09-18 09:45:13'),
(107, 6, 'LOGOUT', 'users', 6, '2025-09-18 09:45:39'),
(108, 2, 'LOGIN', 'users', 2, '2025-09-18 09:45:45'),
(109, 2, 'LOGOUT', 'users', 2, '2025-09-18 09:54:48'),
(110, 1, 'LOGIN', 'users', 1, '2025-09-18 09:54:54'),
(111, 1, 'LOGOUT', 'users', 1, '2025-09-18 09:55:06'),
(112, 6, 'LOGIN', 'users', 6, '2025-09-18 09:55:15'),
(113, 6, 'LOGOUT', 'users', 6, '2025-09-18 09:55:52'),
(114, 2, 'LOGIN', 'users', 2, '2025-09-18 09:55:59'),
(115, 2, 'UPDATE', 'incident_reports', 10, '2025-09-18 09:56:26'),
(116, 2, 'LOGOUT', 'users', 2, '2025-09-18 09:56:31'),
(117, 6, 'LOGIN', 'users', 6, '2025-09-18 09:57:23'),
(118, 6, 'CREATE', 'incident_reports', 11, '2025-09-18 09:57:33'),
(119, 6, 'LOGOUT', 'users', 6, '2025-09-18 09:57:35'),
(120, 2, 'LOGIN', 'users', 2, '2025-09-18 09:57:41'),
(121, 2, 'LOGOUT', 'users', 2, '2025-09-18 09:59:06'),
(122, 6, 'LOGIN', 'users', 6, '2025-09-18 09:59:14'),
(123, 6, 'CREATE', 'incident_reports', 12, '2025-09-18 09:59:24'),
(124, 6, 'LOGOUT', 'users', 6, '2025-09-18 09:59:40'),
(125, 1, 'LOGIN', 'users', 1, '2025-09-18 09:59:45'),
(126, 1, 'LOGOUT', 'users', 1, '2025-09-18 10:06:03'),
(127, 2, 'LOGIN', 'users', 2, '2025-09-18 10:06:15'),
(128, 2, 'UPDATE', 'incident_reports', 11, '2025-09-18 10:06:37'),
(129, 2, 'LOGOUT', 'users', 2, '2025-09-18 10:07:07'),
(130, 6, 'LOGIN', 'users', 6, '2025-09-18 10:07:14'),
(131, 6, 'LOGOUT', 'users', 6, '2025-09-18 10:07:50'),
(132, 2, 'LOGIN', 'users', 2, '2025-09-18 10:07:54'),
(133, 2, 'UPDATE', 'incident_reports', 12, '2025-09-18 10:12:26'),
(134, 2, 'LOGOUT', 'users', 2, '2025-09-18 10:13:32');

-- --------------------------------------------------------

--
-- Table structure for table `incident_comments`
--

DROP TABLE IF EXISTS `incident_comments`;
CREATE TABLE IF NOT EXISTS `incident_comments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `report_id` int NOT NULL,
  `comment_text` text NOT NULL,
  `commented_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `report_id` (`report_id`),
  KEY `commented_by` (`commented_by`)
) ENGINE=MyISAM AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `incident_comments`
--

INSERT INTO `incident_comments` (`id`, `report_id`, `comment_text`, `commented_by`, `created_at`) VALUES
(1, 1, 'Kitchen staff should receive additional fire safety training.', 1, '2025-09-17 05:43:56'),
(2, 2, 'Need to review traffic control procedures for this section of highway.', 2, '2025-09-17 05:43:56'),
(3, 3, 'Consider installing additional handrails in hospital corridors.', 1, '2025-09-17 05:43:56'),
(4, 4, 'Recommend implementing visitor check-in system for dormitories.', 4, '2025-09-17 05:43:56'),
(5, 5, 'Mall security responded quickly and professionally.', 5, '2025-09-17 05:43:56'),
(6, 6, 'Parking garage lighting needs improvement for security.', 2, '2025-09-17 05:43:56'),
(7, 8, 'asd', 1, '2025-09-17 09:17:47'),
(8, 3, 'fdfs', 2, '2025-09-17 09:18:29');

-- --------------------------------------------------------

--
-- Table structure for table `incident_photos`
--

DROP TABLE IF EXISTS `incident_photos`;
CREATE TABLE IF NOT EXISTS `incident_photos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `report_id` int NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `uploaded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `report_id` (`report_id`)
) ENGINE=MyISAM AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `incident_photos`
--

INSERT INTO `incident_photos` (`id`, `report_id`, `file_path`, `uploaded_at`) VALUES
(1, 1, 'uploads/incident_1_photo1.jpg', '2025-09-17 05:43:56'),
(2, 1, 'uploads/incident_1_photo2.jpg', '2025-09-17 05:43:56'),
(3, 2, 'uploads/incident_2_photo1.jpg', '2025-09-17 05:43:56'),
(4, 4, 'uploads/incident_4_photo1.jpg', '2025-09-17 05:43:56'),
(5, 5, 'uploads/incident_5_photo1.jpg', '2025-09-17 05:43:56'),
(6, 6, 'uploads/incident_6_photo1.jpg', '2025-09-17 05:43:56'),
(7, 6, 'uploads/incident_6_photo2.jpg', '2025-09-17 05:43:56'),
(8, 7, 'uploads/incident_7_1758094448_0.jpg', '2025-09-17 07:34:08'),
(9, 8, 'uploads/incident_8_1758094534_0.jpg', '2025-09-17 07:35:34'),
(10, 9, 'uploads/incident_9_1758188558_0.jpeg', '2025-09-18 09:42:38');

-- --------------------------------------------------------

--
-- Table structure for table `incident_reports`
--

DROP TABLE IF EXISTS `incident_reports`;
CREATE TABLE IF NOT EXISTS `incident_reports` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `incident_date` date NOT NULL,
  `incident_time` time NOT NULL,
  `location` varchar(255) NOT NULL,
  `severity_level` enum('Low','Medium','High','Critical') NOT NULL,
  `category` enum('Fire','Accident','Security','Medical','Emergency','Other') NOT NULL,
  `reported_by` int NOT NULL,
  `organization_id` int NOT NULL,
  `status` enum('Pending','In Progress','Resolved','Closed') DEFAULT 'Pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_incident_reports_organization` (`organization_id`),
  KEY `idx_incident_reports_reported_by` (`reported_by`),
  KEY `idx_incident_reports_status` (`status`),
  KEY `idx_incident_reports_date` (`incident_date`)
) ENGINE=MyISAM AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `incident_reports`
--

INSERT INTO `incident_reports` (`id`, `title`, `description`, `incident_date`, `incident_time`, `location`, `severity_level`, `category`, `reported_by`, `organization_id`, `status`, `created_at`) VALUES
(1, 'Kitchen Fire at Restaurant', 'Small grease fire in the kitchen area of downtown restaurant. Fire was contained quickly but caused smoke damage.', '2024-01-15', '14:30:00', '123 Main Street, Downtown', 'Medium', 'Fire', 5, 3, 'Resolved', '2025-09-17 05:43:56'),
(2, 'Traffic Accident on Highway', 'Two-vehicle collision on Interstate 95. Minor injuries reported, vehicles blocking two lanes.', '2024-01-16', '08:45:00', 'Interstate 95, Mile Marker 42', 'High', 'Accident', 6, 2, 'In Progress', '2025-09-17 05:43:56'),
(3, 'Patient Fall in Hospital', 'Elderly patient fell in hallway near room 205. No serious injuries but requires medical attention.', '2024-01-17', '10:15:00', 'City General Hospital, 2nd Floor', 'Low', 'Medical', 7, 1, 'Pending', '2025-09-17 05:43:56'),
(4, 'Suspicious Activity on Campus', 'Unknown person seen loitering near student dormitories after hours. Security investigation ongoing.', '2024-01-18', '22:30:00', 'University Campus, Dormitory Area', 'Medium', 'Security', 8, 4, 'In Progress', '2025-09-17 05:43:56'),
(5, 'Medical Emergency at Mall', 'Person collapsed in food court. CPR administered, ambulance called.', '2024-01-19', '15:20:00', 'City Mall, Food Court', 'High', 'Medical', 5, 1, 'Resolved', '2025-09-17 05:43:56'),
(6, 'Car Break-in Incident', 'Multiple vehicles broken into in parking garage. Items stolen from vehicles.', '2024-01-20', '06:00:00', 'Downtown Parking Garage', 'Medium', 'Security', 6, 2, 'Closed', '2025-09-17 05:43:56'),
(7, 'dasd', 'fsdf', '2025-09-17', '15:33:00', 'fdsfsd', 'Medium', 'Fire', 6, 6, 'Pending', '2025-09-17 07:34:08'),
(8, 'fdsf', 'fsdfdsfds', '2025-09-17', '15:35:00', 'fsdfsd', 'Low', 'Fire', 6, 6, 'Closed', '2025-09-17 07:35:34'),
(9, 'fdsfds', 'fsdfsdfds', '2025-09-18', '17:42:00', 'fsdfsd', 'Low', 'Fire', 6, 1, 'Resolved', '2025-09-18 09:42:38'),
(10, 'fsdfsd', 'fsdfds', '2025-09-18', '17:43:00', 'fsdfsd', 'Medium', 'Fire', 6, 1, 'Resolved', '2025-09-18 09:44:10'),
(11, 'dfsdf', 'fsdfsdfsd', '2025-09-18', '17:57:00', 'fdsfds', 'Low', 'Fire', 6, 1, 'Resolved', '2025-09-18 09:57:33'),
(12, 'gfdgf', 'gfdgfd', '2025-09-18', '17:59:00', 'gfdgf', 'Low', 'Accident', 6, 1, 'Resolved', '2025-09-18 09:59:24');

-- --------------------------------------------------------

--
-- Table structure for table `incident_updates`
--

DROP TABLE IF EXISTS `incident_updates`;
CREATE TABLE IF NOT EXISTS `incident_updates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `report_id` int NOT NULL,
  `update_text` text NOT NULL,
  `updated_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `report_id` (`report_id`),
  KEY `updated_by` (`updated_by`)
) ENGINE=MyISAM AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `incident_updates`
--

INSERT INTO `incident_updates` (`id`, `report_id`, `update_text`, `updated_by`, `created_at`) VALUES
(1, 1, 'Fire department arrived on scene within 5 minutes. Fire was extinguished using foam extinguisher.', 3, '2025-09-17 05:43:56'),
(2, 1, 'Restaurant closed for cleanup and inspection. Expected to reopen tomorrow.', 3, '2025-09-17 05:43:56'),
(3, 2, 'Police units dispatched to scene. Traffic being diverted to alternate routes.', 2, '2025-09-17 05:43:56'),
(4, 2, 'Ambulance arrived. Two patients transported to hospital with minor injuries.', 2, '2025-09-17 05:43:56'),
(5, 3, 'Patient assessed by nursing staff. No serious injuries detected.', 1, '2025-09-17 05:43:56'),
(6, 4, 'Security patrol increased in dormitory area. Additional cameras being installed.', 4, '2025-09-17 05:43:56'),
(7, 5, 'Patient stabilized and transported to hospital. Family notified.', 1, '2025-09-17 05:43:56'),
(8, 6, 'Investigation completed. Suspect identified through security footage.', 2, '2025-09-17 05:43:56'),
(9, 8, 'ok', 1, '2025-09-17 09:17:44'),
(10, 3, 'fdsfsd', 2, '2025-09-17 09:18:32'),
(11, 8, 'Status changed from \'Pending\' to \'In Progress\'', 1, '2025-09-17 09:21:16'),
(12, 8, 'Status changed from \'In Progress\' to \'Closed\'', 1, '2025-09-17 09:21:28'),
(13, 8, 'Status changed from \'Closed\' to \'Pending\'', 1, '2025-09-17 09:21:36'),
(14, 8, 'Status changed from \'Pending\' to \'In Progress\'', 1, '2025-09-17 09:27:09'),
(15, 8, 'Status changed from \'In Progress\' to \'Resolved\'', 1, '2025-09-17 09:29:57'),
(16, 8, 'Status changed from \'Resolved\' to \'Pending\'', 1, '2025-09-17 09:30:02'),
(17, 8, 'Status changed from \'Pending\' to \'Closed\'', 1, '2025-09-17 09:30:08'),
(18, 9, 'Status changed from \'Pending\' to \'Resolved\'', 2, '2025-09-18 09:43:40'),
(19, 10, 'Report approved by organization. Assigned priority number #2. Status set to In Progress.', 2, '2025-09-18 09:56:01'),
(20, 10, 'Status changed from \'In Progress\' to \'Resolved\'', 2, '2025-09-18 09:56:26'),
(21, 11, 'Report approved by organization. Assigned priority number #3. Status set to In Progress.', 2, '2025-09-18 09:57:48'),
(22, 11, 'Status changed from \'In Progress\' to \'Resolved\'', 2, '2025-09-18 10:06:37'),
(23, 12, 'Report approved by organization. Assigned priority number #4. Status set to In Progress.', 2, '2025-09-18 10:08:17'),
(24, 12, 'Status changed from \'In Progress\' to \'Resolved\'', 2, '2025-09-18 10:12:26');

-- --------------------------------------------------------

--
-- Table structure for table `incident_witnesses`
--

DROP TABLE IF EXISTS `incident_witnesses`;
CREATE TABLE IF NOT EXISTS `incident_witnesses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `report_id` int NOT NULL,
  `witness_name` varchar(255) NOT NULL,
  `witness_contact` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `report_id` (`report_id`)
) ENGINE=MyISAM AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `incident_witnesses`
--

INSERT INTO `incident_witnesses` (`id`, `report_id`, `witness_name`, `witness_contact`, `created_at`) VALUES
(1, 1, 'Maria Rodriguez', 'maria.rodriguez@email.com', '2025-09-17 05:43:56'),
(2, 1, 'James Wilson', '+1-555-1001', '2025-09-17 05:43:56'),
(3, 2, 'David Lee', 'david.lee@email.com', '2025-09-17 05:43:56'),
(4, 3, 'Nurse Patricia Green', 'p.green@cityhospital.com', '2025-09-17 05:43:56'),
(5, 4, 'Student Mark Thompson', 'mark.thompson@university.edu', '2025-09-17 05:43:56'),
(6, 5, 'Mall Security Guard', 'security@citymall.com', '2025-09-17 05:43:56'),
(7, 6, 'Parking Attendant', '+1-555-2001', '2025-09-17 05:43:56'),
(8, 9, 'fdsf', '0915156456', '2025-09-18 09:42:38');

-- --------------------------------------------------------

--
-- Table structure for table `organizations`
--

DROP TABLE IF EXISTS `organizations`;
CREATE TABLE IF NOT EXISTS `organizations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `org_name` varchar(255) NOT NULL,
  `org_type` enum('Hospital','Police','Fire Department','Security','Emergency Services') NOT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `address` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `organizations`
--

INSERT INTO `organizations` (`id`, `org_name`, `org_type`, `contact_number`, `address`, `created_at`) VALUES
(1, 'City General Hospital', 'Hospital', '09301791258', '123 Medical Center Dr, City, State 12345', '2025-09-17 05:43:56'),
(2, 'Metropolitan Police Department', 'Police', '09301791258', '456 Law Enforcement Ave, City, State 12345', '2025-09-17 05:43:56'),
(3, 'City Fire Department', 'Fire Department', '0915454564', '789 Fire Station Rd, City, State 12345', '2025-09-17 05:43:56'),
(4, 'University Security', 'Security', '09301791258', '321 Campus Blvd, City, State 12345', '2025-09-17 05:43:56'),
(5, 'Emergency Medical Services', 'Emergency Services', '09301791258', '654 Emergency Way, City, State 12345', '2025-09-17 05:43:56'),
(6, 'bvcbcv', 'Hospital', '09301791258', 'fdssd', '2025-09-17 07:15:06'),
(8, 'gfdgdf', '', 'dasdsa', 'gfdgfd', '2025-09-17 09:06:57');

-- --------------------------------------------------------

--
-- Table structure for table `report_queue`
--

DROP TABLE IF EXISTS `report_queue`;
CREATE TABLE IF NOT EXISTS `report_queue` (
  `id` int NOT NULL AUTO_INCREMENT,
  `report_id` int NOT NULL,
  `organization_id` int NOT NULL,
  `status` enum('Waiting','Approved','Rejected') DEFAULT 'Waiting',
  `priority_number` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `approved_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_report_queue_org` (`organization_id`),
  KEY `idx_report_queue_status` (`status`),
  KEY `idx_report_queue_report` (`report_id`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `report_queue`
--

INSERT INTO `report_queue` (`id`, `report_id`, `organization_id`, `status`, `priority_number`, `created_at`, `approved_at`) VALUES
(1, 9, 1, 'Approved', 1, '2025-09-18 09:42:38', '2025-09-18 09:43:08'),
(2, 10, 1, 'Approved', 2, '2025-09-18 09:44:10', '2025-09-18 09:56:01'),
(3, 11, 1, 'Approved', 3, '2025-09-18 09:57:33', '2025-09-18 09:57:48'),
(4, 12, 1, 'Approved', 4, '2025-09-18 09:59:24', '2025-09-18 10:08:17');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(191) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Admin','Organization Account','Responder') NOT NULL,
  `organization_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `organization_id` (`organization_id`)
) ENGINE=MyISAM AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `organization_id`, `created_at`) VALUES
(1, 'System Administrator', 'admin@incidentmgmt.com', '$2y$10$SpFrF9vzgCYrkzOYXWgddeTmmtyRInNIhmBZMwiThebt5D5WpCQ1m', 'Admin', NULL, '2025-09-17 05:43:56'),
(2, 'Dr. Sarah Johnson', 'sarah.johnson@cityhospital.com', '$2y$10$/FtEmu6H3wLW0PLbrs/OjuBQgGhQFl/e9N7.l7NwxUZm/iTstP4Nu', 'Organization Account', 1, '2025-09-17 05:43:56'),
(3, 'Chief Michael Brown', 'michael.brown@metropd.com', '$2y$10$R5zUmkiiuR8Lj2.bc055l.iUoDNm6KgFw0I2QPpMx4u5aYpe72Me6', 'Organization Account', 2, '2025-09-17 05:43:56'),
(4, 'Captain Lisa Davis', 'lisa.davis@cityfire.com', '$2y$10$EiSlF6N2rQtbk83uhEekHea.ur.hjgef4VT2JzxO5Xd4lHQOsyONm', 'Organization Account', 3, '2025-09-17 05:43:56'),
(5, 'Security Manager Tom Wilson', 'tom.wilson@university.edu', '$2y$10$ThwVxrnGLBwbjkjCN81mK.jd34swMeIahD94KYHNdjlGyLq1sJ9Ue', 'Organization Account', 4, '2025-09-17 05:43:56'),
(6, 'John Smith', 'john.smith@email.com', '$2y$10$RbLoJYBYBVcFtyjoW2dy8un/in2XTRRCijg68emAGrqL1Ge38z7jW', 'Responder', NULL, '2025-09-17 05:43:56'),
(7, 'Jane Doe', 'jane.doe@email.com', '$2y$10$XVDQCrSC0CR7yyZiMh9svepr1kZRXbLqwrj/IVN59GDxhACgaYHN2', 'Responder', NULL, '2025-09-17 05:43:56'),
(8, 'Bob Johnson', 'bob.johnson@email.com', '$2y$10$fXyDwnA04/xKLAmxMMjjsu8REWzs9BKmTJswtHBapfrdAjlOOXTZ2', 'Responder', NULL, '2025-09-17 05:43:56'),
(9, 'Alice Brown', 'alice.brown@email.com', '$2y$10$gHsgRy522t8Rn1tEIOsi..dkQbQkySJ7i00nRPorNRc3n2sVsibJm', 'Responder', NULL, '2025-09-17 05:43:56');
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
