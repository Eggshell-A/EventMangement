<?php
require __DIR__ . '/includes/bootstrap.php';
requireRole('user');
$id = (int) ($_GET['id'] ?? 0);
$statement = $pdo->prepare('SELECT e.title, e.start_time, e.location, e.capacity, (SELECT COUNT(*) FROM bookings b2 WHERE b2.event_id = e.event_id) AS booking_count FROM events e JOIN bookings b ON b.event_id = e.event_id WHERE e.event_id = ? AND b.user_id = ?');
$statement->execute([$id, currentUser()['user_id']]);
$event = $statement->fetch();
if (!$event) {
    http_response_code(404);
    exit('Booking not found.');
}
$left = max(0, (int) $event['capacity'] - (int) $event['booking_count']);
$pageTitle = 'Booking confirmed';
require __DIR__ . '/includes/header.php';
?>
<article class="success-panel">
  <p class="kicker">Booking confirmed</p>
  <h1><?= e($event['title']) ?></h1>
  <p><?= e(date('l j F Y, H:i', strtotime($event['start_time']))) ?><br><?= e($event['location']) ?></p>
  <p class="places-line"><strong><?= $left ?></strong> <?= $left === 1 ? 'place' : 'places' ?> left after your booking.</p>
  <img class="booking-qr" src="qr.php?id=<?= $id ?>" alt="QR code for the event page">
  <p class="muted">Keep this confirmation handy. Event staff can also find you by name at check-in.</p>
  <div class="form-actions">
    <a class="button" href="my_bookings.php">My bookings</a>
    <a class="button ghost" href="event.php?id=<?= $id ?>">Back to the event</a>
  </div>
</article>
<?php require __DIR__ . '/includes/footer.php';
