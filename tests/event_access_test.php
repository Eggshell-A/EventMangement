<?php
declare(strict_types=1);

require __DIR__ . '/../includes/event_access.php';

$failures = [];
function expectAccess(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        $failures[] = $message;
    }
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE staff_assignments (staff_user_id INTEGER NOT NULL, creator_user_id INTEGER NOT NULL, UNIQUE (staff_user_id, creator_user_id))');
$pdo->exec('CREATE TABLE users (user_id INTEGER PRIMARY KEY, role TEXT NOT NULL, is_active INTEGER NOT NULL DEFAULT 1)');
$pdo->exec("INSERT INTO users (user_id, role, is_active) VALUES (10, 'creator', 1), (20, 'creator', 1), (30, 'staff', 1), (31, 'staff', 1), (40, 'admin', 1), (50, 'user', 1)");
$pdo->exec('INSERT INTO staff_assignments (staff_user_id, creator_user_id) VALUES (30, 10)');

$ownedEvent = ['event_id' => 100, 'organiser_id' => 10];
$otherEvent = ['event_id' => 101, 'organiser_id' => 20];
$creator = ['user_id' => 10, 'role' => 'creator'];
$otherCreator = ['user_id' => 20, 'role' => 'creator'];
$assignedStaff = ['user_id' => 30, 'role' => 'staff'];
$unassignedStaff = ['user_id' => 31, 'role' => 'staff'];
$admin = ['user_id' => 40, 'role' => 'admin'];
$user = ['user_id' => 50, 'role' => 'user'];

expectAccess(canCreateEvent($creator), 'Creator must be able to create an event.');
expectAccess(!canCreateEvent($assignedStaff), 'Staff must not be able to create an event.');
expectAccess(canEditEvent($pdo, $creator, $ownedEvent), 'Creator must edit their own event.');
expectAccess(!canEditEvent($pdo, $creator, $otherEvent), 'Creator must not edit another creator event.');
expectAccess(canEditEvent($pdo, $assignedStaff, $ownedEvent), 'Assigned Staff must edit the assigned creator event.');
expectAccess(!canEditEvent($pdo, $assignedStaff, $otherEvent), 'Assigned Staff must not edit a different creator event.');
expectAccess(!canEditEvent($pdo, $unassignedStaff, $ownedEvent), 'Unassigned Staff must not edit an event by manual ID.');
expectAccess(!canEditEvent($pdo, $admin, $ownedEvent), 'Admin must not use the event editing workspace.');
expectAccess(!canEditEvent($pdo, $user, $ownedEvent), 'User must not edit an event.');

expectAccess(canManageAttendees($pdo, $creator, $ownedEvent), 'Creator must manage attendees for their event.');
expectAccess(!canManageAttendees($pdo, $otherCreator, $ownedEvent), 'Another Creator must not manage attendees.');
expectAccess(canManageAttendees($pdo, $assignedStaff, $ownedEvent), 'Assigned Staff must manage attendees and check-in.');
expectAccess(!canManageAttendees($pdo, $unassignedStaff, $ownedEvent), 'Unassigned Staff must not manage attendees by manual ID.');
expectAccess(!canManageAttendees($pdo, $admin, $ownedEvent), 'Admin must not use attendee or check-in pages as a working page.');

expectAccess(assignStaffToCreator($pdo, 31, 20), 'Admin workflow must assign a Staff account to a Creator account.');
expectAccess(!assignStaffToCreator($pdo, 31, 20), 'A duplicate Staff assignment must not be created.');
expectAccess(!assignStaffToCreator($pdo, 50, 20), 'A User account must not be assignable as Staff.');
expectAccess(!assignStaffToCreator($pdo, 31, 50), 'Staff must not be assigned to a non-Creator account.');
expectAccess(unassignStaffFromCreator($pdo, 31, 20), 'Admin workflow must remove an existing Staff assignment.');
expectAccess(!unassignStaffFromCreator($pdo, 31, 20), 'Removing a missing assignment must report no change.');

expectAccess(setAccountActive($pdo, 40, 50, false), 'Admin must be able to disable another account.');
expectAccess((int) $pdo->query('SELECT is_active FROM users WHERE user_id = 50')->fetchColumn() === 0, 'Disabling must retain the account and change only active state.');
expectAccess(!setAccountActive($pdo, 40, 40, false), 'Admin must not disable their own current account.');
expectAccess(setAccountActive($pdo, 40, 50, true), 'Admin must be able to reactivate an account.');

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo "ok\n";
