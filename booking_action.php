<?php
require __DIR__ . '/includes/bootstrap.php';
requireRole('user');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}
requireValidCsrf();
$id = (int) ($_POST['event_id'] ?? 0);
$action = $_POST['action'] ?? '';
$userId = (int) currentUser()['user_id'];
if (!in_array($action, ['book', 'wait'], true)) {
    http_response_code(400);
    exit('Invalid action.');
}
try {
    $pdo->beginTransaction();
    $statement = $pdo->prepare("SELECT event_id, capacity FROM events WHERE event_id = ? AND status = 'approved' FOR UPDATE");
    $statement->execute([$id]);
    $event = $statement->fetch();
    if (!$event) {
        $pdo->rollBack();
        flash('This event is not available.', 'error');
        redirect('index.php');
    }
    $statement = $pdo->prepare('SELECT COUNT(*) FROM bookings WHERE event_id = ?');
    $statement->execute([$id]);
    $booked = (int) $statement->fetchColumn();
    if ($action === 'book') {
        if ($booked >= (int) $event['capacity']) {
            $pdo->rollBack();
            flash('This event is now full. You can join the waiting list.', 'error');
            redirect('event.php?id=' . $id);
        }
        $pdo->prepare('INSERT INTO bookings (event_id, user_id) VALUES (?, ?)')->execute([$id, $userId]);
        $pdo->commit();
        flash('Your place is confirmed.');
        redirect('booked.php?id=' . $id);
    }
    if ($booked < (int) $event['capacity']) {
        $pdo->rollBack();
        flash('A place is available. Join it instead.', 'error');
        redirect('event.php?id=' . $id);
    }
    $pdo->prepare('INSERT INTO waiting_list (event_id, user_id) VALUES (?, ?)')->execute([$id, $userId]);
    $pdo->commit();
    flash('You joined the waiting list.');
    redirect('event.php?id=' . $id);
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash('You already have a place, or you are already waiting.', 'error');
    redirect('event.php?id=' . $id);
}
