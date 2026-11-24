# Housing Society Management System (HSMS) - Database Schema Reference

Database Name: `hsms_db`  
Charset: `utf8mb4`  
Collation: `utf8mb4_unicode_ci`  
Engine: `InnoDB`

---

## 1. Core & Multi-Society Structure

### `societies`
- `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `society_code` VARCHAR(20) UNIQUE NOT NULL (e.g., 'GVR-01')
- `name` VARCHAR(150) NOT NULL
- `location` VARCHAR(255) NOT NULL
- `address` TEXT
- `contact_number` VARCHAR(50) NOT NULL
- `email` VARCHAR(100)
- `registration_number` VARCHAR(100)
- `logo_path` VARCHAR(255)
- `start_date` DATE
- `status` ENUM('active', 'inactive') DEFAULT 'active'
- `remarks` TEXT
- `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
- `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP

### `phases`
- `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `society_id` INT UNSIGNED NOT NULL (FK -> societies.id)
- `phase_name` VARCHAR(100) NOT NULL
- `phase_code` VARCHAR(30) NOT NULL
- `description` TEXT
- `status` ENUM('active', 'inactive') DEFAULT 'active'
- `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
- UNIQUE KEY `uk_society_phase` (`society_id`, `phase_name`)

### `blocks`
- `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `society_id` INT UNSIGNED NOT NULL (FK -> societies.id)
- `phase_id` INT UNSIGNED NOT NULL (FK -> phases.id)
- `block_name` VARCHAR(100) NOT NULL
- `block_code` VARCHAR(30) NOT NULL
- `description` TEXT
- `status` ENUM('active', 'inactive') DEFAULT 'active'
- `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
- UNIQUE KEY `uk_phase_block` (`phase_id`, `block_name`)

### `plot_types`
- `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `society_id` INT UNSIGNED NULL (FK -> societies.id) (NULL applies globally)
- `name` VARCHAR(50) NOT NULL (e.g., 'Residential', 'Commercial', 'Industrial')
- `code` VARCHAR(20) NOT NULL
- `description` VARCHAR(255)
- `status` ENUM('active', 'inactive') DEFAULT 'active'

### `plot_sizes`
- `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `society_id` INT UNSIGNED NULL (FK -> societies.id)
- `name` VARCHAR(50) NOT NULL (e.g., '5 Marla', '10 Marla', '1 Kanal', '250 Sq. Yards')
- `size_value` DECIMAL(10,2) NOT NULL
- `unit` ENUM('Marla', 'Kanal', 'Sq. Yards', 'Sq. Feet') DEFAULT 'Marla'
- `dimension_details` VARCHAR(100) (e.g., '25 x 50')
- `status` ENUM('active', 'inactive') DEFAULT 'active'

---

## 2. Plots & Inventory Management

### `plots`
- `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `society_id` INT UNSIGNED NOT NULL (FK -> societies.id)
- `phase_id` INT UNSIGNED NOT NULL (FK -> phases.id)
- `block_id` INT UNSIGNED NOT NULL (FK -> blocks.id)
- `plot_type_id` INT UNSIGNED NOT NULL (FK -> plot_types.id)
- `plot_size_id` INT UNSIGNED NOT NULL (FK -> plot_sizes.id)
- `plot_number` VARCHAR(50) NOT NULL
- `street_road` VARCHAR(100)
- `sector_zone` VARCHAR(50)
- `is_corner` TINYINT(1) DEFAULT 0
- `is_boulevard` TINYINT(1) DEFAULT 0
- `is_park_facing` TINYINT(1) DEFAULT 0
- `base_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00
- `development_charges` DECIMAL(15,2) NOT NULL DEFAULT 0.00
- `additional_charges` DECIMAL(15,2) NOT NULL DEFAULT 0.00
- `discount_allowed` DECIMAL(15,2) NOT NULL DEFAULT 0.00
- `total_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00
- `status` ENUM('available', 'reserved', 'booked', 'sold', 'transferred', 'cancelled', 'blocked') DEFAULT 'available'
- `remarks` TEXT
- `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
- `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
- UNIQUE KEY `uk_block_plot_num` (`block_id`, `plot_number`)
- INDEX `idx_plot_search` (`society_id`, `status`, `plot_type_id`, `plot_size_id`)

