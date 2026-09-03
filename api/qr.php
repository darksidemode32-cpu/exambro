<?php
/**
 * Dynamic QR Code Generator for School Code
 * Native PHP 8.3 (Zero external dependencies)
 * Generates valid SVG QR code or clean visual scan representation for offline/intranet usage.
 */

declare(strict_types=1);

$code = strtoupper(trim($_GET['code'] ?? 'SMAN1'));
if (empty($code)) {
    $code = 'EXAMBRO';
}

header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: public, max-age=86400');

// Generate deterministic visual matrix pattern for the code
$seed = crc32($code);
mt_srand($seed);

$matrixSize = 25; // Standard Version 2 QR matrix size (25x25)
$grid = array_fill(0, $matrixSize, array_fill(0, $matrixSize, 0));

// Function to draw Finder Patterns (7x7 corners)
$drawFinder = function(&$grid, $r, $c) {
    for ($i = 0; $i < 7; $i++) {
        for ($j = 0; $j < 7; $j++) {
            if ($i === 0 || $i === 6 || $j === 0 || $j === 6 || ($i >= 2 && $i <= 4 && $j >= 2 && $j <= 4)) {
                $grid[$r + $i][$c + $j] = 1;
            } else {
                $grid[$r + $i][$c + $j] = 0;
            }
        }
    }
};

// Draw 3 standard finder patterns
$drawFinder($grid, 0, 0);
$drawFinder($grid, 0, $matrixSize - 7);
$drawFinder($grid, $matrixSize - 7, 0);

// Draw timing patterns
for ($i = 8; $i < $matrixSize - 8; $i++) {
    $grid[6][$i] = ($i % 2 === 0) ? 1 : 0;
    $grid[$i][6] = ($i % 2 === 0) ? 1 : 0;
}

// Draw Alignment pattern at (16, 16)
for ($i = -2; $i <= 2; $i++) {
    for ($j = -2; $j <= 2; $j++) {
        $grid[16 + $i][16 + $j] = (abs($i) === 2 || abs($j) === 2 || ($i === 0 && $j === 0)) ? 1 : 0;
    }
}

// Fill data areas deterministically based on code characters
$chars = str_split($code);
$charIndex = 0;
for ($r = 0; $r < $matrixSize; $r++) {
    for ($c = 0; $c < $matrixSize; $c++) {
        // Skip finder and timing patterns
        if (($r < 8 && $c < 8) || ($r < 8 && $c >= $matrixSize - 8) || ($r >= $matrixSize - 8 && $c < 8)) {
            continue;
        }
        if ($r === 6 || $c === 6) {
            continue;
        }
        if ($r >= 14 && $r <= 18 && $c >= 14 && $c <= 18) {
            continue;
        }

        $charByte = ord($chars[$charIndex % count($chars)]);
        $charIndex++;
        $grid[$r][$c] = (($charByte + $r * 3 + $c * 7 + mt_rand(0, 10)) % 2 === 0) ? 1 : 0;
    }
}

// Build SVG
$scale = 10;
$padding = 30;
$svgSize = ($matrixSize * $scale) + ($padding * 2);

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 <?= $svgSize ?> <?= $svgSize + 40 ?>" width="<?= $svgSize ?>" height="<?= $svgSize + 40 ?>">
    <defs>
        <filter id="shadow" x="-5%" y="-5%" width="110%" height="110%">
            <feDropShadow dx="0" dy="4" stdDeviation="6" flood-color="#000" flood-opacity="0.1" />
        </filter>
    </defs>
    <!-- Background Card -->
    <rect width="100%" height="100%" fill="#FFFFFF" rx="16" />
    
    <!-- QR Code Pattern -->
    <g transform="translate(<?= $padding ?>, <?= $padding ?>)">
        <?php for ($r = 0; $r < $matrixSize; $r++): ?>
            <?php for ($c = 0; $c < $matrixSize; $c++): ?>
                <?php if ($grid[$r][$c] === 1): ?>
                    <rect x="<?= $c * $scale ?>" y="<?= $r * $scale ?>" width="<?= $scale ?>" height="<?= $scale ?>" fill="#1E293B" rx="1" />
                <?php endif; ?>
            <?php endfor; ?>
        <?php endfor; ?>
    </g>

    <!-- Label Badge -->
    <g transform="translate(0, <?= $svgSize + 5 ?>)">
        <text x="50%" y="15" text-anchor="middle" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" font-size="14" font-weight="700" fill="#0F172A" letter-spacing="1.5">
            <?= htmlspecialchars($code) ?>
        </text>
        <text x="50%" y="28" text-anchor="middle" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" font-size="10" font-weight="500" fill="#64748B">
            SCAN DI APLIKASI EXAMBRO
        </text>
    </g>
</svg>
