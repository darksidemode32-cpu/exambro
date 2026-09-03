<?php
/**
 * Dynamic 5-Minute Rotating Exit Password (Token) Generator & Validator
 * According to PRD:
 * - Rotates automatically every 5 minutes (300 seconds).
 * - Verifies current window token.
 * - Rejects tokens older than 5 minutes with the exact PRD message:
 *   "Password/token telah kadaluarsa (melewati 5 menit). Silakan minta token terbaru kepada pengawas ujian/admin."
 */

declare(strict_types=1);

define('TOKEN_INTERVAL_SECONDS', 300); // 5 minutes
define('TOKEN_SALT', 'EXAMBRO_SYSTEM_SECRET_KEY_2026_SALT');

/**
 * Generate 6-character alphanumeric token for a given 5-minute time window
 */
function generateWindowToken(int $windowIndex): string {
    $hash = hash_hmac('sha256', (string)$windowIndex, TOKEN_SALT);
    // Convert first 8 hex characters into readable uppercase charset
    $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ'; // 32 characters, no 0/O, 1/I confusion
    $token = '';
    for ($i = 0; $i < 6; $i++) {
        $byte = hexdec(substr($hash, $i * 2, 2));
        $token .= $chars[$byte % strlen($chars)];
    }
    return $token;
}

/**
 * Get current active 5-minute Exit Token details
 * Returns:
 * [
 *   'token' => '...',
 *   'remaining_seconds' => int,
 *   'elapsed_seconds' => int,
 *   'interval' => 300,
 *   'expires_at' => int (timestamp)
 * ]
 */
function getCurrentExitTokenInfo(): array {
    $now = time();
    $windowIndex = (int)floor($now / TOKEN_INTERVAL_SECONDS);
    $elapsed = $now % TOKEN_INTERVAL_SECONDS;
    $remaining = TOKEN_INTERVAL_SECONDS - $elapsed;
    $token = generateWindowToken($windowIndex);

    return [
        'token' => $token,
        'remaining_seconds' => $remaining,
        'elapsed_seconds' => $elapsed,
        'interval' => TOKEN_INTERVAL_SECONDS,
        'expires_at' => $now + $remaining,
        'window_index' => $windowIndex
    ];
}

/**
 * Validate submitted exit password
 * Returns:
 * [
 *   'valid' => bool,
 *   'status' => 'valid' | 'expired' | 'invalid',
 *   'message' => string
 * ]
 */
function validateExitPassword(string $submittedToken): array {
    $cleanToken = strtoupper(trim($submittedToken));
    if (empty($cleanToken)) {
        return [
            'valid' => false,
            'status' => 'invalid',
            'message' => 'Password keluar tidak boleh kosong.'
        ];
    }

    $now = time();
    $currentWindow = (int)floor($now / TOKEN_INTERVAL_SECONDS);
    $currentToken = generateWindowToken($currentWindow);

    // 1. Check if token matches active window
    if ($cleanToken === $currentToken) {
        return [
            'valid' => true,
            'status' => 'valid',
            'message' => 'Password keluar valid. Kunci aplikasi dibuka.'
        ];
    }

    // 2. Check if token matches the immediate previous window (expired)
    $previousToken = generateWindowToken($currentWindow - 1);
    if ($cleanToken === $previousToken) {
        return [
            'valid' => false,
            'status' => 'expired',
            'message' => 'Password/token telah kadaluarsa (sudah melewati 5 menit). Silakan minta password/token terbaru kepada pengawas ujian/admin.'
        ];
    }

    // Check older tokens (up to 30 mins back) to confirm it was once valid but has expired
    for ($w = 2; $w <= 6; $w++) {
        if ($cleanToken === generateWindowToken($currentWindow - $w)) {
            return [
                'valid' => false,
                'status' => 'expired',
                'message' => 'Password/token telah kadaluarsa (sudah melewati 5 menit). Silakan minta password/token terbaru kepada pengawas ujian/admin.'
            ];
        }
    }

    // 3. Completely invalid token
    return [
        'valid' => false,
        'status' => 'invalid',
        'message' => 'Password keluar salah. Pastikan Anda memasukkan kode token yang benar dari pengawas.'
    ];
}