### `plot_price_history`
- `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `plot_id` INT UNSIGNED NOT NULL (FK -> plots.id)
- `old_base_price` DECIMAL(15,2) NOT NULL
- `new_base_price` DECIMAL(15,2) NOT NULL
- `reason` VARCHAR(255)
- `changed_by` INT UNSIGNED NOT NULL (FK -> users.id)
- `changed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP

---

## 3. Customer Master & KYC

### `customers`
- `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `customer_id_code` VARCHAR(30) UNIQUE NOT NULL (e.g., 'CUST-0001')
- `full_name` VARCHAR(150) NOT NULL
- `father_husband_name` VARCHAR(150) NOT NULL
- `cnic_passport` VARCHAR(30) NOT NULL
- `mobile` VARCHAR(30) NOT NULL
- `whatsapp` VARCHAR(30)
- `email` VARCHAR(100)
- `residential_address` TEXT NOT NULL
- `permanent_address` TEXT
- `city` VARCHAR(100) NOT NULL
- `country` VARCHAR(100) DEFAULT 'Pakistan'
- `nominee_name` VARCHAR(150)
- `nominee_relation` VARCHAR(50)
- `nominee_cnic` VARCHAR(30)
- `nominee_contact` VARCHAR(30)
- `photo_path` VARCHAR(255)
- `status` ENUM('active', 'inactive', 'blacklisted') DEFAULT 'active'
- `remarks` TEXT
- `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
- `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
- INDEX `idx_customer_cnic` (`cnic_passport`), INDEX `idx_customer_mobile` (`mobile`)

### `customer_documents`
- `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `customer_id` INT UNSIGNED NOT NULL (FK -> customers.id)
- `doc_type` VARCHAR(50) NOT NULL (e.g., 'CNIC Front', 'CNIC Back', 'Affidavit', 'Nominee CNIC')
- `file_path` VARCHAR(255) NOT NULL
- `file_name` VARCHAR(255) NOT NULL
- `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP

---

## 4. Installment Plan Templates & Schedules

### `installment_plan_templates`
- `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `society_id` INT UNSIGNED NULL (FK -> societies.id)
- `plan_name` VARCHAR(100) NOT NULL
- `plan_code` VARCHAR(30) NOT NULL
- `duration_months` INT UNSIGNED NOT NULL
- `down_payment_type` ENUM('percentage', 'fixed') DEFAULT 'percentage'
- `down_payment_value` DECIMAL(15,2) NOT NULL
- `frequency` ENUM('monthly', 'quarterly', 'semi_annual', 'annual', 'custom') DEFAULT 'monthly'
- `total_installments` INT UNSIGNED NOT NULL
- `grace_period_days` INT UNSIGNED DEFAULT 10
- `late_fee_type` ENUM('fixed', 'percentage', 'none') DEFAULT 'none'
- `late_fee_value` DECIMAL(10,2) DEFAULT 0.00
- `status` ENUM('active', 'inactive') DEFAULT 'active'
- `description` TEXT
- `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP

### `bookings`
- `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `booking_number` VARCHAR(50) UNIQUE NOT NULL (e.g., 'BK-2026-0001')
- `society_id` INT UNSIGNED NOT NULL (FK -> societies.id)
- `plot_id` INT UNSIGNED NOT NULL (FK -> plots.id)
- `customer_id` INT UNSIGNED NOT NULL (FK -> customers.id)
- `plan_template_id` INT UNSIGNED NULL (FK -> installment_plan_templates.id)
- `booking_date` DATE NOT NULL
- `base_price` DECIMAL(15,2) NOT NULL
- `development_charges` DECIMAL(15,2) NOT NULL DEFAULT 0.00
- `extra_charges` DECIMAL(15,2) NOT NULL DEFAULT 0.00
- `discount_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00
- `net_sale_price` DECIMAL(15,2) NOT NULL
- `down_payment_amount` DECIMAL(15,2) NOT NULL
- `down_payment_status` ENUM('pending', 'partially_paid', 'paid') DEFAULT 'pending'
- `total_paid` DECIMAL(15,2) NOT NULL DEFAULT 0.00
- `remaining_balance` DECIMAL(15,2) NOT NULL
- `advance_balance` DECIMAL(15,2) NOT NULL DEFAULT 0.00
- `sales_officer_id` INT UNSIGNED NULL (FK -> users.id)
- `status` ENUM('active', 'transferred', 'cancelled', 'completed') DEFAULT 'active'
- `remarks` TEXT
- `created_by` INT UNSIGNED NOT NULL (FK -> users.id)
- `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
- `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
- INDEX `idx_booking_search` (`society_id`, `customer_id`, `plot_id`, `status`)

### `installment_schedules`
- `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `booking_id` INT UNSIGNED NOT NULL (FK -> bookings.id)
- `installment_no` INT UNSIGNED NOT NULL (0 for Down Payment, 1..N for scheduled)
- `installment_title` VARCHAR(100) NOT NULL (e.g., 'Down Payment', 'Installment #1', 'Possession Charge')
- `due_date` DATE NOT NULL
- `amount_due` DECIMAL(15,2) NOT NULL
- `principal_amount` DECIMAL(15,2) NOT NULL
- `charges_amount` DECIMAL(15,2) DEFAULT 0.00
- `amount_paid` DECIMAL(15,2) NOT NULL DEFAULT 0.00
- `balance_due` DECIMAL(15,2) NOT NULL
- `late_fee_charged` DECIMAL(10,2) DEFAULT 0.00
- `late_fee_waived` DECIMAL(10,2) DEFAULT 0.00
- `status` ENUM('pending', 'partially_paid', 'paid', 'overdue', 'waived') DEFAULT 'pending'
- `is_custom` TINYINT(1) DEFAULT 0
- `paid_date` DATE NULL
- `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
- `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
- INDEX `idx_schedule_due` (`booking_id`, `status`, `due_date`)

