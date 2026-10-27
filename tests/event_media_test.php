<?php
declare(strict_types=1);

require __DIR__ . '/../includes/event_media.php';

$failures = [];
function expectMedia(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        $failures[] = $message;
    }
}

$png = tempnam(sys_get_temp_dir(), 'fairboard-png-');
file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl2nGQAAAAASUVORK5CYII='));
$text = tempnam(sys_get_temp_dir(), 'fairboard-text-');
file_put_contents($text, 'not an image');
$dir = sys_get_temp_dir() . '/fairboard-media-' . bin2hex(random_bytes(6));
mkdir($dir);

$saved = saveEventPoster(['error' => UPLOAD_ERR_OK, 'size' => filesize($png), 'tmp_name' => $png, 'name' => 'poster.png'], $dir);
expectMedia($saved['error'] === null, 'A valid PNG poster must be accepted.');
expectMedia(is_string($saved['filename']) && preg_match('/^[a-f0-9]{32}\.png$/', $saved['filename']) === 1, 'Poster must use a random server filename with the validated extension.');
expectMedia(file_exists($dir . '/' . $saved['filename']), 'Accepted poster must be stored in the upload directory.');

$invalid = saveEventPoster(['error' => UPLOAD_ERR_OK, 'size' => filesize($text), 'tmp_name' => $text, 'name' => 'poster.png'], $dir);
expectMedia($invalid['filename'] === null && $invalid['error'] !== null, 'A text file disguised as PNG must be rejected.');
$large = saveEventPoster(['error' => UPLOAD_ERR_OK, 'size' => EVENT_POSTER_MAX_BYTES + 1, 'tmp_name' => $text, 'name' => 'large.png'], $dir);
expectMedia($large['filename'] === null && $large['error'] !== null, 'A poster above the size limit must be rejected before storage.');
$empty = saveEventPoster(['error' => UPLOAD_ERR_NO_FILE, 'size' => 0, 'tmp_name' => '', 'name' => ''], $dir);
expectMedia($empty === ['filename' => null, 'error' => null], 'No uploaded file must be treated as an optional empty value.');

foreach (glob($dir . '/*') ?: [] as $file) { unlink($file); }
rmdir($dir);
@unlink($png);
@unlink($text);

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo "ok\n";
