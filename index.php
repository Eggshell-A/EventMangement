<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
$filters = normaliseCatalogFilters($_GET);
$where = catalogWhere($filters);
$statement = $pdo->prepare(eventCardSql() . ' ' . $where['sql'] . ' ORDER BY e.start_time ASC');
$statement->execute($where['params']);
$events = $statement->fetchAll();
$pageTitle = 'Explore events';
$bareMain = true;
require __DIR__ . '/includes/header.php';
?>
<section class="explore-hero">
  <div class="container hero-grid">
    <div>
      <p class="kicker">Find your next thing</p>
      <h1>Events worth showing up for.</h1>
      <p>Discover campus societies, practical workshops, performances, and community gatherings — with clear places left before you commit.</p>
    </div>
    <form class="catalog-search" method="get" action="index.php">
      <label for="q">Search events</label>
      <input id="q" type="search" name="q" maxlength="80" placeholder="Title, place, or keyword" value="<?= e($filters['q']) ?>">
      <div class="filter-row">
        <div>
          <label for="date">When</label>
          <select id="date" name="date">
            <option value="">Any upcoming date</option>
            <option value="today" <?= $filters['date'] === 'today' ? 'selected' : '' ?>>Today</option>
            <option value="weekend" <?= $filters['date'] === 'weekend' ? 'selected' : '' ?>>This weekend</option>
            <option value="week" <?= $filters['date'] === 'week' ? 'selected' : '' ?>>Next 7 days</option>
          </select>
        </div>
        <div>
          <label for="category">Category</label>
          <select id="category" name="category">
            <option value="">All categories</option>
            <?php foreach (eventCategories() as $code => $label): ?>
              <option value="<?= e($code) ?>" <?= $filters['category'] === $code ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <label class="check-row"><input type="checkbox" name="low_data" value="1" <?= $filters['low_data'] ? 'checked' : '' ?>> Low-data list (no poster images)</label>
      <div class="form-actions"><button type="submit">Find events</button><a class="button ghost" href="index.php">Clear</a></div>
    </form>
  </div>
</section>

<div class="container event-catalog">
  <div class="catalog-heading">
    <div>
      <p class="kicker"><?= count($events) ?> upcoming</p>
      <h2><?= $filters['q'] !== '' ? 'Results for “' . e($filters['q']) . '”' : 'Upcoming events' ?></h2>
    </div>
  </div>
  <?php if (!$events): ?>
    <div class="empty-state"><h3>No events match those filters.</h3><p>Try a different date, category, or a broader keyword.</p><a class="button ghost" href="index.php">See all upcoming events</a></div>
  <?php else: ?>
    <ul class="poster-grid<?= $filters['low_data'] ? ' low-data' : '' ?>">
      <?php foreach ($events as $event): $left = placesLeft($event); ?>
        <li>
          <a class="poster-card" href="event.php?id=<?= (int) $event['event_id'] ?>">
            <span class="event-date-ticket"><strong><?= e(date('d', strtotime($event['start_time']))) ?></strong><span><?= e(strtoupper(date('M', strtotime($event['start_time'])))) ?></span><small><?= e(date('D', strtotime($event['start_time']))) ?></small></span>
            <span class="poster-meta">
              <span class="category-label"><?= e(eventCategories()[$event['category']] ?? eventCategories()['other']) ?></span>
              <strong><?= e($event['title']) ?></strong>
              <span><?= e(organisationName($event)) ?></span>
              <span class="card-meta"><?= e(date('H:i', strtotime($event['start_time']))) ?><span aria-hidden="true"> / </span><?= e($event['location']) ?></span>
              <span class="places<?= $left === 0 ? ' full' : '' ?>"><?= $left === 0 ? ((int) $event['waiting_count'] . ' waiting') : $left . ' places left' ?></span>
            </span>
            <?php if (!$filters['low_data'] && !empty($event['poster_path'])): ?><img class="event-card-thumb" src="<?= e($event['poster_path']) ?>" alt=""><?php endif; ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php';
