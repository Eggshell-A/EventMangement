<?php
require __DIR__ . '/includes/bootstrap.php';
$id = (int) ($_GET['id'] ?? 0);
$statement = $pdo->prepare('SELECT e.*, u.name AS organiser_name FROM events e JOIN users u ON u.user_id = e.organiser_id WHERE e.event_id = ?');
$statement->execute([$id]);
$event = $statement->fetch();
if (!$event || ($event['status'] !== 'approved' && !currentUserCanEditEvent($pdo, $event))) {
    http_response_code(404);
    exit('Event not found.');
}
$eventUrl = rtrim($config['base_url'], '/') . '/e.php?id=' . $id;
$pageTitle = 'Poster: ' . $event['title'];
$extraStylesheet = 'assets/poster.css';
require __DIR__ . '/includes/header.php';
?>
<article class="poster card">
  <p class="poster-brand">Fairboard presents</p>
  <h1><?= e($event['title']) ?></h1>
  <p class="poster-date"><?= e(date('l j F Y · H:i', strtotime($event['start_time']))) ?></p>
  <p class="poster-location"><?= e($event['location']) ?></p>
  <p class="poster-description"><?= nl2br(e($event['description'])) ?></p>
  <img class="poster-qr" src="qr.php?id=<?= $id ?>" alt="QR code linking to the event page">
  <p class="poster-instruction">Scan to view event details and book a place</p>
  <p class="poster-url"><?= e($eventUrl) ?></p>
  <p class="poster-organiser">Created by <?= e($event['organiser_name']) ?></p>
</article>
<p class="no-print"><button type="button" onclick="window.print()">Print A4 poster</button></p>
<?php require __DIR__ . '/includes/footer.php'; ?>
