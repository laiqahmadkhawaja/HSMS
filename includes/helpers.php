<?php
/**
 * HSMS - General Helper Functions
 */

declare(strict_types=1);

/**
 * Escape HTML output to prevent XSS (handles string, int, float, null, etc.)
 */
function e(mixed $string): string {
    return htmlspecialchars((string)($string ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Format currency amount with commas and standard currency prefix
 */
function format_currency(mixed $amount, int $decimals = 2, bool $showSymbol = true): string {
    $num = is_numeric($amount) ? (float)$amount : 0.0;
    $formatted = number_format($num, $decimals);
    return $showSymbol ? CURRENCY_SYMBOL . $formatted : $formatted;
}

/**
 * Format date for consistent user display
 */
function format_date(mixed $date, string $format = 'd M Y'): string {
    $dateStr = (string)($date ?? '');
    if (empty($dateStr) || $dateStr === '0000-00-00' || $dateStr === '0000-00-00 00:00:00') {
        return '—';
    }
    try {
        $dt = new DateTime($dateStr);
        return $dt->format($format);
    } catch (Exception) {
        return $dateStr;
    }
}

/**
 * Generate absolute or root-relative application URL
 */
function base_url(string $path = ''): string {
    $path = ltrim($path, '/');
    // We are hosted at /HSMS
    return '/HSMS/' . $path;
}

/**
 * Perform safe HTTP redirection preventing headers-already-sent errors
 */
function redirect(string $url): never {
    if (headers_sent($file, $line)) {
        // Fallback to JS redirect if output was prematurely sent
        echo "<script>window.location.href=" . json_encode($url) . ";</script>";
        echo "<noscript><meta http-equiv='refresh' content='0;url=" . htmlspecialchars($url) . "'></noscript>";
        exit;
    }
    header("Location: {$url}");
    exit;
}

/**
 * Set a session flash message and redirect
 */
function redirect_with_message(string $url, string $message, string $type = 'success'): never {
    set_flash($type, $message);
    redirect($url);
}

/**
 * Store flash message in session
 */
function set_flash(string $type, string $message): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['__flash'][] = [
        'type'    => in_array($type, ['success', 'danger', 'warning', 'info']) ? $type : 'info',
        'message' => $message
    ];
}

/**
 * Check if flash messages exist
 */
function has_flash(): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return !empty($_SESSION['__flash']);
}

/**
 * Retrieve and clear flash messages
 */
function get_flash(): array {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $flashes = $_SESSION['__flash'] ?? [];
    unset($_SESSION['__flash']);
    return $flashes;
}

/**
 * Send uniform JSON response for AJAX endpoints
 */
function json_response(bool $success, string $message = '', array $data = [], array $errors = [], int $statusCode = 200): never {
    if (!headers_sent()) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data'    => $data,
        'errors'  => $errors
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Log audit events into `audit_logs` table
 */
function log_audit(
    string $action,
    string $module,
    ?int $recordId = null,
    mixed $oldValues = null,
    mixed $newValues = null,
    ?int $societyId = null
): void {
    try {
        $db = get_db_connection();
        $userId = $_SESSION['user_id'] ?? null;
        if ($societyId === null && isset($_SESSION['active_society_id'])) {
            $societyId = (int)$_SESSION['active_society_id'];
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? 'CLI', 0, 255);

        $oldJson = $oldValues !== null ? json_encode($oldValues) : null;
        $newJson = $newValues !== null ? json_encode($newValues) : null;

        $stmt = $db->prepare("
            INSERT INTO `audit_logs` (`user_id`, `society_id`, `action`, `module`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`)
            VALUES (:uid, :soc_id, :action, :module, :rec_id, :old, :new, :ip, :ua)
        ");
        $stmt->execute([
            'uid'     => $userId,
            'soc_id'  => $societyId,
            'action'  => $action,
            'module'  => $module,
            'rec_id'  => $recordId,
            'old'     => $oldJson,
            'new'     => $newJson,
            'ip'      => $ip,
            'ua'      => $ua
        ]);
    } catch (Throwable $e) {
        // Never allow audit logging failure to crash primary business transactions, but log to file
        error_log("[Audit Log Error] " . $e->getMessage());
    }
}
