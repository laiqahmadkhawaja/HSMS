# HSMS - Master Testing Checklist

## 1. Foundation & Authentication
- [x] No PHP lint errors across any file (`php -l`)
- [x] Database PDO connection handles failure gracefully with friendly error (no credentials leak)
- [x] CSRF token generation and validation for all state-changing requests
- [x] Session regeneration upon successful authentication (prevents session fixation)
- [x] Proper header redirects followed immediately by `exit;` (zero "headers already sent" errors)
- [x] RBAC enforcement: Unauthorized role cannot access restricted endpoints
- [x] Society switcher updates session and restricts queries to authorized society

## 2. Master Data & Plot Inventory
- [x] Society CRUD with validation (unique code, valid email/phone)
- [x] Phase and Block hierarchy correctly linked
- [x] Plot Type and Plot Size configurable
- [x] Plot creation with unique constraint (`block_id` + `plot_number`)
- [x] Plot price calculation (Base + Dev + Extra - Discount = Total)
- [x] Plot status transitions: Available -> Reserved -> Booked -> Sold -> Transferred / Cancelled
- [x] CSV/Excel bulk import with error reporting on invalid rows

## 3. Customer Management
- [x] Unique CNIC / Passport check with warning/block
- [x] Mobile & WhatsApp validation
- [x] Next of kin / Nominee details saved
- [x] Document file upload security (allowed MIME types, sanitized filenames, safe storage)
- [x] Customer 360 profile displays all plots, ledgers, overdue amounts, and receipts

## 4. Installment Engine (`services/installment_service.php`)
- [x] Equal installment calculation matches Net Price exactly:
      `Down Payment + SUM(Installments) == Net Sale Price`
- [x] Rounding discrepancy adjusted onto the final installment transparently
- [x] Frequencies supported: Monthly, Quarterly, Semi-Annual, Annual, Custom
- [x] Calendar due dates handle short months and leap years correctly (e.g. Feb 28/29, April 30)
- [x] Balloon and milestone custom installment additions
- [x] Schedule revision retains previous version snapshot and audit reason

## 5. Booking & Sales Module
- [x] Double-booking prevention via database row-locking (`SELECT ... FOR UPDATE`)
- [x] Full atomic transaction: Booking creation, Schedule generation, Plot status update, Customer ledger entry
- [x] Transaction rollback on intentional failure (no orphan bookings, no corrupted plot statuses)
- [x] Unique booking number generation (`BK-YYYY-XXXX`)

## 6. Payment Allocation Engine & Recovery (`services/payment_service.php`)
- [x] Strict atomic transaction: Receipt generation, Payment row, Allocations, Schedule balances, Customer Ledger, Journal Entry
- [x] Duplicate payment prevention (idempotency key & unique receipt numbers)
- [x] FIFO Allocation: Oldest overdue installment paid first
- [x] Specific Installment Allocation: Exact selection by authorized officer
- [x] Advance handling: Excess payment credited to customer advance liability
- [x] Installment balance never goes negative: `amount_paid <= amount_due`
- [x] Receipts printable in A4, 80mm thermal, 58mm thermal formats

## 7. Field Recovery & Promises to Pay
- [x] Officer assignment by block/society
- [x] Promise-to-Pay tracking: Pending, Fulfilled, Broken
- [x] Overdue aging reports (30, 60, 90, 180+ days) and recovery target progress

## 8. Plot Transfer & Cancellation
- [x] Ownership transfer carries forward or settles outstanding dues
- [x] Historical owner records preserved with complete ledger trail
- [x] Transfer fee collected and credited to income with balanced double-entry JV
- [x] Plot cancellation calculates deduction and refund payable
- [x] Plot returned to available inventory only after approved cancellation

## 9. Accounting & Financial Integrity
- [x] Chart of accounts hierarchy (Assets, Liabilities, Equity, Income, Expenses)
- [x] Journal entries strictly balanced: `Total Debits == Total Credits` (unbalanced entries blocked)
- [x] Customer ledger running balance reconciles with individual booking balances
- [x] Cash & Bank balances reconcile with posted receipts and expenses
- [x] Financial statements: Trial Balance in exact equilibrium, Cash & Bank Book, CSV exports

## 10. Dashboard & Operational Reports
- [x] All metrics computed via dynamic SQL queries (zero hardcoded values)
- [x] Filters for Society, Phase, Block, Status, and Date range
- [x] Print and CSV exports matching on-screen figures exactly across all reports

## 11. Notification Engine & Messaging
- [x] Template key catalog and message definitions
- [x] Dynamic variable interpolation (`{customer_name}`, `{plot_number}`, `{amount}`, etc.)
- [x] WhatsApp international phone sanitization (E.164 standard)
- [x] Click-to-chat deep link generation (`https://wa.me/...`)
- [x] Simulated SMS gateway dispatch stub
- [x] Persistent audit logging in `notification_logs` table
- [x] Real-time UI composer and recent log table

## 12. Final QA, Hardening & Security Audit
- [x] Comprehensive recursive PHP syntax linting across entire project (0 syntax errors across 89 PHP files)
- [x] SQL Injection prevention: 100% prepared statements with parameterized queries
- [x] Cross-Site Scripting (XSS) defense: proper html escaping via `e()` on user output
- [x] Cross-Site Request Forgery (CSRF): token verification on all POST actions
- [x] Header manipulation defense: zero premature output, zero "headers already sent"
- [x] Sequential execution of all test suites (Phases 1 through 12) via `tests/run_all_tests.php` (12/12 PASSED)
- [x] Master Double-Entry balance audit: debits equal credits with zero variance (`Rs. 2,508,333.00 == Rs. 2,508,333.00`)
- [x] Full database backup before production sign-off (`backups/production_final_...sql`)
- [x] Complete documentation handoff (`DEVELOPMENT.md`, `DATABASE_SCHEMA.md`, `MODULE_STATUS.md`, `TESTING_CHECKLIST.md`, `ERROR_LOG.md`)
