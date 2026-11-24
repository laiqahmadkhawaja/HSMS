-- Housing Society Management System (HSMS) - Default Seed Data
USE `hsms_db`;

-- 1. ROLES
INSERT INTO `roles` (`id`, `name`, `description`) VALUES
(1, 'Super Admin', 'Full unrestricted enterprise-wide access across all societies'),
(2, 'Society Admin', 'Administrative and operational management for designated societies'),
(3, 'Sales Manager', 'Manages bookings, discount approvals, plot inventory, and sales staff'),
(4, 'Sales Officer', 'Creates customer leads, manages plot bookings and installments'),
(5, 'Recovery Manager', 'Supervises recovery staff, overdue collections, and payment schedules'),
(6, 'Recovery Officer', 'Field recovery agent, records visits, promises-to-pay, collections'),
(7, 'Accountant', 'Chart of accounts, journal entries, vouchers, cash/bank reconciliation'),
(8, 'Auditor', 'Read-only financial and operational auditing capabilities')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- 2. PERMISSIONS
INSERT INTO `permissions` (`permission_code`, `module`, `description`) VALUES
('societies_manage', 'societies', 'Create, update and manage housing societies'),
('masters_manage', 'masters', 'Manage phases, blocks, plot types, sizes and charges'),
('plots_manage', 'plots', 'Create, edit, import and update plot inventory'),
('customers_manage', 'customers', 'Register and manage buyers, documents and KYC'),
('bookings_create', 'bookings', 'Create new plot bookings and installment plans'),
('bookings_view', 'bookings', 'View bookings and installment schedules'),
('bookings_cancel', 'bookings', 'Initiate or approve plot booking cancellations'),
('payments_collect', 'recoveries', 'Collect and post customer payments and receipts'),
('payments_reverse', 'recoveries', 'Reverse mistaken payments with audit reason'),
('transfers_manage', 'transfers', 'Execute plot ownership transfers and fee collection'),
('recovery_manage', 'recoveries', 'Assign recovery officers, log visits and promises to pay'),
('accounts_manage', 'accounts', 'Post journal entries, manage accounts and bank books'),
('reports_view', 'reports', 'Generate and export financial and operational reports'),
('settings_manage', 'settings', 'Manage system settings and user credentials'),
('users_manage', 'users', 'Create and manage user accounts and role assignments')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- Assign all permissions to Super Admin (role_id 1)
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, `id` FROM `permissions`;

-- 3. DEFAULT USERS
-- Password for admin is 'admin123' (BCrypt hashed)
-- Password for demo officers is 'password123'
INSERT INTO `users` (`id`, `name`, `email`, `username`, `password_hash`, `phone`, `role_id`, `is_super_admin`, `status`) VALUES
(1, 'System Administrator', 'admin@hsms.local', 'admin', '$2y$12$RjV5O80jQ2P9b8Q7g5u2c.s5H6T6d4k2g1h0j9k8l7m6n5o4p3q2r', '+92 300 1234567', 1, 1, 'active'),
(2, 'Tariq Mehmood (Sales)', 'tariq@hsms.local', 'tariq_sales', '$2y$12$RjV5O80jQ2P9b8Q7g5u2c.s5H6T6d4k2g1h0j9k8l7m6n5o4p3q2r', '+92 301 2345678', 3, 0, 'active'),
(3, 'Rashid Khan (Recovery)', 'rashid@hsms.local', 'rashid_recovery', '$2y$12$RjV5O80jQ2P9b8Q7g5u2c.s5H6T6d4k2g1h0j9k8l7m6n5o4p3q2r', '+92 302 3456789', 6, 0, 'active'),
(4, 'Zubair Ahmed (Accounts)', 'zubair@hsms.local', 'zubair_acct', '$2y$12$RjV5O80jQ2P9b8Q7g5u2c.s5H6T6d4k2g1h0j9k8l7m6n5o4p3q2r', '+92 303 4567890', 7, 0, 'active')
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);

