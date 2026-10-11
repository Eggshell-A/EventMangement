# Project status

## Completed

- PHP/PDO project structure, Git-safe configuration example and MySQL schema.
- Registration, generic-error login, logout and attendee/organiser/admin roles.
- Pending-by-default events; server-side public visibility, ownership and role checks.
- Admin approval, rejection and removal; event browsing/detail; booking, cancellation and My Bookings.
- Capacity display, duplicate constraints, FIFO basic waiting list, cancellation identifies the next queue member.
- Organiser dashboard, Add Places, protected attendee lists and manual check-in.
- Responsive, mobile-first CSS, semantic forms, output escaping and print CSS foundation.
- QR code endpoint and printable A4 poster use `chillerlan/php-qrcode` 5.x. QR generation is limited to approved events.

## In progress

- Browser and database tests passed for the core flows listed in `TESTING.md`; additional permission, duplicate, responsive and print tests remain pending.
- Existing waitlist members cannot manually claim capacity when it later becomes available; the event page continues to say they are already waiting. The required MVP only identifies the next queue member; automated offers are deferred.

## Pending security hardening

- CSRF tokens; booking transaction/row lock to avoid final-place race conditions; CSP; rate limiting; failed-login lockout; session timeout; production HTTPS and Secure cookies.

## Release 2 / 3 deferred

- Full waitlist offers/expiry/email, email verification/reminders, password reset, search/filters, images, organiser suspension, low-data/flyer/SMS, ICS, profile editing, analytics, QR scanning and reports.

## Known limitations / issues

- An event cancellation changes its status to `removed`, rather than deleting records.
- A waitlisted attendee cannot currently claim a newly available place through the event page. This is recorded as a partial waitlist workflow in `TESTING.md`; the MVP does identify the earliest person in the queue.
- No email or automatic waitlist allocation exists.
- Capacity checks are normal-request logic only; concurrent requests can overbook until transactional locking is added.

## Test environment / pending verification

- XAMPP PHP 8.2.4 and MariaDB 10.4.28 are installed; PDO/MySQL and QR SVG generation, core browser flows, and PHP syntax were tested. `ctlscript.sh status` reports MySQL stopped even while PDO connections succeed. Print-to-PDF/physical and representative tablet/desktop viewport checks remain pending.

## Recommended next steps

1. Decide whether to allow existing waitlist members to manually book reopened capacity; implement/verify that behavior.
2. Add CSRF tokens to every state-changing form.
3. Add MySQL transactions and row locking for booking.
4. Complete pending cases in `TESTING.md`, including actual A4 print and responsive review.
5. Add lockout/session hardening and CSP.
6. Build the automated waitlist offer workflow once email infrastructure is approved.
