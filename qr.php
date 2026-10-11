<?php
require __DIR__ . '/includes/bootstrap.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || !file_exists(__DIR__ . '/vendor/autoload.php')) {
    http_response_code($id ? 503 : 400);
    exit('QR code is unavailable.');
}

$statement = $pdo->prepare("SELECT event_id FROM events WHERE event_id = ? AND status = 'approved'");
$statement->execute([$id]);
if (!$statement->fetch()) {
    http_response_code(404);
    exit('Event not found.');
}

require __DIR__ . '/vendor/autoload.php';

$url = rtrim($config['base_url'], '/') . '/e.php?id=' . $id;
$options = new \chillerlan\QRCode\QROptions();
$options->outputBase64 = false;
$svg = (new \chillerlan\QRCode\QRCode($options))->render($url);

header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');
echo $svg;
