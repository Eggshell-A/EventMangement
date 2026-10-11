<?php require __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'Upcoming events';
$statement = $pdo->prepare("SELECT e.*, u.name AS organiser_name, COUNT(DISTINCT b.booking_id) AS booking_count, COUNT(DISTINCT w.entry_id) AS waiting_count FROM events e JOIN users u ON u.user_id=e.organiser_id LEFT JOIN bookings b ON b.event_id=e.event_id LEFT JOIN waiting_list w ON w.event_id=e.event_id WHERE e.status='approved' AND e.start_time >= NOW() GROUP BY e.event_id ORDER BY e.start_time ASC");
$statement->execute();
$events = $statement->fetchAll();
require __DIR__ . '/includes/header.php'; ?>
<h1>Upcoming events</h1><p class="meta">Discover local events run by societies and community groups.</p>
<?php if (!$events): ?><p class="notice">There are no approved upcoming events yet.</p><?php else: ?><section class="event-grid" aria-label="Events"><?php foreach ($events as $event): $remaining=max(0,(int)$event['capacity']-(int)$event['booking_count']); ?>
<article class="card event-card"><h2><?= e($event['title']) ?></h2><p class="meta"><?= e(date('D j M Y, H:i', strtotime($event['start_time']))) ?><br><?= e($event['location']) ?></p><p><?= $remaining ?> place<?= $remaining === 1 ? '' : 's' ?> remaining of <?= (int)$event['capacity'] ?></p><?php if ($remaining === 0): ?><p class="status rejected">Full · <?= (int)$event['waiting_count'] ?> in queue</p><?php endif; ?><a class="button" href="event.php?id=<?= (int)$event['event_id'] ?>">View event</a></article>
<?php endforeach; ?></section><?php endif; require __DIR__ . '/includes/footer.php';
