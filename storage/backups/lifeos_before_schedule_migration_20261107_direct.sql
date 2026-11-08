-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)

-- Host: 127.0.0.1    Database: lifeos



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





DROP TABLE IF EXISTS `accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `accounts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `type` enum('cash','bank','ewallet','savings','other') NOT NULL DEFAULT 'bank',
  `account_number` varchar(50) DEFAULT NULL,
  `initial_balance` decimal(19,2) NOT NULL DEFAULT 0.00,
  `color` varchar(20) DEFAULT '#344f41',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_accounts_active` (`is_active`),
  KEY `idx_accounts_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;





LOCK TABLES `accounts` WRITE;
/*!40000 ALTER TABLE `accounts` DISABLE KEYS */;
INSERT INTO `accounts` VALUES (1,'BRI','bank','300601003496502',1577000.00,'#0062ff',1,'Main Bank Account','2026-11-04 14:54:17','2026-11-04 14:54:17'),(2,'GOPAY','ewallet','085652108170',0.00,'#1bd072',1,NULL,'2026-11-04 14:54:52','2026-11-04 14:54:52'),(3,'SPAY','ewallet','085652108170',60000.00,'#344f41',1,NULL,'2026-11-04 14:55:08','2026-11-04 14:55:08'),(4,'Cash Dompet','cash',NULL,354000.00,'#e3e3e3',1,NULL,'2026-11-04 14:57:28','2026-11-04 14:57:28');
/*!40000 ALTER TABLE `accounts` ENABLE KEYS */;
UNLOCK TABLES;





DROP TABLE IF EXISTS `app_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `app_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `profile_id` bigint(20) unsigned NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_app_settings_profile_key` (`profile_id`,`setting_key`),
  CONSTRAINT `fk_app_settings_profile` FOREIGN KEY (`profile_id`) REFERENCES `profiles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;





LOCK TABLES `app_settings` WRITE;
/*!40000 ALTER TABLE `app_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `app_settings` ENABLE KEYS */;
UNLOCK TABLES;





