<?php
declare(strict_types=1);

require __DIR__ . '/../includes/helpers.php';

$expected = [
    'concert' => 'Concert',
    'cinema' => 'Cinema',
    'gallery' => 'Gallery',
    'digital' => 'Digital',
    'city' => 'City',
    'nature' => 'Nature',
    'learning' => 'Study',
    'sports' => 'Court',
    'social' => 'Gathering',
    'other' => 'Abstract',
];

$failures = 0;
if (artworkChoices() !== array_keys($expected)) {
    fwrite(STDERR, "FAIL artwork identifiers changed\n");
    $failures++;
}
foreach ($expected as $key => $label) {
    if (artworkLabel($key) !== $label) {
        fwrite(STDERR, "FAIL artwork label: {$key}\n");
        $failures++;
    }
    $path = artworkPath($key);
    if ($path !== "assets/art/{$key}.svg" || !is_file(__DIR__ . '/../' . $path)) {
        fwrite(STDERR, "FAIL artwork SVG missing: {$key}\n");
        $failures++;
    }
}
if (artworkLabel('unknown') !== 'Concert' || artworkPath('unknown') !== 'assets/art/concert.svg') {
    fwrite(STDERR, "FAIL invalid artwork fallback\n");
    $failures++;
}
if (glob(__DIR__ . '/../assets/art/*.webp') !== []) {
    fwrite(STDERR, "FAIL deprecated WebP artwork templates still present\n");
    $failures++;
}

if ($failures) {
    fwrite(STDERR, "{$failures} artwork catalogue tests failed\n");
    exit(1);
}
echo "PASS artwork IDs, display labels, SVG files and deprecated asset cleanup\n";
