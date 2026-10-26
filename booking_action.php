<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
requireRole('user');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}
requireValidCsrf();

$id = (int) ($_POST['event_id'] ?? 0);
$action = $_POST['action'] ?? '';
$userId = (int) currentUser()['user_id'];
if (!in_array($action, ['book', 'wait'], true)) {
    http_response_code(400);
    exit('Invalid action.');
}

try {
    $result = $action === 'book'
        ? bookEventPlace($pdo, $id, $userId)
        : joinEventWaitlist($pdo, $id, $userId);
} catch (Throwable $exception) {
    flash('Your request could not be completed. Please try again.', 'error');
    redirect('event.php?id=' . $id);
}

if ($result === 'booked') {
    flash('Your place is confirmed.');
    redirect('booked.php?id=' . $id);
}
if ($result === 'waiting') {
    flash('You are on Fairboard’s waiting list. We will keep your place in line.');
    redirect('event.php?id=' . $id);
}

$messages = [
    'full' => 'This event is now full. You can join the waiting list.',
    'available' => 'A place is available. Join it instead.',
    'already_booked' => 'You already have a confirmed place.',
    'already_waiting' => 'You are already on the waiting list.',
    'unavailable' => 'This event is not available.',
];
flash($messages[$result] ?? 'Your request could not be completed.', 'error');
redirect($result === 'unavailable' ? 'index.php' : 'event.php?id=' . $id);
