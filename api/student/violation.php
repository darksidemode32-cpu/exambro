<?php
/**
 * API: Log Student Violation & Anti-Cheating Breaches
 * According to PRD:
 * - Anti Split-Screen detection
 * - Block navigation and home button events
 * - Recording cheating attempts for proctor review
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
$violationType = sanitizeInput($data['violation_type'] ?? 'unknown');
$description = sanitizeInput($data['description'] ?? '');

try {
    $pdo = getDb();
    $sessionId = null;
    $schoolId = null;

    if (!empty($sessionToken)) {
        $stmt = $pdo->prepare("SELECT id, school_id FROM student_sessions WHERE session_token = ?");
        $stmt->execute([$sessionToken]);
        $session = $stmt->fetch();
        if ($session) {
            $sessionId = (int)$session['id'];
            $schoolId = (int)$session['school_id'];
        }
    }

    $insertStmt = $pdo->prepare("
        INSERT INTO violations (session_id, school_id, violation_type, description, created_at)
        VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
    ");
    $insertStmt->execute([$sessionId, $schoolId, $violationType, $description]);

    $newViolationCount = 1;
    $maxViolations = 3;
    $isLocked = false;

    if ($sessionId) {
        // Increment student session violation count
        $upStmt = $pdo->prepare("
            UPDATE student_sessions 
            SET violation_count = COALESCE(violation_count, 0) + 1 
            WHERE id = ?
        ");
        $upStmt->execute([$sessionId]);

        // Check against school max_violations
        $checkStmt = $pdo->prepare("
            SELECT s.violation_count, sc.max_violations
            FROM student_sessions s
            JOIN schools sc ON s.school_id = sc.id
            WHERE s.id = ?
        ");
        $checkStmt->execute([$sessionId]);
        $checkData = $checkStmt->fetch();

        if ($checkData) {
            $newViolationCount = (int)$checkData['violation_count'];
            $maxViolations = isset($checkData['max_violations']) ? (int)$checkData['max_violations'] : 3;

            if ($newViolationCount >= $maxViolations) {
                $isLocked = true;
                $lockStmt = $pdo->prepare("UPDATE student_sessions SET is_locked = 1 WHERE id = ?");
                $lockStmt->execute([$sessionId]);
            }
        }
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Pelanggaran berhasil dicatat di server pengawas.',
        'violation_count' => $newViolationCount,
        'max_violations' => $maxViolations,
        'is_locked' => $isLocked
    ]);

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Gagal mencatat pelanggaran: ' . $e->getMessage()
    ]);
}
