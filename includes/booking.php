<?php
declare(strict_types=1);

class BookingAccessDenied extends RuntimeException {}
class CapacityBelowBookings extends RuntimeException {}

/** Every booking/cancellation/capacity writer locks the event before reading its bookings. */
function withEventLock(PDO $pdo, int $eventId, callable $operation): mixed {
    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare('SELECT * FROM events WHERE event_id=? FOR UPDATE');
        $statement->execute([$eventId]);
        $result = $operation($statement->fetch());
        $pdo->commit();
        return $result;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function bookOrWait(PDO $pdo, int $eventId, int $userId, string $action): string {
    if (!in_array($action, ['book', 'wait'], true)) {
        throw new InvalidArgumentException('Invalid booking action.');
    }
    return withEventLock($pdo, $eventId, function ($event) use ($pdo, $eventId, $userId, $action): string {
        if (!$event || $event['status'] !== 'approved') {
            return 'unavailable';
        }
        $statement = $pdo->prepare('SELECT 1 FROM bookings WHERE event_id=? AND user_id=?');
        $statement->execute([$eventId, $userId]);
        if ($statement->fetchColumn()) {
            return 'already_booked';
        }
        $info = eventCapacityInfo($pdo, $eventId);
        if ($action === 'book') {
            if ($info['bookings'] >= (int) $event['capacity']) {
                return 'full';
            }
            $pdo->prepare('INSERT INTO bookings (event_id,user_id) VALUES (?,?)')->execute([$eventId, $userId]);
            $pdo->prepare('DELETE FROM waiting_list WHERE event_id=? AND user_id=?')->execute([$eventId, $userId]);
            return 'booked';
        }
        if ($info['bookings'] < (int) $event['capacity']) {
            return 'available';
        }
        $statement = $pdo->prepare('SELECT 1 FROM waiting_list WHERE event_id=? AND user_id=?');
        $statement->execute([$eventId, $userId]);
        if ($statement->fetchColumn()) {
            return 'already_waiting';
        }
        $pdo->prepare('INSERT INTO waiting_list (event_id,user_id) VALUES (?,?)')->execute([$eventId, $userId]);
        return 'waiting';
    });
}

function cancelOwnBooking(PDO $pdo, int $bookingId, int $userId): bool {
    // Discover the event outside the transaction; recheck the booking after the event lock.
    $statement = $pdo->prepare('SELECT event_id FROM bookings WHERE booking_id=? AND user_id=?');
    $statement->execute([$bookingId, $userId]);
    $eventId = $statement->fetchColumn();
    if ($eventId === false) {
        throw new BookingAccessDenied();
    }
    return withEventLock($pdo, (int) $eventId, function ($event) use ($pdo, $bookingId, $userId, $eventId): bool {
        $statement = $pdo->prepare('SELECT booking_id FROM bookings WHERE booking_id=? AND user_id=? FOR UPDATE');
        $statement->execute([$bookingId, $userId]);
        if (!$event || !$statement->fetch()) {
            throw new BookingAccessDenied();
        }
        $pdo->prepare('DELETE FROM bookings WHERE booking_id=? AND user_id=?')->execute([$bookingId, $userId]);
        // Keep Fairboard's FIFO promotion inside the same event transaction.
        $info = eventCapacityInfo($pdo, (int) $eventId);
        if ($event['status'] !== 'approved' || $info['bookings'] >= (int) $event['capacity']) {
            return false;
        }
        $statement = $pdo->prepare("SELECT w.entry_id,w.user_id FROM waiting_list w JOIN users u ON u.user_id=w.user_id WHERE w.event_id=? AND u.role='user' AND NOT EXISTS (SELECT 1 FROM bookings b WHERE b.event_id=w.event_id AND b.user_id=w.user_id) ORDER BY w.joined_at,w.entry_id LIMIT 1 FOR UPDATE");
        $statement->execute([$eventId]);
        $next = $statement->fetch();
        if (!$next) {
            return false;
        }
        $pdo->prepare('INSERT INTO bookings (event_id,user_id) VALUES (?,?)')->execute([$eventId, $next['user_id']]);
        $pdo->prepare('DELETE FROM waiting_list WHERE entry_id=?')->execute([$next['entry_id']]);
        return true;
    });
}
