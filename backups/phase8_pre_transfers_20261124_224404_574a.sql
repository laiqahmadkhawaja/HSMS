-- HSMS Database Automated Backup
-- Generated: 2026-11-24 22:44:04
-- Database: hsms_db

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `accounts`;
CREATE TABLE `accounts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `society_id` int(10) unsigned DEFAULT NULL,
  `account_code` varchar(30) NOT NULL,
  `account_name` varchar(150) NOT NULL,
  `account_type` enum('asset','liability','equity','income','expense') NOT NULL,
  `sub_type` varchar(50) DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `account_number` varchar(50) DEFAULT NULL,
  `opening_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `current_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `account_code` (`account_code`),
  KEY `fk_acc_society` (`society_id`),
  CONSTRAINT `fk_acc_society` FOREIGN KEY (`society_id`) REFERENCES `societies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `accounts` (`id`, `society_id`, `account_code`, `account_name`, `account_type`, `sub_type`, `bank_name`, `account_number`, `opening_balance`, `current_balance`, `is_system`, `status`, `created_at`) VALUES ('1', NULL, '1001', 'Cash on Hand - Main Office', 'asset', 'cash', NULL, NULL, '0.00', '620000.00', '1', 'active', '2026-11-24 22:02:51');
INSERT INTO `accounts` (`id`, `society_id`, `account_code`, `account_name`, `account_type`, `sub_type`, `bank_name`, `account_number`, `opening_balance`, `current_balance`, `is_system`, `status`, `created_at`) VALUES ('2', NULL, '1002', 'Cash on Hand - Recovery Vault', 'asset', 'cash', NULL, NULL, '0.00', '0.00', '1', 'active', '2026-11-24 22:02:51');
INSERT INTO `accounts` (`id`, `society_id`, `account_code`, `account_name`, `account_type`, `sub_type`, `bank_name`, `account_number`, `opening_balance`, `current_balance`, `is_system`, `status`, `created_at`) VALUES ('3', NULL, '1010', 'HBL Collection Account', 'asset', 'bank', NULL, NULL, '0.00', '0.00', '1', 'active', '2026-11-24 22:02:51');
INSERT INTO `accounts` (`id`, `society_id`, `account_code`, `account_name`, `account_type`, `sub_type`, `bank_name`, `account_number`, `opening_balance`, `current_balance`, `is_system`, `status`, `created_at`) VALUES ('4', NULL, '1011', 'Meezan Bank Operations Account', 'asset', 'bank', NULL, NULL, '0.00', '0.00', '1', 'active', '2026-11-24 22:02:51');
INSERT INTO `accounts` (`id`, `society_id`, `account_code`, `account_name`, `account_type`, `sub_type`, `bank_name`, `account_number`, `opening_balance`, `current_balance`, `is_system`, `status`, `created_at`) VALUES ('5', NULL, '1020', 'Customer Plot Receivables', 'asset', 'accounts_receivable', NULL, NULL, '0.00', '-620000.00', '1', 'active', '2026-11-24 22:02:51');
INSERT INTO `accounts` (`id`, `society_id`, `account_code`, `account_name`, `account_type`, `sub_type`, `bank_name`, `account_number`, `opening_balance`, `current_balance`, `is_system`, `status`, `created_at`) VALUES ('6', NULL, '2001', 'Customer Advance Liabilities', 'liability', 'advances', NULL, NULL, '0.00', '0.00', '1', 'active', '2026-11-24 22:02:51');
INSERT INTO `accounts` (`id`, `society_id`, `account_code`, `account_name`, `account_type`, `sub_type`, `bank_name`, `account_number`, `opening_balance`, `current_balance`, `is_system`, `status`, `created_at`) VALUES ('7', NULL, '2010', 'Customer Refund Payables', 'liability', 'payables', NULL, NULL, '0.00', '0.00', '1', 'active', '2026-11-24 22:02:51');
INSERT INTO `accounts` (`id`, `society_id`, `account_code`, `account_name`, `account_type`, `sub_type`, `bank_name`, `account_number`, `opening_balance`, `current_balance`, `is_system`, `status`, `created_at`) VALUES ('8', NULL, '3001', 'Developer Owner Capital', 'equity', 'capital', NULL, NULL, '0.00', '0.00', '1', 'active', '2026-11-24 22:02:51');
INSERT INTO `accounts` (`id`, `society_id`, `account_code`, `account_name`, `account_type`, `sub_type`, `bank_name`, `account_number`, `opening_balance`, `current_balance`, `is_system`, `status`, `created_at`) VALUES ('9', NULL, '3010', 'Retained Earnings', 'equity', 'retained_earnings', NULL, NULL, '0.00', '0.00', '1', 'active', '2026-11-24 22:02:51');
INSERT INTO `accounts` (`id`, `society_id`, `account_code`, `account_name`, `account_type`, `sub_type`, `bank_name`, `account_number`, `opening_balance`, `current_balance`, `is_system`, `status`, `created_at`) VALUES ('10', NULL, '4001', 'Plot Sales Revenue', 'income', 'sales_income', NULL, NULL, '0.00', '0.00', '1', 'active', '2026-11-24 22:02:51');
INSERT INTO `accounts` (`id`, `society_id`, `account_code`, `account_name`, `account_type`, `sub_type`, `bank_name`, `account_number`, `opening_balance`, `current_balance`, `is_system`, `status`, `created_at`) VALUES ('11', NULL, '4002', 'Development Charges Income', 'income', 'dev_charges', NULL, NULL, '0.00', '0.00', '1', 'active', '2026-11-24 22:02:51');
INSERT INTO `accounts` (`id`, `society_id`, `account_code`, `account_name`, `account_type`, `sub_type`, `bank_name`, `account_number`, `opening_balance`, `current_balance`, `is_system`, `status`, `created_at`) VALUES ('12', NULL, '4003', 'Plot Transfer Fee Income', 'income', 'fees', NULL, NULL, '0.00', '0.00', '1', 'active', '2026-11-24 22:02:51');
INSERT INTO `accounts` (`id`, `society_id`, `account_code`, `account_name`, `account_type`, `sub_type`, `bank_name`, `account_number`, `opening_balance`, `current_balance`, `is_system`, `status`, `created_at`) VALUES ('13', NULL, '4004', 'Cancellation Charges Income', 'income', 'fees', NULL, NULL, '0.00', '0.00', '1', 'active', '2026-11-24 22:02:51');
INSERT INTO `accounts` (`id`, `society_id`, `account_code`, `account_name`, `account_type`, `sub_type`, `bank_name`, `account_number`, `opening_balance`, `current_balance`, `is_system`, `status`, `created_at`) VALUES ('14', NULL, '4005', 'Late Payment Surcharges', 'income', 'fees', NULL, NULL, '0.00', '0.00', '1', 'active', '2026-11-24 22:02:51');
INSERT INTO `accounts` (`id`, `society_id`, `account_code`, `account_name`, `account_type`, `sub_type`, `bank_name`, `account_number`, `opening_balance`, `current_balance`, `is_system`, `status`, `created_at`) VALUES ('15', NULL, '5001', 'Sales Discounts Allowed', 'expense', 'discount', NULL, NULL, '0.00', '0.00', '1', 'active', '2026-11-24 22:02:51');
INSERT INTO `accounts` (`id`, `society_id`, `account_code`, `account_name`, `account_type`, `sub_type`, `bank_name`, `account_number`, `opening_balance`, `current_balance`, `is_system`, `status`, `created_at`) VALUES ('16', NULL, '5002', 'Office Staff Salaries', 'expense', 'salaries', NULL, NULL, '0.00', '0.00', '1', 'active', '2026-11-24 22:02:51');
INSERT INTO `accounts` (`id`, `society_id`, `account_code`, `account_name`, `account_type`, `sub_type`, `bank_name`, `account_number`, `opening_balance`, `current_balance`, `is_system`, `status`, `created_at`) VALUES ('17', NULL, '5003', 'Site Marketing & Advertising', 'expense', 'marketing', NULL, NULL, '0.00', '0.00', '1', 'active', '2026-11-24 22:02:51');
INSERT INTO `accounts` (`id`, `society_id`, `account_code`, `account_name`, `account_type`, `sub_type`, `bank_name`, `account_number`, `opening_balance`, `current_balance`, `is_system`, `status`, `created_at`) VALUES ('18', NULL, '5004', 'Office Utilities & Electricity', 'expense', 'utilities', NULL, NULL, '0.00', '0.00', '1', 'active', '2026-11-24 22:02:51');
INSERT INTO `accounts` (`id`, `society_id`, `account_code`, `account_name`, `account_type`, `sub_type`, `bank_name`, `account_number`, `opening_balance`, `current_balance`, `is_system`, `status`, `created_at`) VALUES ('19', NULL, '5005', 'Earthwork & Development Expenses', 'expense', 'site_work', NULL, NULL, '0.00', '0.00', '1', 'active', '2026-11-24 22:02:51');
INSERT INTO `accounts` (`id`, `society_id`, `account_code`, `account_name`, `account_type`, `sub_type`, `bank_name`, `account_number`, `opening_balance`, `current_balance`, `is_system`, `status`, `created_at`) VALUES ('20', NULL, '5006', 'Legal & Registration Fees', 'expense', 'legal', NULL, NULL, '0.00', '0.00', '1', 'active', '2026-11-24 22:02:51');

DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `society_id` int(10) unsigned DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `module` varchar(50) NOT NULL,
  `record_id` int(10) unsigned DEFAULT NULL,
  `old_values` longtext DEFAULT NULL,
  `new_values` longtext DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audit_search` (`module`,`record_id`,`created_at`),
  KEY `fk_al_user` (`user_id`),
  KEY `fk_al_society` (`society_id`),
  CONSTRAINT `fk_al_society` FOREIGN KEY (`society_id`) REFERENCES `societies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_al_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `audit_logs` (`id`, `user_id`, `society_id`, `action`, `module`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES ('1', '1', '1', 'USER_LOGIN', 'auth', '1', NULL, NULL, '::1', 'CLI', '2026-11-24 22:08:10');
INSERT INTO `audit_logs` (`id`, `user_id`, `society_id`, `action`, `module`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES ('2', '1', '1', 'PLOT_CREATED', 'plots', '1', NULL, '{\"plot_number\":\"TEST-P2-01\",\"total_price\":2950000}', '::1', 'CLI', '2026-11-24 22:13:02');
INSERT INTO `audit_logs` (`id`, `user_id`, `society_id`, `action`, `module`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES ('3', '1', '1', 'PLOT_UPDATED', 'plots', '1', '{\"id\":1,\"society_id\":1,\"phase_id\":1,\"block_id\":1,\"plot_type_id\":1,\"plot_size_id\":2,\"plot_number\":\"TEST-P2-01\",\"street_road\":\"Boulevard North\",\"sector_zone\":\"Zone 1\",\"is_corner\":1,\"is_boulevard\":0,\"is_park_facing\":0,\"base_price\":\"2500000.00\",\"development_charges\":\"400000.00\",\"additional_charges\":\"100000.00\",\"discount_allowed\":\"50000.00\",\"total_price\":\"2950000.00\",\"status\":\"available\",\"remarks\":\"Test automated plot\",\"created_at\":\"2026-11-24 22:13:02\",\"updated_at\":\"2026-11-24 22:13:02\",\"phase_name\":\"Phase 1 - Executive\",\"block_name\":\"Block A - Rose\"}', '{\"plot_number\":\"TEST-P2-01\",\"total_price\":3150000}', '::1', 'CLI', '2026-11-24 22:13:02');
INSERT INTO `audit_logs` (`id`, `user_id`, `society_id`, `action`, `module`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES ('4', '1', '1', 'PLOTS_BULK_IMPORTED', 'plots', NULL, NULL, '{\"count\":2}', '::1', 'CLI', '2026-11-24 22:13:03');
INSERT INTO `audit_logs` (`id`, `user_id`, `society_id`, `action`, `module`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES ('5', '1', '1', 'CUSTOMER_REGISTERED', 'customers', '1', NULL, '{\"name\":\"Muhammad Asif\",\"cnic\":\"37405-1122334-1\",\"code\":\"CUST-0001\"}', '::1', 'CLI', '2026-11-24 22:16:36');
INSERT INTO `audit_logs` (`id`, `user_id`, `society_id`, `action`, `module`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES ('6', '1', '1', 'CUSTOMER_DOC_UPLOADED', 'customers', '1', NULL, '{\"doc_type\":\"CNIC Front\",\"file\":\"test_kyc_cnic.pdf\"}', '::1', 'CLI', '2026-11-24 22:16:36');
INSERT INTO `audit_logs` (`id`, `user_id`, `society_id`, `action`, `module`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES ('7', '1', '1', 'CUSTOMER_REGISTERED', 'customers', '2', NULL, '{\"name\":\"Muhammad Asif\",\"cnic\":\"37405-1122334-1\",\"code\":\"CUST-0001\"}', '::1', 'CLI', '2026-11-24 22:17:24');
INSERT INTO `audit_logs` (`id`, `user_id`, `society_id`, `action`, `module`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES ('8', '1', '1', 'CUSTOMER_DOC_UPLOADED', 'customers', '2', NULL, '{\"doc_type\":\"CNIC Front\",\"file\":\"test_kyc_cnic.pdf\"}', '::1', 'CLI', '2026-11-24 22:17:25');
INSERT INTO `audit_logs` (`id`, `user_id`, `society_id`, `action`, `module`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES ('9', NULL, NULL, 'DATABASE_BACKED_UP', 'backup', NULL, NULL, '{\"file\":\"phase5_pre_booking_20261124_222119_98ba.sql\"}', '127.0.0.1', 'CLI', '2026-11-24 22:21:20');
INSERT INTO `audit_logs` (`id`, `user_id`, `society_id`, `action`, `module`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES ('10', '1', '1', 'BOOKING_CREATED', 'bookings', '1', NULL, '{\"booking_number\":\"BK-2026-0001\",\"plot_number\":\"TEST-P2-01\",\"customer_id\":2,\"net_sale_price\":3100000}', '::1', 'CLI', '2026-11-24 22:26:07');
INSERT INTO `audit_logs` (`id`, `user_id`, `society_id`, `action`, `module`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES ('11', '1', '1', 'BOOKING_CREATED', 'bookings', '2', NULL, '{\"booking_number\":\"BK-2026-0001\",\"plot_number\":\"TEST-P2-01\",\"customer_id\":2,\"net_sale_price\":3100000}', '::1', 'CLI', '2026-11-24 22:27:29');
INSERT INTO `audit_logs` (`id`, `user_id`, `society_id`, `action`, `module`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES ('12', '1', '1', 'SCHEDULE_REVISED', 'bookings', '2', NULL, '{\"reason\":\"Phase 5 automated test schedule revision\",\"new_count\":12}', '::1', 'CLI', '2026-11-24 22:27:29');
INSERT INTO `audit_logs` (`id`, `user_id`, `society_id`, `action`, `module`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES ('13', '1', '1', 'PAYMENT_POSTED', 'recoveries', '1', NULL, '{\"receipt_number\":\"REC-GVR-01-2026-0001\",\"amount\":620000,\"allocated\":620000,\"advance\":0,\"booking_id\":2}', '::1', 'CLI', '2026-11-24 22:38:36');
INSERT INTO `audit_logs` (`id`, `user_id`, `society_id`, `action`, `module`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES ('14', '1', '1', 'PAYMENT_POSTED', 'recoveries', '2', NULL, '{\"receipt_number\":\"REC-GVR-01-2026-0002\",\"amount\":250000,\"allocated\":250000,\"advance\":0,\"booking_id\":2}', '::1', 'CLI', '2026-11-24 22:38:36');
INSERT INTO `audit_logs` (`id`, `user_id`, `society_id`, `action`, `module`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES ('15', '1', '1', 'PAYMENT_REVERSED', 'recoveries', '2', NULL, '{\"reason\":\"Online transfer bounce test reversal\"}', '::1', 'CLI', '2026-11-24 22:38:36');
INSERT INTO `audit_logs` (`id`, `user_id`, `society_id`, `action`, `module`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES ('16', '1', '1', 'RECOVERY_OFFICER_UPDATED', 'recovery_officers', '1', NULL, '{\"target\":7500000,\"blocks\":\"[\\\"BLK-A\\\",\\\"BLK-B\\\",\\\"BLK-C\\\"]\"}', '::1', 'CLI', '2026-11-24 22:43:22');
INSERT INTO `audit_logs` (`id`, `user_id`, `society_id`, `action`, `module`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES ('17', '1', '1', 'PTP_CREATED', 'promises_to_pay', '1', NULL, '{\"booking_id\":2,\"amount\":150000,\"due_date\":\"2026-11-29\"}', '::1', 'CLI', '2026-11-24 22:43:23');
INSERT INTO `audit_logs` (`id`, `user_id`, `society_id`, `action`, `module`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES ('18', '1', '1', 'PTP_STATUS_UPDATED', 'promises_to_pay', '1', NULL, '{\"previous_status\":\"pending\",\"new_status\":\"fulfilled\"}', '::1', 'CLI', '2026-11-24 22:43:23');
INSERT INTO `audit_logs` (`id`, `user_id`, `society_id`, `action`, `module`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES ('19', '1', '1', 'PTP_CREATED', 'promises_to_pay', '2', NULL, '{\"booking_id\":2,\"amount\":50000,\"due_date\":\"2026-11-22\"}', '::1', 'CLI', '2026-11-24 22:43:23');
INSERT INTO `audit_logs` (`id`, `user_id`, `society_id`, `action`, `module`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES ('20', '1', '1', 'PTP_STATUS_UPDATED', 'promises_to_pay', '2', NULL, '{\"previous_status\":\"pending\",\"new_status\":\"broken\"}', '::1', 'CLI', '2026-11-24 22:43:23');

DROP TABLE IF EXISTS `blocks`;
CREATE TABLE `blocks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `society_id` int(10) unsigned NOT NULL,
  `phase_id` int(10) unsigned NOT NULL,
  `block_name` varchar(100) NOT NULL,
  `block_code` varchar(30) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_phase_block` (`phase_id`,`block_name`),
  KEY `fk_block_society` (`society_id`),
  CONSTRAINT `fk_block_phase` FOREIGN KEY (`phase_id`) REFERENCES `phases` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_block_society` FOREIGN KEY (`society_id`) REFERENCES `societies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `blocks` (`id`, `society_id`, `phase_id`, `block_name`, `block_code`, `description`, `status`, `created_at`) VALUES ('1', '1', '1', 'Block A - Rose', 'BLK-A', 'Near main commercial boulevard and grand mosque', 'active', '2026-11-24 22:02:51');
INSERT INTO `blocks` (`id`, `society_id`, `phase_id`, `block_name`, `block_code`, `description`, `status`, `created_at`) VALUES ('2', '1', '1', 'Block B - Tulip', 'BLK-B', 'Adjacent to central park and sector school', 'active', '2026-11-24 22:02:51');
INSERT INTO `blocks` (`id`, `society_id`, `phase_id`, `block_name`, `block_code`, `description`, `status`, `created_at`) VALUES ('3', '1', '2', 'Block C - Jasmine', 'BLK-C', 'Overseas premium enclave with wide roads', 'active', '2026-11-24 22:02:51');

DROP TABLE IF EXISTS `bookings`;
CREATE TABLE `bookings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `booking_number` varchar(50) NOT NULL,
  `society_id` int(10) unsigned NOT NULL,
  `plot_id` int(10) unsigned NOT NULL,
  `customer_id` int(10) unsigned NOT NULL,
  `plan_template_id` int(10) unsigned DEFAULT NULL,
  `booking_date` date NOT NULL,
  `base_price` decimal(15,2) NOT NULL,
  `development_charges` decimal(15,2) NOT NULL DEFAULT 0.00,
  `extra_charges` decimal(15,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `net_sale_price` decimal(15,2) NOT NULL,
  `down_payment_amount` decimal(15,2) NOT NULL,
  `down_payment_status` enum('pending','partially_paid','paid') NOT NULL DEFAULT 'pending',
  `total_paid` decimal(15,2) NOT NULL DEFAULT 0.00,
  `remaining_balance` decimal(15,2) NOT NULL,
  `advance_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `sales_officer_id` int(10) unsigned DEFAULT NULL,
  `status` enum('active','transferred','cancelled','completed') NOT NULL DEFAULT 'active',
  `remarks` text DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `booking_number` (`booking_number`),
  KEY `idx_booking_soc_cust` (`society_id`,`customer_id`,`plot_id`,`status`),
  KEY `fk_bk_plot` (`plot_id`),
  KEY `fk_bk_customer` (`customer_id`),
  KEY `fk_bk_template` (`plan_template_id`),
  KEY `fk_bk_officer` (`sales_officer_id`),
  KEY `fk_bk_created_by` (`created_by`),
  CONSTRAINT `fk_bk_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_bk_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_bk_officer` FOREIGN KEY (`sales_officer_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_bk_plot` FOREIGN KEY (`plot_id`) REFERENCES `plots` (`id`),
  CONSTRAINT `fk_bk_society` FOREIGN KEY (`society_id`) REFERENCES `societies` (`id`),
  CONSTRAINT `fk_bk_template` FOREIGN KEY (`plan_template_id`) REFERENCES `installment_plan_templates` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `bookings` (`id`, `booking_number`, `society_id`, `plot_id`, `customer_id`, `plan_template_id`, `booking_date`, `base_price`, `development_charges`, `extra_charges`, `discount_amount`, `net_sale_price`, `down_payment_amount`, `down_payment_status`, `total_paid`, `remaining_balance`, `advance_balance`, `sales_officer_id`, `status`, `remarks`, `created_by`, `created_at`, `updated_at`) VALUES ('2', 'BK-2026-0001', '1', '1', '2', NULL, '2026-11-24', '2700000.00', '400000.00', '100000.00', '100000.00', '3100000.00', '620000.00', 'pending', '620000.00', '2480000.00', '0.00', NULL, 'active', 'Automated test sales contract', '1', '2026-11-24 22:27:29', '2026-11-24 22:38:36');

DROP TABLE IF EXISTS `cancellations`;
CREATE TABLE `cancellations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cancellation_number` varchar(50) NOT NULL,
  `booking_id` int(10) unsigned NOT NULL,
  `plot_id` int(10) unsigned NOT NULL,
  `customer_id` int(10) unsigned NOT NULL,
  `cancellation_date` date NOT NULL,
  `total_paid_at_cancel` decimal(15,2) NOT NULL,
  `deduction_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `refund_amount_payable` decimal(15,2) NOT NULL DEFAULT 0.00,
  `refund_paid_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `refund_status` enum('pending','partially_refunded','fully_refunded') NOT NULL DEFAULT 'pending',
  `approval_status` enum('pending','approved','rejected') NOT NULL DEFAULT 'approved',
  `approved_by` int(10) unsigned NOT NULL,
  `reason` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `cancellation_number` (`cancellation_number`),
  KEY `fk_cnl_booking` (`booking_id`),
  KEY `fk_cnl_plot` (`plot_id`),
  KEY `fk_cnl_customer` (`customer_id`),
  KEY `fk_cnl_user` (`approved_by`),
  CONSTRAINT `fk_cnl_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`),
  CONSTRAINT `fk_cnl_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_cnl_plot` FOREIGN KEY (`plot_id`) REFERENCES `plots` (`id`),
  CONSTRAINT `fk_cnl_user` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `customer_documents`;
CREATE TABLE `customer_documents` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` int(10) unsigned NOT NULL,
  `doc_type` varchar(50) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_cd_customer` (`customer_id`),
  CONSTRAINT `fk_cd_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `customer_documents` (`id`, `customer_id`, `doc_type`, `file_path`, `file_name`, `uploaded_at`) VALUES ('2', '2', 'CNIC Front', 'uploads/documents/doc_2_1795540645_b96a7fa4.pdf', 'test_kyc_cnic.pdf', '2026-11-24 22:17:25');

DROP TABLE IF EXISTS `customer_ledger`;
CREATE TABLE `customer_ledger` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `society_id` int(10) unsigned NOT NULL,
  `customer_id` int(10) unsigned NOT NULL,
  `booking_id` int(10) unsigned NOT NULL,
  `plot_id` int(10) unsigned NOT NULL,
  `transaction_date` date NOT NULL,
  `transaction_type` enum('booking_debit','installment_due','payment_credit','advance_credit','advance_adjustment','charge_debit','discount_credit','waiver_credit','transfer_debit','transfer_credit','cancellation_credit','refund_debit','reversal') NOT NULL,
  `reference_id` int(10) unsigned DEFAULT NULL,
  `voucher_no` varchar(50) DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `debit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `credit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `running_balance` decimal(15,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cust_lead` (`customer_id`,`booking_id`,`transaction_date`),
  KEY `fk_cl_society` (`society_id`),
  KEY `fk_cl_booking` (`booking_id`),
  KEY `fk_cl_plot` (`plot_id`),
  CONSTRAINT `fk_cl_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`),
  CONSTRAINT `fk_cl_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_cl_plot` FOREIGN KEY (`plot_id`) REFERENCES `plots` (`id`),
  CONSTRAINT `fk_cl_society` FOREIGN KEY (`society_id`) REFERENCES `societies` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `customer_ledger` (`id`, `society_id`, `customer_id`, `booking_id`, `plot_id`, `transaction_date`, `transaction_type`, `reference_id`, `voucher_no`, `description`, `debit`, `credit`, `running_balance`, `created_at`) VALUES ('2', '1', '2', '2', '1', '2026-11-24', 'booking_debit', '2', 'BK-2026-0001', 'Plot Sale Booking BK-2026-0001 (Plot #TEST-P2-01)', '3100000.00', '0.00', '3100000.00', '2026-11-24 22:27:29');
INSERT INTO `customer_ledger` (`id`, `society_id`, `customer_id`, `booking_id`, `plot_id`, `transaction_date`, `transaction_type`, `reference_id`, `voucher_no`, `description`, `debit`, `credit`, `running_balance`, `created_at`) VALUES ('3', '1', '2', '2', '1', '2026-11-24', 'payment_credit', '1', 'REC-GVR-01-2026-0001', 'Payment Receipt REC-GVR-01-2026-0001 (cash)', '0.00', '620000.00', '2480000.00', '2026-11-24 22:38:36');
INSERT INTO `customer_ledger` (`id`, `society_id`, `customer_id`, `booking_id`, `plot_id`, `transaction_date`, `transaction_type`, `reference_id`, `voucher_no`, `description`, `debit`, `credit`, `running_balance`, `created_at`) VALUES ('4', '1', '2', '2', '1', '2026-11-24', 'payment_credit', '2', 'REC-GVR-01-2026-0002', 'Payment Receipt REC-GVR-01-2026-0002 (bank_transfer)', '0.00', '250000.00', '2230000.00', '2026-11-24 22:38:36');
INSERT INTO `customer_ledger` (`id`, `society_id`, `customer_id`, `booking_id`, `plot_id`, `transaction_date`, `transaction_type`, `reference_id`, `voucher_no`, `description`, `debit`, `credit`, `running_balance`, `created_at`) VALUES ('5', '1', '2', '2', '1', '2026-11-24', 'reversal', '2', 'REV-REC-GVR-01-2026-0002', 'Reversal of Receipt REC-GVR-01-2026-0002: Online transfer bounce test reversal', '250000.00', '0.00', '2480000.00', '2026-11-24 22:38:36');

DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `customer_id_code` varchar(30) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `father_husband_name` varchar(150) NOT NULL,
  `cnic_passport` varchar(30) NOT NULL,
  `mobile` varchar(30) NOT NULL,
  `whatsapp` varchar(30) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `residential_address` text NOT NULL,
  `permanent_address` text DEFAULT NULL,
  `city` varchar(100) NOT NULL,
  `country` varchar(100) NOT NULL DEFAULT 'Pakistan',
  `nominee_name` varchar(150) DEFAULT NULL,
  `nominee_relation` varchar(50) DEFAULT NULL,
  `nominee_cnic` varchar(30) DEFAULT NULL,
  `nominee_contact` varchar(30) DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive','blacklisted') NOT NULL DEFAULT 'active',
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `customer_id_code` (`customer_id_code`),
  KEY `idx_cust_cnic` (`cnic_passport`),
  KEY `idx_cust_mobile` (`mobile`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `customers` (`id`, `customer_id_code`, `full_name`, `father_husband_name`, `cnic_passport`, `mobile`, `whatsapp`, `email`, `residential_address`, `permanent_address`, `city`, `country`, `nominee_name`, `nominee_relation`, `nominee_cnic`, `nominee_contact`, `photo_path`, `status`, `remarks`, `created_at`, `updated_at`) VALUES ('2', 'CUST-0001', 'Muhammad Asif', 'Abdul Rasheed', '37405-1122334-1', '03001234567', '03001234567', 'asif.buyer@example.com', 'House 45, Street 12, Sector F-8/2', 'Village Kotha, Swabi', 'Islamabad', 'Pakistan', 'Fatima Asif', 'Wife', '37405-9988776-2', '03019876543', NULL, 'active', 'Automated test buyer', '2026-11-24 22:17:24', '2026-11-24 22:17:24');

DROP TABLE IF EXISTS `expense_categories`;
CREATE TABLE `expense_categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `society_id` int(10) unsigned DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `account_id` int(10) unsigned DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  KEY `fk_ec_society` (`society_id`),
  KEY `fk_ec_account` (`account_id`),
  CONSTRAINT `fk_ec_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`),
  CONSTRAINT `fk_ec_society` FOREIGN KEY (`society_id`) REFERENCES `societies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `expense_categories` (`id`, `society_id`, `name`, `account_id`, `status`) VALUES ('1', NULL, 'Staff Salaries & Wages', '16', 'active');
INSERT INTO `expense_categories` (`id`, `society_id`, `name`, `account_id`, `status`) VALUES ('2', NULL, 'Marketing & Advertising', '17', 'active');
INSERT INTO `expense_categories` (`id`, `society_id`, `name`, `account_id`, `status`) VALUES ('3', NULL, 'Office Utilities & Bills', '18', 'active');
INSERT INTO `expense_categories` (`id`, `society_id`, `name`, `account_id`, `status`) VALUES ('4', NULL, 'Site Earthwork & Roads', '19', 'active');
INSERT INTO `expense_categories` (`id`, `society_id`, `name`, `account_id`, `status`) VALUES ('5', NULL, 'Legal & Documentation', '20', 'active');
INSERT INTO `expense_categories` (`id`, `society_id`, `name`, `account_id`, `status`) VALUES ('6', NULL, 'Miscellaneous Office Expenses', '18', 'active');

DROP TABLE IF EXISTS `expenses`;
CREATE TABLE `expenses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `voucher_number` varchar(50) NOT NULL,
  `society_id` int(10) unsigned NOT NULL,
  `expense_category_id` int(10) unsigned NOT NULL,
  `payment_account_id` int(10) unsigned NOT NULL,
  `expense_date` date NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `paid_to` varchar(150) NOT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `approval_status` enum('approved','pending','rejected') NOT NULL DEFAULT 'approved',
  `created_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `voucher_number` (`voucher_number`),
  KEY `fk_exp_society` (`society_id`),
  KEY `fk_exp_category` (`expense_category_id`),
  KEY `fk_exp_account` (`payment_account_id`),
  KEY `fk_exp_user` (`created_by`),
  CONSTRAINT `fk_exp_account` FOREIGN KEY (`payment_account_id`) REFERENCES `accounts` (`id`),
  CONSTRAINT `fk_exp_category` FOREIGN KEY (`expense_category_id`) REFERENCES `expense_categories` (`id`),
  CONSTRAINT `fk_exp_society` FOREIGN KEY (`society_id`) REFERENCES `societies` (`id`),
  CONSTRAINT `fk_exp_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `installment_plan_templates`;
CREATE TABLE `installment_plan_templates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `society_id` int(10) unsigned DEFAULT NULL,
  `plan_name` varchar(100) NOT NULL,
  `plan_code` varchar(30) NOT NULL,
  `duration_months` int(10) unsigned NOT NULL,
  `down_payment_type` enum('percentage','fixed') NOT NULL DEFAULT 'percentage',
  `down_payment_value` decimal(15,2) NOT NULL,
  `frequency` enum('monthly','quarterly','semi_annual','annual','custom') NOT NULL DEFAULT 'monthly',
  `total_installments` int(10) unsigned NOT NULL,
  `grace_period_days` int(10) unsigned NOT NULL DEFAULT 10,
  `late_fee_type` enum('fixed','percentage','none') NOT NULL DEFAULT 'none',
  `late_fee_value` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_ipt_society` (`society_id`),
  CONSTRAINT `fk_ipt_society` FOREIGN KEY (`society_id`) REFERENCES `societies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `installment_plan_templates` (`id`, `society_id`, `plan_name`, `plan_code`, `duration_months`, `down_payment_type`, `down_payment_value`, `frequency`, `total_installments`, `grace_period_days`, `late_fee_type`, `late_fee_value`, `status`, `description`, `created_at`) VALUES ('1', '1', '3-Year Standard Monthly Plan', 'STD-36M', '36', 'percentage', '20.00', 'monthly', '36', '10', 'fixed', '500.00', 'active', '20% down payment with 36 equal monthly installments', '2026-11-24 22:02:51');
INSERT INTO `installment_plan_templates` (`id`, `society_id`, `plan_name`, `plan_code`, `duration_months`, `down_payment_type`, `down_payment_value`, `frequency`, `total_installments`, `grace_period_days`, `late_fee_type`, `late_fee_value`, `status`, `description`, `created_at`) VALUES ('2', '1', '2-Year Executive Quarterly Plan', 'EXEC-24Q', '24', 'percentage', '25.00', 'quarterly', '8', '15', 'percentage', '2.00', 'active', '25% down payment with 8 quarterly installments', '2026-11-24 22:02:51');
INSERT INTO `installment_plan_templates` (`id`, `society_id`, `plan_name`, `plan_code`, `duration_months`, `down_payment_type`, `down_payment_value`, `frequency`, `total_installments`, `grace_period_days`, `late_fee_type`, `late_fee_value`, `status`, `description`, `created_at`) VALUES ('3', '1', '1-Year Fast Track Plan', 'FAST-12M', '12', 'percentage', '30.00', 'monthly', '12', '7', 'none', '0.00', 'active', '30% down payment with 12 monthly installments', '2026-11-24 22:02:51');

DROP TABLE IF EXISTS `installment_revisions`;
CREATE TABLE `installment_revisions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` int(10) unsigned NOT NULL,
  `revision_number` int(10) unsigned NOT NULL,
  `reason` text NOT NULL,
  `snapshot_json` longtext NOT NULL,
  `approved_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_ir_booking` (`booking_id`),
  KEY `fk_ir_user` (`approved_by`),
  CONSTRAINT `fk_ir_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ir_user` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `installment_revisions` (`id`, `booking_id`, `revision_number`, `reason`, `snapshot_json`, `approved_by`, `created_at`) VALUES ('2', '2', '1', 'Phase 5 automated test schedule revision', '[\n    {\n        \"id\": 26,\n        \"booking_id\": 2,\n        \"installment_no\": 0,\n        \"installment_title\": \"Down Payment \\/ Booking Advance\",\n        \"due_date\": \"2026-11-24\",\n        \"amount_due\": \"620000.00\",\n        \"principal_amount\": \"620000.00\",\n        \"charges_amount\": \"0.00\",\n        \"amount_paid\": \"0.00\",\n        \"balance_due\": \"620000.00\",\n        \"late_fee_charged\": \"0.00\",\n        \"late_fee_waived\": \"0.00\",\n        \"status\": \"pending\",\n        \"is_custom\": 0,\n        \"paid_date\": null,\n        \"created_at\": \"2026-11-24 22:27:29\",\n        \"updated_at\": \"2026-11-24 22:27:29\"\n    },\n    {\n        \"id\": 27,\n        \"booking_id\": 2,\n        \"installment_no\": 1,\n        \"installment_title\": \"Installment #1 (Monthly)\",\n        \"due_date\": \"2026-12-10\",\n        \"amount_due\": \"103333.33\",\n        \"principal_amount\": \"103333.33\",\n        \"charges_amount\": \"0.00\",\n        \"amount_paid\": \"0.00\",\n        \"balance_due\": \"103333.33\",\n        \"late_fee_charged\": \"0.00\",\n        \"late_fee_waived\": \"0.00\",\n        \"status\": \"pending\",\n        \"is_custom\": 0,\n        \"paid_date\": null,\n        \"created_at\": \"2026-11-24 22:27:29\",\n        \"updated_at\": \"2026-11-24 22:27:29\"\n    },\n    {\n        \"id\": 28,\n        \"booking_id\": 2,\n        \"installment_no\": 2,\n        \"installment_title\": \"Installment #2 (Monthly)\",\n        \"due_date\": \"2027-01-10\",\n        \"amount_due\": \"103333.33\",\n        \"principal_amount\": \"103333.33\",\n        \"charges_amount\": \"0.00\",\n        \"amount_paid\": \"0.00\",\n        \"balance_due\": \"103333.33\",\n        \"late_fee_charged\": \"0.00\",\n        \"late_fee_waived\": \"0.00\",\n        \"status\": \"pending\",\n        \"is_custom\": 0,\n        \"paid_date\": null,\n        \"created_at\": \"2026-11-24 22:27:29\",\n        \"updated_at\": \"2026-11-24 22:27:29\"\n    },\n    {\n        \"id\": 29,\n        \"booking_id\": 2,\n        \"installment_no\": 3,\n        \"installment_title\": \"Installment #3 (Monthly)\",\n        \"due_date\": \"2027-02-10\",\n        \"amount_due\": \"103333.33\",\n        \"principal_amount\": \"103333.33\",\n        \"charges_amount\": \"0.00\",\n        \"amount_paid\": \"0.00\",\n        \"balance_due\": \"103333.33\",\n        \"late_fee_charged\": \"0.00\",\n        \"late_fee_waived\": \"0.00\",\n        \"status\": \"pending\",\n        \"is_custom\": 0,\n        \"paid_date\": null,\n        \"created_at\": \"2026-11-24 22:27:29\",\n        \"updated_at\": \"2026-11-24 22:27:29\"\n    },\n    {\n        \"id\": 30,\n        \"booking_id\": 2,\n        \"installment_no\": 4,\n        \"installment_title\": \"Installment #4 (Monthly)\",\n        \"due_date\": \"2027-03-10\",\n        \"amount_due\": \"103333.33\",\n        \"principal_amount\": \"103333.33\",\n        \"charges_amount\": \"0.00\",\n        \"amount_paid\": \"0.00\",\n        \"balance_due\": \"103333.33\",\n        \"late_fee_charged\": \"0.00\",\n        \"late_fee_waived\": \"0.00\",\n        \"status\": \"pending\",\n        \"is_custom\": 0,\n        \"paid_date\": null,\n        \"created_at\": \"2026-11-24 22:27:29\",\n        \"updated_at\": \"2026-11-24 22:27:29\"\n    },\n    {\n        \"id\": 31,\n        \"booking_id\": 2,\n        \"installment_no\": 5,\n        \"installment_title\": \"Installment #5 (Monthly)\",\n        \"due_date\": \"2027-04-10\",\n        \"amount_due\": \"103333.33\",\n        \"principal_amount\": \"103333.33\",\n        \"charges_amount\": \"0.00\",\n        \"amount_paid\": \"0.00\",\n        \"balance_due\": \"103333.33\",\n        \"late_fee_charged\": \"0.00\",\n        \"late_fee_waived\": \"0.00\",\n        \"status\": \"pending\",\n        \"is_custom\": 0,\n        \"paid_date\": null,\n        \"created_at\": \"2026-11-24 22:27:29\",\n        \"updated_at\": \"2026-11-24 22:27:29\"\n    },\n    {\n        \"id\": 32,\n        \"booking_id\": 2,\n        \"installment_no\": 6,\n        \"installment_title\": \"Installment #6 (Monthly)\",\n        \"due_date\": \"2027-05-10\",\n        \"amount_due\": \"103333.33\",\n        \"principal_amount\": \"103333.33\",\n        \"charges_amount\": \"0.00\",\n        \"amount_paid\": \"0.00\",\n        \"balance_due\": \"103333.33\",\n        \"late_fee_charged\": \"0.00\",\n        \"late_fee_waived\": \"0.00\",\n        \"status\": \"pending\",\n        \"is_custom\": 0,\n        \"paid_date\": null,\n        \"created_at\": \"2026-11-24 22:27:29\",\n        \"updated_at\": \"2026-11-24 22:27:29\"\n    },\n    {\n        \"id\": 33,\n        \"booking_id\": 2,\n        \"installment_no\": 7,\n        \"installment_title\": \"Installment #7 (Monthly)\",\n        \"due_date\": \"2027-06-10\",\n        \"amount_due\": \"103333.33\",\n        \"principal_amount\": \"103333.33\",\n        \"charges_amount\": \"0.00\",\n        \"amount_paid\": \"0.00\",\n        \"balance_due\": \"103333.33\",\n        \"late_fee_charged\": \"0.00\",\n        \"late_fee_waived\": \"0.00\",\n        \"status\": \"pending\",\n        \"is_custom\": 0,\n        \"paid_date\": null,\n        \"created_at\": \"2026-11-24 22:27:29\",\n        \"updated_at\": \"2026-11-24 22:27:29\"\n    },\n    {\n        \"id\": 34,\n        \"booking_id\": 2,\n        \"installment_no\": 8,\n        \"installment_title\": \"Installment #8 (Monthly)\",\n        \"due_date\": \"2027-07-10\",\n        \"amount_due\": \"103333.33\",\n        \"principal_amount\": \"103333.33\",\n        \"charges_amount\": \"0.00\",\n        \"amount_paid\": \"0.00\",\n        \"balance_due\": \"103333.33\",\n        \"late_fee_charged\": \"0.00\",\n        \"late_fee_waived\": \"0.00\",\n        \"status\": \"pending\",\n        \"is_custom\": 0,\n        \"paid_date\": null,\n        \"created_at\": \"2026-11-24 22:27:29\",\n        \"updated_at\": \"2026-11-24 22:27:29\"\n    },\n    {\n        \"id\": 35,\n        \"booking_id\": 2,\n        \"installment_no\": 9,\n        \"installment_title\": \"Installment #9 (Monthly)\",\n        \"due_date\": \"2027-08-10\",\n        \"amount_due\": \"103333.33\",\n        \"principal_amount\": \"103333.33\",\n        \"charges_amount\": \"0.00\",\n        \"amount_paid\": \"0.00\",\n        \"balance_due\": \"103333.33\",\n        \"late_fee_charged\": \"0.00\",\n        \"late_fee_waived\": \"0.00\",\n        \"status\": \"pending\",\n        \"is_custom\": 0,\n        \"paid_date\": null,\n        \"created_at\": \"2026-11-24 22:27:29\",\n        \"updated_at\": \"2026-11-24 22:27:29\"\n    },\n    {\n        \"id\": 36,\n        \"booking_id\": 2,\n        \"installment_no\": 10,\n        \"installment_title\": \"Installment #10 (Monthly)\",\n        \"due_date\": \"2027-09-10\",\n        \"amount_due\": \"103333.33\",\n        \"principal_amount\": \"103333.33\",\n        \"charges_amount\": \"0.00\",\n        \"amount_paid\": \"0.00\",\n        \"balance_due\": \"103333.33\",\n        \"late_fee_charged\": \"0.00\",\n        \"late_fee_waived\": \"0.00\",\n        \"status\": \"pending\",\n        \"is_custom\": 0,\n        \"paid_date\": null,\n        \"created_at\": \"2026-11-24 22:27:29\",\n        \"updated_at\": \"2026-11-24 22:27:29\"\n    },\n    {\n        \"id\": 37,\n        \"booking_id\": 2,\n        \"installment_no\": 11,\n        \"installment_title\": \"Installment #11 (Monthly)\",\n        \"due_date\": \"2027-10-10\",\n        \"amount_due\": \"103333.33\",\n        \"principal_amount\": \"103333.33\",\n        \"charges_amount\": \"0.00\",\n        \"amount_paid\": \"0.00\",\n        \"balance_due\": \"103333.33\",\n        \"late_fee_charged\": \"0.00\",\n        \"late_fee_waived\": \"0.00\",\n        \"status\": \"pending\",\n        \"is_custom\": 0,\n        \"paid_date\": null,\n        \"created_at\": \"2026-11-24 22:27:29\",\n        \"updated_at\": \"2026-11-24 22:27:29\"\n    },\n    {\n        \"id\": 38,\n        \"booking_id\": 2,\n        \"installment_no\": 12,\n        \"installment_title\": \"Installment #12 (Monthly)\",\n        \"due_date\": \"2027-11-10\",\n        \"amount_due\": \"103333.33\",\n        \"principal_amount\": \"103333.33\",\n        \"charges_amount\": \"0.00\",\n        \"amount_paid\": \"0.00\",\n        \"balance_due\": \"103333.33\",\n        \"late_fee_charged\": \"0.00\",\n        \"late_fee_waived\": \"0.00\",\n        \"status\": \"pending\",\n        \"is_custom\": 0,\n        \"paid_date\": null,\n        \"created_at\": \"2026-11-24 22:27:29\",\n        \"updated_at\": \"2026-11-24 22:27:29\"\n    },\n    {\n        \"id\": 39,\n        \"booking_id\": 2,\n        \"installment_no\": 13,\n        \"installment_title\": \"Installment #13 (Monthly)\",\n        \"due_date\": \"2027-12-10\",\n        \"amount_due\": \"103333.33\",\n        \"principal_amount\": \"103333.33\",\n        \"charges_amount\": \"0.00\",\n        \"amount_paid\": \"0.00\",\n        \"balance_due\": \"103333.33\",\n        \"late_fee_charged\": \"0.00\",\n        \"late_fee_waived\": \"0.00\",\n        \"status\": \"pending\",\n        \"is_custom\": 0,\n        \"paid_date\": null,\n        \"created_at\": \"2026-11-24 22:27:29\",\n        \"updated_at\": \"2026-11-24 22:27:29\"\n    },\n    {\n        \"id\": 40,\n        \"booking_id\": 2,\n        \"installment_no\": 14,\n        \"installment_title\": \"Installment #14 (Monthly)\",\n        \"due_date\": \"2028-01-10\",\n        \"amount_due\": \"103333.33\",\n        \"principal_amount\": \"103333.33\",\n        \"charges_amount\": \"0.00\",\n        \"amount_paid\": \"0.00\",\n        \"balance_due\": \"103333.33\",\n        \"late_fee_charged\": \"0.00\",\n        \"late_fee_waived\": \"0.00\",\n        \"status\": \"pending\",\n        \"is_custom\": 0,\n        \"paid_date\": null,\n        \"created_at\": \"2026-11-24 22:27:29\",\n        \"updated_at\": \"2026-11-24 22:27:29\"\n    },\n    {\n        \"id\": 41,\n        \"booking_id\": 2,\n        \"installment_no\": 15,\n        \"installment_title\": \"Installment #15 (Monthly)\",\n        \"due_date\": \"2028-02-10\",\n        \"amount_due\": \"103333.33\",\n        \"principal_amount\": \"103333.33\",\n        \"charges_amount\": \"0.00\",\n        \"amount_paid\": \"0.00\",\n        \"balance_due\": \"103333.33\",\n        \"late_fee_charged\": \"0.00\",\n        \"late_fee_waived\": \"0.00\",\n        \"status\": \"pending\",\n        \"is_custom\": 0,\n        \"paid_date\": null,\n        \"created_at\": \"2026-11-24 22:27:29\",\n        \"updated_at\": \"2026-11-24 22:27:29\"\n    },\n    {\n        \"id\": 42,\n        \"booking_id\": 2,\n        \"installment_no\": 16,\n        \"installment_title\": \"Installment #16 (Monthly)\",\n        \"due_date\": \"2028-03-10\",\n        \"amount_due\": \"103333.33\",\n        \"principal_amount\": \"103333.33\",\n        \"charges_amount\": \"0.00\",\n        \"amount_paid\": \"0.00\",\n        \"balance_due\": \"103333.33\",\n        \"late_fee_charged\": \"0.00\",\n        \"late_fee_waived\": \"0.00\",\n        \"status\": \"pending\",\n        \"is_custom\": 0,\n        \"paid_date\": null,\n        \"created_at\": \"2026-11-24 22:27:29\",\n        \"updated_at\": \"2026-11-24 22:27:29\"\n    },\n    {\n        \"id\": 43,\n        \"booking_id\": 2,\n        \"installment_no\": 17,\n        \"installment_title\": \"Installment #17 (Monthly)\",\n        \"due_date\": \"2028-04-10\",\n        \"amount_due\": \"103333.33\",\n        \"principal_amount\": \"103333.33\",\n        \"charges_amount\": \"0.00\",\n        \"amount_paid\": \"0.00\",\n        \"balance_due\": \"103333.33\",\n        \"late_fee_charged\": \"0.00\",\n        \"late_fee_waived\": \"0.00\",\n        \"status\": \"pending\",\n        \"is_custom\": 0,\n        \"paid_date\": null,\n        \"created_at\": \"2026-11-24 22:27:29\",\n        \"updated_at\": \"2026-11-24 22:27:29\"\n    },\n    {\n        \"id\": 44,\n        \"booking_id\": 2,\n        \"installment_no\": 18,\n        \"installment_title\": \"Installment #18 (Monthly)\",\n        \"due_date\": \"2028-05-10\",\n        \"amount_due\": \"103333.33\",\n        \"principal_amount\": \"103333.33\",\n        \"charges_amount\": \"0.00\",\n        \"amount_paid\": \"0.00\",\n        \"balance_due\": \"103333.33\",\n        \"late_fee_charged\": \"0.00\",\n        \"late_fee_waived\": \"0.00\",\n        \"status\": \"pending\",\n        \"is_custom\": 0,\n        \"paid_date\": null,\n        \"created_at\": \"2026-11-24 22:27:29\",\n        \"updated_at\": \"2026-11-24 22:27:29\"\n    },\n    {\n        \"id\": 45,\n        \"booking_id\": 2,\n        \"installment_no\": 19,\n        \"installment_title\": \"Installment #19 (Monthly)\",\n        \"due_date\": \"2028-06-10\",\n        \"amount_due\": \"103333.33\",\n        \"principal_amount\": \"103333.33\",\n        \"charges_amount\": \"0.00\",\n        \"amount_paid\": \"0.00\",\n        \"balance_due\": \"103333.33\",\n        \"late_fee_charged\": \"0.00\",\n        \"late_fee_waived\": \"0.00\",\n        \"status\": \"pending\",\n        \"is_custom\": 0,\n        \"paid_date\": null,\n        \"created_at\": \"2026-11-24 22:27:29\",\n        \"updated_at\": \"2026-11-24 22:27:29\"\n    },\n    {\n        \"id\": 46,\n        \"booking_id\": 2,\n        \"installment_no\": 20,\n        \"installment_title\": \"Installment #20 (Monthly)\",\n        \"due_date\": \"2028-07-10\",\n        \"amount_due\": \"103333.33\",\n        \"principal_amount\": \"103333.33\",\n        \"charges_amount\": \"0.00\",\n        \"amount_paid\": \"0.00\",\n        \"balance_due\": \"103333.33\",\n        \"late_fee_charged\": \"0.00\",\n        \"late_fee_waived\": \"0.00\",\n        \"status\": \"pending\",\n        \"is_custom\": 0,\n        \"paid_date\": null,\n        \"created_at\": \"2026-11-24 22:27:29\",\n        \"updated_at\": \"2026-11-24 22:27:29\"\n    },\n    {\n        \"id\": 47,\n        \"booking_id\": 2,\n        \"installment_no\": 21,\n        \"installment_title\": \"Installment #21 (Monthly)\",\n        \"due_date\": \"2028-08-10\",\n        \"amount_due\": \"103333.33\",\n        \"principal_amount\": \"103333.33\",\n        \"charges_amount\": \"0.00\",\n        \"amount_paid\": \"0.00\",\n        \"balance_due\": \"103333.33\",\n        \"late_fee_charged\": \"0.00\",\n        \"late_fee_waived\": \"0.00\",\n        \"status\": \"pending\",\n        \"is_custom\": 0,\n        \"paid_date\": null,\n        \"created_at\": \"2026-11-24 22:27:29\",\n        \"updated_at\": \"2026-11-24 22:27:29\"\n    },\n    {\n        \"id\": 48,\n        \"booking_id\": 2,\n        \"installment_no\": 22,\n        \"installment_title\": \"Installment #22 (Monthly)\",\n        \"due_date\": \"2028-09-10\",\n        \"amount_due\": \"103333.33\",\n        \"principal_amount\": \"103333.33\",\n        \"charges_amount\": \"0.00\",\n        \"amount_paid\": \"0.00\",\n        \"balance_due\": \"103333.33\",\n        \"late_fee_charged\": \"0.00\",\n        \"late_fee_waived\": \"0.00\",\n        \"status\": \"pending\",\n        \"is_custom\": 0,\n        \"paid_date\": null,\n        \"created_at\": \"2026-11-24 22:27:29\",\n        \"updated_at\": \"2026-11-24 22:27:29\"\n    },\n    {\n        \"id\": 49,\n        \"booking_id\": 2,\n        \"installment_no\": 23,\n        \"installment_title\": \"Installment #23 (Monthly)\",\n        \"due_date\": \"2028-10-10\",\n        \"amount_due\": \"103333.33\",\n        \"principal_amount\": \"103333.33\",\n        \"charges_amount\": \"0.00\",\n        \"amount_paid\": \"0.00\",\n        \"balance_due\": \"103333.33\",\n        \"late_fee_charged\": \"0.00\",\n        \"late_fee_waived\": \"0.00\",\n        \"status\": \"pending\",\n        \"is_custom\": 0,\n        \"paid_date\": null,\n        \"created_at\": \"2026-11-24 22:27:29\",\n        \"updated_at\": \"2026-11-24 22:27:29\"\n    },\n    {\n        \"id\": 50,\n        \"booking_id\": 2,\n        \"installment_no\": 24,\n        \"installment_title\": \"Installment #24 (Monthly)\",\n        \"due_date\": \"2028-11-10\",\n        \"amount_due\": \"103333.41\",\n        \"principal_amount\": \"103333.41\",\n        \"charges_amount\": \"0.00\",\n        \"amount_paid\": \"0.00\",\n        \"balance_due\": \"103333.41\",\n        \"late_fee_charged\": \"0.00\",\n        \"late_fee_waived\": \"0.00\",\n        \"status\": \"pending\",\n        \"is_custom\": 0,\n        \"paid_date\": null,\n        \"created_at\": \"2026-11-24 22:27:29\",\n        \"updated_at\": \"2026-11-24 22:27:29\"\n    }\n]', '1', '2026-11-24 22:27:29');

DROP TABLE IF EXISTS `installment_schedules`;
CREATE TABLE `installment_schedules` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` int(10) unsigned NOT NULL,
  `installment_no` int(10) unsigned NOT NULL,
  `installment_title` varchar(100) NOT NULL,
  `due_date` date NOT NULL,
  `amount_due` decimal(15,2) NOT NULL,
  `principal_amount` decimal(15,2) NOT NULL,
  `charges_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `amount_paid` decimal(15,2) NOT NULL DEFAULT 0.00,
  `balance_due` decimal(15,2) NOT NULL,
  `late_fee_charged` decimal(10,2) NOT NULL DEFAULT 0.00,
  `late_fee_waived` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','partially_paid','paid','overdue','waived') NOT NULL DEFAULT 'pending',
  `is_custom` tinyint(1) NOT NULL DEFAULT 0,
  `paid_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sched_due` (`booking_id`,`status`,`due_date`),
  CONSTRAINT `fk_sched_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=63 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `installment_schedules` (`id`, `booking_id`, `installment_no`, `installment_title`, `due_date`, `amount_due`, `principal_amount`, `charges_amount`, `amount_paid`, `balance_due`, `late_fee_charged`, `late_fee_waived`, `status`, `is_custom`, `paid_date`, `created_at`, `updated_at`) VALUES ('51', '2', '1', 'Revised Installment #1', '2027-01-15', '258333.33', '258333.33', '0.00', '258333.33', '0.00', '0.00', '0.00', 'paid', '0', '2026-11-24', '2026-11-24 22:27:29', '2026-11-24 22:38:36');
INSERT INTO `installment_schedules` (`id`, `booking_id`, `installment_no`, `installment_title`, `due_date`, `amount_due`, `principal_amount`, `charges_amount`, `amount_paid`, `balance_due`, `late_fee_charged`, `late_fee_waived`, `status`, `is_custom`, `paid_date`, `created_at`, `updated_at`) VALUES ('52', '2', '2', 'Revised Installment #2', '2027-02-15', '258333.33', '258333.33', '0.00', '258333.33', '0.00', '0.00', '0.00', 'paid', '0', '2026-11-24', '2026-11-24 22:27:29', '2026-11-24 22:38:36');
INSERT INTO `installment_schedules` (`id`, `booking_id`, `installment_no`, `installment_title`, `due_date`, `amount_due`, `principal_amount`, `charges_amount`, `amount_paid`, `balance_due`, `late_fee_charged`, `late_fee_waived`, `status`, `is_custom`, `paid_date`, `created_at`, `updated_at`) VALUES ('53', '2', '3', 'Revised Installment #3', '2027-03-15', '258333.33', '258333.33', '0.00', '103333.34', '154999.99', '0.00', '0.00', 'pending', '0', NULL, '2026-11-24 22:27:29', '2026-11-24 22:38:36');
INSERT INTO `installment_schedules` (`id`, `booking_id`, `installment_no`, `installment_title`, `due_date`, `amount_due`, `principal_amount`, `charges_amount`, `amount_paid`, `balance_due`, `late_fee_charged`, `late_fee_waived`, `status`, `is_custom`, `paid_date`, `created_at`, `updated_at`) VALUES ('54', '2', '4', 'Revised Installment #4', '2027-04-15', '258333.33', '258333.33', '0.00', '0.00', '258333.33', '0.00', '0.00', 'pending', '0', NULL, '2026-11-24 22:27:29', '2026-11-24 22:38:36');
INSERT INTO `installment_schedules` (`id`, `booking_id`, `installment_no`, `installment_title`, `due_date`, `amount_due`, `principal_amount`, `charges_amount`, `amount_paid`, `balance_due`, `late_fee_charged`, `late_fee_waived`, `status`, `is_custom`, `paid_date`, `created_at`, `updated_at`) VALUES ('55', '2', '5', 'Revised Installment #5', '2027-05-15', '258333.33', '258333.33', '0.00', '0.00', '258333.33', '0.00', '0.00', 'pending', '0', NULL, '2026-11-24 22:27:29', '2026-11-24 22:27:29');
INSERT INTO `installment_schedules` (`id`, `booking_id`, `installment_no`, `installment_title`, `due_date`, `amount_due`, `principal_amount`, `charges_amount`, `amount_paid`, `balance_due`, `late_fee_charged`, `late_fee_waived`, `status`, `is_custom`, `paid_date`, `created_at`, `updated_at`) VALUES ('56', '2', '6', 'Revised Installment #6', '2027-06-15', '258333.33', '258333.33', '0.00', '0.00', '258333.33', '0.00', '0.00', 'pending', '0', NULL, '2026-11-24 22:27:29', '2026-11-24 22:27:29');
INSERT INTO `installment_schedules` (`id`, `booking_id`, `installment_no`, `installment_title`, `due_date`, `amount_due`, `principal_amount`, `charges_amount`, `amount_paid`, `balance_due`, `late_fee_charged`, `late_fee_waived`, `status`, `is_custom`, `paid_date`, `created_at`, `updated_at`) VALUES ('57', '2', '7', 'Revised Installment #7', '2027-07-15', '258333.33', '258333.33', '0.00', '0.00', '258333.33', '0.00', '0.00', 'pending', '0', NULL, '2026-11-24 22:27:29', '2026-11-24 22:27:29');
INSERT INTO `installment_schedules` (`id`, `booking_id`, `installment_no`, `installment_title`, `due_date`, `amount_due`, `principal_amount`, `charges_amount`, `amount_paid`, `balance_due`, `late_fee_charged`, `late_fee_waived`, `status`, `is_custom`, `paid_date`, `created_at`, `updated_at`) VALUES ('58', '2', '8', 'Revised Installment #8', '2027-08-15', '258333.33', '258333.33', '0.00', '0.00', '258333.33', '0.00', '0.00', 'pending', '0', NULL, '2026-11-24 22:27:29', '2026-11-24 22:27:29');
INSERT INTO `installment_schedules` (`id`, `booking_id`, `installment_no`, `installment_title`, `due_date`, `amount_due`, `principal_amount`, `charges_amount`, `amount_paid`, `balance_due`, `late_fee_charged`, `late_fee_waived`, `status`, `is_custom`, `paid_date`, `created_at`, `updated_at`) VALUES ('59', '2', '9', 'Revised Installment #9', '2027-09-15', '258333.33', '258333.33', '0.00', '0.00', '258333.33', '0.00', '0.00', 'pending', '0', NULL, '2026-11-24 22:27:29', '2026-11-24 22:27:29');
INSERT INTO `installment_schedules` (`id`, `booking_id`, `installment_no`, `installment_title`, `due_date`, `amount_due`, `principal_amount`, `charges_amount`, `amount_paid`, `balance_due`, `late_fee_charged`, `late_fee_waived`, `status`, `is_custom`, `paid_date`, `created_at`, `updated_at`) VALUES ('60', '2', '10', 'Revised Installment #10', '2027-10-15', '258333.33', '258333.33', '0.00', '0.00', '258333.33', '0.00', '0.00', 'pending', '0', NULL, '2026-11-24 22:27:29', '2026-11-24 22:27:29');
INSERT INTO `installment_schedules` (`id`, `booking_id`, `installment_no`, `installment_title`, `due_date`, `amount_due`, `principal_amount`, `charges_amount`, `amount_paid`, `balance_due`, `late_fee_charged`, `late_fee_waived`, `status`, `is_custom`, `paid_date`, `created_at`, `updated_at`) VALUES ('61', '2', '11', 'Revised Installment #11', '2027-11-15', '258333.33', '258333.33', '0.00', '0.00', '258333.33', '0.00', '0.00', 'pending', '0', NULL, '2026-11-24 22:27:29', '2026-11-24 22:27:29');
INSERT INTO `installment_schedules` (`id`, `booking_id`, `installment_no`, `installment_title`, `due_date`, `amount_due`, `principal_amount`, `charges_amount`, `amount_paid`, `balance_due`, `late_fee_charged`, `late_fee_waived`, `status`, `is_custom`, `paid_date`, `created_at`, `updated_at`) VALUES ('62', '2', '12', 'Revised Installment #12', '2027-12-15', '258333.37', '258333.37', '0.00', '0.00', '258333.37', '0.00', '0.00', 'pending', '0', NULL, '2026-11-24 22:27:29', '2026-11-24 22:27:29');

DROP TABLE IF EXISTS `journal_entries`;
CREATE TABLE `journal_entries` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `entry_number` varchar(50) NOT NULL,
  `society_id` int(10) unsigned NOT NULL,
  `entry_date` date NOT NULL,
  `entry_type` enum('journal','receipt','payment','sale','transfer','cancellation','expense') NOT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` int(10) unsigned DEFAULT NULL,
  `narration` text NOT NULL,
  `total_debit` decimal(15,2) NOT NULL,
  `total_credit` decimal(15,2) NOT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `entry_number` (`entry_number`),
  KEY `fk_je_society` (`society_id`),
  KEY `fk_je_user` (`created_by`),
  CONSTRAINT `fk_je_society` FOREIGN KEY (`society_id`) REFERENCES `societies` (`id`),
  CONSTRAINT `fk_je_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `journal_entries` (`id`, `entry_number`, `society_id`, `entry_date`, `entry_type`, `reference_type`, `reference_id`, `narration`, `total_debit`, `total_credit`, `created_by`, `created_at`) VALUES ('1', 'JV-2026-0001', '1', '2026-11-24', 'receipt', 'payment', '1', 'Receipt REC-GVR-01-2026-0001 for Booking #BK-2026-0001', '620000.00', '620000.00', '1', '2026-11-24 22:38:36');
INSERT INTO `journal_entries` (`id`, `entry_number`, `society_id`, `entry_date`, `entry_type`, `reference_type`, `reference_id`, `narration`, `total_debit`, `total_credit`, `created_by`, `created_at`) VALUES ('2', 'JV-2026-0002', '1', '2026-11-24', 'receipt', 'payment', '2', 'Receipt REC-GVR-01-2026-0002 for Booking #BK-2026-0001', '250000.00', '250000.00', '1', '2026-11-24 22:38:36');
INSERT INTO `journal_entries` (`id`, `entry_number`, `society_id`, `entry_date`, `entry_type`, `reference_type`, `reference_id`, `narration`, `total_debit`, `total_credit`, `created_by`, `created_at`) VALUES ('3', 'JV-2026-0003', '1', '2026-11-24', 'journal', 'payment_reversal', '2', 'Reversal of Receipt REC-GVR-01-2026-0002: Online transfer bounce test reversal', '250000.00', '250000.00', '1', '2026-11-24 22:38:36');

DROP TABLE IF EXISTS `journal_entry_lines`;
CREATE TABLE `journal_entry_lines` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `journal_entry_id` int(10) unsigned NOT NULL,
  `account_id` int(10) unsigned NOT NULL,
  `debit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `credit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `memo` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_line_acc` (`account_id`,`journal_entry_id`),
  KEY `fk_jel_entry` (`journal_entry_id`),
  CONSTRAINT `fk_jel_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`),
  CONSTRAINT `fk_jel_entry` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `journal_entry_lines` (`id`, `journal_entry_id`, `account_id`, `debit`, `credit`, `memo`) VALUES ('1', '1', '1', '620000.00', '0.00', 'Payment received via cash');
INSERT INTO `journal_entry_lines` (`id`, `journal_entry_id`, `account_id`, `debit`, `credit`, `memo`) VALUES ('2', '1', '5', '0.00', '620000.00', 'Installment allocation on REC-GVR-01-2026-0001');
INSERT INTO `journal_entry_lines` (`id`, `journal_entry_id`, `account_id`, `debit`, `credit`, `memo`) VALUES ('3', '2', '3', '250000.00', '0.00', 'Payment received via bank_transfer');
INSERT INTO `journal_entry_lines` (`id`, `journal_entry_id`, `account_id`, `debit`, `credit`, `memo`) VALUES ('4', '2', '5', '0.00', '250000.00', 'Installment allocation on REC-GVR-01-2026-0002');
INSERT INTO `journal_entry_lines` (`id`, `journal_entry_id`, `account_id`, `debit`, `credit`, `memo`) VALUES ('5', '3', '5', '250000.00', '0.00', 'Reversal of allocation for REC-GVR-01-2026-0002');
INSERT INTO `journal_entry_lines` (`id`, `journal_entry_id`, `account_id`, `debit`, `credit`, `memo`) VALUES ('6', '3', '3', '0.00', '250000.00', 'Cash/bank outflow for reversal of REC-GVR-01-2026-0002');

DROP TABLE IF EXISTS `notification_logs`;
CREATE TABLE `notification_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `society_id` int(10) unsigned NOT NULL,
  `customer_id` int(10) unsigned DEFAULT NULL,
  `recipient` varchar(100) NOT NULL,
  `channel` enum('sms','whatsapp','email') NOT NULL,
  `message_type` varchar(50) NOT NULL,
  `message_body` text NOT NULL,
  `status` enum('sent','failed','pending','manual_link_generated') NOT NULL DEFAULT 'pending',
  `external_ref` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_nl_society` (`society_id`),
  KEY `fk_nl_customer` (`customer_id`),
  CONSTRAINT `fk_nl_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_nl_society` FOREIGN KEY (`society_id`) REFERENCES `societies` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `payment_allocations`;
CREATE TABLE `payment_allocations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `payment_id` int(10) unsigned NOT NULL,
  `schedule_id` int(10) unsigned NOT NULL,
  `allocated_amount` decimal(15,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_pa_payment` (`payment_id`),
  KEY `fk_pa_schedule` (`schedule_id`),
  CONSTRAINT `fk_pa_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pa_schedule` FOREIGN KEY (`schedule_id`) REFERENCES `installment_schedules` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `payment_allocations` (`id`, `payment_id`, `schedule_id`, `allocated_amount`, `created_at`) VALUES ('1', '1', '51', '258333.33', '2026-11-24 22:38:36');
INSERT INTO `payment_allocations` (`id`, `payment_id`, `schedule_id`, `allocated_amount`, `created_at`) VALUES ('2', '1', '52', '258333.33', '2026-11-24 22:38:36');
INSERT INTO `payment_allocations` (`id`, `payment_id`, `schedule_id`, `allocated_amount`, `created_at`) VALUES ('3', '1', '53', '103333.34', '2026-11-24 22:38:36');
INSERT INTO `payment_allocations` (`id`, `payment_id`, `schedule_id`, `allocated_amount`, `created_at`) VALUES ('4', '2', '53', '154999.99', '2026-11-24 22:38:36');
INSERT INTO `payment_allocations` (`id`, `payment_id`, `schedule_id`, `allocated_amount`, `created_at`) VALUES ('5', '2', '54', '95000.01', '2026-11-24 22:38:36');

DROP TABLE IF EXISTS `payment_reversals`;
CREATE TABLE `payment_reversals` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `payment_id` int(10) unsigned NOT NULL,
  `reversal_reason` text NOT NULL,
  `reversed_by` int(10) unsigned NOT NULL,
  `reversal_date` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_pr_payment` (`payment_id`),
  KEY `fk_pr_user` (`reversed_by`),
  CONSTRAINT `fk_pr_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`),
  CONSTRAINT `fk_pr_user` FOREIGN KEY (`reversed_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `payment_reversals` (`id`, `payment_id`, `reversal_reason`, `reversed_by`, `reversal_date`) VALUES ('1', '2', 'Online transfer bounce test reversal', '1', '2026-11-24 22:38:36');

DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `receipt_number` varchar(50) NOT NULL,
  `society_id` int(10) unsigned NOT NULL,
  `booking_id` int(10) unsigned NOT NULL,
  `customer_id` int(10) unsigned NOT NULL,
  `payment_date` date NOT NULL,
  `payment_amount` decimal(15,2) NOT NULL,
  `allocated_amount` decimal(15,2) NOT NULL,
  `advance_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `payment_method` enum('cash','cheque','bank_transfer','online','jazzcash','easypaisa','pay_order') NOT NULL,
  `cash_bank_account_id` int(10) unsigned NOT NULL,
  `transaction_ref` varchar(100) DEFAULT NULL,
  `cheque_clearance_date` date DEFAULT NULL,
  `recovery_officer_id` int(10) unsigned DEFAULT NULL,
  `status` enum('posted','cleared','bounced','reversed') NOT NULL DEFAULT 'posted',
  `remarks` text DEFAULT NULL,
  `idempotency_key` varchar(64) DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `receipt_number` (`receipt_number`),
  UNIQUE KEY `idempotency_key` (`idempotency_key`),
  KEY `idx_pay_soc_date` (`society_id`,`payment_date`),
  KEY `fk_pay_booking` (`booking_id`),
  KEY `fk_pay_customer` (`customer_id`),
  KEY `fk_pay_account` (`cash_bank_account_id`),
  KEY `fk_pay_officer` (`recovery_officer_id`),
  KEY `fk_pay_created_by` (`created_by`),
  CONSTRAINT `fk_pay_account` FOREIGN KEY (`cash_bank_account_id`) REFERENCES `accounts` (`id`),
  CONSTRAINT `fk_pay_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`),
  CONSTRAINT `fk_pay_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_pay_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_pay_officer` FOREIGN KEY (`recovery_officer_id`) REFERENCES `recovery_officers` (`id`),
  CONSTRAINT `fk_pay_society` FOREIGN KEY (`society_id`) REFERENCES `societies` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `payments` (`id`, `receipt_number`, `society_id`, `booking_id`, `customer_id`, `payment_date`, `payment_amount`, `allocated_amount`, `advance_amount`, `payment_method`, `cash_bank_account_id`, `transaction_ref`, `cheque_clearance_date`, `recovery_officer_id`, `status`, `remarks`, `idempotency_key`, `created_by`, `created_at`) VALUES ('1', 'REC-GVR-01-2026-0001', '1', '2', '2', '2026-11-24', '620000.00', '620000.00', '0.00', 'cash', '1', 'COUNTER-CASH-001', NULL, NULL, 'posted', 'Down payment settlement test', NULL, '1', '2026-11-24 22:38:36');
INSERT INTO `payments` (`id`, `receipt_number`, `society_id`, `booking_id`, `customer_id`, `payment_date`, `payment_amount`, `allocated_amount`, `advance_amount`, `payment_method`, `cash_bank_account_id`, `transaction_ref`, `cheque_clearance_date`, `recovery_officer_id`, `status`, `remarks`, `idempotency_key`, `created_by`, `created_at`) VALUES ('2', 'REC-GVR-01-2026-0002', '1', '2', '2', '2026-11-24', '250000.00', '250000.00', '0.00', 'bank_transfer', '3', 'HBL-ONLINE-98765', NULL, NULL, 'reversed', 'Multiple installment + advance test', NULL, '1', '2026-11-24 22:38:36');

DROP TABLE IF EXISTS `permissions`;
CREATE TABLE `permissions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `permission_code` varchar(80) NOT NULL,
  `module` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permission_code` (`permission_code`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `permissions` (`id`, `permission_code`, `module`, `description`) VALUES ('1', 'societies_manage', 'societies', 'Create, update and manage housing societies');
INSERT INTO `permissions` (`id`, `permission_code`, `module`, `description`) VALUES ('2', 'masters_manage', 'masters', 'Manage phases, blocks, plot types, sizes and charges');
INSERT INTO `permissions` (`id`, `permission_code`, `module`, `description`) VALUES ('3', 'plots_manage', 'plots', 'Create, edit, import and update plot inventory');
INSERT INTO `permissions` (`id`, `permission_code`, `module`, `description`) VALUES ('4', 'customers_manage', 'customers', 'Register and manage buyers, documents and KYC');
INSERT INTO `permissions` (`id`, `permission_code`, `module`, `description`) VALUES ('5', 'bookings_create', 'bookings', 'Create new plot bookings and installment plans');
INSERT INTO `permissions` (`id`, `permission_code`, `module`, `description`) VALUES ('6', 'bookings_view', 'bookings', 'View bookings and installment schedules');
INSERT INTO `permissions` (`id`, `permission_code`, `module`, `description`) VALUES ('7', 'bookings_cancel', 'bookings', 'Initiate or approve plot booking cancellations');
INSERT INTO `permissions` (`id`, `permission_code`, `module`, `description`) VALUES ('8', 'payments_collect', 'recoveries', 'Collect and post customer payments and receipts');
INSERT INTO `permissions` (`id`, `permission_code`, `module`, `description`) VALUES ('9', 'payments_reverse', 'recoveries', 'Reverse mistaken payments with audit reason');
INSERT INTO `permissions` (`id`, `permission_code`, `module`, `description`) VALUES ('10', 'transfers_manage', 'transfers', 'Execute plot ownership transfers and fee collection');
INSERT INTO `permissions` (`id`, `permission_code`, `module`, `description`) VALUES ('11', 'recovery_manage', 'recoveries', 'Assign recovery officers, log visits and promises to pay');
INSERT INTO `permissions` (`id`, `permission_code`, `module`, `description`) VALUES ('12', 'accounts_manage', 'accounts', 'Post journal entries, manage accounts and bank books');
INSERT INTO `permissions` (`id`, `permission_code`, `module`, `description`) VALUES ('13', 'reports_view', 'reports', 'Generate and export financial and operational reports');
INSERT INTO `permissions` (`id`, `permission_code`, `module`, `description`) VALUES ('14', 'settings_manage', 'settings', 'Manage system settings and user credentials');

DROP TABLE IF EXISTS `phases`;
CREATE TABLE `phases` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `society_id` int(10) unsigned NOT NULL,
  `phase_name` varchar(100) NOT NULL,
  `phase_code` varchar(30) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_soc_phase` (`society_id`,`phase_name`),
  CONSTRAINT `fk_phase_society` FOREIGN KEY (`society_id`) REFERENCES `societies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `phases` (`id`, `society_id`, `phase_name`, `phase_code`, `description`, `status`, `created_at`) VALUES ('1', '1', 'Phase 1 - Executive', 'PH1', 'Prime entrance sector with underground utilities', 'active', '2026-11-24 22:02:51');
INSERT INTO `phases` (`id`, `society_id`, `phase_name`, `phase_code`, `description`, `status`, `created_at`) VALUES ('2', '1', 'Phase 2 - Overseas', 'PH2', 'Luxury sector with community club and lake view', 'active', '2026-11-24 22:02:51');

DROP TABLE IF EXISTS `plot_price_history`;
CREATE TABLE `plot_price_history` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `plot_id` int(10) unsigned NOT NULL,
  `old_base_price` decimal(15,2) NOT NULL,
  `new_base_price` decimal(15,2) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `changed_by` int(10) unsigned NOT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_pph_plot` (`plot_id`),
  KEY `fk_pph_user` (`changed_by`),
  CONSTRAINT `fk_pph_plot` FOREIGN KEY (`plot_id`) REFERENCES `plots` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pph_user` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `plot_price_history` (`id`, `plot_id`, `old_base_price`, `new_base_price`, `reason`, `changed_by`, `changed_at`) VALUES ('1', '1', '2500000.00', '2700000.00', 'Phase 2 test price revision', '1', '2026-11-24 22:13:02');

DROP TABLE IF EXISTS `plot_sizes`;
CREATE TABLE `plot_sizes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `society_id` int(10) unsigned DEFAULT NULL,
  `name` varchar(50) NOT NULL,
  `size_value` decimal(10,2) NOT NULL,
  `unit` enum('Marla','Kanal','Sq. Yards','Sq. Feet') NOT NULL DEFAULT 'Marla',
  `dimension_details` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  KEY `fk_plot_size_society` (`society_id`),
  CONSTRAINT `fk_plot_size_society` FOREIGN KEY (`society_id`) REFERENCES `societies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `plot_sizes` (`id`, `society_id`, `name`, `size_value`, `unit`, `dimension_details`, `status`) VALUES ('1', NULL, '3 Marla', '3.00', 'Marla', '20 x 35', 'active');
INSERT INTO `plot_sizes` (`id`, `society_id`, `name`, `size_value`, `unit`, `dimension_details`, `status`) VALUES ('2', NULL, '5 Marla', '5.00', 'Marla', '25 x 45', 'active');
INSERT INTO `plot_sizes` (`id`, `society_id`, `name`, `size_value`, `unit`, `dimension_details`, `status`) VALUES ('3', NULL, '7 Marla', '7.00', 'Marla', '30 x 55', 'active');
INSERT INTO `plot_sizes` (`id`, `society_id`, `name`, `size_value`, `unit`, `dimension_details`, `status`) VALUES ('4', NULL, '10 Marla', '10.00', 'Marla', '35 x 65', 'active');
INSERT INTO `plot_sizes` (`id`, `society_id`, `name`, `size_value`, `unit`, `dimension_details`, `status`) VALUES ('5', NULL, '1 Kanal', '20.00', 'Marla', '50 x 90', 'active');
INSERT INTO `plot_sizes` (`id`, `society_id`, `name`, `size_value`, `unit`, `dimension_details`, `status`) VALUES ('6', NULL, '2 Kanal', '40.00', 'Marla', '75 x 120', 'active');
INSERT INTO `plot_sizes` (`id`, `society_id`, `name`, `size_value`, `unit`, `dimension_details`, `status`) VALUES ('7', NULL, '4 Marla Commercial', '4.00', 'Marla', '30 x 30', 'active');
INSERT INTO `plot_sizes` (`id`, `society_id`, `name`, `size_value`, `unit`, `dimension_details`, `status`) VALUES ('8', NULL, '8 Marla Commercial', '8.00', 'Marla', '40 x 45', 'active');

DROP TABLE IF EXISTS `plot_transfers`;
CREATE TABLE `plot_transfers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `transfer_number` varchar(50) NOT NULL,
  `society_id` int(10) unsigned NOT NULL,
  `plot_id` int(10) unsigned NOT NULL,
  `booking_id` int(10) unsigned NOT NULL,
  `from_customer_id` int(10) unsigned NOT NULL,
  `to_customer_id` int(10) unsigned NOT NULL,
  `transfer_date` date NOT NULL,
  `transfer_fee` decimal(15,2) NOT NULL DEFAULT 0.00,
  `transfer_fee_status` enum('paid','waived','pending') NOT NULL DEFAULT 'paid',
  `pending_balance_transferred` decimal(15,2) NOT NULL DEFAULT 0.00,
  `new_booking_id` int(10) unsigned DEFAULT NULL,
  `approval_status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `approved_by` int(10) unsigned DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `transfer_number` (`transfer_number`),
  KEY `fk_trf_society` (`society_id`),
  KEY `fk_trf_plot` (`plot_id`),
  KEY `fk_trf_booking` (`booking_id`),
  KEY `fk_trf_from_cust` (`from_customer_id`),
  KEY `fk_trf_to_cust` (`to_customer_id`),
  KEY `fk_trf_new_bk` (`new_booking_id`),
  KEY `fk_trf_approved_by` (`approved_by`),
  CONSTRAINT `fk_trf_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_trf_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`),
  CONSTRAINT `fk_trf_from_cust` FOREIGN KEY (`from_customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_trf_new_bk` FOREIGN KEY (`new_booking_id`) REFERENCES `bookings` (`id`),
  CONSTRAINT `fk_trf_plot` FOREIGN KEY (`plot_id`) REFERENCES `plots` (`id`),
  CONSTRAINT `fk_trf_society` FOREIGN KEY (`society_id`) REFERENCES `societies` (`id`),
  CONSTRAINT `fk_trf_to_cust` FOREIGN KEY (`to_customer_id`) REFERENCES `customers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `plot_types`;
CREATE TABLE `plot_types` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `society_id` int(10) unsigned DEFAULT NULL,
  `name` varchar(50) NOT NULL,
  `code` varchar(20) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  KEY `fk_plot_type_society` (`society_id`),
  CONSTRAINT `fk_plot_type_society` FOREIGN KEY (`society_id`) REFERENCES `societies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `plot_types` (`id`, `society_id`, `name`, `code`, `description`, `status`) VALUES ('1', NULL, 'Residential', 'RES', 'Standard residential dwelling plots', 'active');
INSERT INTO `plot_types` (`id`, `society_id`, `name`, `code`, `description`, `status`) VALUES ('2', NULL, 'Commercial', 'COM', 'Commercial avenues and shopping strips', 'active');
INSERT INTO `plot_types` (`id`, `society_id`, `name`, `code`, `description`, `status`) VALUES ('3', NULL, 'Industrial', 'IND', 'Industrial and warehousing zones', 'active');
INSERT INTO `plot_types` (`id`, `society_id`, `name`, `code`, `description`, `status`) VALUES ('4', NULL, 'Farm House', 'FRM', 'Agricultural and luxury farm plots', 'active');

DROP TABLE IF EXISTS `plots`;
CREATE TABLE `plots` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `society_id` int(10) unsigned NOT NULL,
  `phase_id` int(10) unsigned NOT NULL,
  `block_id` int(10) unsigned NOT NULL,
  `plot_type_id` int(10) unsigned NOT NULL,
  `plot_size_id` int(10) unsigned NOT NULL,
  `plot_number` varchar(50) NOT NULL,
  `street_road` varchar(100) DEFAULT NULL,
  `sector_zone` varchar(50) DEFAULT NULL,
  `is_corner` tinyint(1) NOT NULL DEFAULT 0,
  `is_boulevard` tinyint(1) NOT NULL DEFAULT 0,
  `is_park_facing` tinyint(1) NOT NULL DEFAULT 0,
  `base_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `development_charges` decimal(15,2) NOT NULL DEFAULT 0.00,
  `additional_charges` decimal(15,2) NOT NULL DEFAULT 0.00,
  `discount_allowed` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('available','reserved','booked','sold','transferred','cancelled','blocked') NOT NULL DEFAULT 'available',
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_block_plot_num` (`block_id`,`plot_number`),
  KEY `idx_plot_search` (`society_id`,`status`,`plot_type_id`,`plot_size_id`),
  KEY `fk_plot_phase` (`phase_id`),
  KEY `fk_plot_type` (`plot_type_id`),
  KEY `fk_plot_size` (`plot_size_id`),
  CONSTRAINT `fk_plot_block` FOREIGN KEY (`block_id`) REFERENCES `blocks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_plot_phase` FOREIGN KEY (`phase_id`) REFERENCES `phases` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_plot_size` FOREIGN KEY (`plot_size_id`) REFERENCES `plot_sizes` (`id`),
  CONSTRAINT `fk_plot_society` FOREIGN KEY (`society_id`) REFERENCES `societies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_plot_type` FOREIGN KEY (`plot_type_id`) REFERENCES `plot_types` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `plots` (`id`, `society_id`, `phase_id`, `block_id`, `plot_type_id`, `plot_size_id`, `plot_number`, `street_road`, `sector_zone`, `is_corner`, `is_boulevard`, `is_park_facing`, `base_price`, `development_charges`, `additional_charges`, `discount_allowed`, `total_price`, `status`, `remarks`, `created_at`, `updated_at`) VALUES ('1', '1', '1', '1', '1', '2', 'TEST-P2-01', 'Boulevard North', 'Zone 1', '1', '0', '0', '2700000.00', '400000.00', '100000.00', '50000.00', '3150000.00', 'booked', 'Test automated plot', '2026-11-24 22:13:02', '2026-11-24 22:27:29');
INSERT INTO `plots` (`id`, `society_id`, `phase_id`, `block_id`, `plot_type_id`, `plot_size_id`, `plot_number`, `street_road`, `sector_zone`, `is_corner`, `is_boulevard`, `is_park_facing`, `base_price`, `development_charges`, `additional_charges`, `discount_allowed`, `total_price`, `status`, `remarks`, `created_at`, `updated_at`) VALUES ('2', '1', '1', '1', '1', '2', 'IMPORT-01', 'Street 10', NULL, '0', '0', '0', '2600000.00', '400000.00', '0.00', '0.00', '3000000.00', 'available', 'Bulk CSV Test 1', '2026-11-24 22:13:03', '2026-11-24 22:13:03');
INSERT INTO `plots` (`id`, `society_id`, `phase_id`, `block_id`, `plot_type_id`, `plot_size_id`, `plot_number`, `street_road`, `sector_zone`, `is_corner`, `is_boulevard`, `is_park_facing`, `base_price`, `development_charges`, `additional_charges`, `discount_allowed`, `total_price`, `status`, `remarks`, `created_at`, `updated_at`) VALUES ('3', '1', '1', '2', '1', '4', 'IMPORT-02', 'Avenue 2', NULL, '1', '0', '0', '4800000.00', '600000.00', '0.00', '0.00', '5400000.00', 'available', 'Bulk CSV Test 2', '2026-11-24 22:13:03', '2026-11-24 22:13:03');

DROP TABLE IF EXISTS `promises_to_pay`;
CREATE TABLE `promises_to_pay` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` int(10) unsigned NOT NULL,
  `customer_id` int(10) unsigned NOT NULL,
  `recovery_officer_id` int(10) unsigned NOT NULL,
  `promise_date` date NOT NULL,
  `promised_amount` decimal(15,2) NOT NULL,
  `contact_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('pending','fulfilled','broken','rescheduled') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_ptp_booking` (`booking_id`),
  KEY `fk_ptp_customer` (`customer_id`),
  KEY `fk_ptp_officer` (`recovery_officer_id`),
  CONSTRAINT `fk_ptp_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`),
  CONSTRAINT `fk_ptp_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_ptp_officer` FOREIGN KEY (`recovery_officer_id`) REFERENCES `recovery_officers` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `promises_to_pay` (`id`, `booking_id`, `customer_id`, `recovery_officer_id`, `promise_date`, `promised_amount`, `contact_date`, `notes`, `status`, `created_at`) VALUES ('1', '2', '2', '1', '2026-11-29', '150000.00', '2026-11-24', 'Customer agreed to pay Rs. 150,000 within 5 days', 'fulfilled', '2026-11-24 22:43:23');
INSERT INTO `promises_to_pay` (`id`, `booking_id`, `customer_id`, `recovery_officer_id`, `promise_date`, `promised_amount`, `contact_date`, `notes`, `status`, `created_at`) VALUES ('2', '2', '2', '1', '2026-11-22', '50000.00', '2026-11-14', 'Follow-up call commitment test', 'broken', '2026-11-24 22:43:23');

DROP TABLE IF EXISTS `receipt_sequences`;
CREATE TABLE `receipt_sequences` (
  `society_id` int(10) unsigned NOT NULL,
  `fiscal_year` varchar(10) NOT NULL,
  `last_number` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`society_id`,`fiscal_year`),
  CONSTRAINT `fk_rs_society` FOREIGN KEY (`society_id`) REFERENCES `societies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `receipt_sequences` (`society_id`, `fiscal_year`, `last_number`) VALUES ('1', '2026', '2');

DROP TABLE IF EXISTS `recovery_officers`;
CREATE TABLE `recovery_officers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `society_id` int(10) unsigned NOT NULL,
  `assigned_blocks` text DEFAULT NULL,
  `recovery_target` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  KEY `fk_ro_user` (`user_id`),
  KEY `fk_ro_society` (`society_id`),
  CONSTRAINT `fk_ro_society` FOREIGN KEY (`society_id`) REFERENCES `societies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ro_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `recovery_officers` (`id`, `user_id`, `society_id`, `assigned_blocks`, `recovery_target`, `status`) VALUES ('1', '3', '1', '[\"BLK-A\",\"BLK-B\",\"BLK-C\"]', '7500000.00', 'active');

DROP TABLE IF EXISTS `role_permissions`;
CREATE TABLE `role_permissions` (
  `role_id` int(10) unsigned NOT NULL,
  `permission_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `fk_rp_permission` (`permission_id`),
  CONSTRAINT `fk_rp_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '1');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '2');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '3');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '4');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '5');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '6');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '7');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '8');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '9');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '10');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '11');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '12');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '13');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '14');

DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `roles` (`id`, `name`, `description`, `created_at`) VALUES ('1', 'Super Admin', 'Full unrestricted enterprise-wide access across all societies', '2026-11-24 22:02:51');
INSERT INTO `roles` (`id`, `name`, `description`, `created_at`) VALUES ('2', 'Society Admin', 'Administrative and operational management for designated societies', '2026-11-24 22:02:51');
INSERT INTO `roles` (`id`, `name`, `description`, `created_at`) VALUES ('3', 'Sales Manager', 'Manages bookings, discount approvals, plot inventory, and sales staff', '2026-11-24 22:02:51');
INSERT INTO `roles` (`id`, `name`, `description`, `created_at`) VALUES ('4', 'Sales Officer', 'Creates customer leads, manages plot bookings and installments', '2026-11-24 22:02:51');
INSERT INTO `roles` (`id`, `name`, `description`, `created_at`) VALUES ('5', 'Recovery Manager', 'Supervises recovery staff, overdue collections, and payment schedules', '2026-11-24 22:02:51');
INSERT INTO `roles` (`id`, `name`, `description`, `created_at`) VALUES ('6', 'Recovery Officer', 'Field recovery agent, records visits, promises-to-pay, collections', '2026-11-24 22:02:51');
INSERT INTO `roles` (`id`, `name`, `description`, `created_at`) VALUES ('7', 'Accountant', 'Chart of accounts, journal entries, vouchers, cash/bank reconciliation', '2026-11-24 22:02:51');
INSERT INTO `roles` (`id`, `name`, `description`, `created_at`) VALUES ('8', 'Auditor', 'Read-only financial and operational auditing capabilities', '2026-11-24 22:02:51');

DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `society_id` int(10) unsigned DEFAULT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_group` varchar(50) NOT NULL DEFAULT 'general',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_soc_setting` (`society_id`,`setting_key`),
  CONSTRAINT `fk_set_society` FOREIGN KEY (`society_id`) REFERENCES `societies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `societies`;
CREATE TABLE `societies` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `society_code` varchar(20) NOT NULL,
  `name` varchar(150) NOT NULL,
  `location` varchar(255) NOT NULL,
  `address` text DEFAULT NULL,
  `contact_number` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `registration_number` varchar(100) DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `society_code` (`society_code`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `societies` (`id`, `society_code`, `name`, `location`, `address`, `contact_number`, `email`, `registration_number`, `logo_path`, `start_date`, `status`, `remarks`, `created_at`, `updated_at`) VALUES ('1', 'GVR-01', 'Green Valley Residency', 'Main Expressway, Islamabad/Rawalpindi', 'Sector 14-B, Green Valley Expressway', '+92 51 9876543', 'info@greenvalley.local', 'RDA/HS/2024/98', NULL, '2024-01-01', 'active', 'Official Test Society for verified sales and recovery workflows', '2026-11-24 22:02:51', '2026-11-24 22:02:51');

DROP TABLE IF EXISTS `user_society_access`;
CREATE TABLE `user_society_access` (
  `user_id` int(10) unsigned NOT NULL,
  `society_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`user_id`,`society_id`),
  KEY `fk_usa_society` (`society_id`),
  CONSTRAINT `fk_usa_society` FOREIGN KEY (`society_id`) REFERENCES `societies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_usa_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `user_society_access` (`user_id`, `society_id`) VALUES ('1', '1');
INSERT INTO `user_society_access` (`user_id`, `society_id`) VALUES ('2', '1');
INSERT INTO `user_society_access` (`user_id`, `society_id`) VALUES ('3', '1');
INSERT INTO `user_society_access` (`user_id`, `society_id`) VALUES ('4', '1');

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `role_id` int(10) unsigned NOT NULL,
  `is_super_admin` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `username` (`username`),
  KEY `fk_user_role` (`role_id`),
  CONSTRAINT `fk_user_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`id`, `name`, `email`, `username`, `password_hash`, `phone`, `role_id`, `is_super_admin`, `status`, `last_login`, `created_at`) VALUES ('1', 'System Administrator', 'admin@hsms.local', 'admin', '$2y$10$ZRslM4qZn0cvDBdynL7mbOAMm2wxc8iSqZaGCU4p48/ZPJhrl9pD2', '+92 300 1234567', '1', '1', 'active', '2026-11-24 22:08:10', '2026-11-24 22:02:51');
INSERT INTO `users` (`id`, `name`, `email`, `username`, `password_hash`, `phone`, `role_id`, `is_super_admin`, `status`, `last_login`, `created_at`) VALUES ('2', 'Tariq Mehmood (Sales)', 'tariq@hsms.local', 'tariq_sales', '$2y$10$2W5jvGE6pfk3AL2EiVqqNef2qQkmJf4yMLYjjMHJ12C3QLl2qTWGK', '+92 301 2345678', '3', '0', 'active', NULL, '2026-11-24 22:02:51');
INSERT INTO `users` (`id`, `name`, `email`, `username`, `password_hash`, `phone`, `role_id`, `is_super_admin`, `status`, `last_login`, `created_at`) VALUES ('3', 'Rashid Khan (Recovery)', 'rashid@hsms.local', 'rashid_recovery', '$2y$10$2W5jvGE6pfk3AL2EiVqqNef2qQkmJf4yMLYjjMHJ12C3QLl2qTWGK', '+92 302 3456789', '6', '0', 'active', NULL, '2026-11-24 22:02:51');
INSERT INTO `users` (`id`, `name`, `email`, `username`, `password_hash`, `phone`, `role_id`, `is_super_admin`, `status`, `last_login`, `created_at`) VALUES ('4', 'Zubair Ahmed (Accounts)', 'zubair@hsms.local', 'zubair_acct', '$2y$10$2W5jvGE6pfk3AL2EiVqqNef2qQkmJf4yMLYjjMHJ12C3QLl2qTWGK', '+92 303 4567890', '7', '0', 'active', NULL, '2026-11-24 22:02:51');

SET FOREIGN_KEY_CHECKS=1;
