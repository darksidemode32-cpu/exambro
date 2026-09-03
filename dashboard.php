<?php
/**
 * Master Admin Live Proctored Dashboard
 * Native PHP 8.3
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/security.php';
require_once __DIR__ . '/config/token_generator.php';

$pdo = getDb();

// Handle AJAX brightness update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_brightness') {
    header('Content-Type: application/json; charset=utf-8');
    $brightness = max(10, min(100, (int)($_POST['brightness'] ?? 80)));
    $schoolId = $_POST['school_id'] ?? 'all';

    if ($schoolId === 'all') {
        $stmt = $pdo->prepare("UPDATE schools SET remote_brightness = ?");
        $stmt->execute([$brightness]);
    } else {
        $stmt = $pdo->prepare("UPDATE schools SET remote_brightness = ? WHERE id = ?");
        $stmt->execute([$brightness, (int)$schoolId]);
    }

    echo json_encode(['status' => 'success', 'brightness' => $brightness]);
    exit;
}

// Handle AJAX student unlock
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

// Fetch aggregate statistics
$stmt = $pdo->query("SELECT COUNT(*) FROM schools WHERE is_active = 1");
$totalSchools = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM student_sessions WHERE is_online = 1 AND last_heartbeat >= datetime('now', '-30 seconds')");
$onlineStudents = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM student_sessions");
$totalSessions = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM violations");
$totalViolations = (int)$stmt->fetchColumn();

// Fetch schools for dropdown
$schoolsStmt = $pdo->query("SELECT id, school_code, school_name, remote_brightness FROM schools ORDER BY school_name ASC");
$schools = $schoolsStmt->fetchAll();

// Default global brightness
$currentBrightness = !empty($schools) ? (int)$schools[0]['remote_brightness'] : 80;

// Fetch initial active student sessions
$studentStmt = $pdo->query("
    SELECT s.*, sc.school_name, sc.school_code, sc.max_violations
    FROM student_sessions s
    LEFT JOIN schools sc ON s.school_id = sc.id
    ORDER BY s.is_online DESC, s.last_heartbeat DESC
    LIMIT 20
");
$recentStudents = $studentStmt->fetchAll();

$tokenInfo = getCurrentExitTokenInfo();
$pageTitle = 'Dashboard Pengawasan Live';
$pageHeading = 'Pusat Kendali Pengawasan Ujian';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Statistics Overview -->
<div class="stats-grid">
    <div class="card stat-card">
        <div class="stat-info">
            <h3>Siswa Online Ujian</h3>
            <div class="stat-number" id="statOnlineStudents" style="color: var(--success);"><?= $onlineStudents ?></div>
        </div>
        <div class="stat-icon emerald">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><polyline points="16 11 18 13 22 9"></polyline></svg>
        </div>
    </div>

    <div class="card stat-card">
        <div class="stat-info">
            <h3>Sekolah Terdaftar</h3>
            <div class="stat-number"><?= $totalSchools ?></div>
        </div>
        <div class="stat-icon">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
        </div>
    </div>

    <div class="card stat-card">
        <div class="stat-info">
            <h3>Total Sesi Masuk</h3>
            <div class="stat-number" id="statTotalSessions"><?= $totalSessions ?></div>
        </div>
        <div class="stat-icon indigo">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
        </div>
    </div>

    <div class="card stat-card">
        <div class="stat-info">
            <h3>Log Pelanggaran</h3>
            <div class="stat-number" style="color: <?= $totalViolations > 0 ? 'var(--danger)' : 'var(--text-main)' ?>;"><?= $totalViolations ?></div>
        </div>
        <div class="stat-icon rose">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
        </div>
    </div>
</div>

<!-- Core PRD Feature Highlight Grid: 5-Minute Token & Remote Brightness -->
<div class="hero-grid">
    <!-- 1. Dynamic 5-Minute Rotating Exit Password Card -->
    <div class="card token-card">
        <div class="token-header">
            <span class="token-badge">PRD &bull; Dynamic Exit Password</span>
            <span style="font-size: 0.78rem; color: var(--text-dim);">Rotasi Otomatis Setiap 5 Menit</span>
        </div>

        <div class="token-content-flex">
            <div class="token-display-box">
                <div class="token-sublabel">Password Keluar Siswa Aktif:</div>
                <div class="token-giant">
                    <span id="currentTokenVal"><?= e($tokenInfo['token']) ?></span>
                    <button type="button" class="copy-btn" data-copy="currentTokenVal" title="Salin Token">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                    </button>
                </div>
                <p style="font-size: 0.76rem; color: var(--text-dim); margin-top: 8px;">
                    Berikan kode ini kepada siswa yang telah selesai ujian. Token kedaluwarsa setelah 5 menit dan akan otomatis ditolak oleh APK.
                </p>
            </div>

            <!-- SVG Circular Countdown -->
            <div class="countdown-container" title="Sisa Waktu Masa Aktif Token">
                <svg class="countdown-svg" viewBox="0 0 90 90">
                    <circle class="countdown-circle-bg" cx="45" cy="45" r="38"></circle>
                    <circle class="countdown-circle-progress" id="countdownProgress" cx="45" cy="45" r="38"></circle>
                </svg>
                <div class="countdown-text">
                    <div class="countdown-sec" id="countdownSec">--:--</div>
                    <div class="countdown-label">Sisa</div>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Remote Screen Brightness Controller Card -->
    <div class="card brightness-card">
        <div class="token-header">
            <span class="token-badge" style="background: rgba(245, 158, 11, 0.15); color: var(--warning);">
                PRD &bull; Device Control
            </span>
            <div style="font-size: 0.78rem; color: var(--text-dim); display: flex; align-items: center; gap: 8px;">
                <span>Target:</span>
                <select id="brightnessSchoolSelect" class="form-control" style="padding: 3px 8px; font-size: 0.75rem; width: auto;">
                    <option value="all">Semua Sekolah (Global)</option>
                    <?php foreach ($schools as $sch): ?>
                        <option value="<?= $sch['id'] ?>"><?= e($sch['school_code']) ?> - <?= e($sch['school_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="slider-val-header">
            <h4>Tingkat Kecerahan Layar Android:</h4>
            <span class="percent" id="brightnessPercentLabel"><?= $currentBrightness ?>%</span>
        </div>

        <div class="brightness-slider-wrapper">
            <input type="range" id="remoteBrightnessSlider" class="custom-range" min="10" max="100" step="5" value="<?= $currentBrightness ?>">
        </div>

        <div class="quick-brightness-buttons">
            <button type="button" class="btn-preset" data-val="30">30% (Redup)</button>
            <button type="button" class="btn-preset" data-val="60">60% (Sedang)</button>
            <button type="button" class="btn-preset" data-val="85">85% (Standar)</button>
            <button type="button" class="btn-preset" data-val="100">100% (Maksimal)</button>
        </div>
        <p style="font-size: 0.75rem; color: var(--text-dim); margin-top: 12px;">
            Perubahan nilai slider langsung disinkronkan ke seluruh layar perangkat Android siswa secara real-time melalui heartbeat.
        </p>
    </div>
</div>

<!-- GitHub Actions Cloud Build Info Banner (Laptop Memory Solution) -->
<div class="card" style="margin-bottom: 30px; background: linear-gradient(135deg, rgba(15, 23, 42, 0.95), rgba(30, 41, 59, 0.8)); border-left: 4px solid var(--primary);">
    <div style="display: flex; align-items: center; justify-content: space-between; gap: 20px; flex-wrap: wrap;">
        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(56, 189, 248, 0.15); display: flex; align-items: center; justify-content: center; color: var(--primary);">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg>
            </div>
            <div>
                <h4 style="font-size: 1rem; font-weight: 700; color: #FFFFFF;">Build APK Otomatis di Cloud (Tanpa Android Studio)</h4>
                <p style="font-size: 0.82rem; color: var(--text-muted); margin-top: 2px;">
                    Laptop Anda penuh? Kami telah menyertakan workflow <code>.github/workflows/build-apk.yml</code>. Cukup push project ini ke GitHub, file APK otomatis ter-compile gratis di GitHub Actions!
                </p>
            </div>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="student/index.php" target="_blank" class="btn btn-primary btn-sm">
                Buka Web Kiosk Simulator &#x2197;
            </a>
        </div>
    </div>
</div>

<!-- Real-Time Live Student Monitoring Table -->
<div class="card table-card">
    <div class="table-header">
        <div class="table-title">
            <h3>Monitoring Siswa Real-Time (Live)</h3>
            <p>Terbarui otomatis setiap 3.5 detik tanpa reload halaman &bull; Mencatat perangkat, IP, dan koordinat GPS</p>
        </div>
        <div class="table-actions">
            <span class="server-pill" style="font-size: 0.75rem;">
                <span class="pulse-dot"></span> Live Polling Active
            </span>
            <a href="students.php" class="btn btn-secondary btn-sm">Lihat Log Lengkap</a>
        </div>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nama &amp; Sekolah</th>
                    <th>Perangkat &amp; OS</th>
                    <th>IP Address</th>
                    <th>Baterai</th>
                    <th>Pelanggaran</th>
                    <th>Status Layar</th>
                    <th>Pelacakan GPS</th>
                    <th>Aksi Remote</th>
                </tr>
            </thead>
            <tbody id="liveStudentsTableBody">
                <?php if (empty($recentStudents)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; color: var(--text-dim); padding: 30px;">
                            Belum ada siswa yang login ke sesi ujian. Buka <a href="student/index.php" target="_blank" style="color: var(--primary);">Simulator Siswa</a> untuk memulai simulasi.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentStudents as $s): 
                        $isOnline = (int)$s['is_online'] === 1;
                        $hasLocation = !empty($s['latitude']) && !empty($s['longitude']);
                        $isLocked = (int)($s['is_locked'] ?? 0) === 1;
                        $vCount = (int)($s['violation_count'] ?? 0);
                        $maxV = (int)($s['max_violations'] ?? 3);
                    ?>
                        <tr id="session-row-<?= $s['id'] ?>">
                            <td>
                                <div style="font-weight: 700; color: var(--text-main);"><?= e($s['student_name']) ?></div>
                                <div style="font-size: 0.75rem; color: var(--text-dim);"><?= e($s['school_name'] ?? $s['school_code']) ?></div>
                            </td>
                            <td>
                                <div style="font-weight: 600;"><?= e($s['device_brand']) ?> <?= e($s['device_model']) ?></div>
                                <div style="font-size: 0.75rem; color: var(--text-dim);"><?= e($s['device_os']) ?> &bull; <?= e($s['screen_resolution'] ?: '-') ?></div>
                            </td>
                            <td>
                                <code style="background: rgba(255,255,255,0.06); padding: 2px 6px; border-radius: 4px; font-size: 0.8rem;"><?= e($s['ip_address']) ?></code>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="6" width="18" height="12" rx="2"></rect><line x1="23" y1="13" x2="23" y2="11"></line></svg>
                                    <span><?= (int)$s['battery_level'] ?>%</span>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight: 700; font-size: 0.88rem; color: <?= $vCount > 0 ? ($vCount >= $maxV ? '#EF4444' : '#F59E0B') : 'var(--text-muted)' ?>;">
                                    <?= $vCount ?> / <?= $maxV ?>
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
                                    <span class="badge badge-offline">Offline</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($hasLocation): ?>
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="openLocationModal(<?= $s['latitude'] ?>, <?= $s['longitude'] ?>, '<?= e(addslashes($s['student_name'])) ?>', '<?= e(addslashes($s['device_model'])) ?>')">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg> Peta GPS
                                    </button>
                                <?php else: ?>
                                    <span style="color: var(--text-dim); font-size: 0.78rem;">Tidak Ada GPS</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: 6px;">
                                    <?php if ($isLocked): ?>
                                        <button type="button" class="btn btn-sm" style="background: #10B981; color: white;" onclick="unlockStudent(<?= $s['id'] ?>, '<?= e(addslashes($s['student_name'])) ?>')">
                                            🔓 Buka Kunci
                                        </button>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-danger btn-sm" onclick="forceExitStudent(<?= $s['id'] ?>, '<?= e(addslashes($s['student_name'])) ?>')">
                                        Force Exit
                                    </button>
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
