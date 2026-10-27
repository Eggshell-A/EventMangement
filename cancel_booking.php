<?php
require __DIR__ . '/includes/bootstrap.php';
requirePost();
requireRole('user');
try {
    $moved = cancelOwnBooking($pdo, postPositiveInt('booking_id'), (int) currentUser()['user_id']);
} catch (BookingAccessDenied $exception) {
    http_response_code(403);
    exit('Booking not found.');
} catch (Throwable $exception) {
    serverError($exception);
}
// Queue members' identities are visible only on the protected organiser/admin list.
flash($moved ? 'Booking cancelled. The place went to the next person on the waiting list.' : 'Booking cancelled. The place is free again.');
redirect('my_bookings.php');
