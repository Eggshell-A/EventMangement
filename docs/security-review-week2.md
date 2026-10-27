> Historical Week 2 report. For the current implementation and validation, see [RELEASE.md](RELEASE.md) and [TEST_REPORT.md](TEST_REPORT.md).

# Week 2 role review

Source review of the week 1 and week 2 role code on 16 Oct 2026. The pages were not executed in a browser. Week 3 school-email verification was not reviewed.

## What holds

`git check-ignore -v config.php` reports `.gitignore:1:config.php`. `git ls-files` does not list `config.php`. `git grep` and `git log -S` find no `AVNS_` string and no `mysql-3cef13c3` string in tracked files or in history. The ignored local `config.php` does hold the live Aiven host and password. That file is allowed to hold them.

`C:\Users\jlpsa\Desktop\man gp\ca.pem` is outside the repo. The file is a single certificate block. It contains no private-key block. No `.pem` file is inside `EventMangement`, and `ca.pem` is not tracked. Leave the certificate outside the repo.

Each role script calls `requireRole` before it reads or changes data.

- `dashboard.php`, `event_form.php`, and `organiser_action.php` require `creator`.
- `staff.php` requires `staff`.
- `admin.php` and `admin_action.php` require `admin`.
- `my_bookings.php`, `booking_action.php`, and `cancel_booking.php` require `user`.

`register.php` accepts only `user` and `creator`. Any other posted role, including `staff` and `admin`, is rejected before the insert. `database.sql` only comments the statements that would set those two roles.

`dashboard.php` binds `events.organiser_id` to the current user. A new event inserts that same id. `organiser_action.php` loads the row with `event_id` and `organiser_id` before it adds places or marks the event removed. `staff.php` joins `staff_assignments` and binds `staff_user_id` to the current user. No other PHP script queries `staff_assignments`. `admin_action.php` updates status only after `requireRole('admin')`, and the new status comes from a fixed map.

Request values that reach SQL are passed through `prepare` and `execute`. No query in these pages concatenates `$_GET`, `$_POST`, or `$_REQUEST` into the SQL text. `includes/bootstrap.php` builds the DSN from `config.php`, not from the request.

`php tests/role_access_test.php` prints `ok`. That test calls `pageAllowed` only. None of the pages call `pageAllowed`. The request gate is the `requireRole` call on each script, and those calls match the map in `guardedPages`.

`event_form.php` selects and updates by `event_id` alone. `organiser_action.php` then updates capacity or status by `event_id` alone. Both run only after an ownership check in the same request. A creator cannot save another creator's event through those scripts. Those write statements do not repeat `organiser_id` in the `WHERE` clause.

## What does not

No state-changing form sends a CSRF token. `includes/bootstrap.php` calls `session_start` and does not store a last-activity time, so an idle session is not expired by this app. `login.php` checks the password and does not count failures or slow them down.

`cancel_booking.php` still loads the next waiting-list name and puts that name in the flash message. `showFlashes` escapes the message, so the bug discloses the name. The bug does not inject markup.

`attendees.php` calls `requireLogin` and then `isEventOwnerOrAdmin`. It does not call `requireRole`. `checkin.php` uses the same two checks. Staff cannot open either page. An admin can open both, because `isEventOwnerOrAdmin` is true for every admin.

## Class demo

Week 2 role separation is safe enough for a class demo. It is not safe enough for a public server. A person using the site cannot register as staff or admin, and a signed-in role cannot open another role's scripts. The missing CSRF checks, idle timeout, and login rate limit are enough to keep this build off the public internet. The waiting-list name in the cancel flash is a real disclosure to point at in the demo, not a bug to leave in a deployed build.
