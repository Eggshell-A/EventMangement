<?php
require __DIR__ . '/includes/bootstrap.php';
requireRole('user');
$statement = $pdo->prepare('SELECT b.booking_id, b.checked_in, b.event_id, e.title, e.start_time, e.location FROM bookings b JOIN events e ON e.event_id = b.event_id WHERE b.user_id = ? ORDER BY e.start_time ASC');
$statement->execute([currentUser()['user_id']]);
$bookings = $statement->fetchAll();
$statement = $pdo->prepare('SELECT w.entry_id, w.event_id, w.joined_at, e.title, e.start_time, e.location FROM waiting_list w JOIN events e ON e.event_id = w.event_id WHERE w.user_id = ? ORDER BY e.start_time ASC');
$statement->execute([currentUser()['user_id']]);
$waiting = $statement->fetchAll();
$pageTitle = 'My bookings';
require __DIR__ . '/includes/header.php';
?>
<h1>My bookings</h1>
<?php if (!$bookings && !$waiting): ?>
  <p class="notice">You have no bookings or waiting-list places yet.</p>
<?php else: ?>
  <?php if ($bookings): ?>
  <h2>Confirmed</h2>
  <ul class="booking-list">
    <?php foreach ($bookings as $booking): ?>
      <li class="booking-card">
        <div>
          <a href="event.php?id=<?= (int) $booking['event_id'] ?>"><strong><?= e($booking['title']) ?></strong></a>
          <p class="meta"><?= e(date('D j M Y, H:i', strtotime($booking['start_time']))) ?><br><?= e($booking['location']) ?></p>
          <p><?= $booking['checked_in'] ? 'Checked in' : 'Confirmed' ?></p>
        </div>
        <form method="post" action="cancel_booking.php">
          <?= csrfField() ?>
          <input type="hidden" name="booking_id" value="<?= (int) $booking['booking_id'] ?>">
          <button class="danger" type="submit">Cancel booking</button>
        </form>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
  <?php if ($waiting): ?>
    <h2>Waiting list</h2>
    <ul class="booking-list">
      <?php foreach ($waiting as $entry): ?>
        <li class="booking-card">
          <div>
            <a href="event.php?id=<?= (int) $entry['event_id'] ?>"><strong><?= e($entry['title']) ?></strong></a>
            <p class="meta"><?= e(date('D j M Y, H:i', strtotime($entry['start_time']))) ?><br><?= e($entry['location']) ?></p>
            <p>Waiting since <?= e(date('j M Y, H:i', strtotime($entry['joined_at']))) ?></p>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php';
