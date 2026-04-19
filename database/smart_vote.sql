-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 13, 2026 at 06:40 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `smart_vote`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` bigint(20) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_type` enum('admin','voter','system') NOT NULL DEFAULT 'system',
  `username` varchar(100) DEFAULT NULL,
  `activity_type` varchar(50) NOT NULL,
  `activity_category` enum('authentication','election','voting','candidate','voter','backup','system','export','security') NOT NULL,
  `action` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `target_type` varchar(50) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `target_name` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `request_method` varchar(10) DEFAULT NULL,
  `request_uri` varchar(500) DEFAULT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `status` enum('success','failure','warning','info') NOT NULL DEFAULT 'info',
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `severity` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `fullname` varchar(150) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `password`, `fullname`, `created_at`) VALUES
(1, 'admin', '$2a$12$O2dTaPSf0Iktx7CyCJdcCu7WMGh8DQUfEgl1RubC9RiTx2AGN6aBK', 'System Administrator', '2026-04-13 16:03:49');

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `id` bigint(20) NOT NULL,
  `session_id` varchar(128) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_type` enum('admin','voter','system','anonymous') NOT NULL,
  `username` varchar(255) DEFAULT NULL,
  `action_category` enum('authentication','authorization','data_access','data_modification','system_configuration','security_event') NOT NULL,
  `action_type` varchar(100) NOT NULL,
  `resource_type` varchar(100) NOT NULL,
  `resource_id` varchar(100) DEFAULT NULL,
  `description` text NOT NULL,
  `result` enum('success','failure','partial','blocked') NOT NULL,
  `severity` enum('low','medium','high','critical') DEFAULT 'low',
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `request_method` varchar(10) DEFAULT NULL,
  `request_uri` varchar(500) DEFAULT NULL,
  `request_params` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `response_status` int(11) DEFAULT NULL,
  `before_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `after_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `risk_score` int(11) DEFAULT 0,
  `compliance_flags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `backup_logs`
--

