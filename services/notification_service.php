<?php
/**
 * HSMS - Notification & Messaging Service
 * Manages SMS gateway stubs, WhatsApp Click-to-Chat deep links,
 * dynamic variable interpolation, and persistent audit delivery logs.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

class NotificationService {
    /**
     * Default message templates
     */
    public const DEFAULT_TEMPLATES = [
        'BOOKING_CONFIRMATION' => "Dear {customer_name}, Congratulations! Your booking for Plot #{plot_number} ({block_name}) in {society_name} has been confirmed under Booking #{booking_number}. Net Price: Rs. {net_price}, Down Payment Paid: Rs. {down_payment}. Thank you for choosing us.",
        'PAYMENT_RECEIPT' => "Dear {customer_name}, Payment received! Receipt #{receipt_number} for Rs. {amount} has been posted against Booking #{booking_number} (Plot #{plot_number}). Remaining balance: Rs. {remaining_balance}. Society Management.",
        'INSTALLMENT_DUE' => "Dear {customer_name}, Friendly reminder: Installment of Rs. {amount_due} for Plot #{plot_number} ({society_name}) is due on {due_date}. Please clear by due date to avoid late surcharge.",
        'OVERDUE_DEMAND' => "FINAL DEMAND NOTICE: Dear {customer_name}, you have {overdue_count} overdue installment(s) totaling Rs. {overdue_amount} on Plot #{plot_number} ({society_name}). Please settle immediately to prevent cancellation.",
        'TRANSFER_COMPLETED' => "Dear {customer_name}, Ownership transfer for Plot #{plot_number} has been completed successfully under Transfer Deed #{transfer_number}. Balance assumed: Rs. {balance_transferred}. Welcome to {society_name}."
    ];

    /**
     * Interpolate template variables
     */
    public static function renderTemplate(string $template, array $variables): string {
        $rendered = $template;
        foreach ($variables as $key => $val) {
            $rendered = str_replace('{' . $key . '}', (string)$val, $rendered);
        }
        return $rendered;
    }

    /**
     * Sanitize phone number into international WhatsApp E.164 format (e.g. 923001234567)
     */
    public static function formatPhoneForWhatsApp(string $phone): string {
        $digits = preg_replace('/[^0-9]/', '', $phone) ?? '';
        if (str_starts_with($digits, '0')) {
            $digits = '92' . substr($digits, 1);
        } elseif (str_starts_with($digits, '3') && strlen($digits) === 10) {
            $digits = '92' . $digits;
        }
        return $digits;
    }

    /**
     * Generate Click-to-Chat WhatsApp deep link
     */
    public static function generateWhatsAppLink(string $phone, string $message): string {
        $cleanPhone = self::formatPhoneForWhatsApp($phone);
        return 'https://wa.me/' . $cleanPhone . '?text=' . rawurlencode($message);
    }

    /**
     * Dispatch notification and write audit log into `notification_logs` table
     */
    public static function logNotification(
        int $societyId,
        ?int $customerId,
        string $recipient,
        string $channel, // 'sms', 'whatsapp', 'email'
        string $messageType,
        string $messageBody,
        string $status = 'manual_link_generated', // 'sent', 'simulated', 'manual_link_generated', 'failed'
        ?string $externalRef = null
    ): int {
        $db = get_db_connection();

        $stmt = $db->prepare("
            INSERT INTO `notification_logs` (
                `society_id`, `customer_id`, `recipient`, `channel`,
                `message_type`, `message_body`, `status`, `external_ref`
            ) VALUES (
                :sid, :cid, :recipient, :channel,
                :mtype, :mbody, :stat, :ref
            )
        ");
        $stmt->execute([
            'sid'       => $societyId,
            'cid'       => $customerId ?: null,
            'recipient' => $recipient,
            'channel'   => $channel,
            'mtype'     => $messageType,
            'mbody'     => $messageBody,
            'stat'      => $status,
            'ref'       => $externalRef ?: null
        ]);

        return (int)$db->lastInsertId();
    }
}
