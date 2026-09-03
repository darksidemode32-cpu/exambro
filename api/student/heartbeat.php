<?php
/**
 * API: Student Heartbeat & Real-time Remote Command Sync
 * According to PRD:
 * - Admin/Pengawas dapat melakukan override atau mengatur tingkat kecerahan (brightness) layar secara remote.
 * - Memperbarui status online/offline dan detak jantung perangkat siswa.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/security.php';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?? $_POST;

$sessionToken = trim($data['session_token'] ?? '');
$batteryLevel = isset($data['battery_level']) ? (int)$data['battery_level'] : null;

if (empty($sessionToken)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'session_token wajib disertakan.'
    ]);
    exit;
}

try {
    $pdo = getDb();
    
    // Find session and join with school to get current remote_brightness & lock status
    $stmt = $pdo->prepare("
        SELECT s.id, s.school_id, s.force_exit, s.is_locked, s.violation_count, sc.remote_brightness, sc.max_violations, sc.is_active
        FROM student_sessions s
        JOIN schools sc ON s.school_id = sc.id
        WHERE s.session_token = ?
    ");
    $stmt->execute([$sessionToken]);
    $session = $stmt->fetch();

    if (!$session) {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'Sesi tidak ditemukan.'
        ]);
        exit;
    }

    // Update heartbeat & battery
    if ($batteryLevel !== null) {
        $updateStmt = $pdo->prepare("
            UPDATE student_sessions
            SET is_online = 1, last_heartbeat = CURRENT_TIMESTAMP, battery_level = ?
            WHERE id = ?
        ");
        $updateStmt->execute([$batteryLevel, (int)$session['id']]);
    } else {
        $updateStmt = $pdo->prepare("
            UPDATE student_sessions
            SET is_online = 1, last_heartbeat = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $updateStmt->execute([(int)$session['id']]);
    }

    echo json_encode([
        'status' => 'success',
        'commands' => [
            'remote_brightness' => (int)$session['remote_brightness'],
            'force_exit' => (int)$session['force_exit'] === 1,
            'is_locked' => (int)$session['is_locked'] === 1,
            'violation_count' => (int)($session['violation_count'] ?? 0),
            'max_violations' => (int)($session['max_violations'] ?? 3),
            'school_active' => (int)$session['is_active'] === 1
        ],
        'server_time' => time()
    ], JSON_UNESCAPED_UNICODE);

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Kesalahan server: ' . $e->getMessage()
    ]);
}
