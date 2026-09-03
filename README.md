# Aplikasi Exambro Master Server & Android APK (Native PHP 8.3)

Sistem pelaksanaan ujian terkunci (Kiosk Mode), tersinkronisasi secara real-time, multi-tenant (white-label), dan sangat ringan murni menggunakan **Native PHP 8.3** (< 1 MB) sesuai spesifikasi [prd.md](prd.md).

---

## 🌟 Fitur Utama Sesuai PRD

1. **White-Label & Multi-Tenant (Dynamic Endpoint Routing)**:
   - Satu file APK dapat digunakan untuk ribuan sekolah berbeda tanpa perlu *hardcode* domain.
   - Siswa/pengawas cukup memasukkan **Kode Sekolah** (contoh: `SMAN1`) atau memindai **QR Code**.
   - Server otomatis mengembalikan konfigurasi visual, logo, banner, dan URL server ujian CBT.

2. **Sistem Keamanan Keluar (Dynamic 5-Minute Exit Password)**:
   - Password keluar (Token) digenerate secara dinamis dari Web Admin dan **berubah otomatis setiap 5 menit**.
   - **Mekanisme Kadaluarsa:** Jika siswa memasukkan password yang usianya sudah melewati 5 menit, APK menolak dan menampilkan pesan peringatan agar siswa meminta password/token terbaru kepada pengawas.
   - Dilengkapi countdown timer animasi lingkaran pada dashboard pengawas.

3. **Manajemen Lingkungan Perangkat (Device Control)**:
   - Pengawas dapat mengatur tingkat kecerahan (*brightness*) layar perangkat Android siswa secara *remote* melalui slider di Web Admin secara real-time.

4. **Monitoring & Log Siswa**:
   - **Log Login:** Mencatat waktu masuk (timestamp) secara presisi.
   - **Identifikasi Perangkat:** Merk, model, nama, OS, dan resolusi layar siswa.
   - **Jejak Jaringan:** IP Address siswa.
   - **Pelacakan Lokasi:** Koordinat GPS (latitude, longitude, akurasi) dengan tampilan peta interaktif (OpenStreetMap / Google Maps).
   - **Audit Pelanggaran:** Mencatat percobaan split-screen, minimize, atau shortcut terlarang.

5. **Standar Arsitektur & Keamanan Backend (Native PHP 8.3)**:
   - **Ukuran Ringan:** < 1 MB tanpa framework berat, performa super cepat.
   - **PDO Prepared Statements:** Mutlak untuk mencegah SQL Injection (SQLite zero-config + MariaDB support).
   - **Proteksi CSRF Token:** Pada setiap form submission.
   - **Sanitasi Ketat Anti-XSS:** Perlindungan dari script berbahaya.
   - **Rate Limiting & Auto IP Block:** Otomatis memblokir IP selama 15 menit jika terdeteksi 5 kali percobaan gagal login.
   - **Hashing Aman:** `password_hash()` BCRYPT native PHP.

---

## 🚀 Cara Menjalankan & Mengakses Sistem

### 1. Akses Web Admin Pengawas
Buka browser dan kunjungi:
👉 **[http://localhost:5000/exambro/](http://localhost:5000/exambro/)**

**Akun Login Default:**
- **Username:** `admin`
- **Password:** `admin123`

### 2. Akses Simulator Siswa (Web Kiosk Simulator)
Buka di tab browser terpisah atau di HP:
👉 **[http://localhost:5000/exambro/student/](http://localhost:5000/exambro/student/)**
- Masukkan Kode Sekolah: `SMAN1` (atau `SMKN2`).
- Klik **Mulai Sesi Ujian (Kiosk Mode)**.
- Layar akan terkunci penuh, terhubung ke CBT demo, dan tersinkronisasi dengan slider kecerahan di Web Admin!
- Untuk keluar, klik **Keluar Ujian** dan masukkan 6-karakter token aktif 5-menit dari Web Admin.

### 3. Akses Demo Soal Ujian CBT
👉 **[http://localhost:5000/exambro/exam_demo.php](http://localhost:5000/exambro/exam_demo.php)**

---

## 📱 Solusi Build APK di Cloud (Tanpa Android Studio)

Jika laptop Anda penuh dan tidak memiliki Android Studio, Anda dapat memanfaatkan **GitHub Actions** untuk mengkompilasi file APK secara gratis di cloud:

1. Buat repository baru di akun GitHub Anda (misal: `exambro-system`).
2. Buka terminal di folder project ini (`c:\xampp82\htdocs\exambro`), lalu jalankan perintah:
   ```bash
   git init
   git add .
   git commit -m "Initial Exambro System"
   git branch -M main
   git remote add origin https://github.com/USERNAME-ANDA/exambro-system.git
   git push -u origin main
   ```
3. Buka repository Anda di browser, masuk ke tab **Actions**.
4. Workflow **Build Exambro APK (Cloud CI/CD)** akan berjalan otomatis (~2-3 menit).
5. Setelah status centang hijau (Success), klik workflow tersebut dan unduh file **`exambro-debug-apk`** pada bagian **Artifacts**.
6. File `.apk` siap diinstall ke smartphone atau tablet Android siswa!
