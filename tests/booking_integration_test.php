<?php
declare(strict_types=1);

require __DIR__ . '/../includes/booking_policy.php';

$failures = [];
function expectBookingIntegration(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        $failures[] = $message;
    }
}

function bookingTestPdo(string $path): PDO
{
    $pdo = new PDO('sqlite:' . $path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA busy_timeout = 5000');
    return $pdo;
}

function createBookingSchema(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE events (event_id INTEGER PRIMARY KEY, capacity INTEGER NOT NULL, original_capacity INTEGER NOT NULL, refill_count INTEGER NOT NULL DEFAULT 0, status TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE bookings (booking_id INTEGER PRIMARY KEY AUTOINCREMENT, event_id INTEGER NOT NULL, user_id INTEGER NOT NULL, checked_in INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE(event_id, user_id))');
    $pdo->exec('CREATE TABLE waiting_list (entry_id INTEGER PRIMARY KEY AUTOINCREMENT, event_id INTEGER NOT NULL, user_id INTEGER NOT NULL, joined_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE(event_id, user_id))');
}

$dbPath = tempnam(sys_get_temp_dir(), 'fairboard-booking-');
$pdo = bookingTestPdo($dbPath);
createBookingSchema($pdo);

// Two independent processes race for the one remaining place.
$pdo->exec("INSERT INTO events (event_id, capacity, original_capacity, refill_count, status) VALUES (1, 1, 1, 0, 'approved')");
$startPath = $dbPath . '.start';
$resultPaths = [$dbPath . '.r1', $dbPath . '.r2'];
$children = [];
foreach ([101, 102] as $index => $userId) {
    $pid = pcntl_fork();
    if ($pid === 0) {
        while (!file_exists($startPath)) {
            usleep(10_000);
        }
        $childPdo = bookingTestPdo($dbPath);
        file_put_contents($resultPaths[$index], bookEventPlace($childPdo, 1, $userId));
        exit(0);
    }
    $children[] = $pid;
}
touch($startPath);
foreach ($children as $pid) {
    pcntl_waitpid($pid, $status);
}
$raceResults = [trim((string) file_get_contents($resultPaths[0])), trim((string) file_get_contents($resultPaths[1]))];
sort($raceResults);
expectBookingIntegration($raceResults === ['booked', 'full'], 'Exactly one concurrent request must take the final place.');
expectBookingIntegration((int) $pdo->query('SELECT COUNT(*) FROM bookings WHERE event_id = 1')->fetchColumn() === 1, 'Final-place race must leave exactly one booking.');

// Another user cannot cancel someone else's booking.
$bookingId = (int) $pdo->query('SELECT booking_id FROM bookings WHERE event_id = 1')->fetchColumn();
expectBookingIntegration(cancelOwnBooking($pdo, $bookingId, 999) === 'not_found', 'A user must not cancel another user booking.');
expectBookingIntegration((int) $pdo->query('SELECT COUNT(*) FROM bookings WHERE booking_id = ' . $bookingId)->fetchColumn() === 1, 'Rejected cancellation must retain the booking.');

// FIFO promotion moves the first waiting person and increments the audit counter.
$pdo->exec("INSERT INTO events (event_id, capacity, original_capacity, refill_count, status) VALUES (2, 1, 100, 0, 'approved')");
$pdo->exec('INSERT INTO bookings (event_id, user_id) VALUES (2, 201)');
$fifoBookingId = (int) $pdo->lastInsertId();
$pdo->exec("INSERT INTO waiting_list (event_id, user_id, joined_at) VALUES (2, 202, '2026-10-27 10:00:00'), (2, 203, '2026-10-27 10:01:00')");
expectBookingIntegration(cancelOwnBooking($pdo, $fifoBookingId, 201) === 'promoted', 'Cancellation must promote the first waiting person while refill allowance remains.');
expectBookingIntegration((int) $pdo->query('SELECT user_id FROM bookings WHERE event_id = 2')->fetchColumn() === 202, 'FIFO must promote the earliest waitlist entry.');
expectBookingIntegration((int) $pdo->query('SELECT user_id FROM waiting_list WHERE event_id = 2 ORDER BY joined_at, entry_id LIMIT 1')->fetchColumn() === 203, 'FIFO must leave later waitlist entries in order.');
expectBookingIntegration((int) $pdo->query('SELECT refill_count FROM events WHERE event_id = 2')->fetchColumn() === 1, 'Promotion must increment the refill audit counter.');

// The eleventh promotion is refused for original capacity 100.
$pdo->exec("INSERT INTO events (event_id, capacity, original_capacity, refill_count, status) VALUES (3, 1, 100, 10, 'approved')");
$pdo->exec('INSERT INTO bookings (event_id, user_id) VALUES (3, 301)');
$cappedBookingId = (int) $pdo->lastInsertId();
$pdo->exec("INSERT INTO waiting_list (event_id, user_id, joined_at) VALUES (3, 302, '2026-10-27 11:00:00')");
expectBookingIntegration(cancelOwnBooking($pdo, $cappedBookingId, 301) === 'released', 'Cancellation must succeed without an eleventh automatic refill.');
expectBookingIntegration((int) $pdo->query('SELECT COUNT(*) FROM bookings WHERE event_id = 3')->fetchColumn() === 0, 'Refill cap must leave the released place open.');
expectBookingIntegration((int) $pdo->query('SELECT COUNT(*) FROM waiting_list WHERE event_id = 3 AND user_id = 302')->fetchColumn() === 1, 'Refill cap must keep the waiting person on the list.');
expectBookingIntegration((int) $pdo->query('SELECT refill_count FROM events WHERE event_id = 3')->fetchColumn() === 10, 'Refill cap must not increase after refusal.');

foreach ([$startPath, ...$resultPaths, $dbPath] as $path) {
    if (file_exists($path)) {
        unlink($path);
    }
}

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo "ok\n";
