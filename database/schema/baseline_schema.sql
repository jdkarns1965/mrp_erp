
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
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
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
DROP TABLE IF EXISTS `bom_headers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bom_headers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int unsigned NOT NULL,
  `version` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1.0',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `effective_date` date NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `approved_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
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
DROP TABLE IF EXISTS `customer_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `customer_orders` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `order_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_date` date NOT NULL,
  `required_date` date NOT NULL,
  `status` enum('pending','confirmed','in_production','completed','shipped','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_number` (`order_number`),
  KEY `idx_order_status` (`status`),
  KEY `idx_order_dates` (`order_date`,`required_date`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inventory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `item_type` enum('material','product') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `item_id` int unsigned NOT NULL COMMENT 'References either materials.id or products.id',
  `lot_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `location_id` int unsigned NOT NULL,
  `quantity` decimal(15,4) NOT NULL,
  `reserved_quantity` decimal(15,4) DEFAULT '0.0000',
  `uom_id` int unsigned NOT NULL,
  `manufacture_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `received_date` date NOT NULL,
  `supplier_id` int unsigned DEFAULT NULL,
  `po_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit_cost` decimal(15,4) DEFAULT NULL,
  `status` enum('available','reserved','quarantine','expired','consumed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'available',
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
DROP TABLE IF EXISTS `inventory_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory_transactions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `transaction_type` enum('receipt','issue','adjustment','transfer','return','scrap') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `transaction_date` datetime NOT NULL,
  `item_type` enum('material','product') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `item_id` int unsigned NOT NULL,
  `lot_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `from_location_id` int unsigned DEFAULT NULL,
  `to_location_id` int unsigned DEFAULT NULL,
  `quantity` decimal(15,4) NOT NULL,
  `uom_id` int unsigned NOT NULL,
  `reference_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'PO, Production Order, Sales Order, etc.',
  `reference_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `performed_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  `status` enum('draft','firm','released','completed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'draft',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_mps` (`product_id`,`period_id`),
  KEY `idx_mps_status` (`status`),
  KEY `idx_mps_period` (`period_id`),
  CONSTRAINT `master_production_schedule_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `master_production_schedule_ibfk_2` FOREIGN KEY (`period_id`) REFERENCES `planning_calendar` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `material_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `material_categories` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `materials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `materials` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `material_code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `category_id` int unsigned DEFAULT NULL,
  `material_type` enum('resin','insert','packaging','component','consumable','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
DROP TABLE IF EXISTS `planning_calendar`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `planning_calendar` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `period_type` enum('daily','weekly','monthly') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'weekly',
  `period_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_working_period` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_period` (`period_start`,`period_end`),
  KEY `idx_period_dates` (`period_start`,`period_end`),
  KEY `idx_period_type` (`period_type`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_categories` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
  `lot_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `issue_date` datetime DEFAULT NULL,
  `issued_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `material_id` (`material_id`),
  KEY `idx_po_material` (`production_order_id`,`material_id`),
  CONSTRAINT `production_order_materials_ibfk_1` FOREIGN KEY (`production_order_id`) REFERENCES `production_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `production_order_materials_ibfk_2` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
  `status` enum('planned','ready','in_progress','completed','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'planned',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `operator_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
DROP TABLE IF EXISTS `production_order_status_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `production_order_status_history` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `production_order_id` int unsigned NOT NULL,
  `old_status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `changed_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `changed_at` datetime NOT NULL,
  `reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status_history_order` (`production_order_id`),
  KEY `idx_status_history_date` (`changed_at`),
  CONSTRAINT `production_order_status_history_ibfk_1` FOREIGN KEY (`production_order_id`) REFERENCES `production_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `production_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `production_orders` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `order_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_order_id` int unsigned DEFAULT NULL,
  `product_id` int unsigned NOT NULL,
  `quantity_ordered` decimal(15,4) NOT NULL,
  `quantity_completed` decimal(15,4) DEFAULT '0.0000',
  `quantity_scrapped` decimal(15,4) DEFAULT '0.0000',
  `scheduled_start_date` date DEFAULT NULL,
  `scheduled_end_date` date DEFAULT NULL,
  `actual_start_date` date DEFAULT NULL,
  `actual_end_date` date DEFAULT NULL,
  `priority_level` enum('low','normal','high','urgent') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'normal',
  `status` enum('planned','released','in_progress','completed','cancelled','on_hold') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'planned',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
DROP TABLE IF EXISTS `production_routes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `production_routes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int unsigned NOT NULL,
  `work_center_id` int unsigned NOT NULL,
  `operation_sequence` int NOT NULL,
  `operation_description` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `products` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `product_code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
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
DROP TABLE IF EXISTS `storage_locations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `storage_locations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `warehouse_id` int unsigned NOT NULL,
  `code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location_type` enum('raw_material','wip','finished_goods','quarantine') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'raw_material',
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
DROP TABLE IF EXISTS `suppliers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `suppliers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_person` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `payment_terms` int DEFAULT '30' COMMENT 'Days',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_supplier_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `units_of_measure`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `units_of_measure` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('weight','volume','count','length','area') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_uom_type` (`type`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
DROP TABLE IF EXISTS `warehouses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `warehouses` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_warehouse_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_work_center_date_shift` (`work_center_id`,`date`,`shift_start`),
  KEY `idx_calendar_date` (`date`),
  CONSTRAINT `work_center_calendar_ibfk_1` FOREIGN KEY (`work_center_id`) REFERENCES `work_centers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=128 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `work_centers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `work_centers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `location` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `work_center_type` enum('machine','assembly','packaging','quality','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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

