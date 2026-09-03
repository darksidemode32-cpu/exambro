<?php
/**
 * Master Admin Log Cleaning & Maintenance
 * Native PHP 8.3
 * Allows proctors/admins to clear logs globally (all schools) or per specific school.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/security.php';

$pdo = getDb();

// Fetch schools list for filter and selection
$schools = $pdo->query("SELECT id, school_code, school_name FROM schools ORDER BY school_name ASC")->fetchAll();

// Handle Form Submission: Clear Logs
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Token CSRF tidak valid. Silakan coba lagi.');
        header('Location: clear_logs.php');
        exit;
    }

    $targetSchool = $_POST['target_school'] ?? 'all';
    $clearViolations = isset($_POST['clear_violations']);
    $clearSessions = isset($_POST['clear_sessions']);
    $resetLock = isset($_POST['reset_lock']);
    $clearLoginAttempts = isset($_POST['clear_login_attempts']);

    if (!$clearViolations && !$clearSessions && !$resetLock && !$clearLoginAttempts) {
        setFlash('warning', 'Pilih minimal satu jenis data log yang ingin dibersihkan.');
        header('Location: clear_logs.php');
        exit;
    }

    try {
        $deletedCounts = [];
        $schoolName = 'Semua Sekolah (Global)';

        if ($targetSchool !== 'all') {
            $schoolId = (int)$targetSchool;
            $findSchool = $pdo->prepare("SELECT school_name, school_code FROM schools WHERE id = ?");
            $findSchool->execute([$schoolId]);
            $sch = $findSchool->fetch();
            if ($sch) {
                $schoolName = $sch['school_name'] . " [{$sch['school_code']}]";
            }
        } else {
            $schoolId = null;
        }

        // 1. Clear Violations
        if ($clearViolations) {
            if ($schoolId !== null) {
                $stmt = $pdo->prepare("
                    DELETE FROM violations 
                    WHERE school_id = ? 
                       OR session_id IN (SELECT id FROM student_sessions WHERE school_id = ?)
                ");
                $stmt->execute([$schoolId, $schoolId]);
                $count = $stmt->rowCount();
            } else {
                $count = $pdo->exec("DELETE FROM violations");
            }
            $deletedCounts[] = "{$count} log pelanggaran siswa";
        }

        // 2. Clear Student Sessions
        if ($clearSessions) {
            if ($schoolId !== null) {
                // Delete child violations first if any remained
                $stmtV = $pdo->prepare("DELETE FROM violations WHERE session_id IN (SELECT id FROM student_sessions WHERE school_id = ?)");
                $stmtV->execute([$schoolId]);

                $stmt = $pdo->prepare("DELETE FROM student_sessions WHERE school_id = ?");
                $stmt->execute([$schoolId]);
                $count = $stmt->rowCount();
            } else {
                $pdo->exec("DELETE FROM violations");
                $count = $pdo->exec("DELETE FROM student_sessions");
            }
            $deletedCounts[] = "{$count} riwayat sesi siswa";
        }

        // 3. Reset Lockout Status (Unlocks frozen screens and resets violation count)
        if ($resetLock) {
            if ($schoolId !== null) {
                $stmt = $pdo->prepare("UPDATE student_sessions SET is_locked = 0, violation_count = 0 WHERE school_id = ?");
                $stmt->execute([$schoolId]);
                $count = $stmt->rowCount();
            } else {
                $count = $pdo->exec("UPDATE student_sessions SET is_locked = 0, violation_count = 0");
            }
            $deletedCounts[] = "status kunci {$count} siswa berhasil di-reset";
        }

        // 4. Clear Failed Admin Login Attempts
        if ($clearLoginAttempts) {
            $count = $pdo->exec("DELETE FROM login_attempts");
            $deletedCounts[] = "{$count} catatan percobaan login gagal";
        }

        $summaryText = implode(', ', $deletedCounts);
        setFlash('success', "Pembersihan log untuk <strong>{$schoolName}</strong> berhasil: {$summaryText}.");

    } catch (\Throwable $e) {
        setFlash('danger', 'Gagal membersihkan log: ' . $e->getMessage());
    }

    header('Location: clear_logs.php');
    exit;
}

// Fetch Current Counts for Display
$preSelectedSchool = isset($_GET['school_id']) ? $_GET['school_id'] : 'all';

$totalViolations = (int)$pdo->query("SELECT COUNT(*) FROM violations")->fetchColumn();
$totalSessions = (int)$pdo->query("SELECT COUNT(*) FROM student_sessions")->fetchColumn();
$totalLocked = (int)$pdo->query("SELECT COUNT(*) FROM student_sessions WHERE is_locked = 1")->fetchColumn();
$totalAttempts = (int)$pdo->query("SELECT COUNT(*) FROM login_attempts")->fetchColumn();

$pageTitle = 'Pembersihan Log (Clear Log)';
$pageHeading = 'Menu Pembersihan Log & Maintenance';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Stat Cards Overview -->
<div class="stats-grid" style="margin-bottom: 24px;">
    <div class="card stat-card">
        <div class="stat-info">
            <h3>Total Log Pelanggaran</h3>
            <div class="stat-number" style="color: <?= $totalViolations > 0 ? '#EF4444' : 'var(--text-main)' ?>;"><?= $totalViolations ?></div>
        </div>
        <div class="stat-icon rose">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
        </div>
    </div>

    <div class="card stat-card">
        <div class="stat-info">
            <h3>Total Sesi Siswa</h3>
            <div class="stat-number"><?= $totalSessions ?></div>
        </div>
        <div class="stat-icon indigo">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
        </div>
    </div>

    <div class="card stat-card">
        <div class="stat-info">
            <h3>Siswa Terkunci (Auto-Lock)</h3>
            <div class="stat-number" style="color: <?= $totalLocked > 0 ? '#F59E0B' : 'var(--text-main)' ?>;"><?= $totalLocked ?></div>
        </div>
        <div class="stat-icon emerald">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
        </div>
    </div>

    <div class="card stat-card">
        <div class="stat-info">
            <h3>Log Percobaan Login</h3>
            <div class="stat-number"><?= $totalAttempts ?></div>
        </div>
        <div class="stat-icon">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
        </div>
    </div>
</div>

<!-- Main Clear Log Form Card -->
<div class="card" style="max-width: 820px; margin: 0 auto 30px; border-left: 4px solid var(--danger);">
    <div style="border-bottom: 1px solid var(--border-color); padding-bottom: 16px; margin-bottom: 24px;">
        <h3 style="font-size: 1.25rem; font-weight: 800; color: #FFFFFF; display: flex; align-items: center; gap: 10px;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#EF4444" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
            Form Pembersihan Log Ujian
        </h3>
        <p style="font-size: 0.85rem; color: var(--text-dim); margin-top: 4px;">
            Gunakan fitur ini untuk mereset riwayat sebelum sesi ujian baru dimulai. Anda dapat membersihkan data secara global (semua sekolah) atau hanya untuk instansi sekolah tertentu.
        </p>
    </div>

    <form method="POST" action="clear_logs.php" id="clearLogForm" onsubmit="return confirmClearLog(event)">
        <?= csrfField() ?>

        <!-- Target Scope Dropdown -->
        <div class="form-group" style="margin-bottom: 24px;">
            <label class="form-label" for="targetSchoolSelect" style="font-size: 0.95rem; font-weight: 700;">
                1. Pilih Cakupan Sekolah:
            </label>
            <select name="target_school" id="targetSchoolSelect" class="form-control" style="font-size: 0.95rem; padding: 12px 14px; font-weight: 600;">
                <option value="all" <?= $preSelectedSchool === 'all' ? 'selected' : '' ?>>
                    🌐 Semua Sekolah (Global - Seluruh Data Log)
                </option>
                <optgroup label="Pilih Per Instansi Sekolah:">
                    <?php foreach ($schools as $sch): ?>
                        <option value="<?= $sch['id'] ?>" <?= (string)$sch['id'] === (string)$preSelectedSchool ? 'selected' : '' ?>>
                            🏫 <?= e($sch['school_code']) ?> - <?= e($sch['school_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </optgroup>
            </select>
            <small style="color: var(--text-muted); font-size: 0.78rem; display: block; margin-top: 6px;">
                Jika memilih sekolah tertentu, hanya data log dari siswa sekolah tersebut yang akan dibersihkan.
            </small>
        </div>

        <!-- Log Types Selection -->
        <div class="form-group" style="margin-bottom: 28px;">
            <label class="form-label" style="font-size: 0.95rem; font-weight: 700; margin-bottom: 12px;">
                2. Pilih Kategori Log yang Akan Dihapus:
            </label>

            <div style="display: flex; flex-direction: column; gap: 14px;">
                <!-- Violations Checkbox -->
                <label style="display: flex; align-items: flex-start; gap: 12px; cursor: pointer; background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); padding: 14px 18px; border-radius: 10px;">
                    <input type="checkbox" name="clear_violations" value="1" checked style="width: 20px; height: 20px; margin-top: 2px; accent-color: #EF4444;">
                    <div>
                        <div style="font-weight: 700; color: #FFFFFF; font-size: 0.92rem;">
                            Log Pelanggaran Siswa (Violations)
                        </div>
                        <div style="font-size: 0.78rem; color: var(--text-dim); margin-top: 2px;">
                            Menghapus seluruh audit kecurangan seperti menekan tombol ESC, minimize browser, membuka tab Google, atau menekan tombol pintas.
                        </div>
                    </div>
                </label>

                <!-- Student Sessions Checkbox -->
                <label style="display: flex; align-items: flex-start; gap: 12px; cursor: pointer; background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); padding: 14px 18px; border-radius: 10px;">
                    <input type="checkbox" name="clear_sessions" value="1" checked style="width: 20px; height: 20px; margin-top: 2px; accent-color: #EF4444;">
                    <div>
                        <div style="font-weight: 700; color: #FFFFFF; font-size: 0.92rem;">
                            Riwayat Sesi Siswa (Student Sessions &amp; GPS)
                        </div>
                        <div style="font-size: 0.78rem; color: var(--text-dim); margin-top: 2px;">
                            Menghapus riwayat login siswa, data perangkat (merk, model, resolusi), jejak IP, dan titik koordinat GPS dari database.
                        </div>
                    </div>
                </label>

                <!-- Reset Lockout Checkbox -->
                <label style="display: flex; align-items: flex-start; gap: 12px; cursor: pointer; background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); padding: 14px 18px; border-radius: 10px;">
                    <input type="checkbox" name="reset_lock" value="1" checked style="width: 20px; height: 20px; margin-top: 2px; accent-color: #10B981;">
                    <div>
                        <div style="font-weight: 700; color: #FFFFFF; font-size: 0.92rem;">
                            Reset Status Kunci Siswa (Auto-Lockout Reset)
                        </div>
                        <div style="font-size: 0.78rem; color: var(--text-dim); margin-top: 2px;">
                            Membuka kembali semua layar siswa yang saat ini berstatus dibekukan (terkunci) dan mengembalikan counter pelanggaran siswa ke 0.
                        </div>
                    </div>
                </label>

                <!-- Login Attempts Checkbox -->
                <label style="display: flex; align-items: flex-start; gap: 12px; cursor: pointer; background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); padding: 14px 18px; border-radius: 10px;">
                    <input type="checkbox" name="clear_login_attempts" value="1" style="width: 20px; height: 20px; margin-top: 2px; accent-color: #0284C7;">
                    <div>
                        <div style="font-weight: 700; color: #FFFFFF; font-size: 0.92rem;">
                            Riwayat Percobaan Login Admin (Brute-Force Logs)
                        </div>
                        <div style="font-size: 0.78rem; color: var(--text-dim); margin-top: 2px;">
                            Menghapus daftar riwayat percobaan gagal login ke akun administrator Web Admin (berlaku global).
                        </div>
                    </div>
                </label>
            </div>
        </div>

        <!-- Warning Alert Box -->
        <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 8px; padding: 14px 18px; margin-bottom: 24px; display: flex; align-items: flex-start; gap: 12px;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#EF4444" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
            <div style="font-size: 0.82rem; color: #FECACA; line-height: 1.5;">
                <strong>Perhatian:</strong> Tindakan pembersihan ini permanen dan tidak dapat dibatalkan. Pastikan seluruh laporan ujian atau rekapitulasi data telah diunduh sebelum menjalankan pembersihan log.
            </div>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 12px;">
            <a href="security_logs.php" class="btn btn-secondary">
                Batal / Kembali
            </a>
            <button type="submit" class="btn btn-danger" style="padding: 12px 24px; font-size: 0.95rem;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                Bersihkan Log Sekarang
            </button>
        </div>
    </form>
</div>

<script>
function confirmClearLog(e) {
    const select = document.getElementById('targetSchoolSelect');
    const schoolText = select.options[select.selectedIndex].text.trim();
    
    const isConfirmed = confirm(
        `PERINGATAN PEMBERSIHAN LOG:\n\n` +
        `Anda akan menghapus log untuk:\n"${schoolText}"\n\n` +
        `Apakah Anda yakin ingin melanjutkan proses pembersihan ini? Data yang terhapus tidak dapat dikembalikan.`
    );

    if (!isConfirmed) {
        e.preventDefault();
        return false;
    }
    return true;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
