<?php
/**
 * Application Entrypoint
 * Native PHP 8.3
 */

declare(strict_types=1);

require_once __DIR__ . '/config/security.php';

if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: dashboard.php');
    exit;
}

header('Location: login.php');
exit;
