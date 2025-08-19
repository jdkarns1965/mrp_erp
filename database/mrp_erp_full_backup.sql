-- MySQL dump 10.13  Distrib 8.0.43, for Linux (x86_64)
--
-- Host: localhost    Database: mrp_erp
-- ------------------------------------------------------
-- Server version	8.0.43-0ubuntu0.22.04.1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `mrp_erp`
--

/*!40000 DROP DATABASE IF EXISTS `mrp_erp`*/;

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `mrp_erp` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;

USE `mrp_erp`;

--
-- Table structure for table `bom_details`
--

DROP TABLE IF EXISTS `bom_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bom_details` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `bom_header_id` int unsigned NOT NULL,
  `material_id` int unsigned NOT NULL,
  `quantity_per` decimal(15,6) NOT NULL COMMENT 'Quantity needed per unit of finished product',
  `uom_id` int unsigned NOT NULL,
  `scrap_percentage` decimal(5,2) DEFAULT '0.00',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_bom_material` (`bom_header_id`,`material_id`),
  KEY `uom_id` (`uom_id`),
  KEY `idx_material_lookup` (`material_id`),
  CONSTRAINT `bom_details_ibfk_1` FOREIGN KEY (`bom_header_id`) REFERENCES `bom_headers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bom_details_ibfk_2` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `bom_details_ibfk_3` FOREIGN KEY (`uom_id`) REFERENCES `units_of_measure` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bom_details`
--

