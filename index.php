<?php
require __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'Events';
$statement = $pdo->query(eventCardSql() . " WHERE e.status = 'approved' AND e.start_time >= NOW() ORDER BY e.start_time ASC");
$events = $statement->fetchAll();
$featured = array_slice($events, 0, 3);
$bareMain = true;
require __DIR__ . '/includes/header.php';
?>
<?php if ($featured): ?>
<section class="banner" data-banner aria-roledescription="carousel" aria-label="Featured events">
  <div class="banner-frame">
    <?php foreach ($featured as $index => $event): ?>
      <a class="banner-slide<?= $index === 0 ? ' is-on' : '' ?>" href="event.php?id=<?= (int) $event['event_id'] ?>">
        <span class="poster tone-<?= posterTone($event['title']) ?>" aria-hidden="true"><?= e(mb_substr($event['title'], 0, 1)) ?></span>
        <span class="banner-copy">
          <span class="kicker">Featured</span>
          <strong><?= e($event['title']) ?></strong>
          <span><?= e(date('D j M Y, H:i', strtotime($event['start_time']))) ?> · <?= e($event['location']) ?></span>
        </span>
      </a>
    <?php endforeach; ?>
    <?php if (count($featured) > 1): ?>
      <button class="banner-arrow prev" type="button" data-banner-prev aria-label="Previous event">‹</button>
      <button class="banner-arrow next" type="button" data-banner-next aria-label="Next event">›</button>
      <div class="banner-dots">
        <?php foreach ($featured as $index => $event): ?>
          <button class="banner-dot<?= $index === 0 ? ' is-on' : '' ?>" type="button" data-banner-dot="<?= $index ?>" aria-label="Show <?= e($event['title']) ?>"></button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
<script src="assets/home.js"></script>
<?php endif; ?>

<div class="container">
<section class="list-head">
  <h1>Events</h1>
  <p>Upcoming events from societies and community groups. Open one to see the details.</p>
</section>
<?php if (!$events): ?>
  <p class="notice">There are no upcoming events yet.</p>
<?php else: ?>
  <ul class="poster-grid">
    <?php foreach ($events as $event): $left = placesLeft($event); ?>
      <li>
        <a class="poster-card" href="event.php?id=<?= (int) $event['event_id'] ?>">
          <span class="poster tone-<?= posterTone($event['title']) ?>" aria-hidden="true"><?= e(mb_substr($event['title'], 0, 1)) ?></span>
          <span class="poster-meta">
            <strong><?= e($event['title']) ?></strong>
            <span><?= e(organisationName($event)) ?></span>
            <span><?= e(date('D j M Y, H:i', strtotime($event['start_time']))) ?></span>
            <span><?= e($event['location']) ?></span>
            <span class="places<?= $left === 0 ? ' full' : '' ?>"><?= $left === 0 ? 'Full' : $left . ' left' ?></span>
          </span>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php';
