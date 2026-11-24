# HSMS - Development Error Log & Resolutions

This log documents every issue encountered during development, its root cause, and the verified resolution.

---

### Issue #001: MySQL CLI Command Not in PATH
- **Severity:** Low
- **Symptom:** PowerShell command `mysql --version` returned not found.
- **Root Cause:** Wamp server keeps binaries in `C:\wamp64\bin\mariadb\mariadb11.4.9\bin` and `C:\wamp64\bin\mysql\mysql8.4.7\bin` rather than system PATH.
- **Resolution:** PHP PDO MySQL was verified via `php -r "new PDO('mysql:host=127.0.0.1;port=3306', 'root', '');"` and successfully connected to MariaDB 10.4.32 on port 3306. Database operations and migrations can execute reliably via PHP scripts and PDO.
- **Status:** Resolved.

---

### Issue #002: PDO Named Parameter Reuse under Native Prepared Statements
- **Severity:** Medium
- **Symptom:** `ajax/customers/search.php` threw `PDOException: SQLSTATE[HY093]: Invalid parameter number`.
- **Root Cause:** When `PDO::ATTR_EMULATE_PREPARES => false` is enforced for security, native MySQL prepared statements disallow repeating the same named placeholder `:q` multiple times across different `OR` conditions.
- **Resolution:** Disaggregated placeholder `:q` into distinct placeholders `:q1, :q2, :q3, :q4` in `ajax/customers/search.php`.
- **Status:** Resolved. Tested and verified.

---

### Issue #003: Duplicate Named Placeholders in Booking & Installment Revision Prepared Statements
- **Severity:** Medium
- **Symptom:** `BookingService::createBooking` and `InstallmentService::reviseSchedule` threw `SQLSTATE[HY093]: Invalid parameter number`.
- **Root Cause:** In `BookingService::createBooking`, `:net` was repeated for both `net_sale_price` and `remaining_balance`. In `InstallmentService::reviseSchedule`, `:due` was repeated for both `amount_due` and `balance_due`. Under native PDO prepares, each bound variable must have a unique placeholder name.
- **Resolution:** Renamed parameters to `:net_sale`, `:rem_bal`, `:due`, and `:bal_due` with corresponding unique associative array keys.
- **Status:** Resolved. Verified 100% via `tests/test_phase5.php`.

---

### Issue #004: Duplicate Placeholders in Schedule Update & Reversal Prepared Statements
- **Severity:** Medium
- **Symptom:** `PaymentService::postPayment` threw `SQLSTATE[HY093]: Invalid parameter number` at schedule update.
- **Root Cause:** `:alloc` was repeated in `amount_paid = amount_paid + :alloc` and `balance_due = balance_due - :alloc`. Similarly in `reversePayment`, `:alloc` and `:allocated` were repeated across multiple arithmetic clauses.
- **Resolution:** Replaced with distinct `:alloc_add`, `:alloc_sub`, `:alloc_check`, and `:allocated_sub`, `:allocated_add`.
- **Status:** Resolved. Verified 100% via `tests/test_phase6.php`.

---

### Issue #006: ENUM Mismatch in Customer Ledger During Transfer & Cancellation
- **Severity:** Medium
- **Symptom:** `TransferService` and `CancellationService` threw data truncation warning/error on inserting into `customer_ledger.transaction_type`.
- **Root Cause:** Services used `'transfer_out'` and `'transfer_in'` or `'cancellation_close'` which were not in the database column's ENUM definition.
- **Resolution:** Updated `TransferService` to use `'transfer_credit'` and `'transfer_debit'`, and `CancellationService` to use `'cancellation_credit'`.
- **Status:** Resolved. Verified 100% via `tests/test_phase8.php`.

---

### Issue #007: Column Name Mismatch in Customers Table (`address` vs `residential_address`)
- **Severity:** Low
- **Symptom:** Print templates threw `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'address' in 'field list'`.
- **Root Cause:** Schema defines `residential_address` and `permanent_address`, while view templates requested `address`.
- **Resolution:** Replaced `c.address` with `c.residential_address` in print views.
- **Status:** Resolved. Verified 100% via `tests/test_phase8.php`.

---

### Issue #008: Column Name Mismatch in Plots Table (`plot_category`)
- **Severity:** Low
- **Symptom:** Transfer/Cancellation print views requested `p.plot_category`.
- **Root Cause:** Schema separates plot size and type into lookup tables (`plot_types`, `plot_sizes`) and booleans (`is_corner`, `is_boulevard`).
- **Resolution:** Joined `plot_sizes` and `plot_types` to display size name and type name.
- **Status:** Resolved. Verified 100% via `tests/test_phase8.php`.