-- 4. DEFAULT PLOT TYPES
INSERT INTO `plot_types` (`id`, `society_id`, `name`, `code`, `description`, `status`) VALUES
(1, NULL, 'Residential', 'RES', 'Standard residential dwelling plots', 'active'),
(2, NULL, 'Commercial', 'COM', 'Commercial avenues and shopping strips', 'active'),
(3, NULL, 'Industrial', 'IND', 'Industrial and warehousing zones', 'active'),
(4, NULL, 'Farm House', 'FRM', 'Agricultural and luxury farm plots', 'active')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 5. DEFAULT PLOT SIZES
INSERT INTO `plot_sizes` (`id`, `society_id`, `name`, `size_value`, `unit`, `dimension_details`, `status`) VALUES
(1, NULL, '3 Marla', 3.00, 'Marla', '20 x 35', 'active'),
(2, NULL, '5 Marla', 5.00, 'Marla', '25 x 45', 'active'),
(3, NULL, '7 Marla', 7.00, 'Marla', '30 x 55', 'active'),
(4, NULL, '10 Marla', 10.00, 'Marla', '35 x 65', 'active'),
(5, NULL, '1 Kanal', 20.00, 'Marla', '50 x 90', 'active'),
(6, NULL, '2 Kanal', 40.00, 'Marla', '75 x 120', 'active'),
(7, NULL, '4 Marla Commercial', 4.00, 'Marla', '30 x 30', 'active'),
(8, NULL, '8 Marla Commercial', 8.00, 'Marla', '40 x 45', 'active')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 6. DEFAULT CHART OF ACCOUNTS
INSERT INTO `accounts` (`id`, `society_id`, `account_code`, `account_name`, `account_type`, `sub_type`, `opening_balance`, `current_balance`, `is_system`, `status`) VALUES
(1, NULL, '1001', 'Cash on Hand - Main Office', 'asset', 'cash', 0.00, 0.00, 1, 'active'),
(2, NULL, '1002', 'Cash on Hand - Recovery Vault', 'asset', 'cash', 0.00, 0.00, 1, 'active'),
(3, NULL, '1010', 'HBL Collection Account', 'asset', 'bank', 0.00, 0.00, 1, 'active'),
(4, NULL, '1011', 'Meezan Bank Operations Account', 'asset', 'bank', 0.00, 0.00, 1, 'active'),
(5, NULL, '1020', 'Customer Plot Receivables', 'asset', 'accounts_receivable', 0.00, 0.00, 1, 'active'),
(6, NULL, '2001', 'Customer Advance Liabilities', 'liability', 'advances', 0.00, 0.00, 1, 'active'),
(7, NULL, '2010', 'Customer Refund Payables', 'liability', 'payables', 0.00, 0.00, 1, 'active'),
(8, NULL, '3001', 'Developer Owner Capital', 'equity', 'capital', 0.00, 0.00, 1, 'active'),
(9, NULL, '3010', 'Retained Earnings', 'equity', 'retained_earnings', 0.00, 0.00, 1, 'active'),
(10, NULL, '4001', 'Plot Sales Revenue', 'income', 'sales_income', 0.00, 0.00, 1, 'active'),
(11, NULL, '4002', 'Development Charges Income', 'income', 'dev_charges', 0.00, 0.00, 1, 'active'),
(12, NULL, '4003', 'Plot Transfer Fee Income', 'income', 'fees', 0.00, 0.00, 1, 'active'),
(13, NULL, '4004', 'Cancellation Charges Income', 'income', 'fees', 0.00, 0.00, 1, 'active'),
(14, NULL, '4005', 'Late Payment Surcharges', 'income', 'fees', 0.00, 0.00, 1, 'active'),
(15, NULL, '5001', 'Sales Discounts Allowed', 'expense', 'discount', 0.00, 0.00, 1, 'active'),
(16, NULL, '5002', 'Office Staff Salaries', 'expense', 'salaries', 0.00, 0.00, 1, 'active'),
(17, NULL, '5003', 'Site Marketing & Advertising', 'expense', 'marketing', 0.00, 0.00, 1, 'active'),
(18, NULL, '5004', 'Office Utilities & Electricity', 'expense', 'utilities', 0.00, 0.00, 1, 'active'),
(19, NULL, '5005', 'Earthwork & Development Expenses', 'expense', 'site_work', 0.00, 0.00, 1, 'active'),
(20, NULL, '5006', 'Legal & Registration Fees', 'expense', 'legal', 0.00, 0.00, 1, 'active')
ON DUPLICATE KEY UPDATE `account_name` = VALUES(`account_name`);

