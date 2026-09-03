/**
 * EXAMBRO STUDENT KIOSK & ANDROID SIMULATOR - CLIENT ENGINE
 * Handles:
 * - Immediate synchronous Fullscreen & Keyboard Lock
 * - Anti-spam keyrepeat debounce & rate-limiting
 * - Auto-Lock Screen when violation threshold is exceeded
 * - GPS Geolocation & Device Specs sync
 * - Remote Screen Brightness & Proctor Unlock via Heartbeat
 * - 5-Minute Dynamic Exit Password Validation
 */

let activeSession = null;
let heartbeatInterval = null;
let currentBrightness = 80;
let violationCount = 0;
let maxViolations = 3;
let isStudentLocked = false;
let tabLeaveTimestamp = null;
let isIntentionallyExiting = false;
let lastViolationTime = 0;
let originalExamUrl = '';

document.addEventListener('DOMContentLoaded', () => {
    initSchoolBrandingWatcher();
    initSetupForm();
    initExitModal();
    initAntiCheatingGuards();
    initViolationModalButtons();
});

/* ==========================================================
   0. Dynamic School Visual & Announcement Branding Loader
   ========================================================== */
let brandingDebounceTimer = null;

function initSchoolBrandingWatcher() {
    const codeInput = document.getElementById('inputSchoolCode');
    if (!codeInput) return;

    // Load initial branding for default school
    if (codeInput.value.trim()) {
        loadSchoolBranding(codeInput.value.trim());
    }

    // Live update when student types/scans a different school code
    codeInput.addEventListener('input', () => {
        clearTimeout(brandingDebounceTimer);
        const val = codeInput.value.trim().toUpperCase();
        if (val.length >= 2) {
            brandingDebounceTimer = setTimeout(() => {
                loadSchoolBranding(val);
            }, 300);
        }
    });
}

async function loadSchoolBranding(code) {
    try {
        const res = await fetch(`../api/school.php?code=${encodeURIComponent(code)}`);
        const json = await res.json();
        if (json.status === 'success' && json.data) {
            const sch = json.data;

            // 1. Update Logo
            const logoEl = document.getElementById('schoolSetupLogo');
            if (logoEl && sch.logo_url) {
                logoEl.src = sch.logo_url;
            }

            // 2. Update Banner
            const bannerEl = document.getElementById('schoolBannerContainer');
            if (bannerEl && sch.banner_url) {
                bannerEl.style.backgroundImage = `url('${sch.banner_url}')`;
            }

            // 3. Update Title & Subtitle
            const titleEl = document.getElementById('schoolSetupTitle');
            if (titleEl) {
                titleEl.textContent = sch.school_name;
            }
            const subEl = document.getElementById('schoolSetupSubtitle');
            if (subEl) {
                subEl.textContent = sch.address || 'Ujian Terkunci & Pengawasan Real-Time';
            }

            // 4. Update Announcement
            const annBox = document.getElementById('schoolAnnouncementBox');
            const annText = document.getElementById('schoolAnnouncementText');
            if (annBox && annText) {
                if (sch.announcement && sch.announcement.trim().length > 0) {
                    annText.textContent = sch.announcement;
                    annBox.style.display = 'block';
                } else {
                    annBox.style.display = 'none';
                }
            }
        }
    } catch (e) {
        // Silently keep default
    }
}

/* ==========================================================
   1. Setup & Login with GPS Geolocation
   ========================================================== */
