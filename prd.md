# **Product Requirements Document (PRD): Aplikasi Exambro Android & Web Admin**

## **1\. Ringkasan Eksekutif**

Dokumen ini menguraikan spesifikasi kebutuhan untuk pengembangan aplikasi Exambro berbasis Android beserta Web Admin-nya. Sistem ini dirancang untuk pelaksanaan ujian yang aman (terkunci), tersinkronisasi secara real-time, fleksibel untuk banyak klien (multi-tenant), dan sangat ringan (Native PHP 8.3 dengan ukuran \~1 MB).

## **2\. Solusi Fleksibilitas Distribusi (White-label & Multi-Tenant)**

**Menjawab Kebutuhan:** Tidak perlu melakukan *generate* atau rombak source code APK setiap kali ada klien (sekolah) baru yang menggunakan web admin di subdomain berbeda.

> * **Sistem Kode Sekolah (School Code) / QR Code:** Saat APK Exambro dijalankan pertama kali, siswa atau pengawas hanya perlu memasukkan "Kode Sekolah" atau memindai QR Code dari pengawas.  
> * **Dynamic Endpoint Routing:** Kode tersebut akan melakukan *request* API ke server pusat (master server) yang kemudian mengembalikan data spesifik milik sekolah tersebut, termasuk: URL/IP server ujian, Logo, Banner, dan Informasi instansi.  
> * **Keuntungan:** Hanya butuh 1 file APK (di Play Store atau di-download langsung) yang bisa dipakai oleh ribuan sekolah berbeda tanpa perlu \*hardcode\* domain.

## **3\. Spesifikasi Kebutuhan Aplikasi Android (Exambro APK)**

> * **Sinkronisasi Data Otomatis:** Aplikasi mengambil data konfigurasi visual dan endpoint dari API berdasarkan input awal (Kode Sekolah).  
> * **Sistem Keamanan Keluar (Exit Password):**  
  * Siswa tidak dapat menutup/keluar dari aplikasi tanpa memasukkan password yang benar.  
  * Password (Token) digenerate secara dinamis dari server Web Admin dan **berubah otomatis setiap 5 menit**.  
  * **Mekanisme Kadaluarsa:** Jika siswa memasukkan password yang usianya sudah melewati 5 menit, APK akan menolak dan menampilkan pesan peringatan agar siswa meminta password/token terbaru kepada pengawas ujian/admin.  
> * **Mode Terkunci (Kiosk Mode / Anti Kecurangan):**  
  * Menggunakan fitur *Lock Task Mode* / *Screen Pinning* native Android.  
  * Mencegah fungsi layar belah (*Anti Split-Screen*).  
  * Menonaktifkan tombol navigasi (Home, Back, Recent Apps).  
  * Memblokir notifikasi masuk dari aplikasi lain.

## **4\. Spesifikasi Kebutuhan Web Admin**

> * **Manajemen Lingkungan Perangkat (Device Control):** Admin/Pengawas dapat melakukan *override* atau mengatur tingkat kecerahan (*brightness*) layar perangkat Android siswa secara \*remote\* agar sesuai dengan standar pengawasan.  
> * **Monitoring & Log Siswa:**  
  * **Log Login:** Mencatat waktu masuk (timestamp) secara presisi.  
  * **Identifikasi Perangkat:** Menarik data nama, merk, dan model perangkat Android siswa.  
  * **Jejak Jaringan:** Mencatat IP Address yang digunakan.  
  * **Pelacakan Lokasi:** Mencatat koordinat GPS (titik lokasi) siswa saat mengakses ujian (membutuhkan permission lokasi pada APK).  
> * **Standar Arsitektur & Keamanan (Backend):**  
  * **Ukuran & Performa:** Dibangun murni menggunakan **Native PHP 8.3**. Ditargetkan memiliki ukuran file source code maksimal **\~1 MB** agar sangat ringan, minim *resource*, dan cepat di-install di server lokal/sekolah yang berspesifikasi rendah.  
  * **Autentikasi & Anti-Hacker:**  
    * Implementasi *Prepared Statements* (PDO) mutlak untuk mencegah *SQL Injection*.  
    * Proteksi CSRF (*Cross-Site Request Forgery*) token pada setiap \*form submission\*.  
    * Sanitasi input ketat (Anti-XSS).  
    * Sistem \*Rate Limiting\* dan \*Block IP\* otomatis jika mendeteksi percobaan \*brute force\* pada halaman login admin.  
    * Hashing password tingkat lanjut menggunakan password\_hash() native PHP.