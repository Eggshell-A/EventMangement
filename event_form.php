<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
if (!isLoggedIn()) {
    requireLogin();
}
if (!isRole('creator') && !isRole('staff')) {
    http_response_code(403);
    exit('You do not have permission to access this page.');
}

$event = null;
$errors = [];
if (isset($_GET['id'])) {
    $statement = $pdo->prepare('SELECT * FROM events WHERE event_id = ?');
    $statement->execute([(int) $_GET['id']]);
    $event = $statement->fetch();
    if (!$event || !currentUserCanEditEvent($pdo, $event)) {
        http_response_code(403);
        exit('You cannot edit this event.');
    }
} elseif (!canCreateEvent(currentUser())) {
    http_response_code(403);
    exit('Staff can edit an assigned event, not create a new one.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();
    $id = (int) ($_POST['event_id'] ?? 0);
    if ($id) {
        $statement = $pdo->prepare('SELECT * FROM events WHERE event_id = ?');
        $statement->execute([$id]);
        $event = $statement->fetch();
        if (!$event || !currentUserCanEditEvent($pdo, $event)) {
            http_response_code(403);
            exit('You cannot edit this event.');
        }
    } elseif (!canCreateEvent(currentUser())) {
        http_response_code(403);
        exit('Staff can edit an assigned event, not create a new one.');
    }

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $start = trim($_POST['start_time'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $capacity = filter_var($_POST['capacity'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $category = (string) ($_POST['category'] ?? 'other');
    $socialUrl = trim($_POST['social_url'] ?? '');
    $intent = (string) ($_POST['intent'] ?? 'save');

    if ($title === '' || strlen($title) > 150) $errors[] = 'Enter a title of up to 150 characters.';
    if ($description === '') $errors[] = 'Enter a description.';
    if (!$start || strtotime($start) === false) $errors[] = 'Enter a valid date and time.';
    if ($location === '' || strlen($location) > 200) $errors[] = 'Enter a location of up to 200 characters.';
    if ($capacity === false) $errors[] = 'Capacity must be at least 1.';
    if (!array_key_exists($category, eventCategories())) $errors[] = 'Choose a valid category.';
    if (!validHttpUrl($socialUrl)) $errors[] = 'Social link must start with http:// or https://.';
    if ($event && $capacity !== false) {
        $info = eventCapacityInfo($pdo, (int) $event['event_id']);
        if ($capacity < $info['bookings']) $errors[] = 'Capacity cannot be below confirmed bookings.';
    }

    $poster = ['filename' => null, 'error' => null];
    if (!$errors) {
        $poster = saveEventPoster($_FILES['poster'] ?? ['error' => UPLOAD_ERR_NO_FILE], __DIR__ . '/uploads');
        if ($poster['error'] !== null) $errors[] = $poster['error'];
    }

    if (!$errors) {
        $status = eventStatusAfterSave((string) currentUser()['role'], $event['status'] ?? null, $intent, $event === null);
        $posterPath = $poster['filename'] !== null ? 'uploads/' . $poster['filename'] : ($event['poster_path'] ?? null);
        $dateTime = date('Y-m-d H:i:s', strtotime($start));
        if ($event) {
            $statement = $pdo->prepare('UPDATE events SET title = ?, description = ?, start_time = ?, location = ?, capacity = ?, category = ?, poster_path = ?, social_url = ?, status = ? WHERE event_id = ?');
            $statement->execute([$title, $description, $dateTime, $location, $capacity, $category, $posterPath, $socialUrl ?: null, $status, $event['event_id']]);
            flash($status === 'pending' ? 'Event updated and submitted for review.' : ($status === 'draft' ? 'Draft saved.' : 'Event updated.'));
            redirect(isRole('staff') ? 'staff.php' : 'dashboard.php');
        }
        $statement = $pdo->prepare('INSERT INTO events (organiser_id, title, description, start_time, location, capacity, original_capacity, category, poster_path, social_url, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $statement->execute([(int) currentUser()['user_id'], $title, $description, $dateTime, $location, $capacity, $capacity, $category, $posterPath, $socialUrl ?: null, $status]);
        flash($status === 'pending' ? 'Event created and submitted for review.' : 'Draft saved.');
        redirect('dashboard.php');
    }
}

$pageTitle = $event ? 'Edit event' : 'Create event';
$back = isRole('staff') ? 'staff.php' : 'dashboard.php';
$values = $_POST ?: ($event ?: ['category' => 'other']);
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <div>
    <p class="kicker"><?= $event ? 'Event workspace' : 'New event' ?></p>
    <h1><?= e($pageTitle) ?></h1>
    <p class="muted"><?= isRole('staff') ? 'You are helping the assigned Creator. Review status stays under Creator and Admin control.' : 'Save a private draft or submit it to the Admin review queue.' ?></p>
  </div>
</div>
<form method="post" enctype="multipart/form-data" class="event-editor">
  <?= csrfField() ?>
  <?php if ($event): ?><input type="hidden" name="event_id" value="<?= (int) $event['event_id'] ?>"><?php endif; ?>
  <?php foreach ($errors as $error): ?><p class="flash error"><?= e($error) ?></p><?php endforeach; ?>

  <fieldset>
    <legend>1. Basics</legend>
    <label for="title">Event title</label>
    <input id="title" name="title" maxlength="150" required value="<?= e($values['title'] ?? '') ?>">
    <label for="category">Category</label>
    <select id="category" name="category" required>
      <?php foreach (eventCategories() as $code => $label): ?>
        <option value="<?= e($code) ?>" <?= ($values['category'] ?? 'other') === $code ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <label for="description">Description</label>
    <textarea id="description" name="description" required><?= e($values['description'] ?? '') ?></textarea>
  </fieldset>

  <fieldset>
    <legend>2. Date & place</legend>
    <label for="start_time">Date and time</label>
    <input id="start_time" type="datetime-local" name="start_time" required value="<?= e(isset($values['start_time']) && $values['start_time'] !== '' ? date('Y-m-d\TH:i', strtotime($values['start_time'])) : '') ?>">
    <label for="location">Location</label>
    <input id="location" name="location" maxlength="200" required value="<?= e($values['location'] ?? '') ?>">
  </fieldset>

  <fieldset>
    <legend>3. Capacity</legend>
    <label for="capacity">Total places</label>
    <input id="capacity" type="number" min="1" name="capacity" required value="<?= e((string) ($values['capacity'] ?? '')) ?>">
    <p class="muted">Increasing capacity does not reset the event’s waiting-list refill allowance.</p>
  </fieldset>

  <fieldset>
    <legend>4. Promotion</legend>
    <label for="poster">Poster image</label>
    <input id="poster" type="file" name="poster" accept="image/jpeg,image/png,image/webp">
    <p class="muted">Optional JPEG, PNG, or WebP up to 5 MB.</p>
    <?php if (!empty($event['poster_path'])): ?><p><a href="<?= e($event['poster_path']) ?>">View current poster</a></p><?php endif; ?>
    <label for="social_url">Instagram, Facebook, or event link</label>
    <input id="social_url" type="url" name="social_url" placeholder="https://" value="<?= e($values['social_url'] ?? '') ?>">
  </fieldset>

  <fieldset>
    <legend>5. Review</legend>
    <p>Check the title, time, place, capacity, and public description before submitting.</p>
    <div class="form-actions">
      <?php if (isRole('creator')): ?>
        <button type="submit" name="intent" value="save_draft" class="button ghost">Save draft</button>
        <button type="submit" name="intent" value="submit">Submit for review</button>
      <?php else: ?>
        <button type="submit" name="intent" value="save">Save assigned event</button>
      <?php endif; ?>
      <a class="button ghost" href="<?= e($back) ?>">Cancel</a>
    </div>
  </fieldset>
</form>
<?php require __DIR__ . '/includes/footer.php';
