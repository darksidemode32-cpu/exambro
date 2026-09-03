<?php
/**
 * API: School Configuration Endpoint (Dynamic White-label & Routing)
 * According to PRD:
 * Takes School Code / QR Code and returns specific school config:
 * - Exam Server URL
 * - School Name, Logo, Banner, Address, Contact
 * - Current Remote Brightness setting
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

$schoolCode = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);
    if (is_array($body) && !empty($body['school_code'])) {
        $schoolCode = (string)$body['school_code'];
    } elseif (!empty($_POST['school_code'])) {
        $schoolCode = (string)$_POST['school_code'];
    }
} else {
    $schoolCode = (string)($_GET['code'] ?? $_GET['school_code'] ?? '');
}

$schoolCode = strtoupper(trim($schoolCode));

if (empty($schoolCode)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Kode Sekolah wajib diisi.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = getDb();
    $stmt = $pdo->prepare("
        SELECT id, school_code, school_name, exam_url, logo_url, banner_url, announcement, address, contact, remote_brightness, max_violations, is_active
        FROM schools
        WHERE UPPER(school_code) = ?
    ");
    $stmt->execute([$schoolCode]);
    $school = $stmt->fetch();

    if (!$school) {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => "Kode Sekolah '{$schoolCode}' tidak ditemukan di sistem master server."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ((int)$school['is_active'] !== 1) {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => "Instansi sekolah '{$school['school_name']}' sedang dinonaktifkan oleh administrator."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Default placeholder logo and banner if empty
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME'], 2), '/\\');
    
    $logo = !empty($school['logo_url']) ? $school['logo_url'] : $baseUrl . '/assets/img/default-logo.svg';
    $banner = !empty($school['banner_url']) ? $school['banner_url'] : $baseUrl . '/assets/img/default-banner.svg';

    echo json_encode([
        'status' => 'success',
        'message' => 'Konfigurasi sekolah berhasil dimuat.',
        'data' => [
            'id' => (int)$school['id'],
            'school_code' => $school['school_code'],
            'school_name' => $school['school_name'],
            'exam_url' => $school['exam_url'],
            'logo_url' => $logo,
            'banner_url' => $banner,
            'announcement' => $school['announcement'] ?? '',
            'address' => $school['address'],
            'contact' => $school['contact'],
            'remote_brightness' => (int)$school['remote_brightness'],
            'max_violations' => isset($school['max_violations']) ? (int)$school['max_violations'] : 3,
            'server_time' => time(),
            'datetime_str' => date('Y-m-d H:i:s')
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Terjadi kesalahan server: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
