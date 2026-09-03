<?php
/**
 * Administrator Profile & System Settings
 * Native PHP 8.3
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/security.php';

$pdo = getDb();
$adminId = (int)$_SESSION['admin_id'];

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Token CSRF tidak valid.');
        header('Location: settings.php');
        exit;
    }

    $name = trim($_POST['name'] ?? '');
    $oldPassword = (string)($_POST['old_password'] ?? '');
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if (empty($name)) {
        setFlash('danger', 'Nama Administrator tidak boleh kosong.');
    } else {
        $stmt = $pdo->prepare("SELECT password_hash FROM admins WHERE id = ?");
        $stmt->execute([$adminId]);
        $currentHash = $stmt->fetchColumn();

        if (!empty($newPassword)) {
            if (!password_verify($oldPassword, $currentHash)) {
                setFlash('danger', 'Password lama yang Anda masukkan salah.');
            } elseif (strlen($newPassword) < 6) {
                setFlash('danger', 'Password baru minimal harus 6 karakter.');
            } elseif ($newPassword !== $confirmPassword) {
                setFlash('danger', 'Konfirmasi password baru tidak cocok.');
            } else {
                $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 10]);
                $upStmt = $pdo->prepare("UPDATE admins SET name = ?, password_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $upStmt->execute([$name, $newHash, $adminId]);
                $_SESSION['admin_name'] = $name;
                setFlash('success', 'Profil dan password administrator berhasil diperbarui.');
            }
        } else {
            // Update name only
            $upStmt = $pdo->prepare("UPDATE admins SET name = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $upStmt->execute([$name, $adminId]);
            $_SESSION['admin_name'] = $name;
            setFlash('success', 'Profil nama administrator berhasil diperbarui.');
        }
    }
    header('Location: settings.php');
    exit;
}

// Fetch current admin profile
$stmt = $pdo->prepare("SELECT username, name, role, created_at FROM admins WHERE id = ?");
$stmt->execute([$adminId]);
$admin = $stmt->fetch();

$dbSize = file_exists(DB_SQLITE_PATH) ? round(filesize(DB_SQLITE_PATH) / 1024, 2) : 0;

$pageTitle = 'Pengaturan Sistem';
$pageHeading = 'Pengaturan Akun & Server';
require_once __DIR__ . '/includes/header.php';
?>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
    <!-- Profile & Password Form -->
    <div class="card">
        <h3 style="font-size: 1.15rem; font-weight: 800; margin-bottom: 6px; color: #FFFFFF;">Profil &amp; Keamanan Akun</h3>
        <p style="font-size: 0.82rem; color: var(--text-dim); margin-bottom: 20px;">
            Perbarui nama tampilan atau ganti password login administrator master
        </p>

        <form method="POST" action="settings.php">
            <?= csrfField() ?>

            <div class="form-group">
                <label class="form-label">Username</label>
                <input type="text" class="form-control" value="<?= e($admin['username']) ?>" disabled style="opacity: 0.6; cursor: not-allowed;">
                <small style="color: var(--text-dim); font-size: 0.75rem;">Username master tidak dapat diubah.</small>
            </div>

            <div class="form-group">
                <label class="form-label" for="name">Nama Lengkap Administrator</label>
                <input type="text" name="name" id="name" class="form-control" value="<?= e($admin['name']) ?>" required>
            </div>

            <hr style="border: none; border-top: 1px solid var(--border-color); margin: 24px 0;">

            <h4 style="font-size: 0.95rem; font-weight: 700; color: var(--text-muted); margin-bottom: 14px;">Ganti Password (Opsional)</h4>

            <div class="form-group">
                <label class="form-label" for="old_password">Password Lama</label>
                <input type="password" name="old_password" id="old_password" class="form-control" placeholder="Masukkan password saat ini">
            </div>

            <div class="form-group">
                <label class="form-label" for="new_password">Password Baru</label>
                <input type="password" name="new_password" id="new_password" class="form-control" placeholder="Minimal 6 karakter">
            </div>

            <div class="form-group">
                <label class="form-label" for="confirm_password">Ulangi Password Baru</label>
                <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Konfirmasi password baru">
            </div>

            <button type="submit" class="btn btn-primary" style="margin-top: 10px;">
                Simpan Perubahan
            </button>
        </form>
    </div>

    <!-- System Diagnostics & Environment Specs -->
    <div class="card">
        <h3 style="font-size: 1.15rem; font-weight: 800; margin-bottom: 6px; color: #FFFFFF;">Informasi Lingkungan Sistem</h3>
        <p style="font-size: 0.82rem; color: var(--text-dim); margin-bottom: 20px;">
            Spesifikasi Native PHP 8.3 &bull; Target ukuran super ringan ~1 MB
        </p>

        <div style="display: flex; flex-direction: column; gap: 14px;">
            <div style="display: flex; justify-content: space-between; padding: 12px 16px; background: rgba(0,0,0,0.2); border-radius: var(--radius-sm);">
                <span style="color: var(--text-muted);">Versi PHP</span>
                <strong style="color: var(--primary);">PHP <?= PHP_VERSION ?></strong>
            </div>

            <div style="display: flex; justify-content: space-between; padding: 12px 16px; background: rgba(0,0,0,0.2); border-radius: var(--radius-sm);">
                <span style="color: var(--text-muted);">Database Driver</span>
                <strong>PDO (<?= strtoupper(DB_DRIVER) ?>)</strong>
            </div>

            <div style="display: flex; justify-content: space-between; padding: 12px 16px; background: rgba(0,0,0,0.2); border-radius: var(--radius-sm);">
                <span style="color: var(--text-muted);">Ukuran Database SQLite</span>
                <strong style="color: var(--success);"><?= $dbSize ?> KB</strong>
            </div>

            <div style="display: flex; justify-content: space-between; padding: 12px 16px; background: rgba(0,0,0,0.2); border-radius: var(--radius-sm);">
                <span style="color: var(--text-muted);">Interval Exit Password</span>
                <strong>5 Menit (300 Detik) &bull; Dynamic TOTP</strong>
            </div>

            <div style="display: flex; justify-content: space-between; padding: 12px 16px; background: rgba(0,0,0,0.2); border-radius: var(--radius-sm);">
                <span style="color: var(--text-muted);">Proteksi Brute-Force</span>
                <span class="badge badge-online">Aktif (Max 5x Gagal)</span>
            </div>

            <div style="display: flex; justify-content: space-between; padding: 12px 16px; background: rgba(0,0,0,0.2); border-radius: var(--radius-sm);">
                <span style="color: var(--text-muted);">Proteksi Form</span>
                <span class="badge badge-online">CSRF Token Verified</span>
            </div>
        </div>

        <div style="margin-top: 24px; padding: 16px; background: rgba(56, 189, 248, 0.08); border-left: 4px solid var(--primary); border-radius: 4px;">
            <h4 style="font-size: 0.9rem; font-weight: 700; color: #FFFFFF;">Arsitektur White-Label</h4>
            <p style="font-size: 0.78rem; color: var(--text-muted); margin-top: 4px;">
                Aplikasi Exambro dirancang untuk fleksibilitas maksimal: tidak perlu mengkompilasi ulang source code APK untuk setiap sekolah. Cukup gunakan kode sekolah untuk me-route endpoint secara dinamis.
            </p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
