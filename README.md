# Housing Society Management System (HSMS) ERP

A production-grade, enterprise Enterprise Resource Planning (ERP) application engineered in **Core PHP (Procedural/Modular)**, **MySQL / MariaDB PDO with parameterized prepared statements**, and **Bootstrap 5 (Custom Executive Slate/Navy Theme)** with **Vanilla JavaScript / AJAX Fetch API**.

HSMS is specifically architected for housing societies, land developers, and real estate communities to manage end-to-end operations: multi-society hierarchies, plot inventory, customer KYC profiles, automated installment planning, payment recovery, legal plot transfers, cancellations, double-entry GAAP accounting, and executive reporting.

---

## Key Features & Functional Modules

### 1. Multi-Society & Phase Hierarchy
- Multi-project support with society isolation (`Society -> Phase -> Block -> Plot`).
- Real-time society switcher in top navigation with session-scoped queries.
- Master setup for plot types (Residential, Commercial, Industrial, Farmhouse) and standard plot sizes (5 Marla, 10 Marla, 1 Kanal, 2 Kanal).

### 2. Plot Inventory Management
- Real-time occupancy tracking (`available`, `booked`, `sold`, `reserved`, `cancelled`).
- Pricing engine with Base Price, Development Charges, Category Premiums (Boulevard, Corner, Park-Facing), and approved discounts.
- Full price mutation audit history in `plot_price_history`.
- Bulk CSV plot importer with transaction rollback on validation errors.

### 3. Customer Registry & KYC Compliance
- Comprehensive customer profile with unique CNIC/Passport duplicate prevention.
- Nominee & Next-of-Kin records with verified relationship tracking.
- Secure KYC document upload (CNIC scans, allotment forms, affidavits) with MIME-type verification and safe storage.
- Customer 360° view linking all owned plots, running ledgers, and payment history.

### 4. Installment Schedule Engine
- Exact penny reconciliation: $\text{Down Payment} + \sum(\text{Installments}) = \text{Net Sale Price}$.
- Discrepancy adjustment onto the final installment to eliminate rounding errors.
- Frequencies supported: Monthly, Quarterly, Semi-Annual, Annual, and Custom Balloon Milestones.
- Automatic calendar logic handling short months and leap year ends (e.g., Feb 28/29, April 30).
- Versioned schedule revision snapshotting with audit reasoning.

### 5. Plot Booking & Double-Booking Defense
- Atomic multi-table database transactions with row-level locks (`SELECT ... FOR UPDATE`).
- Automatic generation of unique booking numbers (`BK-YYYY-XXXX`).
- Printable official **Allotment Certificate & Order** with custom watermark layout.

### 6. Payment Allocation & Recovery Engine
- Multi-receipt allocation: Oldest overdue FIFO and specific installment selection.
- Automatic customer advance liability management for excess payments.
- Payment reversal workflow with complete ledger, account balance, and schedule rollbacks.
- Multi-format receipt printing: Standard A4 certificate, 80mm POS Thermal, and 58mm Thermal slip.

### 7. Field Recovery & Promises to Pay (PTP)
- Field recovery staff allocation by block and society with monthly collection targets.
- Promise-to-Pay (PTP) tracking: Pending, Fulfilled, Broken, and Rescheduled states.
- Defaulter recovery queue with direct WhatsApp click-to-chat demand notices.

### 8. Legal Transfers & Plot Cancellations
- **Ownership Transfer:** Full legal conveyance with seller ledger reconciliation, buyer balance assumption, transfer fee collection, and printable **Deed of Transfer & Assignment**.
- **Cancellation & Refunds:** Policy deduction calculation (10%, 20%, or custom), refund payable liability tracking, and automatic **plot inventory restoration** to `available`.

### 9. Strict GAAP Double-Entry Accounting
- 5-Pillar Chart of Accounts (Assets, Liabilities, Equity, Revenue, Expenses).
- Unbalanced manual journal entries (`Debit != Credit`) are strictly blocked at service level.
- Automated system JVs posted for bookings, receipts, transfer fees, cancellations, and expenses.
- Real-time **Trial Balance** in exact mathematical equilibrium ($\Delta = 0.00$) with CSV export.
- Cash & Bank Book statements across Cash on Hand, Meezan Bank, and HBL.

