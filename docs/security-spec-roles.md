# Role separation

Fairboard keeps four roles apart in the database and on each request. The codes are `user`, `creator`, `staff`, and `admin`. `users.role` stores the code. The `roles` table is the lookup list. A row in that table does not grant access by itself.

## Server-side gates

Guarded scripts call `requireRole` before they read or change data. A missing session is sent to the login page. A logged-in person whose role does not match receives HTTP 403.

`includes/role_access.php` is the page matrix. It does not open a database connection. `pageAllowed` is false for a logged-out user. A role may open a guarded script only when that role is in the list for the script.

| Script | Allowed roles |
|---|---|
| `dashboard.php`, `organiser_action.php` | creator |
| `event_form.php` | creator; staff for an assigned creator’s existing event |
| `staff.php` | staff |
| `admin.php`, `admin_action.php` | admin |
| `my_bookings.php`, `booking_action.php`, `cancel_booking.php` | user |

## Registration

`register.php` accepts only `user` and `creator`. The labels are User and Event creator. A posted `staff` or `admin` value is rejected. Those two roles are assigned in the database, not on the public form.

## Data scope

Creator pages filter events with `events.organiser_id` equal to the current user id. The column name stays `organiser_id`. `isEventOwnerOrAdmin` is true for an admin, or for a creator who owns that event.

Staff pages join `staff_assignments` and keep rows whose `staff_user_id` is the current user. Assigned staff can edit existing events through the event form. Staff cannot create events or open the creator dashboard or admin queue. Assignment checks run again when an edit is saved.

Admin is the only role on `admin.php` and `admin_action.php`. Admin can verify a pending school email. User verification and the creator-plan update are transactional.

## Security restoration — 22 October 2026

All bootstrapped POST requests now require a CSRF token. Authentication uses persistent rate limits, secure session cookies, rotation and idle/absolute expiry. Roles refresh from the database on each request. See [the current security release](../SECURITY_RELEASE.md) and [verification report](../TESTING.md).
