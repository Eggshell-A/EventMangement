# Security regression verification — 22 October 2026

Base: `f26023444283e4ee5ea5f412eaf297914907e401`.

**113 automated HTTP/database checks passed; 0 failed.** All 31 application
and test PHP files passed syntax lint. `php tests/role_access_test.php` passed.
Python compilation, JavaScript syntax and git diff --check passed. Installed
QR dependencies matched composer.lock: php-qrcode 5.0.5 and
php-settings-container 3.3.0. Composer package versions were not changed.

Environment: Linux, PHP 8.3.6 with pdo_mysql and mbstring, MariaDB 10.11.14
with InnoDB, and an eight-worker PHP HTTP server. Synthetic accounts lived in
a randomly named eventlink_security_ database, removed after the run.
A temporary application copy supplied its own test configuration.

| Area | Verified |
|---|---|
| CSRF/sessions | Nine POST endpoints reject missing/wrong tokens; other-session, array and pre-login tokens are rejected. Rendered forms include tokens. Rotation, expiry and role revocation work. |
| Authentication | Persistent account/IP limits, new-session bypass prevention, Retry-After, expiry recovery, forwarding-header spoof resistance, password policy and hash upgrades. |
| Current roles | Creator/admin/staff login destinations, return to an event, owner boundaries, assigned-staff editing, rejected staff creation and assignment revocation. |
| School verification | Admin-only access, input validation, verified-society plan update and atomic rollback on a forced plan-write failure. |
| Bookings | Booking/check-in/cancellation, duplicate prevention, queue privacy, FIFO promotion and claiming added capacity. |
| Concurrency | Eight final-seat requests produce one booking; simultaneous cancellations promote distinct waiters; capacity cannot shrink below bookings. |
| Failure recovery | Booking/promotion failures hide SQL details and roll back bookings, cancellations and queue changes. A missing authentication migration fails closed. |
| Rendering/schema | Stored title encoding, response headers, approved-event QR SVG, poster/external-script HTTP responses and repeatable migration. |

## Repeat

Use a disposable MySQL/MariaDB account with CREATE/DROP DATABASE and table/TRIGGER
permissions. Install existing Composer dependencies, then run on Linux/WSL:

```sh
export PHP_BIN=php
export EVENTLINK_TEST_DB_HOST=127.0.0.1
export EVENTLINK_TEST_DB_PORT=3306
export EVENTLINK_TEST_DB_USER=your_test_user
export EVENTLINK_TEST_DB_PASS=your_test_password
php tests/role_access_test.php
python3 tests/security_integration.py --output security_test_results.json
```

The suite does not use the application's current config.php or database.
Its result JSON contains every named assertion. The optional browser check needs
Node.js, Playwright and Chromium: set EVENTLINK_BROWSER_SMOKE=1 and optionally
PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH for an existing browser executable.

Chromium was unavailable, so browser JavaScript, printing, mobile layout and
actual browser CSP behavior were not verified. Production HTTPS/database TLS
and PHP 8.5.4 still require deployment-environment checks. Older week-specific
reports in docs describe historical states; use this report for restored controls.