CREATE TABLE `backup_logs` (
  `id` int(11) NOT NULL,
  `backup_type` enum('full','partial','manual','scheduled') NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_size` bigint(20) DEFAULT NULL,
  `tables_included` text DEFAULT NULL,
  `status` enum('success','failed','in_progress') DEFAULT 'in_progress',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `error_message` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `candidates`
--

CREATE TABLE `candidates` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `position_id` int(11) DEFAULT NULL,
  `position` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `symbol_path` varchar(255) DEFAULT NULL,
  `symbol` varchar(255) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `candidates`
--

INSERT INTO `candidates` (`id`, `name`, `position_id`, `position`, `description`, `photo`, `photo_path`, `symbol_path`, `symbol`, `active`, `created_at`) VALUES
(1, 'Sokiri Henry', 1, '', 'President', NULL, 'uploads/candidates/photos/candidate_1_photo_1776097827_69dd1a23aa514.png', 'uploads/candidates/symbols/candidate_1_symbol_1776097827_69dd1a238dcc8.png', NULL, 1, '2026-04-13 16:11:52');

-- --------------------------------------------------------

--
-- Table structure for table `compliance_events`
--

CREATE TABLE `compliance_events` (
  `id` bigint(20) NOT NULL,
  `audit_log_id` bigint(20) NOT NULL,
  `compliance_type` enum('gdpr','election_law','data_retention','access_control','audit_trail') NOT NULL,
  `requirement_met` tinyint(1) NOT NULL,
  `details` text DEFAULT NULL,
  `evidence` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`evidence`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `data_changes`
--

CREATE TABLE `data_changes` (
  `id` bigint(20) NOT NULL,
  `audit_log_id` bigint(20) NOT NULL,
  `table_name` varchar(100) NOT NULL,
  `record_id` varchar(100) NOT NULL,
  `field_name` varchar(100) NOT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `value_type` enum('string','number','boolean','json','encrypted') DEFAULT 'string',
  `change_type` enum('insert','update','delete') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `elections`
--

CREATE TABLE `elections` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `status` enum('active','closed','pending','expired') NOT NULL DEFAULT 'closed',
  `manually_activated` tinyint(1) DEFAULT 0,
  `manually_activated_at` timestamp NULL DEFAULT NULL,
  `voters_can_view_results` tinyint(1) DEFAULT 0,
  `results_published_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `name` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `election_positions`
--

CREATE TABLE `election_positions` (
  `id` int(11) NOT NULL,
  `election_id` int(11) NOT NULL,
  `position_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `encrypted_fields`
--

CREATE TABLE `encrypted_fields` (
  `id` int(11) NOT NULL,
  `table_name` varchar(100) NOT NULL,
  `field_name` varchar(100) NOT NULL,
  `record_id` varchar(100) NOT NULL,
  `encryption_method` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `encryption_audit`
--

CREATE TABLE `encryption_audit` (
  `id` bigint(20) NOT NULL,
  `operation` enum('encrypt','decrypt','key_rotation') NOT NULL,
  `table_name` varchar(100) NOT NULL,
  `field_name` varchar(100) NOT NULL,
  `record_id` varchar(100) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_type` enum('admin','voter','system') NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `success` tinyint(1) NOT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `geo_restrictions`
--

CREATE TABLE `geo_restrictions` (
  `id` int(11) NOT NULL,
  `rule_name` varchar(100) NOT NULL,
  `rule_type` enum('allow_countries','block_countries','allow_regions','block_regions') NOT NULL,
  `targets` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`targets`)),
  `applies_to` enum('all','admin','voter') DEFAULT 'all',
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ip_access_logs`
--

CREATE TABLE `ip_access_logs` (
  `id` bigint(20) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `country_code` varchar(2) DEFAULT NULL,
  `country_name` varchar(100) DEFAULT NULL,
  `region` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `request_uri` varchar(500) DEFAULT NULL,
  `request_method` varchar(10) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_type` enum('admin','voter','guest') DEFAULT NULL,
  `action_type` varchar(50) DEFAULT NULL,
  `status` enum('allowed','blocked','suspicious') NOT NULL,
  `reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ip_blacklist`
--

CREATE TABLE `ip_blacklist` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `ip_range` varchar(50) DEFAULT NULL,
  `reason` enum('manual','brute_force','suspicious','malware','spam') NOT NULL,
  `severity` enum('low','medium','high','critical') DEFAULT 'medium',
  `description` text DEFAULT NULL,
  `auto_generated` tinyint(1) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ip_whitelist`
--

CREATE TABLE `ip_whitelist` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `ip_range` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `username` varchar(255) NOT NULL,
  `attempt_type` enum('admin','voter') NOT NULL,
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_agent` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `ip_address`, `username`, `attempt_type`, `success`, `attempted_at`, `user_agent`) VALUES
(3, '::1', 'admin', 'admin', 1, '2026-04-13 16:09:10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(4, '::1', 'kiweewa7g@gmail.com', 'voter', 1, '2026-04-13 16:31:41', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');

-- --------------------------------------------------------

--
-- Table structure for table `positions`
--

CREATE TABLE `positions` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `max_candidates` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `positions`
--

INSERT INTO `positions` (`id`, `name`, `description`, `max_candidates`, `created_at`) VALUES
(1, 'President', 'President', 4, '2026-04-13 16:10:57');

-- --------------------------------------------------------

--
-- Table structure for table `rate_limits`
--

CREATE TABLE `rate_limits` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action_type` varchar(50) NOT NULL,
  `request_count` int(11) NOT NULL DEFAULT 1,
  `window_start` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_request` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `blocked_until` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rate_limits`
--

INSERT INTO `rate_limits` (`id`, `ip_address`, `user_id`, `action_type`, `request_count`, `window_start`, `last_request`, `blocked_until`) VALUES
(1, '::1', NULL, 'login', 4, '2026-04-13 16:05:05', '2026-04-13 16:31:41', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `security_events`
--

CREATE TABLE `security_events` (
  `id` int(11) NOT NULL,
  `event_type` varchar(50) NOT NULL,
  `severity` enum('low','medium','high','critical') NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_type` enum('admin','voter') DEFAULT NULL,
  `description` text NOT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `security_events`
--

