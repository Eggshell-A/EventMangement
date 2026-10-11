<?php
require __DIR__ . '/includes/bootstrap.php';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(404);
    exit('Event not found.');
}
$statement = $pdo->prepare("SELECT event_id FROM events WHERE event_id = ? AND status = 'approved'");
$statement->execute([$id]);
if (!$statement->fetch()) {
    http_response_code(404);
    exit('Event not found.');
}
redirect('event.php?id=' . $id);
