<?php
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
} elseif (!isRole('creator')) {
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
    } elseif (!isRole('creator')) {
        http_response_code(403);
        exit('Staff can edit an assigned event, not create a new one.');
    }
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $start = trim($_POST['start_time'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $capacity = filter_var($_POST['capacity'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($title === '' || strlen($title) > 150) $errors[] = 'Enter a title of up to 150 characters.';
    if ($description === '') $errors[] = 'Enter a description.';
    if (!$start || strtotime($start) === false) $errors[] = 'Enter a valid date and time.';
    if ($location === '' || strlen($location) > 200) $errors[] = 'Enter a location of up to 200 characters.';
    if ($capacity === false) $errors[] = 'Capacity must be at least 1.';
    if ($event && $capacity !== false) {
        $info = eventCapacityInfo($pdo, (int) $event['event_id']);
        if ($capacity < $info['bookings']) $errors[] = 'Capacity cannot be below confirmed bookings.';
    }
    if (!$errors) {
        if ($event) {
            $statement = $pdo->prepare('UPDATE events SET title=?, description=?, start_time=?, location=?, capacity=? WHERE event_id=?');
            $statement->execute([$title, $description, date('Y-m-d H:i:s', strtotime($start)), $location, $capacity, $event['event_id']]);
            flash('Event updated.');
            redirect(isRole('staff') ? 'staff.php' : 'dashboard.php');
        }
        $statement = $pdo->prepare("INSERT INTO events (organiser_id, title, description, start_time, location, capacity, status) VALUES (?,?,?,?,?,?,'pending')");
        $statement->execute([currentUser()['user_id'], $title, $description, date('Y-m-d H:i:s', strtotime($start)), $location, $capacity]);
        flash('Event created and submitted for approval.');
        redirect('dashboard.php');
    }
}
$pageTitle = $event ? 'Edit event' : 'Create event';
$back = isRole('staff') ? 'staff.php' : 'dashboard.php';
require __DIR__ . '/includes/header.php';
$values = $_POST ?: ($event ?: []);
?>
<h1><?= e($pageTitle) ?></h1>
<?php if (!$event): ?><p class="notice">New events are pending until an admin approves them.</p><?php endif; ?>
<?php if ($event && isRole('staff')): ?><p class="notice">You are editing this event for the assigned creator.</p><?php endif; ?>
<form method="post">
<?= csrfField() ?>
<?php if ($event): ?><input type="hidden" name="event_id" value="<?= (int) $event['event_id'] ?>"><?php endif; ?>
<?php foreach ($errors as $error): ?><p class="flash error"><?= e($error) ?></p><?php endforeach; ?>
<label for="title">Event title</label>
<input id="title" name="title" required value="<?= e($values['title'] ?? '') ?>">
<label for="description">Description</label>
<textarea id="description" name="description" required><?= e($values['description'] ?? '') ?></textarea>
<label for="start_time">Date and time</label>
<input id="start_time" type="datetime-local" name="start_time" required value="<?= e(isset($values['start_time']) ? date('Y-m-d\TH:i', strtotime($values['start_time'])) : '') ?>">
<label for="location">Location</label>
<input id="location" name="location" required value="<?= e($values['location'] ?? '') ?>">
<label for="capacity">Capacity</label>
<input id="capacity" type="number" min="1" name="capacity" required value="<?= e((string) ($values['capacity'] ?? '')) ?>">
<div class="form-actions"><button>Save event</button><a class="button ghost" href="<?= e($back) ?>">Cancel</a></div>
</form>
<?php require __DIR__ . '/includes/footer.php';