---

### Issue #009: Inverted Balance Delta on Asset Accounts in `postJournalEntry`
- **Severity:** High
- **Symptom:** Cash on hand balance decreased on debit and increased on credit.
- **Root Cause:** `fetchColumn()` on query `SELECT id, account_type FROM accounts WHERE id = :aid` returned the ID `1` instead of `'asset'`, causing the normal balance check to treat Cash as a liability.
- **Resolution:** Changed query to `SELECT account_type FROM accounts WHERE id = :aid`.
- **Status:** Resolved. Verified 100% via `tests/test_phase9.php`.

---

### Issue #010: Missing Title String in Trial Balance CSV Header
- **Severity:** Low
- **Symptom:** Automated test assertion expected string `'Trial Balance Statement'` in CSV export.
- **Root Cause:** CSV export began with date metadata before the statement title.
- **Resolution:** Added `'Trial Balance Statement'` to row 1 of CSV export.
- **Status:** Resolved. Verified 100% via `tests/test_phase9.php`.

---

### Issue #012: Column Not Found `p.plot_category` in Transfer Deed Print Query (Image 1)
- **Severity:** High
- **Symptom:** `Fatal error: Uncaught PDOException: SQLSTATE[42S22]: Column not found: 1054 Unknown column 'p.plot_category' in 'field list' in modules/transfers/print.php on line 17`.
- **Root Cause:** In `modules/transfers/print.php`, the query selected `p.plot_category`. The database schema separates plot characteristics into `plot_types` (`pt.name as type_name`), `plot_sizes` (`ps.name as size_name`), and booleans (`is_corner`, `is_boulevard`, `is_park_facing`).
- **Resolution:** Replaced `p.plot_category` with joins to `plot_sizes` and `plot_types`, selecting `ps.name as size_name, pt.name as type_name, p.is_corner, p.is_boulevard, p.is_park_facing`.
- **Status:** Resolved. Verified 100% via `tests/test_fix_verification.php`.

---

### Issue #013: Strict TypeError in `e()` on Numeric / Integer Arguments (Image 2)
- **Severity:** High
- **Symptom:** `Fatal error: Uncaught TypeError: e(): Argument #1 ($string) must be of type ?string, int given, called in modules/installment_plans/edit.php on line 113 and defined in includes/helpers.php on line 11`.
- **Root Cause:** Helper function was declared with strict parameter type `function e(?string $string): string`. Under PHP 8.2 `declare(strict_types=1);`, passing integers (e.g. `$plan['duration_months']`, IDs, counts, years) caused fatal TypeErrors.
- **Resolution:** Updated `e()` and `format_date()` in `includes/helpers.php` to accept `mixed $string` and `mixed $date`, safely casting input to `(string)($string ?? '')` before escaping.
- **Status:** Resolved. Verified 100% across all forms and templates via `tests/test_fix_verification.php`.

---

### Issue #014: Access Denied on `users_manage` & Missing System Modules (Image 3)
- **Severity:** High
- **Symptom:** Navigating to Recovery Officers or clicking Users & Roles yielded `Access Denied: You do not possess the required permission (users_manage)` or HTTP 404 on settings pages.
- **Root Cause:**
  1. `modules/employees/recovery_officers.php` checked `require_permission('users_manage')` instead of `recovery_manage`.
  2. The `permissions` table lacked the `users_manage` record for non-super-admins.
  3. `modules/users/index.php`, `modules/settings/backup.php`, `modules/settings/audit_logs.php`, `modules/settings/profile.php`, and `modules/settings/index.php` were unbuilt stubs.
- **Resolution:**
  1. Changed permission check in `recovery_officers.php` to `recovery_manage`.
  2. Added `users_manage` to `permissions` table and mapped to roles 1 and 2 in `seed.sql` and database.
  3. Implemented full, responsive, and secure interfaces for `modules/users/index.php` (Users & Roles), `modules/settings/backup.php` (Instant backup & downloads), `modules/settings/audit_logs.php` (Compliance trail), `modules/settings/profile.php` (Profile & password), and `modules/settings/index.php` (Society settings).
- **Status:** Resolved. Verified 100% via `tests/test_fix_verification.php`.