LOCK TABLES `bom_details` WRITE;
/*!40000 ALTER TABLE `bom_details` DISABLE KEYS */;
INSERT INTO `bom_details` (`id`, `bom_header_id`, `material_id`, `quantity_per`, `uom_id`, `scrap_percentage`, `notes`, `created_at`, `updated_at`) VALUES (1,1,1,0.120000,1,5.00,NULL,'2025-08-19 14:16:20','2025-08-19 14:16:20'),(2,2,1,1.100000,1,8.00,NULL,'2025-08-19 14:16:20','2025-08-19 14:16:20'),(3,2,3,4.000000,7,2.00,NULL,'2025-08-19 14:16:20','2025-08-19 14:16:20');
/*!40000 ALTER TABLE `bom_details` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bom_headers`
--

DROP TABLE IF EXISTS `bom_headers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bom_headers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int unsigned NOT NULL,
  `version` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1.0',
  `description` text COLLATE utf8mb4_unicode_ci,
  `effective_date` date NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `approved_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_active_bom` (`product_id`,`version`),
  KEY `idx_bom_active` (`is_active`),
  KEY `idx_bom_dates` (`effective_date`,`expiry_date`),
  CONSTRAINT `bom_headers_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bom_headers`
--

LOCK TABLES `bom_headers` WRITE;
/*!40000 ALTER TABLE `bom_headers` DISABLE KEYS */;
INSERT INTO `bom_headers` (`id`, `product_id`, `version`, `description`, `effective_date`, `expiry_date`, `is_active`, `approved_by`, `approved_date`, `created_at`, `updated_at`) VALUES (1,1,'1.0','BOM for Test Container','2025-01-01',NULL,1,'Engineering','2025-01-15','2025-08-19 14:16:20','2025-08-19 14:16:20'),(2,2,'1.0','BOM for Complex Product','2025-01-01',NULL,1,'Engineering','2025-01-15','2025-08-19 14:16:20','2025-08-19 14:16:20');
/*!40000 ALTER TABLE `bom_headers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customer_order_details`
--

DROP TABLE IF EXISTS `customer_order_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `customer_order_details` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int unsigned NOT NULL,
  `product_id` int unsigned NOT NULL,
  `quantity` decimal(15,4) NOT NULL,
  `uom_id` int unsigned NOT NULL,
  `unit_price` decimal(15,4) DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `uom_id` (`uom_id`),
  KEY `idx_order_product` (`order_id`,`product_id`),
  CONSTRAINT `customer_order_details_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `customer_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `customer_order_details_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `customer_order_details_ibfk_3` FOREIGN KEY (`uom_id`) REFERENCES `units_of_measure` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customer_order_details`
--

LOCK TABLES `customer_order_details` WRITE;
/*!40000 ALTER TABLE `customer_order_details` DISABLE KEYS */;
INSERT INTO `customer_order_details` (`id`, `order_id`, `product_id`, `quantity`, `uom_id`, `unit_price`, `notes`, `created_at`, `updated_at`) VALUES (1,1,1,25.0000,7,12.9900,NULL,'2025-08-19 14:16:44','2025-08-19 14:16:44'),(2,2,2,35.0000,7,49.9900,NULL,'2025-08-19 14:16:44','2025-08-19 14:16:44');
/*!40000 ALTER TABLE `customer_order_details` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customer_orders`
--

DROP TABLE IF EXISTS `customer_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `customer_orders` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `order_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_date` date NOT NULL,
  `required_date` date NOT NULL,
  `status` enum('pending','confirmed','in_production','completed','shipped','cancelled') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_number` (`order_number`),
  KEY `idx_order_status` (`status`),
  KEY `idx_order_dates` (`order_date`,`required_date`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customer_orders`
--

LOCK TABLES `customer_orders` WRITE;
/*!40000 ALTER TABLE `customer_orders` DISABLE KEYS */;
INSERT INTO `customer_orders` (`id`, `order_number`, `customer_name`, `order_date`, `required_date`, `status`, `notes`, `created_at`, `updated_at`) VALUES (1,'SO-001','Test Customer','2025-08-18','2025-08-30','confirmed','Test order for validation','2025-08-19 14:16:44','2025-08-19 14:16:44'),(2,'SO-002','Large Customer','2025-08-18','2025-09-15','confirmed','Large test order','2025-08-19 14:16:44','2025-08-19 14:16:44');
/*!40000 ALTER TABLE `customer_orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory`
--

DROP TABLE IF EXISTS `inventory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `item_type` enum('material','product') COLLATE utf8mb4_unicode_ci NOT NULL,
  `item_id` int unsigned NOT NULL COMMENT 'References either materials.id or products.id',
  `lot_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `location_id` int unsigned NOT NULL,
  `quantity` decimal(15,4) NOT NULL,
  `reserved_quantity` decimal(15,4) DEFAULT '0.0000',
  `uom_id` int unsigned NOT NULL,
  `manufacture_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `received_date` date NOT NULL,
  `supplier_id` int unsigned DEFAULT NULL,
  `po_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit_cost` decimal(15,4) DEFAULT NULL,
  `status` enum('available','reserved','quarantine','expired','consumed') COLLATE utf8mb4_unicode_ci DEFAULT 'available',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `uom_id` (`uom_id`),
  KEY `supplier_id` (`supplier_id`),
  KEY `idx_item_type_id` (`item_type`,`item_id`),
  KEY `idx_lot_number` (`lot_number`),
  KEY `idx_status` (`status`),
  KEY `idx_location` (`location_id`),
  KEY `idx_expiry` (`expiry_date`),
  KEY `idx_inventory_available` (`status`,`item_type`,`item_id`),
  CONSTRAINT `inventory_ibfk_1` FOREIGN KEY (`location_id`) REFERENCES `storage_locations` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `inventory_ibfk_2` FOREIGN KEY (`uom_id`) REFERENCES `units_of_measure` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `inventory_ibfk_3` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory`
--

LOCK TABLES `inventory` WRITE;
/*!40000 ALTER TABLE `inventory` DISABLE KEYS */;
INSERT INTO `inventory` (`id`, `item_type`, `item_id`, `lot_number`, `location_id`, `quantity`, `reserved_quantity`, `uom_id`, `manufacture_date`, `expiry_date`, `received_date`, `supplier_id`, `po_number`, `unit_cost`, `status`, `created_at`, `updated_at`) VALUES (1,'material',1,'ABS-2025-001',1,850.0000,0.0000,1,'2025-01-15','2026-01-15','2025-01-20',1,'PO-001',2.4500,'available','2025-08-19 14:16:33','2025-08-19 14:16:33'),(2,'material',2,'PP-2025-001',1,200.0000,0.0000,1,'2025-02-01','2026-02-01','2025-02-05',1,'PO-002',1.8000,'available','2025-08-19 14:16:33','2025-08-19 14:16:33'),(3,'material',3,'INS-2025-001',1,2500.0000,0.0000,7,NULL,NULL,'2025-01-25',2,'PO-003',0.1400,'available','2025-08-19 14:16:33','2025-08-19 14:16:33');
/*!40000 ALTER TABLE `inventory` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory_transactions`
--

DROP TABLE IF EXISTS `inventory_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory_transactions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `transaction_type` enum('receipt','issue','adjustment','transfer','return','scrap') COLLATE utf8mb4_unicode_ci NOT NULL,
  `transaction_date` datetime NOT NULL,
  `item_type` enum('material','product') COLLATE utf8mb4_unicode_ci NOT NULL,
  `item_id` int unsigned NOT NULL,
  `lot_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `from_location_id` int unsigned DEFAULT NULL,
  `to_location_id` int unsigned DEFAULT NULL,
  `quantity` decimal(15,4) NOT NULL,
  `uom_id` int unsigned NOT NULL,
  `reference_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'PO, Production Order, Sales Order, etc.',
  `reference_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `performed_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `from_location_id` (`from_location_id`),
  KEY `to_location_id` (`to_location_id`),
  KEY `uom_id` (`uom_id`),
  KEY `idx_transaction_date` (`transaction_date`),
  KEY `idx_transaction_type` (`transaction_type`),
  KEY `idx_item_lookup` (`item_type`,`item_id`),
  KEY `idx_reference` (`reference_type`,`reference_number`),
  CONSTRAINT `inventory_transactions_ibfk_1` FOREIGN KEY (`from_location_id`) REFERENCES `storage_locations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_transactions_ibfk_2` FOREIGN KEY (`to_location_id`) REFERENCES `storage_locations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_transactions_ibfk_3` FOREIGN KEY (`uom_id`) REFERENCES `units_of_measure` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_transactions`
--

LOCK TABLES `inventory_transactions` WRITE;
/*!40000 ALTER TABLE `inventory_transactions` DISABLE KEYS */;
/*!40000 ALTER TABLE `inventory_transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `master_production_schedule`
--

DROP TABLE IF EXISTS `master_production_schedule`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `master_production_schedule` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int unsigned NOT NULL,
  `period_id` int unsigned NOT NULL,
  `demand_qty` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `firm_planned_qty` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `scheduled_qty` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `available_to_promise` decimal(15,4) DEFAULT '0.0000',
  `status` enum('draft','firm','released','completed') COLLATE utf8mb4_unicode_ci DEFAULT 'draft',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_mps` (`product_id`,`period_id`),
  KEY `idx_mps_status` (`status`),
  KEY `idx_mps_period` (`period_id`),
  CONSTRAINT `master_production_schedule_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `master_production_schedule_ibfk_2` FOREIGN KEY (`period_id`) REFERENCES `planning_calendar` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `master_production_schedule`
--

LOCK TABLES `master_production_schedule` WRITE;
/*!40000 ALTER TABLE `master_production_schedule` DISABLE KEYS */;
/*!40000 ALTER TABLE `master_production_schedule` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `material_categories`
--

DROP TABLE IF EXISTS `material_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `material_categories` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `material_categories`
--

LOCK TABLES `material_categories` WRITE;
/*!40000 ALTER TABLE `material_categories` DISABLE KEYS */;
INSERT INTO `material_categories` (`id`, `name`, `description`, `created_at`, `updated_at`) VALUES (1,'Raw Materials','Primary materials used in production','2025-08-19 14:13:10','2025-08-19 14:13:10'),(2,'Packaging','Packaging materials','2025-08-19 14:13:10','2025-08-19 14:13:10'),(3,'Components','Purchased components and parts','2025-08-19 14:13:10','2025-08-19 14:13:10'),(4,'Consumables','Consumable supplies','2025-08-19 14:13:10','2025-08-19 14:13:10');
/*!40000 ALTER TABLE `material_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `materials`
--

DROP TABLE IF EXISTS `materials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `materials` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `material_code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `category_id` int unsigned DEFAULT NULL,
  `material_type` enum('resin','insert','packaging','component','consumable','other') COLLATE utf8mb4_unicode_ci NOT NULL,
  `uom_id` int unsigned NOT NULL,
  `min_stock_qty` decimal(15,4) DEFAULT '0.0000',
  `max_stock_qty` decimal(15,4) DEFAULT '0.0000',
  `reorder_point` decimal(15,4) DEFAULT '0.0000',
  `safety_stock_qty` decimal(15,4) DEFAULT '0.0000',
  `lead_time_days` int DEFAULT '0',
  `default_supplier_id` int unsigned DEFAULT NULL,
  `cost_per_unit` decimal(15,4) DEFAULT '0.0000',
  `is_lot_controlled` tinyint(1) DEFAULT '1',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `material_code` (`material_code`),
  KEY `category_id` (`category_id`),
  KEY `uom_id` (`uom_id`),
  KEY `default_supplier_id` (`default_supplier_id`),
  KEY `idx_material_type` (`material_type`),
  KEY `idx_material_active` (`is_active`),
  KEY `idx_material_deleted` (`deleted_at`),
  CONSTRAINT `materials_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `material_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `materials_ibfk_2` FOREIGN KEY (`uom_id`) REFERENCES `units_of_measure` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `materials_ibfk_3` FOREIGN KEY (`default_supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `materials`
--

LOCK TABLES `materials` WRITE;
/*!40000 ALTER TABLE `materials` DISABLE KEYS */;
INSERT INTO `materials` (`id`, `material_code`, `name`, `description`, `category_id`, `material_type`, `uom_id`, `min_stock_qty`, `max_stock_qty`, `reorder_point`, `safety_stock_qty`, `lead_time_days`, `default_supplier_id`, `cost_per_unit`, `is_lot_controlled`, `is_active`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,'RES-001','ABS Plastic Resin','Test ABS resin',1,'resin',1,500.0000,2000.0000,750.0000,0.0000,14,1,2.5000,1,1,'2025-08-19 14:15:56','2025-08-19 14:15:56',NULL),(2,'RES-002','PP Polypropylene','Test PP resin',1,'resin',1,300.0000,1500.0000,500.0000,0.0000,21,1,1.8500,1,1,'2025-08-19 14:15:56','2025-08-19 14:15:56',NULL),(3,'INS-001','Brass Insert','Test brass insert',3,'insert',7,1000.0000,5000.0000,1500.0000,0.0000,10,2,0.1500,0,1,'2025-08-19 14:15:56','2025-08-19 14:15:56',NULL);
/*!40000 ALTER TABLE `materials` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `mrp_requirements`
--

DROP TABLE IF EXISTS `mrp_requirements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mrp_requirements` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `calculation_date` datetime NOT NULL,
  `order_id` int unsigned NOT NULL,
  `product_id` int unsigned NOT NULL,
  `material_id` int unsigned NOT NULL,
  `gross_requirement` decimal(15,4) NOT NULL,
  `available_stock` decimal(15,4) NOT NULL,
  `net_requirement` decimal(15,4) NOT NULL,
  `suggested_order_qty` decimal(15,4) DEFAULT NULL,
  `suggested_order_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `material_id` (`material_id`),
  KEY `idx_calculation_date` (`calculation_date`),
  KEY `idx_order_lookup` (`order_id`),
  CONSTRAINT `mrp_requirements_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `customer_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mrp_requirements_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `mrp_requirements_ibfk_3` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mrp_requirements`
--

LOCK TABLES `mrp_requirements` WRITE;
/*!40000 ALTER TABLE `mrp_requirements` DISABLE KEYS */;
INSERT INTO `mrp_requirements` (`id`, `calculation_date`, `order_id`, `product_id`, `material_id`, `gross_requirement`, `available_stock`, `net_requirement`, `suggested_order_qty`, `suggested_order_date`, `created_at`) VALUES (1,'2025-08-19 14:34:56',2,2,3,142.8000,2500.0000,0.0000,0.0000,'2025-09-05','2025-08-19 18:34:56'),(2,'2025-08-19 14:34:56',2,2,1,41.5800,850.0000,0.0000,0.0000,'2025-09-01','2025-08-19 18:34:56');
/*!40000 ALTER TABLE `mrp_requirements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `planning_calendar`
--

DROP TABLE IF EXISTS `planning_calendar`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `planning_calendar` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `period_type` enum('daily','weekly','monthly') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'weekly',
  `period_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_working_period` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_period` (`period_start`,`period_end`),
  KEY `idx_period_dates` (`period_start`,`period_end`),
  KEY `idx_period_type` (`period_type`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `planning_calendar`
--

LOCK TABLES `planning_calendar` WRITE;
/*!40000 ALTER TABLE `planning_calendar` DISABLE KEYS */;
INSERT INTO `planning_calendar` (`id`, `period_start`, `period_end`, `period_type`, `period_name`, `is_working_period`, `created_at`, `updated_at`) VALUES (1,'2025-08-18','2025-08-24','weekly','Week 1 - 2025',1,'2025-08-19 17:39:16','2025-08-19 17:39:16'),(2,'2025-08-25','2025-08-31','weekly','Week 2 - 2025',1,'2025-08-19 17:39:16','2025-08-19 17:39:16'),(3,'2025-09-01','2025-09-07','weekly','Week 3 - 2025',1,'2025-08-19 17:39:16','2025-08-19 17:39:16'),(4,'2025-09-08','2025-09-14','weekly','Week 4 - 2025',1,'2025-08-19 17:39:16','2025-08-19 17:39:16'),(5,'2025-09-15','2025-09-21','weekly','Week 5 - 2025',1,'2025-08-19 17:39:16','2025-08-19 17:39:16'),(6,'2025-09-22','2025-09-28','weekly','Week 6 - 2025',1,'2025-08-19 17:39:16','2025-08-19 17:39:16'),(7,'2025-09-29','2025-10-05','weekly','Week 7 - 2025',1,'2025-08-19 17:39:16','2025-08-19 17:39:16'),(8,'2025-10-06','2025-10-12','weekly','Week 8 - 2025',1,'2025-08-19 17:39:16','2025-08-19 17:39:16'),(9,'2025-10-13','2025-10-19','weekly','Week 9 - 2025',1,'2025-08-19 17:39:16','2025-08-19 17:39:16'),(10,'2025-10-20','2025-10-26','weekly','Week 10 - 2025',1,'2025-08-19 17:39:16','2025-08-19 17:39:16'),(11,'2025-10-27','2025-11-02','weekly','Week 11 - 2025',1,'2025-08-19 17:39:16','2025-08-19 17:39:16'),(12,'2025-11-03','2025-11-09','weekly','Week 12 - 2025',1,'2025-08-19 17:39:16','2025-08-19 17:39:16'),(13,'2025-11-10','2025-11-16','weekly','Week 13 - 2025',1,'2025-08-19 17:39:16','2025-08-19 17:39:16');
/*!40000 ALTER TABLE `planning_calendar` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_categories`
--

DROP TABLE IF EXISTS `product_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_categories` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_categories`
--

LOCK TABLES `product_categories` WRITE;
/*!40000 ALTER TABLE `product_categories` DISABLE KEYS */;
INSERT INTO `product_categories` (`id`, `name`, `description`, `created_at`, `updated_at`) VALUES (1,'Finished Goods','Completed products ready for sale','2025-08-19 14:13:10','2025-08-19 14:13:10'),(2,'Semi-Finished','Partially completed products','2025-08-19 14:13:10','2025-08-19 14:13:10'),(3,'Assemblies','Product assemblies','2025-08-19 14:13:10','2025-08-19 14:13:10');
/*!40000 ALTER TABLE `product_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `production_order_materials`
--

DROP TABLE IF EXISTS `production_order_materials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `production_order_materials` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `production_order_id` int unsigned NOT NULL,
  `material_id` int unsigned NOT NULL,
  `quantity_required` decimal(15,4) NOT NULL,
  `quantity_reserved` decimal(15,4) DEFAULT '0.0000',
  `quantity_issued` decimal(15,4) DEFAULT '0.0000',
  `lot_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `issue_date` datetime DEFAULT NULL,
  `issued_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `material_id` (`material_id`),
  KEY `idx_po_material` (`production_order_id`,`material_id`),
  CONSTRAINT `production_order_materials_ibfk_1` FOREIGN KEY (`production_order_id`) REFERENCES `production_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `production_order_materials_ibfk_2` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `production_order_materials`
--

LOCK TABLES `production_order_materials` WRITE;
/*!40000 ALTER TABLE `production_order_materials` DISABLE KEYS */;
/*!40000 ALTER TABLE `production_order_materials` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `production_order_operations`
--

DROP TABLE IF EXISTS `production_order_operations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `production_order_operations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `production_order_id` int unsigned NOT NULL,
  `route_id` int unsigned NOT NULL,
  `work_center_id` int unsigned NOT NULL,
  `operation_sequence` int NOT NULL,
  `scheduled_start_datetime` datetime DEFAULT NULL,
  `scheduled_end_datetime` datetime DEFAULT NULL,
  `actual_start_datetime` datetime DEFAULT NULL,
  `actual_end_datetime` datetime DEFAULT NULL,
  `quantity_to_produce` decimal(15,4) NOT NULL,
  `quantity_completed` decimal(15,4) DEFAULT '0.0000',
  `quantity_scrapped` decimal(15,4) DEFAULT '0.0000',
  `setup_completed` tinyint(1) DEFAULT '0',
  `status` enum('planned','ready','in_progress','completed','cancelled') COLLATE utf8mb4_unicode_ci DEFAULT 'planned',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `operator_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `production_order_id` (`production_order_id`),
  KEY `route_id` (`route_id`),
  KEY `idx_operation_schedule` (`scheduled_start_datetime`,`scheduled_end_datetime`),
  KEY `idx_operation_work_center` (`work_center_id`),
  KEY `idx_operation_status` (`status`),
  CONSTRAINT `production_order_operations_ibfk_1` FOREIGN KEY (`production_order_id`) REFERENCES `production_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `production_order_operations_ibfk_2` FOREIGN KEY (`route_id`) REFERENCES `production_routes` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `production_order_operations_ibfk_3` FOREIGN KEY (`work_center_id`) REFERENCES `work_centers` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `production_order_operations`
--

LOCK TABLES `production_order_operations` WRITE;
/*!40000 ALTER TABLE `production_order_operations` DISABLE KEYS */;
/*!40000 ALTER TABLE `production_order_operations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `production_order_status_history`
--

DROP TABLE IF EXISTS `production_order_status_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `production_order_status_history` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `production_order_id` int unsigned NOT NULL,
  `old_status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `changed_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `changed_at` datetime NOT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status_history_order` (`production_order_id`),
  KEY `idx_status_history_date` (`changed_at`),
  CONSTRAINT `production_order_status_history_ibfk_1` FOREIGN KEY (`production_order_id`) REFERENCES `production_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `production_order_status_history`
--

LOCK TABLES `production_order_status_history` WRITE;
/*!40000 ALTER TABLE `production_order_status_history` DISABLE KEYS */;
/*!40000 ALTER TABLE `production_order_status_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `production_orders`
--

DROP TABLE IF EXISTS `production_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `production_orders` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `order_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_order_id` int unsigned DEFAULT NULL,
  `product_id` int unsigned NOT NULL,
  `quantity_ordered` decimal(15,4) NOT NULL,
  `quantity_completed` decimal(15,4) DEFAULT '0.0000',
  `quantity_scrapped` decimal(15,4) DEFAULT '0.0000',
  `scheduled_start_date` date DEFAULT NULL,
  `scheduled_end_date` date DEFAULT NULL,
  `actual_start_date` date DEFAULT NULL,
  `actual_end_date` date DEFAULT NULL,
  `priority_level` enum('low','normal','high','urgent') COLLATE utf8mb4_unicode_ci DEFAULT 'normal',
  `status` enum('planned','released','in_progress','completed','cancelled','on_hold') COLLATE utf8mb4_unicode_ci DEFAULT 'planned',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_number` (`order_number`),
  KEY `customer_order_id` (`customer_order_id`),
  KEY `product_id` (`product_id`),
  KEY `idx_production_status` (`status`),
  KEY `idx_production_dates` (`scheduled_start_date`,`scheduled_end_date`),
  KEY `idx_production_priority` (`priority_level`),
  CONSTRAINT `production_orders_ibfk_1` FOREIGN KEY (`customer_order_id`) REFERENCES `customer_orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `production_orders_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `production_orders`
--

LOCK TABLES `production_orders` WRITE;
/*!40000 ALTER TABLE `production_orders` DISABLE KEYS */;
/*!40000 ALTER TABLE `production_orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `production_routes`
--

DROP TABLE IF EXISTS `production_routes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `production_routes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int unsigned NOT NULL,
  `work_center_id` int unsigned NOT NULL,
  `operation_sequence` int NOT NULL,
  `operation_description` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `setup_time_minutes` int DEFAULT '0',
  `run_time_per_unit_seconds` decimal(8,2) NOT NULL,
  `teardown_time_minutes` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_product_sequence` (`product_id`,`operation_sequence`),
  KEY `idx_route_work_center` (`work_center_id`),
  CONSTRAINT `production_routes_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `production_routes_ibfk_2` FOREIGN KEY (`work_center_id`) REFERENCES `work_centers` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `production_routes`
--

LOCK TABLES `production_routes` WRITE;
/*!40000 ALTER TABLE `production_routes` DISABLE KEYS */;
/*!40000 ALTER TABLE `production_routes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `products` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `product_code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `category_id` int unsigned DEFAULT NULL,
  `uom_id` int unsigned NOT NULL,
  `weight_kg` decimal(10,4) DEFAULT NULL,
  `cycle_time_seconds` int DEFAULT NULL,
  `cavity_count` int DEFAULT '1',
  `min_stock_qty` decimal(15,4) DEFAULT '0.0000',
  `max_stock_qty` decimal(15,4) DEFAULT '0.0000',
  `safety_stock_qty` decimal(15,4) DEFAULT '0.0000',
  `lead_time_days` int DEFAULT '0',
  `standard_cost` decimal(15,4) DEFAULT '0.0000',
  `selling_price` decimal(15,4) DEFAULT '0.0000',
  `is_lot_controlled` tinyint(1) DEFAULT '1',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_code` (`product_code`),
  KEY `category_id` (`category_id`),
  KEY `uom_id` (`uom_id`),
  KEY `idx_product_active` (`is_active`),
  KEY `idx_product_deleted` (`deleted_at`),
  CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `product_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `products_ibfk_2` FOREIGN KEY (`uom_id`) REFERENCES `units_of_measure` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` (`id`, `product_code`, `name`, `description`, `category_id`, `uom_id`, `weight_kg`, `cycle_time_seconds`, `cavity_count`, `min_stock_qty`, `max_stock_qty`, `safety_stock_qty`, `lead_time_days`, `standard_cost`, `selling_price`, `is_lot_controlled`, `is_active`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,'PROD-001','Test Container','Simple test container',1,7,0.1250,45,4,50.0000,200.0000,25.0000,0,5.5000,12.9900,1,1,'2025-08-19 14:16:07','2025-08-19 14:16:07',NULL),(2,'PROD-002','Test Complex Product','Multi-material product',1,7,1.2500,180,1,20.0000,100.0000,10.0000,0,18.7500,49.9900,1,1,'2025-08-19 14:16:07','2025-08-19 14:16:07',NULL);
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `storage_locations`
--

DROP TABLE IF EXISTS `storage_locations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `storage_locations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `warehouse_id` int unsigned NOT NULL,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location_type` enum('raw_material','wip','finished_goods','quarantine') COLLATE utf8mb4_unicode_ci DEFAULT 'raw_material',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_location` (`warehouse_id`,`code`),
  KEY `idx_location_type` (`location_type`),
  KEY `idx_location_active` (`is_active`),
  CONSTRAINT `storage_locations_ibfk_1` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `storage_locations`
--

LOCK TABLES `storage_locations` WRITE;
/*!40000 ALTER TABLE `storage_locations` DISABLE KEYS */;
INSERT INTO `storage_locations` (`id`, `warehouse_id`, `code`, `description`, `location_type`, `is_active`, `created_at`, `updated_at`) VALUES (1,1,'RM-01','Raw Material Storage 1','raw_material',1,'2025-08-19 14:13:10','2025-08-19 14:13:10'),(2,1,'RM-02','Raw Material Storage 2','raw_material',1,'2025-08-19 14:13:10','2025-08-19 14:13:10'),(3,1,'WIP-01','Work in Progress Area 1','wip',1,'2025-08-19 14:13:10','2025-08-19 14:13:10'),(4,1,'FG-01','Finished Goods Storage 1','finished_goods',1,'2025-08-19 14:13:10','2025-08-19 14:13:10'),(5,1,'QC-01','Quality Control Quarantine','quarantine',1,'2025-08-19 14:13:10','2025-08-19 14:13:10');
/*!40000 ALTER TABLE `storage_locations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `suppliers`
--

DROP TABLE IF EXISTS `suppliers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `suppliers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_person` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `payment_terms` int DEFAULT '30' COMMENT 'Days',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_supplier_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `suppliers`
--

LOCK TABLES `suppliers` WRITE;
/*!40000 ALTER TABLE `suppliers` DISABLE KEYS */;
INSERT INTO `suppliers` (`id`, `code`, `name`, `contact_person`, `email`, `phone`, `address`, `payment_terms`, `is_active`, `created_at`, `updated_at`) VALUES (1,'SUP001','Test Supplier 1','John Smith','john@test.com','555-1234',NULL,30,1,'2025-08-19 14:15:37','2025-08-19 14:15:37'),(2,'SUP002','Test Supplier 2','Jane Doe','jane@test.com','555-5678',NULL,45,1,'2025-08-19 14:15:37','2025-08-19 14:15:37');
/*!40000 ALTER TABLE `suppliers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `units_of_measure`
--

DROP TABLE IF EXISTS `units_of_measure`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `units_of_measure` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('weight','volume','count','length','area') COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_uom_type` (`type`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `units_of_measure`
--

LOCK TABLES `units_of_measure` WRITE;
/*!40000 ALTER TABLE `units_of_measure` DISABLE KEYS */;
INSERT INTO `units_of_measure` (`id`, `code`, `description`, `type`, `created_at`, `updated_at`) VALUES (1,'KG','Kilogram','weight','2025-08-19 14:13:10','2025-08-19 14:13:10'),(2,'G','Gram','weight','2025-08-19 14:13:10','2025-08-19 14:13:10'),(3,'LB','Pound','weight','2025-08-19 14:13:10','2025-08-19 14:13:10'),(4,'L','Liter','volume','2025-08-19 14:13:10','2025-08-19 14:13:10'),(5,'ML','Milliliter','volume','2025-08-19 14:13:10','2025-08-19 14:13:10'),(6,'GAL','Gallon','volume','2025-08-19 14:13:10','2025-08-19 14:13:10'),(7,'PC','Piece','count','2025-08-19 14:13:10','2025-08-19 14:13:10'),(8,'EA','Each','count','2025-08-19 14:13:10','2025-08-19 14:13:10'),(9,'BOX','Box','count','2025-08-19 14:13:10','2025-08-19 14:13:10'),(10,'CASE','Case','count','2025-08-19 14:13:10','2025-08-19 14:13:10'),(11,'M','Meter','length','2025-08-19 14:13:10','2025-08-19 14:13:10'),(12,'CM','Centimeter','length','2025-08-19 14:13:10','2025-08-19 14:13:10'),(13,'IN','Inch','length','2025-08-19 14:13:10','2025-08-19 14:13:10'),(14,'FT','Feet','length','2025-08-19 14:13:10','2025-08-19 14:13:10'),(15,'SQM','Square Meter','area','2025-08-19 14:13:10','2025-08-19 14:13:10'),(16,'SQFT','Square Feet','area','2025-08-19 14:13:10','2025-08-19 14:13:10');
/*!40000 ALTER TABLE `units_of_measure` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Temporary view structure for view `v_current_inventory`
--

DROP TABLE IF EXISTS `v_current_inventory`;
/*!50001 DROP VIEW IF EXISTS `v_current_inventory`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v_current_inventory` AS SELECT 
 1 AS `item_type`,
 1 AS `item_id`,
 1 AS `item_code`,
 1 AS `item_name`,
 1 AS `available_quantity`,
 1 AS `total_quantity`,
 1 AS `reserved_quantity`,
 1 AS `uom_code`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `v_production_schedule`
--

DROP TABLE IF EXISTS `v_production_schedule`;
/*!50001 DROP VIEW IF EXISTS `v_production_schedule`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v_production_schedule` AS SELECT 
 1 AS `id`,
 1 AS `order_number`,
 1 AS `product_id`,
 1 AS `product_code`,
 1 AS `product_name`,
 1 AS `quantity_ordered`,
 1 AS `quantity_completed`,
 1 AS `scheduled_start_date`,
 1 AS `scheduled_end_date`,
 1 AS `priority_level`,
 1 AS `status`,
 1 AS `total_operations`,
 1 AS `completed_operations`,
 1 AS `completion_percentage`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `v_work_center_capacity`
--

DROP TABLE IF EXISTS `v_work_center_capacity`;
/*!50001 DROP VIEW IF EXISTS `v_work_center_capacity`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v_work_center_capacity` AS SELECT 
 1 AS `id`,
 1 AS `code`,
 1 AS `name`,
 1 AS `capacity_units_per_hour`,
 1 AS `date`,
 1 AS `shift_start`,
 1 AS `shift_end`,
 1 AS `available_hours`,
 1 AS `planned_downtime_hours`,
 1 AS `effective_hours`,
 1 AS `daily_capacity`*/;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `warehouses`