function initSetupForm() {
    const form = document.getElementById('studentLoginForm');
    const startBtn = document.getElementById('btnStartExam');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        // 1. CRITICAL: Request fullscreen IMMEDIATELY in the synchronous user click event
        // Browsers like Chrome reject requestFullscreen() if delayed after await fetch()
        requestFullScreenLock();

        startBtn.disabled = true;
        startBtn.textContent = 'Menghubungkan ke Server...';

        const schoolCode = document.getElementById('inputSchoolCode').value.trim().toUpperCase();
        const studentName = document.getElementById('inputStudentName').value.trim() || 'Siswa Ujian';

        try {
            // 2. Fetch School config
            const schoolRes = await fetch(`../api/school.php?code=${encodeURIComponent(schoolCode)}`);
            const schoolData = await schoolRes.json();
            if (schoolData.status !== 'success') {
                alert(schoolData.message || 'Kode Sekolah tidak ditemukan.');
                startBtn.disabled = false;
                startBtn.textContent = 'Mulai Sesi Ujian (Kiosk Mode)';
                return;
            }

            const school = schoolData.data;
            maxViolations = school.max_violations || 3;
            originalExamUrl = school.exam_url;

            // 3. Request GPS Location
            startBtn.textContent = 'Mendeteksi Lokasi GPS...';
            let latitude = null;
            let longitude = null;
            let accuracy = null;

            if (navigator.geolocation) {
                try {
                    const pos = await new Promise((resolve, reject) => {
                        navigator.geolocation.getCurrentPosition(resolve, reject, {
                            timeout: 5000,
                            enableHighAccuracy: true
                        });
                    });
                    latitude = pos.coords.latitude;
                    longitude = pos.coords.longitude;
                    accuracy = pos.coords.accuracy;
                } catch (locErr) {
                    console.warn('GPS notice:', locErr.message);
                }
            }

            // 4. Detect Device Specs
            const brand = detectDeviceBrand();
            const model = detectDeviceModel();
            const os = detectDeviceOs();
            const screenRes = `${window.screen.width}x${window.screen.height}`;

            let batteryLevel = 95;
            if (navigator.getBattery) {
                try {
                    const b = await navigator.getBattery();
                    batteryLevel = Math.round(b.level * 100);
                } catch (err) {}
            }

            // 5. Register Session via API
            startBtn.textContent = 'Mengunci Perangkat...';
            const loginPayload = {
                school_code: schoolCode,
                student_name: studentName,
                device_brand: brand,
                device_model: model,
                device_os: os,
                screen_resolution: screenRes,
                battery_level: batteryLevel,
                latitude: latitude,
                longitude: longitude,
                location_accuracy: accuracy
            };

            const sessionRes = await fetch('../api/student/login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(loginPayload)
            });

            const sessionJson = await sessionRes.json();
            if (sessionJson.status !== 'success') {
                alert(sessionJson.message || 'Gagal memulai sesi.');
                startBtn.disabled = false;
                startBtn.textContent = 'Mulai Sesi Ujian (Kiosk Mode)';
                return;
            }

            activeSession = sessionJson.data;
            isIntentionallyExiting = false;
            isStudentLocked = false;
            violationCount = 0;
            if (activeSession.max_violations) {
                maxViolations = activeSession.max_violations;
            }

            // 6. Enter Kiosk Mode!
            enterKioskMode(school, activeSession);

        } catch (err) {
            alert('Gagal menghubungi server: ' + err.message);
            startBtn.disabled = false;
            startBtn.textContent = 'Mulai Sesi Ujian (Kiosk Mode)';
        }
    });
}

/* ==========================================================
   2. Kiosk Mode & Remote Brightness Execution
   ========================================================== */
function enterKioskMode(school, session) {
    // Switch Views
    document.getElementById('setupView').style.display = 'none';
    const kioskView = document.getElementById('kioskView');
    kioskView.style.display = 'flex';

    // Update Topbar Info
    document.getElementById('kioskStudentName').textContent = session.student_name || 'Siswa';
    document.getElementById('kioskSchoolName').textContent = school.school_name;

    // School Logo in Topbar
    const kioskLogo = document.getElementById('kioskSchoolLogo');
    if (kioskLogo && school.logo_url) {
        kioskLogo.src = school.logo_url;
    }

    // School Modal Info
    const modalLogo = document.getElementById('schoolModalLogo');
    if (modalLogo && school.logo_url) {
        modalLogo.src = school.logo_url;
    }
    const modalTitle = document.getElementById('schoolModalTitle');
    if (modalTitle) {
        modalTitle.textContent = school.school_name;
    }

    // School Announcement Popup in Kiosk Topbar
    const infoBtn = document.getElementById('btnOpenSchoolInfoModal');
    const annContent = document.getElementById('schoolModalAnnouncementContent');
    if (infoBtn) {
        if (school.announcement && school.announcement.trim().length > 0) {
            infoBtn.style.display = 'inline-flex';
            infoBtn.onclick = () => {
                if (annContent) annContent.textContent = school.announcement;
                const modal = document.getElementById('schoolInfoModal');
                if (modal) modal.classList.add('show');
            };
        } else {
            infoBtn.style.display = 'none';
        }
    }

    // Apply initial brightness
    setBrightnessFilter(school.remote_brightness || 80);

    // Load Exam URL into Iframe (with smart embed formatter for YouTube and web apps)
    const iframe = document.getElementById('examIframe');
    iframe.src = formatExamUrlForSimulator(school.exam_url);

    // Attach security bridge to iframe once loaded
    iframe.onload = () => {
        try {
            attachIframeSecurityBridge(iframe);
        } catch (e) {
            console.warn('Iframe security bridge notice:', e);
        }
    };

    // Make sure fullscreen is firmly requested
    requestFullScreenLock();

    // Start periodic heartbeat (every 3.5s)
    startHeartbeat(session.session_token);
}

