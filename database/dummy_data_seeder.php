<?php
/**
 * HSMS - Comprehensive Dummy Data Seeder
 * Populates realistic housing society records with multi-month historical dates (2025-2026)
 * ensuring GAAP-compliant double-entry accounting equilibrium and rich reports.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../services/booking_service.php';
require_once __DIR__ . '/../services/installment_service.php';
require_once __DIR__ . '/../services/payment_service.php';
require_once __DIR__ . '/../services/accounting_service.php';
require_once __DIR__ . '/../services/notification_service.php';

$db = get_db_connection();
$socId = 1; // Green Valley Residency

echo "=== Seeding Realistic Housing Society ERP Dummy Data ===\n\n";

// 1. Phases & Blocks
echo ">>> Step 1: Phases & Blocks Setup...\n";
$db->exec("
    INSERT IGNORE INTO `phases` (`id`, `society_id`, `phase_name`, `phase_code`, `status`, `description`) VALUES
    (1, {$socId}, 'Executive Sector', 'PH-1', 'active', 'Master executive phase with main commercial boulevard'),
    (2, {$socId}, 'Overseas Enclave', 'PH-2', 'active', 'Exclusive overseas enclave with modern amenities');

    INSERT INTO `blocks` (`id`, `society_id`, `phase_id`, `block_name`, `block_code`, `status`) VALUES
    (1, {$socId}, 1, 'Block A - Rose', 'BLK-A', 'active'),
    (2, {$socId}, 1, 'Block B - Tulip', 'BLK-B', 'active'),
    (3, {$socId}, 1, 'Block C - Jasmine', 'BLK-C', 'active'),
    (4, {$socId}, 2, 'Block D - Orchid', 'BLK-D', 'active'),
    (5, {$socId}, 2, 'Block E - Lotus', 'BLK-E', 'active')
    ON DUPLICATE KEY UPDATE `status` = 'active';

    INSERT INTO `recovery_officers` (`id`, `user_id`, `society_id`, `assigned_blocks`, `recovery_target`, `status`) VALUES
    (1, 3, {$socId}, '[\"BLK-A\",\"BLK-B\",\"BLK-C\"]', 7500000.00, 'active'),
    (2, 2, {$socId}, '[\"BLK-D\",\"BLK-E\"]', 5000000.00, 'active')
    ON DUPLICATE KEY UPDATE `status` = 'active';
");

// 2. Realistic Customers
echo ">>> Step 2: Customer Registry...\n";
$customersData = [
    [
        'code' => 'CUST-1001', 'name' => 'Chaudhry Muhammad Akram', 'father' => 'Chaudhry Rehmat Ali',
        'cnic' => '35202-1234567-1', 'mobile' => '03008456123', 'whatsapp' => '03008456123',
        'email' => 'akram.chaudhry@example.com', 'address' => 'House 14-B, Model Town', 'city' => 'Lahore',
        'nominee_name' => 'Hamza Akram', 'nominee_relation' => 'Son', 'nominee_cnic' => '35202-9876543-1', 'nominee_contact' => '03214567890'
    ],
    [
        'code' => 'CUST-1002', 'name' => 'Malik Shahid Hussain', 'father' => 'Malik Ghulam Qadir',
        'cnic' => '37405-2345678-3', 'mobile' => '03015556677', 'whatsapp' => '03015556677',
        'email' => 'shahid.malik@example.com', 'address' => 'Villa 88, Street 4, Sector F-7/2', 'city' => 'Islamabad',
        'nominee_name' => 'Zainab Bibi', 'nominee_relation' => 'Wife', 'nominee_cnic' => '37405-8765432-2', 'nominee_contact' => '03009988776'
    ],
    [
        'code' => 'CUST-1003', 'name' => 'Dr. Ayesha Siddiqui', 'father' => 'Prof. Dr. Tariq Siddiqui',
        'cnic' => '17301-3456789-4', 'mobile' => '03339123456', 'whatsapp' => '03339123456',
        'email' => 'ayesha.siddiqui@example.com', 'address' => 'B-12, Hayatabad Phase 3', 'city' => 'Peshawar',
        'nominee_name' => 'Dr. Daniyal Khan', 'nominee_relation' => 'Husband', 'nominee_cnic' => '17301-7654321-1', 'nominee_contact' => '03339887766'
    ],
    [
        'code' => 'CUST-1004', 'name' => 'Engr. Farhan Ali', 'father' => 'Muhammad Younis',
        'cnic' => '33100-4567890-5', 'mobile' => '03457123987', 'whatsapp' => '03457123987',
        'email' => 'farhan.engr@example.com', 'address' => 'House 204, Peoples Colony', 'city' => 'Faisalabad',
        'nominee_name' => 'Farwa Farhan', 'nominee_relation' => 'Wife', 'nominee_cnic' => '33100-6543210-2', 'nominee_contact' => '03451234567'
    ],
    [
        'code' => 'CUST-1005', 'name' => 'Brig. (R) Javed Akhtar', 'father' => 'Col. Akhtar Zaman',
        'cnic' => '37405-5678901-7', 'mobile' => '03005112233', 'whatsapp' => '03005112233',
        'email' => 'javed.akhtar@example.com', 'address' => 'Plot 45, Askari 14', 'city' => 'Rawalpindi',
        'nominee_name' => 'Bilal Akhtar', 'nominee_relation' => 'Son', 'nominee_cnic' => '37405-5432109-3', 'nominee_contact' => '03005223344'
    ],
    [
        'code' => 'CUST-1006', 'name' => 'Mian Abdul Wahid', 'father' => 'Mian Khurshid Alam',
        'cnic' => '34101-6789012-9', 'mobile' => '03226119988', 'whatsapp' => '03226119988',
        'email' => 'wahid.textiles@example.com', 'address' => 'G.T. Road Industrial Estate', 'city' => 'Gujranwala',
        'nominee_name' => 'Mian Usman Wahid', 'nominee_relation' => 'Son', 'nominee_cnic' => '34101-4321098-5', 'nominee_contact' => '03226778899'
    ],
    [
        'code' => 'CUST-1007', 'name' => 'Syed Murtaza Shah', 'father' => 'Syed Zulfiqar Shah',
        'cnic' => '42101-7890123-1', 'mobile' => '03002445566', 'whatsapp' => '03002445566',
        'email' => 'murtaza.shah@example.com', 'address' => 'DHA Phase 6, Khayaban-e-Bukhari', 'city' => 'Karachi',
        'nominee_name' => 'Syeda Fatima Shah', 'nominee_relation' => 'Daughter', 'nominee_cnic' => '42101-3210987-4', 'nominee_contact' => '03002112233'
    ],
    [
        'code' => 'CUST-1008', 'name' => 'Haji Noor Muhammad', 'father' => 'Haji Sher Baz',
        'cnic' => '54400-8901234-3', 'mobile' => '03138887766', 'whatsapp' => '03138887766',
        'email' => 'noor.traders@example.com', 'address' => 'Chaman Road, Jinnah Town', 'city' => 'Quetta',
        'nominee_name' => 'Abdul Qadir', 'nominee_relation' => 'Brother', 'nominee_cnic' => '54400-2109876-5', 'nominee_contact' => '03138112233'
    ]
];

$custIds = [];
$stmtCustIns = $db->prepare("
    INSERT INTO `customers` (
        `customer_id_code`, `full_name`, `father_husband_name`, `cnic_passport`,
        `mobile`, `whatsapp`, `email`, `residential_address`, `city`, `country`,
        `nominee_name`, `nominee_relation`, `nominee_cnic`, `nominee_contact`, `status`, `created_at`
    ) VALUES (
        :code, :name, :father, :cnic,
        :mob, :wa, :email, :addr, :city, 'Pakistan',
        :nom_name, :nom_rel, :nom_cnic, :nom_con, 'active', '2025-01-10 10:00:00'
    )
    ON DUPLICATE KEY UPDATE `full_name` = VALUES(`full_name`)
");

foreach ($customersData as $cd) {
    $stmtCustIns->execute([
        'code'     => $cd['code'],
        'name'     => $cd['name'],
        'father'   => $cd['father'],
        'cnic'     => $cd['cnic'],
        'mob'      => $cd['mobile'],
        'wa'       => $cd['whatsapp'],
        'email'    => $cd['email'],
        'addr'     => $cd['address'],
        'city'     => $cd['city'],
        'nom_name' => $cd['nominee_name'],
        'nom_rel'  => $cd['nominee_relation'],
        'nom_cnic' => $cd['nominee_cnic'],
        'nom_con'  => $cd['nominee_contact']
    ]);

    $stmtId = $db->prepare("SELECT id FROM `customers` WHERE `cnic_passport` = :c");
    $stmtId->execute(['c' => $cd['cnic']]);
    $custIds[$cd['code']] = (int)$stmtId->fetchColumn();
}
echo "  [OK] " . count($custIds) . " Customers verified/created.\n";

// 3. Rich Plot Inventory
echo ">>> Step 3: Plot Inventory Matrix Creation...\n";
$plotsList = [
    // Block A - Rose (Phase 1)
    ['blk' => 1, 'num' => 'A-101', 'type' => 1, 'size' => 2, 'base' => 2500000, 'dev' => 400000, 'corner' => 1, 'blvd' => 1, 'park' => 0],
    ['blk' => 1, 'num' => 'A-102', 'type' => 1, 'size' => 2, 'base' => 2500000, 'dev' => 400000, 'corner' => 0, 'blvd' => 0, 'park' => 1],
    ['blk' => 1, 'num' => 'A-103', 'type' => 1, 'size' => 3, 'base' => 4500000, 'dev' => 600000, 'corner' => 1, 'blvd' => 0, 'park' => 0],
    ['blk' => 1, 'num' => 'A-104', 'type' => 1, 'size' => 3, 'base' => 4500000, 'dev' => 600000, 'corner' => 0, 'blvd' => 1, 'park' => 0],
    ['blk' => 1, 'num' => 'A-105', 'type' => 2, 'size' => 2, 'base' => 7000000, 'dev' => 800000, 'corner' => 1, 'blvd' => 1, 'park' => 0], // Commercial
    ['blk' => 1, 'num' => 'A-106', 'type' => 1, 'size' => 4, 'base' => 8500000, 'dev' => 1200000, 'corner' => 0, 'blvd' => 0, 'park' => 1], // 1 Kanal

    // Block B - Tulip (Phase 1)
    ['blk' => 2, 'num' => 'B-201', 'type' => 1, 'size' => 2, 'base' => 2400000, 'dev' => 400000, 'corner' => 0, 'blvd' => 0, 'park' => 0],
    ['blk' => 2, 'num' => 'B-202', 'type' => 1, 'size' => 2, 'base' => 2400000, 'dev' => 400000, 'corner' => 1, 'blvd' => 0, 'park' => 1],
    ['blk' => 2, 'num' => 'B-203', 'type' => 1, 'size' => 3, 'base' => 4400000, 'dev' => 600000, 'corner' => 0, 'blvd' => 1, 'park' => 0],
    ['blk' => 2, 'num' => 'B-204', 'type' => 1, 'size' => 3, 'base' => 4400000, 'dev' => 600000, 'corner' => 0, 'blvd' => 0, 'park' => 0],
    ['blk' => 2, 'num' => 'B-205', 'type' => 2, 'size' => 2, 'base' => 6800000, 'dev' => 800000, 'corner' => 1, 'blvd' => 1, 'park' => 0],

    // Block C - Jasmine (Phase 1)
    ['blk' => 3, 'num' => 'C-301', 'type' => 1, 'size' => 2, 'base' => 2300000, 'dev' => 400000, 'corner' => 1, 'blvd' => 0, 'park' => 0],
    ['blk' => 3, 'num' => 'C-302', 'type' => 1, 'size' => 2, 'base' => 2300000, 'dev' => 400000, 'corner' => 0, 'blvd' => 0, 'park' => 1],
    ['blk' => 3, 'num' => 'C-303', 'type' => 1, 'size' => 4, 'base' => 8000000, 'dev' => 1200000, 'corner' => 1, 'blvd' => 1, 'park' => 1],

    // Block D - Orchid (Phase 2 Overseas)
    ['blk' => 4, 'num' => 'D-401', 'type' => 1, 'size' => 3, 'base' => 5000000, 'dev' => 700000, 'corner' => 1, 'blvd' => 1, 'park' => 0],
    ['blk' => 4, 'num' => 'D-402', 'type' => 1, 'size' => 3, 'base' => 5000000, 'dev' => 700000, 'corner' => 0, 'blvd' => 0, 'park' => 1],
    ['blk' => 4, 'num' => 'D-403', 'type' => 1, 'size' => 4, 'base' => 9500000, 'dev' => 1400000, 'corner' => 1, 'blvd' => 1, 'park' => 1],

    // Block E - Lotus (Phase 2 Overseas)
    ['blk' => 5, 'num' => 'E-501', 'type' => 1, 'size' => 2, 'base' => 2800000, 'dev' => 450000, 'corner' => 0, 'blvd' => 0, 'park' => 0],
    ['blk' => 5, 'num' => 'E-502', 'type' => 1, 'size' => 3, 'base' => 5200000, 'dev' => 700000, 'corner' => 1, 'blvd' => 0, 'park' => 0],
    ['blk' => 5, 'num' => 'E-503', 'type' => 2, 'size' => 3, 'base' => 11000000, 'dev' => 1500000, 'corner' => 1, 'blvd' => 1, 'park' => 0],
];

$plotIds = [];
$stmtPlotIns = $db->prepare("
    INSERT INTO `plots` (
        `society_id`, `phase_id`, `block_id`, `plot_type_id`, `plot_size_id`, `plot_number`,
        `base_price`, `development_charges`, `additional_charges`, `discount_allowed`, `total_price`,
        `is_corner`, `is_boulevard`, `is_park_facing`, `status`, `created_at`
    ) VALUES (
        :sid, :phid, :bid, :tid, :siz, :num,
        :base, :dev, :extra, 0, :total,
        :corner, :blvd, :park, 'available', NOW()
    )
    ON DUPLICATE KEY UPDATE `base_price` = VALUES(`base_price`), `development_charges` = VALUES(`development_charges`)
");

foreach ($plotsList as $pl) {
    $phaseId = ($pl['blk'] >= 4) ? 2 : 1;
    $extra = ($pl['corner'] ? 100000 : 0) + ($pl['blvd'] ? 100000 : 0) + ($pl['park'] ? 50000 : 0);
    $total = $pl['base'] + $pl['dev'] + $extra;
    $stmtPlotIns->execute([
        'sid'    => $socId,
        'phid'   => $phaseId,
        'bid'    => $pl['blk'],
        'tid'    => $pl['type'],
        'siz'    => $pl['size'],
        'num'    => $pl['num'],
        'base'   => $pl['base'],
        'dev'    => $pl['dev'],
        'extra'  => $extra,
        'total'  => $total,
        'corner' => $pl['corner'],
        'blvd'   => $pl['blvd'],
        'park'   => $pl['park']
    ]);

    $stmtPId = $db->prepare("SELECT id FROM `plots` WHERE `society_id` = :sid AND `plot_number` = :p");
    $stmtPId->execute(['sid' => $socId, 'p' => $pl['num']]);
    $plotIds[$pl['num']] = (int)$stmtPId->fetchColumn();
}
echo "  [OK] " . count($plotIds) . " Plots registered in inventory.\n";

// 4. Strategic Bookings with Diverse Dates (2025-2026)
echo ">>> Step 4: Creating Strategic Bookings Across Multiple Dates...\n";

// Clean any previous test bookings on these dummy plots
$cleanPlots = "'A-101','B-201','A-105','C-303','D-401','B-203','A-103'";
$db->exec("
    DELETE FROM `promises_to_pay` WHERE `booking_id` IN (SELECT id FROM `bookings` WHERE `plot_id` IN (SELECT id FROM `plots` WHERE `plot_number` IN ({$cleanPlots})));
    DELETE FROM `payment_allocations` WHERE `payment_id` IN (SELECT id FROM `payments` WHERE `booking_id` IN (SELECT id FROM `bookings` WHERE `plot_id` IN (SELECT id FROM `plots` WHERE `plot_number` IN ({$cleanPlots}))));
    DELETE FROM `payments` WHERE `booking_id` IN (SELECT id FROM `bookings` WHERE `plot_id` IN (SELECT id FROM `plots` WHERE `plot_number` IN ({$cleanPlots})));
    DELETE FROM `customer_ledger` WHERE `plot_id` IN (SELECT id FROM `plots` WHERE `plot_number` IN ({$cleanPlots}));
    DELETE FROM `installment_schedules` WHERE `booking_id` IN (SELECT id FROM `bookings` WHERE `plot_id` IN (SELECT id FROM `plots` WHERE `plot_number` IN ({$cleanPlots})));
    DELETE FROM `bookings` WHERE `plot_id` IN (SELECT id FROM `plots` WHERE `plot_number` IN ({$cleanPlots}));
    UPDATE `plots` SET `status` = 'available' WHERE `plot_number` IN ({$cleanPlots});
");

$bookingsConfig = [
    // Booking 1: Early booking, paid up to date (Active in good standing)
    [
        'plot' => 'A-101', 'cust' => 'CUST-1001', 'date' => '2025-10-15',
        'base' => 2500000, 'dev' => 400000, 'extra' => 200000, 'disc' => 50000, 'dp' => 600000,
        'inst_count' => 24, 'freq' => 'monthly', 'due_day' => 10,
        'payments' => [
            ['date' => '2025-10-15', 'amount' => 600000, 'method' => 'online_transfer', 'acct' => 2, 'ref' => 'MEEZAN-FT-8891'],
            ['date' => '2025-11-10', 'amount' => 102083, 'method' => 'online_transfer', 'acct' => 2, 'ref' => 'MEEZAN-FT-9102'],
            ['date' => '2025-12-10', 'amount' => 102083, 'method' => 'online_transfer', 'acct' => 2, 'ref' => 'MEEZAN-FT-9450'],
            ['date' => '2026-01-10', 'amount' => 102083, 'method' => 'cheque',          'acct' => 3, 'ref' => 'HBL-CHQ-1102'],
            ['date' => '2026-02-10', 'amount' => 102083, 'method' => 'cash',            'acct' => 1, 'ref' => 'CASH-REC-001'],
            ['date' => '2026-03-10', 'amount' => 102083, 'method' => 'online_transfer', 'acct' => 2, 'ref' => 'MEEZAN-FT-9800'],
            ['date' => '2026-04-10', 'amount' => 102083, 'method' => 'online_transfer', 'acct' => 2, 'ref' => 'MEEZAN-FT-9912'],
            ['date' => '2026-05-10', 'amount' => 102083, 'method' => 'online_transfer', 'acct' => 2, 'ref' => 'MEEZAN-FT-1011'],
            ['date' => '2026-06-10', 'amount' => 102083, 'method' => 'online_transfer', 'acct' => 2, 'ref' => 'MEEZAN-FT-1044'],
            ['date' => '2026-07-10', 'amount' => 102083, 'method' => 'online_transfer', 'acct' => 2, 'ref' => 'MEEZAN-FT-1077'],
            ['date' => '2026-08-10', 'amount' => 102083, 'method' => 'online_transfer', 'acct' => 2, 'ref' => 'MEEZAN-FT-1099'],
            ['date' => '2026-09-10', 'amount' => 102083, 'method' => 'online_transfer', 'acct' => 2, 'ref' => 'MEEZAN-FT-1122'],
            ['date' => '2026-10-10', 'amount' => 102083, 'method' => 'online_transfer', 'acct' => 2, 'ref' => 'MEEZAN-FT-1155'],
            ['date' => '2026-11-10', 'amount' => 102083, 'method' => 'online_transfer', 'acct' => 2, 'ref' => 'MEEZAN-FT-1188'],
        ]
    ],

    // Booking 2: Dec 2025 booking, paid partially until May 2026 (Now in 90-180 days aging overdue!)
    [
        'plot' => 'B-201', 'cust' => 'CUST-1002', 'date' => '2025-12-01',
        'base' => 2400000, 'dev' => 400000, 'extra' => 0, 'disc' => 0, 'dp' => 560000,
        'inst_count' => 24, 'freq' => 'monthly', 'due_day' => 5,
        'payments' => [
            ['date' => '2025-12-01', 'amount' => 560000, 'method' => 'cheque',          'acct' => 3, 'ref' => 'HBL-CHQ-5521'],
            ['date' => '2026-01-05', 'amount' => 93333,  'method' => 'online_transfer', 'acct' => 2, 'ref' => 'MEEZAN-FT-3321'],
            ['date' => '2026-02-05', 'amount' => 93333,  'method' => 'online_transfer', 'acct' => 2, 'ref' => 'MEEZAN-FT-3456'],
            ['date' => '2026-03-05', 'amount' => 93333,  'method' => 'cash',            'acct' => 1, 'ref' => 'CASH-REC-002'],
            ['date' => '2026-04-05', 'amount' => 93333,  'method' => 'online_transfer', 'acct' => 2, 'ref' => 'MEEZAN-FT-3678'],
            // Unpaid from May 2026 onwards -> Will show prominently in 30, 60, 90, 180+ days aging!
        ]
    ],

    // Booking 3: Jan 2026 Commercial plot booking, chronic defaulter (No installments paid after Down Payment!)
    [
        'plot' => 'A-105', 'cust' => 'CUST-1006', 'date' => '2026-01-10',
        'base' => 7000000, 'dev' => 800000, 'extra' => 200000, 'disc' => 0, 'dp' => 2000000,
        'inst_count' => 12, 'freq' => 'monthly', 'due_day' => 15,
        'payments' => [
            ['date' => '2026-01-10', 'amount' => 2000000, 'method' => 'online_transfer', 'acct' => 2, 'ref' => 'MEEZAN-FT-7711'],
            // Zero installments paid -> Major Defaulter with 10 overdue installments!
        ]
    ],

    // Booking 4: 1-Kanal Luxury Plot (C-303) - March 2026
    [
        'plot' => 'C-303', 'cust' => 'CUST-1005', 'date' => '2026-03-01',
        'base' => 8000000, 'dev' => 1200000, 'extra' => 250000, 'disc' => 150000, 'dp' => 2325000,
        'inst_count' => 12, 'freq' => 'quarterly', 'due_day' => 1,
        'payments' => [
            ['date' => '2026-03-01', 'amount' => 2325000, 'method' => 'online_transfer', 'acct' => 2, 'ref' => 'MEEZAN-FT-9900'],
            ['date' => '2026-06-01', 'amount' => 581250,  'method' => 'cheque',          'acct' => 3, 'ref' => 'HBL-CHQ-9012'],
            ['date' => '2026-09-01', 'amount' => 581250,  'method' => 'online_transfer', 'acct' => 2, 'ref' => 'MEEZAN-FT-9955'],
        ]
    ],

    // Booking 5: Overseas Enclave Plot (D-401) - May 2026
    [
        'plot' => 'D-401', 'cust' => 'CUST-1003', 'date' => '2026-05-15',
        'base' => 5000000, 'dev' => 700000, 'extra' => 200000, 'disc' => 100000, 'dp' => 1450000,
        'inst_count' => 24, 'freq' => 'monthly', 'due_day' => 15,
        'payments' => [
            ['date' => '2026-05-15', 'amount' => 1450000, 'method' => 'online_transfer', 'acct' => 2, 'ref' => 'MEEZAN-FT-4411'],
            ['date' => '2026-06-15', 'amount' => 181250,  'method' => 'online_transfer', 'acct' => 2, 'ref' => 'MEEZAN-FT-4422'],
            ['date' => '2026-07-15', 'amount' => 181250,  'method' => 'online_transfer', 'acct' => 2, 'ref' => 'MEEZAN-FT-4433'],
            ['date' => '2026-08-15', 'amount' => 181250,  'method' => 'online_transfer', 'acct' => 2, 'ref' => 'MEEZAN-FT-4444'],
            // September, October, November overdue (30, 60, 90 days)
        ]
    ],

    // Booking 6: Recent Booking (October 2026)
    [
        'plot' => 'B-203', 'cust' => 'CUST-1004', 'date' => '2026-10-01',
        'base' => 4400000, 'dev' => 600000, 'extra' => 100000, 'disc' => 50000, 'dp' => 1010000,
        'inst_count' => 18, 'freq' => 'monthly', 'due_day' => 1,
        'payments' => [
            ['date' => '2026-10-01', 'amount' => 1010000, 'method' => 'cheque', 'acct' => 3, 'ref' => 'HBL-CHQ-7788'],
            ['date' => '2026-11-01', 'amount' => 224444,  'method' => 'online_transfer', 'acct' => 2, 'ref' => 'MEEZAN-FT-8833'],
        ]
    ],

    // Booking 7: Current Month Fresh Booking (November 2026)
    [
        'plot' => 'A-103', 'cust' => 'CUST-1007', 'date' => '2026-11-05',
        'base' => 4500000, 'dev' => 600000, 'extra' => 100000, 'disc' => 0, 'dp' => 1040000,
        'inst_count' => 24, 'freq' => 'monthly', 'due_day' => 5,
        'payments' => [
            ['date' => '2026-11-05', 'amount' => 1040000, 'method' => 'online_transfer', 'acct' => 2, 'ref' => 'MEEZAN-FT-9911'],
        ]
    ]
];

$activeBookings = [];

foreach ($bookingsConfig as $idx => $bc) {
    $plotId = $plotIds[$bc['plot']];
    $custId = $custIds[$bc['cust']];

    // Ensure plot is available for booking
    $db->prepare("UPDATE `plots` SET `status` = 'available' WHERE `id` = :pid")->execute(['pid' => $plotId]);

    try {
        $bookingId = BookingService::createBooking(
            $socId,
            $plotId,
            $custId,
            null,
            $bc['date'],
            (float)$bc['base'],
            (float)$bc['dev'],
            (float)$bc['extra'],
            (float)$bc['disc'],
            (float)$bc['dp'],
            (int)$bc['inst_count'],
            $bc['freq'],
            (int)$bc['due_day'],
            2, // Sales officer: Tariq
            1, // Created by: admin
            "Commercial sale agreement for Plot #{$bc['plot']}"
        );

        $activeBookings[$bc['plot']] = $bookingId;
        echo "  [OK] Booking created for Plot {$bc['plot']} (ID: {$bookingId}, Date: {$bc['date']})\n";

        // Post configured payments with realistic dates
        foreach ($bc['payments'] as $p) {
            PaymentService::postPayment(
                $socId,
                $bookingId,
                $custId,
                (float)$p['amount'],
                $p['date'],
                $p['method'],
                (int)$p['acct'],
                $p['ref'],
                'fifo',
                null,
                1, // Recovery officer: Rashid
                "Installment recovery against Plot #{$bc['plot']}",
                null,
                1
            );
        }
    } catch (Throwable $e) {
        echo "  [WARN] Booking Plot {$bc['plot']}: " . $e->getMessage() . "\n";
    }
}

// 5. Promises to Pay (PTP) for Recovery Desk
echo "\n>>> Step 5: Recording Field Recovery Promises to Pay...\n";
if (!empty($activeBookings['B-201'])) {
    $b201Id = $activeBookings['B-201'];
    $c1002Id = $custIds['CUST-1002'];

    $db->exec("
        INSERT INTO `promises_to_pay` (`booking_id`, `customer_id`, `recovery_officer_id`, `promised_amount`, `promise_date`, `status`, `notes`, `created_at`) VALUES
        ({$b201Id}, {$c1002Id}, 1, 93333.00, '2026-06-15', 'broken', 'Customer requested grace period due to business travel but failed to deposit.', '2026-06-01 10:00:00'),
        ({$b201Id}, {$c1002Id}, 1, 93333.00, '2026-08-20', 'broken', 'Customer promised pay order from Bank Alfalah but did not deliver.', '2026-08-10 11:30:00'),
        ({$b201Id}, {$c1002Id}, 1, 186666.00, '2026-11-30', 'pending', 'Customer confirmed settlement of 2 overdue installments by month end.', NOW());
    ");
    echo "  [OK] Field recovery promises to pay logged for Booking B-201.\n";
}

if (!empty($activeBookings['A-105'])) {
    $a105Id = $activeBookings['A-105'];
    $c1006Id = $custIds['CUST-1006'];

    $db->exec("
        INSERT INTO `promises_to_pay` (`booking_id`, `customer_id`, `recovery_officer_id`, `promised_amount`, `promise_date`, `status`, `notes`, `created_at`) VALUES
        ({$a105Id}, {$c1006Id}, 1, 500000.00, '2026-09-30', 'broken', 'Commercial investor promised part clearance against export proceeds.', '2026-09-15 14:00:00'),
        ({$a105Id}, {$c1006Id}, 1, 1000000.00, '2026-12-15', 'pending', 'Final commitment before legal cancellation notice is dispatched.', NOW());
    ");
    echo "  [OK] Field recovery promises to pay logged for Booking A-105.\n";
}

// 6. Operational Expenses Across Multiple Months
echo "\n>>> Step 6: Recording Real-World Society Expenses...\n";
$expenses = [
    ['date' => '2026-02-15', 'amount' => 450000.00, 'paid_to' => 'Al-Makkah Earthmovers Ltd.', 'cat_id' => 4, 'acct_id' => 19, 'category' => 'Site Earthwork & Roads', 'acct' => 2, 'notes' => 'Heavy excavation & ground grading for Sector B avenues'],
    ['date' => '2026-04-20', 'amount' => 850000.00, 'paid_to' => 'Pak-Electroworks Electrical Contractors', 'cat_id' => 4, 'acct_id' => 19, 'category' => 'Site Earthwork & Roads', 'acct' => 2, 'notes' => 'HT/LT transformer feeder line laying in Phase 1'],
    ['date' => '2026-06-30', 'amount' => 125000.00, 'paid_to' => 'Eagle Eye Security Services', 'cat_id' => 1, 'acct_id' => 16, 'category' => 'Staff Salaries & Wages', 'acct' => 1, 'notes' => 'Monthly guard deployment & 24/7 boom-barrier security'],
    ['date' => '2026-08-15', 'amount' => 280000.00, 'paid_to' => 'Green Vista Horticulture & Landscaping', 'cat_id' => 4, 'acct_id' => 19, 'category' => 'Site Earthwork & Roads', 'acct' => 2, 'notes' => 'Plantation, imported turf grass & jogging track pavers'],
    ['date' => '2026-10-10', 'amount' => 350000.00, 'paid_to' => 'National Engineering & Survey Consultants', 'cat_id' => 5, 'acct_id' => 20, 'category' => 'Legal & Documentation', 'acct' => 3, 'notes' => 'Master plan revision and boundary demarcation survey'],
    ['date' => '2026-11-15', 'amount' => 180000.00, 'paid_to' => 'Islamabad Electric Supply Company (IESCO)', 'cat_id' => 3, 'acct_id' => 18, 'category' => 'Office Utilities & Bills', 'acct' => 2, 'notes' => 'Head office and main entrance gate electrification bill']
];

$stmtExpIns = $db->prepare("
    INSERT INTO `expenses` (
        `society_id`, `voucher_number`, `expense_date`, `expense_category_id`, `payment_account_id`,
        `paid_to`, `amount`, `description`, `approval_status`, `created_by`, `created_at`
    ) VALUES (
        :sid, :vnum, :edate, :cat_id, :aid,
        :pto, :amt, :desc, 'approved', 1, :cdate
    )
    ON DUPLICATE KEY UPDATE `amount` = VALUES(`amount`)
");

foreach ($expenses as $idx => $exp) {
    $vNum = sprintf('EXP-GVR-01-2026-%04d', $idx + 20);
    $stmtExpIns->execute([
        'sid'    => $socId,
        'vnum'   => $vNum,
        'edate'  => $exp['date'],
        'cdate'  => $exp['date'] . ' 12:00:00',
        'cat_id' => $exp['cat_id'],
        'aid'    => $exp['acct'],
        'pto'    => $exp['paid_to'],
        'amt'    => $exp['amount'],
        'desc'   => $exp['notes']
    ]);
    $expId = (int)$db->lastInsertId();

    // Post balanced double-entry JV for expense
    AccountingService::postJournalEntry(
        $socId,
        $exp['date'],
        'payment',
        'expense',
        $expId,
        "Payment for {$exp['category']} to {$exp['paid_to']} ({$vNum})",
        [
            ['account_id' => $exp['acct_id'], 'debit' => $exp['amount'], 'credit' => 0.0, 'memo' => $exp['category']],
            ['account_id' => $exp['acct'], 'debit' => 0.0, 'credit' => $exp['amount'], 'memo' => "Disbursement from Bank Account"]
        ],
        1
    );
}
echo "  [OK] " . count($expenses) . " Multi-month expense vouchers and balanced JVs posted.\n";

// 7. Notification History Logs
echo "\n>>> Step 7: Populating Outbound Notification Delivery Logs...\n";
$notifs = [
    ['cid' => $custIds['CUST-1001'], 'phone' => '03008456123', 'type' => 'BOOKING_CONFIRMATION', 'chan' => 'whatsapp', 'msg' => 'Dear Chaudhry Muhammad Akram, your booking for Plot #A-101 has been confirmed under Booking #BK-2025-0001. Society Management.'],
    ['cid' => $custIds['CUST-1001'], 'phone' => '03008456123', 'type' => 'PAYMENT_RECEIPT', 'chan' => 'whatsapp', 'msg' => 'Dear Chaudhry Muhammad Akram, payment of Rs. 102,083 received against Plot #A-101. Remaining balance: Rs. 1,633,334. Society Management.'],
    ['cid' => $custIds['CUST-1002'], 'phone' => '03015556677', 'type' => 'OVERDUE_DEMAND', 'chan' => 'whatsapp', 'msg' => 'DEMAND NOTICE: Dear Malik Shahid Hussain, you have 6 overdue installments totaling Rs. 560,000 on Plot #B-201. Please settle immediately.'],
    ['cid' => $custIds['CUST-1006'], 'phone' => '03226119988', 'type' => 'OVERDUE_DEMAND', 'chan' => 'whatsapp', 'msg' => 'FINAL NOTICE: Dear Mian Abdul Wahid, Commercial Plot #A-105 has unpaid dues of Rs. 5,000,000. Prevent cancellation by visiting society office.'],
    ['cid' => null, 'phone' => '+923001234567', 'type' => 'SMS_BROADCAST', 'chan' => 'sms', 'msg' => 'Green Valley Residency: Site development of Sector B completed. Possession ceremonies commencing next quarter. Contact office for details.']
];

foreach ($notifs as $n) {
    NotificationService::logNotification(
        $socId,
        $n['cid'],
        $n['phone'],
        $n['chan'],
        $n['type'],
        $n['msg'],
        'sent',
        'SIM-' . time() . '-' . rand(100, 999)
    );
}
echo "  [OK] Outbound WhatsApp & SMS delivery records logged.\n";

// 8. General Ledger Equilibrium Check
echo "\n>>> Step 8: Verifying Master General Ledger Double-Entry Equilibrium...\n";
$stmtJv = $db->query("SELECT SUM(debit) as debits, SUM(credit) as credits FROM `journal_entry_lines`");
$jv = $stmtJv->fetch();
$debits = (float)$jv['debits'];
$credits = (float)$jv['credits'];
$diff = abs($debits - $credits);

echo "  Total General Ledger Debits : Rs. " . number_format($debits, 2) . "\n";
echo "  Total General Ledger Credits: Rs. " . number_format($credits, 2) . "\n";
echo "  Variance                     : Rs. " . number_format($diff, 2) . "\n";

if ($diff < 0.001) {
    echo "  [SUCCESS] General Ledger is in EXACT equilibrium (Debit == Credit, Zero Variance)!\n";
} else {
    echo "  [ERROR] Unbalanced entries detected in General Ledger!\n";
    exit(1);
}

echo "\n========================================================================\n";
echo "    DUMMY DATA SEEDING COMPLETED 100% SUCCESSFULLY ACROSS ALL MODULES!  \n";
echo "========================================================================\n";
