<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Exambro Client - Aplikasi Ujian Terkunci Siswa</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/student.css">
    <link rel="icon" type="image/svg+xml" href="../assets/img/default-logo.svg">
</head>
<body>

<!-- Remote Screen Brightness Filter Overlay (Adjusted live by Web Admin) -->
<div id="brightnessFilterOverlay"></div>

<!-- 1. Setup / Login View (First Screen) -->
<div id="setupView" class="setup-container">
    <div class="setup-card" style="padding-top: 0; overflow: hidden;">
        <!-- Dynamic School Banner -->
        <div id="schoolBannerContainer" style="height: 120px; margin: 0 -32px 18px -32px; background: url('../assets/img/default-banner.svg') center/cover no-repeat, #1E293B; position: relative; border-bottom: 1px solid rgba(255,255,255,0.1);">
            <div style="position: absolute; inset: 0; background: linear-gradient(to top, rgba(15, 23, 42, 0.9), transparent);"></div>
            <div style="position: absolute; bottom: -20px; left: 50%; transform: translateX(-50%);">
                <img id="schoolSetupLogo" src="../assets/img/default-logo.svg" alt="Exambro Logo" class="setup-logo" style="margin: 0; width: 60px; height: 60px; border-radius: 12px; background: #0F172A; border: 2px solid var(--kiosk-border); padding: 4px; box-shadow: 0 4px 14px rgba(0,0,0,0.5);">
            </div>
        </div>

        <div style="margin-top: 28px;">
            <h2 class="setup-title" id="schoolSetupTitle">EXAMBRO CLIENT</h2>
            <p class="setup-subtitle" id="schoolSetupSubtitle">Aplikasi Ujian Aman &bull; Mode Terkunci (Kiosk Mode)</p>
        </div>

        <!-- Dynamic School Announcement Box -->
        <div id="schoolAnnouncementBox" style="display: none; background: rgba(56, 189, 248, 0.08); border-left: 3px solid #38BDF8; padding: 12px 14px; border-radius: 6px; text-align: left; margin-bottom: 18px;">
            <div style="font-size: 0.8rem; font-weight: 700; color: #38BDF8; display: flex; align-items: center; gap: 6px; margin-bottom: 4px;">
                <span>📢</span> Pesan Pengawas ke Siswa:
            </div>
            <div id="schoolAnnouncementText" style="font-size: 0.78rem; color: var(--kiosk-text); line-height: 1.45; white-space: pre-line;"></div>
        </div>

        <form id="studentLoginForm">
            <div class="form-group">
                <label class="form-label" for="inputSchoolCode">Kode Sekolah / Scan QR</label>
                <input type="text" id="inputSchoolCode" class="form-control" placeholder="Contoh: SMAN1 atau SMKN2" value="SMAN1" required autofocus style="text-transform: uppercase; font-weight: 700; letter-spacing: 1px;">
                <small style="color: var(--kiosk-muted); font-size: 0.72rem; display: block; margin-top: 4px;">
                    Kode default demo: <code>SMAN1</code> atau <code>SMKN2</code>
                </small>
            </div>

            <div class="form-group">
                <label class="form-label" for="inputStudentName">Nama Siswa / No. Peserta</label>
                <input type="text" id="inputStudentName" class="form-control" placeholder="Masukkan nama Anda" value="Budi Santoso (Siswa)" required>
            </div>

            <div style="background: rgba(56, 189, 248, 0.08); border-left: 3px solid var(--kiosk-primary); padding: 12px 14px; border-radius: 6px; text-align: left; margin-bottom: 20px;">
                <div style="font-size: 0.8rem; font-weight: 700; color: var(--kiosk-primary); margin-bottom: 4px;">
                    🛡️ Fitur Anti-Kecurangan Aktif:
                </div>
                <div style="font-size: 0.74rem; color: var(--kiosk-muted); line-height: 1.4;">
                    &bull; Penguncian Layar Penuh (Fullscreen Lock &amp; Keyboard Lock)<br>
                    &bull; Deteksi Otomatis Buka Tab Baru / Buka Google<br>
                    &bull; Pelacakan Titik GPS &amp; Spesifikasi Perangkat ke Pengawas
                </div>
            </div>

            <button type="submit" id="btnStartExam" class="btn-start">
                Mulai Sesi Ujian (Kiosk Mode)
            </button>
        </form>

        <div style="margin-top: 24px; font-size: 0.75rem; color: var(--kiosk-muted);">
            <a href="../dashboard.php" target="_blank" style="color: var(--kiosk-primary); text-decoration: none;">
                Buka Dashboard Pengawas (Admin) &#x2197;
            </a>
        </div>
    </div>
