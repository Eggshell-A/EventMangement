<?php
declare(strict_types=1);

function refillLimit(int $originalCapacity): int
{
    if ($originalCapacity <= 0) {
        return 0;
    }
    return max(1, intdiv($originalCapacity, 10));
}

function canRefill(int $used, int $originalCapacity): bool
{
    return $used >= 0 && $used < refillLimit($originalCapacity);
}

function bookingState(array $event, ?array $booking, ?array $waiting): string
{
    if ($booking !== null) {
        return 'booked';
    }
    if ($waiting !== null) {
        return 'waiting';
    }
    if (($event['status'] ?? '') !== 'approved') {
        return 'unavailable';
    }
    $startTime = strtotime((string) ($event['start_time'] ?? ''));
    if ($startTime !== false && $startTime < time()) {
        return 'unavailable';
    }
    return (int) ($event['booking_count'] ?? 0) < (int) ($event['capacity'] ?? 0)
        ? 'available'
        : 'waitlist';
}

function beginBookingWrite(PDO $pdo): void
{
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $pdo->exec('BEGIN IMMEDIATE TRANSACTION');
        return;
    }
    $pdo->beginTransaction();
}

function bookingLockSuffix(PDO $pdo): string
{
    return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
}

function bookEventPlace(PDO $pdo, int $eventId, int $userId): string
{
    try {
        beginBookingWrite($pdo);
        $statement = $pdo->prepare(
            "SELECT event_id, capacity, status FROM events WHERE event_id = ?" . bookingLockSuffix($pdo)
        );
        $statement->execute([$eventId]);
        $event = $statement->fetch();
        if (!$event || $event['status'] !== 'approved') {
            $pdo->rollBack();
            return 'unavailable';
        }

        $statement = $pdo->prepare('SELECT 1 FROM bookings WHERE event_id = ? AND user_id = ?');
        $statement->execute([$eventId, $userId]);
        if ($statement->fetchColumn()) {
            $pdo->rollBack();
            return 'already_booked';
        }
        $statement = $pdo->prepare('SELECT 1 FROM waiting_list WHERE event_id = ? AND user_id = ?');
        $statement->execute([$eventId, $userId]);
        if ($statement->fetchColumn()) {
            $pdo->rollBack();
            return 'already_waiting';
        }

        $statement = $pdo->prepare('SELECT COUNT(*) FROM bookings WHERE event_id = ?');
        $statement->execute([$eventId]);
        if ((int) $statement->fetchColumn() >= (int) $event['capacity']) {
            $pdo->rollBack();
            return 'full';
        }

        $pdo->prepare('INSERT INTO bookings (event_id, user_id) VALUES (?, ?)')->execute([$eventId, $userId]);
        $pdo->commit();
        return 'booked';
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function joinEventWaitlist(PDO $pdo, int $eventId, int $userId): string
{
    try {
        beginBookingWrite($pdo);
        $statement = $pdo->prepare(
            "SELECT event_id, capacity, status FROM events WHERE event_id = ?" . bookingLockSuffix($pdo)
        );
        $statement->execute([$eventId]);
        $event = $statement->fetch();
        if (!$event || $event['status'] !== 'approved') {
            $pdo->rollBack();
            return 'unavailable';
        }
        $statement = $pdo->prepare('SELECT COUNT(*) FROM bookings WHERE event_id = ?');
        $statement->execute([$eventId]);
        if ((int) $statement->fetchColumn() < (int) $event['capacity']) {
            $pdo->rollBack();
            return 'available';
        }
        $statement = $pdo->prepare('SELECT 1 FROM bookings WHERE event_id = ? AND user_id = ?');
        $statement->execute([$eventId, $userId]);
        if ($statement->fetchColumn()) {
            $pdo->rollBack();
            return 'already_booked';
        }
        $statement = $pdo->prepare('SELECT 1 FROM waiting_list WHERE event_id = ? AND user_id = ?');
        $statement->execute([$eventId, $userId]);
        if ($statement->fetchColumn()) {
            $pdo->rollBack();
            return 'already_waiting';
        }
        $pdo->prepare('INSERT INTO waiting_list (event_id, user_id) VALUES (?, ?)')->execute([$eventId, $userId]);
        $pdo->commit();
        return 'waiting';
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function cancelOwnBooking(PDO $pdo, int $bookingId, int $userId): string
{
    try {
        beginBookingWrite($pdo);
        $statement = $pdo->prepare(
            'SELECT booking_id, event_id FROM bookings WHERE booking_id = ? AND user_id = ?' . bookingLockSuffix($pdo)
        );
        $statement->execute([$bookingId, $userId]);
        $booking = $statement->fetch();
        if (!$booking) {
            $pdo->rollBack();
            return 'not_found';
        }

        $statement = $pdo->prepare(
            'SELECT event_id, original_capacity, refill_count FROM events WHERE event_id = ?' . bookingLockSuffix($pdo)
        );
        $statement->execute([(int) $booking['event_id']]);
        $event = $statement->fetch();
        if (!$event) {
            $pdo->rollBack();
            return 'not_found';
        }

        $pdo->prepare('DELETE FROM bookings WHERE booking_id = ? AND user_id = ?')->execute([$bookingId, $userId]);
        $statement = $pdo->prepare(
            'SELECT entry_id, user_id FROM waiting_list WHERE event_id = ? ORDER BY joined_at, entry_id LIMIT 1'
            . bookingLockSuffix($pdo)
        );
        $statement->execute([(int) $booking['event_id']]);
        $next = $statement->fetch();
        if ($next && canRefill((int) $event['refill_count'], (int) $event['original_capacity'])) {
            $pdo->prepare('DELETE FROM waiting_list WHERE entry_id = ?')->execute([(int) $next['entry_id']]);
            $pdo->prepare('INSERT INTO bookings (event_id, user_id) VALUES (?, ?)')->execute([(int) $booking['event_id'], (int) $next['user_id']]);
            $pdo->prepare('UPDATE events SET refill_count = refill_count + 1 WHERE event_id = ?')->execute([(int) $booking['event_id']]);
            $pdo->commit();
            return 'promoted';
        }

        $pdo->commit();
        return 'released';
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}
