<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
requireRole('staff');
$statement = $pdo->prepare('SELECT e.event_id, e.title, e.start_time, e.location, e.status, u.name AS creator_name FROM events e JOIN staff_assignments s ON s.creator_user_id = e.organiser_id JOIN users u ON u.user_id = e.organiser_id WHERE s.staff_user_id = ? ORDER BY e.start_time DESC');
$statement->execute([currentUser()['user_id']]);
$events = $statement->fetchAll();
$pageTitle = 'Staff';
require __DIR__ . '/includes/header.php';
?>
<p class="eyebrow">YOUR TEAM, YOUR EVENTS</p><h1>Assigned events.</h1><p><a class="button" href="scan.php">Open scanner ↗</a></p>
<?php if (!$events): ?><p class="notice">No events are assigned to you.</p><?php else: ?>
<div class="table-wrap"><table><thead><tr><th>Event</th><th>Creator</th><th>Status</th><th class="no-print">Assist</th></tr></thead><tbody>
<?php foreach ($events as $event): ?>
<tr><td><strong><?= e($event['title']) ?></strong><br><span class="muted"><?= e(date('j M Y, H:i', strtotime($event['start_time']))) ?> · <?= e($event['location']) ?></span></td><td><?= e($event['creator_name']) ?></td><td><span class="status <?= e($event['status']) ?>"><?= e($event['status']) ?></span></td><td class="no-print"><a class="action-btn edit" href="event_form.php?id=<?= (int) $event['event_id'] ?>">Edit</a> <a class="action-btn" href="attendees.php?event_id=<?= (int) $event['event_id'] ?>">Guests</a></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; require __DIR__ . '/includes/footer.php';