/**
 * Smart URL Formatter:
 * Converts YouTube watch URLs (e.g. youtube.com/watch?v=XYZ) into embed URLs (youtube.com/embed/XYZ)
 * so they can render smoothly in the desktop simulator iframe without being blocked by Chrome X-Frame-Options.
 */
function formatExamUrlForSimulator(url) {
    if (!url) return 'about:blank';
    try {
        const ytMatch = url.match(/(?:youtube\.com\/(?:watch\?v=|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]+)/i);
        if (ytMatch && ytMatch[1]) {
            return `https://www.youtube.com/embed/${ytMatch[1]}?autoplay=1&rel=0`;
        }
    } catch (e) {}
    return url;
}

function requestFullScreenLock() {
    const docEl = document.documentElement;
    try {
        if (!document.fullscreenElement && !document.webkitFullscreenElement) {
            if (docEl.requestFullscreen) {
                docEl.requestFullscreen().catch(() => {});
            } else if (docEl.webkitRequestFullscreen) {
                docEl.webkitRequestFullscreen();
            }
        }
    } catch (e) {
        console.warn('Fullscreen request failed:', e);
    }

    // Chrome Keyboard Lock API (Locks the Escape key when in fullscreen)
    if (navigator.keyboard && typeof navigator.keyboard.lock === 'function') {
        try {
            navigator.keyboard.lock(['Escape', 'Tab', 'AltLeft', 'AltRight']).catch(() => {});
        } catch (err) {}
    }
}

function setBrightnessFilter(brightnessVal) {
    currentBrightness = Math.max(10, Math.min(100, parseInt(brightnessVal) || 80));
    const overlay = document.getElementById('brightnessFilterOverlay');
    if (overlay) {
        const darkness = ((100 - currentBrightness) / 100) * 0.85;
        overlay.style.opacity = darkness.toFixed(2);
    }
    const indicator = document.getElementById('kioskBrightnessVal');
    if (indicator) {
        indicator.textContent = `${currentBrightness}%`;
    }
}

function startHeartbeat(sessionToken) {
    if (heartbeatInterval) clearInterval(heartbeatInterval);

    heartbeatInterval = setInterval(async () => {
        if (!activeSession) return;

        let battery = 90;
        if (navigator.getBattery) {
            try {
                const b = await navigator.getBattery();
                battery = Math.round(b.level * 100);
            } catch (err) {}
        }

        try {
            const res = await fetch('../api/student/heartbeat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    session_token: sessionToken,
                    battery_level: battery
                })
            });

            const json = await res.json();
            if (json.status === 'success' && json.commands) {
                // Sync remote brightness
                if (json.commands.remote_brightness && json.commands.remote_brightness !== currentBrightness) {
                    setBrightnessFilter(json.commands.remote_brightness);
                }

                // Handle admin Force Exit command
                if (json.commands.force_exit) {
                    clearInterval(heartbeatInterval);
                    alert("PERINGATAN PENGAWAS:\nPengawas ujian telah mengeluarkan Anda dari sesi ujian secara paksa.");
                    exitKioskMode();
                    return;
                }

                // Handle Remote Lockout vs Proctor Unlock
                if (json.commands.is_locked && !isStudentLocked) {
                    lockoutStudent(`Sesi ujian dikunci secara remote oleh Pengawas karena terdeteksi ${json.commands.violation_count || violationCount} pelanggaran.`);
                } else if (!json.commands.is_locked && isStudentLocked) {
                    // Proctor unlocked student from Admin Web Dashboard!
                    unlockStudent();
                }

                if (json.commands.max_violations) {
                    maxViolations = json.commands.max_violations;
                }
            }
        } catch (err) {
            // Transient offline
        }
    }, 3500);
}

