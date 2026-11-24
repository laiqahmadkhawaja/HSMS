<?php
/**
 * HSMS - Authentication & Session Management
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

/**
 * Return current logged-in user array or null
 */
function current_user(): ?array {
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    return [
        'id'             => (int)$_SESSION['user_id'],
        'name'           => (string)($_SESSION['user_name'] ?? ''),
        'username'       => (string)($_SESSION['username'] ?? ''),
        'email'          => (string)($_SESSION['user_email'] ?? ''),
        'role_id'        => (int)($_SESSION['user_role_id'] ?? 0),
        'role_name'      => (string)($_SESSION['user_role_name'] ?? ''),
        'is_super_admin' => (bool)($_SESSION['is_super_admin'] ?? false)
    ];
}

/**
 * Check if a user is currently logged in
 */
function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

/**
 * Enforce login on protected pages
 */
function require_login(): void {
    if (!is_logged_in()) {
        redirect_with_message(base_url('login.php'), 'Please sign in to access the system.', 'warning');
    }
}

/**
 * Authenticate user credentials and regenerate session
 */
function login_user(string $username, string $password): bool {
    $db = get_db_connection();
    $stmt = $db->prepare("
        SELECT u.*, r.name AS role_name
        FROM `users` u
        JOIN `roles` r ON u.role_id = r.id
        WHERE (u.username = :uname OR u.email = :uemail) AND u.status = 'active'
        LIMIT 1
    ");
    $stmt->execute(['uname' => $username, 'uemail' => $username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    // Session regeneration prevents session fixation attacks
    session_regenerate_id(true);

    $_SESSION['user_id']        = (int)$user['id'];
    $_SESSION['user_name']      = (string)$user['name'];
    $_SESSION['username']       = (string)$user['username'];
    $_SESSION['user_email']     = (string)$user['email'];
    $_SESSION['user_role_id']   = (int)$user['role_id'];
    $_SESSION['user_role_name'] = (string)$user['role_name'];
    $_SESSION['is_super_admin'] = (bool)$user['is_super_admin'];

    // Update last login
    $upd = $db->prepare("UPDATE `users` SET `last_login` = NOW() WHERE `id` = :id");
    $upd->execute(['id' => $user['id']]);

    // Set initial active society
    $societies = get_user_societies((int)$user['id'], (bool)$user['is_super_admin']);
    if (!empty($societies)) {
        $_SESSION['active_society_id'] = (int)$societies[0]['id'];
        $_SESSION['active_society_name'] = (string)$societies[0]['name'];
        $_SESSION['active_society_code'] = (string)$societies[0]['society_code'];
    }

    log_audit('USER_LOGIN', 'auth', (int)$user['id']);

    return true;
}

/**
 * Log out user and clean session
 */
function logout_user(): void {
    if (isset($_SESSION['user_id'])) {
        log_audit('USER_LOGOUT', 'auth', (int)$_SESSION['user_id']);
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
}

/**
 * Get societies accessible to a given user
 */
function get_user_societies(int $userId, bool $isSuperAdmin): array {
    $db = get_db_connection();
    if ($isSuperAdmin) {
        $stmt = $db->query("SELECT `id`, `society_code`, `name`, `location`, `status` FROM `societies` WHERE `status` = 'active' ORDER BY `name` ASC");
        return $stmt->fetchAll();
    }

    $stmt = $db->prepare("
        SELECT s.`id`, s.`society_code`, s.`name`, s.`location`, s.`status`
        FROM `societies` s
        JOIN `user_society_access` usa ON s.id = usa.society_id
        WHERE usa.user_id = :uid AND s.status = 'active'
        ORDER BY s.name ASC
    ");
    $stmt->execute(['uid' => $userId]);
    return $stmt->fetchAll();
}

/**
 * Get active society ID from session
 */
function get_active_society_id(): int {
    if (!empty($_SESSION['active_society_id'])) {
        return (int)$_SESSION['active_society_id'];
    }
    // Fallback: fetch first active society
    $db = get_db_connection();
    $stmt = $db->query("SELECT `id` FROM `societies` WHERE `status` = 'active' ORDER BY `id` ASC LIMIT 1");
    $id = (int)$stmt->fetchColumn();
    if ($id > 0) {
        $_SESSION['active_society_id'] = $id;
    }
    return $id;
}

/**
 * Get full active society details
 */
function get_active_society(): ?array {
    $socId = get_active_society_id();
    if ($socId <= 0) {
        return null;
    }
    $db = get_db_connection();
    $stmt = $db->prepare("SELECT * FROM `societies` WHERE `id` = :id LIMIT 1");
    $stmt->execute(['id' => $socId]);
    return $stmt->fetch() ?: null;
}

/**
 * Switch active society in session
 */
function set_active_society_id(int $societyId): bool {
    $user = current_user();
    if (!$user) {
        return false;
    }
    $societies = get_user_societies($user['id'], $user['is_super_admin']);
    foreach ($societies as $soc) {
        if ((int)$soc['id'] === $societyId) {
            $_SESSION['active_society_id']   = (int)$soc['id'];
            $_SESSION['active_society_name'] = (string)$soc['name'];
            $_SESSION['active_society_code'] = (string)$soc['society_code'];
            return true;
        }
    }
    return false;
}
