-- Housing Society Management System (HSMS) - Complete Database Schema
-- Charset: utf8mb4 | Collation: utf8mb4_unicode_ci | Engine: InnoDB

CREATE DATABASE IF NOT EXISTS `hsms_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `hsms_db`;

-- 1. SOCIETIES & PROJECT STRUCTURE
CREATE TABLE IF NOT EXISTS `societies` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `society_code` VARCHAR(20) NOT NULL UNIQUE,
  `name` VARCHAR(150) NOT NULL,
  `location` VARCHAR(255) NOT NULL,
  `address` TEXT NULL,
  `contact_number` VARCHAR(50) NOT NULL,
  `email` VARCHAR(100) NULL,
  `registration_number` VARCHAR(100) NULL,
  `logo_path` VARCHAR(255) NULL,
  `start_date` DATE NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `remarks` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `phases` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `society_id` INT UNSIGNED NOT NULL,
  `phase_name` VARCHAR(100) NOT NULL,
  `phase_code` VARCHAR(30) NOT NULL,
  `description` TEXT NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_soc_phase` (`society_id`, `phase_name`),
  CONSTRAINT `fk_phase_society` FOREIGN KEY (`society_id`) REFERENCES `societies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `blocks` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `society_id` INT UNSIGNED NOT NULL,
  `phase_id` INT UNSIGNED NOT NULL,
  `block_name` VARCHAR(100) NOT NULL,
  `block_code` VARCHAR(30) NOT NULL,
  `description` TEXT NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_phase_block` (`phase_id`, `block_name`),
  CONSTRAINT `fk_block_society` FOREIGN KEY (`society_id`) REFERENCES `societies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_block_phase` FOREIGN KEY (`phase_id`) REFERENCES `phases` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `plot_types` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `society_id` INT UNSIGNED NULL,
  `name` VARCHAR(50) NOT NULL,
  `code` VARCHAR(20) NOT NULL,
  `description` VARCHAR(255) NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  CONSTRAINT `fk_plot_type_society` FOREIGN KEY (`society_id`) REFERENCES `societies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `plot_sizes` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `society_id` INT UNSIGNED NULL,
  `name` VARCHAR(50) NOT NULL,
  `size_value` DECIMAL(10,2) NOT NULL,
  `unit` ENUM('Marla', 'Kanal', 'Sq. Yards', 'Sq. Feet') NOT NULL DEFAULT 'Marla',
  `dimension_details` VARCHAR(100) NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  CONSTRAINT `fk_plot_size_society` FOREIGN KEY (`society_id`) REFERENCES `societies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. PLOTS & INVENTORY