DROP TABLE IF EXISTS `budget_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `budget_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `budget_id` bigint(20) unsigned NOT NULL,
  `category_id` bigint(20) unsigned NOT NULL,
  `allocated_amount` decimal(19,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_budget_category` (`budget_id`,`category_id`),
  KEY `idx_budget_items_category` (`category_id`),
  CONSTRAINT `fk_budget_items_budget` FOREIGN KEY (`budget_id`) REFERENCES `budgets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_budget_items_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;





LOCK TABLES `budget_items` WRITE;
/*!40000 ALTER TABLE `budget_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `budget_items` ENABLE KEYS */;
UNLOCK TABLES;





DROP TABLE IF EXISTS `budgets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `budgets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `period` enum('weekly','monthly','quarterly','yearly','custom') NOT NULL DEFAULT 'monthly',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `total_limit` decimal(19,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_budgets_period` (`period`),
  KEY `idx_budgets_dates` (`start_date`,`end_date`),
  KEY `idx_budgets_deleted` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;





LOCK TABLES `budgets` WRITE;
/*!40000 ALTER TABLE `budgets` DISABLE KEYS */;
/*!40000 ALTER TABLE `budgets` ENABLE KEYS */;
UNLOCK TABLES;





DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `type` enum('income','expense') NOT NULL,
  `icon` varchar(50) DEFAULT '◈',
  `color` varchar(20) DEFAULT '#526f5d',
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_name_type` (`name`,`type`),
  KEY `idx_categories_type` (`type`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;





LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (1,'Gaji & Pendapatan','income','↗','#2f6846',1,'2026-11-04 14:52:44','2026-11-04 14:52:44'),(2,'Penjualan & Bisnis','income','◫','#396b52',1,'2026-11-04 14:52:44','2026-11-04 14:52:44'),(3,'Investasi & Dividen','income','◈','#3b705e',1,'2026-11-04 14:52:44','2026-11-04 14:52:44'),(4,'Hadiah & Bonus','income','◇','#4a7c68',1,'2026-11-04 14:52:44','2026-11-04 14:52:44'),(5,'Pendapatan Lain','income','＋','#53826e',1,'2026-11-04 14:52:44','2026-11-04 14:52:44'),(6,'Makanan & Minuman','expense','☕','#8c594d',1,'2026-11-04 14:52:44','2026-11-04 14:52:44'),(7,'Transportasi','expense','→','#7a6352',1,'2026-11-04 14:52:44','2026-11-04 14:52:44'),(8,'Tempat Tinggal & Utilitas','expense','⌂','#636b75',1,'2026-11-04 14:52:44','2026-11-04 14:52:44'),(9,'Kebutuhan Rumah Tangga','expense','▦','#6b705c',1,'2026-11-04 14:52:44','2026-11-04 14:52:44'),(10,'Tagihan & Langganan','expense','▤','#7e5a6f',1,'2026-11-04 14:52:44','2026-11-04 14:52:44'),(11,'Kesehatan & Medis','expense','✚','#8f4f4f',1,'2026-11-04 14:52:44','2026-11-04 14:52:44'),(12,'Pendidikan & Pengembangan','expense','✎','#546b82',1,'2026-11-04 14:52:44','2026-11-04 14:52:44'),(13,'Hiburan & Hobi','expense','♬','#825f78',1,'2026-11-04 14:52:44','2026-11-04 14:52:44'),(14,'Beban Bunga Pinjaman','expense','％','#a35050',1,'2026-11-04 14:52:44','2026-11-04 14:52:44'),(15,'Pengeluaran Lain','expense','↙','#787878',1,'2026-11-04 14:52:44','2026-11-04 14:52:44');
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;





DROP TABLE IF EXISTS `fixed_bill_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fixed_bill_templates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `category_id` bigint(20) unsigned DEFAULT NULL,
  `account_id` bigint(20) unsigned DEFAULT NULL,
  `expected_amount` decimal(19,2) NOT NULL DEFAULT 0.00,
  `frequency` enum('weekly','monthly','quarterly','yearly','once') NOT NULL DEFAULT 'monthly',
  `due_day` tinyint(3) unsigned DEFAULT NULL,
  `next_due_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_bills_active` (`is_active`),
  KEY `idx_bills_due` (`next_due_date`),
  KEY `fk_bills_category` (`category_id`),
  KEY `fk_bills_account` (`account_id`),
  CONSTRAINT `fk_bills_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_bills_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;





LOCK TABLES `fixed_bill_templates` WRITE;
/*!40000 ALTER TABLE `fixed_bill_templates` DISABLE KEYS */;
INSERT INTO `fixed_bill_templates` VALUES (1,'Bayar Kos',8,1,700000.00,'monthly',NULL,'2026-12-10',NULL,1,'2026-11-04 14:58:42','2026-11-04 14:58:42',NULL);
/*!40000 ALTER TABLE `fixed_bill_templates` ENABLE KEYS */;
UNLOCK TABLES;





DROP TABLE IF EXISTS `loan_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `loan_payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `loan_id` bigint(20) unsigned NOT NULL,
  `account_id` bigint(20) unsigned NOT NULL,
  `principal_paid` decimal(19,2) NOT NULL DEFAULT 0.00,
  `interest_paid` decimal(19,2) NOT NULL DEFAULT 0.00,
  `payment_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `principal_transaction_id` bigint(20) unsigned DEFAULT NULL,
  `interest_transaction_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_payments_loan` (`loan_id`),
  KEY `idx_payments_account` (`account_id`),
  KEY `idx_payments_principal_tx` (`principal_transaction_id`),
  KEY `idx_payments_interest_tx` (`interest_transaction_id`),
  CONSTRAINT `fk_payments_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_payments_interest_tx` FOREIGN KEY (`interest_transaction_id`) REFERENCES `transactions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_payments_loan` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payments_principal_tx` FOREIGN KEY (`principal_transaction_id`) REFERENCES `transactions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;





LOCK TABLES `loan_payments` WRITE;
/*!40000 ALTER TABLE `loan_payments` DISABLE KEYS */;
/*!40000 ALTER TABLE `loan_payments` ENABLE KEYS */;
UNLOCK TABLES;





DROP TABLE IF EXISTS `loans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `loans` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `lender_name` varchar(150) NOT NULL,
  `loan_type` enum('borrowed','lent') NOT NULL DEFAULT 'borrowed',
  `principal_amount` decimal(19,2) NOT NULL DEFAULT 0.00,
  `interest_amount` decimal(19,2) NOT NULL DEFAULT 0.00,
  `borrowed_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `status` enum('unpaid','partially_paid','paid') NOT NULL DEFAULT 'unpaid',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_loans_status` (`status`),
  KEY `idx_loans_deleted` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;





LOCK TABLES `loans` WRITE;
/*!40000 ALTER TABLE `loans` DISABLE KEYS */;
/*!40000 ALTER TABLE `loans` ENABLE KEYS */;
UNLOCK TABLES;





DROP TABLE IF EXISTS `profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `profiles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `name` varchar(100) NOT NULL,
  `timezone` varchar(64) NOT NULL DEFAULT 'Asia/Jakarta',
  `currency` char(3) NOT NULL DEFAULT 'IDR',
  `is_default` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_profiles_uuid` (`uuid`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;





LOCK TABLES `profiles` WRITE;
/*!40000 ALTER TABLE `profiles` DISABLE KEYS */;
INSERT INTO `profiles` VALUES (1,'66151546-d85f-11f1-a70a-088fc37341a0','Personal','Asia/Jakarta','IDR',1,'2026-11-04 21:52:44','2026-11-04 21:52:44');
/*!40000 ALTER TABLE `profiles` ENABLE KEYS */;
UNLOCK TABLES;





DROP TABLE IF EXISTS `schedule_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `schedule_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `color` varchar(20) DEFAULT '#5a5a5a',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_schedule_category_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;





LOCK TABLES `schedule_categories` WRITE;
/*!40000 ALTER TABLE `schedule_categories` DISABLE KEYS */;
INSERT INTO `schedule_categories` VALUES (1,'Personal','#5a5a5a','2026-11-04 14:52:44','2026-11-04 14:52:44'),(2,'Work','#315c4c','2026-11-04 14:52:44','2026-11-04 14:52:44'),(3,'Reminder','#8a6d3b','2026-11-04 14:52:44','2026-11-04 14:52:44');
/*!40000 ALTER TABLE `schedule_categories` ENABLE KEYS */;
UNLOCK TABLES;





DROP TABLE IF EXISTS `schedule_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `schedule_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `start_date` date NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_date` date NOT NULL,
  `end_time` time DEFAULT NULL,
  `all_day` tinyint(1) NOT NULL DEFAULT 0,
  `category_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_events_start` (`start_date`,`start_time`),
  KEY `idx_events_end` (`end_date`,`end_time`),
  KEY `idx_events_deleted` (`deleted_at`),
  KEY `fk_schedule_category` (`category_id`),
  CONSTRAINT `fk_schedule_category` FOREIGN KEY (`category_id`) REFERENCES `schedule_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;





LOCK TABLES `schedule_events` WRITE;
/*!40000 ALTER TABLE `schedule_events` DISABLE KEYS */;
/*!40000 ALTER TABLE `schedule_events` ENABLE KEYS */;
UNLOCK TABLES;





DROP TABLE IF EXISTS `task_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `task_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `color` varchar(20) DEFAULT '#5a5a5a',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_task_category_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;





LOCK TABLES `task_categories` WRITE;
/*!40000 ALTER TABLE `task_categories` DISABLE KEYS */;
INSERT INTO `task_categories` VALUES (1,'Uncategorized','#777777','2026-11-04 14:52:44','2026-11-04 14:52:44'),(2,'Work','#315c4c','2026-11-04 14:52:44','2026-11-04 14:52:44'),(3,'Personal','#5a5a5a','2026-11-04 14:52:44','2026-11-04 14:52:44');
/*!40000 ALTER TABLE `task_categories` ENABLE KEYS */;
UNLOCK TABLES;





DROP TABLE IF EXISTS `tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tasks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('todo','in_progress','done','cancelled') NOT NULL DEFAULT 'todo',
  `priority` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `due_date` date DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `category_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tasks_due` (`due_date`),
  KEY `idx_tasks_status` (`status`),
  KEY `idx_tasks_deleted` (`deleted_at`),
  KEY `fk_task_category` (`category_id`),
  CONSTRAINT `fk_task_category` FOREIGN KEY (`category_id`) REFERENCES `task_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;





LOCK TABLES `tasks` WRITE;
/*!40000 ALTER TABLE `tasks` DISABLE KEYS */;
/*!40000 ALTER TABLE `tasks` ENABLE KEYS */;
UNLOCK TABLES;





DROP TABLE IF EXISTS `transaction_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transaction_lines` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `transaction_id` bigint(20) unsigned NOT NULL,
  `account_id` bigint(20) unsigned NOT NULL,
  `direction` enum('in','out') NOT NULL,
  `amount` decimal(19,2) NOT NULL,
  `line_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_lines_tx` (`transaction_id`),
  KEY `idx_lines_account_dir` (`account_id`,`direction`),
  KEY `idx_lines_date` (`line_date`),
  CONSTRAINT `fk_lines_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_lines_transaction` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;





LOCK TABLES `transaction_lines` WRITE;
/*!40000 ALTER TABLE `transaction_lines` DISABLE KEYS */;
INSERT INTO `transaction_lines` VALUES (1,1,1,'in',150000.00,'2026-11-06','2026-11-07 05:48:55'),(5,5,1,'out',75000.00,'2026-11-06','2026-11-07 05:50:47'),(6,2,1,'out',100000.00,'2026-11-06','2026-11-07 05:50:55'),(7,3,4,'out',60000.00,'2026-11-06','2026-11-07 05:51:01'),(8,4,1,'out',40000.00,'2026-11-06','2026-11-07 05:51:04'),(9,6,4,'out',55000.00,'2026-11-05','2026-11-07 05:54:10');
/*!40000 ALTER TABLE `transaction_lines` ENABLE KEYS */;
UNLOCK TABLES;





DROP TABLE IF EXISTS `transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `type` enum('income','expense','transfer') NOT NULL,
  `status` enum('posted','draft','void') NOT NULL DEFAULT 'posted',
  `description` varchar(255) NOT NULL,
  `amount` decimal(19,2) NOT NULL,
  `transaction_date` date NOT NULL,
  `account_id` bigint(20) unsigned NOT NULL,
  `target_account_id` bigint(20) unsigned DEFAULT NULL,
  `category_id` bigint(20) unsigned DEFAULT NULL,
  `is_loan_principal` tinyint(1) NOT NULL DEFAULT 0,
  `reference_number` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_transactions_type` (`type`),
  KEY `idx_transactions_status` (`status`),
  KEY `idx_transactions_date` (`transaction_date`),
  KEY `idx_transactions_account` (`account_id`),
  KEY `idx_transactions_target_account` (`target_account_id`),
  KEY `idx_transactions_category` (`category_id`),
  KEY `idx_transactions_deleted` (`deleted_at`),
  CONSTRAINT `fk_transactions_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_transactions_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_transactions_target_account` FOREIGN KEY (`target_account_id`) REFERENCES `accounts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;





LOCK TABLES `transactions` WRITE;
/*!40000 ALTER TABLE `transactions` DISABLE KEYS */;
INSERT INTO `transactions` VALUES (1,'income','posted','Dari Emak',150000.00,'2026-11-06',1,NULL,4,0,NULL,'Dari Emak','2026-11-07 05:48:55','2026-11-07 05:48:55',NULL),(2,'expense','posted','Hura hura',100000.00,'2026-11-06',1,NULL,NULL,0,NULL,NULL,'2026-11-07 05:49:20','2026-11-07 05:50:55',NULL),(3,'expense','posted','Makan dan Jajan',60000.00,'2026-11-06',4,NULL,NULL,0,NULL,NULL,'2026-11-07 05:50:12','2026-11-07 05:51:01',NULL),(4,'expense','posted','Jajan CNB',40000.00,'2026-11-06',1,NULL,NULL,0,NULL,NULL,'2026-11-07 05:50:29','2026-11-07 05:51:04',NULL),(5,'expense','posted','TF Bebeb',75000.00,'2026-11-06',1,NULL,NULL,0,NULL,NULL,'2026-11-07 05:50:47','2026-11-07 05:50:47',NULL),(6,'expense','posted','Makan',55000.00,'2026-11-05',4,NULL,NULL,0,NULL,NULL,'2026-11-07 05:54:10','2026-11-07 05:54:10',NULL);
/*!40000 ALTER TABLE `transactions` ENABLE KEYS */;
UNLOCK TABLES;





DROP TABLE IF EXISTS `transfers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transfers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `transaction_id` bigint(20) unsigned NOT NULL,
  `from_account_id` bigint(20) unsigned NOT NULL,
  `to_account_id` bigint(20) unsigned NOT NULL,
  `amount` decimal(19,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_transfers_transaction` (`transaction_id`),
  KEY `idx_transfers_from` (`from_account_id`),
  KEY `idx_transfers_to` (`to_account_id`),
  CONSTRAINT `fk_transfers_from` FOREIGN KEY (`from_account_id`) REFERENCES `accounts` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_transfers_to` FOREIGN KEY (`to_account_id`) REFERENCES `accounts` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_transfers_tx` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;





LOCK TABLES `transfers` WRITE;
/*!40000 ALTER TABLE `transfers` DISABLE KEYS */;
/*!40000 ALTER TABLE `transfers` ENABLE KEYS */;
UNLOCK TABLES;








/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
