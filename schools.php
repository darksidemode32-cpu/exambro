<?php
/**
 * School Management (Multi-Tenant & White-Label)
 * Native PHP 8.3
 * According to PRD:
 * - Dynamic School Code / QR Code
 * - Dynamic Endpoint Routing (Exam URL, Logo, Banner, Instansi info)
 * - Single APK distribution for thousands of schools
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/security.php';

$pdo = getDb();
$action = $_GET['action'] ?? 'list';
$editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editSchool = null;

// Handle Form Submissions (Add / Edit / Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Token CSRF tidak valid. Silakan coba lagi.');
        header('Location: schools.php');
        exit;
    }

    $formAction = $_POST['form_action'] ?? 'save';

    if ($formAction === 'delete') {
        $deleteId = (int)($_POST['school_id'] ?? 0);
        if ($deleteId > 0) {
            $delStmt = $pdo->prepare("DELETE FROM schools WHERE id = ?");
            $delStmt->execute([$deleteId]);
            setFlash('success', 'Data sekolah berhasil dihapus dari sistem master.');
        }
        header('Location: schools.php');
        exit;
    }

    // Save (Add or Update)
    $schoolCode = strtoupper(trim($_POST['school_code'] ?? ''));
    $schoolName = trim($_POST['school_name'] ?? '');
    $examUrl = trim($_POST['exam_url'] ?? '');
    $announcement = trim($_POST['announcement'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $brightness = max(10, min(100, (int)($_POST['remote_brightness'] ?? 80)));
    $maxViolations = max(1, min(50, (int)($_POST['max_violations'] ?? 3)));
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $saveId = (int)($_POST['school_id'] ?? 0);

    // Handle File Uploads or URL
    $uploadDir = __DIR__ . '/uploads/schools/';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
    }
    $allowedExts = ['png', 'jpg', 'jpeg', 'svg', 'webp', 'gif'];

    // 1. Logo
    $finalLogoUrl = trim($_POST['logo_url'] ?? '');
    if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES['logo_file']['tmp_name'];
        $ext = strtolower(pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowedExts)) {
            $filename = 'logo_' . preg_replace('/[^A-Z0-9_]/', '', $schoolCode) . '_' . time() . '.' . $ext;
            $dest = $uploadDir . $filename;
            if (move_uploaded_file($tmpName, $dest)) {
                $finalLogoUrl = 'uploads/schools/' . $filename;
            }
        }
    }

    // 2. Banner
    $finalBannerUrl = trim($_POST['banner_url'] ?? '');
    if (isset($_FILES['banner_file']) && $_FILES['banner_file']['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES['banner_file']['tmp_name'];
        $ext = strtolower(pathinfo($_FILES['banner_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowedExts)) {
            $filename = 'banner_' . preg_replace('/[^A-Z0-9_]/', '', $schoolCode) . '_' . time() . '.' . $ext;
            $dest = $uploadDir . $filename;
            if (move_uploaded_file($tmpName, $dest)) {
                $finalBannerUrl = 'uploads/schools/' . $filename;
            }
        }
    }

    if (empty($schoolCode) || empty($schoolName) || empty($examUrl)) {
        setFlash('danger', 'Kode Sekolah, Nama Sekolah, dan URL Ujian CBT wajib diisi.');
    } else {
        try {
            if ($saveId > 0) {
                // Fetch existing logo/banner if not updated
                $oldStmt = $pdo->prepare("SELECT logo_url, banner_url FROM schools WHERE id = ?");
                $oldStmt->execute([$saveId]);
                $oldData = $oldStmt->fetch();

                if (empty($finalLogoUrl) && !empty($oldData['logo_url'])) {
                    $finalLogoUrl = $oldData['logo_url'];
                }
                if (empty($finalBannerUrl) && !empty($oldData['banner_url'])) {
                    $finalBannerUrl = $oldData['banner_url'];
                }

                // Update
                $stmt = $pdo->prepare("
                    UPDATE schools
                    SET school_code = ?, school_name = ?, exam_url = ?, logo_url = ?, banner_url = ?, announcement = ?, address = ?, contact = ?, remote_brightness = ?, max_violations = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stmt->execute([$schoolCode, $schoolName, $examUrl, $finalLogoUrl, $finalBannerUrl, $announcement, $address, $contact, $brightness, $maxViolations, $isActive, $saveId]);
                setFlash('success', "Data & visual sekolah '{$schoolName}' berhasil diperbarui.");
            } else {
                // Insert
                $stmt = $pdo->prepare("
                    INSERT INTO schools (school_code, school_name, exam_url, logo_url, banner_url, announcement, address, contact, remote_brightness, max_violations, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$schoolCode, $schoolName, $examUrl, $finalLogoUrl, $finalBannerUrl, $announcement, $address, $contact, $brightness, $maxViolations, $isActive]);
                setFlash('success', "Sekolah baru '{$schoolName}' [{$schoolCode}] berhasil didaftarkan.");
            }
            header('Location: schools.php');
            exit;
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'UNIQUE') || str_contains($e->getMessage(), 'Duplicate')) {
                setFlash('danger', "Kode Sekolah '{$schoolCode}' sudah digunakan oleh instansi lain. Harap gunakan kode yang berbeda.");
            } else {
                setFlash('danger', 'Gagal menyimpan data: ' . $e->getMessage());
            }
        }
    }
}

if ($action === 'edit' && $editId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM schools WHERE id = ?");
    $stmt->execute([$editId]);
    $editSchool = $stmt->fetch();
}

// Fetch all schools
$schoolsStmt = $pdo->query("
    SELECT s.*, 
           (SELECT COUNT(*) FROM student_sessions WHERE school_id = s.id AND is_online = 1) as active_students_count,
           (SELECT COUNT(*) FROM student_sessions WHERE school_id = s.id) as total_sessions_count
    FROM schools s
    ORDER BY s.id DESC
");
$schools = $schoolsStmt->fetchAll();

$pageTitle = 'Manajemen Sekolah & Multi-Tenant';
$pageHeading = 'Sekolah & Distribusi Multi-Tenant';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Header Actions -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; gap: 16px; flex-wrap: wrap;">
    <div>
        <h3 style="font-size: 1.15rem; font-weight: 800; color: #FFFFFF;">Instansi Sekolah Klien (White-Label)</h3>
        <p style="font-size: 0.82rem; color: var(--text-dim); margin-top: 2px;">
            Setiap sekolah memiliki Kode Sekolah &amp; QR Code unik. Cukup 1 APK Exambro untuk melayani ribuan sekolah berbeda.
        </p>
    </div>
    <button type="button" class="btn btn-primary" onclick="openAddSchoolModal()">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
        Tambah Sekolah Baru
    </button>
</div>

<!-- Schools Grid Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 24px; margin-bottom: 30px;">
    <?php foreach ($schools as $sch): 
        $hasBanner = !empty($sch['banner_url']);
        $hasLogo = !empty($sch['logo_url']);
        $bannerSrc = $hasBanner ? $sch['banner_url'] : 'assets/img/default-banner.svg';
        $logoSrc = $hasLogo ? $sch['logo_url'] : 'assets/img/default-logo.svg';
    ?>
        <div class="card" style="display: flex; flex-direction: column; justify-content: space-between; border-left: 4px solid <?= (int)$sch['is_active'] === 1 ? 'var(--primary)' : 'var(--text-dim)' ?>; padding: 0; overflow: hidden;">
            <!-- School Banner & Logo Header -->
            <div style="height: 110px; background: url('<?= e($bannerSrc) ?>') center/cover no-repeat, #1E293B; position: relative; border-bottom: 1px solid var(--border-color);">
                <div style="position: absolute; inset: 0; background: linear-gradient(to top, rgba(15, 23, 42, 0.85), transparent);"></div>
                <div style="position: absolute; top: 12px; right: 12px;">
                    <span class="badge <?= (int)$sch['is_active'] === 1 ? 'badge-primary' : 'badge-offline' ?>">
                        <?= e($sch['school_code']) ?>
                    </span>
                </div>
                <div style="position: absolute; bottom: -18px; left: 18px; display: flex; align-items: center; gap: 12px;">
                    <img src="<?= e($logoSrc) ?>" alt="Logo" style="width: 50px; height: 50px; border-radius: 10px; background: #0F172A; border: 2px solid var(--border-color); object-fit: contain; padding: 3px; box-shadow: 0 4px 10px rgba(0,0,0,0.4);">
                </div>
            </div>

            <div style="padding: 24px 20px 16px 20px; flex: 1;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                    <h4 style="font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin: 0; line-height: 1.3;">
                        <?= e($sch['school_name']) ?>
                    </h4>
                    <!-- Quick QR Modal Trigger -->
                    <button type="button" class="btn btn-secondary btn-sm" onclick="openQrModal('<?= e(addslashes($sch['school_code'])) ?>', '<?= e(addslashes($sch['school_name'])) ?>')" title="Lihat QR Code" style="margin-left: 10px; flex-shrink: 0;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                        QR
                    </button>
                </div>

                <?php if (!empty($sch['announcement'])): ?>
                    <div style="background: rgba(56, 189, 248, 0.08); border: 1px solid rgba(56, 189, 248, 0.25); border-radius: 8px; padding: 10px 12px; margin-bottom: 14px; font-size: 0.8rem;">
                        <div style="font-weight: 700; color: #38BDF8; display: flex; align-items: center; gap: 6px; margin-bottom: 2px;">
                            <span>📢</span> Pesan Pengawas ke Siswa:
                        </div>
                        <div style="color: var(--text-main); line-height: 1.4;"><?= nl2br(e($sch['announcement'])) ?></div>
                    </div>
                <?php endif; ?>

                <div style="font-size: 0.82rem; color: var(--text-muted); margin-bottom: 16px; display: flex; flex-direction: column; gap: 6px;">
                    <div>
                        <strong style="color: var(--text-dim);">URL Ujian CBT:</strong><br>
                        <a href="<?= e($sch['exam_url']) ?>" target="_blank" style="color: var(--primary); text-decoration: none; word-break: break-all;">
                            <?= e($sch['exam_url']) ?> &#x2197;
                        </a>
                    </div>
                    <?php if (!empty($sch['address'])): ?>
                        <div>
                            <strong style="color: var(--text-dim);">Alamat:</strong> <?= e($sch['address']) ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($sch['contact'])): ?>
                        <div>
                            <strong style="color: var(--text-dim);">Kontak:</strong> <?= e($sch['contact']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Card Bottom Meta & Actions -->
            <div style="border-top: 1px solid var(--border-color); padding: 14px 20px; background: rgba(0,0,0,0.15); display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; gap: 14px; font-size: 0.78rem;">
                    <div>
                        <span style="color: var(--text-dim);">Online:</span>
                        <strong style="color: var(--success);"><?= (int)$sch['active_students_count'] ?> Siswa</strong>
                    </div>
                    <div>
                        <span style="color: var(--text-dim);">Batas Auto-Lock:</span>
                        <strong style="color: #EF4444;"><?= (int)($sch['max_violations'] ?? 3) ?>x</strong>
                    </div>
                </div>

                <div style="display: flex; gap: 8px;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick='openEditSchoolModal(<?= json_encode($sch) ?>)'>
                        Edit Visual &amp; Data
                    </button>
                    <form method="POST" action="schools.php" onsubmit="return confirm('Hapus sekolah <?= e(addslashes($sch['school_name'])) ?>?');" style="display: inline;">
                        <?= csrfField() ?>
                        <input type="hidden" name="form_action" value="delete">
                        <input type="hidden" name="school_id" value="<?= $sch['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm" title="Hapus">
                            &times;
                        </button>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Modal: Add / Edit School Form -->
<div class="modal-backdrop" id="schoolFormModal">
    <div class="modal-card" style="max-width: 620px;">
        <form method="POST" action="schools.php" enctype="multipart/form-data">
            <?= csrfField() ?>
            <input type="hidden" name="form_action" value="save">
            <input type="hidden" name="school_id" id="formSchoolId" value="0">

            <div class="modal-header">
                <h3 id="formModalTitle">Tambah Instansi Sekolah</h3>
                <button type="button" class="modal-close" onclick="closeAllModals()">&times;</button>
            </div>
            <div class="modal-body" style="max-height: 75vh; overflow-y: auto;">
                <div class="form-group">
                    <label class="form-label" for="formSchoolCode">Kode Sekolah (Unique)</label>
                    <input type="text" name="school_code" id="formSchoolCode" class="form-control" placeholder="Contoh: SMAN1, SMKN2, BIMBEL88" required uppercase>
                    <small style="color: var(--text-dim); font-size: 0.75rem;">Kode ini dimasukkan siswa di APK atau dipindai via QR Code.</small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="formSchoolName">Nama Sekolah / Instansi</label>
                    <input type="text" name="school_name" id="formSchoolName" class="form-control" placeholder="Nama lengkap sekolah" required>
                </div>

                <!-- Informasi / Pengumuman untuk Siswa -->
                <div class="form-group" style="background: rgba(56, 189, 248, 0.05); border: 1px solid rgba(56, 189, 248, 0.2); border-radius: 8px; padding: 14px;">
                    <label class="form-label" for="formAnnouncement" style="color: #38BDF8; font-weight: 700; display: flex; align-items: center; gap: 6px;">
                        <span>📢</span> Informasi / Pengumuman Ujian ke Siswa
                    </label>
                    <textarea name="announcement" id="formAnnouncement" class="form-control" rows="3" placeholder="Contoh: Ujian dimulai pukul 08:00 WIB. Dilarang membuka catatan atau membawa alat bantu hitung."></textarea>
                    <small style="color: var(--text-dim); font-size: 0.75rem; display: block; margin-top: 4px;">
                        Pesan ini akan otomatis tampil di layar persiapan siswa dan layar Kiosk Mode selama ujian.
                    </small>
                </div>

                <!-- Logo & Banner Upload -->
                <div style="background: rgba(255, 255, 255, 0.03); border: 1px solid var(--border-color); border-radius: 8px; padding: 14px; margin-bottom: 16px;">
                    <h4 style="font-size: 0.92rem; font-weight: 700; color: #FFFFFF; margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
                        <span>🎨</span> Identitas Visual Sekolah (White-Label)
                    </h4>

                    <!-- Logo Sekolah -->
                    <div class="form-group" style="margin-bottom: 16px;">
                        <label class="form-label">Logo Sekolah (Icon / Lambang)</label>
                        <div style="display: flex; gap: 12px; align-items: center; margin-bottom: 8px;">
                            <img id="previewSchoolLogo" src="assets/img/default-logo.svg" alt="Preview Logo" style="width: 50px; height: 50px; border-radius: 8px; background: rgba(0,0,0,0.3); border: 1px solid var(--border-color); object-fit: contain; padding: 4px;">
                            <div style="flex: 1;">
                                <input type="file" name="logo_file" id="formLogoFile" class="form-control" accept="image/*" onchange="previewImage(this, 'previewSchoolLogo')">
                            </div>
                        </div>
                        <input type="text" name="logo_url" id="formLogoUrl" class="form-control" placeholder="Atau paste URL gambar logo (opsional)" oninput="document.getElementById('previewSchoolLogo').src = this.value || 'assets/img/default-logo.svg'">
                    </div>

                    <!-- Banner Header Sekolah -->
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Banner Header Sekolah</label>
                        <div style="margin-bottom: 8px;">
                            <img id="previewSchoolBanner" src="assets/img/default-banner.svg" alt="Preview Banner" style="width: 100%; height: 85px; border-radius: 8px; background: rgba(0,0,0,0.3); border: 1px solid var(--border-color); object-fit: cover;">
                        </div>
                        <input type="file" name="banner_file" id="formBannerFile" class="form-control" accept="image/*" style="margin-bottom: 6px;" onchange="previewImage(this, 'previewSchoolBanner')">
                        <input type="text" name="banner_url" id="formBannerUrl" class="form-control" placeholder="Atau paste URL gambar banner (opsional)" oninput="document.getElementById('previewSchoolBanner').src = this.value || 'assets/img/default-banner.svg'">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="formExamUrl">URL Server Ujian (CBT Endpoint)</label>
                    <input type="url" name="exam_url" id="formExamUrl" class="form-control" placeholder="http://192.168.1.100/cbt/ atau URL ujian" required>
                    <small style="color: var(--text-dim); font-size: 0.75rem;">Default Demo: <code>http://localhost:5000/exambro/exam_demo.php</code></small>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="form-group">
                        <label class="form-label" for="formBrightness">Kecerahan Layar Default (%)</label>
                        <input type="number" name="remote_brightness" id="formBrightness" class="form-control" min="10" max="100" value="80">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="formMaxViolations">Batas Toleransi Pelanggaran (Auto-Lock)</label>
                        <input type="number" name="max_violations" id="formMaxViolations" class="form-control" min="1" max="20" value="3" required>
                        <small style="color: var(--text-dim); font-size: 0.72rem;">Jika dilanggar, layar ujian auto-lock.</small>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="formContact">Kontak / Telp Pengawas</label>
                    <input type="text" name="contact" id="formContact" class="form-control" placeholder="(021) 555-xxxx">
                </div>

                <div class="form-group">
                    <label class="form-label" for="formAddress">Alamat Sekolah</label>
                    <input type="text" name="address" id="formAddress" class="form-control" placeholder="Alamat lengkap instansi">
                </div>

                <div style="margin-top: 10px;">
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 0.88rem;">
                        <input type="checkbox" name="is_active" id="formIsActive" value="1" checked style="width: 18px; height: 18px;">
                        <span>Aktifkan status sekolah (bisa diakses oleh siswa)</span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAllModals()">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Sekolah</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: QR Code Display -->
<div class="modal-backdrop" id="qrModal">
    <div class="modal-card" style="max-width: 400px; text-align: center;">
        <div class="modal-header">
            <h3 id="qrModalTitle">QR Code Sekolah</h3>
            <button type="button" class="modal-close" onclick="closeAllModals()">&times;</button>
        </div>
        <div class="modal-body" style="padding: 24px;">
            <p style="font-size: 0.82rem; color: var(--text-dim); margin-bottom: 16px;">
                Siswa cukup mengarahkan kamera APK Exambro ke QR Code ini untuk langsung menghubungkan ke server ujian.
            </p>
            <div style="background: white; border-radius: 12px; padding: 12px; display: inline-block; box-shadow: 0 4px 14px rgba(0,0,0,0.3);">
                <img id="qrModalImg" src="" alt="QR Code" style="width: 240px; height: 280px; display: block;">
            </div>
            <div style="margin-top: 16px;">
                <a id="qrDownloadLink" href="" download="" class="btn btn-primary btn-sm">
                    Unduh QR Code SVG
                </a>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeAllModals()">Tutup</button>
        </div>
    </div>
</div>

<script>
function previewImage(input, targetId) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById(targetId).src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function openAddSchoolModal() {
    document.getElementById('formModalTitle').textContent = 'Tambah Instansi Sekolah';
    document.getElementById('formSchoolId').value = '0';
    document.getElementById('formSchoolCode').value = '';
    document.getElementById('formSchoolName').value = '';
    document.getElementById('formAnnouncement').value = '';
    document.getElementById('formLogoUrl').value = '';
    document.getElementById('formBannerUrl').value = '';
    document.getElementById('previewSchoolLogo').src = 'assets/img/default-logo.svg';
    document.getElementById('previewSchoolBanner').src = 'assets/img/default-banner.svg';
    document.getElementById('formExamUrl').value = 'http://localhost:5000/exambro/exam_demo.php';
    document.getElementById('formBrightness').value = '80';
    document.getElementById('formMaxViolations').value = '3';
    document.getElementById('formContact').value = '';
    document.getElementById('formAddress').value = '';
    document.getElementById('formIsActive').checked = true;
    document.getElementById('schoolFormModal').classList.add('show');
}

function openEditSchoolModal(sch) {
    document.getElementById('formModalTitle').textContent = 'Edit Data Sekolah: ' + sch.school_name;
    document.getElementById('formSchoolId').value = sch.id;
    document.getElementById('formSchoolCode').value = sch.school_code;
    document.getElementById('formSchoolName').value = sch.school_name;
    document.getElementById('formAnnouncement').value = sch.announcement || '';
    document.getElementById('formLogoUrl').value = sch.logo_url || '';
    document.getElementById('formBannerUrl').value = sch.banner_url || '';
    document.getElementById('previewSchoolLogo').src = sch.logo_url || 'assets/img/default-logo.svg';
    document.getElementById('previewSchoolBanner').src = sch.banner_url || 'assets/img/default-banner.svg';
    document.getElementById('formExamUrl').value = sch.exam_url;
    document.getElementById('formBrightness').value = sch.remote_brightness;
    document.getElementById('formMaxViolations').value = sch.max_violations || 3;
    document.getElementById('formContact').value = sch.contact || '';
    document.getElementById('formAddress').value = sch.address || '';
    document.getElementById('formIsActive').checked = parseInt(sch.is_active) === 1;
    document.getElementById('schoolFormModal').classList.add('show');
}

function openQrModal(code, name) {
    document.getElementById('qrModalTitle').textContent = 'QR Code: ' + code;
    const qrUrl = 'api/qr.php?code=' + encodeURIComponent(code);
    document.getElementById('qrModalImg').src = qrUrl;
    const dlLink = document.getElementById('qrDownloadLink');
    dlLink.href = qrUrl;
    dlLink.download = 'QR_EXAMBRO_' + code + '.svg';
    document.getElementById('qrModal').classList.add('show');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
