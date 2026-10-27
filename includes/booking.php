<?php
declare(strict_types=1);

function waitingRefillLimit(array $event): int {
    $original=(int)($event['original_capacity']??0);
    if($original<=0)$original=(int)$event['capacity'];
    return max(1,intdiv($original,10));
}
function waitingRefillRemaining(array $event): int {
    return max(0,waitingRefillLimit($event)-(int)($event['refill_count']??0));
}

class BookingAccessDenied extends RuntimeException {}
class CapacityBelowBookings extends RuntimeException {}

/** Every booking/cancellation/capacity writer locks the event before reading its bookings. */
function withEventLock(PDO $pdo, int $eventId, callable $operation): mixed {
    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare('SELECT * FROM events WHERE event_id=? FOR UPDATE');
        $statement->execute([$eventId]);
        $event=$statement->fetch();
        if($event && (int)($event['original_capacity']??0)===0){$pdo->prepare('UPDATE events SET original_capacity=capacity WHERE event_id=?')->execute([$eventId]);$event['original_capacity']=$event['capacity'];}
        $result = $operation($event);
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
        if (!$event || $event['status'] !== 'approved' || strtotime($event['start_time']) <= time()) {
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
            $bookingId = (int) $pdo->lastInsertId();
            $pdo->prepare('DELETE FROM waiting_list WHERE event_id=? AND user_id=?')->execute([$eventId, $userId]);
            audit($pdo,'booking.create',$bookingId);
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
        $statement = $pdo->prepare('SELECT booking_id,checked_in FROM bookings WHERE booking_id=? AND user_id=? FOR UPDATE');
        $statement->execute([$bookingId, $userId]);
        $booking = $statement->fetch();
        if (!$event || !$booking || (int)$booking['checked_in'] || strtotime($event['start_time']) <= time()) {
            throw new BookingAccessDenied();
        }
        $pdo->prepare('DELETE FROM bookings WHERE booking_id=? AND user_id=?')->execute([$bookingId, $userId]);
        audit($pdo,'booking.cancel',$bookingId);
        return promoteWaiting($pdo,$event,1) > 0;
    });
}
/** Caller holds the event row lock. Fill freed seats in FIFO order. */
function promoteWaiting(PDO $pdo, array $event, int $maximum): int {
    $eventId=(int)$event['event_id'];
    if($event['status']!=='approved'||strtotime($event['start_time'])<=time())return 0;
    $info=eventCapacityInfo($pdo,$eventId);
    $free=min($maximum,waitingRefillRemaining($event),max(0,(int)$event['capacity']-$info['bookings']));$promoted=0;
    while($free-->0){
        $q=$pdo->prepare("SELECT w.entry_id,w.user_id FROM waiting_list w JOIN users u ON u.user_id=w.user_id WHERE w.event_id=? AND u.role='user' AND u.is_active=1 AND NOT EXISTS(SELECT 1 FROM bookings b WHERE b.event_id=w.event_id AND b.user_id=w.user_id) ORDER BY w.joined_at,w.entry_id LIMIT 1 FOR UPDATE");$q->execute([$eventId]);$next=$q->fetch();
        if(!$next)break;
        $pdo->prepare('INSERT INTO bookings(event_id,user_id) VALUES(?,?)')->execute([$eventId,$next['user_id']]);
        $pdo->prepare('DELETE FROM waiting_list WHERE entry_id=?')->execute([$next['entry_id']]);
        notifyUser($pdo,(int)$next['user_id'],'A place is yours! You have been moved from the waiting list for '.$event['title'].'.',$eventId);
        $pdo->prepare('UPDATE events SET refill_count=refill_count+1 WHERE event_id=?')->execute([$eventId]);
        audit($pdo,'waitlist.promote',(int)$next['entry_id']);
        $promoted++;
    }
    return $promoted;
}
