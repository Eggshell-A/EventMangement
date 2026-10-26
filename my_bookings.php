<?php
require __DIR__ . '/includes/bootstrap.php';
requireRole('user');
$statement = $pdo->prepare('SELECT b.booking_id, b.checked_in, b.event_id, e.title, e.start_time, e.location FROM bookings b JOIN events e ON e.event_id = b.event_id WHERE b.user_id = ? ORDER BY e.start_time ASC');
$statement->execute([currentUser()['user_id']]);
$bookings = $statement->fetchAll();
$statement = $pdo->prepare('SELECT w.entry_id, w.event_id, w.joined_at, e.title, e.start_time, e.location FROM waiting_list w JOIN events e ON e.event_id = w.event_id WHERE w.user_id = ? ORDER BY e.start_time ASC');
$statement->execute([currentUser()['user_id']]);
$waiting = $statement->fetchAll();
$now = time();
$upcoming = array_values(array_filter($bookings, fn(array $booking): bool => strtotime($booking['start_time']) >= $now));
$past = array_values(array_filter($bookings, fn(array $booking): bool => strtotime($booking['start_time']) < $now));
$activeWaiting = array_values(array_filter($waiting, fn(array $entry): bool => strtotime($entry['start_time']) >= $now));
$pageTitle = 'My bookings';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head"><div><p class="kicker">Your plans</p><h1>My activity</h1><p class="muted">Confirmed places, waiting-list positions, and past events in one place.</p></div><a class="button ghost" href="index.php">Explore events</a></div>
<?php if (!$upcoming && !$activeWaiting && !$past): ?>
  <div class="empty-state"><h2>No activity yet.</h2><p>Browse upcoming events and reserve your first place.</p><a class="button" href="index.php">Find an event</a></div>
<?php else: ?>
  <?php if ($upcoming): ?>
  <div class="section-heading"><h2>Upcoming</h2><span><?= count($upcoming) ?> confirmed</span></div>
  <ul class="booking-list">
    <?php foreach ($upcoming as $booking): ?>
      <li class="booking-card">
        <div class="mini-date"><strong><?= e(date('d', strtotime($booking['start_time']))) ?></strong><span><?= e(strtoupper(date('M', strtotime($booking['start_time'])))) ?></span></div>
        <div class="booking-copy">
          <a href="event.php?id=<?= (int) $booking['event_id'] ?>"><strong><?= e($booking['title']) ?></strong></a>
          <p class="meta"><?= e(date('D j M Y, H:i', strtotime($booking['start_time']))) ?><span aria-hidden="true"> / </span><?= e($booking['location']) ?></p>
          <span class="status-tag confirmed"><?= $booking['checked_in'] ? 'Checked in' : 'Confirmed' ?></span>
        </div>
        <form method="post" action="cancel_booking.php" class="compact-form">
          <?= csrfField() ?>
          <input type="hidden" name="booking_id" value="<?= (int) $booking['booking_id'] ?>">
          <button class="danger" type="submit" data-confirm="Cancel your place at <?= e($booking['title']) ?>?">Cancel place</button>
        </form>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
  <?php if ($activeWaiting): ?>
    <div class="section-heading"><h2>Waiting list</h2><span><?= count($activeWaiting) ?> in line</span></div>
    <ul class="booking-list">
      <?php foreach ($activeWaiting as $entry): ?>
        <li class="booking-card">
          <div class="mini-date waiting"><strong><?= e(date('d', strtotime($entry['start_time']))) ?></strong><span><?= e(strtoupper(date('M', strtotime($entry['start_time'])))) ?></span></div>
          <div class="booking-copy">
            <a href="event.php?id=<?= (int) $entry['event_id'] ?>"><strong><?= e($entry['title']) ?></strong></a>
            <p class="meta"><?= e(date('D j M Y, H:i', strtotime($entry['start_time']))) ?><span aria-hidden="true"> / </span><?= e($entry['location']) ?></p>
            <span class="status-tag waiting">Waiting since <?= e(date('j M, H:i', strtotime($entry['joined_at']))) ?></span>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
  <?php if ($past): ?>
    <div class="section-heading"><h2>Past</h2><span><?= count($past) ?> event<?= count($past) === 1 ? '' : 's' ?></span></div>
    <ul class="booking-list past-list">
      <?php foreach ($past as $booking): ?>
        <li class="booking-card"><div class="mini-date past"><strong><?= e(date('d', strtotime($booking['start_time']))) ?></strong><span><?= e(strtoupper(date('M', strtotime($booking['start_time'])))) ?></span></div><div class="booking-copy"><a href="event.php?id=<?= (int) $booking['event_id'] ?>"><strong><?= e($booking['title']) ?></strong></a><p class="meta"><?= e($booking['location']) ?></p><span class="status-tag past"><?= $booking['checked_in'] ? 'Checked in' : 'Event ended' ?></span></div></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php';
