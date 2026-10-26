<?php
declare(strict_types=1);

require __DIR__ . '/../includes/booking_policy.php';

$failures = [];
function expectBookingPolicy(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        $failures[] = $message;
    }
}

expectBookingPolicy(refillLimit(100) === 10, 'Capacity 100 must allow ten waitlist refills.');
expectBookingPolicy(canRefill(9, 100), 'The tenth refill must be allowed.');
expectBookingPolicy(!canRefill(10, 100), 'The eleventh refill must be refused.');
expectBookingPolicy(refillLimit(9) === 1, 'An event below ten places must allow one refill.');
expectBookingPolicy(canRefill(0, 9), 'The first refill for a small event must be allowed.');
expectBookingPolicy(!canRefill(1, 9), 'A second refill for a nine-place event must be refused.');
expectBookingPolicy(refillLimit(100) === refillLimit(100), 'The limit must depend on original capacity, not a later capacity value.');

$event = ['status' => 'approved', 'capacity' => 20, 'booking_count' => 19, 'start_time' => '2099-01-01 10:00:00'];
expectBookingPolicy(bookingState($event, null, null) === 'available', 'An approved future event with space must be available.');
expectBookingPolicy(bookingState($event, ['booking_id' => 1], null) === 'booked', 'A confirmed booking must take priority over availability.');
expectBookingPolicy(bookingState($event, null, ['entry_id' => 2]) === 'waiting', 'A waitlist entry must take priority over availability.');
$event['booking_count'] = 20;
expectBookingPolicy(bookingState($event, null, null) === 'waitlist', 'A full approved event must offer the waitlist.');
$event['status'] = 'cancelled';
expectBookingPolicy(bookingState($event, null, null) === 'unavailable', 'A cancelled event must be unavailable.');

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo "ok\n";
