<?php
require __DIR__ . '/includes/bootstrap.php';
requireRole('user');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}
$bookingId = (int) ($_POST['booking_id'] ?? 0);
$userId = (int) currentUser()['user_id'];
try {
    $pdo->beginTransaction();
    $statement = $pdo->prepare('SELECT booking_id, event_id FROM bookings WHERE booking_id = ? AND user_id = ? FOR UPDATE');
    $statement->execute([$bookingId, $userId]);
    $booking = $statement->fetch();
    if (!$booking) {
        $pdo->rollBack();
        http_response_code(403);
        exit('Booking not found.');
    }
    $pdo->prepare('SELECT event_id FROM events WHERE event_id = ? FOR UPDATE')->execute([$booking['event_id']]);
    $pdo->prepare('DELETE FROM bookings WHERE booking_id = ? AND user_id = ?')->execute([$bookingId, $userId]);
    $statement = $pdo->prepare('SELECT entry_id, user_id FROM waiting_list WHERE event_id = ? ORDER BY joined_at, entry_id LIMIT 1 FOR UPDATE');
    $statement->execute([$booking['event_id']]);
    $next = $statement->fetch();
    $moved = false;
    if ($next) {
        $pdo->prepare('DELETE FROM waiting_list WHERE entry_id = ?')->execute([$next['entry_id']]);
        $pdo->prepare('INSERT INTO bookings (event_id, user_id) VALUES (?, ?)')->execute([$booking['event_id'], $next['user_id']]);
        $moved = true;
    }
    $pdo->commit();
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash('The booking could not be cancelled.', 'error');
    redirect('my_bookings.php');
}
flash($moved ? 'Booking cancelled. The place went to the next person on the waiting list.' : 'Booking cancelled. The place is free again.');
redirect('my_bookings.php');