/* ==========================================================
   3. Strict Anti-Cheating, Debounced Keys & Auto-Lock Screen
   ========================================================== */
function initAntiCheatingGuards() {
    // 1. Fullscreen Change Watcher
    const handleFullscreenChange = () => {
        const isFullscreen = !!(document.fullscreenElement || document.webkitFullscreenElement);
        if (!isFullscreen && activeSession && !isIntentionallyExiting && !isStudentLocked) {
            // Fullscreen lost! Debounce check
            if (Date.now() - lastViolationTime > 1500) {
                lastViolationTime = Date.now();
                recordViolationEvent(
                    'fullscreen_exit',
                    'Siswa keluar dari mode fullscreen (menekan tombol ESC atau F11)'
                );
                playAlarmSound();
            }

            const overlay = document.getElementById('fullscreenViolationOverlay');
            if (overlay) overlay.classList.add('show');
        } else if (isFullscreen) {
            const overlay = document.getElementById('fullscreenViolationOverlay');
            if (overlay) overlay.classList.remove('show');
        }
    };

    document.addEventListener('fullscreenchange', handleFullscreenChange);
    document.addEventListener('webkitfullscreenchange', handleFullscreenChange);

    // 2. Tab Switch & Minimize Detection (Leaving tab to Google or new tab)
    document.addEventListener('visibilitychange', () => {
        if (!activeSession || isIntentionallyExiting || isStudentLocked) return;

        if (document.hidden) {
            tabLeaveTimestamp = Date.now();
        } else {
            if (tabLeaveTimestamp) {
                const secondsAway = Math.max(1, Math.round((Date.now() - tabLeaveTimestamp) / 1000));
                tabLeaveTimestamp = null;

                if (Date.now() - lastViolationTime > 1500) {
                    lastViolationTime = Date.now();
                    recordViolationEvent(
                        'tab_switch',
                        `Siswa meninggalkan tab ujian dan membuka tab lain / Google selama ${secondsAway} detik`
                    );
                    playAlarmSound();
                }

                const modal = document.getElementById('tabSwitchViolationModal');
                const durText = document.getElementById('tabSwitchDurationText');
                if (durText) {
                    durText.textContent = `Anda terdeteksi meninggalkan halaman ujian dan membuka tab lain (Google / aplikasi lain) selama ${secondsAway} detik!`;
                }
                if (modal && !isStudentLocked) {
                    modal.classList.add('show');
                }
            }
        }
    });

    // 3. Window Blur Detection
    window.addEventListener('blur', () => {
        if (activeSession && !isIntentionallyExiting && !isStudentLocked) {
            if (Date.now() - lastViolationTime > 2000) {
                lastViolationTime = Date.now();
                reportViolation('app_blur', 'Fokus jendela ujian terlepas (kemungkinan membuka split-screen atau aplikasi lain)');
            }
        }
    });

    // 4. Shortcut Key Interception with Anti-Spam Repeat Debounce
    document.addEventListener('keydown', handleKeyInterception);

    // 5. Prevent Context Menu (Right Click)
    document.addEventListener('contextmenu', (e) => {
        if (activeSession) {
            e.preventDefault();
            return false;
        }
    });

    // 6. Message Event Bridge from Exam Iframe
    window.addEventListener('message', (event) => {
        if (!activeSession || isIntentionallyExiting || isStudentLocked) return;
        if (event.data && event.data.type === 'VIOLATION_KEY') {
            const key = event.data.key;
            if (Date.now() - lastViolationTime > 1500) {
                lastViolationTime = Date.now();
                if (key === 'Escape') {
                    recordViolationEvent('escape_pressed_iframe', 'Siswa menekan tombol ESC di dalam soal ujian');
                    playAlarmSound();
                    const overlay = document.getElementById('fullscreenViolationOverlay');
                    if (overlay) overlay.classList.add('show');
                } else {
                    recordViolationEvent('forbidden_shortcut_iframe', `Percobaan tombol pintas di dalam ujian: ${key}`);
                    playAlarmSound();
                }
            }
        }
    });
}

