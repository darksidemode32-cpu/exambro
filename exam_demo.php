<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Ujian CBT Online - Demo Server Exambro</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
        }
        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            background: #F1F5F9;
            color: #0F172A;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .cbt-header {
            background: #1E293B;
            color: white;
            padding: 14px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid #38BDF8;
        }
        .cbt-brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .cbt-brand h2 {
            font-size: 1.1rem;
            font-weight: 800;
        }
        .cbt-brand span {
            font-size: 0.75rem;
            color: #94A3B8;
            background: rgba(255,255,255,0.1);
            padding: 3px 8px;
            border-radius: 4px;
        }
        .cbt-timer {
            background: #0F172A;
            border: 1px solid #334155;
            padding: 8px 16px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-family: monospace;
            font-size: 1.1rem;
            font-weight: 800;
            color: #38BDF8;
        }
        .cbt-container {
            max-width: 1000px;
            margin: 24px auto;
            padding: 0 16px;
            width: 100%;
            display: grid;
            grid-template-columns: 1fr 280px;
            gap: 20px;
            flex: 1;
        }
        .question-card {
            background: white;
            border-radius: 14px;
            padding: 28px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.06);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .q-meta {
            display: flex;
            justify-content: space-between;
            border-bottom: 1px solid #E2E8F0;
            padding-bottom: 12px;
            margin-bottom: 18px;
            font-size: 0.85rem;
            color: #64748B;
            font-weight: 600;
        }
        .q-text {
            font-size: 1.05rem;
            font-weight: 600;
            line-height: 1.6;
            margin-bottom: 24px;
            color: #1E293B;
        }
        .options-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .option-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 18px;
            border: 2px solid #E2E8F0;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.95rem;
        }
        .option-item:hover {
            border-color: #38BDF8;
            background: #F0F9FF;
        }
        .option-item.selected {
            border-color: #0284C7;
            background: #E0F2FE;
            font-weight: 700;
            color: #0369A1;
        }
        .option-badge {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #E2E8F0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            color: #475569;
            flex-shrink: 0;
        }
        .option-item.selected .option-badge {
            background: #0284C7;
            color: white;
        }
        .nav-sidebar {
            background: white;
            border-radius: 14px;
            padding: 20px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.06);
            height: fit-content;
        }
        .nav-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-top: 14px;
        }
        .q-num-btn {
            padding: 10px 0;
            border-radius: 8px;
            border: 1px solid #CBD5E1;
            background: #F8FAFC;
            font-weight: 700;
            font-size: 0.88rem;
            cursor: pointer;
            text-align: center;
        }
        .q-num-btn.active {
            background: #0284C7;
            color: white;
            border-color: #0284C7;
        }
        .q-num-btn.answered {
            background: #10B981;
            color: white;
            border-color: #10B981;
        }
        .q-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 24px;
            padding-top: 18px;
            border-top: 1px solid #E2E8F0;
        }
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            border: none;
        }
        .btn-prev { background: #E2E8F0; color: #475569; }
        .btn-next { background: #0284C7; color: white; }
        .btn-finish { background: #10B981; color: white; width: 100%; margin-top: 20px; padding: 12px; }

        @media (max-width: 768px) {
            .cbt-container { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body oncontextmenu="return false;">

<header class="cbt-header">
    <div class="cbt-brand">
        <h2>UJIAN NASIONAL BERBASIS KOMPUTER (CBT)</h2>
        <span>Mata Pelajaran: Literasi &amp; Sains</span>
    </div>
    <div class="cbt-timer">
        <span>Sisa Waktu:</span>
        <span id="cbtTimerText">44:59</span>
    </div>
</header>

<main class="cbt-container">
    <!-- Question Area -->
    <div class="question-card">
        <div>
            <div class="q-meta">
                <span id="currentQNumText">Soal Nomor 1 dari 5</span>
                <span>Bobot: 20 Poin</span>
            </div>
            <div class="q-text" id="questionText">
                Memuat pertanyaan ujian...
            </div>
            <div class="options-list" id="optionsContainer">
                <!-- Options rendered by JS -->
            </div>
        </div>

        <div class="q-actions">
            <button type="button" class="btn btn-prev" id="btnPrev" onclick="navigateQuestion(-1)">&#x2190; Soal Sebelumnya</button>
            <button type="button" class="btn btn-next" id="btnNext" onclick="navigateQuestion(1)">Soal Selanjutnya &#x2192;</button>
        </div>
    </div>

    <!-- Navigation Grid -->
    <div class="nav-sidebar">
        <h4 style="font-size: 0.95rem; font-weight: 700; color: #1E293B;">Nomor Soal</h4>
        <div class="nav-grid" id="navGrid">
            <!-- Buttons rendered by JS -->
        </div>

        <button type="button" class="btn btn-finish" onclick="finishExam()">
            Selesai Ujian
        </button>
    </div>
</main>

<script>
// Prevent shortcuts (Ctrl+C, Ctrl+V, F12, Escape, etc.)
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' || e.key === 'F11' || e.key === 'F12' || (e.ctrlKey && (e.key === 'u' || e.key === 'c' || e.key === 'v' || e.key === 's' || e.key === 't' || e.key === 'w' || e.key === 'n'))) {
        e.preventDefault();
        try {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({ type: 'VIOLATION_KEY', key: e.key }, '*');
            }
        } catch (err) {}
        return false;
    }
});

const questions = [
    {
        id: 1,
        text: "Manakah teknologi arsitektur di bawah ini yang digunakan oleh Exambro Master Server untuk menjamin keamanan dari serangan SQL Injection?",
        options: [
            "Prepared Statements menggunakan PHP Data Objects (PDO)",
            "Query concatenating biasa dengan string quote",
            "Menghapus parameter query dari URL",
            "Menggunakan framework PHP yang sangat berat"
        ],
        answer: 0
    },
    {
        id: 2,
        text: "Berapa lama masa aktif Exit Password (Token Keluar) Exambro sebelum berganti otomatis secara dinamis?",
        options: [
            "Setiap 1 menit",
            "Setiap 5 menit (300 detik)",
            "Setiap 1 jam",
            "Tidak pernah berganti (statis)"
        ],
        answer: 1
    },
    {
        id: 3,
        text: "Fitur keamanan Kiosk Mode pada APK Exambro Android native menggunakan fungsi apa untuk mengunci layar penuh siswa?",
        options: [
            "Lock Task Mode & Screen Pinning native Android",
            "Hanya menggunakan JavaScript alert()",
            "Mode split-screen bebas",
            "Membiarkan tombol navigasi Home & Recent terbuka"
        ],
        answer: 0
    },
    {
        id: 4,
        text: "Bagaimana cara pengawas ujian mengatur kecerahan (brightness) layar perangkat siswa di ruang ujian?",
        options: [
            "Harus mendatangi satu per satu meja siswa",
            "Melalui slider Remote Device Control di Web Admin secara real-time",
            "Meminta siswa menyetel sendiri di setting HP",
            "Kecerahan layar tidak bisa diatur"
        ],
        answer: 1
    },
    {
        id: 5,
        text: "Apa keuntungan utama sistem Kode Sekolah / QR Code pada Exambro White-Label?",
        options: [
            "Hanya butuh 1 file APK untuk digunakan oleh ribuan sekolah berbeda tanpa perlu kompilasi ulang domain",
            "APK harus di-build ulang setiap ada sekolah baru",
            "Siswa tidak perlu internet untuk sinkronisasi",
            "Semua sekolah wajib memakai domain yang sama"
        ],
        answer: 0
    }
];

let currentIndex = 0;
const userAnswers = {};

function renderCurrentQuestion() {
    const q = questions[currentIndex];
    document.getElementById('currentQNumText').textContent = `Soal Nomor ${currentIndex + 1} dari ${questions.length}`;
    document.getElementById('questionText').textContent = q.text;

    const optContainer = document.getElementById('optionsContainer');
    optContainer.innerHTML = '';

    const letters = ['A', 'B', 'C', 'D'];
    q.options.forEach((opt, idx) => {
        const isSelected = userAnswers[currentIndex] === idx;
        const div = document.createElement('div');
        div.className = `option-item ${isSelected ? 'selected' : ''}`;
        div.innerHTML = `
            <div class="option-badge">${letters[idx]}</div>
            <div>${opt}</div>
        `;
        div.onclick = () => {
            userAnswers[currentIndex] = idx;
            renderCurrentQuestion();
            renderNavGrid();
        };
        optContainer.appendChild(div);
    });

    document.getElementById('btnPrev').style.visibility = currentIndex === 0 ? 'hidden' : 'visible';
    document.getElementById('btnNext').textContent = currentIndex === questions.length - 1 ? 'Tinjau Kembali' : 'Soal Selanjutnya →';
}

function renderNavGrid() {
    const grid = document.getElementById('navGrid');
    grid.innerHTML = '';
    questions.forEach((q, idx) => {
        const btn = document.createElement('button');
        btn.className = `q-num-btn ${idx === currentIndex ? 'active' : ''} ${userAnswers[idx] !== undefined ? 'answered' : ''}`;
        btn.textContent = idx + 1;
        btn.onclick = () => {
            currentIndex = idx;
            renderCurrentQuestion();
            renderNavGrid();
        };
        grid.appendChild(btn);
    });
}

function navigateQuestion(delta) {
    const next = currentIndex + delta;
    if (next >= 0 && next < questions.length) {
        currentIndex = next;
        renderCurrentQuestion();
        renderNavGrid();
    }
}

function finishExam() {
    const answeredCount = Object.keys(userAnswers).length;
    if (confirm(`Anda telah menjawab ${answeredCount} dari ${questions.length} soal. Apakah Anda yakin ingin menyelesaikan ujian sekarang?`)) {
        alert("Jawaban Anda berhasil dikirim ke server ujian CBT! Silakan minta Password Keluar (Token 5 Menit) kepada pengawas untuk keluar dari aplikasi.");
    }
}

// Timer Countdown
let remainingSecs = 45 * 60;
setInterval(() => {
    if (remainingSecs > 0) {
        remainingSecs--;
        const m = Math.floor(remainingSecs / 60);
        const s = remainingSecs % 60;
        document.getElementById('cbtTimerText').textContent = `${m}:${s < 10 ? '0' : ''}${s}`;
    }
}, 1000);

// Initialize
renderCurrentQuestion();
renderNavGrid();
</script>

</body>
</html>