</div>

<!-- 2. Active Kiosk Mode View (Exam Screen) -->
<div id="kioskView">
    <!-- Top Proctor Bar -->
    <div class="kiosk-bar">
        <div class="kiosk-brand-info">
            <img id="kioskSchoolLogo" src="../assets/img/default-logo.svg" alt="School Logo" style="width: 26px; height: 26px; border-radius: 6px; object-fit: contain; background: #0F172A; border: 1px solid rgba(255,255,255,0.15); padding: 2px;">
            <span class="kiosk-badge">
                <span style="width: 7px; height: 7px; border-radius: 50%; background: #10B981; display: inline-block;"></span>
                KIOSK TERKUNCI
            </span>
            <span style="font-size: 0.85rem; font-weight: 700; color: #FFFFFF;" id="kioskStudentName">Siswa</span>
            <span style="font-size: 0.75rem; color: var(--kiosk-muted);" id="kioskSchoolName">Sekolah</span>

            <!-- School Announcement Popup Trigger in Kiosk Bar -->
            <button type="button" id="btnOpenSchoolInfoModal" style="display: none; background: rgba(56, 189, 248, 0.15); border: 1px solid rgba(56, 189, 248, 0.4); border-radius: 99px; padding: 3px 10px; color: #38BDF8; font-size: 0.75rem; font-weight: 700; cursor: pointer; align-items: center; gap: 4px;">
                <span>📢</span> Info Ujian
            </button>
        </div>

        <div class="kiosk-controls">
            <!-- Violation Count Badge -->
            <div id="violationCounterBadge" style="display: none; align-items: center; gap: 6px; padding: 3px 10px; background: rgba(239, 68, 68, 0.2); border: 1px solid rgba(239, 68, 68, 0.5); border-radius: 99px; font-size: 0.75rem; font-weight: 800; color: #EF4444;">
                <span>⚠️ Pelanggaran:</span>
                <span id="violationCountText">0x</span>
            </div>

            <!-- Live Remote Brightness Feedback -->
            <div style="font-size: 0.78rem; color: #F59E0B; display: flex; align-items: center; gap: 4px;" title="Kecerahan layar dikontrol remote oleh pengawas">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
                <span>Kecerahan: <strong id="kioskBrightnessVal">80%</strong></span>
            </div>

            <!-- Exit Button (Requires 5-Minute Token) -->
            <button type="button" id="btnOpenExitModal" class="btn-kiosk-exit" title="Keluar dari Aplikasi Exambro">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                Keluar Ujian
            </button>
        </div>
    </div>

    <!-- Exam Frame Container -->
    <div class="exam-frame-wrapper">
        <iframe id="examIframe" src="about:blank" allow="fullscreen"></iframe>
    </div>
</div>

