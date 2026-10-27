> Historical Week 2 report. For the current implementation and validation, see [RELEASE.md](RELEASE.md) and [TEST_REPORT.md](TEST_REPORT.md).

# Week 2 access test report

Run date is 16 October 2026. The checks cover the week 2 role gates. No application PHP was edited. This report does not include the database password from `config.php`.

## Verdict

Pass. The access test, the syntax check, the seed password check, and each code check passed. There were no failures.

## Checks

| Check | Result | Evidence |
| --- | --- | --- |
| Logged-out denial of `dashboard.php`, `event_form.php`, `staff.php`, `admin.php`, `admin_action.php`, `my_bookings.php`, `booking_action.php`, and `cancel_booking.php` | Pass | Each file calls `requireRole`. `guardedPages` in `includes/role_access.php` lists the same eight pages. `pageAllowed` for a null user returns deny for all eight. |
| `attendees.php` and `checkin.php` | Pass | Both call `requireLogin` only, then `isEventOwnerOrAdmin`. Neither calls `requireRole`. |
| Creator cannot open `staff.php` or `admin.php` | Pass | `staff.php` calls `requireRole` with `staff`. `admin.php` calls `requireRole` with `admin`. `pageAllowed` denies a creator on both pages. |
| Staff cannot open `admin.php` or the creator dashboard | Pass | `admin.php` calls `requireRole` with `admin`. `dashboard.php` calls `requireRole` with `creator`. `pageAllowed` denies staff on both pages. |
| User cannot open creator, staff, or admin pages | Pass | `dashboard.php`, `event_form.php`, and `organiser_action.php` call `requireRole` with `creator`. `staff.php` calls `requireRole` with `staff`. `admin.php` and `admin_action.php` call `requireRole` with `admin`. `pageAllowed` denies a user on those pages. |
| Role homes | Pass | `roleHome` returns `index.php` for user, `dashboard.php` for creator, `staff.php` for staff, and `admin.php` for admin. After a successful password check, `login.php` redirects with `roleHome`. |
| Creator event list filters `organiser_id` | Pass | `dashboard.php` uses `WHERE e.organiser_id=?` and binds `currentUser()['user_id']`. |
| Staff event list filters `staff_user_id` | Pass | `staff.php` joins `staff_assignments` and uses `WHERE s.staff_user_id = ?` with `currentUser()['user_id']`. |
| `register.php` rejects staff and admin | Pass | The posted role must be `user` or `creator`. Any other value adds the error `Choose a valid account type.` The form lists only those two options. |
| Create event link and event form gate | Pass | The creator dashboard links `Create event` to `event_form.php`. `event_form.php` calls `requireRole` with `creator`. |
| `php tests/role_access_test.php` | Pass | The script printed `ok`. The process exit code was 0. |
| `php -l` on every PHP file | Pass | 27 files. No `vendor` directory was present. Every file reported no syntax errors. |
| `password_verify` of the seed hash | Pass | `database/seed_week12.sql` has four seed user hashes and one unique value. `password_verify` of `Fairboard1!` against that hash printed `true`. |

## Failures

None.

## Command output

### php tests/role_access_test.php

Working directory was `EventMangement`.

```
ok
```

Exit code 0.

### php -l

`Get-ChildItem -Recurse -Filter *.php` found 27 files. No path contained `vendor`. Each command was `php -l` on that file.

