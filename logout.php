<?php
/**
 * HSMS - User Logout Handler
 */

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';

logout_user();
redirect_with_message(base_url('login.php'), 'You have been successfully signed out.', 'info');
