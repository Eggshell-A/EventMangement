# Manual testing

Environment check: XAMPP PHP 8.2.4, MariaDB 10.4.28, `pdo_mysql`, and `mbstring` were available. MariaDB was started by the user; `ctlscript.sh status` incorrectly continued to say “mysql not running”, although MariaDB accepted socket and PDO connections. PHP syntax lint passed for all 23 application PHP files. Temporary synthetic accounts/event were used for browser tests and removed afterwards; the four application tables were verified empty at cleanup.

| Area | Test | Expected result | Status |
|---|---|---|---|
| PHP syntax | Run PHP lint on every application PHP file | No syntax errors | Passed |
| Database | Import schema; inspect four tables, foreign keys and unique booking/waitlist keys | Tables and constraints are present | Passed |
| Dependencies | `composer install` using PHP 8.2.4 | QR package and autoloader install | Passed (chillerlan/php-qrcode 5.0.5) |
| Dependencies | `composer validate --no-check-publish` | Composer manifest and lock metadata validate | Passed (non-blocking missing-license warning) |
| PDO | Connect with app config and list tables | PHP PDO reaches EventLink database | Passed |
| Registration | Register attendee and organiser accounts | Account is created with selected allowed role | Passed |
| Registration | Weak password | Validation rejects it without creating an account | Passed |
| Registration | Duplicate email | Helpful validation; duplicate not created | Not tested / Pending |
| Login | Unknown email | Generic invalid credentials message | Passed |
| Login | Valid attendee/organiser/admin credentials | Session starts and role navigation appears | Passed |
| Events | Organiser creates event | Event is pending and absent publicly | Passed |
| Ownership | Another organiser changes someone else's event | Request is forbidden | Not tested / Pending |
| Admin | Approve and remove event | Status changes; only approved event is public | Passed |
| Admin | Reject event | Status changes and event stays private | Not tested / Pending |
| Booking | Attendee books available event | Confirmed booking appears and remaining count drops | Passed |
| Booking | Book same event twice | Database/app prevents duplicate | Not tested / Pending |
| Capacity | Book final place | Further attendee sees Join waiting list | Passed |
| Waiting list | Join full event | Queue membership and count display | Passed (one queue entry) |
| Waiting list | Two or more users join the full event | Queue ordering is FIFO | Not tested / Pending |
| Waiting list | Cancellation creates availability for a queued attendee | Remove booking and identify the earliest queue member without auto-booking | Passed (one queue entry; next member named) |
| Waiting list | Add Places/cancel creates availability for a queued attendee | Manual claim action, if included in the MVP | Partial: no action is shown to a user already waiting; offer workflow is deferred |
| Cancellation | Attendee cancels own booking | Booking is deleted and earliest queue member is identified | Passed |
| Privacy | Attendee opens another event's attendee list | Request is forbidden | Passed |
| Privacy | Attendee cancels another user's guessed booking ID | Request is forbidden | Not tested / Pending |
| Dashboard | Organiser views demand / adds places | Counts render; capacity increases | Passed |
| Attendees | Organiser views list / checks in attendee | Owner sees the list and can check in | Passed |
| Attendees | Other organiser opens list | Request is forbidden | Not tested / Pending |
| Accessibility | Keyboard navigate forms and actions | Focus visible; labels and controls usable | Not tested / Pending |
| QR | Generate QR SVG and load QR on an approved event poster | SVG loads in poster with short link | Passed |
| Poster | Open print view and inspect print CSS/button | Poster content and print controls render | Passed (screen only) |
| Poster | Print physical/PDF A4 page | A4 output has suitable page layout | Not tested / Pending |
| QR access | Request QR for pending, rejected, removed, or missing event | No QR is returned | Not tested / Pending |
| Responsive/print | Review phone, tablet, desktop viewports | Layout works at representative sizes | Not tested / Pending |
| Cleanup | Remove temporary browser-test fixtures | No temporary users, events, bookings or waitlist rows remain | Passed (all four table counts were 0) |
