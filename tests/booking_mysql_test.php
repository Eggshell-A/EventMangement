<?php
declare(strict_types=1);

require __DIR__ . '/../includes/booking_policy.php';

$socket = getenv('FAIRBOARD_MYSQL_SOCKET');
if (!$socket) {
    echo "skip: set FAIRBOARD_MYSQL_SOCKET to run the MySQL transaction test\n";
    exit(0);
}

function mysqlBookingPdo(string $socket): PDO
{
    return new PDO(
        'mysql:unix_socket=' . $socket . ';dbname=fairboard_test;charset=utf8mb4',
        'root',
        '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
}

$failures = [];
function expectMysqlBooking(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        $failures[] = $message;
    }
}

$pdo = mysqlBookingPdo($socket);
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (['waiting_list', 'bookings', 'events', 'staff_assignments', 'creator_plans', 'users'] as $table) {
    $pdo->exec('TRUNCATE TABLE ' . $table);
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
$hash = password_hash('Fairboard1!', PASSWORD_DEFAULT);
$insertUser = $pdo->prepare('INSERT INTO users (user_id, name, email, password_hash, role) VALUES (?, ?, ?, ?, ?)');
foreach ([
    [1, 'Creator', 'mysql-creator@fairboard.test', $hash, 'creator'],
    [101, 'User 101', 'mysql-101@fairboard.test', $hash, 'user'],
    [102, 'User 102', 'mysql-102@fairboard.test', $hash, 'user'],
    [201, 'User 201', 'mysql-201@fairboard.test', $hash, 'user'],
    [202, 'User 202', 'mysql-202@fairboard.test', $hash, 'user'],
    [203, 'User 203', 'mysql-203@fairboard.test', $hash, 'user'],
    [301, 'User 301', 'mysql-301@fairboard.test', $hash, 'user'],
    [302, 'User 302', 'mysql-302@fairboard.test', $hash, 'user'],
] as $account) {
    $insertUser->execute($account);
}

$insertEvent = $pdo->prepare("INSERT INTO events (event_id, organiser_id, title, description, start_time, location, capacity, original_capacity, refill_count, status) VALUES (?, 1, ?, 'Test', '2099-01-01 10:00:00', 'Test room', ?, ?, ?, 'approved')");
$insertEvent->execute([1, 'Final place', 1, 1, 0]);
$insertEvent = null;
$pdo = null;

$startPath = tempnam(sys_get_temp_dir(), 'fairboard-mysql-start-');
unlink($startPath);
$resultPaths = [$startPath . '.r1', $startPath . '.r2'];
$children = [];
foreach ([101, 102] as $index => $userId) {
    $pid = pcntl_fork();
    if ($pid === 0) {
        while (!file_exists($startPath)) {
            usleep(10_000);
        }
        $childPdo = mysqlBookingPdo($socket);
        file_put_contents($resultPaths[$index], bookEventPlace($childPdo, 1, $userId));
        exit(0);
    }
    $children[] = $pid;
}
touch($startPath);
foreach ($children as $pid) {
    pcntl_waitpid($pid, $status);
}
$pdo = mysqlBookingPdo($socket);
$results = [trim((string) file_get_contents($resultPaths[0])), trim((string) file_get_contents($resultPaths[1]))];
sort($results);
expectMysqlBooking($results === ['booked', 'full'], 'InnoDB row locking must allow exactly one final-place booking.');
expectMysqlBooking((int) $pdo->query('SELECT COUNT(*) FROM bookings WHERE event_id = 1')->fetchColumn() === 1, 'Final-place race must not exceed capacity.');

$insertEvent = $pdo->prepare("INSERT INTO events (event_id, organiser_id, title, description, start_time, location, capacity, original_capacity, refill_count, status) VALUES (?, 1, ?, 'Test', '2099-01-01 10:00:00', 'Test room', ?, ?, ?, 'approved')");
$insertEvent->execute([2, 'FIFO', 1, 100, 0]);
$pdo->exec('INSERT INTO bookings (event_id, user_id) VALUES (2, 201)');
$fifoBookingId = (int) $pdo->lastInsertId();
$pdo->exec("INSERT INTO waiting_list (event_id, user_id, joined_at) VALUES (2, 202, '2026-10-27 10:00:00'), (2, 203, '2026-10-27 10:01:00')");
expectMysqlBooking(cancelOwnBooking($pdo, $fifoBookingId, 999) === 'not_found', 'MySQL cancellation must enforce booking ownership.');
expectMysqlBooking(cancelOwnBooking($pdo, $fifoBookingId, 201) === 'promoted', 'MySQL cancellation must promote the first waiting person.');
expectMysqlBooking((int) $pdo->query('SELECT user_id FROM bookings WHERE event_id = 2')->fetchColumn() === 202, 'MySQL FIFO must choose the earliest waitlist entry.');

$insertEvent->execute([3, 'Refill cap', 1, 100, 10]);
$pdo->exec('INSERT INTO bookings (event_id, user_id) VALUES (3, 301)');
$cappedBookingId = (int) $pdo->lastInsertId();
$pdo->exec("INSERT INTO waiting_list (event_id, user_id, joined_at) VALUES (3, 302, '2026-10-27 11:00:00')");
expectMysqlBooking(cancelOwnBooking($pdo, $cappedBookingId, 301) === 'released', 'MySQL must refuse the eleventh automatic refill.');
expectMysqlBooking((int) $pdo->query('SELECT COUNT(*) FROM waiting_list WHERE event_id = 3 AND user_id = 302')->fetchColumn() === 1, 'Refill refusal must keep the waiting user in line.');

foreach ([$startPath, ...$resultPaths] as $path) {
    if (file_exists($path)) {
        unlink($path);
    }
}

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo "ok\n";
