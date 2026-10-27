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
    $category = postString('category');
    $artwork = postString('artwork');
    $intent=postString('intent','submit');
    $socialUrl=trim(postString('social_url'));
    if(!in_array($intent,['submit','save_draft','save'],true))$errors[]='Choose a valid save action.';
    if($intent==='save_draft'&&!isRole('creator'))$errors[]='Only the creator can save a draft.';
    if(!validEventUrl($socialUrl))$errors[]='Event link must be an HTTP or HTTPS URL of up to 500 characters.';
    $description = trim(postString('description'));
    $start = trim(postString('start_time'));
    $location = trim(postString('location'));
    $capacityInput = postString('capacity');
    $capacity = filter_var($capacityInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100000]]);
    $date = preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/D', $start) === 1
        ? DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $start) : false;
    $values = ['title' => $title, 'description' => $description, 'start_time' => $start, 'location' => $location, 'capacity' => $capacityInput];
    if (!in_array($category,categories(),true) || !in_array($artwork,artworkChoices(),true)) $errors[]='Choose a valid category and artwork.';
    $values['social_url']=$socialUrl; $values['category']=$category; $values['artwork']=$artwork;
    if ($title === '' || !mb_check_encoding($title, 'UTF-8') || mb_strlen($title, 'UTF-8') > 150) $errors[] = 'Enter a title of up to 150 characters.';
    if ($description === '' || !mb_check_encoding($description, 'UTF-8') || strlen($description) > 65535) $errors[] = 'Enter a description of up to 65,535 bytes.';
    if (!$date || $date->format('Y-m-d\TH:i') !== $start || $date->getTimestamp() <= time()) $errors[] = 'Choose a valid future date and time.';
    if ($location === '' || !mb_check_encoding($location, 'UTF-8') || mb_strlen($location, 'UTF-8') > 200) $errors[] = 'Enter a location of up to 200 characters.';
    if ($capacity === false) $errors[] = 'Capacity must be between 1 and 100,000.';
    if (!$errors) {
        try {
            if ($event) {
                withEventLock($pdo, $id, function ($lockedEvent) use ($pdo, $id, $title, $description, $date, $location, $capacity, $category, $artwork, $intent, $socialUrl): void {
                    if (!$lockedEvent || !canEditEvent($pdo, $lockedEvent)) {
                        throw new BookingAccessDenied();
                    }
                    $info = eventCapacityInfo($pdo, $id);
                    if ($capacity < $info['bookings']) {
                        throw new CapacityBelowBookings();
                    }
                    if ($lockedEvent['status']==='approved' && $capacity > (int)$lockedEvent['capacity'] + max(1,(int)ceil((int)$lockedEvent['capacity']/10))) throw new DomainException('Increase capacity by at most 10% per refill.');
                    $changed = $title !== $lockedEvent['title'] || $description !== $lockedEvent['description'] || $location !== $lockedEvent['location'] || $date->format('Y-m-d H:i:s') !== $lockedEvent['start_time'];
                    $changed=$changed || $socialUrl!==(string)($lockedEvent['social_url']??'');
                    $status=$intent==='save_draft'?'draft':(($changed||in_array($lockedEvent['status'],['draft','rejected','cancelled'],true)&&isRole('creator'))?'pending':$lockedEvent['status']);
                    $statement = $pdo->prepare('UPDATE events SET title=?,description=?,start_time=?,location=?,capacity=?,category=?,artwork=?,social_url=?,status=? WHERE event_id=?');
                    $statement->execute([$title,$description,$date->format('Y-m-d H:i:s'),$location,$capacity,$category,$artwork,$socialUrl?:null,$status,$id]);
                    $updated=$lockedEvent; $updated['capacity']=$capacity; $updated['status']=$status;
                    promoteWaiting($pdo,$updated,max(0,$capacity-(int)$lockedEvent['capacity']));
                    if($changed){$q=$pdo->prepare('SELECT user_id FROM bookings WHERE event_id=?');$q->execute([$id]);foreach($q->fetchAll()as $u)notifyUser($pdo,(int)$u['user_id'],'Event details changed and are awaiting approval: '.$title,$id);}
                    audit($pdo,'event.edit',$id);
                });
                flash($intent==='save_draft'?'Draft saved.':'Event updated.');
            } else {
                $statement = $pdo->prepare("INSERT INTO events (organiser_id,title,description,start_time,location,capacity,original_capacity,category,artwork,social_url,status) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
                $statement->execute([currentUser()['user_id'], $title, $description, $date->format('Y-m-d H:i:s'), $location, $capacity, $capacity, $category, $artwork, $socialUrl?:null, $intent==='save_draft'?'draft':'pending']);
                flash($intent==='save_draft'?'Draft saved.':'Event created and submitted for approval.');
            }
            redirect($back);
        } catch (CapacityBelowBookings $exception) {
            $errors[] = 'Capacity cannot be below confirmed bookings.';
        } catch (BookingAccessDenied $exception) {
            http_response_code(403);
            exit('You cannot edit this event.');
        } catch (DomainException $exception) {
            $errors[] = $exception->getMessage();
        } catch (Throwable $exception) {
            serverError($exception);
        }
    }
}
$pageTitle = $event ? 'Edit event' : 'Create event';
require __DIR__ . '/includes/header.php';
$startValue = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($values['start_time'] ?? '') : (isset($values['start_time']) ? date('Y-m-d\TH:i', strtotime($values['start_time'])) : '');
?>
<div class="form-panel"><p class="eyebrow">BRING PEOPLE TOGETHER</p><h1><?= e($pageTitle) ?>.</h1>
<?php if (!$event): ?><p class="notice">Save a private draft, or submit the event for administrator approval.</p><?php endif; ?>
<?php if ($event && isRole('staff')): ?><p class="notice">You are editing this event for the assigned creator.</p><?php endif; ?>
<form method="post" class="panel">
  <?= csrfField() ?>
  <?php if ($event): ?><input type="hidden" name="event_id" value="<?= (int) $event['event_id'] ?>"><?php endif; ?>
  <?php foreach ($errors as $error): ?><p class="flash error"><?= e($error) ?></p><?php endforeach; ?>
  <label for="title">Event title</label><input id="title" name="title" required maxlength="150" value="<?= e($values['title'] ?? '') ?>">
  <label for="description">Description</label><textarea id="description" name="description" required><?= e($values['description'] ?? '') ?></textarea>
  <label for="start_time">Date and time</label><input id="start_time" type="datetime-local" name="start_time" required value="<?= e($startValue) ?>">
  <label for="location">Location</label><input id="location" name="location" required maxlength="200" value="<?= e($values['location'] ?? '') ?>">
  <label for="capacity">Capacity</label><input id="capacity" type="number" min="1" max="100000" name="capacity" required value="<?= e((string) ($values['capacity'] ?? '')) ?>">
  <label for="category">Category</label><select id="category" name="category"><?php foreach(categories() as $cat):?><option value="<?=e($cat)?>" <?=($values['category']??'Community')===$cat?'selected':''?>><?=e($cat)?></option><?php endforeach;?></select>
  <label for="social_url">Event website or social link (optional)</label><input id="social_url" type="url" name="social_url" maxlength="500" value="<?=e($values['social_url']??'')?>" placeholder="https://">
  <label>Choose cover artwork</label><div class="artwork-options"><?php foreach(artworkChoices() as $art):?><label class="artwork-choice"><input type="radio" name="artwork" value="<?=e($art)?>" <?=($values['artwork']??'city')===$art?'checked':''?>><img src="assets/art/<?=e($art)?>.svg" alt=""><span><?=e(ucfirst($art))?></span></label><?php endforeach;?></div>
  <p class="fine-print">After saving, open Cover studio to upload your own artwork. Changes to event details are sent for approval.</p>
  <div class="form-actions"><?php if(isRole('creator')):?><button class="secondary" name="intent" value="save_draft">Save draft</button><button name="intent" value="submit">Submit for review</button><?php else:?><button name="intent" value="save">Save assigned event</button><?php endif;?><a class="button ghost" href="<?= e($back) ?>">Cancel</a></div>
</form></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