--

DROP TABLE IF EXISTS `warehouses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `warehouses` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_warehouse_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `warehouses`
--

LOCK TABLES `warehouses` WRITE;
/*!40000 ALTER TABLE `warehouses` DISABLE KEYS */;
INSERT INTO `warehouses` (`id`, `code`, `name`, `address`, `is_active`, `created_at`, `updated_at`) VALUES (1,'MAIN','Main Warehouse','123 Industrial Ave',1,'2025-08-19 14:13:10','2025-08-19 14:13:10');
/*!40000 ALTER TABLE `warehouses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `work_center_calendar`
--

DROP TABLE IF EXISTS `work_center_calendar`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `work_center_calendar` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `work_center_id` int unsigned NOT NULL,
  `date` date NOT NULL,
  `shift_start` time NOT NULL,
  `shift_end` time NOT NULL,
  `available_hours` decimal(4,2) NOT NULL,
  `planned_downtime_hours` decimal(4,2) DEFAULT '0.00',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_work_center_date_shift` (`work_center_id`,`date`,`shift_start`),
  KEY `idx_calendar_date` (`date`),
  CONSTRAINT `work_center_calendar_ibfk_1` FOREIGN KEY (`work_center_id`) REFERENCES `work_centers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=128 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `work_center_calendar`
