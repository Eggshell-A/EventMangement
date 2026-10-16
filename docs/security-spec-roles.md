# Role separation

Fairboard keeps four roles apart in the database and on each request. The codes are `user`, `creator`, `staff`, and `admin`. `users.role` stores the code. The `roles` table is the lookup list. A row in that table does not grant access by itself.

## Server-side gates

Guarded scripts call `requireRole` before they read or change data. A missing session is sent to the login page. A logged-in person whose role does not match receives HTTP 403.

`includes/role_access.php` is the page matrix. It does not open a database connection. `pageAllowed` is false for a logged-out user. A role may open a guarded script only when that role is in the list for the script.

| Script | Allowed roles |
|---|---|
| `dashboard.php`, `event_form.php`, `organiser_action.php` | creator |
| `staff.php` | staff |
| `admin.php`, `admin_action.php` | admin |
| `my_bookings.php`, `booking_action.php`, `cancel_booking.php` | user |

## Registration

`register.php` accepts only `user` and `creator`. The labels are User and Event creator. A posted `staff` or `admin` value is rejected. Those two roles are assigned in the database, not on the public form.

## Data scope

Creator pages filter events with `events.organiser_id` equal to the current user id. The column name stays `organiser_id`. `isEventOwnerOrAdmin` is true for an admin, or for a creator who owns that event.

Staff pages join `staff_assignments` and keep rows whose `staff_user_id` is the current user. Staff do not get the creator dashboard, the event form, or the admin queue.

Admin is the only role on `admin.php` and `admin_action.php`. The user table on the admin page is read-only.

## Not in this week

CSRF protection is not done. Rate limits are not done. Session timeout is not done.
