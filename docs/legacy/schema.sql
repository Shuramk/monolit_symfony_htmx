/*M!999999\- enable the sandbox mode */ 

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
DROP TABLE IF EXISTS `sklad_MExpenses_BSU`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_MExpenses_BSU` (
  `mebID` int(11) NOT NULL AUTO_INCREMENT,
  `mebQuantity` varchar(500) DEFAULT NULL,
  `mebDate` int(11) DEFAULT NULL,
  PRIMARY KEY (`mebID`),
  KEY `mebDate` (`mebDate`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_arrears_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_arrears_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customerID` int(11) NOT NULL,
  `date` datetime NOT NULL DEFAULT current_timestamp(),
  `contactDate` datetime DEFAULT NULL,
  `contactComment` text DEFAULT NULL,
  `userID` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=969 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_articles_expenditure`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_articles_expenditure` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `parent` int(11) NOT NULL DEFAULT 0,
  `changeable` int(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=62 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_b_chart_of_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_b_chart_of_accounts` (
  `chartAccID` int(11) NOT NULL,
  `parent` int(11) NOT NULL DEFAULT 0,
  `description` varchar(255) DEFAULT NULL,
  `used` int(11) DEFAULT NULL,
  PRIMARY KEY (`chartAccID`),
  KEY `parent` (`parent`),
  KEY `used` (`used`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_ca_contracts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_ca_contracts` (
  `cacID` int(11) NOT NULL AUTO_INCREMENT,
  `cacDate` datetime NOT NULL DEFAULT current_timestamp(),
  `caID` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `markup` int(1) DEFAULT NULL,
  `closed` int(1) NOT NULL DEFAULT 0,
  `ourcaID` int(11) DEFAULT NULL,
  PRIMARY KEY (`cacID`),
  KEY `caID` (`caID`)
) ENGINE=InnoDB AUTO_INCREMENT=375 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_ca_contracts_materials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_ca_contracts_materials` (
  `cacID` int(11) NOT NULL,
  `materialIDs` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`cacID`)
) ENGINE=InnoDB DEFAULT CHARSET=cp1251 COLLATE=cp1251_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_ca_ctrl_points`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_ca_ctrl_points` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cacID` int(11) DEFAULT NULL,
  `date` datetime DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cacID` (`cacID`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_ca_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_ca_groups` (
  `caGroupID` int(11) NOT NULL AUTO_INCREMENT,
  `caGroupName` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `caGroupCode` int(5) unsigned zerofill NOT NULL,
  `caGroupCEdit` int(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`caGroupID`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_ca_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_ca_payments` (
  `pID` int(11) NOT NULL AUTO_INCREMENT,
  `caID` int(11) DEFAULT NULL,
  `cacID` int(11) DEFAULT NULL,
  `capDate` datetime NOT NULL DEFAULT current_timestamp(),
  `accepted_amount` decimal(10,2) DEFAULT NULL,
  `paid_amount` decimal(10,2) DEFAULT NULL,
  `userID` int(11) DEFAULT NULL,
  PRIMARY KEY (`pID`),
  KEY `caID` (`caID`),
  KEY `cacID` (`cacID`)
) ENGINE=InnoDB AUTO_INCREMENT=2542 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_ca_persons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_ca_persons` (
  `caPersonID` int(11) NOT NULL AUTO_INCREMENT,
  `caPersonPosition` varchar(255) DEFAULT NULL,
  `caPersonName` varchar(255) DEFAULT NULL,
  `caID` int(11) NOT NULL,
  PRIMARY KEY (`caPersonID`),
  KEY `caID` (`caID`)
) ENGINE=InnoDB AUTO_INCREMENT=55 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_calls_visitors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_calls_visitors` (
  `dateID` int(11) NOT NULL,
  `calls` int(11) NOT NULL DEFAULT 0,
  `new_calls` int(11) NOT NULL DEFAULT 0,
  `visitors` int(11) NOT NULL DEFAULT 0,
  `new_visitors` int(11) NOT NULL DEFAULT 0,
  `userID` int(11) DEFAULT NULL,
  PRIMARY KEY (`dateID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_car_card`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_car_card` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `carID` int(11) NOT NULL,
  `date` datetime NOT NULL DEFAULT current_timestamp(),
  `service_id` int(11) NOT NULL,
  `mileage` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_car_mileage`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_car_mileage` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `date` datetime NOT NULL DEFAULT current_timestamp(),
  `carID` int(11) NOT NULL,
  `odometer` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `carID` (`carID`)
) ENGINE=InnoDB AUTO_INCREMENT=568 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_car_mileage_endpoint`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_car_mileage_endpoint` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `date` datetime DEFAULT NULL,
  `carID` int(11) DEFAULT NULL,
  `mileage` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `carID` (`carID`)
) ENGINE=InnoDB AUTO_INCREMENT=519 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_car_monitor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_car_monitor` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `date` int(11) DEFAULT NULL COMMENT 'дата в формате yymmdd',
  `carID` int(11) DEFAULT NULL,
  `mileage` decimal(10,3) NOT NULL DEFAULT 0.000 COMMENT 'пробег (км)',
  `time` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'время в движении (минут)',
  `fuel_consumption` decimal(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7317 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_car_refueling`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_car_refueling` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `carID` int(11) NOT NULL,
  `date` datetime NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1731 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_carpark`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_carpark` (
  `carID` int(5) NOT NULL AUTO_INCREMENT,
  `carModel` varchar(255) NOT NULL,
  `stateNumber` varchar(10) NOT NULL,
  `driverID` int(10) DEFAULT NULL,
  `gpsID` int(11) DEFAULT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`carID`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_carpark_stats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_carpark_stats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `car_id` int(11) NOT NULL,
  `date` datetime DEFAULT NULL,
  `type` enum('maintenance','wheelchange') NOT NULL,
  `comment` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_carriers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_carriers` (
  `carrierID` int(10) NOT NULL AUTO_INCREMENT,
  `carrierName` varchar(255) NOT NULL,
  `carrierPhone` varchar(255) NOT NULL,
  `carrierOwner` int(1) NOT NULL DEFAULT 0,
  `priority` int(11) NOT NULL DEFAULT 200,
  `carID` int(11) DEFAULT NULL,
  PRIMARY KEY (`carrierID`)
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=cp1251 COLLATE=cp1251_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_cashbox`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_cashbox` (
  `cashboxID` int(11) NOT NULL AUTO_INCREMENT,
  `cashboxAmount` decimal(10,2) NOT NULL,
  `cashboxDescription` varchar(255) NOT NULL,
  `userIDs` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`cashboxID`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_chat`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_chat` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chatID` int(11) NOT NULL,
  `message` text DEFAULT NULL,
  `date` datetime NOT NULL DEFAULT current_timestamp(),
  `userID` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `chatID` (`chatID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_contacts` (
  `contactID` int(11) NOT NULL AUTO_INCREMENT,
  `t_contactID` int(11) DEFAULT NULL,
  `caID` int(11) DEFAULT NULL,
  `caPersonID` int(11) DEFAULT NULL,
  `contact` text DEFAULT NULL,
  PRIMARY KEY (`contactID`),
  KEY `caPersonID` (`caPersonID`)
) ENGINE=InnoDB AUTO_INCREMENT=247 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_counter_agents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_counter_agents` (
  `caID` int(11) NOT NULL AUTO_INCREMENT,
  `caName` text DEFAULT NULL,
  `caFullName` text DEFAULT NULL,
  `caCode` int(5) unsigned zerofill DEFAULT NULL,
  `caPerson` varchar(255) DEFAULT NULL,
  `caGroupID` int(1) DEFAULT NULL,
  `caINN` bigint(20) DEFAULT NULL,
  `caNumberCertificate` int(11) DEFAULT NULL,
  `caEDRPOU` bigint(20) DEFAULT NULL,
  PRIMARY KEY (`caID`),
  KEY `caCode` (`caCode`),
  KEY `caGroup` (`caGroupID`),
  KEY `caPerson` (`caPerson`)
) ENGINE=InnoDB AUTO_INCREMENT=308 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_crm_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_crm_tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `userID` int(11) DEFAULT NULL,
  `date` datetime NOT NULL DEFAULT current_timestamp(),
  `text` text DEFAULT NULL,
  `director_comment` text DEFAULT NULL,
  `executor` int(11) DEFAULT NULL,
  `co_executors` varchar(255) DEFAULT NULL,
  `observers` varchar(255) DEFAULT NULL,
  `execute_date` datetime DEFAULT NULL,
  `completion_date` datetime DEFAULT NULL,
  `cancelled` tinyint(1) DEFAULT NULL,
  `hidden` tinyint(1) DEFAULT NULL,
  `last_comment` text DEFAULT NULL,
  `files` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `userID` (`userID`),
  KEY `executor` (`executor`)
) ENGINE=InnoDB AUTO_INCREMENT=183 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_crm_tasks_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_crm_tasks_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL,
  `date` datetime NOT NULL DEFAULT current_timestamp(),
  `text` text DEFAULT NULL,
  `userID` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `task_id` (`task_id`),
  KEY `userID` (`userID`)
) ENGINE=InnoDB AUTO_INCREMENT=467 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_crm_tasks_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_crm_tasks_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `taskID` int(11) NOT NULL,
  `userID` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `taskID` (`taskID`),
  KEY `usersID` (`userID`)
) ENGINE=InnoDB AUTO_INCREMENT=141 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_currency_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_currency_types` (
  `CID` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(7) DEFAULT NULL,
  `currency_value` double DEFAULT NULL,
  `where2show` int(11) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `currency_iso_3` varchar(3) DEFAULT NULL,
  `display_template_en` varchar(20) DEFAULT NULL,
  `Name_en` varchar(30) DEFAULT NULL,
  `decimal_symbol` char(1) NOT NULL DEFAULT '',
  `decimal_places` int(10) unsigned NOT NULL DEFAULT 0,
  `thousands_delimiter` char(1) NOT NULL DEFAULT '',
  `Name_ru` varchar(30) DEFAULT NULL,
  `display_template_ru` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`CID`),
  KEY `sort_order` (`sort_order`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_customers` (
  `customerID` int(11) NOT NULL AUTO_INCREMENT,
  `customerName` varchar(255) NOT NULL,
  `customerPhone` varchar(10) NOT NULL DEFAULT 'XXXXXXXXXX',
  `customerPhone1` varchar(10) DEFAULT NULL,
  `customerPhone2` varchar(10) DEFAULT NULL,
  `registrationDate` datetime NOT NULL DEFAULT current_timestamp(),
  `person` varchar(10) DEFAULT NULL,
  `contactPerson` varchar(100) DEFAULT NULL,
  `buyer` varchar(10) DEFAULT NULL,
  `stacker` int(11) DEFAULT NULL,
  `cComment` text DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`customerID`),
  KEY `person` (`person`),
  KEY `buyer` (`buyer`)
) ENGINE=InnoDB AUTO_INCREMENT=6114 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_divisions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_divisions` (
  `xID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `xName` varchar(255) NOT NULL,
  `xConnectedID` int(10) NOT NULL,
  `xUnicKey` varchar(255) NOT NULL,
  `xParentID` int(10) unsigned NOT NULL DEFAULT 0,
  `xEnabled` tinyint(1) NOT NULL DEFAULT 0,
  `xPriority` smallint(5) unsigned NOT NULL DEFAULT 0,
  `xTemplate` varchar(100) NOT NULL,
  `xLinkDivisionUKey` varchar(30) NOT NULL,
  `xSubParentID` int(10) NOT NULL,
  `Description` varchar(255) NOT NULL,
  PRIMARY KEY (`xID`)
) ENGINE=InnoDB AUTO_INCREMENT=1000000 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `caID` int(11) NOT NULL,
  `document_type` int(11) DEFAULT NULL,
  `document_number` varchar(255) DEFAULT NULL,
  `date_sending` date DEFAULT NULL,
  `type_sending` int(11) NOT NULL,
  `executor` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `date_receipt` date DEFAULT NULL,
  `userID` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `caID` (`caID`),
  KEY `document_type` (`document_type`)
) ENGINE=InnoDB AUTO_INCREMENT=572 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_ei_cards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_ei_cards` (
  `eicID` int(11) NOT NULL AUTO_INCREMENT,
  `eiID` varchar(255) NOT NULL,
  `productID` int(11) NOT NULL,
  `Quantity` decimal(10,3) NOT NULL,
  `productPrice` decimal(10,4) NOT NULL,
  PRIMARY KEY (`eicID`)
) ENGINE=InnoDB AUTO_INCREMENT=2612 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_enterprise`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_enterprise` (
  `eID` int(11) NOT NULL AUTO_INCREMENT,
  `offNameVAT` varchar(255) DEFAULT NULL,
  `offNamePE` varchar(255) DEFAULT NULL,
  `NameEnt` varchar(255) DEFAULT NULL,
  `NameEntShort` varchar(255) DEFAULT NULL,
  `EDRPOU_VAT` varchar(255) DEFAULT NULL,
  `EDRPOU_PE` varchar(255) DEFAULT NULL,
  `paymentAccount_VAT` varchar(255) DEFAULT NULL,
  `paymentAccount_PE` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `accounting_phone` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `www` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `wText` text DEFAULT NULL,
  PRIMARY KEY (`eID`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_events` (
  `eventID` int(11) NOT NULL AUTO_INCREMENT,
  `description` text NOT NULL,
  `url` varchar(100) DEFAULT NULL,
  `groupID` tinyint(2) DEFAULT NULL,
  `approved` tinyint(1) NOT NULL DEFAULT 0,
  `date` timestamp NOT NULL DEFAULT current_timestamp(),
  `target_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `userID` int(11) NOT NULL,
  `forUserID` int(11) DEFAULT NULL,
  `done` int(1) NOT NULL DEFAULT 0,
  `doneUserID` int(11) DEFAULT NULL,
  `doneDate` datetime DEFAULT NULL,
  `doneComment` text DEFAULT NULL,
  PRIMARY KEY (`eventID`),
  KEY `userID` (`userID`),
  KEY `forUserID` (`forUserID`),
  KEY `groupID` (`groupID`)
) ENGINE=InnoDB AUTO_INCREMENT=415 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_expcarpark`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_expcarpark` (
  `ecpID` int(10) NOT NULL AUTO_INCREMENT,
  `carID` int(10) NOT NULL,
  `expClass` varchar(11) NOT NULL,
  `expType` varchar(11) NOT NULL,
  `expDate` datetime NOT NULL DEFAULT current_timestamp(),
  `expQuantity` decimal(10,2) DEFAULT NULL,
  `expCost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `expComment` varchar(255) DEFAULT NULL,
  `userID` int(10) NOT NULL,
  `typeOfPayment` int(11) NOT NULL DEFAULT 0 COMMENT '0-нал, 1-налКарта, 2-ФОП, 3-ТОВ',
  PRIMARY KEY (`ecpID`),
  KEY `expClass` (`expClass`),
  KEY `userID` (`userID`)
) ENGINE=InnoDB AUTO_INCREMENT=2465 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_expendable_invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_expendable_invoices` (
  `eiID` int(11) NOT NULL AUTO_INCREMENT,
  `caID` int(11) DEFAULT NULL,
  `eiNumber` varchar(255) DEFAULT NULL,
  `date` datetime NOT NULL DEFAULT current_timestamp(),
  `orderID` int(11) DEFAULT NULL,
  `markup` int(11) NOT NULL,
  `taskID` int(11) DEFAULT NULL,
  `mark_remove` int(1) NOT NULL DEFAULT 0,
  `mrUserID` int(11) DEFAULT NULL,
  `mrDate` datetime DEFAULT NULL,
  PRIMARY KEY (`eiID`),
  KEY `orderID` (`orderID`),
  KEY `caID` (`caID`)
) ENGINE=InnoDB AUTO_INCREMENT=1212 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_expenses` (
  `expensesID` int(11) NOT NULL AUTO_INCREMENT,
  `expensesDate` datetime NOT NULL DEFAULT current_timestamp(),
  `expensesAmount` decimal(10,2) NOT NULL,
  `expensesComment` varchar(255) DEFAULT NULL,
  `material_historyID` int(11) DEFAULT NULL,
  `expenseType` int(11) DEFAULT NULL,
  `chartAccID` int(11) DEFAULT NULL,
  `expArtID` int(11) DEFAULT NULL,
  `userID` int(11) DEFAULT NULL,
  `typeOfPayment` int(11) NOT NULL DEFAULT 0 COMMENT '0-нал, 1-налКарта, 2-ФОП, 3-ТОВ',
  PRIMARY KEY (`expensesID`),
  KEY `chartAccID` (`chartAccID`),
  KEY `userID` (`userID`),
  KEY `expArtID` (`expArtID`)
) ENGINE=InnoDB AUTO_INCREMENT=22375 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_forms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_forms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parentID` int(11) NOT NULL,
  `pressID` int(11) DEFAULT NULL,
  `date` datetime DEFAULT NULL,
  `userID` int(11) DEFAULT NULL,
  `archive` tinyint(1) DEFAULT NULL,
  `archive_date` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pressID` (`pressID`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `prod_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `date` datetime NOT NULL DEFAULT current_timestamp(),
  `action` varchar(255) DEFAULT NULL,
  `num_stor` decimal(10,2) NOT NULL,
  `storage` int(1) NOT NULL,
  `comment` varchar(255) NOT NULL,
  `taskID` int(11) DEFAULT NULL,
  `manufacturingDate` datetime DEFAULT NULL,
  `userID` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `prod_id` (`prod_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=85590 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_incomingBills`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_incomingBills` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `date` datetime NOT NULL DEFAULT current_timestamp(),
  `caID` int(11) NOT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `comment` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_instr_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_instr_category` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_instr_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_instr_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `date` datetime NOT NULL DEFAULT current_timestamp(),
  `instrumentID` int(11) DEFAULT NULL,
  `action` enum('add','remove','writeoff') NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 0.00,
  `comment` text DEFAULT NULL,
  `serials` text DEFAULT NULL,
  `userID` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2656 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_instr_inventory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_instr_inventory` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `date` datetime NOT NULL DEFAULT current_timestamp(),
  `card` text DEFAULT NULL,
  `userID` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_instr_posting`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_instr_posting` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `date` datetime DEFAULT current_timestamp(),
  `provider` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `card` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=421 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_instr_products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_instr_products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `category_id` int(11) DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `serial_accounting` tinyint(1) DEFAULT NULL,
  `min` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=833 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_instr_reception`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_instr_reception` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `wID` int(11) DEFAULT NULL,
  `card` text DEFAULT NULL,
  `date` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `wID` (`wID`)
) ENGINE=InnoDB AUTO_INCREMENT=288 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_instr_storage`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_instr_storage` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `productID` int(11) DEFAULT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 0.00,
  `serials` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `productID` (`productID`)
) ENGINE=InnoDB AUTO_INCREMENT=681 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_instr_transmission`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_instr_transmission` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `wID` int(11) DEFAULT NULL,
  `card` text DEFAULT NULL,
  `date` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `wID` (`wID`)
) ENGINE=InnoDB AUTO_INCREMENT=659 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_instr_write_off`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_instr_write_off` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `wID` int(11) DEFAULT NULL,
  `date` datetime NOT NULL DEFAULT current_timestamp(),
  `card` text DEFAULT NULL,
  `comment` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=308 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_instruments_cards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_instruments_cards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `wID` int(11) NOT NULL,
  `instrumentID` int(11) DEFAULT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 0.00,
  `serial` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `instrumentID` (`instrumentID`),
  KEY `wID` (`wID`)
) ENGINE=InnoDB AUTO_INCREMENT=936 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_invoices` (
  `invoiceID` varchar(255) NOT NULL,
  `invoiceDate` datetime NOT NULL DEFAULT current_timestamp(),
  `orderID` int(11) NOT NULL,
  `shippingCost` decimal(10,0) DEFAULT NULL,
  `shippingCount` int(11) DEFAULT NULL,
  `shippingCost1` decimal(10,0) DEFAULT NULL,
  `shippingCount1` int(11) DEFAULT NULL,
  PRIMARY KEY (`invoiceID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_invoices_card`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_invoices_card` (
  `icID` int(11) NOT NULL AUTO_INCREMENT,
  `invoiceID` varchar(255) NOT NULL,
  `productID` int(11) NOT NULL,
  `productQuantity` decimal(10,3) NOT NULL,
  `productPrice` decimal(10,4) NOT NULL DEFAULT 0.0000,
  PRIMARY KEY (`icID`),
  KEY `productID` (`productID`,`invoiceID`)
) ENGINE=InnoDB AUTO_INCREMENT=11029 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_made_deliveries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_made_deliveries` (
  `mdID` int(11) NOT NULL AUTO_INCREMENT,
  `carID` int(11) DEFAULT NULL,
  `deliveryDate` datetime NOT NULL DEFAULT current_timestamp(),
  `deliveryCost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `taskID` int(11) DEFAULT NULL,
  `orderID` int(11) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `userID` int(11) DEFAULT NULL,
  `driverID` int(11) DEFAULT NULL,
  PRIMARY KEY (`mdID`),
  KEY `carID` (`carID`),
  KEY `taskID` (`taskID`,`orderID`)
) ENGINE=InnoDB AUTO_INCREMENT=2518 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_madeextdeliveries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_madeextdeliveries` (
  `mdID` int(11) NOT NULL AUTO_INCREMENT,
  `carrierID` int(11) DEFAULT NULL,
  `deliveryCost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `taskID` int(11) DEFAULT NULL,
  `orderID` int(11) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `userID` int(11) DEFAULT NULL,
  PRIMARY KEY (`mdID`),
  KEY `carrierID` (`carrierID`),
  KEY `taskID` (`taskID`,`orderID`)
) ENGINE=InnoDB AUTO_INCREMENT=230 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_material_balances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_material_balances` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `materialID` int(11) NOT NULL,
  `date` datetime NOT NULL DEFAULT current_timestamp(),
  `balance` decimal(10,3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `materialID` (`materialID`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_material_recv`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_material_recv` (
  `historyID` int(11) NOT NULL,
  `userID` int(11) NOT NULL,
  `completed` int(1) DEFAULT NULL,
  PRIMARY KEY (`historyID`),
  KEY `userID` (`userID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_materials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_materials` (
  `materialID` int(1) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `measureID` varchar(255) NOT NULL,
  `materialQuantity` decimal(10,2) NOT NULL DEFAULT 0.00,
  `mExpendable` int(11) NOT NULL DEFAULT 0,
  `mRatioKG` decimal(10,6) DEFAULT NULL,
  `mBasic` int(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) DEFAULT NULL,
  `production` int(1) DEFAULT NULL,
  `minQuantity` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`materialID`)
) ENGINE=InnoDB AUTO_INCREMENT=62 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_materials_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_materials_history` (
  `historyID` int(11) NOT NULL AUTO_INCREMENT,
  `materialID` int(1) NOT NULL,
  `date` datetime NOT NULL DEFAULT current_timestamp(),
  `caID` int(11) DEFAULT NULL,
  `cacID` int(11) DEFAULT NULL,
  `mQuantity` decimal(10,3) NOT NULL,
  `storage` varchar(2) DEFAULT NULL,
  `mPrice` decimal(13,5) NOT NULL DEFAULT 0.00000,
  `userID` int(11) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  PRIMARY KEY (`historyID`),
  KEY `material_id` (`materialID`),
  KEY `caID` (`caID`),
  KEY `cacID` (`cacID`)
) ENGINE=InnoDB AUTO_INCREMENT=47970 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_measures`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_measures` (
  `measureID` int(11) NOT NULL AUTO_INCREMENT,
  `measureName` varchar(255) DEFAULT NULL,
  `measureNameUK` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`measureID`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_messages` (
  `msgID` int(11) NOT NULL AUTO_INCREMENT,
  `authorID` int(11) NOT NULL,
  `msgDate` datetime NOT NULL DEFAULT current_timestamp(),
  `message` text DEFAULT NULL,
  `recipientID` int(11) NOT NULL,
  `readDate` datetime DEFAULT NULL,
  `msgState` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`msgID`),
  KEY `recipientID` (`recipientID`)
) ENGINE=InnoDB AUTO_INCREMENT=113883 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_order_editing_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_order_editing_log` (
  `oecID` int(10) NOT NULL AUTO_INCREMENT,
  `orderID` int(10) NOT NULL,
  `oscID` int(10) NOT NULL,
  `orderEditing` text NOT NULL,
  PRIMARY KEY (`oecID`),
  KEY `orderID` (`orderID`,`oscID`)
) ENGINE=InnoDB AUTO_INCREMENT=104667 DEFAULT CHARSET=cp1251 COLLATE=cp1251_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_order_partial_shipment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_order_partial_shipment` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `taskID` int(11) DEFAULT NULL,
  `orderID` int(11) NOT NULL,
  `list` varchar(1024) NOT NULL,
  `carrierID` int(10) DEFAULT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `carrierID` (`carrierID`),
  KEY `taskID` (`taskID`),
  KEY `orderID` (`orderID`)
) ENGINE=InnoDB AUTO_INCREMENT=28160 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_order_returnDraft`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_order_returnDraft` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `products` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `date` datetime NOT NULL DEFAULT current_timestamp(),
  `comment` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `userID` int(11) DEFAULT NULL,
  `done` int(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1310 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_order_returns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_order_returns` (
  `returnID` int(10) NOT NULL AUTO_INCREMENT,
  `orderID` int(10) NOT NULL,
  `returnAmount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `returnDate` datetime NOT NULL DEFAULT current_timestamp(),
  `returnTaken` int(11) NOT NULL COMMENT 'acceptor userID',
  `userID` int(11) DEFAULT NULL,
  `returnMDate` datetime DEFAULT NULL,
  PRIMARY KEY (`returnID`),
  KEY `orderID` (`orderID`),
  KEY `returneDone` (`returnMDate`),
  KEY `returnTaken` (`returnTaken`)
) ENGINE=InnoDB AUTO_INCREMENT=5376 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_order_returns_carts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_order_returns_carts` (
  `orcID` int(10) NOT NULL AUTO_INCREMENT,
  `returnID` int(10) NOT NULL,
  `productID` int(10) NOT NULL,
  `productQuantity` decimal(10,2) NOT NULL DEFAULT 0.00,
  `productPrice` decimal(10,4) NOT NULL DEFAULT 0.0000,
  PRIMARY KEY (`orcID`),
  KEY `returnID` (`returnID`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=6093 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_order_status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_order_status` (
  `statusID` int(1) NOT NULL AUTO_INCREMENT,
  `status_name` varchar(30) NOT NULL,
  `color` varchar(255) NOT NULL,
  `checked` int(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`statusID`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_order_status_changelog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_order_status_changelog` (
  `oscID` int(11) NOT NULL AUTO_INCREMENT,
  `orderID` int(11) NOT NULL,
  `statusID` int(1) NOT NULL,
  `status_change_time` datetime NOT NULL DEFAULT current_timestamp(),
  `changelog` varchar(255) NOT NULL,
  `taskID` int(11) DEFAULT NULL,
  `userID` int(11) NOT NULL,
  `returnID` int(10) DEFAULT NULL,
  PRIMARY KEY (`oscID`),
  KEY `orderID` (`orderID`),
  KEY `taskID` (`taskID`),
  KEY `statusID` (`statusID`),
  KEY `returnID` (`returnID`)
) ENGINE=InnoDB AUTO_INCREMENT=231757 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_ordered_carts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_ordered_carts` (
  `orderCartID` int(11) NOT NULL AUTO_INCREMENT,
  `orderID` int(11) NOT NULL,
  `productID` int(11) NOT NULL,
  `Quantity` decimal(10,3) NOT NULL,
  `newPrice` decimal(10,4) NOT NULL DEFAULT 0.0000,
  PRIMARY KEY (`orderCartID`),
  KEY `orderID` (`orderID`,`productID`)
) ENGINE=InnoDB AUTO_INCREMENT=50749 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_orders` (
  `orderid` int(11) NOT NULL AUTO_INCREMENT,
  `order_time` datetime NOT NULL DEFAULT current_timestamp(),
  `status_id` int(1) NOT NULL,
  `customerID` int(11) NOT NULL,
  `shipping_address` text DEFAULT NULL,
  `desired_date` datetime NOT NULL DEFAULT current_timestamp(),
  `comments` text DEFAULT NULL,
  `prepayment` decimal(10,2) NOT NULL DEFAULT 0.00,
  `userID` int(5) NOT NULL,
  `carrierID` int(11) DEFAULT NULL,
  `shippingCost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `shippingCount` int(11) DEFAULT NULL,
  `stackerID` int(11) DEFAULT NULL,
  `markup` int(1) NOT NULL DEFAULT 0,
  `oDiscount` int(1) DEFAULT NULL,
  `ei_per_delivery` int(1) NOT NULL DEFAULT 0,
  `spec_per_delivery` int(1) NOT NULL DEFAULT 0,
  `contractID` int(11) DEFAULT NULL,
  `requestID` int(11) DEFAULT NULL,
  `loyalty` tinytext DEFAULT NULL,
  `loyalty_comment` text DEFAULT NULL,
  PRIMARY KEY (`orderid`),
  KEY `userID` (`userID`),
  KEY `status_id` (`status_id`),
  KEY `customerID` (`customerID`)
) ENGINE=InnoDB AUTO_INCREMENT=26080306 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_payments` (
  `paymentID` int(11) NOT NULL AUTO_INCREMENT,
  `sumOfPayment` decimal(10,2) NOT NULL,
  `userID` int(11) DEFAULT NULL,
  `orderID` int(11) DEFAULT NULL,
  `paymentDate` datetime NOT NULL DEFAULT current_timestamp(),
  `moneyTaken` int(1) DEFAULT NULL,
  `paymentComment` text DEFAULT NULL,
  `pCashless` int(1) NOT NULL DEFAULT 0,
  `toCard` int(1) DEFAULT NULL,
  PRIMARY KEY (`paymentID`)
) ENGINE=InnoDB AUTO_INCREMENT=23142 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_payments_cashoffice`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_payments_cashoffice` (
  `paymentID` int(10) NOT NULL AUTO_INCREMENT,
  `paymentAmount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `userID` int(10) NOT NULL,
  `orderID` int(11) DEFAULT NULL,
  `paymentDate` datetime NOT NULL DEFAULT current_timestamp(),
  `paymentComment` text DEFAULT NULL,
  `cashboxID` int(1) DEFAULT NULL,
  PRIMARY KEY (`paymentID`),
  KEY `userID` (`userID`),
  KEY `orderID` (`orderID`),
  KEY `cashboxID` (`cashboxID`)
) ENGINE=InnoDB AUTO_INCREMENT=23435 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_payments_shipment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_payments_shipment` (
  `paymentID` int(10) NOT NULL AUTO_INCREMENT,
  `carID` int(5) DEFAULT NULL,
  `sumOfPayment` decimal(10,2) NOT NULL DEFAULT 0.00,
  `userID` int(10) DEFAULT NULL,
  `taskID` int(10) DEFAULT NULL,
  `paymentDate` datetime NOT NULL DEFAULT current_timestamp(),
  `moneyTaken` int(1) DEFAULT NULL,
  `paymentComment` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `pCashless` int(1) NOT NULL DEFAULT 0,
  `orderID` int(10) DEFAULT NULL,
  `toCard` int(1) DEFAULT NULL,
  PRIMARY KEY (`paymentID`),
  KEY `carID` (`carID`)
) ENGINE=InnoDB AUTO_INCREMENT=2951 DEFAULT CHARSET=cp1251 COLLATE=cp1251_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_product_price_archive`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_product_price_archive` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `productID` int(11) NOT NULL,
  `price` text NOT NULL,
  `archive_date` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `productID` (`productID`)
) ENGINE=InnoDB AUTO_INCREMENT=6449 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_production_recipe_archive`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_production_recipe_archive` (
  `prID` int(11) NOT NULL AUTO_INCREMENT,
  `productID` int(11) NOT NULL,
  `pRecipe` text NOT NULL,
  `prOutputWeight` decimal(10,4) DEFAULT NULL,
  `prOutput` double(10,4) DEFAULT NULL,
  `prDescr` text DEFAULT NULL,
  `date` datetime DEFAULT NULL,
  `pressID` int(11) DEFAULT NULL,
  `archive_date` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`prID`),
  KEY `productID` (`productID`)
) ENGINE=InnoDB AUTO_INCREMENT=419 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_production_recipes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_production_recipes` (
  `prID` int(11) NOT NULL AUTO_INCREMENT,
  `productID` int(11) NOT NULL,
  `pRecipe` text NOT NULL,
  `prOutputWeight` decimal(10,4) DEFAULT NULL,
  `prOutput` double(10,4) DEFAULT NULL,
  `prDescr` text DEFAULT NULL,
  `date` datetime DEFAULT NULL,
  `pressID` int(11) DEFAULT NULL,
  PRIMARY KEY (`prID`),
  KEY `productID` (`productID`)
) ENGINE=InnoDB AUTO_INCREMENT=644 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_production_recipes_off`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_production_recipes_off` (
  `prID` int(11) NOT NULL AUTO_INCREMENT,
  `productID` int(11) NOT NULL,
  `pRecipe` text NOT NULL,
  `prOutputWeight` decimal(10,4) DEFAULT NULL,
  `prOutput` double(10,4) DEFAULT NULL,
  `prDescr` text DEFAULT NULL,
  PRIMARY KEY (`prID`),
  KEY `productID` (`productID`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent` int(1) NOT NULL,
  `name` varchar(510) NOT NULL,
  `measure` varchar(255) NOT NULL,
  `storage1` decimal(10,2) DEFAULT 0.00,
  `storage2` decimal(10,2) DEFAULT 0.00,
  `storage3` decimal(10,2) NOT NULL DEFAULT 0.00,
  `price` decimal(10,2) NOT NULL,
  `sorting` int(1) NOT NULL,
  `type` int(1) NOT NULL,
  `pallets` varchar(255) DEFAULT NULL,
  `invName` varchar(255) DEFAULT NULL,
  `name_uk` varchar(510) DEFAULT NULL,
  `weight_per_unit` decimal(10,2) DEFAULT NULL,
  `enabled` int(11) NOT NULL DEFAULT 1,
  `forms` varchar(255) DEFAULT NULL,
  `minQuantity` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1774 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_products_drafts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_products_drafts` (
  `draftID` int(11) NOT NULL AUTO_INCREMENT,
  `productID` int(11) NOT NULL,
  `Quantity` float NOT NULL,
  `userID` int(11) NOT NULL,
  `addtime` datetime NOT NULL,
  PRIMARY KEY (`draftID`)
) ENGINE=InnoDB AUTO_INCREMENT=71 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_products_moving`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_products_moving` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `date` datetime NOT NULL DEFAULT current_timestamp(),
  `source` int(11) DEFAULT NULL,
  `destination` int(11) DEFAULT NULL,
  `card` text DEFAULT NULL,
  `state` int(1) DEFAULT NULL COMMENT '0-новое, 1-создана задача на отгрузку, 2-выполнена задача на отгрузку, 3-выполнено  ',
  `taskID` int(11) DEFAULT NULL,
  `userID` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_request_cards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_request_cards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `requestID` int(11) NOT NULL,
  `productID` int(11) NOT NULL,
  `quantity` decimal(10,2) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4540 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_request_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_request_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `requestID` int(11) NOT NULL,
  `date` datetime NOT NULL DEFAULT current_timestamp(),
  `text` text DEFAULT NULL,
  `userID` int(11) NOT NULL,
  `cancelComment` text DEFAULT NULL,
  `cancelType` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7557 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_request_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_request_tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `requestID` int(11) NOT NULL,
  `userID` int(11) NOT NULL,
  `date` datetime NOT NULL DEFAULT current_timestamp(),
  `text` text DEFAULT NULL,
  `executor` int(11) DEFAULT NULL,
  `done` tinyint(1) DEFAULT NULL,
  `date_completion` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `requestID` (`requestID`),
  KEY `done` (`done`)
) ENGINE=InnoDB AUTO_INCREMENT=4257 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_requests` (
  `requestID` int(11) NOT NULL AUTO_INCREMENT,
  `request_time` datetime NOT NULL DEFAULT current_timestamp(),
  `customerID` int(11) DEFAULT NULL,
  `shipping_address` text DEFAULT NULL,
  `desired_date` date NOT NULL DEFAULT current_timestamp(),
  `comment` text DEFAULT NULL,
  `budget` decimal(10,2) NOT NULL DEFAULT 0.00,
  `customer_source` int(11) DEFAULT NULL COMMENT '1 - Флаер, 2 - Бигборд, 3 - Поисковик/сайт, 4 - Соц. сети, 5 - По совету',
  `stackerID` int(11) DEFAULT NULL,
  `director_comment` text DEFAULT NULL,
  `userID` int(5) NOT NULL,
  `markup` int(1) NOT NULL DEFAULT 0,
  `cancelled` tinyint(1) DEFAULT NULL,
  `hidden` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`requestID`),
  KEY `userID` (`userID`),
  KEY `customerID` (`customerID`)
) ENGINE=InnoDB AUTO_INCREMENT=6537 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_stacker_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_stacker_payments` (
  `spID` int(11) NOT NULL AUTO_INCREMENT,
  `stackerID` int(11) NOT NULL,
  `orderID` int(11) NOT NULL,
  `clientLedStacker` int(11) NOT NULL DEFAULT 0,
  `spPayDate` datetime NOT NULL DEFAULT current_timestamp(),
  `spAmount` decimal(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`spID`),
  KEY `stackerID` (`stackerID`),
  KEY `orderID` (`orderID`)
) ENGINE=InnoDB AUTO_INCREMENT=4330 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_supplies_tools_cats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_supplies_tools_cats` (
  `catID` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  PRIMARY KEY (`catID`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_supplies_tools_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_supplies_tools_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `prodID` int(11) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT NULL,
  `comment` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `date` datetime NOT NULL DEFAULT current_timestamp(),
  `wID` int(11) DEFAULT NULL,
  `userID` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=88 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_supplies_tools_ones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_supplies_tools_ones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `catID` int(11) NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT 0.00,
  `wID` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `catID` (`catID`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_t_contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_t_contacts` (
  `t_contactID` int(11) NOT NULL AUTO_INCREMENT,
  `t_contactName` varchar(255) DEFAULT NULL,
  `t_cParentID` int(11) NOT NULL DEFAULT 0,
  `t_contact_person` int(1) DEFAULT NULL,
  PRIMARY KEY (`t_contactID`),
  KEY `t_cParentID` (`t_cParentID`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_task_p_drafts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_task_p_drafts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tpID` int(11) NOT NULL,
  `userID` int(11) NOT NULL,
  `date` datetime NOT NULL,
  `productID` int(11) DEFAULT NULL,
  `Quantity` decimal(10,2) DEFAULT NULL,
  `pressID` int(11) DEFAULT NULL,
  `shiftID` int(11) DEFAULT NULL,
  `ssDate` datetime DEFAULT NULL,
  `ssUserID` int(11) DEFAULT NULL,
  `comment` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tpID` (`tpID`)
) ENGINE=InnoDB AUTO_INCREMENT=32540 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `prod_id` int(11) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT NULL,
  `storage` int(1) NOT NULL,
  `priority` int(1) DEFAULT NULL,
  `date` datetime NOT NULL,
  `status` int(1) NOT NULL DEFAULT 0,
  `type` int(1) NOT NULL,
  `comment` varchar(255) DEFAULT NULL,
  `orderID` int(11) DEFAULT NULL,
  `dateOfPerformance` datetime DEFAULT NULL,
  `dateOfCompletion` datetime DEFAULT NULL,
  `carrierID` int(5) DEFAULT NULL,
  `shipmentPrice` decimal(10,2) DEFAULT NULL,
  `salesInvoiceID` int(11) DEFAULT NULL,
  `ttndistance` decimal(10,0) DEFAULT NULL,
  `allowed` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `carrierID` (`carrierID`),
  KEY `status` (`status`),
  KEY `orderID` (`orderID`),
  KEY `dateOfCompletion` (`dateOfCompletion`),
  KEY `dateOfPerformance` (`dateOfPerformance`)
) ENGINE=InnoDB AUTO_INCREMENT=28928 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_tasks_carts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_tasks_carts` (
  `tcID` int(11) NOT NULL AUTO_INCREMENT,
  `taskID` int(11) NOT NULL,
  `productID` int(11) NOT NULL,
  `Quantity` decimal(10,3) NOT NULL,
  `Price` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`tcID`)
) ENGINE=InnoDB AUTO_INCREMENT=75719 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_tasks_production`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_tasks_production` (
  `tpID` int(11) NOT NULL AUTO_INCREMENT,
  `tpDate` datetime NOT NULL DEFAULT current_timestamp(),
  `userID` int(11) NOT NULL,
  `dateCompletion` datetime NOT NULL DEFAULT current_timestamp(),
  `productID` varchar(255) NOT NULL,
  `Quantity` decimal(10,2) NOT NULL DEFAULT 0.00,
  `pressID` int(11) NOT NULL,
  `order_sort` int(11) DEFAULT NULL,
  `tpDone` int(1) DEFAULT NULL,
  `taskComment` text DEFAULT NULL,
  PRIMARY KEY (`tpID`),
  KEY `productID` (`productID`),
  KEY `pressID` (`pressID`),
  KEY `order_sort` (`order_sort`)
) ENGINE=InnoDB AUTO_INCREMENT=5698 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_tasks_shipment_collected`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_tasks_shipment_collected` (
  `taskID` int(10) NOT NULL,
  `taskCollected` int(10) NOT NULL DEFAULT 0,
  PRIMARY KEY (`taskID`)
) ENGINE=InnoDB DEFAULT CHARSET=cp1251 COLLATE=cp1251_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_tasks_transport_list`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_tasks_transport_list` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `date` datetime NOT NULL DEFAULT current_timestamp(),
  `dateOfPerformance` datetime DEFAULT NULL,
  `carID` int(11) DEFAULT NULL,
  `state` int(1) NOT NULL DEFAULT 0,
  `dateOfCompletion` datetime DEFAULT NULL,
  `shipmentPrice` decimal(10,2) NOT NULL DEFAULT 0.00,
  `comment` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=94 DEFAULT CHARSET=cp1251 COLLATE=cp1251_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_timeTable`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_timeTable` (
  `ttID` int(11) NOT NULL COMMENT 'Ymd',
  `dayTTable` text DEFAULT NULL,
  `commentL` text DEFAULT NULL,
  PRIMARY KEY (`ttID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_timeTable_shifts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_timeTable_shifts` (
  `ttsID` int(11) NOT NULL AUTO_INCREMENT,
  `ttID` int(11) NOT NULL,
  `pressID` int(1) NOT NULL,
  `shiftID` int(1) NOT NULL,
  `wIDs` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `userID` int(11) NOT NULL,
  PRIMARY KEY (`ttsID`)
) ENGINE=InnoDB AUTO_INCREMENT=288 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_tt_add_works`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_tt_add_works` (
  `awID` int(11) NOT NULL AUTO_INCREMENT,
  `ttID` int(11) DEFAULT NULL,
  `wID` int(11) DEFAULT NULL,
  `comment` varchar(255) DEFAULT NULL,
  `hours` decimal(10,1) DEFAULT NULL,
  PRIMARY KEY (`awID`),
  KEY `ttID` (`ttID`,`wID`)
) ENGINE=InnoDB AUTO_INCREMENT=3817 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_tt_presses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_tt_presses` (
  `ttID` int(11) NOT NULL,
  `pressComment` text DEFAULT NULL,
  `pressForms` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`ttID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_user_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_user_permissions` (
  `userID` int(11) NOT NULL,
  `userPermissions` varchar(1024) DEFAULT NULL,
  `didDefault` int(10) NOT NULL DEFAULT 101,
  PRIMARY KEY (`userID`)
) ENGINE=InnoDB DEFAULT CHARSET=cp1251 COLLATE=cp1251_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_users` (
  `user_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_login` varchar(30) DEFAULT NULL,
  `user_password` varchar(32) NOT NULL DEFAULT '*',
  `user_hash` varchar(32) DEFAULT NULL,
  `hash_time` datetime DEFAULT NULL,
  `user_priv` int(1) unsigned NOT NULL DEFAULT 0,
  `user_name` varchar(255) NOT NULL,
  `sortdirection` varchar(255) DEFAULT NULL,
  `orderStateSelected` varchar(25) DEFAULT NULL,
  `managerSelected` varchar(255) DEFAULT NULL,
  `userPhone` varchar(10) DEFAULT NULL,
  `groupID` int(1) NOT NULL DEFAULT 1000,
  `wID` int(11) DEFAULT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=1607 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_visits_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_visits_records` (
  `ttID` int(11) NOT NULL,
  `dayTTable` text NOT NULL,
  PRIMARY KEY (`ttID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_wCredits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_wCredits` (
  `wCreditID` int(11) NOT NULL AUTO_INCREMENT,
  `wID` int(11) NOT NULL,
  `wCreditDate` datetime NOT NULL DEFAULT current_timestamp(),
  `wCreditAmount` varchar(255) NOT NULL,
  `wCreditShPay` decimal(10,2) NOT NULL DEFAULT 0.00,
  `wCreditPaid` decimal(10,2) DEFAULT 0.00,
  `wCreditClosed` int(1) NOT NULL DEFAULT 0,
  `wCreditClosedDate` datetime DEFAULT NULL,
  PRIMARY KEY (`wCreditID`),
  KEY `wID` (`wID`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_wCreditsHistory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_wCreditsHistory` (
  `wchID` int(11) NOT NULL,
  `wCreditID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_wPayments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_wPayments` (
  `wpID` int(11) NOT NULL AUTO_INCREMENT,
  `wpDID` int(11) DEFAULT NULL,
  `wID` int(11) DEFAULT NULL,
  `wpAmount` decimal(10,2) DEFAULT NULL,
  `wpDate` datetime NOT NULL DEFAULT current_timestamp(),
  `wpTypePayment` int(11) DEFAULT NULL,
  `wpComment` varchar(255) DEFAULT NULL,
  `userID` int(11) DEFAULT NULL,
  `wCreditID` int(11) DEFAULT NULL,
  PRIMARY KEY (`wpID`),
  KEY `wID` (`wID`)
) ENGINE=InnoDB AUTO_INCREMENT=14074 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_workers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_workers` (
  `wID` int(11) NOT NULL AUTO_INCREMENT,
  `wSurname` varchar(255) DEFAULT NULL,
  `wName` varchar(255) DEFAULT NULL,
  `wPatronymic` varchar(255) DEFAULT NULL,
  `wPhone` varchar(255) DEFAULT NULL,
  `wSubdivision` int(11) DEFAULT NULL,
  `wPosition` int(11) DEFAULT NULL,
  `wDateOfEmployment` datetime NOT NULL DEFAULT current_timestamp(),
  `wDismissed` int(1) NOT NULL DEFAULT 0,
  `wAddress` varchar(255) DEFAULT NULL,
  `wPassport` varchar(255) DEFAULT NULL,
  `wINN` int(11) DEFAULT NULL,
  `wDateOfDismissal` datetime DEFAULT NULL,
  `contractNumber` varchar(255) DEFAULT NULL,
  `wageRate` decimal(10,2) DEFAULT NULL,
  `wageRatePerDay` decimal(10,2) DEFAULT NULL,
  `additional` text DEFAULT NULL,
  `wCabinet` tinyint(2) DEFAULT NULL,
  PRIMARY KEY (`wID`),
  KEY `wPhone` (`wPhone`),
  KEY `wDismissed` (`wDismissed`)
) ENGINE=InnoDB AUTO_INCREMENT=863 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sklad_workers_info`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sklad_workers_info` (
  `wID` int(11) NOT NULL,
  `wDateOfBirth` date DEFAULT NULL,
  `wAddress` text DEFAULT NULL,
  `wResidenceAddress` text DEFAULT NULL,
  `wEmail` varchar(255) DEFAULT NULL,
  `wEducation` text DEFAULT NULL,
  `wChildren` text DEFAULT NULL,
  `wPosition` varchar(255) DEFAULT NULL,
  `wFirstWorkDay` date DEFAULT NULL,
  `wDateOfEmployment` date DEFAULT NULL,
  `wDateOfDismissal` date DEFAULT NULL,
  UNIQUE KEY `wID` (`wID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

