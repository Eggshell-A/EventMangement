<?php
declare(strict_types=1);
const TICKET_INTERVAL = 30;
function ticketToken(int $bookingId, int $eventId, ?int $now = null): string {
    $payload = 'v1.' . $bookingId . '.' . $eventId . '.' . intdiv($now ?? time(), TICKET_INTERVAL);
    return $payload . '.' . hash_hmac('sha256', $payload, applicationKey());
}
function verifyTicketToken(string $token, ?int $now = null): ?array {
    if (!preg_match('/^v1\.([1-9][0-9]{0,9})\.([1-9][0-9]{0,9})\.([0-9]{1,12})\.([a-f0-9]{64})$/D', $token, $m)) return null;
    $window = intdiv($now ?? time(), TICKET_INTERVAL);
    if ((int)$m[3] !== $window) return null; // No grace window: old codes expire at the boundary.
    $payload = 'v1.' . $m[1] . '.' . $m[2] . '.' . $m[3];
    if (!hash_equals(hash_hmac('sha256',$payload,applicationKey()), $m[4])) return null;
    return ['booking_id'=>(int)$m[1], 'event_id'=>(int)$m[2]];
}
function qrSvg(string $text): string {
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!is_file($autoload)) throw new RuntimeException('Run composer install.');
    require_once $autoload;
    $options = new \chillerlan\QRCode\QROptions(['outputBase64'=>false,'outputType'=>\chillerlan\QRCode\QRCode::OUTPUT_MARKUP_SVG,'svgUseFillAttributes'=>true,'addQuietzone'=>true]);
    return (new \chillerlan\QRCode\QRCode($options))->render($text);
}
function checkinOpen(array $event): bool {
    global $config;
    $start = strtotime($event['start_time']);
    return $event['status'] === 'approved' && time() >= $start - (int)($config['checkin_before_seconds'] ?? 7200)
        && time() <= $start + (int)($config['checkin_after_seconds'] ?? 43200);
}
function checkInBooking(PDO $pdo, int $bookingId, int $eventId, ?string $token = null): string {
    return withEventLock($pdo, $eventId, function($event) use($pdo,$bookingId,$eventId,$token): string {
        if (!$event || !canManageAttendees($pdo,$event)) throw new BookingAccessDenied();
        // Revalidate after waiting for the lock so expired queued requests never enter.
        if ($token !== null && verifyTicketToken($token) !== ['booking_id'=>$bookingId,'event_id'=>$eventId]) return 'expired';
        if (!checkinOpen($event)) return 'closed';
        $q=$pdo->prepare('SELECT b.checked_in FROM bookings b JOIN users u ON u.user_id=b.user_id WHERE b.booking_id=? AND b.event_id=? AND u.is_active=1 AND u.role=\'user\' FOR UPDATE');$q->execute([$bookingId,$eventId]);$row=$q->fetch();
        if (!$row) return 'missing';
        if ((int)$row['checked_in']) return 'used';
        $pdo->prepare('UPDATE bookings SET checked_in=1,checked_in_at=NOW(),checked_in_by=? WHERE booking_id=? AND checked_in=0')->execute([currentUser()['user_id'],$bookingId]);
        audit($pdo,'ticket.checkin',$bookingId);
        return 'success';
    });
}
