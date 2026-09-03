<?php
/**
 * Database Configuration & Connection Handler
 * Pure Native PHP 8.3 with PDO Prepared Statements
 * Supports SQLite (zero-config, high portability) with auto-schema migration,
 * and seamless fallback/switch to MySQL/MariaDB.
 */

declare(strict_types=1);

// Database driver: 'sqlite' or 'mysql'
define('DB_DRIVER', 'sqlite');

// SQLite settings
define('DB_SQLITE_PATH', __DIR__ . '/../data/database.sqlite');

// MySQL / MariaDB settings (optional alternative)
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'exambro');
define('DB_USER', 'root');
define('DB_PASS', '');

function getDb(): PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    if (DB_DRIVER === 'sqlite') {
        $dir = dirname(DB_SQLITE_PATH);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $isNewDb = !file_exists(DB_SQLITE_PATH);
        $dsn = 'sqlite:' . DB_SQLITE_PATH;
        $pdo = new PDO($dsn, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON;');
        $pdo->exec('PRAGMA journal_mode = WAL;');

        if ($isNewDb || filesize(DB_SQLITE_PATH) === 0) {
            initDatabaseSchema($pdo, 'sqlite');
        } else {
            // Apply any column migrations
            try { $pdo->exec("ALTER TABLE schools ADD COLUMN max_violations INTEGER DEFAULT 3;"); } catch (\Throwable $e) {}
            try { $pdo->exec("ALTER TABLE schools ADD COLUMN announcement TEXT DEFAULT '';"); } catch (\Throwable $e) {}
            try { $pdo->exec("ALTER TABLE student_sessions ADD COLUMN is_locked INTEGER DEFAULT 0;"); } catch (\Throwable $e) {}
            try { $pdo->exec("ALTER TABLE student_sessions ADD COLUMN violation_count INTEGER DEFAULT 0;"); } catch (\Throwable $e) {}
        }
    } else {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            try { $pdo->exec("ALTER TABLE schools ADD COLUMN max_violations INT DEFAULT 3;"); } catch (\Throwable $e) {}
            try { $pdo->exec("ALTER TABLE schools ADD COLUMN announcement TEXT;"); } catch (\Throwable $e) {}
            try { $pdo->exec("ALTER TABLE student_sessions ADD COLUMN is_locked TINYINT(1) DEFAULT 0;"); } catch (\Throwable $e) {}
            try { $pdo->exec("ALTER TABLE student_sessions ADD COLUMN violation_count INT DEFAULT 0;"); } catch (\Throwable $e) {}
        } catch (PDOException $e) {
            // If database does not exist, try to create it
            $rootDsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=utf8mb4';
            $rootPdo = new PDO($rootDsn, DB_USER, DB_PASS);
            $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            initDatabaseSchema($pdo, 'mysql');
        }
    }

    return $pdo;
}

/**
 * Initialize Tables & Default Admin Account
 */
