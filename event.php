<?php
require __DIR__ . '/includes/bootstrap.php';
$id = (int) ($_GET['id'] ?? 0);
$statement = $pdo->prepare(eventCardSql() . ' WHERE e.event_id = ?');
$statement->execute([$id]);
$event = $statement->fetch();
if (!$event || !eventIsAccessible($pdo, $event)) {
    http_response_code(404);
    exit('Event not found.');
}
$left = placesLeft($event);
$fee = planFeeLabel($event['plan_code'] ?? null);
$booking = null;
$waiting = null;
if (isLoggedIn()) {
    $statement = $pdo->prepare('SELECT booking_id, checked_in FROM bookings WHERE event_id = ? AND user_id = ?');
    $statement->execute([$id, currentUser()['user_id']]);
    $booking = $statement->fetch() ?: null;
    $statement = $pdo->prepare('SELECT entry_id, joined_at FROM waiting_list WHERE event_id = ? AND user_id = ?');
    $statement->execute([$id, currentUser()['user_id']]);
    $waiting = $statement->fetch() ?: null;
}
$state = bookingState($event, $booking, $waiting);
$next = 'event.php?id=' . $id;
$pageTitle = $event['title'];
require __DIR__ . '/includes/header.php';
?>
<article class="detail">
  <?php if (!empty($event['poster_path'])): ?>
    <img class="detail-poster event-image" src="<?= e($event['poster_path']) ?>" alt="<?= e($event['title']) ?> poster">
  <?php else: ?>
    <div class="poster tone-<?= posterTone($event['title']) ?> detail-poster" aria-hidden="true"><?= e(mb_substr($event['title'], 0, 1)) ?></div>
  <?php endif; ?>
  <div class="detail-copy">
    <p class="kicker"><?= e(eventCategories()[$event['category']] ?? eventCategories()['other']) ?> · <?= e(organisationName($event)) ?></p>
    <h1><?= e($event['title']) ?></h1>
    <p class="meta"><?= e(date('l j F Y, H:i', strtotime($event['start_time']))) ?><br><?= e($event['location']) ?></p>
    <?php if ($fee !== ''): ?><p class="fee"><?= e($fee) ?></p><?php endif; ?>
    <p><?= nl2br(e($event['description'])) ?></p>
    <?php if (!empty($event['social_url'])): ?><p><a href="<?= e($event['social_url']) ?>" rel="noopener noreferrer" target="_blank">Open the organiser’s event link</a></p><?php endif; ?>
    <p class="places-line"><strong><?= $left ?></strong> <?= $left === 1 ? 'place' : 'places' ?> left of <?= (int) $event['capacity'] ?><?php if ((int) $event['waiting_count'] > 0): ?> · <?= (int) $event['waiting_count'] ?> waiting<?php endif; ?></p>
    <?php if ($event['status'] === 'approved'): ?>
      <div class="join-box no-print">
        <?php if (!isLoggedIn()): ?>
          <a class="button" href="login.php?next=<?= e($next) ?>">Join</a>
          <p class="muted">You need to log in before joining.</p>
        <?php elseif (isRole('user')): ?>
          <?php if ($state === 'booked'): ?>
            <p class="notice">You have a place. <a href="booked.php?id=<?= $id ?>">View your booking</a></p>
          <?php elseif ($state === 'waiting'): ?>
            <p class="notice">You are on Fairboard’s waiting list. Your place in line is kept by join time.</p>
          <?php elseif ($state === 'available'): ?>
            <form method="post" action="booking_action.php">
              <?= csrfField() ?>
              <input type="hidden" name="event_id" value="<?= $id ?>">
              <input type="hidden" name="action" value="book">
              <button>Join</button>
            </form>
          <?php elseif ($state === 'waitlist'): ?>
            <form method="post" action="booking_action.php">
              <?= csrfField() ?>
              <input type="hidden" name="event_id" value="<?= $id ?>">
              <input type="hidden" name="action" value="wait">
              <button>Join waiting list</button>
            </form>
          <?php else: ?>
            <p class="notice">Booking is not available for this event.</p>
          <?php endif; ?>
        <?php else: ?>
          <p class="notice">Log in with a user account to join this event.</p>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <p class="status <?= e($event['status']) ?>"><?= e($event['status']) ?></p>
    <?php endif; ?>
    <?php if (currentUserCanManageAttendees($pdo, $event)): ?>
      <p class="no-print"><a class="button" href="attendees.php?event_id=<?= $id ?>">View attendee list</a></p>
    <?php endif; ?>
  </div>
</article>
<?php require __DIR__ . '/includes/footer.php';
