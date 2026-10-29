<?php
require __DIR__ . '/includes/bootstrap.php';
requirePost();
requireRole('user');
enforceActionLimit($pdo, 'booking-change', 30);
requireVerifiedEmail();
$id = postPositiveInt('event_id');
$action = postString('action');
if (!in_array($action, ['book', 'wait'], true)) {
    http_response_code(400);
    exit('Invalid action.');
}
try {
    $result = bookOrWait($pdo, $id, (int) currentUser()['user_id'], $action);
} catch (Throwable $exception) {
    serverError($exception);
}
$messages = [
    'booked' => ['Your place is confirmed.', 'success'],
    'waiting' => ['You joined the waiting list.', 'success'],
    'already_booked' => ['You already have a booking for this event.', 'error'],
    'already_waiting' => ['You are already in the waiting list.', 'error'],
    'full' => ['This event is now full. You can join the waiting list.', 'error'],
    'available' => ['A place is available; book it instead.', 'error'],
    'unverified' => ['Verify your email before booking.', 'error'],
    'unavailable' => ['This event is not available.', 'error'],
];
flash(...$messages[$result]);
redirect(match ($result) {
    'unverified' => 'account.php',
    'unavailable' => 'index.php',
    'booked' => 'booked.php?id=' . $id,
    default => 'event.php?id=' . $id,
});
