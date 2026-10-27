<?php
declare(strict_types=1);

require __DIR__ . '/../includes/role_access.php';

$failures = 0;

function expect(bool $condition, string $label): void
{
    global $failures;
    if ($condition) {
        return;
    }
    fwrite(STDERR, "FAIL {$label}\n");
    $failures++;
}

function account(string $role): array
{
    return ['role' => $role];
}

foreach (['dashboard.php', 'event_form.php', 'staff.php', 'admin.php', 'my_bookings.php'] as $page) {
    expect(pageAllowed(null, $page) === false, "logged-out cannot open {$page}");
}

expect(pageAllowed(account('creator'), 'dashboard.php') === true, 'creator can open dashboard.php');
expect(pageAllowed(account('creator'), 'event_form.php') === true, 'creator can open event_form.php');
expect(pageAllowed(account('creator'), 'attendees.php') === true, 'creator can open attendee management before event ownership is checked');
expect(pageAllowed(account('creator'), 'checkin.php') === true, 'creator can open check-in before event ownership is checked');
expect(pageAllowed(account('creator'), 'staff.php') === false, 'creator cannot open staff.php');
expect(pageAllowed(account('creator'), 'admin.php') === false, 'creator cannot open admin.php');

expect(pageAllowed(account('staff'), 'staff.php') === true, 'staff can open staff.php');
expect(pageAllowed(account('staff'), 'admin.php') === false, 'staff cannot open admin.php');
expect(pageAllowed(account('staff'), 'dashboard.php') === false, 'staff cannot open dashboard.php');
expect(pageAllowed(account('staff'), 'event_form.php') === true, 'staff can open event_form.php for an assigned creator');
expect(pageAllowed(account('staff'), 'attendees.php') === true, 'staff can open attendee management before assignment is checked');
expect(pageAllowed(account('staff'), 'checkin.php') === true, 'staff can open check-in before assignment is checked');

expect(pageAllowed(account('admin'), 'admin.php') === true, 'admin can open admin.php');
expect(pageAllowed(account('admin'), 'staff.php') === false, 'admin cannot open staff.php');
expect(pageAllowed(account('admin'), 'dashboard.php') === false, 'admin cannot open dashboard.php');
expect(pageAllowed(account('admin'), 'attendees.php') === false, 'admin cannot use attendee management as a working page');
expect(pageAllowed(account('admin'), 'checkin.php') === false, 'admin cannot use check-in as a working page');

expect(pageAllowed(account('user'), 'my_bookings.php') === true, 'user can open my_bookings.php');
expect(pageAllowed(account('user'), 'dashboard.php') === false, 'user cannot open dashboard.php');
expect(pageAllowed(account('user'), 'staff.php') === false, 'user cannot open staff.php');
expect(pageAllowed(account('user'), 'admin.php') === false, 'user cannot open admin.php');

expect(roleHome('user') === 'index.php', 'roleHome user is index.php');
expect(roleHome('creator') === 'dashboard.php', 'roleHome creator is dashboard.php');
expect(roleHome('staff') === 'staff.php', 'roleHome staff is staff.php');
expect(roleHome('admin') === 'admin.php', 'roleHome admin is admin.php');
expect(roleHome('other') === 'index.php', 'roleHome default is index.php');

if ($failures > 0) {
    fwrite(STDERR, "{$failures} failed\n");
    exit(1);
}

echo "ok\n";
exit(0);