INSERT INTO `security_events` (`id`, `event_type`, `severity`, `ip_address`, `user_id`, `user_type`, `description`, `metadata`, `created_at`) VALUES
(1, 'failed_login', 'medium', '::1', NULL, NULL, 'Failed login attempt for admin: admin', '{\"username\":\"admin\",\"user_type\":\"admin\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/146.0.0.0 Safari\\/537.36\"}', '2026-04-13 16:05:06'),
(2, 'failed_login', 'medium', '::1', NULL, NULL, 'Failed login attempt for admin: admin', '{\"username\":\"admin\",\"user_type\":\"admin\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/146.0.0.0 Safari\\/537.36\"}', '2026-04-13 16:05:13'),
(3, 'successful_login', 'low', '::1', NULL, NULL, 'Successful login for admin: admin', '{\"username\":\"admin\",\"user_type\":\"admin\"}', '2026-04-13 16:09:10'),
(4, 'encryption_key_generated', 'high', '::1', 1, 'admin', 'New encryption key generated', '{\"key_file\":\"C:\\\\xampp\\\\htdocs\\\\SmartVote\\\\includes\\/..\\/.encryption_key\",\"key_length\":32}', '2026-04-13 16:09:29'),
(5, 'successful_login', 'low', '::1', NULL, NULL, 'Successful login for voter: kiweewa7g@gmail.com', '{\"username\":\"kiweewa7g@gmail.com\",\"user_type\":\"voter\"}', '2026-04-13 16:31:41');

-- --------------------------------------------------------

--
-- Table structure for table `system_logs`
--

CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_type` enum('admin','voter') DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_logs`
--

INSERT INTO `system_logs` (`id`, `user_id`, `user_type`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'admin', 'admin_logout', 'Admin logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-13 16:39:57');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `two_factor_settings`
--

CREATE TABLE `two_factor_settings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_type` enum('admin','voter') NOT NULL,
  `method` enum('email','sms','totp','backup') NOT NULL,
  `identifier` varchar(255) NOT NULL,
  `backup_codes` text DEFAULT NULL,
  `enabled` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_used` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` bigint(20) NOT NULL,
  `session_id` varchar(128) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_type` enum('admin','voter','anonymous') NOT NULL,
  `username` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `country_code` varchar(2) DEFAULT NULL,
  `country_name` varchar(100) DEFAULT NULL,
  `login_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `logout_time` timestamp NULL DEFAULT NULL,
  `logout_type` enum('manual','timeout','forced','system') DEFAULT NULL,
  `session_duration` int(11) DEFAULT NULL,
  `actions_count` int(11) DEFAULT 0,
  `risk_events_count` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `voters`
--

