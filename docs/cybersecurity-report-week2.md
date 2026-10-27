> Historical Week 2 report. For the current implementation and validation, see [RELEASE.md](RELEASE.md) and [TEST_REPORT.md](TEST_REPORT.md).

# Cybersecurity report, week 2

## Week 1 code audit

The cloned EventLink tree was a three-role MVP. `users.role` was an enum of `attendee`, `organiser`, and `admin`. There was no staff role, no `roles` table, and no `staff_assignments` table. Login always sent a successful session to `index.php`. Registration offered attendee and organiser only, so admin was not a public choice. Staff did not exist yet.

Creator-owned events were already in place. `dashboard.php` and `event_form.php` filter and insert `events.organiser_id`, and the Create event control was already on the dashboard. Booking, cancellation, the waiting list, posters, and QR codes were present. This week did not remove them.

`database.sql` started with `CREATE DATABASE` and `USE eventlink`. That does not fit Aiven, which provides a single database named `defaultdb`.

CSRF tokens, booking row locks, rate limits, and session timeout were absent. This audit did not run a security test suite. It does not claim a pass count.

## What week 2 added

The role model is now user, creator, staff, and admin. A fresh import creates `roles` and `staff_assignments` and does not require `CREATE DATABASE`. `database/migrate_week12.sql` maps `attendee` to `user` and `organiser` to `creator`, then narrows the enum.

`includes/role_access.php` holds role homes and the page matrix. It has no database dependency. `tests/role_access_test.php` calls that module directly. Login redirects with `roleHome`. Registration rejects staff and admin.

`staff.php` lists events whose `organiser_id` is assigned to the current staff user. It has no school-email verification actions. `admin.php` keeps the approval queue and adds a read-only table of name, email, and role.

PDO includes `db_port` when it is set. If `db_ssl_ca` points at a readable file and the PDO constant exists, the connection sets `PDO::MYSQL_ATTR_SSL_CA`. If `db_ssl_verify` is true and the verify constant exists, the connection sets it. `config.php` is not created here. The database password stays in that local file only.

## Comparison with other products

Eventbrite's help page "Manage roles and permissions" lists Owner, Admin, two check-in roles, and custom roles. Meetup's "Managing a leadership team" lists organizer, co-organizer, assistant organizer, and event organizer, plus a one-off event host. Luma's "Adding Hosts and Managers to Your Event" lists a creator who is always a manager, hosts with or without manager access, and a separate check-in staff option. None of those public pages describe this user, creator, staff, and admin split, or a `staff_assignments` link from a staff account to a creator account.

## Residual risks

Forms still have no CSRF token. Booking still has no transaction and no `SELECT ... FOR UPDATE` row lock, so two requests can take the last place. The database password is only as safe as the uncommitted `config.php` on that machine. School-email verification, booking place-count changes, the waitlist one-tenth refill cap, and fee collection are week 3. They are not in this build.
