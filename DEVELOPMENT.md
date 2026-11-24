# Housing Society Management System (HSMS) - Development Guide

## 1. System Overview & Architecture
HSMS is an enterprise-grade, multi-society real estate management and financial accounting software engineered specifically for housing societies, land developers, and property recovery teams.

### Tech Stack
- **Backend:** Pure Modular Core PHP (PHP 8.2+ compatibility), PDO MySQL with strict prepared statements
- **Frontend:** HTML5, CSS3, Vanilla JavaScript (Fetch API / async-await), Bootstrap 5 (heavily customized with modern navy/slate enterprise theme)
- **Database:** MariaDB / MySQL InnoDB engine, `utf8mb4_unicode_ci`, full referential integrity and strict foreign keys
- **Visualization:** Chart.js (locally included/bundled) for financial and sales dashboard metrics

### Prohibited Patterns
- No heavy frameworks (Laravel, Symfony, CodeIgniter)
- No Node.js backend or SPA frameworks (React, Vue)
- No raw SQL string concatenation
- No floating-point data types for currency (`DECIMAL(15,2)` is strictly enforced)
- No unhandled exceptions, unvalidated user input, or headers-already-sent errors

---

## 2. Coding Standards & Error-Prevention Rules

### A. Output Buffering & Header Redirection
To ensure `Cannot modify header information – headers already sent` can never occur:
1. All request processing, auth checks, permissions, and redirects are completed **before** any HTML or layout file (`header.php`, `sidebar.php`) is loaded.
2. In request handling scripts:
   ```php
   // Correct pattern:
   require_once __DIR__ . '/includes/auth.php';
   if (!has_permission('societies_manage')) {
       redirect_with_error('dashboard.php', 'Access denied.');
       exit;
   }
   ```
3. Never leave trailing whitespace or UTF-8 BOM after `?>`. In pure PHP files, omit closing `?>` tags.

### B. Database Transactions & Financial Integrity
All operations impacting monetary balances, plot inventory, customer ledgers, and double-entry accounting journals must execute within atomic database transactions:
```php
$db->beginTransaction();
try {
    // 1. Lock rows if necessary (SELECT ... FOR UPDATE)
    // 2. Perform business logic
    // 3. Post journal entries & ledger movements
    // 4. Update parent statuses
    $db->commit();
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    log_error($e->getMessage(), 'CRITICAL');
    throw $e;
}
```

### C. Standardized AJAX API Responses
Every AJAX endpoint returns strict, uniform JSON:
- **Success:**
  ```json
  {
    "success": true,
    "message": "Operation completed successfully",
    "data": {}
  }
  ```
- **Error:**
  ```json
  {
    "success": false,
    "message": "Validation or processing error description",
    "errors": {}
  }
  ```

---

## 3. Financial Posting Rules (Double-Entry Engine)

Every transaction adheres to the accounting identity: `Assets = Liabilities + Equity`.
Debits and Credits must be precisely equal (`SUM(debit) == SUM(credit)`).

### Recognized Scenarios:
1. **Plot Sale Recognition / Booking:**
   - **Debit:** Customer Receivables (Asset) [Net Sale Price]
   - **Credit:** Plot Sales Revenue (Income) [Base Plot Price + Additional Charges]
   - **Credit:** Development Charges Payable / Revenue (Liability/Income) [Dev Charges]
   - *(Note: If discount applied: Debit Discount Allowed (Expense))*

2. **Down Payment & Installment Payment Receipt:**
   - **Debit:** Cash on Hand / Designated Bank Account (Asset)
   - **Credit:** Customer Receivables (Asset) [Allocated to due installments]
   - **Credit:** Customer Advance Liability (Liability) [Any excess unallocated amount]

3. **Advance Settlement Against Newly Due Installments:**
   - **Debit:** Customer Advance Liability (Liability)
   - **Credit:** Customer Receivables (Asset)

4. **Transfer Fee Receipt:**
   - **Debit:** Cash / Bank (Asset)
   - **Credit:** Plot Transfer Fee Income (Income)

5. **Plot Cancellation & Deduction:**
   - **Debit:** Plot Sales Revenue (Income Reversal) [Net Sale Price]
   - **Credit:** Customer Receivables (Asset Reversal) [Remaining Balance]
   - **Credit:** Cancellation Fee Income (Income) [Configured Deduction]
   - **Credit:** Refund Payable to Customer (Liability) [Net Refund Due]

6. **Refund Disbursement:**
   - **Debit:** Refund Payable to Customer (Liability)
   - **Credit:** Cash / Bank Account (Asset)

7. **Operational Expenses:**
   - **Debit:** Designated Expense Account (Expense)
   - **Credit:** Cash / Bank Account (Asset)

---

## 4. Multi-Society Architecture
- All records (Plots, Bookings, Schedules, Payments, Expenses, Accounts, Vouchers) belong to a `society_id`.
- Users have defined society access via `user_society_access` table.
- A global Society Switcher in the top navigation updates the active session society. Super Admins can operate across all societies; scoped users are restricted to their assigned societies.

---

## 5. Development Roadmap & Milestones
- **Phase 0:** Architecture, Database Schema, Documentation, Seed Data [COMPLETED]
- **Phase 1:** Core Foundation, PDO Connection, Error Handler, Auth & Session, RBAC, Master Layout [COMPLETED]
- **Phase 2:** Master Data (Societies, Phases, Blocks, Plot Types/Sizes, Plot Inventory, Excel/CSV Import) [COMPLETED]
- **Phase 3:** Customer Management (Full Profile, Nominees, KYC Docs, Multi-Plot Ownership) [COMPLETED]
- **Phase 4:** Installment Plan Engine (`services/installment_service.php` - Custom schedules, frequencies, rounding adjustments, balloon payments) [COMPLETED]
- **Phase 5:** Plot Booking & Sales Module (Atomic transactions, locking, duplicate prevention) [COMPLETED]
- **Phase 6:** Payment Allocation Engine & Recovery Module (`services/payment_service.php` - FIFO / manual allocations, multi-receipts, A4/Thermal printing) [COMPLETED]
- **Phase 7:** Recovery Management & Field Staff (Officer assignments, Visit logs, Promises to Pay) [COMPLETED]
- **Phase 8:** Ownership Transfer & Cancellation Workflows (With audit trails and balance takeovers) [COMPLETED]
- **Phase 9:** Double-Entry Accounting & Cash/Bank Management (Chart of accounts, general ledger, trial balance, P&L, balance sheet) [COMPLETED]
- **Phase 10:** Executive Dashboard & Dynamic Operational Reports (Inventory, aging, cashbook, defaulters) [COMPLETED]
- **Phase 11:** Notification Engine & WhatsApp Messaging Linker [COMPLETED]
- **Phase 12:** End-to-End QA, Security Review, Database Backups, Final Verification [COMPLETED]

---

## 6. Project Verification & Automated Test Suite
All 12 automated test suites are executed via the master test runner:
```bash
php tests/run_all_tests.php
```
Test results:
- 89 PHP files syntax-linted: 0 errors detected (`php -l`)
- 12 / 12 test suites passed: 100% test success rate
- Master General Ledger balance: `Total Debits == Total Credits` with Rs. 0.00 variance
- Production database snapshot: `backups/production_final_...sql`

