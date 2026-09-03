<?php
/**
 * API: Student Exam Session Registration & Identification Log
 * According to PRD:
 * - Waktu masuk (timestamp) presisi
 * - Identifikasi perangkat (merk, model, nama, OS)
 * - Jejak jaringan (IP Address)
 * - Pelacakan lokasi (Koordinat GPS latitude/longitude & akurasi)
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

$schoolCode = strtoupper(trim($data['school_code'] ?? ''));
$studentName = trim($data['student_name'] ?? 'Siswa Ujian');
$deviceBrand = trim($data['device_brand'] ?? 'Android');
$deviceModel = trim($data['device_model'] ?? 'Generic Device');
$deviceOs = trim($data['device_os'] ?? 'Android');
$screenRes = trim($data['screen_resolution'] ?? '');
$batteryLevel = isset($data['battery_level']) ? (int)$data['battery_level'] : 100;

$latitude = isset($data['latitude']) && is_numeric($data['latitude']) ? (float)$data['latitude'] : null;
$longitude = isset($data['longitude']) && is_numeric($data['longitude']) ? (float)$data['longitude'] : null;
$accuracy = isset($data['location_accuracy']) && is_numeric($data['location_accuracy']) ? (float)$data['location_accuracy'] : null;

$ipAddress = getClientIp();

if (empty($schoolCode)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Kode sekolah wajib disertakan.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = getDb();
    $schoolStmt = $pdo->prepare("SELECT id, school_name, exam_url, remote_brightness, max_violations, is_active FROM schools WHERE UPPER(school_code) = ?");
    $schoolStmt->execute([$schoolCode]);
    $school = $schoolStmt->fetch();

    if (!$school) {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => "Kode sekolah '{$schoolCode}' tidak valid."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ((int)$school['is_active'] !== 1) {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => "Instansi sekolah '{$school['school_name']}' saat ini tidak aktif."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Generate unique session token
    $sessionToken = bin2hex(random_bytes(24));

    // Insert student session log
    $insertStmt = $pdo->prepare("
        INSERT INTO student_sessions (
            session_token, school_id, student_name, device_brand, device_model, device_os,
            screen_resolution, ip_address, latitude, longitude, location_accuracy,
            battery_level, is_online, last_heartbeat
        ) VALUES (
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, 1, CURRENT_TIMESTAMP
        )
    ");

    $insertStmt->execute([
        $sessionToken,
        (int)$school['id'],
        $studentName,
        $deviceBrand,
        $deviceModel,
        $deviceOs,
        $screenRes,
        $ipAddress,
        $latitude,
        $longitude,
        $accuracy,
        $batteryLevel
    ]);

    $sessionId = (int)$pdo->lastInsertId();

    echo json_encode([
        'status' => 'success',
        'message' => 'Sesi ujian berhasil dibuat.',
        'data' => [
            'session_id' => $sessionId,
            'session_token' => $sessionToken,
            'school_name' => $school['school_name'],
            'exam_url' => $school['exam_url'],
            'remote_brightness' => (int)$school['remote_brightness'],
            'max_violations' => isset($school['max_violations']) ? (int)$school['max_violations'] : 3,
            'ip_address' => $ipAddress,
            'has_location' => ($latitude !== null && $longitude !== null),
            'login_time' => date('Y-m-d H:i:s')
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Gagal mencatat sesi: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
