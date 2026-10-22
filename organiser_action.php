<?php
require __DIR__ . '/includes/bootstrap.php';
requirePost();
requireRole('creator');
$id = postPositiveInt('event_id');
$number = null;
if (isset($_POST['add_places'])) {
    $number = filter_var(postString('add_places'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 4294967295]]);
    if ($number === false) {
        flash('Enter a valid number of places.', 'error');
        redirect('dashboard.php');
    }
} elseif (postString('action') !== 'cancel') {
    http_response_code(400);
    exit('Invalid action.');
}
try {
    withEventLock($pdo, $id, function ($event) use ($pdo, $id, $number): void {
        if (!$event || !isEventOwnerOrAdmin($event)) {
            throw new BookingAccessDenied();
        }
        if ($number !== null) {
            if ((int) $event['capacity'] > 4294967295 - $number) {
                throw new OverflowException();
            }
            $pdo->prepare('UPDATE events SET capacity=capacity+? WHERE event_id=?')->execute([$number, $id]);
        } else {
            $pdo->prepare("UPDATE events SET status='removed' WHERE event_id=?")->execute([$id]);
        }
    });
    flash($number !== null ? 'Places added.' : 'Event cancelled and removed from public pages.');
} catch (BookingAccessDenied $exception) {
    http_response_code(403);
    exit('Event not found.');
} catch (OverflowException $exception) {
    flash('The capacity is too large.', 'error');
} catch (Throwable $exception) {
    serverError($exception);
}
redirect('dashboard.php');
