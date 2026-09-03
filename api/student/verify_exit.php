<?php
/**
 * API: Verify 5-Minute Exit Password
 * According to PRD:
 * - Siswa tidak dapat menutup/keluar tanpa password yang benar.
 * - Password berubah otomatis setiap 5 menit.
 * - Jika password sudah melewati 5 menit, APK menolak dan menampilkan pesan peringatan agar siswa
 *   meminta password/token terbaru kepada pengawas ujian/admin.
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
require_once __DIR__ . '/../../config/token_generator.php';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?? $_POST;

$sessionToken = trim($data['session_token'] ?? '');
$submittedPassword = trim($data['exit_password'] ?? '');

if (empty($submittedPassword)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Password keluar wajib diisi.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$validation = validateExitPassword($submittedPassword);

try {
    $pdo = getDb();
    
    // Check session if provided
    if (!empty($sessionToken)) {
        $stmt = $pdo->prepare("SELECT id, school_id FROM student_sessions WHERE session_token = ?");
        $stmt->execute([$sessionToken]);
        $session = $stmt->fetch();

        if ($session) {
            if ($validation['valid']) {
                // Mark session as offline upon proper authorized exit
                $upStmt = $pdo->prepare("UPDATE student_sessions SET is_online = 0 WHERE id = ?");
                $upStmt->execute([(int)$session['id']]);
            } else {
                // Log failed exit attempt as security event
                $vStmt = $pdo->prepare("
                    INSERT INTO violations (session_id, school_id, violation_type, description)
                    VALUES (?, ?, 'failed_exit_attempt', ?)
                ");
                $desc = "Percobaan keluar gagal dengan input: '{$submittedPassword}' (Status: {$validation['status']})";
                $vStmt->execute([(int)$session['id'], (int)$session['school_id'], $desc]);
            }
        }
    }

    if ($validation['valid']) {
        echo json_encode([
            'status' => 'success',
            'valid' => true,
            'message' => $validation['message']
        ], JSON_UNESCAPED_UNICODE);
    } elseif ($validation['status'] === 'expired') {
        http_response_code(403);
        echo json_encode([
            'status' => 'expired',
            'valid' => false,
            'message' => $validation['message']
        ], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(401);
        echo json_encode([
            'status' => 'invalid',
            'valid' => false,
            'message' => $validation['message']
        ], JSON_UNESCAPED_UNICODE);
    }

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Kesalahan server: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
