<?php
/**
 * Administrator Secure Login Page
 * Native PHP 8.3
 * Features:
 * - Anti-Brute Force Rate Limiter & Automatic IP Blocking (15 mins on 5 failures)
 * - CSRF Token Protection
 * - PDO Prepared Statements
 * - Strict Anti-XSS Input Sanitization
 */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/security.php';

// Redirect if already logged in
if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: dashboard.php');
    exit;
}

$pdo = getDb();
$clientIp = getClientIp();

// Check if IP is currently blocked
[$isBlocked, $remainingSeconds] = isIpBlocked($pdo, $clientIp);

$errorMessage = '';
$successMessage = '';

if (isset($_GET['msg']) && $_GET['msg'] === 'logout') {
    $successMessage = 'Anda telah berhasil keluar dari sistem.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isBlocked) {
        $minutes = ceil($remainingSeconds / 60);
        $errorMessage = "Alamat IP Anda ({$clientIp}) diblokir sementara karena terlalu banyak percobaan login yang gagal. Silakan coba lagi dalam {$minutes} menit.";
    } elseif (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errorMessage = 'Token keamanan (CSRF) tidak valid atau telah kadaluarsa. Silakan refresh halaman dan coba lagi.';
    } else {
        $username = sanitizeInput($_POST['username'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        if (empty($username) || empty($password)) {
            $errorMessage = 'Username dan password wajib diisi.';
        } else {
            $stmt = $pdo->prepare("SELECT id, username, password_hash, name, role FROM admins WHERE username = ?");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password_hash'])) {
                // Successful login
                clearLoginAttempts($pdo, $clientIp);
                session_regenerate_id(true);

                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = (int)$admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_name'] = $admin['name'];
                $_SESSION['admin_role'] = $admin['role'];

                $redirect = $_GET['redirect'] ?? 'dashboard.php';
                header('Location: ' . $redirect);
                exit;
            } else {
                // Failed login attempt -> Record in Rate Limiter
                $rateResult = recordFailedLogin($pdo, $clientIp, $username);

                if ($rateResult['blocked']) {
                    $errorMessage = "Terdeteksi percobaan brute force! Alamat IP Anda telah diblokir selama 15 menit.";
                    $isBlocked = true;
                    $remainingSeconds = 900;
                } else {
                    $remaining = $rateResult['remaining_attempts'];
                    $errorMessage = "Username atau password salah. Sisa kesempatan: {$remaining} kali sebelum IP Anda diblokir otomatis.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Administrator - Exambro Proctor Server</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admin.css">
    <link rel="icon" type="image/svg+xml" href="assets/img/default-logo.svg">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: radial-gradient(circle at 50% 20%, #1E293B 0%, #0B0F19 80%);
            padding: 20px;
        }
        .login-wrapper {
            width: 100%;
            max-width: 440px;
        }
        .login-brand {
            text-align: center;
            margin-bottom: 28px;
        }
        .login-brand img {
            width: 72px;
            height: 72px;
            margin-bottom: 14px;
            filter: drop-shadow(0 6px 16px rgba(56, 189, 248, 0.35));
        }
        .login-brand h1 {
            font-size: 1.5rem;
            font-weight: 800;
            letter-spacing: 0.5px;
            color: #FFFFFF;
        }
        .login-brand p {
            font-size: 0.85rem;
            color: var(--text-dim);
            margin-top: 4px;
        }
        .login-box {
            background: rgba(30, 41, 59, 0.75);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: var(--radius-lg);
            padding: 34px;
            box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.6);
        }
        .demo-credential-pill {
            margin-top: 24px;
            padding: 12px 16px;
            background: rgba(56, 189, 248, 0.08);
            border: 1px dashed rgba(56, 189, 248, 0.3);
            border-radius: var(--radius-sm);
            font-size: 0.8rem;
            color: #94A3B8;
        }
        .demo-credential-pill strong {
            color: var(--primary);
        }
    </style>
</head>
<body>

<div class="login-wrapper">
    <div class="login-brand">
        <img src="assets/img/default-logo.svg" alt="Exambro Logo">
        <h1>EXAMBRO PROCTOR</h1>
        <p>Master Server &amp; Pengawasan Ujian Real-Time</p>
    </div>

    <div class="login-box">
        <?php if ($errorMessage): ?>
            <div class="alert alert-danger">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                <span><?= e($errorMessage) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($successMessage): ?>
            <div class="alert alert-success">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                <span><?= e($successMessage) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($isBlocked): ?>
            <div style="text-align: center; padding: 20px 0;">
                <div style="font-size: 2.5rem; margin-bottom: 10px;">🔒</div>
                <h3 style="color: var(--danger); margin-bottom: 8px;">IP Anda Diblokir Sementara</h3>
                <p style="font-size: 0.85rem; color: var(--text-dim); margin-bottom: 16px;">
                    Percobaan login terlalu sering. Sistem keamanan otomatis mengunci IP <code><?= e($clientIp) ?></code> selama 15 menit.
                </p>
                <div style="font-weight: 700; color: var(--warning);">
                    Sisa Waktu: <?= ceil($remainingSeconds / 60) ?> Menit
                </div>
            </div>
        <?php else: ?>
            <form method="POST" action="login.php<?= isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '' ?>">
                <?= csrfField() ?>

                <div class="form-group">
                    <label class="form-label" for="username">Username Admin</label>
                    <input type="text" name="username" id="username" class="form-control" placeholder="Masukkan username" required autofocus autocomplete="username">
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <input type="password" name="password" id="password" class="form-control" placeholder="Masukkan password" required autocomplete="current-password">
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; margin-top: 10px; font-weight: 700;">
                    Masuk ke Sistem Pengawasan
                </button>
            </form>

            <div class="demo-credential-pill">
                <strong>Akun Default:</strong><br>
                Username: <code>admin</code> &bull; Password: <code>admin123</code>
            </div>
        <?php endif; ?>
    </div>

    <div style="text-align: center; margin-top: 24px; font-size: 0.78rem; color: var(--text-dim);">
        &copy; <?= date('Y') ?> Exambro Proctor Server &bull; Native PHP 8.3 &bull; Anti-Hacker Protected
    </div>
</div>

</body>
</html>