<!-- 3. Fullscreen Violation Blocker Overlay (When Esc/F11 is pressed or Fullscreen lost) -->
<div id="fullscreenViolationOverlay" class="exit-modal-backdrop" style="background: rgba(15, 23, 42, 0.96);">
    <div class="exit-modal-card" style="border-color: #EF4444; max-width: 480px;">
        <div style="width: 64px; height: 64px; border-radius: 50%; background: rgba(239, 68, 68, 0.2); color: #EF4444; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; font-size: 2rem;">
            🚨
        </div>
        <h3 style="font-size: 1.3rem; font-weight: 800; color: #EF4444; margin-bottom: 8px;">
            PERINGATAN KECURANGAN!
        </h3>
        <p style="font-size: 0.95rem; font-weight: 700; color: #FFFFFF; margin-bottom: 8px;">
            Anda Terdeteksi Keluar dari Mode Fullscreen (Menekan Tombol ESC)
        </p>
        <p style="font-size: 0.82rem; color: var(--kiosk-muted); margin-bottom: 24px; line-height: 1.5;">
            Tindakan ini dilarang selama ujian! Pelanggaran telah otomatis dicatat di server Web Admin pengawas ujian beserta waktu dan koordinat Anda.
        </p>
        <div style="display: flex; flex-direction: column; gap: 10px;">
            <button type="button" id="btnRelockFullscreen" class="btn-start" style="background: linear-gradient(135deg, #10B981, #059669); margin: 0; font-size: 0.95rem;">
                🔒 Kunci Layar Kembali (Lanjut Ujian)
            </button>
            <button type="button" id="btnEscapeToExitModal" class="btn-start" style="background: rgba(255,255,255,0.08); color: var(--kiosk-muted); margin: 0; font-size: 0.85rem;">
                Saya Sudah Selesai Ujian (Minta Token Keluar)
            </button>
        </div>
    </div>
</div>

<!-- 4. Tab Switch / Google / Minimize Violation Modal (When user returns from another tab) -->
<div id="tabSwitchViolationModal" class="exit-modal-backdrop" style="background: rgba(15, 23, 42, 0.92);">
    <div class="exit-modal-card" style="border-color: #F59E0B; max-width: 480px;">
        <div style="width: 64px; height: 64px; border-radius: 50%; background: rgba(245, 158, 11, 0.2); color: #F59E0B; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; font-size: 2rem;">
            ⚠️
        </div>
        <h3 style="font-size: 1.25rem; font-weight: 800; color: #F59E0B; margin-bottom: 8px;">
            TERDETEKSI MEMBUKA TAB LAIN!
        </h3>
        <p style="font-size: 0.9rem; font-weight: 600; color: #FFFFFF; margin-bottom: 8px;" id="tabSwitchDurationText">
            Anda terdeteksi meninggalkan halaman ujian dan membuka tab lain (Google/Aplikasi lain).
        </p>
        <div style="padding: 12px; background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 8px; color: #FECACA; font-size: 0.8rem; margin-bottom: 20px; line-height: 1.4;">
            <strong>Catatan Pengawas:</strong> Membuka tab Google, browser lain, atau split-screen terekam di sistem pengawasan. Akun Anda dapat didiskualifikasi jika pelanggaran berulang.
        </div>
        <button type="button" id="btnAckTabSwitch" class="btn-start" style="margin: 0;">
            Saya Mengerti &amp; Lanjutkan Ujian
        </button>
    </div>
</div>

<!-- 5. Permanent Auto-Lockout Modal (When Violation Limit Exceeded) -->
<div id="autoLockoutModal" class="exit-modal-backdrop" style="background: rgba(11, 15, 25, 0.98); z-index: 100005;">
    <div class="exit-modal-card" style="border-color: #EF4444; max-width: 500px; box-shadow: 0 0 40px rgba(239, 68, 68, 0.5);">
        <div style="width: 72px; height: 72px; border-radius: 50%; background: rgba(239, 68, 68, 0.2); color: #EF4444; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; font-size: 2.4rem;">
            🚫
        </div>
        <h3 style="font-size: 1.35rem; font-weight: 900; color: #EF4444; margin-bottom: 8px; letter-spacing: 0.5px;">
            UJIAN TERKUNCI OTOMATIS!
        </h3>
        <p style="font-size: 0.95rem; font-weight: 700; color: #FFFFFF; margin-bottom: 8px;" id="lockoutReasonText">
            Anda telah melanggar batas maksimal kecurangan yang ditentukan pengawas!
        </p>
        <p style="font-size: 0.82rem; color: var(--kiosk-muted); margin-bottom: 24px; line-height: 1.5;">
            Layar pengerjaan soal telah dibekukan. Anda tidak dapat melanjutkan ujian sebelum pengawas membuka kunci (Unlock) sesi Anda dari Dashboard Admin, atau memasukkan Token Keluar.
        </p>

        <div style="display: flex; flex-direction: column; gap: 10px;">
            <button type="button" id="btnProctorUnlockModal" class="btn-start" style="background: linear-gradient(135deg, #0284C7, #2563EB); margin: 0;">
                🔑 Buka Kunci dengan Token Pengawas
            </button>
        </div>
    </div>