function initDatabaseSchema(PDO $pdo, string $driver): void {
    if ($driver === 'sqlite') {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS admins (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                password_hash TEXT NOT NULL,
                name TEXT NOT NULL,
                role TEXT DEFAULT 'admin',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS schools (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                school_code TEXT UNIQUE NOT NULL,
                school_name TEXT NOT NULL,
                exam_url TEXT NOT NULL,
                logo_url TEXT DEFAULT '',
                banner_url TEXT DEFAULT '',
                address TEXT DEFAULT '',
                contact TEXT DEFAULT '',
                remote_brightness INTEGER DEFAULT 80,
                is_active INTEGER DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS student_sessions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                session_token TEXT UNIQUE NOT NULL,
                school_id INTEGER NOT NULL,
                student_name TEXT DEFAULT 'Siswa',
                device_brand TEXT DEFAULT 'Unknown',
                device_model TEXT DEFAULT 'Unknown',
                device_os TEXT DEFAULT 'Unknown',
                screen_resolution TEXT DEFAULT '',
                ip_address TEXT NOT NULL,
                latitude REAL DEFAULT NULL,
                longitude REAL DEFAULT NULL,
                location_accuracy REAL DEFAULT NULL,
                battery_level INTEGER DEFAULT 100,
                is_online INTEGER DEFAULT 1,
                last_heartbeat DATETIME DEFAULT CURRENT_TIMESTAMP,
                force_exit INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS violations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                session_id INTEGER NULL,
                school_id INTEGER NULL,
                violation_type TEXT NOT NULL,
                description TEXT DEFAULT '',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS login_attempts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ip_address TEXT NOT NULL,
                username TEXT DEFAULT '',
                attempt_time INTEGER NOT NULL
            );

            CREATE TABLE IF NOT EXISTS blocked_ips (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ip_address TEXT UNIQUE NOT NULL,
                blocked_until INTEGER NOT NULL,
                reason TEXT DEFAULT 'Too many failed login attempts'
            );

            CREATE TABLE IF NOT EXISTS app_settings (
                setting_key TEXT PRIMARY KEY,
                setting_value TEXT NOT NULL
            );
        ");
    } else {
        // MySQL syntax
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS admins (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) UNIQUE NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                name VARCHAR(100) NOT NULL,
                role VARCHAR(20) DEFAULT 'admin',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS schools (
                id INT AUTO_INCREMENT PRIMARY KEY,
                school_code VARCHAR(50) UNIQUE NOT NULL,
                school_name VARCHAR(150) NOT NULL,
                exam_url VARCHAR(255) NOT NULL,
                logo_url VARCHAR(255) DEFAULT '',
                banner_url VARCHAR(255) DEFAULT '',
                address TEXT,
                contact VARCHAR(100) DEFAULT '',
                remote_brightness INT DEFAULT 80,
                is_active TINYINT(1) DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS student_sessions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                session_token VARCHAR(64) UNIQUE NOT NULL,
                school_id INT NOT NULL,
                student_name VARCHAR(100) DEFAULT 'Siswa',
                device_brand VARCHAR(50) DEFAULT 'Unknown',
                device_model VARCHAR(50) DEFAULT 'Unknown',
                device_os VARCHAR(50) DEFAULT 'Unknown',
                screen_resolution VARCHAR(50) DEFAULT '',
                ip_address VARCHAR(45) NOT NULL,
                latitude DECIMAL(10, 8) NULL,
                longitude DECIMAL(11, 8) NULL,
                location_accuracy DECIMAL(8, 2) NULL,
                battery_level INT DEFAULT 100,
                is_online TINYINT(1) DEFAULT 1,
                last_heartbeat DATETIME DEFAULT CURRENT_TIMESTAMP,
                force_exit TINYINT(1) DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS violations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                session_id INT NULL,
                school_id INT NULL,
                violation_type VARCHAR(50) NOT NULL,
                description TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS login_attempts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ip_address VARCHAR(45) NOT NULL,
                username VARCHAR(50) DEFAULT '',
                attempt_time INT NOT NULL
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS blocked_ips (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ip_address VARCHAR(45) UNIQUE NOT NULL,
                blocked_until INT NOT NULL,
                reason VARCHAR(255) DEFAULT 'Too many failed login attempts'
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS app_settings (
                setting_key VARCHAR(50) PRIMARY KEY,
                setting_value TEXT NOT NULL
            ) ENGINE=InnoDB;
        ");
    }

    // Seed default administrator (admin / admin123)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins");
    $stmt->execute();
    if ((int)$stmt->fetchColumn() === 0) {
        $defaultPasswordHash = password_hash('admin123', PASSWORD_BCRYPT, ['cost' => 10]);
        $insertAdmin = $pdo->prepare("INSERT INTO admins (username, password_hash, name, role) VALUES (?, ?, ?, ?)");
        $insertAdmin->execute(['admin', $defaultPasswordHash, 'Administrator Master', 'superadmin']);
    }

    // Seed default demonstration schools
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM schools");
    $stmt->execute();
    if ((int)$stmt->fetchColumn() === 0) {
        $insertSchool = $pdo->prepare("
            INSERT INTO schools (school_code, school_name, exam_url, address, contact, remote_brightness, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $insertSchool->execute([
            'SMAN1',
            'SMA Negeri 1 Indonesia Unggul',
            'http://localhost:5000/exambro/exam_demo.php',
            'Jl. Pendidikan Nasional No. 45, Jakarta Pusat',
            '(021) 555-1234',
            85,
            1
        ]);
        $insertSchool->execute([
            'SMKN2',
            'SMK Negeri 2 Teknologi Digital',
            'http://localhost:5000/exambro/exam_demo.php',
            'Jl. Sains & Teknologi No. 88, Bandung',
            '(022) 777-9876',
            90,
            1
        ]);
    }

    // Auto-migration for existing tables (Batas Pelanggaran & Auto Lock & Pengumuman)
    try {
        $pdo->exec("ALTER TABLE schools ADD COLUMN max_violations INTEGER DEFAULT 3;");
    } catch (\Throwable $e) {}

    try {
        $pdo->exec("ALTER TABLE schools ADD COLUMN announcement TEXT DEFAULT '';");
    } catch (\Throwable $e) {}

    try {
        $pdo->exec("ALTER TABLE student_sessions ADD COLUMN is_locked INTEGER DEFAULT 0;");
    } catch (\Throwable $e) {}

    try {
        $pdo->exec("ALTER TABLE student_sessions ADD COLUMN violation_count INTEGER DEFAULT 0;");
    } catch (\Throwable $e) {}
}