### `installment_revisions`
- `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `booking_id` INT UNSIGNED NOT NULL (FK -> bookings.id)
- `revision_number` INT UNSIGNED NOT NULL
- `reason` TEXT NOT NULL
- `snapshot_json` LONGTEXT NOT NULL (complete old schedule snapshot)
- `approved_by` INT UNSIGNED NOT NULL (FK -> users.id)
- `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP

---

## 5. Payments, Allocations & Receipts

### `receipt_sequences`
- `society_id` INT UNSIGNED NOT NULL (FK -> societies.id)
- `fiscal_year` VARCHAR(10) NOT NULL
- `last_number` INT UNSIGNED NOT NULL DEFAULT 0
- PRIMARY KEY (`society_id`, `fiscal_year`)

### `payments`
- `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `receipt_number` VARCHAR(50) UNIQUE NOT NULL (e.g., 'REC-GVR-2026-0001')
- `society_id` INT UNSIGNED NOT NULL (FK -> societies.id)
- `booking_id` INT UNSIGNED NOT NULL (FK -> bookings.id)
- `customer_id` INT UNSIGNED NOT NULL (FK -> customers.id)
- `payment_date` DATE NOT NULL
- `payment_amount` DECIMAL(15,2) NOT NULL
- `allocated_amount` DECIMAL(15,2) NOT NULL
- `advance_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00
- `payment_method` ENUM('cash', 'cheque', 'bank_transfer', 'online', 'jazzcash', 'easypaisa', 'pay_order') NOT NULL
- `cash_bank_account_id` INT UNSIGNED NOT NULL (FK -> accounts.id)
- `transaction_ref` VARCHAR(100) (Cheque No / Transfer Ref)
- `cheque_clearance_date` DATE NULL
- `recovery_officer_id` INT UNSIGNED NULL (FK -> recovery_officers.id)
- `status` ENUM('posted', 'cleared', 'bounced', 'reversed') DEFAULT 'posted'
- `remarks` TEXT
- `idempotency_key` VARCHAR(64) UNIQUE NULL
- `created_by` INT UNSIGNED NOT NULL (FK -> users.id)
- `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
- INDEX `idx_payment_dates` (`society_id`, `payment_date`)

### `payment_allocations`
- `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `payment_id` INT UNSIGNED NOT NULL (FK -> payments.id)
- `schedule_id` INT UNSIGNED NOT NULL (FK -> installment_schedules.id)
- `allocated_amount` DECIMAL(15,2) NOT NULL
- `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP

### `payment_reversals`
- `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `payment_id` INT UNSIGNED NOT NULL (FK -> payments.id)
- `reversal_reason` TEXT NOT NULL
- `reversed_by` INT UNSIGNED NOT NULL (FK -> users.id)
- `reversal_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP

---

## 6. Ledgers & Double-Entry Accounting

### `customer_ledger`
- `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `society_id` INT UNSIGNED NOT NULL (FK -> societies.id)
- `customer_id` INT UNSIGNED NOT NULL (FK -> customers.id)
- `booking_id` INT UNSIGNED NOT NULL (FK -> bookings.id)
- `plot_id` INT UNSIGNED NOT NULL (FK -> plots.id)
- `transaction_date` DATE NOT NULL
- `transaction_type` ENUM('booking_debit', 'installment_due', 'payment_credit', 'advance_credit', 'advance_adjustment', 'charge_debit', 'discount_credit', 'waiver_credit', 'transfer_debit', 'transfer_credit', 'cancellation_credit', 'refund_debit', 'reversal') NOT NULL
- `reference_id` INT UNSIGNED NULL (Payment ID / Booking ID / Charge ID)
- `voucher_no` VARCHAR(50)
- `description` VARCHAR(255) NOT NULL
- `debit` DECIMAL(15,2) NOT NULL DEFAULT 0.00
- `credit` DECIMAL(15,2) NOT NULL DEFAULT 0.00
- `running_balance` DECIMAL(15,2) NOT NULL
- `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
- INDEX `idx_cust_ledger` (`customer_id`, `booking_id`, `transaction_date`)

### `accounts` (Chart of Accounts)
- `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `society_id` INT UNSIGNED NULL (FK -> societies.id) (NULL for global templates)
- `account_code` VARCHAR(30) UNIQUE NOT NULL (e.g., '1001', '1010', '2001', '4001')
- `account_name` VARCHAR(150) NOT NULL
- `account_type` ENUM('asset', 'liability', 'equity', 'income', 'expense') NOT NULL
- `sub_type` VARCHAR(50) (e.g., 'cash', 'bank', 'accounts_receivable', 'advances', 'sales_income')
- `bank_name` VARCHAR(100) NULL
- `account_number` VARCHAR(50) NULL
- `opening_balance` DECIMAL(15,2) NOT NULL DEFAULT 0.00
- `current_balance` DECIMAL(15,2) NOT NULL DEFAULT 0.00
- `is_system` TINYINT(1) DEFAULT 0
- `status` ENUM('active', 'inactive') DEFAULT 'active'
- `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP

### `journal_entries`
- `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `entry_number` VARCHAR(50) UNIQUE NOT NULL (e.g., 'JV-2026-0001')
- `society_id` INT UNSIGNED NOT NULL (FK -> societies.id)
- `entry_date` DATE NOT NULL
- `entry_type` ENUM('journal', 'receipt', 'payment', 'sale', 'transfer', 'cancellation', 'expense') NOT NULL
- `reference_type` VARCHAR(50) (e.g., 'booking', 'payment', 'expense', 'transfer')
- `reference_id` INT UNSIGNED NULL
- `narration` TEXT NOT NULL
- `total_debit` DECIMAL(15,2) NOT NULL
- `total_credit` DECIMAL(15,2) NOT NULL
- `created_by` INT UNSIGNED NOT NULL (FK -> users.id)
- `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP

### `journal_entry_lines`
- `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `journal_entry_id` INT UNSIGNED NOT NULL (FK -> journal_entries.id ON DELETE CASCADE)
- `account_id` INT UNSIGNED NOT NULL (FK -> accounts.id)
- `debit` DECIMAL(15,2) NOT NULL DEFAULT 0.00
- `credit` DECIMAL(15,2) NOT NULL DEFAULT 0.00
- `memo` VARCHAR(255)
- INDEX `idx_jv_account` (`account_id`, `journal_entry_id`)