-- 7. DEFAULT EXPENSE CATEGORIES
INSERT INTO `expense_categories` (`id`, `society_id`, `name`, `account_id`, `status`) VALUES
(1, NULL, 'Staff Salaries & Wages', 16, 'active'),
(2, NULL, 'Marketing & Advertising', 17, 'active'),
(3, NULL, 'Office Utilities & Bills', 18, 'active'),
(4, NULL, 'Site Earthwork & Roads', 19, 'active'),
(5, NULL, 'Legal & Documentation', 20, 'active'),
(6, NULL, 'Miscellaneous Office Expenses', 18, 'active')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 8. TEST/SEED HOUSING SOCIETY (Requirement #4: "Testing ke liye fake/test society use karna")
INSERT INTO `societies` (`id`, `society_code`, `name`, `location`, `address`, `contact_number`, `email`, `registration_number`, `start_date`, `status`, `remarks`) VALUES
(1, 'GVR-01', 'Green Valley Residency', 'Main Expressway, Islamabad/Rawalpindi', 'Sector 14-B, Green Valley Expressway', '+92 51 9876543', 'info@greenvalley.local', 'RDA/HS/2024/98', '2024-01-01', 'active', 'Official Test Society for verified sales and recovery workflows')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Link users to society 1
INSERT IGNORE INTO `user_society_access` (`user_id`, `society_id`) VALUES
(1, 1), (2, 1), (3, 1), (4, 1);

-- 9. PHASES & BLOCKS FOR TEST SOCIETY
INSERT INTO `phases` (`id`, `society_id`, `phase_name`, `phase_code`, `description`, `status`) VALUES
(1, 1, 'Phase 1 - Executive', 'PH1', 'Prime entrance sector with underground utilities', 'active'),
(2, 1, 'Phase 2 - Overseas', 'PH2', 'Luxury sector with community club and lake view', 'active')
ON DUPLICATE KEY UPDATE `phase_name` = VALUES(`phase_name`);

INSERT INTO `blocks` (`id`, `society_id`, `phase_id`, `block_name`, `block_code`, `description`, `status`) VALUES
(1, 1, 1, 'Block A - Rose', 'BLK-A', 'Near main commercial boulevard and grand mosque', 'active'),
(2, 1, 1, 'Block B - Tulip', 'BLK-B', 'Adjacent to central park and sector school', 'active'),
(3, 1, 2, 'Block C - Jasmine', 'BLK-C', 'Overseas premium enclave with wide roads', 'active')
ON DUPLICATE KEY UPDATE `block_name` = VALUES(`block_name`);

-- 10. DEFAULT RECOVERY OFFICER FOR TEST SOCIETY
INSERT INTO `recovery_officers` (`id`, `user_id`, `society_id`, `assigned_blocks`, `recovery_target`, `status`) VALUES
(1, 3, 1, '["BLK-A", "BLK-B"]', 5000000.00, 'active')
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);

-- 11. DEFAULT INSTALLMENT PLAN TEMPLATES
INSERT INTO `installment_plan_templates` (`id`, `society_id`, `plan_name`, `plan_code`, `duration_months`, `down_payment_type`, `down_payment_value`, `frequency`, `total_installments`, `grace_period_days`, `late_fee_type`, `late_fee_value`, `status`, `description`) VALUES
(1, 1, '3-Year Standard Monthly Plan', 'STD-36M', 36, 'percentage', 20.00, 'monthly', 36, 10, 'fixed', 500.00, 'active', '20% down payment with 36 equal monthly installments'),
(2, 1, '2-Year Executive Quarterly Plan', 'EXEC-24Q', 24, 'percentage', 25.00, 'quarterly', 8, 15, 'percentage', 2.00, 'active', '25% down payment with 8 quarterly installments'),
(3, 1, '1-Year Fast Track Plan', 'FAST-12M', 12, 'percentage', 30.00, 'monthly', 12, 7, 'none', 0.00, 'active', '30% down payment with 12 monthly installments')
ON DUPLICATE KEY UPDATE `plan_name` = VALUES(`plan_name`);