### 10. Executive Dashboard & Operational Reports Hub
- Dynamic SQL aggregate queries (zero hardcoded numbers or dummy placeholders).
- Interactive Chart.js plot status distribution chart.
- Exportable reports (HTML & CSV):
  - **Sales Register:** Chronological booking breakdown by date, agent, and value.
  - **Inventory Matrix:** Occupancy density by phase, block, and plot type.
  - **Overdue Aging Analysis:** Multi-tier aging buckets (Current, 30, 60, 90, 180+ days).
  - **Defaulters Register:** Active non-performing bookings with recovery action links.

### 11. WhatsApp & Notification Engine
- International phone sanitization to E.164 standards (`+92...`).
- Automated WhatsApp Click-to-Chat deep link generator (`https://wa.me/...`) with variable interpolation (`{customer_name}`, `{plot_number}`, `{amount_due}`).
- Simulated SMS gateway stub and persistent logging in `notification_logs`.

### 12. Security, Backup & Administration
- **Authentication Firewall:** Protected routes enforce HTTP 302 login redirection.
- **CSRF Protection:** Cryptographic tokens verified on all mutating POST requests.
- **XSS Defense:** Universal `e()` helper handles strings, numbers, and nulls safely.
- **SQL Injection Defense:** 100% prepared statements with parameterized queries.
- **Database Backup Center:** Point-in-time SQL snapshot generation and web-based file download.
- **Audit Logs:** Compliance audit trail tracking administrative actions and data mutations.

---

## Technology Stack

- **Backend:** Core PHP (Procedural / Modular, Strict Types, Zero Frameworks)
- **Database:** MySQL / MariaDB (InnoDB, UTF8mb4, Parameterized PDO)
- **Frontend:** Bootstrap 5, Bootstrap Icons, Custom Executive Theme, Vanilla JS (Fetch API)
- **Charts:** Chart.js 4.4+ (Doughnut & Bar visualizations)
- **Web Server:** Apache (WAMP / LAMP / XAMPP with `mod_rewrite`)

---

## Installation & Setup

1. **Clone the repository:**
   ```bash
   git clone https://github.com/laiqahmadkhawaja/HSMS.git
   ```
2. **Move to web server root:**
   Place the project directory in your Apache document root:
   - WAMP: `C:\wamp64\www\HSMS`
   - XAMPP: `C:\xampp\htdocs\HSMS`
3. **Database Configuration:**
   Verify connection credentials in `config/database.php`:
   ```php
   'host' => '127.0.0.1',
   'port' => 3306,
   'dbname' => 'hsms_db',
   'username' => 'root',
   'password' => ''
   ```
4. **Run Database Migrations & Seeds:**
   Execute the migration script via CLI or browser:
   ```bash
   php install.php
   ```
   *(Optional)* Populate rich multi-date dummy operations:
   ```bash
   php database/dummy_data_seeder.php
   ```

5. **Access the Application:**
   - URL: `http://localhost/HSMS/`
   - **Default Administrator Credentials:**
     - **Username:** `admin` (or `admin@hsms.local`)
     - **Password:** `admin123`

---

## Verification & Automated Test Suite

HSMS comes with an automated test runner verifying all 12 modules, security layers, and accounting equilibrium:

```bash
php tests/run_all_tests.php
```

All 12 test suites execute sequentially with 100% pass guarantee:
- `test_phase1.php` — Core Foundation & Authentication
- `test_phase2.php` — Master Data & Society Hierarchy
- `test_phase3.php` — Customer Registry & 360 Profile
- `test_phase4.php` — Installment Schedule Engine
- `test_phase5.php` — Booking & Sales Transaction Engine
- `test_phase6.php` — Payment Allocation Engine & Receipts
- `test_phase7.php` — Field Staff & Promises to Pay
- `test_phase8.php` — Plot Transfers & Cancellations
- `test_phase9.php` — Double-Entry Accounting & Expenses
- `test_phase10.php` — Executive Dashboard & Reports
- `test_phase11.php` — Notification Engine & Settings
- `test_phase12_security.php` — Security Audit & System Hardening

---

## License & Credits

Developed with precision for enterprise housing society operations. All rights reserved.
