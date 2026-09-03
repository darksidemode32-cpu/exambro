<?php
/**
 * Security, Anti-Hacker & CSRF Protection Module
 * Native PHP 8.3
 * - Strict CSRF token validation
 * - Anti-XSS sanitization
 * - Rate Limiter & Automatic IP Blocking (Brute-force protection)
 * - Safe session initialization
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    // Configure secure session cookie settings
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

/**
 * Get Client Real IP Address safely
 */
function getClientIp(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $forwarded = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $candidate = trim($forwarded[0]);
        if (filter_var($candidate, FILTER_VALIDATE_IP)) {
            $ip = $candidate;
        }
    }
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '127.0.0.1';
}

/**
 * Generate or retrieve current CSRF token
 */
function getCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Render hidden CSRF input field
 */
function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Verify submitted CSRF token
 */
function verifyCsrfToken(?string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * HTML Escape helper (Anti-XSS)
 */
function e(?string $string): string {
    return htmlspecialchars((string)$string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Sanitize generic string input
 */
function sanitizeInput(?string $input): string {
    return trim(strip_tags((string)$input));
}

/**
 * Rate Limiter: Check if IP is currently blocked
 * Returns [bool $isBlocked, int $remainingSeconds]
 */
function isIpBlocked(PDO $pdo, string $ip): array {
    $now = time();
    $stmt = $pdo->prepare("SELECT blocked_until FROM blocked_ips WHERE ip_address = ? AND blocked_until > ?");
    $stmt->execute([$ip, $now]);
    $blockedUntil = $stmt->fetchColumn();

    if ($blockedUntil) {
        return [true, (int)$blockedUntil - $now];
    }
    return [false, 0];
}

/**
 * Rate Limiter: Record failed login attempt and auto-block if threshold exceeded
 * Threshold: 5 failed attempts within 15 minutes (900 seconds) -> Block for 15 minutes
 */
function recordFailedLogin(PDO $pdo, string $ip, string $username = ''): array {
    $now = time();
    $window = 900; // 15 minutes
    $maxAttempts = 5;

    // Insert attempt
    $stmt = $pdo->prepare("INSERT INTO login_attempts (ip_address, username, attempt_time) VALUES (?, ?, ?)");
    $stmt->execute([$ip, $username, $now]);

    // Count attempts in window
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempt_time >= ?");
    $stmt->execute([$ip, $now - $window]);
    $attempts = (int)$stmt->fetchColumn();

    if ($attempts >= $maxAttempts) {
        $blockedUntil = $now + $window;
        $reason = "Terdeteksi percobaan brute force ($attempts kali gagal dalam 15 menit)";
        
        $blockStmt = $pdo->prepare("
            INSERT INTO blocked_ips (ip_address, blocked_until, reason)
            VALUES (?, ?, ?)
            ON CONFLICT(ip_address) DO UPDATE SET blocked_until = excluded.blocked_until, reason = excluded.reason
        ");
        try {
            $blockStmt->execute([$ip, $blockedUntil, $reason]);
        } catch (\Exception $ex) {
            // MySQL alternative syntax fallback
            $mysqlBlock = $pdo->prepare("
                INSERT INTO blocked_ips (ip_address, blocked_until, reason)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE blocked_until = VALUES(blocked_until), reason = VALUES(reason)
            ");
            $mysqlBlock->execute([$ip, $blockedUntil, $reason]);
        }

        return ['blocked' => true, 'remaining' => $window, 'attempts' => $attempts];
    }

    return ['blocked' => false, 'attempts' => $attempts, 'remaining_attempts' => $maxAttempts - $attempts];
}

/**
 * Clear failed login attempts after successful authentication
 */
function clearLoginAttempts(PDO $pdo, string $ip): void {
    $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
    $stmt->execute([$ip]);
}

/**
 * Set flash message
 */
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Get and clear flash message
 */
function getFlash(): ?array {
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}