</div>

<!-- 6. Exit Password Modal (5-Minute Rotating Token) -->
<div id="exitPasswordModal" class="exit-modal-backdrop">
    <div class="exit-modal-card">
        <div style="width: 54px; height: 54px; border-radius: 14px; background: rgba(239, 68, 68, 0.15); color: #EF4444; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
        </div>

        <h3 style="font-size: 1.2rem; font-weight: 800; color: #FFFFFF; margin-bottom: 6px;">Kunci Keluar Exambro</h3>
        <p style="font-size: 0.82rem; color: var(--kiosk-muted); margin-bottom: 20px;">
            Masukkan Password / Token Keluar yang Anda dapatkan dari pengawas ujian.
        </p>

        <div id="exitModalMsg" style="display: none; padding: 12px; background: rgba(239, 68, 68, 0.2); border: 1px solid rgba(239, 68, 68, 0.4); border-radius: 8px; color: #FECACA; font-size: 0.82rem; margin-bottom: 16px; text-align: left; line-height: 1.4;"></div>

        <div style="margin-bottom: 20px;">
            <input type="text" id="inputExitPassword" class="form-control" placeholder="6 Karakter Token (Contoh: EX8492)" maxlength="10" style="text-align: center; font-size: 1.4rem; font-weight: 900; letter-spacing: 4px; text-transform: uppercase;">
            <div style="font-size: 0.72rem; color: var(--kiosk-muted); margin-top: 6px;">
                Token berganti otomatis setiap 5 menit di server admin.
            </div>
        </div>

        <div style="display: flex; gap: 10px;">
            <button type="button" id="btnCloseExitModal" class="btn-start" style="background: rgba(255,255,255,0.08); color: var(--kiosk-muted); margin: 0; flex: 1;">
                Batal
            </button>
            <button type="button" id="btnConfirmExit" class="btn-start" style="background: #EF4444; margin: 0; flex: 2;">
                Buka Kunci &amp; Keluar
            </button>
        </div>
    </div>
</div>

<!-- 7. School Info & Announcement Modal during Exam -->
<div id="schoolInfoModal" class="exit-modal-backdrop">
    <div class="exit-modal-card" style="border-color: #38BDF8; max-width: 480px; text-align: left;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--kiosk-border); padding-bottom: 12px; margin-bottom: 14px;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <img id="schoolModalLogo" src="../assets/img/default-logo.svg" style="width: 32px; height: 32px; border-radius: 6px; object-fit: contain;">
                <div>
                    <h3 style="font-size: 1.05rem; font-weight: 800; color: #FFFFFF; margin: 0;" id="schoolModalTitle">Informasi Sekolah</h3>
                    <span style="font-size: 0.72rem; color: #38BDF8;" id="schoolModalSubtitle">Pesan Resmi Pengawas Ujian</span>
                </div>
            </div>
            <button type="button" class="btn-start" style="background: transparent; color: var(--kiosk-muted); margin: 0; font-size: 1.4rem; padding: 0 6px;" onclick="document.getElementById('schoolInfoModal').classList.remove('show')">&times;</button>
        </div>
        <div id="schoolModalAnnouncementContent" style="font-size: 0.85rem; color: var(--kiosk-text); line-height: 1.6; white-space: pre-line; background: rgba(255,255,255,0.03); border: 1px solid var(--kiosk-border); border-radius: 8px; padding: 14px; margin-bottom: 18px;">
        </div>
        <button type="button" class="btn-start" style="margin: 0;" onclick="document.getElementById('schoolInfoModal').classList.remove('show')">
            Tutup Informasi
        </button>
    </div>
</div>

<script src="../assets/js/student.js"></script>
</body>
</html>
