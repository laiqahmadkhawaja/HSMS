<?php
/**
 * HSMS - Role-Based Access Control (RBAC)
 */

declare(strict_types=1);

function is_super_admin(): bool {
    $user = current_user();
    return $user !== null && $user['is_super_admin'];
}

function has_permission(string $permissionCode): bool {
    $user = current_user();
    if (!$user) {
        return false;
    }

    if ($user['is_super_admin']) {
        return true;
    }

    // Cache user permissions in session to avoid repetitive database hits
    if (!isset($_SESSION['user_permissions'])) {
        $db = get_db_connection();
        $stmt = $db->prepare("
            SELECT p.permission_code
            FROM `permissions` p
            JOIN `role_permissions` rp ON p.id = rp.permission_id
            WHERE rp.role_id = :role_id
        ");
        $stmt->execute(['role_id' => $user['role_id']]);
        $_SESSION['user_permissions'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    return in_array($permissionCode, $_SESSION['user_permissions'], true);
}

function require_permission(string $permissionCode): void {
    require_login();
    if (!has_permission($permissionCode)) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            json_response(false, 'Unauthorized. You do not possess the required permission.', [], [], 403);
        } else {
            redirect_with_message(base_url('dashboard.php'), 'Access Denied: You do not possess the required permission (' . htmlspecialchars($permissionCode) . ').', 'danger');
        }
    }
}