function handleKeyInterception(e) {
    if (!activeSession || isIntentionallyExiting) return;

    // CRITICAL FIX: If key is being held down, ignore continuous repeat events!
    if (e.repeat) {
        e.preventDefault();
        return false;
    }

    if (
        e.key === 'F11' || e.key === 'F12' || e.key === 'Escape' ||
        (e.altKey && e.key === 'Tab') ||
        (e.ctrlKey && (e.key === 'w' || e.key === 'r' || e.key === 't' || e.key === 'n' || e.key === 'q'))
    ) {
        e.preventDefault();

        // Rate limit violations: minimum 1.5 seconds cooldown
        if (Date.now() - lastViolationTime > 1500) {
            lastViolationTime = Date.now();
            recordViolationEvent('forbidden_shortcut', `Percobaan menekan tombol: ${e.key}`);
            playAlarmSound();

            if (e.key === 'F11' || e.key === 'Escape') {
                const overlay = document.getElementById('fullscreenViolationOverlay');
                if (overlay && !isStudentLocked) overlay.classList.add('show');
            }
        }
        return false;
    }
}

function attachIframeSecurityBridge(iframe) {
    try {
        const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
        if (!iframeDoc) return;

        iframeDoc.addEventListener('keydown', (e) => {
            if (!activeSession || isIntentionallyExiting || isStudentLocked) return;

            // Ignore key-repeats inside iframe as well
            if (e.repeat) {
                e.preventDefault();
                return false;
            }

            if (e.key === 'Escape' || e.key === 'F11' || e.key === 'F12' ||
                (e.ctrlKey && (e.key === 'w' || e.key === 'r' || e.key === 't' || e.key === 'n'))) {
                e.preventDefault();
                if (Date.now() - lastViolationTime > 1500) {
                    lastViolationTime = Date.now();
                    recordViolationEvent('iframe_forbidden_key', `Siswa menekan tombol ${e.key} di dalam soal`);
                    playAlarmSound();
                    if (e.key === 'Escape' || e.key === 'F11') {
                        document.getElementById('fullscreenViolationOverlay').classList.add('show');
                    }
                }
                return false;
            }
        });

        iframeDoc.addEventListener('contextmenu', (e) => {
            if (activeSession) {
                e.preventDefault();
                return false;
            }
        });
    } catch (err) {}
}

function recordViolationEvent(type, desc) {
    if (isStudentLocked) return;

    violationCount++;

    // Update floating badge in kiosk bar
    const badge = document.getElementById('violationCounterBadge');
    const text = document.getElementById('violationCountText');
    if (badge && text) {
        badge.style.display = 'flex';
        text.textContent = `${violationCount}x`;
    }

    // CHECK THRESHOLD: Auto-Lock screen if limit exceeded!
    if (violationCount >= maxViolations) {
        lockoutStudent(`Anda telah melakukan ${violationCount} pelanggaran (Batas maksimal pengawas: ${maxViolations}x). Layar ujian dibekukan secara otomatis!`);
    }

    // Report to backend
    reportViolation(type, `[Pelanggaran ke-${violationCount}/${maxViolations}] ${desc}`);
}

async function reportViolation(type, desc) {
    if (!activeSession) return;
    try {
        const res = await fetch('../api/student/violation.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                session_token: activeSession.session_token,
                violation_type: type,
                description: desc
            })
        });
        const json = await res.json();
        if (json && json.is_locked && !isStudentLocked) {
            lockoutStudent(`Sesi ujian Anda telah dibekukan otomatis oleh sistem (Pelanggaran: ${json.violation_count}/${json.max_violations}).`);
        }
    } catch (e) {}
}

/**
 * AUTO LOCKOUT: Freezes screen when maximum violation limit is breached
 */
