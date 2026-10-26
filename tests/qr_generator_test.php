<?php
declare(strict_types=1);

require __DIR__ . '/../includes/qr_generator.php';

$svg = renderEventQr('http://127.0.0.1:8765/e.php?id=42');
if (!str_starts_with(ltrim($svg), '<svg') || !str_contains($svg, '</svg>')) {
    fwrite(STDERR, "QR generator must return a complete SVG document.\n");
    exit(1);
}
if (strlen($svg) < 500) {
    fwrite(STDERR, "QR SVG is unexpectedly empty.\n");
    exit(1);
}
echo "ok\n";