---

## 7. Operational Modules: Recovery, Expenses, Transfers & Cancellations

### `recovery_officers`
- `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `user_id` INT UNSIGNED NOT NULL (FK -> users.id)
- `society_id` INT UNSIGNED NOT NULL (FK -> societies.id)
- `assigned_blocks` TEXT (JSON or comma separated)
- `recovery_target` DECIMAL(15,2) DEFAULT 0.00
- `status` ENUM('active', 'inactive') DEFAULT 'active'

### `promises_to_pay`
- `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `booking_id` INT UNSIGNED NOT NULL (FK -> bookings.id)
- `customer_id` INT UNSIGNED NOT NULL (FK -> customers.id)
- `recovery_officer_id` INT UNSIGNED NOT NULL (FK -> recovery_officers.id)
- `promise_date` DATE NOT NULL
- `promised_amount` DECIMAL(15,2) NOT NULL
- `contact_date` DATE NOT NULL
- `notes` TEXT
- `status` ENUM('pending', 'fulfilled', 'broken', 'rescheduled') DEFAULT 'pending'
- `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP

### `plot_transfers`
- `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `transfer_number` VARCHAR(50) UNIQUE NOT NULL (e.g., 'TRF-2026-0001')
- `society_id` INT UNSIGNED NOT NULL (FK -> societies.id)
- `plot_id` INT UNSIGNED NOT NULL (FK -> plots.id)
- `booking_id` INT UNSIGNED NOT NULL (FK -> bookings.id)
- `from_customer_id` INT UNSIGNED NOT NULL (FK -> customers.id)
- `to_customer_id` INT UNSIGNED NOT NULL (FK -> customers.id)
- `transfer_date` DATE NOT NULL
- `transfer_fee` DECIMAL(15,2) NOT NULL DEFAULT 0.00
- `transfer_fee_status` ENUM('paid', 'waived', 'pending') DEFAULT 'paid'
- `pending_balance_transferred` DECIMAL(15,2) NOT NULL DEFAULT 0.00
- `new_booking_id` INT UNSIGNED NULL (FK -> bookings.id)
- `approval_status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending'
- `approved_by` INT UNSIGNED NULL (FK -> users.id)
- `remarks` TEXT
- `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP

