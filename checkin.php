<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
requireLogin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}
requireValidCsrf();

$booking = (int) ($_POST['booking_id'] ?? 0);
$eventId = (int) ($_POST['event_id'] ?? 0);
$statement = $pdo->prepare('SELECT e.* FROM events e JOIN bookings b ON b.event_id = e.event_id WHERE b.booking_id = ? AND e.event_id = ?');
$statement->execute([$booking, $eventId]);
$event = $statement->fetch();
if (!$event || !isEventOwnerOrAdmin($event)) {
    http_response_code(403);
    exit('You cannot check in this attendee.');
}

$pdo->prepare('UPDATE bookings SET checked_in = 1 WHERE booking_id = ?')->execute([$booking]);
flash('Attendee checked in.');
redirect('attendees.php?event_id=' . $eventId);
