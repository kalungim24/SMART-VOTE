-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: 127.0.0.1    Database: smartvote
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Temporary table structure for view `active_candidates`
--

DROP TABLE IF EXISTS `active_candidates`;
/*!50001 DROP VIEW IF EXISTS `active_candidates`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `active_candidates` AS SELECT
 1 AS `id`,
  1 AS `name`,
  1 AS `position`,
  1 AS `description`,
  1 AS `photo`,
  1 AS `active`,
  1 AS `created_at` */;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activity_logs` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `user_type` enum('admin','voter','system') NOT NULL DEFAULT 'system',
  `username` varchar(100) DEFAULT NULL,
  `activity_type` varchar(50) NOT NULL,
  `activity_category` enum('authentication','election','voting','candidate','voter','backup','system','export','security') NOT NULL,
  `action` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `target_type` varchar(50) DEFAULT NULL COMMENT 'election, candidate, voter, backup, etc.',
  `target_id` int(11) DEFAULT NULL COMMENT 'ID of the affected record',
  `target_name` varchar(255) DEFAULT NULL COMMENT 'Name/title of the affected item',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `request_method` varchar(10) DEFAULT NULL,
  `request_uri` varchar(500) DEFAULT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `status` enum('success','failure','warning','info') NOT NULL DEFAULT 'info',
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Additional data in JSON format' CHECK (json_valid(`metadata`)),
  `severity` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=Low, 2=Medium, 3=High, 4=Critical',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_user_type` (`user_type`),
  KEY `idx_activity_type` (`activity_type`),
  KEY `idx_activity_category` (`activity_category`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_status` (`status`),
  KEY `idx_severity` (`severity`),
  KEY `idx_target_type_id` (`target_type`,`target_id`),
  KEY `idx_activity_search` (`activity_category`,`activity_type`,`created_at`),
  KEY `idx_user_activity` (`user_id`,`user_type`,`created_at`),
  KEY `idx_severity_status` (`severity`,`status`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_logs`
--

LOCK TABLES `activity_logs` WRITE;
/*!40000 ALTER TABLE `activity_logs` DISABLE KEYS */;
INSERT INTO `activity_logs` VALUES (1,NULL,'system','system','database_setup','system','Activity logging system initialized','Enhanced activity logging table created successfully',NULL,NULL,NULL,'127.0.0.1',NULL,NULL,NULL,NULL,'success','{\"version\": \"2.0\", \"features\": [\"real_time_tracking\", \"enhanced_metadata\", \"categorization\"]}',1,'2025-10-28 09:45:29'),(2,NULL,'admin','admin','system_access','authentication','Admin login','Administrator logged into the system',NULL,NULL,NULL,'127.0.0.1',NULL,NULL,NULL,NULL,'success','{\"login_time\": \"2025-10-28 12:00:00\"}',2,'2025-10-28 09:45:29'),(3,NULL,'system','anonymous','system_test','system','Testing fixed activity logging system','System diagnostic test after fixing tableExists method',NULL,NULL,NULL,'unknown',NULL,NULL,NULL,'','success','{\"test\":\"activity_fix\",\"timestamp\":\"2025-10-28 11:17:11\"}',1,'2025-10-28 10:17:11'),(4,NULL,'system','anonymous','system_test','system','Final system test','Complete system test with all fixes applied',NULL,NULL,NULL,'unknown',NULL,NULL,NULL,'','success','{\"test\":\"final_test\",\"version\":\"2.0\"}',1,'2025-10-28 10:18:12');
/*!40000 ALTER TABLE `activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admins`
--

DROP TABLE IF EXISTS `admins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `fullname` varchar(150) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admins`
--

LOCK TABLES `admins` WRITE;
/*!40000 ALTER TABLE `admins` DISABLE KEYS */;
INSERT INTO `admins` VALUES (2,'admin','$2y$10$d.goQQXqLsTm1kB0zxbNFO1IHvseih/.kQPI1myCfkOxzxuAoCoGq','System Administrator','2025-11-20 14:39:32');
/*!40000 ALTER TABLE `admins` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_log`
--

DROP TABLE IF EXISTS `audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_log` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
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
  `request_params` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`request_params`)),
  `response_status` int(11) DEFAULT NULL,
  `before_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`before_data`)),
  `after_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`after_data`)),
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `risk_score` int(11) DEFAULT 0,
  `compliance_flags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`compliance_flags`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_time` (`user_id`,`user_type`,`created_at`),
  KEY `idx_action_time` (`action_type`,`created_at`),
  KEY `idx_category_severity` (`action_category`,`severity`),
  KEY `idx_session` (`session_id`),
  KEY `idx_ip` (`ip_address`),
  KEY `idx_resource` (`resource_type`,`resource_id`),
  KEY `idx_risk_score` (`risk_score`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_log`
--

LOCK TABLES `audit_log` WRITE;
/*!40000 ALTER TABLE `audit_log` DISABLE KEYS */;
INSERT INTO `audit_log` VALUES (1,'',NULL,'anonymous',NULL,'security_event','system_test','security_system',NULL,'Security system test','success','low','0.0.0.0',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'{\"test_type\":\"comprehensive\",\"timestamp\":1761667726}',100,NULL,'2025-10-28 16:08:46');
/*!40000 ALTER TABLE `audit_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `backup_logs`
--

DROP TABLE IF EXISTS `backup_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backup_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `backup_type` enum('full','partial','manual','scheduled') NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_size` bigint(20) DEFAULT NULL,
  `tables_included` text DEFAULT NULL,
  `status` enum('success','failed','in_progress') DEFAULT 'in_progress',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_backup_type` (`backup_type`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `backup_logs`
--

LOCK TABLES `backup_logs` WRITE;
/*!40000 ALTER TABLE `backup_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `backup_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `candidates`
--

DROP TABLE IF EXISTS `candidates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `candidates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `position_id` int(11) DEFAULT NULL,
  `position` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `symbol_path` varchar(255) DEFAULT NULL,
  `symbol` varchar(255) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_candidates_active` (`active`),
  KEY `idx_position_id` (`position_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `candidates`
--

LOCK TABLES `candidates` WRITE;
/*!40000 ALTER TABLE `candidates` DISABLE KEYS */;
INSERT INTO `candidates` VALUES (1,'Margret Tumukwasibwe',1,'President','Dream Big',NULL,'Array','Array',NULL,1,'2025-10-28 14:00:39'),(2,'Sokiri Henry',5,'','efefe',NULL,'uploads/candidates/photos/candidate_2_photo_1763651184_691f2e709657a.png','uploads/candidates/symbols/candidate_2_symbol_1763651184_691f2e709471b.png',NULL,1,'2025-11-20 15:06:24'),(3,'soro livingstone',5,'','ererttt',NULL,'uploads/candidates/photos/candidate_3_photo_1763653144_691f3618dc63e.png','uploads/candidates/symbols/candidate_3_symbol_1763653144_691f3618d68a9.png',NULL,1,'2025-11-20 15:39:04'),(4,'Kiweewa Godfrey',2,'','hhhhhhhhhh',NULL,'uploads/candidates/photos/candidate_4_photo_1763655079_691f3da71fcf0.png','uploads/candidates/symbols/candidate_4_symbol_1763655079_691f3da71c1e4.png',NULL,1,'2025-11-20 16:11:19');
/*!40000 ALTER TABLE `candidates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `compliance_events`
--

DROP TABLE IF EXISTS `compliance_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `compliance_events` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `audit_log_id` bigint(20) NOT NULL,
  `compliance_type` enum('gdpr','election_law','data_retention','access_control','audit_trail') NOT NULL,
  `requirement_met` tinyint(1) NOT NULL,
  `details` text DEFAULT NULL,
  `evidence` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`evidence`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_compliance_type` (`compliance_type`,`requirement_met`),
  KEY `idx_audit_log` (`audit_log_id`),
  CONSTRAINT `compliance_events_ibfk_1` FOREIGN KEY (`audit_log_id`) REFERENCES `audit_log` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `compliance_events`
--

LOCK TABLES `compliance_events` WRITE;
/*!40000 ALTER TABLE `compliance_events` DISABLE KEYS */;
/*!40000 ALTER TABLE `compliance_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `data_changes`
--

DROP TABLE IF EXISTS `data_changes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `data_changes` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `audit_log_id` bigint(20) NOT NULL,
  `table_name` varchar(100) NOT NULL,
  `record_id` varchar(100) NOT NULL,
  `field_name` varchar(100) NOT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `value_type` enum('string','number','boolean','json','encrypted') DEFAULT 'string',
  `change_type` enum('insert','update','delete') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_table_record` (`table_name`,`record_id`),
  KEY `idx_audit_log` (`audit_log_id`),
  KEY `idx_field_change` (`field_name`,`change_type`),
  CONSTRAINT `data_changes_ibfk_1` FOREIGN KEY (`audit_log_id`) REFERENCES `audit_log` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `data_changes`
--

LOCK TABLES `data_changes` WRITE;
/*!40000 ALTER TABLE `data_changes` DISABLE KEYS */;
/*!40000 ALTER TABLE `data_changes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `election_positions`
--

DROP TABLE IF EXISTS `election_positions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `election_positions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `election_id` int(11) NOT NULL,
  `position_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_election_position` (`election_id`,`position_id`),
  KEY `fk_election_positions_position` (`position_id`),
  CONSTRAINT `fk_election_positions_election` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_election_positions_position` FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `election_positions`
--

LOCK TABLES `election_positions` WRITE;
/*!40000 ALTER TABLE `election_positions` DISABLE KEYS */;
INSERT INTO `election_positions` VALUES (1,1,5,'2025-10-28 10:41:05'),(2,1,1,'2025-10-28 10:41:05'),(3,1,3,'2025-10-28 10:41:05'),(4,1,6,'2025-10-28 10:41:05'),(5,1,4,'2025-10-28 10:41:05'),(6,1,2,'2025-10-28 10:41:05'),(7,2,5,'2025-11-20 15:18:35'),(8,2,1,'2025-11-20 15:18:35'),(9,2,3,'2025-11-20 15:18:35'),(10,2,6,'2025-11-20 15:18:35'),(11,2,4,'2025-11-20 15:18:35'),(12,2,2,'2025-11-20 15:18:35');
/*!40000 ALTER TABLE `election_positions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Temporary table structure for view `election_status_view`
--

DROP TABLE IF EXISTS `election_status_view`;
/*!50001 DROP VIEW IF EXISTS `election_status_view`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `election_status_view` AS SELECT
 1 AS `id`,
  1 AS `title`,
  1 AS `description`,
  1 AS `start_date`,
  1 AS `end_date`,
  1 AS `status`,
  1 AS `created_at`,
  1 AS `calculated_status` */;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `elections`
--

DROP TABLE IF EXISTS `elections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `elections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `name` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_elections_dates` (`start_date`,`end_date`),
  KEY `idx_elections_status` (`status`),
  KEY `idx_voters_can_view_results` (`voters_can_view_results`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `elections`
--

LOCK TABLES `elections` WRITE;
/*!40000 ALTER TABLE `elections` DISABLE KEYS */;
INSERT INTO `elections` VALUES (1,'Guild Council Elections 2025','Create, activate, and manage election periods.','2025-10-28 13:40:00','2025-10-29 13:40:00','expired',0,NULL,1,NULL,'2025-10-28 10:41:05','Guild Council Elections 2025'),(2,'students election','2025','2025-11-20 19:20:00','2025-11-21 18:18:00','active',0,NULL,0,NULL,'2025-11-20 15:18:35','');
/*!40000 ALTER TABLE `elections` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `encrypted_fields`
--

DROP TABLE IF EXISTS `encrypted_fields`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `encrypted_fields` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `table_name` varchar(100) NOT NULL,
  `field_name` varchar(100) NOT NULL,
  `record_id` varchar(100) NOT NULL,
  `encryption_method` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_field` (`table_name`,`field_name`,`record_id`),
  KEY `idx_table_record` (`table_name`,`record_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `encrypted_fields`
--

LOCK TABLES `encrypted_fields` WRITE;
/*!40000 ALTER TABLE `encrypted_fields` DISABLE KEYS */;
INSERT INTO `encrypted_fields` VALUES (1,'voters','email','1','AES-256-GCM','2025-10-28 16:08:46','2025-10-28 16:08:46');
/*!40000 ALTER TABLE `encrypted_fields` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `encryption_audit`
--

DROP TABLE IF EXISTS `encryption_audit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `encryption_audit` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `operation` enum('encrypt','decrypt','key_rotation') NOT NULL,
  `table_name` varchar(100) NOT NULL,
  `field_name` varchar(100) NOT NULL,
  `record_id` varchar(100) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_type` enum('admin','voter','system') NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `success` tinyint(1) NOT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_operation_time` (`operation`,`created_at`),
  KEY `idx_table_field` (`table_name`,`field_name`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `encryption_audit`
--

LOCK TABLES `encryption_audit` WRITE;
/*!40000 ALTER TABLE `encryption_audit` DISABLE KEYS */;
INSERT INTO `encryption_audit` VALUES (1,'encrypt','voters','email','1',NULL,'system','0.0.0.0',1,NULL,'2025-10-28 16:08:46'),(2,'decrypt','voters','email','1',NULL,'system','0.0.0.0',1,NULL,'2025-10-28 16:08:46');
/*!40000 ALTER TABLE `encryption_audit` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `geo_restrictions`
--

DROP TABLE IF EXISTS `geo_restrictions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `geo_restrictions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rule_name` varchar(100) NOT NULL,
  `rule_type` enum('allow_countries','block_countries','allow_regions','block_regions') NOT NULL,
  `targets` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`targets`)),
  `applies_to` enum('all','admin','voter') DEFAULT 'all',
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_type_active` (`rule_type`,`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `geo_restrictions`
--

LOCK TABLES `geo_restrictions` WRITE;
/*!40000 ALTER TABLE `geo_restrictions` DISABLE KEYS */;
/*!40000 ALTER TABLE `geo_restrictions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ip_access_logs`
--

DROP TABLE IF EXISTS `ip_access_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ip_access_logs` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ip_status` (`ip_address`,`status`),
  KEY `idx_created` (`created_at`),
  KEY `idx_user` (`user_id`,`user_type`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ip_access_logs`
--

LOCK TABLES `ip_access_logs` WRITE;
/*!40000 ALTER TABLE `ip_access_logs` DISABLE KEYS */;
INSERT INTO `ip_access_logs` VALUES (1,'192.168.1.100',NULL,NULL,NULL,NULL,'','','',NULL,'admin','test','allowed','Normal access','2025-10-28 16:08:46');
/*!40000 ALTER TABLE `ip_access_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ip_blacklist`
--

DROP TABLE IF EXISTS `ip_blacklist`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ip_blacklist` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `ip_range` varchar(50) DEFAULT NULL,
  `reason` enum('manual','brute_force','suspicious','malware','spam') NOT NULL,
  `severity` enum('low','medium','high','critical') DEFAULT 'medium',
  `description` text DEFAULT NULL,
  `auto_generated` tinyint(1) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_ip` (`ip_address`),
  KEY `idx_active_reason` (`active`,`reason`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ip_blacklist`
--

LOCK TABLES `ip_blacklist` WRITE;
/*!40000 ALTER TABLE `ip_blacklist` DISABLE KEYS */;
/*!40000 ALTER TABLE `ip_blacklist` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ip_whitelist`
--

DROP TABLE IF EXISTS `ip_whitelist`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ip_whitelist` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `ip_range` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_ip` (`ip_address`),
  KEY `idx_active` (`active`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ip_whitelist`
--

LOCK TABLES `ip_whitelist` WRITE;
/*!40000 ALTER TABLE `ip_whitelist` DISABLE KEYS */;
/*!40000 ALTER TABLE `ip_whitelist` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `login_attempts`
--

DROP TABLE IF EXISTS `login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `username` varchar(255) NOT NULL,
  `attempt_type` enum('admin','voter') NOT NULL,
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_agent` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ip_type_time` (`ip_address`,`attempt_type`,`attempted_at`),
  KEY `idx_username_type_time` (`username`,`attempt_type`,`attempted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_attempts`
--

LOCK TABLES `login_attempts` WRITE;
/*!40000 ALTER TABLE `login_attempts` DISABLE KEYS */;
INSERT INTO `login_attempts` VALUES (6,'::1','admin','admin',1,'2025-10-28 16:08:15','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36'),(7,'::1','admin','admin',1,'2025-10-29 08:45:22','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36'),(8,'::1','sokirihenry211@gmail.com','voter',1,'2025-10-29 08:46:02','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36'),(9,'::1','admin','admin',1,'2025-10-29 08:46:28','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36'),(12,'::1','admin','admin',1,'2025-10-29 08:47:44','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36'),(15,'::1','admin','admin',1,'2025-11-20 14:40:25','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36'),(16,'::1','kiweewa7g@gmail.com','voter',1,'2025-11-20 15:11:56','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36'),(20,'::1','admin','admin',1,'2025-11-20 15:16:00','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36'),(21,'::1','kiweewa7g@gmail.com','voter',1,'2025-11-20 15:18:45','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36'),(22,'::1','admin','admin',1,'2025-11-20 15:19:10','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36'),(23,'::1','kiweewa7g@gmail.com','voter',1,'2025-11-20 15:37:27','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36'),(24,'::1','admin','admin',1,'2025-11-20 15:38:09','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36'),(25,'::1','kiweewa7g@gmail.com','voter',1,'2025-11-20 15:39:16','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36'),(28,'::1','admin','admin',1,'2025-11-20 15:39:46','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36'),(29,'::1','kiweewa7g@gmail.com','voter',1,'2025-11-20 15:40:06','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36'),(30,'::1','admin','admin',1,'2025-11-20 15:46:39','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36'),(31,'::1','kiweewa7g@gmail.com','voter',1,'2025-11-20 15:53:18','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36'),(34,'::1','admin','admin',1,'2025-11-20 16:09:15','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36'),(36,'::1','jken@gmail.com','voter',1,'2025-11-20 16:16:03','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36'),(37,'::1','admin','admin',1,'2025-11-20 16:31:29','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36'),(38,'::1','admin','admin',1,'2025-11-20 17:19:27','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36'),(42,'::1','jken@gmail.com','voter',1,'2025-11-20 17:22:52','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36'),(43,'::1','admin','admin',1,'2025-11-20 19:09:22','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36'),(46,'::1','admin','admin',1,'2025-11-21 05:37:04','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36');
/*!40000 ALTER TABLE `login_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notification_settings`
--

DROP TABLE IF EXISTS `notification_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notification_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_name` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `description` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_name` (`setting_name`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notification_settings`
--

LOCK TABLES `notification_settings` WRITE;
/*!40000 ALTER TABLE `notification_settings` DISABLE KEYS */;
INSERT INTO `notification_settings` VALUES (1,'failed_login_threshold','5','Failed login attempts before alert','2025-10-28 16:08:45'),(2,'blocked_ip_threshold','3','Blocked IPs before alert','2025-10-28 16:08:45'),(3,'security_event_threshold','10','Security events before alert','2025-10-28 16:08:45'),(4,'notification_cooldown','300','Seconds between similar alerts','2025-10-28 16:08:45'),(5,'admin_emails','[{\"email\":\"admin@smartvote.system\",\"name\":\"System Administrator\"}]','Admin notification recipients','2025-10-28 16:08:45'),(6,'smtp_enabled','false','Enable SMTP email sending','2025-10-28 16:08:45'),(7,'smtp_host','','SMTP server host','2025-10-28 16:08:45'),(8,'smtp_port','587','SMTP server port','2025-10-28 16:08:45'),(9,'smtp_username','','SMTP authentication username','2025-10-28 16:08:45'),(10,'smtp_password','','SMTP authentication password','2025-10-28 16:08:45');
/*!40000 ALTER TABLE `notification_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `positions`
--

DROP TABLE IF EXISTS `positions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `positions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `max_candidates` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `positions`
--

LOCK TABLES `positions` WRITE;
/*!40000 ALTER TABLE `positions` DISABLE KEYS */;
INSERT INTO `positions` VALUES (1,'President','Head of the student body',1,'2025-10-28 09:45:29'),(2,'Vice President','Assistant to the President',1,'2025-10-28 09:45:29'),(3,'Secretary','Handles documentation and communication',1,'2025-10-28 09:45:29'),(4,'Treasurer','Manages financial affairs',1,'2025-10-28 09:45:29'),(5,'Guild President','Head of the student guild',1,'2025-10-28 09:45:29'),(6,'Speaker','Presides over meetings and debates',1,'2025-10-28 09:45:29');
/*!40000 ALTER TABLE `positions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rate_limits`
--

DROP TABLE IF EXISTS `rate_limits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rate_limits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action_type` varchar(50) NOT NULL,
  `request_count` int(11) NOT NULL DEFAULT 1,
  `window_start` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_request` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `blocked_until` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ip_action_window` (`ip_address`,`action_type`,`window_start`),
  KEY `idx_user_action_window` (`user_id`,`action_type`,`window_start`),
  KEY `idx_blocked_until` (`blocked_until`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rate_limits`
--

LOCK TABLES `rate_limits` WRITE;
/*!40000 ALTER TABLE `rate_limits` DISABLE KEYS */;
INSERT INTO `rate_limits` VALUES (6,'::1',NULL,'login',25,'2025-11-20 14:40:25','2025-11-20 16:31:29',NULL),(7,'::1',NULL,'login',1,'2025-11-20 15:19:40','2025-11-20 17:19:27','2025-11-20 13:29:40'),(8,'::1',NULL,'login',0,'2025-11-20 15:19:52','2025-11-20 15:19:52','2025-11-20 13:29:52'),(9,'::1',NULL,'login',0,'2025-11-20 15:20:39','2025-11-20 15:20:39','2025-11-20 13:30:39'),(10,'::1',NULL,'login',0,'2025-11-20 15:20:42','2025-11-20 15:20:42','2025-11-20 13:30:42'),(11,'::1',NULL,'login',0,'2025-11-20 15:20:46','2025-11-20 15:20:46','2025-11-20 13:30:46'),(12,'::1',NULL,'login',2,'2025-11-20 15:21:14','2025-11-20 17:22:10','2025-11-20 13:31:14'),(13,'::1',NULL,'login',2,'2025-11-20 15:22:45','2025-11-20 17:22:52','2025-11-20 13:32:45'),(14,'::1',NULL,'login',0,'2025-11-20 15:25:26','2025-11-20 15:25:26','2025-11-20 13:35:26'),(15,'::1',NULL,'login',0,'2025-11-20 15:25:31','2025-11-20 15:25:31','2025-11-20 13:35:31'),(16,'::1',NULL,'login',0,'2025-11-20 15:35:30','2025-11-20 15:35:30','2025-11-20 13:45:30'),(17,'::1',NULL,'login',0,'2025-11-20 15:35:33','2025-11-20 15:35:33','2025-11-20 13:45:33'),(18,'::1',NULL,'login',1,'2025-11-20 19:09:22','2025-11-20 19:09:22',NULL),(19,'::1',NULL,'login',3,'2025-11-21 05:36:51','2025-11-21 05:37:04',NULL);
/*!40000 ALTER TABLE `rate_limits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `security_events`
--

DROP TABLE IF EXISTS `security_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `security_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_type` varchar(50) NOT NULL,
  `severity` enum('low','medium','high','critical') NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_type` enum('admin','voter') DEFAULT NULL,
  `description` text NOT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_event_type_time` (`event_type`,`created_at`),
  KEY `idx_severity_time` (`severity`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=66 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `security_events`
--

LOCK TABLES `security_events` WRITE;
/*!40000 ALTER TABLE `security_events` DISABLE KEYS */;
INSERT INTO `security_events` VALUES (1,'failed_login','medium','0.0.0.0',NULL,NULL,'Failed login attempt for voter: testuser','{\"username\":\"testuser\",\"user_type\":\"voter\",\"user_agent\":\"Unknown\"}','2025-10-28 15:46:51'),(2,'failed_login','medium','0.0.0.0',NULL,NULL,'Failed login attempt for voter: testuser','{\"username\":\"testuser\",\"user_type\":\"voter\",\"user_agent\":\"Unknown\"}','2025-10-28 15:46:51'),(3,'failed_login','medium','0.0.0.0',NULL,NULL,'Failed login attempt for voter: testuser','{\"username\":\"testuser\",\"user_type\":\"voter\",\"user_agent\":\"Unknown\"}','2025-10-28 15:46:51'),(4,'failed_login','medium','0.0.0.0',NULL,NULL,'Failed login attempt for voter: testuser','{\"username\":\"testuser\",\"user_type\":\"voter\",\"user_agent\":\"Unknown\"}','2025-10-28 15:46:51'),(5,'failed_login','medium','0.0.0.0',NULL,NULL,'Failed login attempt for voter: testuser','{\"username\":\"testuser\",\"user_type\":\"voter\",\"user_agent\":\"Unknown\"}','2025-10-28 15:46:51'),(7,'successful_login','low','::1',NULL,NULL,'Successful login for admin: admin','{\"username\":\"admin\",\"user_type\":\"admin\"}','2025-10-28 16:08:15'),(8,'encryption_key_generated','high','0.0.0.0',NULL,NULL,'New encryption key generated','{\"key_file\":\"C:\\\\xampp\\\\htdocs\\\\SmartVote\\\\includes\\/..\\/.encryption_key\",\"key_length\":32}','2025-10-28 16:08:45'),(9,'2fa_enabled','low','0.0.0.0',NULL,NULL,'2FA enabled for admin user 1 using email','{\"user_id\":1,\"user_type\":\"admin\",\"method\":\"email\"}','2025-10-28 16:08:46'),(10,'successful_login','low','::1',NULL,NULL,'Successful login for admin: admin','{\"username\":\"admin\",\"user_type\":\"admin\"}','2025-10-29 08:45:22'),(11,'csrf_validation_failed','high','::1',1,'admin','CSRF token validation failed for voter login','{\"username\":\"sokirihenry211@gmail.com\"}','2025-10-29 08:45:47'),(12,'csrf_validation_failed','high','::1',1,'admin','CSRF token validation failed for voter login','{\"username\":\"sokirihenry211@gmail.com\"}','2025-10-29 08:45:54'),(13,'successful_login','low','::1',1,'admin','Successful login for voter: sokirihenry211@gmail.com','{\"username\":\"sokirihenry211@gmail.com\",\"user_type\":\"voter\"}','2025-10-29 08:46:02'),(14,'successful_login','low','::1',1,'voter','Successful login for admin: admin','{\"username\":\"admin\",\"user_type\":\"admin\"}','2025-10-29 08:46:28'),(15,'failed_login','medium','::1',NULL,NULL,'Failed login attempt for admin: kiweewa7g@gmail.com','{\"username\":\"kiweewa7g@gmail.com\",\"user_type\":\"admin\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/141.0.0.0 Safari\\/537.36\"}','2025-10-29 08:46:50'),(16,'failed_login','medium','::1',NULL,NULL,'Failed login attempt for admin: kiweewa7g@gmail.com','{\"username\":\"kiweewa7g@gmail.com\",\"user_type\":\"admin\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/141.0.0.0 Safari\\/537.36\"}','2025-10-29 08:47:21'),(17,'successful_login','low','::1',NULL,NULL,'Successful login for admin: admin','{\"username\":\"admin\",\"user_type\":\"admin\"}','2025-10-29 08:47:44'),(18,'failed_login','medium','::1',NULL,NULL,'Failed login attempt for admin: admin','{\"username\":\"admin\",\"user_type\":\"admin\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/142.0.0.0 Safari\\/537.36\"}','2025-11-14 07:14:20'),(19,'failed_login','medium','::1',NULL,NULL,'Failed login attempt for admin: admin','{\"username\":\"admin\",\"user_type\":\"admin\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/142.0.0.0 Safari\\/537.36\"}','2025-11-19 10:25:30'),(20,'successful_login','low','::1',NULL,NULL,'Successful login for admin: admin','{\"username\":\"admin\",\"user_type\":\"admin\"}','2025-11-20 14:40:25'),(21,'successful_login','low','::1',2,'admin','Successful login for voter: kiweewa7g@gmail.com','{\"username\":\"kiweewa7g@gmail.com\",\"user_type\":\"voter\"}','2025-11-20 15:11:56'),(22,'failed_login','medium','::1',2,'voter','Failed login attempt for admin: admin','{\"username\":\"admin\",\"user_type\":\"admin\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/142.0.0.0 Safari\\/537.36\"}','2025-11-20 15:14:30'),(23,'failed_login','medium','::1',2,'voter','Failed login attempt for admin: admin','{\"username\":\"admin\",\"user_type\":\"admin\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/142.0.0.0 Safari\\/537.36\"}','2025-11-20 15:14:39'),(24,'csrf_validation_failed','high','::1',2,'voter','CSRF token validation failed for admin login','{\"username\":\"admin\"}','2025-11-20 15:14:45'),(25,'csrf_validation_failed','high','::1',2,'voter','CSRF token validation failed for admin login','{\"username\":\"admin\"}','2025-11-20 15:14:52'),(26,'failed_login','medium','::1',2,'voter','Failed login attempt for admin: admin','{\"username\":\"admin\",\"user_type\":\"admin\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/142.0.0.0 Safari\\/537.36\"}','2025-11-20 15:14:57'),(27,'successful_login','low','::1',2,'voter','Successful login for admin: admin','{\"username\":\"admin\",\"user_type\":\"admin\"}','2025-11-20 15:16:00'),(28,'successful_login','low','::1',2,'admin','Successful login for voter: kiweewa7g@gmail.com','{\"username\":\"kiweewa7g@gmail.com\",\"user_type\":\"voter\"}','2025-11-20 15:18:46'),(29,'successful_login','low','::1',2,'voter','Successful login for admin: admin','{\"username\":\"admin\",\"user_type\":\"admin\"}','2025-11-20 15:19:10'),(30,'rate_limit_exceeded','medium','::1',2,'admin','Voter login rate limit exceeded','{\"action\":\"login\",\"username\":\"kiweewa7g@gmail.com\",\"retry_after\":600}','2025-11-20 15:19:40'),(31,'rate_limit_exceeded','medium','::1',2,'admin','Voter login rate limit exceeded','{\"action\":\"login\",\"username\":\"kiweewa7g@gmail.com\",\"retry_after\":600}','2025-11-20 15:19:52'),(32,'rate_limit_exceeded','medium','::1',2,'admin','Voter login rate limit exceeded','{\"action\":\"login\",\"username\":\"kiweewa7g@gmail.com\",\"retry_after\":600}','2025-11-20 15:20:39'),(33,'rate_limit_exceeded','medium','::1',2,'admin','Voter login rate limit exceeded','{\"action\":\"login\",\"username\":\"kiweewa7g@gmail.com\",\"retry_after\":600}','2025-11-20 15:20:42'),(34,'rate_limit_exceeded','medium','::1',2,'admin','Voter login rate limit exceeded','{\"action\":\"login\",\"username\":\"kiweewa7g@gmail.com\",\"retry_after\":600}','2025-11-20 15:20:46'),(35,'rate_limit_exceeded','medium','::1',2,'admin','Voter login rate limit exceeded','{\"action\":\"login\",\"username\":\"kiweewa7g@gmail.com\",\"retry_after\":600}','2025-11-20 15:21:14'),(36,'rate_limit_exceeded','medium','::1',2,'admin','Voter login rate limit exceeded','{\"action\":\"login\",\"username\":\"kiweewa7g@gmail.com\",\"retry_after\":600}','2025-11-20 15:22:45'),(37,'rate_limit_exceeded','medium','::1',2,'admin','Voter login rate limit exceeded','{\"action\":\"login\",\"username\":\"kiweewa7g@gmail.com\",\"retry_after\":600}','2025-11-20 15:25:26'),(38,'rate_limit_exceeded','medium','::1',2,'admin','Voter login rate limit exceeded','{\"action\":\"login\",\"username\":\"kiweewa7g@gmail.com\",\"retry_after\":600}','2025-11-20 15:25:31'),(39,'rate_limit_exceeded','medium','::1',2,'admin','Voter login rate limit exceeded','{\"action\":\"login\",\"username\":\"kiweewa7g@gmail.com\",\"retry_after\":600}','2025-11-20 15:35:30'),(40,'rate_limit_exceeded','medium','::1',2,'admin','Voter login rate limit exceeded','{\"action\":\"login\",\"username\":\"kiweewa7g@gmail.com\",\"retry_after\":600}','2025-11-20 15:35:33'),(41,'successful_login','low','::1',2,'admin','Successful login for voter: kiweewa7g@gmail.com','{\"username\":\"kiweewa7g@gmail.com\",\"user_type\":\"voter\"}','2025-11-20 15:37:27'),(42,'successful_login','low','::1',2,'voter','Successful login for admin: admin','{\"username\":\"admin\",\"user_type\":\"admin\"}','2025-11-20 15:38:09'),(43,'successful_login','low','::1',2,'admin','Successful login for voter: kiweewa7g@gmail.com','{\"username\":\"kiweewa7g@gmail.com\",\"user_type\":\"voter\"}','2025-11-20 15:39:16'),(44,'failed_login','medium','::1',2,'voter','Failed login attempt for admin: admin','{\"username\":\"admin\",\"user_type\":\"admin\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/142.0.0.0 Safari\\/537.36\"}','2025-11-20 15:39:31'),(45,'failed_login','medium','::1',2,'voter','Failed login attempt for admin: admin','{\"username\":\"admin\",\"user_type\":\"admin\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/142.0.0.0 Safari\\/537.36\"}','2025-11-20 15:39:39'),(46,'successful_login','low','::1',2,'voter','Successful login for admin: admin','{\"username\":\"admin\",\"user_type\":\"admin\"}','2025-11-20 15:39:46'),(47,'successful_login','low','::1',2,'admin','Successful login for voter: kiweewa7g@gmail.com','{\"username\":\"kiweewa7g@gmail.com\",\"user_type\":\"voter\"}','2025-11-20 15:40:06'),(48,'successful_login','low','::1',2,'voter','Successful login for admin: admin','{\"username\":\"admin\",\"user_type\":\"admin\"}','2025-11-20 15:46:39'),(49,'successful_login','low','::1',2,'admin','Successful login for voter: kiweewa7g@gmail.com','{\"username\":\"kiweewa7g@gmail.com\",\"user_type\":\"voter\"}','2025-11-20 15:53:18'),(50,'failed_login','medium','::1',NULL,NULL,'Failed login attempt for voter: VTR011','{\"username\":\"VTR011\",\"user_type\":\"voter\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/142.0.0.0 Safari\\/537.36\"}','2025-11-20 16:04:03'),(51,'failed_login','medium','::1',NULL,NULL,'Failed login attempt for voter: VTR011','{\"username\":\"VTR011\",\"user_type\":\"voter\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/142.0.0.0 Safari\\/537.36\"}','2025-11-20 16:04:21'),(52,'successful_login','low','::1',NULL,NULL,'Successful login for admin: admin','{\"username\":\"admin\",\"user_type\":\"admin\"}','2025-11-20 16:09:15'),(53,'failed_login','medium','::1',2,'admin','Failed login attempt for voter: jken34331@gmail.com','{\"username\":\"jken34331@gmail.com\",\"user_type\":\"voter\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/142.0.0.0 Safari\\/537.36\"}','2025-11-20 16:15:38'),(54,'successful_login','low','::1',2,'admin','Successful login for voter: jken@gmail.com','{\"username\":\"jken@gmail.com\",\"user_type\":\"voter\"}','2025-11-20 16:16:03'),(55,'successful_login','low','::1',2,'voter','Successful login for admin: admin','{\"username\":\"admin\",\"user_type\":\"admin\"}','2025-11-20 16:31:29'),(56,'csrf_validation_failed','high','::1',2,'admin','CSRF token validation failed for election management','{\"action\":\"manage_elections\"}','2025-11-20 16:42:10'),(57,'successful_login','low','::1',NULL,NULL,'Successful login for admin: admin','{\"username\":\"admin\",\"user_type\":\"admin\"}','2025-11-20 17:19:27'),(58,'failed_login','medium','::1',NULL,NULL,'Failed login attempt for admin: jken@gmail.com','{\"username\":\"jken@gmail.com\",\"user_type\":\"admin\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/142.0.0.0 Safari\\/537.36\"}','2025-11-20 17:22:02'),(59,'failed_login','medium','::1',NULL,NULL,'Failed login attempt for admin: jken@gmail.com','{\"username\":\"jken@gmail.com\",\"user_type\":\"admin\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/142.0.0.0 Safari\\/537.36\"}','2025-11-20 17:22:10'),(60,'failed_login','medium','::1',NULL,NULL,'Failed login attempt for voter: jk','{\"username\":\"jk\",\"user_type\":\"voter\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/142.0.0.0 Safari\\/537.36\"}','2025-11-20 17:22:39'),(61,'successful_login','low','::1',NULL,NULL,'Successful login for voter: jken@gmail.com','{\"username\":\"jken@gmail.com\",\"user_type\":\"voter\"}','2025-11-20 17:22:52'),(62,'successful_login','low','::1',7,'voter','Successful login for admin: admin','{\"username\":\"admin\",\"user_type\":\"admin\"}','2025-11-20 19:09:22'),(63,'failed_login','medium','::1',NULL,NULL,'Failed login attempt for admin: admin','{\"username\":\"admin\",\"user_type\":\"admin\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/142.0.0.0 Safari\\/537.36\"}','2025-11-21 05:36:51'),(64,'failed_login','medium','::1',NULL,NULL,'Failed login attempt for admin: admin','{\"username\":\"admin\",\"user_type\":\"admin\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/142.0.0.0 Safari\\/537.36\"}','2025-11-21 05:37:00'),(65,'successful_login','low','::1',NULL,NULL,'Successful login for admin: admin','{\"username\":\"admin\",\"user_type\":\"admin\"}','2025-11-21 05:37:04');
/*!40000 ALTER TABLE `security_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `security_notifications`
--

DROP TABLE IF EXISTS `security_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `security_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `alert_type` varchar(50) NOT NULL,
  `severity` enum('low','medium','high','critical') NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `recipients` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`recipients`)),
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `event_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`event_data`)),
  PRIMARY KEY (`id`),
  KEY `idx_type_severity` (`alert_type`,`severity`),
  KEY `idx_sent_at` (`sent_at`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `security_notifications`
--

LOCK TABLES `security_notifications` WRITE;
/*!40000 ALTER TABLE `security_notifications` DISABLE KEYS */;
INSERT INTO `security_notifications` VALUES (1,'security_test','low','[SmartVote Security] Security System Test','\r\n        <!DOCTYPE html>\r\n        <html>\r\n        <head>\r\n            <meta charset=\'UTF-8\'>\r\n            <title>SmartVote Security Alert</title>\r\n        </head>\r\n        <body style=\'font-family: Arial, sans-serif; line-height: 1.6; color: #333;\'>\r\n            <div style=\'max-width: 600px; margin: 0 auto; padding: 20px;\'>\r\n                <div style=\'background: linear-gradient(135deg, #10B981 0%, #0c9467 100%); color: white; padding: 20px; border-radius: 8px 8px 0 0;\'>\r\n                    <h1 style=\'margin: 0; font-size: 24px;\'>🛡️ SmartVote Security Alert</h1>\r\n                    <p style=\'margin: 5px 0 0 0; opacity: 0.9;\'>Severity: LOW</p>\r\n                </div>\r\n                \r\n                <div style=\'background: #f8f9fa; padding: 20px; border: 1px solid #dee2e6;\'>\r\n                    <h2 style=\'color: #10B981; margin-top: 0;\'>Security System Test</h2>\r\n                    <div style=\'background: white; padding: 15px; border-radius: 6px; border-left: 4px solid #10B981;\'>\r\n                        This is a test of the security notification system.\r\n                    </div>\r\n                </div>\r\n                \r\n                <div style=\'background: #fff; padding: 20px; border: 1px solid #dee2e6; border-top: 0;\'>\r\n                    <h3>Event Details:</h3>\r\n                    <table style=\'width: 100%; border-collapse: collapse;\'>\r\n                        <tr>\r\n                            <td style=\'padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;\'>Alert Type:</td>\r\n                            <td style=\'padding: 8px; border-bottom: 1px solid #eee;\'>security_test</td>\r\n                        </tr>\r\n                        <tr>\r\n                            <td style=\'padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;\'>Time:</td>\r\n                            <td style=\'padding: 8px; border-bottom: 1px solid #eee;\'>2025-10-28 17:08:46</td>\r\n                        </tr>\r\n                        <tr>\r\n                            <td style=\'padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;\'>System:</td>\r\n                            <td style=\'padding: 8px; border-bottom: 1px solid #eee;\'>SmartVote Digital Voting Platform</td>\r\n                        </tr>\r\n                        <tr>\r\n                            <td style=\'padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;\'>Test:</td>\r\n                            <td style=\'padding: 8px; border-bottom: 1px solid #eee;\'>1</td>\r\n                        </tr>\r\n                    </table>\r\n                </div>\r\n                \r\n                <div style=\'background: #f8f9fa; padding: 15px; border-radius: 0 0 8px 8px; text-align: center; font-size: 12px; color: #6c757d;\'>\r\n                    <p>This is an automated security alert from SmartVote system.</p>\r\n                    <p>Please review the Security Dashboard for more details.</p>\r\n                </div>\r\n            </div>\r\n        </body>\r\n        </html>','[{\"email\":\"admin@smartvote.system\",\"name\":\"System Administrator\"}]','2025-10-28 16:08:48','{\"test\":true}');
/*!40000 ALTER TABLE `security_notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_logs`
--

DROP TABLE IF EXISTS `system_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `user_type` enum('admin','voter') DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_user_type` (`user_type`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_logs`
--

LOCK TABLES `system_logs` WRITE;
/*!40000 ALTER TABLE `system_logs` DISABLE KEYS */;
INSERT INTO `system_logs` VALUES (1,0,'voter','voter_logout','Voter logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-28 14:03:57'),(2,0,'voter','voter_logout','Voter logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-28 15:25:08'),(3,0,'voter','voter_logout','Voter logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-28 15:33:19'),(4,1,'admin','admin_logout','Admin logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36','2025-10-29 08:46:42'),(5,2,'voter','voter_logout','Voter logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36','2025-11-20 16:03:48');
/*!40000 ALTER TABLE `system_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_settings`
--

DROP TABLE IF EXISTS `system_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_settings`
--

LOCK TABLES `system_settings` WRITE;
/*!40000 ALTER TABLE `system_settings` DISABLE KEYS */;
INSERT INTO `system_settings` VALUES (1,'site_name','SmartVote System','2025-10-28 09:45:30','2025-10-28 09:45:30'),(2,'site_description','School Online Voting Platform','2025-10-28 09:45:30','2025-10-28 09:45:30'),(3,'admin_email','','2025-10-28 09:45:30','2025-11-21 05:39:20'),(4,'timezone','America/New_York','2025-10-28 09:45:30','2025-11-21 05:39:20'),(5,'date_format','Y-m-d','2025-10-28 09:45:30','2025-10-28 09:45:30'),(6,'time_format','H:i','2025-10-28 09:45:30','2025-10-28 09:45:30'),(7,'allow_voter_registration','1','2025-10-28 09:45:30','2025-10-28 09:45:30'),(8,'require_voter_approval','0','2025-10-28 09:45:30','2025-10-28 09:45:30'),(9,'max_votes_per_position','1','2025-10-28 09:45:30','2025-10-28 09:45:30'),(10,'voting_start_time','','2025-10-28 09:45:30','2025-10-28 09:45:30'),(11,'voting_end_time','','2025-10-28 09:45:30','2025-10-28 09:45:30'),(12,'show_results_immediately','0','2025-10-28 09:45:30','2025-10-28 09:45:30'),(13,'session_timeout','30','2025-10-28 09:45:30','2025-10-28 09:45:30'),(14,'max_login_attempts','5','2025-10-28 09:45:30','2025-10-28 09:45:30'),(15,'lockout_duration','15','2025-10-28 09:45:30','2025-10-28 09:45:30'),(16,'require_strong_passwords','0','2025-10-28 09:45:30','2025-10-28 09:45:30'),(17,'enable_2fa','0','2025-10-28 09:45:30','2025-10-28 09:45:30'),(18,'log_activity','1','2025-10-28 09:45:30','2025-10-28 09:45:30'),(19,'smtp_host','','2025-10-28 09:45:30','2025-10-28 09:45:30'),(20,'smtp_port','587','2025-10-28 09:45:30','2025-10-28 09:45:30'),(21,'smtp_username','','2025-10-28 09:45:30','2025-10-28 09:45:30'),(22,'smtp_password','','2025-10-28 09:45:30','2025-10-28 09:45:30'),(23,'smtp_encryption','tls','2025-10-28 09:45:30','2025-10-28 09:45:30'),(24,'from_email','','2025-10-28 09:45:30','2025-10-28 09:45:30'),(25,'from_name','SmartVote System','2025-10-28 09:45:30','2025-10-28 09:45:30'),(26,'system_name','SmartVote','2025-11-21 05:39:20','2025-11-21 05:39:20'),(27,'system_description','','2025-11-21 05:39:20','2025-11-21 05:39:20'),(30,'max_votes_per_election','1','2025-11-21 05:39:20','2025-11-21 05:39:20');
/*!40000 ALTER TABLE `system_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `two_factor_settings`
--

DROP TABLE IF EXISTS `two_factor_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `two_factor_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `user_type` enum('admin','voter') NOT NULL,
  `method` enum('email','sms','totp','backup') NOT NULL,
  `identifier` varchar(255) NOT NULL,
  `backup_codes` text DEFAULT NULL,
  `enabled` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_used` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_method` (`user_id`,`user_type`,`method`),
  KEY `idx_user` (`user_id`,`user_type`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `two_factor_settings`
--

LOCK TABLES `two_factor_settings` WRITE;
/*!40000 ALTER TABLE `two_factor_settings` DISABLE KEYS */;
INSERT INTO `two_factor_settings` VALUES (1,1,'admin','email','test@smartvote.com','[\"F5515721\",\"60691180\",\"3B8D8107\",\"57D161B8\",\"00F40289\",\"ECD48304\",\"FEB37C79\",\"99FB78C8\"]',1,'2025-10-28 16:08:46',NULL);
/*!40000 ALTER TABLE `two_factor_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_sessions`
--

DROP TABLE IF EXISTS `user_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_sessions` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
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
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_id` (`session_id`),
  KEY `idx_user_active` (`user_id`,`user_type`,`is_active`),
  KEY `idx_session_active` (`session_id`,`is_active`),
  KEY `idx_login_time` (`login_time`),
  KEY `idx_ip` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_sessions`
--

LOCK TABLES `user_sessions` WRITE;
/*!40000 ALTER TABLE `user_sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `voters`
--

DROP TABLE IF EXISTS `voters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `voters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `voter_id` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `has_voted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `voter_id` (`voter_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `voters`
--

LOCK TABLES `voters` WRITE;
/*!40000 ALTER TABLE `voters` DISABLE KEYS */;
INSERT INTO `voters` VALUES (1,'VTR001','Sokiri Henry','sokirihenry211@gmail.com','0783366484','Busabala','$2y$10$axiOOrZ9cxzhW3xjoE0iWeqFwDQr64SAf5E3TzzOZN7fBUULiht22',1,'2025-10-28 10:03:22'),(2,'VTR002','Kiweewa Godfrey','kiweewa7g@gmail.com','0779172965','Kampala RD\r\nWakiso','$2y$10$rrCCUmqzk0Q975UAP1YVguP4HMAkFJDYss7aI3iUHOjb5Z9Q8FtKK',1,'2025-10-28 10:19:24'),(3,'VT003','Galandi Vincent','hodtechnology@kti.ac.ug','0702087600','Busabala','$2y$10$7LeIdQ3.G9BpS45r11EwMeRbo58QVTWXFmB9qzUBSBYWVxuU4iWgm',1,'2025-10-28 13:58:11'),(4,'VTR011','nemeye deborah w','kiweewa7g@gmail.com','0779172965','Kampala RD\r\nWakiso','$2y$10$WUO1dlSQsLDaMaVjpHk0z.KS.2IsRY3ORmh.yMVHG5ulDJ/80ergC',0,'2025-11-20 15:05:33'),(7,'VT004','jale albert','jken@gmail.com','0779172965','Kampala RD\r\nWakiso','$2y$10$u76XlRGM92NECcbFKIPCC.FmIbe1dtYPYlKsQ5hyxNoaX./lXV0pa',0,'2025-11-20 17:19:51');
/*!40000 ALTER TABLE `voters` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `votes`
--

DROP TABLE IF EXISTS `votes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `votes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `election_id` int(11) DEFAULT NULL,
  `position_id` int(11) DEFAULT NULL,
  `voter_id` varchar(50) NOT NULL,
  `voter_id_int` int(11) DEFAULT NULL,
  `candidate_id` int(11) NOT NULL,
  `position` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `voter_id` (`voter_id`),
  KEY `candidate_id` (`candidate_id`),
  KEY `idx_election_id` (`election_id`),
  KEY `idx_position_id` (`position_id`),
  KEY `idx_voter_id_int` (`voter_id_int`),
  CONSTRAINT `fk_votes_candidate` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `votes`
--

LOCK TABLES `votes` WRITE;
/*!40000 ALTER TABLE `votes` DISABLE KEYS */;
INSERT INTO `votes` VALUES (1,1,1,'VT003',NULL,1,'President','2025-10-28 14:03:10'),(2,1,1,'VTR001',NULL,1,'President','2025-10-28 15:32:53'),(3,2,NULL,'VTR002',NULL,3,'','2025-11-20 16:03:11'),(4,2,1,'VTR002',NULL,1,'President','2025-11-20 16:03:11'),(5,2,NULL,'VT004',NULL,3,'','2025-11-20 16:31:10'),(6,2,1,'VT004',NULL,1,'President','2025-11-20 16:31:10');
/*!40000 ALTER TABLE `votes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'smartvote'
--

--
-- Final view structure for view `active_candidates`
--

/*!50001 DROP VIEW IF EXISTS `active_candidates`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `active_candidates` AS select `candidates`.`id` AS `id`,`candidates`.`name` AS `name`,`candidates`.`position` AS `position`,`candidates`.`description` AS `description`,`candidates`.`photo` AS `photo`,`candidates`.`active` AS `active`,`candidates`.`created_at` AS `created_at` from `candidates` where `candidates`.`active` = 1 */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `election_status_view`
--

/*!50001 DROP VIEW IF EXISTS `election_status_view`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `election_status_view` AS select `e`.`id` AS `id`,`e`.`title` AS `title`,`e`.`description` AS `description`,`e`.`start_date` AS `start_date`,`e`.`end_date` AS `end_date`,`e`.`status` AS `status`,`e`.`created_at` AS `created_at`,case when current_timestamp() > `e`.`end_date` then 'expired' when current_timestamp() < `e`.`start_date` then 'pending' when `e`.`status` = 'active' and current_timestamp() >= `e`.`start_date` and current_timestamp() <= `e`.`end_date` then 'active' else 'closed' end AS `calculated_status` from `elections` `e` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-11-21  8:46:50