--

LOCK TABLES `work_center_calendar` WRITE;
/*!40000 ALTER TABLE `work_center_calendar` DISABLE KEYS */;
INSERT INTO `work_center_calendar` (`id`, `work_center_id`, `date`, `shift_start`, `shift_end`, `available_hours`, `planned_downtime_hours`, `notes`, `created_at`, `updated_at`) VALUES (1,5,'2025-08-19','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(2,4,'2025-08-19','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(3,3,'2025-08-19','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(4,2,'2025-08-19','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(5,1,'2025-08-19','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(6,5,'2025-08-20','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(7,4,'2025-08-20','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(8,3,'2025-08-20','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(9,2,'2025-08-20','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(10,1,'2025-08-20','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(11,5,'2025-08-21','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(12,4,'2025-08-21','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(13,3,'2025-08-21','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(14,2,'2025-08-21','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(15,1,'2025-08-21','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(16,5,'2025-08-22','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(17,4,'2025-08-22','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(18,3,'2025-08-22','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(19,2,'2025-08-22','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(20,1,'2025-08-22','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(21,5,'2025-08-26','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(22,4,'2025-08-26','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(23,3,'2025-08-26','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(24,2,'2025-08-26','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(25,1,'2025-08-26','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(26,5,'2025-08-27','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(27,4,'2025-08-27','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(28,3,'2025-08-27','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(29,2,'2025-08-27','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(30,1,'2025-08-27','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(31,5,'2025-08-28','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(32,4,'2025-08-28','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(33,3,'2025-08-28','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(34,2,'2025-08-28','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(35,1,'2025-08-28','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(36,5,'2025-08-29','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(37,4,'2025-08-29','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(38,3,'2025-08-29','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(39,2,'2025-08-29','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(40,1,'2025-08-29','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(41,5,'2025-09-02','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(42,4,'2025-09-02','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(43,3,'2025-09-02','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(44,2,'2025-09-02','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(45,1,'2025-09-02','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(46,5,'2025-09-03','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(47,4,'2025-09-03','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(48,3,'2025-09-03','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(49,2,'2025-09-03','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(50,1,'2025-09-03','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(51,5,'2025-09-04','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(52,4,'2025-09-04','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(53,3,'2025-09-04','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(54,2,'2025-09-04','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(55,1,'2025-09-04','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(56,5,'2025-09-05','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(57,4,'2025-09-05','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(58,3,'2025-09-05','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(59,2,'2025-09-05','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(60,1,'2025-09-05','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(61,5,'2025-09-09','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(62,4,'2025-09-09','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(63,3,'2025-09-09','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(64,2,'2025-09-09','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(65,1,'2025-09-09','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(66,5,'2025-09-10','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(67,4,'2025-09-10','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(68,3,'2025-09-10','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(69,2,'2025-09-10','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(70,1,'2025-09-10','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(71,5,'2025-09-11','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(72,4,'2025-09-11','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(73,3,'2025-09-11','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(74,2,'2025-09-11','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(75,1,'2025-09-11','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(76,5,'2025-09-12','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(77,4,'2025-09-12','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(78,3,'2025-09-12','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(79,2,'2025-09-12','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(80,1,'2025-09-12','08:00:00','17:00:00',8.00,1.00,NULL,'2025-08-19 14:13:11','2025-08-19 14:13:11');
/*!40000 ALTER TABLE `work_center_calendar` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `work_centers`
--

DROP TABLE IF EXISTS `work_centers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `work_centers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `location` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `work_center_type` enum('machine','assembly','packaging','quality','other') COLLATE utf8mb4_unicode_ci NOT NULL,
  `capacity_units_per_hour` decimal(10,2) DEFAULT '0.00',
  `setup_time_minutes` int DEFAULT '0',
  `teardown_time_minutes` int DEFAULT '0',
  `efficiency_percentage` decimal(5,2) DEFAULT '100.00',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_work_center_type` (`work_center_type`),
  KEY `idx_work_center_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `work_centers`
--

LOCK TABLES `work_centers` WRITE;
/*!40000 ALTER TABLE `work_centers` DISABLE KEYS */;
INSERT INTO `work_centers` (`id`, `code`, `name`, `description`, `location`, `work_center_type`, `capacity_units_per_hour`, `setup_time_minutes`, `teardown_time_minutes`, `efficiency_percentage`, `is_active`, `created_at`, `updated_at`) VALUES (1,'INJ-01','Injection Molding Machine 1','100-ton injection molding machine',NULL,'machine',240.00,30,0,85.00,1,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(2,'INJ-02','Injection Molding Machine 2','150-ton injection molding machine',NULL,'machine',200.00,45,0,90.00,1,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(3,'ASM-01','Assembly Station 1','Manual assembly workstation',NULL,'assembly',50.00,10,0,95.00,1,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(4,'PKG-01','Packaging Line 1','Automated packaging line',NULL,'packaging',300.00,15,0,92.00,1,'2025-08-19 14:13:11','2025-08-19 14:13:11'),(5,'QC-01','Quality Control Station','Inspection and testing station',NULL,'quality',100.00,5,0,98.00,1,'2025-08-19 14:13:11','2025-08-19 14:13:11');
/*!40000 ALTER TABLE `work_centers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'mrp_erp'
--

--
-- Dumping routines for database 'mrp_erp'
--

--
-- Current Database: `mrp_erp`
--

USE `mrp_erp`;

--
-- Final view structure for view `v_current_inventory`
--

/*!50001 DROP VIEW IF EXISTS `v_current_inventory`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_current_inventory` AS select `i`.`item_type` AS `item_type`,`i`.`item_id` AS `item_id`,(case when (`i`.`item_type` = 'material') then `m`.`material_code` when (`i`.`item_type` = 'product') then `p`.`product_code` end) AS `item_code`,(case when (`i`.`item_type` = 'material') then `m`.`name` when (`i`.`item_type` = 'product') then `p`.`name` end) AS `item_name`,sum((`i`.`quantity` - `i`.`reserved_quantity`)) AS `available_quantity`,sum(`i`.`quantity`) AS `total_quantity`,sum(`i`.`reserved_quantity`) AS `reserved_quantity`,`uom`.`code` AS `uom_code` from (((`inventory` `i` left join `materials` `m` on(((`i`.`item_type` = 'material') and (`i`.`item_id` = `m`.`id`)))) left join `products` `p` on(((`i`.`item_type` = 'product') and (`i`.`item_id` = `p`.`id`)))) left join `units_of_measure` `uom` on((`i`.`uom_id` = `uom`.`id`))) where (`i`.`status` = 'available') group by `i`.`item_type`,`i`.`item_id`,`item_code`,`item_name`,`uom`.`code` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_production_schedule`
--

/*!50001 DROP VIEW IF EXISTS `v_production_schedule`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_production_schedule` AS select `po`.`id` AS `id`,`po`.`order_number` AS `order_number`,`po`.`product_id` AS `product_id`,`p`.`product_code` AS `product_code`,`p`.`name` AS `product_name`,`po`.`quantity_ordered` AS `quantity_ordered`,`po`.`quantity_completed` AS `quantity_completed`,`po`.`scheduled_start_date` AS `scheduled_start_date`,`po`.`scheduled_end_date` AS `scheduled_end_date`,`po`.`priority_level` AS `priority_level`,`po`.`status` AS `status`,count(`poo`.`id`) AS `total_operations`,sum((case when (`poo`.`status` = 'completed') then 1 else 0 end)) AS `completed_operations`,round(((sum((case when (`poo`.`status` = 'completed') then 1 else 0 end)) / count(`poo`.`id`)) * 100),1) AS `completion_percentage` from ((`production_orders` `po` left join `products` `p` on((`po`.`product_id` = `p`.`id`))) left join `production_order_operations` `poo` on((`po`.`id` = `poo`.`production_order_id`))) where (`po`.`status` not in ('completed','cancelled')) group by `po`.`id`,`po`.`order_number`,`po`.`product_id`,`p`.`product_code`,`p`.`name`,`po`.`quantity_ordered`,`po`.`quantity_completed`,`po`.`scheduled_start_date`,`po`.`scheduled_end_date`,`po`.`priority_level`,`po`.`status` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_work_center_capacity`
--

/*!50001 DROP VIEW IF EXISTS `v_work_center_capacity`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_work_center_capacity` AS select `wc`.`id` AS `id`,`wc`.`code` AS `code`,`wc`.`name` AS `name`,`wc`.`capacity_units_per_hour` AS `capacity_units_per_hour`,`wcc`.`date` AS `date`,`wcc`.`shift_start` AS `shift_start`,`wcc`.`shift_end` AS `shift_end`,`wcc`.`available_hours` AS `available_hours`,`wcc`.`planned_downtime_hours` AS `planned_downtime_hours`,(`wcc`.`available_hours` - `wcc`.`planned_downtime_hours`) AS `effective_hours`,(`wc`.`capacity_units_per_hour` * (`wcc`.`available_hours` - `wcc`.`planned_downtime_hours`)) AS `daily_capacity` from (`work_centers` `wc` left join `work_center_calendar` `wcc` on((`wc`.`id` = `wcc`.`work_center_id`))) where (`wc`.`is_active` = true) */;
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

-- Dump completed on 2025-08-19 15:03:03
