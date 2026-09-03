/**
 * EXAMBRO PROCTOR ADMIN - FRONTEND INTERACTIVITY & REAL-TIME SYNC
 * Pure Vanilla JavaScript (Native PHP 8.3 Architecture)
 */

document.addEventListener('DOMContentLoaded', () => {
    initTokenCountdown();
    initBrightnessControl();
    initStudentLivePoller();
    initModals();
    initCopyButtons();
});

/* ==========================================================
   1. 5-Minute Dynamic Exit Token Countdown Engine
   ========================================================== */
function initTokenCountdown() {
    const tokenDisplay = document.getElementById('currentTokenVal');
    const quickTokenDisplay = document.getElementById('quickTokenVal');
    const countdownSec = document.getElementById('countdownSec');
    const circleProgress = document.getElementById('countdownProgress');

    const TOTAL_INTERVAL = 300; // 5 minutes
    const CIRCLE_CIRCUMFERENCE = 238.7; // 2 * PI * r (r=38)

    let currentRemaining = 300;
    let timerInterval = null;

    async function fetchTokenInfo() {
        try {
            const res = await fetch('api/token.php', { cache: 'no-store' });
            if (!res.ok) return;
            const json = await res.json();
            if (json.status === 'success') {
                const data = json.data;
                if (tokenDisplay) tokenDisplay.textContent = data.token;
                if (quickTokenDisplay) quickTokenDisplay.textContent = data.token;
                currentRemaining = data.remaining_seconds;
                updateVisualProgress();
            }
        } catch (e) {
            console.warn('Failed to sync exit token:', e);
        }
    }

    function updateVisualProgress() {
        if (countdownSec) {
            const mins = Math.floor(currentRemaining / 60);
            const secs = currentRemaining % 60;
            countdownSec.textContent = `${mins}:${secs < 10 ? '0' : ''}${secs}`;
        }

        if (circleProgress) {
            const fraction = Math.max(0, Math.min(1, currentRemaining / TOTAL_INTERVAL));
            const offset = CIRCLE_CIRCUMFERENCE * (1 - fraction);
            circleProgress.style.strokeDashoffset = offset.toFixed(1);

            // Change color warning when remaining < 60s
            if (currentRemaining < 45) {
                circleProgress.style.stroke = '#EF4444'; // Red danger
            } else if (currentRemaining < 90) {
                circleProgress.style.stroke = '#F59E0B'; // Amber warning
            } else {
                circleProgress.style.stroke = '#38BDF8'; // Blue normal
            }
        }
    }

    // Initial fetch
    fetchTokenInfo();

    // Local 1-second tick
    timerInterval = setInterval(() => {
        if (currentRemaining > 0) {
            currentRemaining--;
            updateVisualProgress();
        } else {
            // Expired -> fetch fresh token from server immediately
            fetchTokenInfo();
        }
    }, 1000);

    // Sync with server every 30 seconds to prevent local clock drift
    setInterval(fetchTokenInfo, 30000);
}

/* ==========================================================
   2. Remote Screen Brightness Controller (Real-time)
   ========================================================== */
function initBrightnessControl() {
    const slider = document.getElementById('remoteBrightnessSlider');
    const percentLabel = document.getElementById('brightnessPercentLabel');
    const schoolSelect = document.getElementById('brightnessSchoolSelect');
    if (!slider) return;

    let debounceTimer = null;

    function applyBrightness(val) {
        if (percentLabel) percentLabel.textContent = `${val}%`;

        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(async () => {
            const schoolId = schoolSelect ? schoolSelect.value : 'all';
            const formData = new FormData();
            formData.append('action', 'update_brightness');
            formData.append('brightness', val);
            formData.append('school_id', schoolId);

            try {
                const res = await fetch('dashboard.php', {
                    method: 'POST',
                    body: formData
                });
                const resData = await res.json();
                if (resData.status === 'success') {
                    showToast('Kecerahan berhasil disinkronkan ke perangkat siswa!', 'success');
                }
            } catch (err) {
                console.error('Failed to sync brightness:', err);
            }
        }, 300);
    }

    slider.addEventListener('input', (e) => {
        applyBrightness(e.target.value);
    });

    // Preset buttons (25%, 50%, 75%, 100%)
    document.querySelectorAll('.btn-preset').forEach(btn => {
        btn.addEventListener('click', () => {
            const presetVal = btn.getAttribute('data-val');
            slider.value = presetVal;
            applyBrightness(presetVal);
        });
    });
}