CREATE TABLE IF NOT EXISTS `plots` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `society_id` INT UNSIGNED NOT NULL,
  `phase_id` INT UNSIGNED NOT NULL,
  `block_id` INT UNSIGNED NOT NULL,
  `plot_type_id` INT UNSIGNED NOT NULL,
  `plot_size_id` INT UNSIGNED NOT NULL,
  `plot_number` VARCHAR(50) NOT NULL,
  `street_road` VARCHAR(100) NULL,
  `sector_zone` VARCHAR(50) NULL,
  `is_corner` TINYINT(1) NOT NULL DEFAULT 0,
  `is_boulevard` TINYINT(1) NOT NULL DEFAULT 0,
  `is_park_facing` TINYINT(1) NOT NULL DEFAULT 0,
  `base_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `development_charges` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `additional_charges` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `discount_allowed` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `total_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('available', 'reserved', 'booked', 'sold', 'transferred', 'cancelled', 'blocked') NOT NULL DEFAULT 'available',
  `remarks` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_block_plot_num` (`block_id`, `plot_number`),
  INDEX `idx_plot_search` (`society_id`, `status`, `plot_type_id`, `plot_size_id`),
  CONSTRAINT `fk_plot_society` FOREIGN KEY (`society_id`) REFERENCES `societies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_plot_phase` FOREIGN KEY (`phase_id`) REFERENCES `phases` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_plot_block` FOREIGN KEY (`block_id`) REFERENCES `blocks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_plot_type` FOREIGN KEY (`plot_type_id`) REFERENCES `plot_types` (`id`),
  CONSTRAINT `fk_plot_size` FOREIGN KEY (`plot_size_id`) REFERENCES `plot_sizes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. USERS, ROLES & PERMISSIONS
CREATE TABLE IF NOT EXISTS `roles` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL UNIQUE,
  `description` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `permissions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `permission_code` VARCHAR(80) NOT NULL UNIQUE,
  `module` VARCHAR(50) NOT NULL,
  `description` VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `role_permissions` (
  `role_id` INT UNSIGNED NOT NULL,
  `permission_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`role_id`, `permission_id`),
  CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rp_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(30) NULL,
  `role_id` INT UNSIGNED NOT NULL,
  `is_super_admin` TINYINT(1) NOT NULL DEFAULT 0,
  `status` ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
  `last_login` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_user_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_society_access` (
  `user_id` INT UNSIGNED NOT NULL,
  `society_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`user_id`, `society_id`),
  CONSTRAINT `fk_usa_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_usa_society` FOREIGN KEY (`society_id`) REFERENCES `societies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `plot_price_history` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `plot_id` INT UNSIGNED NOT NULL,
  `old_base_price` DECIMAL(15,2) NOT NULL,
  `new_base_price` DECIMAL(15,2) NOT NULL,
  `reason` VARCHAR(255) NULL,
  `changed_by` INT UNSIGNED NOT NULL,
  `changed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_pph_plot` FOREIGN KEY (`plot_id`) REFERENCES `plots` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pph_user` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. CUSTOMERS & KYC
CREATE TABLE IF NOT EXISTS `customers` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `customer_id_code` VARCHAR(30) NOT NULL UNIQUE,
  `full_name` VARCHAR(150) NOT NULL,
  `father_husband_name` VARCHAR(150) NOT NULL,
  `cnic_passport` VARCHAR(30) NOT NULL,
  `mobile` VARCHAR(30) NOT NULL,
  `whatsapp` VARCHAR(30) NULL,
  `email` VARCHAR(100) NULL,
  `residential_address` TEXT NOT NULL,
  `permanent_address` TEXT NULL,
  `city` VARCHAR(100) NOT NULL,
  `country` VARCHAR(100) NOT NULL DEFAULT 'Pakistan',
  `nominee_name` VARCHAR(150) NULL,
  `nominee_relation` VARCHAR(50) NULL,
  `nominee_cnic` VARCHAR(30) NULL,
  `nominee_contact` VARCHAR(30) NULL,
  `photo_path` VARCHAR(255) NULL,
  `status` ENUM('active', 'inactive', 'blacklisted') NOT NULL DEFAULT 'active',
  `remarks` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_cust_cnic` (`cnic_passport`),
  INDEX `idx_cust_mobile` (`mobile`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `customer_documents` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `customer_id` INT UNSIGNED NOT NULL,
  `doc_type` VARCHAR(50) NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_cd_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. PLANS, BOOKINGS & INSTALLMENTS
CREATE TABLE IF NOT EXISTS `installment_plan_templates` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `society_id` INT UNSIGNED NULL,
  `plan_name` VARCHAR(100) NOT NULL,
  `plan_code` VARCHAR(30) NOT NULL,
  `duration_months` INT UNSIGNED NOT NULL,
  `down_payment_type` ENUM('percentage', 'fixed') NOT NULL DEFAULT 'percentage',
  `down_payment_value` DECIMAL(15,2) NOT NULL,
  `frequency` ENUM('monthly', 'quarterly', 'semi_annual', 'annual', 'custom') NOT NULL DEFAULT 'monthly',
  `total_installments` INT UNSIGNED NOT NULL,
  `grace_period_days` INT UNSIGNED NOT NULL DEFAULT 10,
  `late_fee_type` ENUM('fixed', 'percentage', 'none') NOT NULL DEFAULT 'none',
  `late_fee_value` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `description` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_ipt_society` FOREIGN KEY (`society_id`) REFERENCES `societies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bookings` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `booking_number` VARCHAR(50) NOT NULL UNIQUE,
  `society_id` INT UNSIGNED NOT NULL,
  `plot_id` INT UNSIGNED NOT NULL,
  `customer_id` INT UNSIGNED NOT NULL,
  `plan_template_id` INT UNSIGNED NULL,
  `booking_date` DATE NOT NULL,
  `base_price` DECIMAL(15,2) NOT NULL,
  `development_charges` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `extra_charges` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `discount_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `net_sale_price` DECIMAL(15,2) NOT NULL,
  `down_payment_amount` DECIMAL(15,2) NOT NULL,
  `down_payment_status` ENUM('pending', 'partially_paid', 'paid') NOT NULL DEFAULT 'pending',
  `total_paid` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `remaining_balance` DECIMAL(15,2) NOT NULL,
  `advance_balance` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `sales_officer_id` INT UNSIGNED NULL,
  `status` ENUM('active', 'transferred', 'cancelled', 'completed') NOT NULL DEFAULT 'active',
  `remarks` TEXT NULL,
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_booking_soc_cust` (`society_id`, `customer_id`, `plot_id`, `status`),
  CONSTRAINT `fk_bk_society` FOREIGN KEY (`society_id`) REFERENCES `societies` (`id`),
  CONSTRAINT `fk_bk_plot` FOREIGN KEY (`plot_id`) REFERENCES `plots` (`id`),
  CONSTRAINT `fk_bk_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_bk_template` FOREIGN KEY (`plan_template_id`) REFERENCES `installment_plan_templates` (`id`),
  CONSTRAINT `fk_bk_officer` FOREIGN KEY (`sales_officer_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_bk_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `installment_schedules` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `booking_id` INT UNSIGNED NOT NULL,
  `installment_no` INT UNSIGNED NOT NULL,
  `installment_title` VARCHAR(100) NOT NULL,
  `due_date` DATE NOT NULL,
  `amount_due` DECIMAL(15,2) NOT NULL,
  `principal_amount` DECIMAL(15,2) NOT NULL,
  `charges_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `amount_paid` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `balance_due` DECIMAL(15,2) NOT NULL,
  `late_fee_charged` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `late_fee_waived` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('pending', 'partially_paid', 'paid', 'overdue', 'waived') NOT NULL DEFAULT 'pending',
  `is_custom` TINYINT(1) NOT NULL DEFAULT 0,
  `paid_date` DATE NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_sched_due` (`booking_id`, `status`, `due_date`),
  CONSTRAINT `fk_sched_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `installment_revisions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `booking_id` INT UNSIGNED NOT NULL,
  `revision_number` INT UNSIGNED NOT NULL,
  `reason` TEXT NOT NULL,
  `snapshot_json` LONGTEXT NOT NULL,
  `approved_by` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_ir_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ir_user` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. CHART OF ACCOUNTS & DOUBLE-ENTRY
CREATE TABLE IF NOT EXISTS `accounts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `society_id` INT UNSIGNED NULL,
  `account_code` VARCHAR(30) NOT NULL UNIQUE,
  `account_name` VARCHAR(150) NOT NULL,
  `account_type` ENUM('asset', 'liability', 'equity', 'income', 'expense') NOT NULL,
  `sub_type` VARCHAR(50) NULL,
  `bank_name` VARCHAR(100) NULL,
  `account_number` VARCHAR(50) NULL,
  `opening_balance` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `current_balance` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `is_system` TINYINT(1) NOT NULL DEFAULT 0,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_acc_society` FOREIGN KEY (`society_id`) REFERENCES `societies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `journal_entries` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `entry_number` VARCHAR(50) NOT NULL UNIQUE,
  `society_id` INT UNSIGNED NOT NULL,
  `entry_date` DATE NOT NULL,
  `entry_type` ENUM('journal', 'receipt', 'payment', 'sale', 'transfer', 'cancellation', 'expense') NOT NULL,
  `reference_type` VARCHAR(50) NULL,
  `reference_id` INT UNSIGNED NULL,
  `narration` TEXT NOT NULL,
  `total_debit` DECIMAL(15,2) NOT NULL,
  `total_credit` DECIMAL(15,2) NOT NULL,
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_je_society` FOREIGN KEY (`society_id`) REFERENCES `societies` (`id`),
  CONSTRAINT `fk_je_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `journal_entry_lines` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `journal_entry_id` INT UNSIGNED NOT NULL,
  `account_id` INT UNSIGNED NOT NULL,
  `debit` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `credit` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `memo` VARCHAR(255) NULL,
  INDEX `idx_line_acc` (`account_id`, `journal_entry_id`),
  CONSTRAINT `fk_jel_entry` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_jel_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. RECOVERY OFFICERS, PAYMENTS & ALLOCATIONS
CREATE TABLE IF NOT EXISTS `recovery_officers` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `society_id` INT UNSIGNED NOT NULL,
  `assigned_blocks` TEXT NULL,
  `recovery_target` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  CONSTRAINT `fk_ro_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ro_society` FOREIGN KEY (`society_id`) REFERENCES `societies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `receipt_sequences` (
  `society_id` INT UNSIGNED NOT NULL,
  `fiscal_year` VARCHAR(10) NOT NULL,
  `last_number` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`society_id`, `fiscal_year`),
  CONSTRAINT `fk_rs_society` FOREIGN KEY (`society_id`) REFERENCES `societies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `receipt_number` VARCHAR(50) NOT NULL UNIQUE,
  `society_id` INT UNSIGNED NOT NULL,
  `booking_id` INT UNSIGNED NOT NULL,
  `customer_id` INT UNSIGNED NOT NULL,
  `payment_date` DATE NOT NULL,
  `payment_amount` DECIMAL(15,2) NOT NULL,
  `allocated_amount` DECIMAL(15,2) NOT NULL,
  `advance_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `payment_method` ENUM('cash', 'cheque', 'bank_transfer', 'online', 'jazzcash', 'easypaisa', 'pay_order') NOT NULL,
  `cash_bank_account_id` INT UNSIGNED NOT NULL,
  `transaction_ref` VARCHAR(100) NULL,
  `cheque_clearance_date` DATE NULL,
  `recovery_officer_id` INT UNSIGNED NULL,
  `status` ENUM('posted', 'cleared', 'bounced', 'reversed') NOT NULL DEFAULT 'posted',
  `remarks` TEXT NULL,
  `idempotency_key` VARCHAR(64) UNIQUE NULL,
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_pay_soc_date` (`society_id`, `payment_date`),
  CONSTRAINT `fk_pay_society` FOREIGN KEY (`society_id`) REFERENCES `societies` (`id`),
  CONSTRAINT `fk_pay_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`),
  CONSTRAINT `fk_pay_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_pay_account` FOREIGN KEY (`cash_bank_account_id`) REFERENCES `accounts` (`id`),
  CONSTRAINT `fk_pay_officer` FOREIGN KEY (`recovery_officer_id`) REFERENCES `recovery_officers` (`id`),
  CONSTRAINT `fk_pay_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payment_allocations` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `payment_id` INT UNSIGNED NOT NULL,
  `schedule_id` INT UNSIGNED NOT NULL,
  `allocated_amount` DECIMAL(15,2) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_pa_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pa_schedule` FOREIGN KEY (`schedule_id`) REFERENCES `installment_schedules` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payment_reversals` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `payment_id` INT UNSIGNED NOT NULL,
  `reversal_reason` TEXT NOT NULL,
  `reversed_by` INT UNSIGNED NOT NULL,
  `reversal_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_pr_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`),
  CONSTRAINT `fk_pr_user` FOREIGN KEY (`reversed_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. CUSTOMER LEDGER
CREATE TABLE IF NOT EXISTS `customer_ledger` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `society_id` INT UNSIGNED NOT NULL,
  `customer_id` INT UNSIGNED NOT NULL,
  `booking_id` INT UNSIGNED NOT NULL,
  `plot_id` INT UNSIGNED NOT NULL,
  `transaction_date` DATE NOT NULL,
  `transaction_type` ENUM('booking_debit', 'installment_due', 'payment_credit', 'advance_credit', 'advance_adjustment', 'charge_debit', 'discount_credit', 'waiver_credit', 'transfer_debit', 'transfer_credit', 'cancellation_credit', 'refund_debit', 'reversal') NOT NULL,
  `reference_id` INT UNSIGNED NULL,
  `voucher_no` VARCHAR(50) NULL,
  `description` VARCHAR(255) NOT NULL,
  `debit` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `credit` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `running_balance` DECIMAL(15,2) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_cust_lead` (`customer_id`, `booking_id`, `transaction_date`),
  CONSTRAINT `fk_cl_society` FOREIGN KEY (`society_id`) REFERENCES `societies` (`id`),
  CONSTRAINT `fk_cl_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_cl_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`),
  CONSTRAINT `fk_cl_plot` FOREIGN KEY (`plot_id`) REFERENCES `plots` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. PROMISES, TRANSFERS, CANCELLATIONS & EXPENSES
CREATE TABLE IF NOT EXISTS `promises_to_pay` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `booking_id` INT UNSIGNED NOT NULL,
  `customer_id` INT UNSIGNED NOT NULL,
  `recovery_officer_id` INT UNSIGNED NOT NULL,
  `promise_date` DATE NOT NULL,
  `promised_amount` DECIMAL(15,2) NOT NULL,
  `contact_date` DATE NOT NULL,
  `notes` TEXT NULL,
  `status` ENUM('pending', 'fulfilled', 'broken', 'rescheduled') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_ptp_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`),
  CONSTRAINT `fk_ptp_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_ptp_officer` FOREIGN KEY (`recovery_officer_id`) REFERENCES `recovery_officers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `plot_transfers` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `transfer_number` VARCHAR(50) NOT NULL UNIQUE,
  `society_id` INT UNSIGNED NOT NULL,
  `plot_id` INT UNSIGNED NOT NULL,
  `booking_id` INT UNSIGNED NOT NULL,
  `from_customer_id` INT UNSIGNED NOT NULL,
  `to_customer_id` INT UNSIGNED NOT NULL,
  `transfer_date` DATE NOT NULL,
  `transfer_fee` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `transfer_fee_status` ENUM('paid', 'waived', 'pending') NOT NULL DEFAULT 'paid',
  `pending_balance_transferred` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `new_booking_id` INT UNSIGNED NULL,
  `approval_status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  `approved_by` INT UNSIGNED NULL,
  `remarks` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_trf_society` FOREIGN KEY (`society_id`) REFERENCES `societies` (`id`),
  CONSTRAINT `fk_trf_plot` FOREIGN KEY (`plot_id`) REFERENCES `plots` (`id`),
  CONSTRAINT `fk_trf_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`),
  CONSTRAINT `fk_trf_from_cust` FOREIGN KEY (`from_customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_trf_to_cust` FOREIGN KEY (`to_customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_trf_new_bk` FOREIGN KEY (`new_booking_id`) REFERENCES `bookings` (`id`),
  CONSTRAINT `fk_trf_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cancellations` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `cancellation_number` VARCHAR(50) NOT NULL UNIQUE,
  `booking_id` INT UNSIGNED NOT NULL,
  `plot_id` INT UNSIGNED NOT NULL,
  `customer_id` INT UNSIGNED NOT NULL,
  `cancellation_date` DATE NOT NULL,
  `total_paid_at_cancel` DECIMAL(15,2) NOT NULL,
  `deduction_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `refund_amount_payable` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `refund_paid_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `refund_status` ENUM('pending', 'partially_refunded', 'fully_refunded') NOT NULL DEFAULT 'pending',
  `approval_status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'approved',
  `approved_by` INT UNSIGNED NOT NULL,
  `reason` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_cnl_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`),
  CONSTRAINT `fk_cnl_plot` FOREIGN KEY (`plot_id`) REFERENCES `plots` (`id`),
  CONSTRAINT `fk_cnl_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_cnl_user` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `expense_categories` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `society_id` INT UNSIGNED NULL,
  `name` VARCHAR(100) NOT NULL,
  `account_id` INT UNSIGNED NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  CONSTRAINT `fk_ec_society` FOREIGN KEY (`society_id`) REFERENCES `societies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ec_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `expenses` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `voucher_number` VARCHAR(50) NOT NULL UNIQUE,
  `society_id` INT UNSIGNED NOT NULL,
  `expense_category_id` INT UNSIGNED NOT NULL,
  `payment_account_id` INT UNSIGNED NOT NULL,
  `expense_date` DATE NOT NULL,
  `amount` DECIMAL(15,2) NOT NULL,
  `paid_to` VARCHAR(150) NOT NULL,
  `reference_no` VARCHAR(100) NULL,
  `description` TEXT NULL,
  `attachment_path` VARCHAR(255) NULL,
  `approval_status` ENUM('approved', 'pending', 'rejected') NOT NULL DEFAULT 'approved',
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_exp_society` FOREIGN KEY (`society_id`) REFERENCES `societies` (`id`),
  CONSTRAINT `fk_exp_category` FOREIGN KEY (`expense_category_id`) REFERENCES `expense_categories` (`id`),
  CONSTRAINT `fk_exp_account` FOREIGN KEY (`payment_account_id`) REFERENCES `accounts` (`id`),
  CONSTRAINT `fk_exp_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. AUDIT LOGS, NOTIFICATIONS & SETTINGS
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NULL,
  `society_id` INT UNSIGNED NULL,
  `action` VARCHAR(100) NOT NULL,
  `module` VARCHAR(50) NOT NULL,
  `record_id` INT UNSIGNED NULL,
  `old_values` LONGTEXT NULL,
  `new_values` LONGTEXT NULL,
  `ip_address` VARCHAR(50) NULL,
  `user_agent` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_audit_search` (`module`, `record_id`, `created_at`),
  CONSTRAINT `fk_al_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_al_society` FOREIGN KEY (`society_id`) REFERENCES `societies` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notification_logs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `society_id` INT UNSIGNED NOT NULL,
  `customer_id` INT UNSIGNED NULL,
  `recipient` VARCHAR(100) NOT NULL,
  `channel` ENUM('sms', 'whatsapp', 'email') NOT NULL,
  `message_type` VARCHAR(50) NOT NULL,
  `message_body` TEXT NOT NULL,
  `status` ENUM('sent', 'failed', 'pending', 'manual_link_generated') NOT NULL DEFAULT 'pending',
  `external_ref` VARCHAR(100) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_nl_society` FOREIGN KEY (`society_id`) REFERENCES `societies` (`id`),
  CONSTRAINT `fk_nl_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `settings` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `society_id` INT UNSIGNED NULL,
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` TEXT NULL,
  `setting_group` VARCHAR(50) NOT NULL DEFAULT 'general',
  UNIQUE KEY `uk_soc_setting` (`society_id`, `setting_key`),
  CONSTRAINT `fk_set_society` FOREIGN KEY (`society_id`) REFERENCES `societies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
