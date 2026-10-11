# EventLink

EventLink is a simple PHP 8/MySQL web application for small organisations to advertise events, manage capacity and accept bookings. It was created as an initial KV6014 Event Management MVP.

## Stack and setup

- PHP 8, MySQL, PDO, HTML/CSS and small amounts of plain JavaScript-free enhancement.
- Install XAMPP and start Apache and MySQL. Place this project in `htdocs/eventlink`.
- In phpMyAdmin, import `database.sql`. Copy `config.example.php` to `config.php`, then set the local database details and `base_url`.
- Install Composer, open a terminal in the project folder, and run `composer install` to install the QR library. The selected `chillerlan/php-qrcode` 5.x release supports PHP 8.0+ and requires `mbstring`.
- Browse to `http://localhost/eventlink/` (or match the path in `config.php`). `config.php` is ignored by Git.

## Accounts and use

Visitors register as an attendee or organiser. Admin is deliberately not publicly selectable: register an ordinary account, then use phpMyAdmin to run the documented `UPDATE users SET role = 'admin' ...` line at the end of `database.sql`. Do not put a real password or credentials in Git.

Organisers create events; each begins pending. Admin approves it before it appears publicly. Attendees can browse approved events without login, book one place, cancel, and join a FIFO waiting list after capacity is reached.

After approval, organisers can open **Print poster** from the dashboard. The poster includes a QR code and readable short link (`e.php?id=...`). Set `base_url` to a URL reachable by people scanning the poster; `localhost` only works on the computer running XAMPP.

## Important limitations

QR and poster pages use the installed Composer dependency. The MVP has no email, image upload, filters, password reset, automated waitlist offers, or QR scanning. On cancellation, the earliest waitlist entry is identified in a flash message but is not offered/converted automatically. Current test finding: a person already on the waiting list cannot manually claim capacity that later reopens; see `TESTING.md`.

Pending security/data-integrity work: CSRF tokens, booking transactions and `SELECT ... FOR UPDATE` row locks, failed-login lockout, rate limiting, CSP, session timeout, production HTTPS/Secure cookies, and email verification. Therefore do not treat this local XAMPP MVP as production-ready.

See `PROJECT_STATUS.md`, `TESTING.md`, `DEMO.md`, and `FEATURE_MATRIX.md` for an honest implementation record.
