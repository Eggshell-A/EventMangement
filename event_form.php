<?php
require __DIR__ . '/includes/bootstrap.php';
requireLogin();
if (!isRole('creator') && !isRole('staff')) {
    http_response_code(403);
    exit('You do not have permission to access this page.');
}
$event = null;
$errors = [];
$id = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = filter_var(postString('event_id', '0'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 4294967295]]);
    if ($id === false) {
        http_response_code(400);
        exit('Invalid identifier.');
    }
} elseif (isset($_GET['id'])) {
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 4294967295]]);
    if ($id === false) {
        http_response_code(400);
        exit('Invalid identifier.');
    }
}
if ($id) {
    $statement = $pdo->prepare('SELECT * FROM events WHERE event_id=?');
    $statement->execute([$id]);
    $event = $statement->fetch();
    if (!$event || !canEditEvent($pdo, $event)) {
        http_response_code(403);
        exit('You cannot edit this event.');
    }
}
if (!$id && !isRole('creator')) {
    http_response_code(403);
    exit('Staff can edit an assigned event, not create a new one.');
}
$back = isRole('staff') ? 'staff.php' : 'dashboard.php';
$values = $event ?: [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim(postString('title'));
    $description = trim(postString('description'));
    $start = trim(postString('start_time'));
    $location = trim(postString('location'));
    $capacityInput = postString('capacity');
    $capacity = filter_var($capacityInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 4294967295]]);
    $date = preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/D', $start) === 1
        ? DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $start) : false;
    $values = ['title' => $title, 'description' => $description, 'start_time' => $start, 'location' => $location, 'capacity' => $capacityInput];
    if ($title === '' || !mb_check_encoding($title, 'UTF-8') || mb_strlen($title, 'UTF-8') > 150) $errors[] = 'Enter a title of up to 150 characters.';
    if ($description === '' || !mb_check_encoding($description, 'UTF-8') || strlen($description) > 65535) $errors[] = 'Enter a description of up to 65,535 bytes.';
    if (!$date || $date->format('Y-m-d\TH:i') !== $start) $errors[] = 'Enter a valid date and time.';
    if ($location === '' || !mb_check_encoding($location, 'UTF-8') || mb_strlen($location, 'UTF-8') > 200) $errors[] = 'Enter a location of up to 200 characters.';
    if ($capacity === false) $errors[] = 'Capacity must be between 1 and 4,294,967,295.';
    if (!$errors) {
        try {
            if ($event) {
                withEventLock($pdo, $id, function ($lockedEvent) use ($pdo, $id, $title, $description, $date, $location, $capacity): void {
                    if (!$lockedEvent || !canEditEvent($pdo, $lockedEvent)) {
                        throw new BookingAccessDenied();
                    }
                    $info = eventCapacityInfo($pdo, $id);
                    if ($capacity < $info['bookings']) {
                        throw new CapacityBelowBookings();
                    }
                    $statement = $pdo->prepare('UPDATE events SET title=?,description=?,start_time=?,location=?,capacity=? WHERE event_id=?');
                    $statement->execute([$title, $description, $date->format('Y-m-d H:i:s'), $location, $capacity, $id]);
                });
                flash('Event updated.');
            } else {
                $statement = $pdo->prepare("INSERT INTO events (organiser_id,title,description,start_time,location,capacity,status) VALUES (?,?,?,?,?,?,'pending')");
                $statement->execute([currentUser()['user_id'], $title, $description, $date->format('Y-m-d H:i:s'), $location, $capacity]);
                flash('Event created and submitted for approval.');
            }
            redirect($back);
        } catch (CapacityBelowBookings $exception) {
            $errors[] = 'Capacity cannot be below confirmed bookings.';
        } catch (BookingAccessDenied $exception) {
            http_response_code(403);
            exit('You cannot edit this event.');
        } catch (Throwable $exception) {
            serverError($exception);
        }
    }
}
$pageTitle = $event ? 'Edit event' : 'Create event';
require __DIR__ . '/includes/header.php';
$startValue = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($values['start_time'] ?? '') : (isset($values['start_time']) ? date('Y-m-d\TH:i', strtotime($values['start_time'])) : '');
?>
<h1><?= e($pageTitle) ?></h1>
<?php if (!$event): ?><p class="notice">New events are pending until an admin approves them.</p><?php endif; ?>
<?php if ($event && isRole('staff')): ?><p class="notice">You are editing this event for the assigned creator.</p><?php endif; ?>
<form method="post">
  <?= csrfField() ?>
  <?php if ($event): ?><input type="hidden" name="event_id" value="<?= (int) $event['event_id'] ?>"><?php endif; ?>
  <?php foreach ($errors as $error): ?><p class="flash error"><?= e($error) ?></p><?php endforeach; ?>
  <label for="title">Event title</label><input id="title" name="title" required maxlength="150" value="<?= e($values['title'] ?? '') ?>">
  <label for="description">Description</label><textarea id="description" name="description" required><?= e($values['description'] ?? '') ?></textarea>
  <label for="start_time">Date and time</label><input id="start_time" type="datetime-local" name="start_time" required value="<?= e($startValue) ?>">
  <label for="location">Location</label><input id="location" name="location" required maxlength="200" value="<?= e($values['location'] ?? '') ?>">
  <label for="capacity">Capacity</label><input id="capacity" type="number" min="1" max="4294967295" name="capacity" required value="<?= e((string) ($values['capacity'] ?? '')) ?>">
  <div class="form-actions"><button>Save event</button><a class="button ghost" href="<?= e($back) ?>">Cancel</a></div>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>