/* ==========================================================
   3. Live Student Monitoring Poller (3-second refresh)
   ========================================================== */
function initStudentLivePoller() {
    const liveTableBody = document.getElementById('liveStudentsTableBody');
    if (!liveTableBody) return;

    async function pollStudents() {
        try {
            const res = await fetch('students.php?ajax=live_list', { cache: 'no-store' });
            if (!res.ok) return;
            const json = await res.json();
            if (json.status === 'success') {
                renderStudentRows(json.data, liveTableBody);
                updateLiveStats(json.stats);
            }
        } catch (err) {
            // Ignore temporary network glitches during polling
        }
    }

    setInterval(pollStudents, 3500);
}

function renderStudentRows(students, tbody) {
    if (!students || students.length === 0) {
        tbody.innerHTML = `<tr><td colspan="8" style="text-align: center; color: var(--text-dim); padding: 30px;">Belum ada siswa yang terhubung dalam sesi ujian.</td></tr>`;
        return;
    }

    let html = '';
    students.forEach((s) => {
        const isOnline = parseInt(s.is_online) === 1;
        const isLocked = parseInt(s.is_locked || 0) === 1;
        const vCount = parseInt(s.violation_count || 0);
        const maxV = parseInt(s.max_violations || 3);

        const statusBadge = isLocked
            ? `<span class="badge badge-danger" style="animation: pulse 1.5s infinite;">🚨 TERKUNCI (Auto-Lock)</span>`
            : (isOnline
                ? `<span class="badge badge-online"><span class="pulse-dot"></span> Online</span>`
                : `<span class="badge badge-offline">Offline</span>`);

        const vColor = vCount > 0 ? (vCount >= maxV ? '#EF4444' : '#F59E0B') : 'var(--text-muted)';
        const violationHtml = `
            <div style="font-weight: 700; font-size: 0.88rem; color: ${vColor};">
                ${vCount} / ${maxV}
            </div>
            ${vCount >= maxV ? '<span style="font-size: 0.7rem; color: #EF4444; font-weight: 700;">(Batas Tercapai)</span>' : ''}
        `;

        const hasLocation = s.latitude && s.longitude;
        const locationBtn = hasLocation
            ? `<button type="button" class="btn btn-secondary btn-sm" onclick="openLocationModal(${s.latitude}, ${s.longitude}, '${escapeHtml(s.student_name)}', '${escapeHtml(s.device_model)}')">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg> Peta GPS
               </button>`
            : `<span style="color: var(--text-dim); font-size: 0.78rem;">Tidak Ada GPS</span>`;

        const unlockBtn = isLocked
            ? `<button type="button" class="btn btn-sm" style="background: #10B981; color: white;" onclick="unlockStudent(${s.id}, '${escapeHtml(s.student_name)}')">
                🔓 Buka Kunci
               </button>`
            : '';

        html += `
            <tr id="session-row-${s.id}">
                <td>
                    <div style="font-weight: 700; color: var(--text-main);">${escapeHtml(s.student_name)}</div>
                    <div style="font-size: 0.75rem; color: var(--text-dim);">${escapeHtml(s.school_name || s.school_code)}</div>
                </td>
                <td>
                    <div style="font-weight: 600;">${escapeHtml(s.device_brand)} ${escapeHtml(s.device_model)}</div>
                    <div style="font-size: 0.75rem; color: var(--text-dim);">${escapeHtml(s.device_os)} &bull; ${escapeHtml(s.screen_resolution || '-')}</div>
                </td>
                <td>
                    <code style="background: rgba(255,255,255,0.06); padding: 2px 6px; border-radius: 4px; font-size: 0.8rem;">${escapeHtml(s.ip_address)}</code>
                </td>
                <td>
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="6" width="18" height="12" rx="2"></rect><line x1="23" y1="13" x2="23" y2="11"></line></svg>
                        <span>${s.battery_level}%</span>
                    </div>
                </td>
                <td>${violationHtml}</td>
                <td>${statusBadge}</td>
                <td>${locationBtn}</td>
                <td>
                    <div style="display: flex; gap: 6px;">
                        ${unlockBtn}
                        <button type="button" class="btn btn-danger btn-sm" onclick="forceExitStudent(${s.id}, '${escapeHtml(s.student_name)}')">
                            Force Exit
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
}

function updateLiveStats(stats) {
    if (!stats) return;
    const onlineEl = document.getElementById('statOnlineStudents');
    if (onlineEl) onlineEl.textContent = stats.online_count;
    const totalEl = document.getElementById('statTotalSessions');
    if (totalEl) totalEl.textContent = stats.total_sessions;
}

/* ==========================================================
   4. Modals & Actions
   ========================================================== */
function initModals() {
    document.querySelectorAll('.modal-close, .modal-backdrop').forEach(el => {
        el.addEventListener('click', (e) => {
            if (e.target === el) {
                closeAllModals();
            }
        });
    });
}

function closeAllModals() {
    document.querySelectorAll('.modal-backdrop').forEach(m => m.classList.remove('show'));
}

window.openLocationModal = function(lat, lng, name, device) {
    const modal = document.getElementById('gpsLocationModal');
    if (!modal) return;

    document.getElementById('gpsModalTitle').textContent = `Lokasi GPS: ${name} (${device})`;
    document.getElementById('gpsCoordsText').textContent = `Koordinat: ${lat}, ${lng}`;

    const iframe = document.getElementById('gpsMapIframe');
    if (iframe) {
        const delta = 0.005;
        const bbox = `${lng - delta}%2C${lat - delta}%2C${lng + delta}%2C${lat + delta}`;
        iframe.src = `https://www.openstreetmap.org/export/embed.html?bbox=${bbox}&layer=mapnik&marker=${lat}%2C${lng}`;
    }

    const extLink = document.getElementById('gpsExternalLink');
    if (extLink) {
        extLink.href = `https://www.google.com/maps?q=${lat},${lng}`;
    }

    modal.classList.add('show');
};

window.forceExitStudent = async function(sessionId, name) {
    if (!confirm(`Keluarkan paksa siswa "${name}" dari aplikasi Exambro sekarang?`)) {
        return;
    }

    const formData = new FormData();
    formData.append('action', 'force_exit');
    formData.append('session_id', sessionId);

    try {
        const res = await fetch('students.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.status === 'success') {
            showToast(`Perintah Force Exit telah dikirim ke perangkat "${name}".`, 'success');
        }
    } catch (e) {
        showToast('Gagal mengirim perintah.', 'danger');
    }
};

window.unlockStudent = async function(sessionId, name) {
    if (!confirm(`Buka kembali kunci layar ujian siswa "${name}"?`)) {
        return;
    }

    const formData = new FormData();
    formData.append('action', 'unlock_student');
    formData.append('session_id', sessionId);

    try {
        const res = await fetch('students.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.status === 'success') {
            showToast(`Sesi siswa "${name}" berhasil dibuka kembali (Unlocked)!`, 'success');
            // Refresh table immediately
            const liveTableBody = document.getElementById('liveStudentsTableBody');
            if (liveTableBody) {
                const pollRes = await fetch('students.php?ajax=live_list', { cache: 'no-store' });
                const json = await pollRes.json();
                if (json.status === 'success') renderStudentRows(json.data, liveTableBody);
            }
        } else {
            showToast(data.message || 'Gagal membuka kunci.', 'danger');
        }
    } catch (e) {
        showToast('Gagal mengirim perintah unlock.', 'danger');
    }
};

/* ==========================================================
   5. Utility Helpers & Toasts
   ========================================================== */
function initCopyButtons() {
    document.querySelectorAll('.copy-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('data-copy');
            const targetEl = document.getElementById(targetId);
            if (targetEl) {
                navigator.clipboard.writeText(targetEl.textContent.trim()).then(() => {
                    showToast('Kode berhasil disalin ke clipboard!', 'success');
                });
            }
        });
    });
}

function showToast(message, type = 'success') {
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        container.style.cssText = 'position: fixed; bottom: 24px; right: 24px; z-index: 9999; display: flex; flex-direction: column; gap: 10px;';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `alert alert-${type}`;
    toast.style.cssText = 'box-shadow: 0 10px 25px rgba(0,0,0,0.5); animation: modalIn 0.3s ease; margin: 0; min-width: 280px;';
    toast.innerHTML = message;
    container.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.4s ease';
        setTimeout(() => toast.remove(), 400);
    }, 3500);
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
