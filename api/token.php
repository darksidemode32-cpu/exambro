<?php
/**
 * API: Dynamic 5-Minute Exit Token Information
 * Returns current active token, countdown remaining seconds, and expiration timestamp.
 * Accessible by authenticated administrators.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/token_generator.php';

// Only allow authenticated admin or internal requests
if (empty($_SESSION['admin_logged_in'])) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Akses ditolak. Sesi admin diperlukan.'
    ]);
    exit;
}

$tokenInfo = getCurrentExitTokenInfo();

echo json_encode([
    'status' => 'success',
    'data' => [
        'token' => $tokenInfo['token'],
        'remaining_seconds' => $tokenInfo['remaining_seconds'],
        'elapsed_seconds' => $tokenInfo['elapsed_seconds'],
        'total_interval' => $tokenInfo['interval'],
        'expires_at' => $tokenInfo['expires_at'],
        'server_time' => time(),
        'progress_percent' => round(($tokenInfo['remaining_seconds'] / $tokenInfo['interval']) * 100, 1)
    ]
], JSON_UNESCAPED_UNICODE);
