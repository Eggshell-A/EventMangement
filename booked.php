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
<article class="success-panel ticket-panel">
  <div class="ticket-main"><p class="kicker">Place confirmed</p><h1><?= e($event['title']) ?></h1><dl class="event-facts"><div><dt>When</dt><dd><?= e(date('l j F Y, H:i', strtotime($event['start_time']))) ?></dd></div><div><dt>Where</dt><dd><?= e($event['location']) ?></dd></div><div><dt>Availability</dt><dd><?= $left ?> <?= $left === 1 ? 'place' : 'places' ?> left after your reservation</dd></div></dl><p class="muted">Your place also appears under My activity. Cancel there if your plans change so someone else can attend.</p><div class="form-actions"><a class="button" href="my_bookings.php">Open My activity</a><a class="button ghost" href="event.php?id=<?= $id ?>">Event details</a></div></div>
  <div class="ticket-stub"><img class="booking-qr" src="qr.php?id=<?= $id ?>" alt="QR code opening the event page"><strong>Event page</strong><span>Keep the details handy</span></div>
</article>
<?php require __DIR__ . '/includes/footer.php';
