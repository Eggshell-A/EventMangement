<?php
require __DIR__ . '/includes/bootstrap.php';
requirePost();
requireLogin();
$bookingId = postPositiveInt('booking_id');
$eventId = postPositiveInt('event_id');
try {
    withEventLock($pdo, $eventId, function ($event) use ($pdo, $bookingId, $eventId): void {
        if (!$event || !isEventOwnerOrAdmin($event)) {
            throw new BookingAccessDenied();
        }
        $statement = $pdo->prepare('SELECT booking_id FROM bookings WHERE booking_id=? AND event_id=? FOR UPDATE');
        $statement->execute([$bookingId, $eventId]);
        if (!$statement->fetch()) {
            throw new BookingAccessDenied();
        }
        $pdo->prepare('UPDATE bookings SET checked_in=1 WHERE booking_id=? AND event_id=?')->execute([$bookingId, $eventId]);
    });
} catch (BookingAccessDenied $exception) {
    http_response_code(403);
    exit('You cannot check in this attendee.');
} catch (Throwable $exception) {
    serverError($exception);
}
flash('Attendee checked in.');
redirect('attendees.php?event_id=' . $eventId);