```
No syntax errors detected in C:\Users\jlpsa\Desktop\man gp\EventMangement\admin.php
No syntax errors detected in C:\Users\jlpsa\Desktop\man gp\EventMangement\admin_action.php
No syntax errors detected in C:\Users\jlpsa\Desktop\man gp\EventMangement\attendees.php
No syntax errors detected in C:\Users\jlpsa\Desktop\man gp\EventMangement\booking_action.php
No syntax errors detected in C:\Users\jlpsa\Desktop\man gp\EventMangement\cancel_booking.php
No syntax errors detected in C:\Users\jlpsa\Desktop\man gp\EventMangement\checkin.php
No syntax errors detected in C:\Users\jlpsa\Desktop\man gp\EventMangement\config.example.php
No syntax errors detected in C:\Users\jlpsa\Desktop\man gp\EventMangement\config.php
No syntax errors detected in C:\Users\jlpsa\Desktop\man gp\EventMangement\dashboard.php
No syntax errors detected in C:\Users\jlpsa\Desktop\man gp\EventMangement\e.php
No syntax errors detected in C:\Users\jlpsa\Desktop\man gp\EventMangement\event.php
No syntax errors detected in C:\Users\jlpsa\Desktop\man gp\EventMangement\event_form.php
No syntax errors detected in C:\Users\jlpsa\Desktop\man gp\EventMangement\index.php
No syntax errors detected in C:\Users\jlpsa\Desktop\man gp\EventMangement\login.php
No syntax errors detected in C:\Users\jlpsa\Desktop\man gp\EventMangement\logout.php
No syntax errors detected in C:\Users\jlpsa\Desktop\man gp\EventMangement\my_bookings.php
No syntax errors detected in C:\Users\jlpsa\Desktop\man gp\EventMangement\organiser_action.php
No syntax errors detected in C:\Users\jlpsa\Desktop\man gp\EventMangement\poster.php
No syntax errors detected in C:\Users\jlpsa\Desktop\man gp\EventMangement\qr.php
No syntax errors detected in C:\Users\jlpsa\Desktop\man gp\EventMangement\register.php
No syntax errors detected in C:\Users\jlpsa\Desktop\man gp\EventMangement\staff.php
No syntax errors detected in C:\Users\jlpsa\Desktop\man gp\EventMangement\includes\bootstrap.php
No syntax errors detected in C:\Users\jlpsa\Desktop\man gp\EventMangement\includes\footer.php
No syntax errors detected in C:\Users\jlpsa\Desktop\man gp\EventMangement\includes\header.php
No syntax errors detected in C:\Users\jlpsa\Desktop\man gp\EventMangement\includes\helpers.php
No syntax errors detected in C:\Users\jlpsa\Desktop\man gp\EventMangement\includes\role_access.php
No syntax errors detected in C:\Users\jlpsa\Desktop\man gp\EventMangement\tests\role_access_test.php
```

### php -r password_verify

The expression was `password_verify('Fairboard1!', '$2y$12$N3HnUNA.TS8EUNz3Pz6bTe8UtZiSjJaeElRWor1VBZEHyl7ZfWvHa')`. That string is the hash on each of the four seed user rows.

```
true
```

A second read of `database/seed_week12.sql` found 4 hashes, 1 unique value, and `verify=true` for `Fairboard1!`.

### pageAllowed and roleHome

This call loaded `includes/role_access.php` directly. It is not `tests/role_access_test.php`. It records the matrix results in the table.

```
dashboard.php null=deny
event_form.php null=deny
staff.php null=deny
admin.php null=deny
admin_action.php null=deny
my_bookings.php null=deny
booking_action.php null=deny
cancel_booking.php null=deny
creator staff.php=deny
creator admin.php=deny
staff admin.php=deny
staff dashboard.php=deny
user dashboard.php=deny
user event_form.php=deny
user organiser_action.php=deny
user staff.php=deny
user admin.php=deny
user admin_action.php=deny
home user=index.php
home creator=dashboard.php
home staff=staff.php
home admin=admin.php
```

## Limits of the automated test

`tests/role_access_test.php` calls `pageAllowed` and `roleHome`. It does not open the page files. A page that stopped calling `requireRole` would still leave that script green. The `requireRole` results in the table come from reading those files.

`login.php` sends a person who is already logged in to `index.php` before the password check. A new successful login still uses `roleHome`. That does not fail the role-home check.