### `cancellations`
- `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `cancellation_number` VARCHAR(50) UNIQUE NOT NULL
- `booking_id` INT UNSIGNED NOT NULL (FK -> bookings.id)
- `plot_id` INT UNSIGNED NOT NULL (FK -> plots.id)
- `customer_id` INT UNSIGNED NOT NULL (FK -> customers.id)
- `cancellation_date` DATE NOT NULL
- `total_paid_at_cancel` DECIMAL(15,2) NOT NULL
- `deduction_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00
- `refund_amount_payable` DECIMAL(15,2) NOT NULL DEFAULT 0.00
- `refund_paid_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00
- `refund_status` ENUM('pending', 'partially_refunded', 'fully_refunded') DEFAULT 'pending'
- `approval_status` ENUM('pending', 'approved', 'rejected') DEFAULT 'approved'
- `approved_by` INT UNSIGNED NOT NULL (FK -> users.id)
- `reason` TEXT NOT NULL
- `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP

### `expense_categories`
- `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `society_id` INT UNSIGNED NULL (FK -> societies.id)
- `name` VARCHAR(100) NOT NULL
- `account_id` INT UNSIGNED NULL (FK -> accounts.id)
- `status` ENUM('active', 'inactive') DEFAULT 'active'

### `expenses`
- `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `voucher_number` VARCHAR(50) UNIQUE NOT NULL (e.g., 'EXP-2026-0001')
- `society_id` INT UNSIGNED NOT NULL (FK -> societies.id)
- `expense_category_id` INT UNSIGNED NOT NULL (FK -> expense_categories.id)
- `payment_account_id` INT UNSIGNED NOT NULL (FK -> accounts.id)
- `expense_date` DATE NOT NULL
- `amount` DECIMAL(15,2) NOT NULL
- `paid_to` VARCHAR(150) NOT NULL
- `reference_no` VARCHAR(100)
- `description` TEXT
- `attachment_path` VARCHAR(255)
- `approval_status` ENUM('approved', 'pending', 'rejected') DEFAULT 'approved'
- `created_by` INT UNSIGNED NOT NULL (FK -> users.id)
- `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP

---

## 8. Security, RBAC & Audit System

### `users`
- `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `name` VARCHAR(100) NOT NULL
- `email` VARCHAR(100) UNIQUE NOT NULL
- `username` VARCHAR(50) UNIQUE NOT NULL
- `password_hash` VARCHAR(255) NOT NULL
- `phone` VARCHAR(30)
- `role_id` INT UNSIGNED NOT NULL (FK -> roles.id)
- `is_super_admin` TINYINT(1) DEFAULT 0
- `status` ENUM('active', 'inactive', 'suspended') DEFAULT 'active'
- `last_login` DATETIME NULL
- `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP

### `roles`
- `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `name` VARCHAR(50) UNIQUE NOT NULL (e.g., 'Super Admin', 'Society Admin', 'Sales Manager', 'Recovery Officer', 'Accountant')
- `description` VARCHAR(255)
- `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP

### `permissions`
- `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `permission_code` VARCHAR(80) UNIQUE NOT NULL (e.g., 'bookings_create', 'payments_post', 'transfers_approve')
- `module` VARCHAR(50) NOT NULL
- `description` VARCHAR(255)