function lockoutStudent(reason) {
    isStudentLocked = true;
    playAlarmSound();

    // Close other non-critical dialogs
    document.getElementById('fullscreenViolationOverlay').classList.remove('show');
    document.getElementById('tabSwitchViolationModal').classList.remove('show');

    // Hide exam iframe content so student cannot read questions while locked
    const iframe = document.getElementById('examIframe');
    if (iframe) {
        iframe.style.visibility = 'hidden';
    }

    // Show Auto Lockout Modal
    const modal = document.getElementById('autoLockoutModal');
    const reasonText = document.getElementById('lockoutReasonText');
    if (reasonText) {
        reasonText.textContent = reason;
    }
    if (modal) {
        modal.classList.add('show');
    }
}

/**
 * UNLOCK: Restores exam when proctor unlocks from Web Admin or inputs token
 */
function unlockStudent() {
    isStudentLocked = false;
    violationCount = 0;

    const modal = document.getElementById('autoLockoutModal');
    if (modal) modal.classList.remove('show');

    // Restore exam iframe
    const iframe = document.getElementById('examIframe');
    if (iframe) {
        iframe.style.visibility = 'visible';
    }

    // Reset badge
    const badge = document.getElementById('violationCounterBadge');
    if (badge) badge.style.display = 'none';

    // Re-lock Fullscreen
    requestFullScreenLock();

    alert("PEMBERITAHUAN:\nSesi ujian Anda telah dibuka kembali oleh Pengawas. Silakan lanjutkan ujian dengan tertib!");
}

function initViolationModalButtons() {
    // Re-lock Fullscreen Button
    const relockBtn = document.getElementById('btnRelockFullscreen');
    if (relockBtn) {
        relockBtn.addEventListener('click', () => {
            requestFullScreenLock();
            document.getElementById('fullscreenViolationOverlay').classList.remove('show');
        });
    }

    // Escape overlay button to open exit modal
    const toExitBtn = document.getElementById('btnEscapeToExitModal');
    if (toExitBtn) {
        toExitBtn.addEventListener('click', () => {
            document.getElementById('fullscreenViolationOverlay').classList.remove('show');
            document.getElementById('exitPasswordModal').classList.add('show');
            document.getElementById('inputExitPassword').focus();
        });
    }

    // Acknowledge Tab Switch Button
    const ackBtn = document.getElementById('btnAckTabSwitch');
    if (ackBtn) {
        ackBtn.addEventListener('click', () => {
            document.getElementById('tabSwitchViolationModal').classList.remove('show');
            if (!document.fullscreenElement) {
                requestFullScreenLock();
            }
        });
    }

    // Proctor Unlock Button from Lockout Screen
    const proctorUnlockBtn = document.getElementById('btnProctorUnlockModal');
    if (proctorUnlockBtn) {
        proctorUnlockBtn.addEventListener('click', () => {
            document.getElementById('exitPasswordModal').classList.add('show');
            document.getElementById('inputExitPassword').focus();
        });
    }
}

/**
 * Web Audio API synthesized security siren alarm
 */
function playAlarmSound() {
    try {
        const AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtx) return;
        const ctx = new AudioCtx();

        const osc = ctx.createOscillator();
        const gain = ctx.createGain();

        osc.type = 'sawtooth';
        osc.frequency.setValueAtTime(880, ctx.currentTime);
        osc.frequency.exponentialRampToValueAtTime(440, ctx.currentTime + 0.35);

        gain.gain.setValueAtTime(0.3, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.35);

        osc.connect(gain);
        gain.connect(ctx.destination);

        osc.start();
        osc.stop(ctx.currentTime + 0.35);
    } catch (e) {}
}

/* ==========================================================
   4. Exit Password & Proctor Unlock Verification
   ========================================================== */
