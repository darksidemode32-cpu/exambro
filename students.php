<?php
/**
 * Student Monitoring & Detailed Device / Location Logs
 * Native PHP 8.3
 * According to PRD:
 * - Log Login presisi
 * - Identifikasi Perangkat (Merk, Model, Nama, OS)
 * - Jejak Jaringan (IP Address)
 * - Pelacakan Lokasi (Koordinat GPS)
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/security.php';

$pdo = getDb();

// 1. Handle AJAX Polling from Dashboard / Live Table
if (isset($_GET['ajax']) && $_GET['ajax'] === 'live_list') {
    header('Content-Type: application/json; charset=utf-8');
    
    // Auto-mark sessions as offline if no heartbeat in last 45 seconds
    $pdo->exec("UPDATE student_sessions SET is_online = 0 WHERE is_online = 1 AND last_heartbeat < datetime('now', '-45 seconds')");

    $stmt = $pdo->query("
        SELECT s.*, sc.school_name, sc.school_code, sc.max_violations
        FROM student_sessions s
        LEFT JOIN schools sc ON s.school_id = sc.id
        ORDER BY s.is_online DESC, s.last_heartbeat DESC
        LIMIT 50
    ");
    $students = $stmt->fetchAll();

    $statsStmt = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM student_sessions WHERE is_online = 1) as online_count,
            (SELECT COUNT(*) FROM student_sessions) as total_sessions
    ");
    $stats = $statsStmt->fetch();

    echo json_encode([
        'status' => 'success',
        'data' => $students,
        'stats' => $stats
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 2. Handle Force Exit Action via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'force_exit') {
    header('Content-Type: application/json; charset=utf-8');
    $sessionId = (int)($_POST['session_id'] ?? 0);
    if ($sessionId > 0) {
        $stmt = $pdo->prepare("UPDATE student_sessions SET force_exit = 1, is_online = 0 WHERE id = ?");
        $stmt->execute([$sessionId]);
        echo json_encode(['status' => 'success', 'message' => 'Perintah force exit telah diaktifkan.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'ID sesi tidak valid.']);
    }
    exit;
}

// 3. Handle Unlock Student Action via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'unlock_student') {
    header('Content-Type: application/json; charset=utf-8');
    $sessionId = (int)($_POST['session_id'] ?? 0);
    if ($sessionId > 0) {
        $stmt = $pdo->prepare("UPDATE student_sessions SET is_locked = 0, violation_count = 0 WHERE id = ?");
        $stmt->execute([$sessionId]);
        echo json_encode(['status' => 'success', 'message' => 'Sesi ujian siswa berhasil dibuka kembali (Unlocked).']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'ID sesi tidak valid.']);
    }
    exit;
}

// 3. Normal Page Rendering
$schoolFilter = isset($_GET['school_id']) && is_numeric($_GET['school_id']) ? (int)$_GET['school_id'] : 0;
$search = sanitizeInput($_GET['q'] ?? '');

$sql = "
    SELECT s.*, sc.school_name, sc.school_code, sc.max_violations
    FROM student_sessions s
    LEFT JOIN schools sc ON s.school_id = sc.id
    WHERE 1=1
";
$params = [];

if ($schoolFilter > 0) {
    $sql .= " AND s.school_id = ?";
    $params[] = $schoolFilter;
}

if (!empty($search)) {
    $sql .= " AND (s.student_name LIKE ? OR s.device_model LIKE ? OR s.ip_address LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$sql .= " ORDER BY s.id DESC LIMIT 100";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sessions = $stmt->fetchAll();

// Fetch schools list for filter
$schools = $pdo->query("SELECT id, school_code, school_name FROM schools ORDER BY school_name ASC")->fetchAll();

$pageTitle = 'Monitoring & Log Siswa';
$pageHeading = 'Log Aktivitas Siswa & Pelacakan GPS';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Filter & Search Toolbar -->
<div class="card" style="margin-bottom: 24px; padding: 18px 24px;">
    <form method="GET" action="students.php" style="display: flex; gap: 16px; align-items: center; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 220px;">
            <input type="text" name="q" value="<?= e($search) ?>" class="form-control" placeholder="Cari nama siswa, merk HP, atau IP Address...">
        </div>
        <div style="min-width: 200px;">
            <select name="school_id" class="form-control">
                <option value="0">Semua Instansi Sekolah</option>
                <?php foreach ($schools as $sch): ?>
                    <option value="<?= $sch['id'] ?>" <?= $schoolFilter === (int)$sch['id'] ? 'selected' : '' ?>>
                        <?= e($sch['school_code']) ?> - <?= e($sch['school_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            Filter
        </button>
        <?php if (!empty($search) || $schoolFilter > 0): ?>
            <a href="students.php" class="btn btn-secondary btn-sm">Reset</a>
        <?php endif; ?>
    </form>
</div>

<!-- Detailed Student Log Table -->
<div class="card table-card">
    <div class="table-header">
        <div class="table-title">
            <h3>Daftar Riwayat Sesi &amp; Pelacakan Perangkat Siswa</h3>
            <p>Mencakup detail spesifikasi perangkat Android, IP address, waktu presisi, dan koordinat GPS</p>
        </div>
        <div style="display: flex; gap: 10px; align-items: center;">
            <a href="clear_logs.php<?= $schoolFilter > 0 ? '?school_id=' . $schoolFilter : '' ?>" class="btn btn-secondary btn-sm" style="color: var(--danger); border-color: rgba(239, 68, 68, 0.3);">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                Bersihkan Log
            </a>
            <span style="font-size: 0.85rem; color: var(--text-dim);">
                (<?= count($sessions) ?> sesi)
            </span>
        </div>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Waktu Masuk</th>
                    <th>Nama &amp; Sekolah</th>
                    <th>Spesifikasi Perangkat</th>
                    <th>Jejak IP</th>
                    <th>Pelanggaran</th>
                    <th>Status Layar</th>
                    <th>Koordinat GPS</th>
                    <th>Tindakan Remote</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($sessions)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; color: var(--text-dim); padding: 34px;">
                            Tidak ada data sesi siswa yang sesuai dengan kriteria pencarian.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($sessions as $s): 
                        $isOnline = (int)$s['is_online'] === 1;
                        $hasGps = !empty($s['latitude']) && !empty($s['longitude']);
                        $isLocked = (int)($s['is_locked'] ?? 0) === 1;
                        $vCount = (int)($s['violation_count'] ?? 0);
                        $maxV = (int)($s['max_violations'] ?? 3);
                    ?>
                        <tr id="session-row-<?= $s['id'] ?>">
                            <td style="white-space: nowrap; font-size: 0.82rem; color: var(--text-muted);">
                                <?= date('d M Y', strtotime($s['created_at'])) ?><br>
                                <strong style="color: var(--text-main); font-size: 0.9rem;"><?= date('H:i:s', strtotime($s['created_at'])) ?> WIB</strong>
                            </td>
                            <td>
                                <div style="font-weight: 700; color: #FFFFFF;"><?= e($s['student_name']) ?></div>
                                <div style="font-size: 0.78rem; color: var(--primary);"><?= e($s['school_name'] ?? $s['school_code']) ?></div>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: var(--text-main);">
                                    <?= e($s['device_brand']) ?> <?= e($s['device_model']) ?>
                                </div>
                                <div style="font-size: 0.75rem; color: var(--text-dim);">
                                    <?= e($s['device_os']) ?> &bull; <?= e($s['screen_resolution'] ?: 'Resolusi Standar') ?>
                                </div>
                            </td>
                            <td>
                                <code style="background: rgba(255,255,255,0.06); padding: 3px 8px; border-radius: 4px; font-size: 0.82rem; color: #38BDF8;">
                                    <?= e($s['ip_address']) ?>
                                </code>
                            </td>
                            <td>
                                <div style="font-weight: 700; font-size: 0.9rem; color: <?= $vCount > 0 ? ($vCount >= $maxV ? '#EF4444' : '#F59E0B') : 'var(--text-muted)' ?>;">
                                    <?= $vCount ?> / <?= $maxV ?> kali
                                </div>
                                <?php if ($vCount >= $maxV): ?>
                                    <span style="font-size: 0.7rem; color: #EF4444; font-weight: 700;">(Batas Tercapai)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($isLocked): ?>
                                    <span class="badge badge-danger" style="animation: pulse 1.5s infinite;">
                                        🚨 TERKUNCI (Auto-Lock)
                                    </span>
                                <?php elseif ($isOnline): ?>
                                    <span class="badge badge-online"><span class="pulse-dot"></span> Online</span>
                                <?php else: ?>
                                    <span class="badge badge-offline">Selesai / Offline</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($hasGps): ?>
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="openLocationModal(<?= $s['latitude'] ?>, <?= $s['longitude'] ?>, '<?= e(addslashes($s['student_name'])) ?>', '<?= e(addslashes($s['device_model'])) ?>')">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                        Peta GPS
                                    </button>
                                <?php else: ?>
                                    <span style="font-size: 0.78rem; color: var(--text-dim);">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: 6px;">
                                    <?php if ($isLocked): ?>
                                        <button type="button" class="btn btn-sm" style="background: #10B981; color: white;" onclick="unlockStudent(<?= $s['id'] ?>, '<?= e(addslashes($s['student_name'])) ?>')">
                                            🔓 Buka Kunci
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($isOnline): ?>
                                        <button type="button" class="btn btn-danger btn-sm" onclick="forceExitStudent(<?= $s['id'] ?>, '<?= e(addslashes($s['student_name'])) ?>')">
                                            Force Exit
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
