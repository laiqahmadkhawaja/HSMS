<?php
/**
 * HSMS - System Constants
 * Centralized business policies and status definitions
 */

declare(strict_types=1);

// Currency & Locale
if (!defined('CURRENCY_SYMBOL')) define('CURRENCY_SYMBOL', 'Rs. ');
if (!defined('CURRENCY_CODE')) define('CURRENCY_CODE', 'PKR');
if (!defined('DEFAULT_TIMEZONE')) define('DEFAULT_TIMEZONE', 'Asia/Karachi');

// Plot Statuses
if (!defined('PLOT_STATUS_AVAILABLE')) define('PLOT_STATUS_AVAILABLE', 'available');
if (!defined('PLOT_STATUS_RESERVED')) define('PLOT_STATUS_RESERVED', 'reserved');
if (!defined('PLOT_STATUS_BOOKED')) define('PLOT_STATUS_BOOKED', 'booked');
if (!defined('PLOT_STATUS_SOLD')) define('PLOT_STATUS_SOLD', 'sold');
if (!defined('PLOT_STATUS_TRANSFERRED')) define('PLOT_STATUS_TRANSFERRED', 'transferred');
if (!defined('PLOT_STATUS_CANCELLED')) define('PLOT_STATUS_CANCELLED', 'cancelled');
if (!defined('PLOT_STATUS_BLOCKED')) define('PLOT_STATUS_BLOCKED', 'blocked');

// Booking Statuses
if (!defined('BOOKING_STATUS_ACTIVE')) define('BOOKING_STATUS_ACTIVE', 'active');
if (!defined('BOOKING_STATUS_TRANSFERRED')) define('BOOKING_STATUS_TRANSFERRED', 'transferred');
if (!defined('BOOKING_STATUS_CANCELLED')) define('BOOKING_STATUS_CANCELLED', 'cancelled');
if (!defined('BOOKING_STATUS_COMPLETED')) define('BOOKING_STATUS_COMPLETED', 'completed');

// Installment Schedule Statuses
if (!defined('SCHEDULE_STATUS_PENDING')) define('SCHEDULE_STATUS_PENDING', 'pending');
if (!defined('SCHEDULE_STATUS_PARTIAL')) define('SCHEDULE_STATUS_PARTIAL', 'partially_paid');
if (!defined('SCHEDULE_STATUS_PAID')) define('SCHEDULE_STATUS_PAID', 'paid');
if (!defined('SCHEDULE_STATUS_OVERDUE')) define('SCHEDULE_STATUS_OVERDUE', 'overdue');
if (!defined('SCHEDULE_STATUS_WAIVED')) define('SCHEDULE_STATUS_WAIVED', 'waived');

// Payment Methods
if (!defined('PAYMENT_METHOD_CASH')) define('PAYMENT_METHOD_CASH', 'cash');
if (!defined('PAYMENT_METHOD_CHEQUE')) define('PAYMENT_METHOD_CHEQUE', 'cheque');
if (!defined('PAYMENT_METHOD_BANK_TRANSFER')) define('PAYMENT_METHOD_BANK_TRANSFER', 'bank_transfer');
if (!defined('PAYMENT_METHOD_ONLINE')) define('PAYMENT_METHOD_ONLINE', 'online');
if (!defined('PAYMENT_METHOD_JAZZCASH')) define('PAYMENT_METHOD_JAZZCASH', 'jazzcash');
if (!defined('PAYMENT_METHOD_EASYPAISA')) define('PAYMENT_METHOD_EASYPAISA', 'easypaisa');
if (!defined('PAYMENT_METHOD_PAY_ORDER')) define('PAYMENT_METHOD_PAY_ORDER', 'pay_order');

// Ledger Transaction Types
if (!defined('LEDGER_BOOKING_DEBIT')) define('LEDGER_BOOKING_DEBIT', 'booking_debit');
if (!defined('LEDGER_INSTALLMENT_DUE')) define('LEDGER_INSTALLMENT_DUE', 'installment_due');
if (!defined('LEDGER_PAYMENT_CREDIT')) define('LEDGER_PAYMENT_CREDIT', 'payment_credit');
if (!defined('LEDGER_ADVANCE_CREDIT')) define('LEDGER_ADVANCE_CREDIT', 'advance_credit');
if (!defined('LEDGER_ADVANCE_ADJUSTMENT')) define('LEDGER_ADVANCE_ADJUSTMENT', 'advance_adjustment');
if (!defined('LEDGER_CHARGE_DEBIT')) define('LEDGER_CHARGE_DEBIT', 'charge_debit');
if (!defined('LEDGER_DISCOUNT_CREDIT')) define('LEDGER_DISCOUNT_CREDIT', 'discount_credit');
if (!defined('LEDGER_WAIVER_CREDIT')) define('LEDGER_WAIVER_CREDIT', 'waiver_credit');
if (!defined('LEDGER_TRANSFER_DEBIT')) define('LEDGER_TRANSFER_DEBIT', 'transfer_debit');
if (!defined('LEDGER_TRANSFER_CREDIT')) define('LEDGER_TRANSFER_CREDIT', 'transfer_credit');
if (!defined('LEDGER_CANCELLATION_CREDIT')) define('LEDGER_CANCELLATION_CREDIT', 'cancellation_credit');
if (!defined('LEDGER_REFUND_DEBIT')) define('LEDGER_REFUND_DEBIT', 'refund_debit');
if (!defined('LEDGER_REVERSAL')) define('LEDGER_REVERSAL', 'reversal');

// Pagination
if (!defined('DEFAULT_PER_PAGE')) define('DEFAULT_PER_PAGE', 20);
