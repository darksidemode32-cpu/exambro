<?php
/**
 * Administrator Session Verification Guard
 * Native PHP 8.3
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/security.php';

if (empty($_SESSION['admin_logged_in']) || empty($_SESSION['admin_id'])) {
    if (isset($_GET['ajax']) || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => 'error',
            'message' => 'Sesi login telah berakhir. Silakan login kembali.'
        ]);
        exit;
    }

    $redirect = urlencode($_SERVER['REQUEST_URI'] ?? 'dashboard.php');
    header("Location: login.php?redirect={$redirect}");
    exit;
}
