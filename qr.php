<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/qr_generator.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(400);
    exit('QR code is unavailable.');
}

$statement = $pdo->prepare("SELECT event_id FROM events WHERE event_id = ? AND status = 'approved'");
$statement->execute([$id]);
if (!$statement->fetch()) {
    http_response_code(404);
    exit('Event not found.');
}

$url = rtrim($config['base_url'], '/') . '/e.php?id=' . $id;
try {
    $svg = renderEventQr($url);
} catch (RuntimeException $exception) {
    http_response_code(503);
    exit('QR code is unavailable.');
}

header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');
echo $svg;
