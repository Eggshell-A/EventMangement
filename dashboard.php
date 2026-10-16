<?php
require __DIR__ . '/includes/bootstrap.php';
requireRole('creator');
$statement = $pdo->prepare('SELECT e.*, COUNT(DISTINCT b.booking_id) booking_count, COUNT(DISTINCT w.entry_id) waiting_count FROM events e LEFT JOIN bookings b ON b.event_id = e.event_id LEFT JOIN waiting_list w ON w.event_id = e.event_id WHERE e.organiser_id = ? GROUP BY e.event_id ORDER BY e.start_time DESC');
$statement->execute([currentUser()['user_id']]);
$events = $statement->fetchAll();
$pageTitle = 'My events';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <h1>My events</h1>
  <a class="button" href="event_form.php">Create event</a>
</div>
<?php if (!$events): ?>
  <p class="notice">You have not created any events.</p>
<?php else: ?>
  <ul class="mine-list">
    <?php foreach ($events as $event): $remaining = max(0, (int) $event['capacity'] - (int) $event['booking_count']); ?>
      <li class="mine-card">
        <div>
          <strong><?= e($event['title']) ?></strong>
          <p class="meta"><?= e(date('D j M Y, H:i', strtotime($event['start_time']))) ?> · <?= e($event['location']) ?></p>
          <p><span class="status <?= e($event['status']) ?>"><?= e($event['status']) ?></span> · <?= (int) $event['booking_count'] ?> / <?= (int) $event['capacity'] ?> booked · <?= $remaining ?> left · <?= (int) $event['waiting_count'] ?> waiting</p>
        </div>
        <div class="event-actions">
          <a class="action-btn edit" href="event_form.php?id=<?= (int) $event['event_id'] ?>">Edit</a>
          <a class="action-btn attendees" href="attendees.php?event_id=<?= (int) $event['event_id'] ?>">Attendees</a>
          <?php if ($event['status'] === 'approved'): ?>
            <a class="action-btn print" href="poster.php?id=<?= (int) $event['event_id'] ?>">Print poster</a>
          <?php else: ?>
            <span class="action-btn print is-off">Print poster</span>
          <?php endif; ?>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php';
