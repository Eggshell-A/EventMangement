<?php
declare(strict_types=1);

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
<article class="event-detail">
  <div class="event-visual">
    <?php if (!empty($event['poster_path'])): ?>
      <img class="detail-poster event-image" src="<?= e($event['poster_path']) ?>" alt="<?= e($event['title']) ?> poster">
    <?php else: ?>
      <div class="poster tone-<?= posterTone($event['title']) ?> detail-poster" aria-hidden="true"><span><?= e(mb_substr($event['title'], 0, 1)) ?></span><small><?= e(date('d M', strtotime($event['start_time']))) ?></small></div>
    <?php endif; ?>
  </div>
  <div class="detail-copy">
    <p class="category-label"><?= e(eventCategories()[$event['category']] ?? eventCategories()['other']) ?></p>
    <h1><?= e($event['title']) ?></h1>
    <p class="organiser-line">Hosted by <strong><?= e(organisationName($event)) ?></strong></p>
    <?php if ($event['status'] !== 'approved'): ?><p><span class="status-tag <?= e($event['status']) ?>"><?= e(ucfirst($event['status'])) ?></span> <span class="muted">Only authorised team members can preview this event.</span></p><?php endif; ?>
    <dl class="event-facts">
      <div><dt>Date</dt><dd><?= e(date('l j F Y', strtotime($event['start_time']))) ?></dd></div>
      <div><dt>Time</dt><dd><?= e(date('H:i', strtotime($event['start_time']))) ?></dd></div>
      <div><dt>Place</dt><dd><?= e($event['location']) ?></dd></div>
    </dl>
    <?php if ($fee !== ''): ?><p class="fee"><?= e($fee) ?></p><?php endif; ?>
    <section class="event-description"><h2>About this event</h2><p><?= nl2br(e($event['description'])) ?></p></section>
    <?php if (!empty($event['social_url'])): ?><p><a class="text-link" href="<?= e($event['social_url']) ?>" rel="noopener noreferrer" target="_blank">Open the organiser’s event link</a></p><?php endif; ?>
    <?php if (currentUserCanManageAttendees($pdo, $event)): ?><div class="team-actions no-print"><a class="button ghost" href="event_form.php?id=<?= $id ?>">Edit event</a><a class="button ghost" href="attendees.php?event_id=<?= $id ?>">Attendees & check-in</a><a class="button ghost" href="poster.php?id=<?= $id ?>">Promotion kit</a></div><?php endif; ?>
  </div>
  <aside class="reserve-card no-print" aria-label="Reservation status">
    <p class="reserve-count"><strong><?= $left ?></strong><span><?= $left === 1 ? 'place left' : 'places left' ?></span></p>
    <div class="capacity-track" aria-label="<?= (int) $event['booking_count'] ?> of <?= (int) $event['capacity'] ?> places reserved"><span style="width: <?= min(100, (int) round(((int) $event['booking_count'] / max(1, (int) $event['capacity'])) * 100)) ?>%"></span></div>
    <p class="muted"><?= (int) $event['booking_count'] ?> of <?= (int) $event['capacity'] ?> reserved<?php if ((int) $event['waiting_count'] > 0): ?><br><?= (int) $event['waiting_count'] ?> waiting<?php endif; ?></p>
    <?php if ($event['status'] !== 'approved'): ?>
      <p class="notice">Reservations open after Admin approval.</p>
    <?php elseif (!isLoggedIn()): ?>
      <a class="button primary-wide" href="login.php?next=<?= e($next) ?>">Log in to reserve</a><p class="muted">You will return to this event after login.</p>
    <?php elseif (!isRole('user')): ?>
      <p class="notice">Use a User account to reserve an event.</p>
    <?php elseif ($state === 'booked'): ?>
      <span class="status-tag confirmed">Place confirmed</span><a class="button ghost primary-wide" href="booked.php?id=<?= $id ?>">View confirmation</a>
    <?php elseif ($state === 'waiting'): ?>
      <span class="status-tag waiting">On the waiting list</span><p class="muted">Your position is kept by join time.</p>
    <?php elseif ($state === 'available'): ?>
      <form method="post" action="booking_action.php" class="compact-form"><?= csrfField() ?><input type="hidden" name="event_id" value="<?= $id ?>"><input type="hidden" name="action" value="book"><button class="primary-wide">Reserve my place</button></form>
    <?php elseif ($state === 'waitlist'): ?>
      <p><strong>This event is full.</strong></p><form method="post" action="booking_action.php" class="compact-form"><?= csrfField() ?><input type="hidden" name="event_id" value="<?= $id ?>"><input type="hidden" name="action" value="wait"><button class="primary-wide">Join Fairboard’s waiting list</button></form>
    <?php else: ?>
      <p class="notice">Reservations are closed.</p>
    <?php endif; ?>
  </aside>
</article>
<?php require __DIR__ . '/includes/footer.php';