### `role_permissions`
- `role_id` INT UNSIGNED NOT NULL (FK -> roles.id ON DELETE CASCADE)
- `permission_id` INT UNSIGNED NOT NULL (FK -> permissions.id ON DELETE CASCADE)
- PRIMARY KEY (`role_id`, `permission_id`)

### `user_society_access`
- `user_id` INT UNSIGNED NOT NULL (FK -> users.id ON DELETE CASCADE)
- `society_id` INT UNSIGNED NOT NULL (FK -> societies.id ON DELETE CASCADE)
- PRIMARY KEY (`user_id`, `society_id`)

### `audit_logs`
- `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `user_id` INT UNSIGNED NULL (FK -> users.id)
- `society_id` INT UNSIGNED NULL (FK -> societies.id)
- `action` VARCHAR(100) NOT NULL (e.g., 'BOOKING_CREATED', 'PAYMENT_POSTED', 'PLOT_TRANSFERRED')
- `module` VARCHAR(50) NOT NULL
- `record_id` INT UNSIGNED NULL
- `old_values` LONGTEXT NULL (JSON)
- `new_values` LONGTEXT NULL (JSON)
- `ip_address` VARCHAR(50)
- `user_agent` VARCHAR(255)
- `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP

### `settings`
- `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `society_id` INT UNSIGNED NULL (FK -> societies.id)
- `setting_key` VARCHAR(100) NOT NULL
- `setting_value` TEXT
- `setting_group` VARCHAR(50) DEFAULT 'general'
- UNIQUE KEY `uk_society_key` (`society_id`, `setting_key`)
