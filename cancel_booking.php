<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
requireRole('user');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}
requireValidCsrf();

$bookingId = (int) ($_POST['booking_id'] ?? 0);
$userId = (int) currentUser()['user_id'];
try {
    $result = cancelOwnBooking($pdo, $bookingId, $userId);
} catch (Throwable $exception) {
    flash('The booking could not be cancelled.', 'error');
    redirect('my_bookings.php');
}

if ($result === 'not_found') {
    http_response_code(403);
    exit('Booking not found.');
}
flash($result === 'promoted'
    ? 'Booking cancelled. The first person in Fairboard’s waiting line now has the place.'
    : 'Booking cancelled. The place is open again.');
redirect('my_bookings.php');