function initExitModal() {
    const exitBtn = document.getElementById('btnOpenExitModal');
    const modal = document.getElementById('exitPasswordModal');
    const closeBtn = document.getElementById('btnCloseExitModal');
    const confirmBtn = document.getElementById('btnConfirmExit');
    const exitInput = document.getElementById('inputExitPassword');
    const msgBox = document.getElementById('exitModalMsg');

    if (exitBtn) {
        exitBtn.addEventListener('click', () => {
            exitInput.value = '';
            msgBox.style.display = 'none';
            modal.classList.add('show');
            exitInput.focus();
        });
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', () => {
            modal.classList.remove('show');
        });
    }

    if (confirmBtn) {
        confirmBtn.addEventListener('click', async () => {
            const pwd = exitInput.value.trim().toUpperCase();
            if (!pwd) {
                showModalError('Harap masukkan token keluar dari pengawas.');
                return;
            }

            confirmBtn.disabled = true;
            confirmBtn.textContent = 'Memverifikasi...';

            try {
                const res = await fetch('../api/student/verify_exit.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        session_token: activeSession ? activeSession.session_token : '',
                        exit_password: pwd
                    })
                });

                const json = await res.json();

                if (json.valid) {
                    modal.classList.remove('show');

                    // If student was locked, ask if they want to unlock exam or exit completely
                    if (isStudentLocked) {
                        if (confirm("Token Pengawas Valid!\n\nKlik OK untuk membuka kunci ujian dan melanjutkan soal, atau Cancel untuk keluar total dari aplikasi.")) {
                            unlockStudent();
                            return;
                        }
                    }

                    isIntentionallyExiting = true;
                    alert("Token Pengawas Valid. Ujian diakhiri dan aplikasi dibuka.");
                    exitKioskMode();
                } else {
                    playAlarmSound();
                    showModalError(json.message);
                }
            } catch (err) {
                showModalError('Gagal memverifikasi password ke server: ' + err.message);
            } finally {
                confirmBtn.disabled = false;
                confirmBtn.textContent = 'Buka Kunci & Keluar';
            }
        });
    }

    function showModalError(msg) {
        msgBox.textContent = msg;
        msgBox.style.display = 'block';
    }
}

function exitKioskMode() {
    if (heartbeatInterval) clearInterval(heartbeatInterval);
    activeSession = null;
    isIntentionallyExiting = true;
    isStudentLocked = false;

    if (navigator.keyboard && typeof navigator.keyboard.unlock === 'function') {
        try { navigator.keyboard.unlock(); } catch (e) {}
    }

    if (document.exitFullscreen) {
        document.exitFullscreen().catch(() => {});
    }

    // Reset view
    document.getElementById('kioskView').style.display = 'none';
    document.getElementById('setupView').style.display = 'flex';
    document.getElementById('btnStartExam').disabled = false;
    document.getElementById('btnStartExam').textContent = 'Mulai Sesi Ujian (Kiosk Mode)';
    document.getElementById('examIframe').src = 'about:blank';
    document.getElementById('examIframe').style.visibility = 'visible';
    document.getElementById('fullscreenViolationOverlay').classList.remove('show');
    document.getElementById('tabSwitchViolationModal').classList.remove('show');
    document.getElementById('autoLockoutModal').classList.remove('show');
    document.getElementById('violationCounterBadge').style.display = 'none';
}

/* ==========================================================
   5. Device Identification Helpers
   ========================================================== */
function detectDeviceBrand() {
    const ua = navigator.userAgent;
    if (/Samsung/i.test(ua)) return 'Samsung';
    if (/Xiaomi|Redmi|POCO/i.test(ua)) return 'Xiaomi';
    if (/Oppo|Realme/i.test(ua)) return 'Oppo';
    if (/Vivo/i.test(ua)) return 'Vivo';
    if (/Huawei|Honor/i.test(ua)) return 'Huawei';
    if (/Pixel/i.test(ua)) return 'Google';
    if (/iPhone|iPad/i.test(ua)) return 'Apple';
    return 'Android Simulator';
}

function detectDeviceModel() {
    const ua = navigator.userAgent;
    const match = ua.match(/\(([^)]+)\)/);
    if (match && match[1]) {
        const parts = match[1].split(';');
        if (parts.length > 2) return parts[2].trim();
        return parts[0].trim();
    }
    return 'Exambro Client Tab';
}

function detectDeviceOs() {
    const ua = navigator.userAgent;
    if (/Android (\d+(\.\d+)?)/i.test(ua)) {
        return 'Android ' + RegExp.$1;
    }
    if (/Windows NT 10.0/i.test(ua)) return 'Windows 11/10 (Simulated)';
    if (/Mac OS/i.test(ua)) return 'macOS (Simulated)';
    return 'Android 14 (Simulated)';
}