CREATE TABLE `voters` (
  `id` int(11) NOT NULL,
  `voter_id` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `has_voted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `voters`
--

INSERT INTO `voters` (`id`, `voter_id`, `name`, `email`, `phone`, `address`, `password`, `has_voted`, `created_at`) VALUES
(1, '23A/KTI/NDICT/004', 'Kiweewa Godfrey', 'kiweewa7g@gmail.com', '0779172965', 'Kampala RD\r\nWakiso', '$2y$10$AAHkLmW9UjZYCLPdQRcHje8mRbaeYHnlA7SRbg0kN31qhWaMKg68.', 0, '2026-04-13 16:10:27');

-- --------------------------------------------------------

--
-- Table structure for table `votes`
--

CREATE TABLE `votes` (
  `id` int(11) NOT NULL,
  `election_id` int(11) DEFAULT NULL,
  `position_id` int(11) DEFAULT NULL,
  `voter_id` varchar(50) NOT NULL,
  `voter_id_int` int(11) DEFAULT NULL,
  `candidate_id` int(11) NOT NULL,
  `position` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `backup_logs`
--
ALTER TABLE `backup_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `candidates`
--
ALTER TABLE `candidates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_candidates_active` (`active`),
  ADD KEY `idx_position_id` (`position_id`);

--
-- Indexes for table `compliance_events`
--
ALTER TABLE `compliance_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_compliance_type` (`compliance_type`,`requirement_met`),
  ADD KEY `idx_audit_log` (`audit_log_id`);

--
-- Indexes for table `data_changes`
--
ALTER TABLE `data_changes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_table_record` (`table_name`,`record_id`),
  ADD KEY `idx_audit_log` (`audit_log_id`),
  ADD KEY `idx_field_change` (`field_name`,`change_type`);

--
-- Indexes for table `elections`
--
ALTER TABLE `elections`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `election_positions`
--
ALTER TABLE `election_positions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_election_position` (`election_id`,`position_id`),
  ADD KEY `position_id` (`position_id`);

--
-- Indexes for table `encrypted_fields`
--
ALTER TABLE `encrypted_fields`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_field` (`table_name`,`field_name`,`record_id`),
  ADD KEY `idx_table_record` (`table_name`,`record_id`);

--
-- Indexes for table `encryption_audit`
--
ALTER TABLE `encryption_audit`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_operation_time` (`operation`,`created_at`),
  ADD KEY `idx_table_field` (`table_name`,`field_name`);

--
-- Indexes for table `geo_restrictions`
--
ALTER TABLE `geo_restrictions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_type_active` (`rule_type`,`active`);

--
-- Indexes for table `ip_access_logs`
--
ALTER TABLE `ip_access_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ip_status` (`ip_address`,`status`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_user` (`user_id`,`user_type`);

--
-- Indexes for table `ip_blacklist`
--
ALTER TABLE `ip_blacklist`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_ip` (`ip_address`),
  ADD KEY `idx_active_reason` (`active`,`reason`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indexes for table `ip_whitelist`
--
ALTER TABLE `ip_whitelist`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_ip` (`ip_address`),
  ADD KEY `idx_active` (`active`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ip_type_time` (`ip_address`,`attempt_type`,`attempted_at`),
  ADD KEY `idx_username_type_time` (`username`,`attempt_type`,`attempted_at`);

--
-- Indexes for table `positions`
--
ALTER TABLE `positions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `rate_limits`
--
ALTER TABLE `rate_limits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ip_action_window` (`ip_address`,`action_type`,`window_start`),
  ADD KEY `idx_user_action_window` (`user_id`,`action_type`,`window_start`),
  ADD KEY `idx_blocked_until` (`blocked_until`);

--
-- Indexes for table `security_events`
--
ALTER TABLE `security_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_event_type_time` (`event_type`,`created_at`),
  ADD KEY `idx_severity_time` (`severity`,`created_at`);

--
-- Indexes for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_user_type` (`user_type`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `two_factor_settings`
--
ALTER TABLE `two_factor_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_method` (`user_id`,`user_type`,`method`),
  ADD KEY `idx_user` (`user_id`,`user_type`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_id` (`session_id`),
  ADD KEY `idx_user_active` (`user_id`,`user_type`,`is_active`),
  ADD KEY `idx_session_active` (`session_id`,`is_active`),
  ADD KEY `idx_login_time` (`login_time`),
  ADD KEY `idx_ip` (`ip_address`);

--
-- Indexes for table `voters`
--
ALTER TABLE `voters`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `voter_id` (`voter_id`);

--
-- Indexes for table `votes`
--
ALTER TABLE `votes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `voter_id` (`voter_id`),
  ADD KEY `candidate_id` (`candidate_id`),
  ADD KEY `idx_election_id` (`election_id`),
  ADD KEY `idx_position_id` (`position_id`),
  ADD KEY `idx_voter_id_int` (`voter_id_int`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `backup_logs`
--
ALTER TABLE `backup_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `candidates`
--
ALTER TABLE `candidates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `compliance_events`
--
ALTER TABLE `compliance_events`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `data_changes`
--
ALTER TABLE `data_changes`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `elections`
--
ALTER TABLE `elections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `election_positions`
--
ALTER TABLE `election_positions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `encrypted_fields`
--
ALTER TABLE `encrypted_fields`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `encryption_audit`
--
ALTER TABLE `encryption_audit`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `geo_restrictions`
--
ALTER TABLE `geo_restrictions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ip_access_logs`
--
ALTER TABLE `ip_access_logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ip_blacklist`
--
ALTER TABLE `ip_blacklist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ip_whitelist`
--
ALTER TABLE `ip_whitelist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `positions`
--
ALTER TABLE `positions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `rate_limits`
--
ALTER TABLE `rate_limits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `security_events`
--
ALTER TABLE `security_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `two_factor_settings`
--
ALTER TABLE `two_factor_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `voters`
--
ALTER TABLE `voters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `votes`
--
ALTER TABLE `votes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `compliance_events`
--
ALTER TABLE `compliance_events`
  ADD CONSTRAINT `compliance_events_ibfk_1` FOREIGN KEY (`audit_log_id`) REFERENCES `audit_log` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `data_changes`
--
ALTER TABLE `data_changes`
  ADD CONSTRAINT `data_changes_ibfk_1` FOREIGN KEY (`audit_log_id`) REFERENCES `audit_log` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `election_positions`
--
ALTER TABLE `election_positions`
  ADD CONSTRAINT `election_positions_ibfk_1` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `election_positions_ibfk_2` FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `votes`
--
ALTER TABLE `votes`
  ADD CONSTRAINT `votes_ibfk_1` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
