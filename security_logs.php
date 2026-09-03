<?php
/**
 * Security Audit & IP Blocking Management
 * Native PHP 8.3
 * According to PRD:
 * - Rate Limiting & Block IP otomatis jika terdeteksi brute force
 * - Log pelanggaran siswa dan insiden keamanan sistem
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/security.php';

$pdo = getDb();

// Handle IP Unblock Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'unblock_ip') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'CSRF token invalid.');
    } else {
        $ip = trim($_POST['ip_address'] ?? '');
        if (!empty($ip)) {
            $stmt = $pdo->prepare("DELETE FROM blocked_ips WHERE ip_address = ?");
            $stmt->execute([$ip]);
            clearLoginAttempts($pdo, $ip);
            setFlash('success', "Blokir pada alamat IP '{$ip}' berhasil dicabut.");
        }
    }
    header('Location: security_logs.php');
    exit;
}

// Fetch currently blocked IPs
$blockedStmt = $pdo->query("SELECT * FROM blocked_ips ORDER BY blocked_until DESC");
$blockedIps = $blockedStmt->fetchAll();

// Fetch recent failed login attempts
$attemptsStmt = $pdo->query("SELECT * FROM login_attempts ORDER BY attempt_time DESC LIMIT 30");
$failedAttempts = $attemptsStmt->fetchAll();

// Fetch recent student violations
$violationsStmt = $pdo->query("
    SELECT v.*, s.student_name, s.device_model, sc.school_name
    FROM violations v
    LEFT JOIN student_sessions s ON v.session_id = s.id
    LEFT JOIN schools sc ON v.school_id = sc.id
    ORDER BY v.id DESC LIMIT 40
");
$violations = $violationsStmt->fetchAll();

$pageTitle = 'Log Keamanan & IP Block';
$pageHeading = 'Audit Keamanan & Proteksi Brute Force';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Blocked IPs Section -->
<div class="card table-card" style="margin-bottom: 30px; border-left: 4px solid var(--danger);">
    <div class="table-header">
        <div class="table-title">
            <h3 style="display: flex; align-items: center; gap: 10px;">
                <span class="badge badge-danger">Proteksi Aktif</span>
                Alamat IP yang Diblokir Otomatis (Rate Limiter)
            </h3>
            <p>Sistem otomatis memblokir IP yang gagal login 5 kali dalam 15 menit untuk mencegah serangan brute-force</p>
        </div>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Alamat IP</th>
                    <th>Alasan Pemblokiran</th>
                    <th>Status / Waktu Berakhir</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($blockedIps)): ?>
                    <tr>
                        <td colspan="4" style="text-align: center; color: var(--text-dim); padding: 24px;">
                            Tidak ada alamat IP yang sedang diblokir. Sistem aman dan beroperasi normal.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($blockedIps as $b): 
                        $remaining = (int)$b['blocked_until'] - time();
                        $isStillBlocked = $remaining > 0;
                    ?>
                        <tr>
                            <td>
                                <code style="background: rgba(239, 68, 68, 0.15); color: #FECACA; padding: 4px 8px; border-radius: 4px; font-weight: 700;">
                                    <?= e($b['ip_address']) ?>
                                </code>
                            </td>
                            <td style="color: var(--text-muted); font-size: 0.85rem;">
                                <?= e($b['reason']) ?>
                            </td>
                            <td>
                                <?php if ($isStillBlocked): ?>
                                    <span class="badge badge-danger">
                                        Diblokir (sisa <?= ceil($remaining / 60) ?> menit)
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-offline">Masa blokir berakhir</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" action="security_logs.php" style="display: inline;">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="unblock_ip">
                                    <input type="hidden" name="ip_address" value="<?= e($b['ip_address']) ?>">
                                    <button type="submit" class="btn btn-secondary btn-sm" style="color: var(--success); border-color: rgba(16, 185, 129, 0.3);">
                                        Buka Blokir (Unblock)
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Student Cheating & Kiosk Violations Log -->
<div class="card table-card" style="margin-bottom: 30px;">
    <div class="table-header">
        <div class="table-title">
            <h3>Log Pelanggaran Kiosk Siswa (Anti-Kecurangan)</h3>
            <p>Mencatat percobaan split-screen, membuka aplikasi lain, salah input exit token, dan tombol kembali</p>
        </div>
        <div>
            <a href="clear_logs.php" class="btn btn-danger btn-sm">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                Bersihkan Log
            </a>
        </div>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Waktu Kejadian</th>
                    <th>Siswa &amp; Sekolah</th>
                    <th>Jenis Pelanggaran</th>
                    <th>Keterangan Tambahan</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($violations)): ?>
                    <tr>
                        <td colspan="4" style="text-align: center; color: var(--text-dim); padding: 24px;">
                            Belum ada catatan pelanggaran atau kecurangan siswa.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($violations as $v): ?>
                        <tr>
                            <td style="white-space: nowrap; font-size: 0.82rem; color: var(--text-muted);">
                                <?= date('d M Y, H:i:s', strtotime($v['created_at'])) ?>
                            </td>
                            <td>
                                <strong style="color: #FFFFFF;"><?= e($v['student_name'] ?: 'Anonim / Belum Terdaftar') ?></strong>
                                <div style="font-size: 0.75rem; color: var(--text-dim);"><?= e($v['school_name'] ?: '-') ?></div>
                            </td>
                            <td>
                                <span class="badge badge-warning">
                                    <?= strtoupper(e($v['violation_type'])) ?>
                                </span>
                            </td>
                            <td style="font-size: 0.85rem; color: var(--text-muted);">
                                <?= e($v['description']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Recent Failed Login Attempts -->
<div class="card table-card">
    <div class="table-header">
        <div class="table-title">
            <h3>Riwayat Percobaan Login Admin (Brute-Force Monitor)</h3>
            <p>Daftar 30 percobaan login terakhir beserta alamat IP asal</p>
        </div>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Waktu Percobaan</th>
                    <th>Alamat IP</th>
                    <th>Username yang Dituju</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($failedAttempts)): ?>
                    <tr>
                        <td colspan="3" style="text-align: center; color: var(--text-dim); padding: 20px;">
                            Tidak ada catatan percobaan login yang gagal baru-baru ini.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($failedAttempts as $fa): ?>
                        <tr>
                            <td style="font-size: 0.82rem; color: var(--text-muted);">
                                <?= date('d M Y, H:i:s', (int)$fa['attempt_time']) ?>
                            </td>
                            <td>
                                <code><?= e($fa['ip_address']) ?></code>
                            </td>
                            <td>
                                <span style="color: var(--text-main); font-weight: 600;"><?= e($fa['username'] ?: '(kosong)') ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
