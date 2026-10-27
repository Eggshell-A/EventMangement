# Experience and security release — 27 October 2026

This release completes the app's account, discovery, booking, event-management and admission workflows on top of upstream commit `f26023444283e4ee5ea5f412eaf297914907e401`. It incorporates and extends the protections in the still-open security PR #1; merge this release as the combined change, then review whether #1 can be closed as superseded.

## Visible changes

- Responsive dark discovery pages, original event illustrations, category/date/search filters, popular events, accessible forms and navigation.
- Finished login and attendee/creator registration, password confirmation/reveal, email verification, one-use password reset, password changes and account profile.
- Personal live QR tickets with a 30-second expiry, a local camera decoder, pasted-code fallback and authorised manual admission. Printed posters retain permanent event-discovery QR links.
- Waiting-list status and departure, cancellation and FIFO promotion, notifications, creator capacity refill/cancellation, staff management and moderation.
- Authenticated cover studio with six built-in covers and raster uploads. The standalone unauthenticated Puter demo is replaced; third-party AI generation is not connected.
- Development-only SQL table explorer: explicit admin allowlist, ten-minute reauthentication, metadata/counts and safe row previews.
- Creator invoices and administrator recording of offline payments. No online card processing.

## Security behaviour

All application POST requests pass the same CSRF guard, including login, logout, uploads, token redemption and QR issue. Accounts use bcrypt with cost 12; new passwords require at least 12 characters, upper/lowercase, a digit and a symbol, with a 72-byte maximum to avoid bcrypt truncation. Existing hashes remain usable and are upgraded on login when needed.

Sessions use a dedicated `FAIRBOARDSESSID` cookie scoped to the configured base path, HttpOnly, SameSite=Lax and Secure on HTTPS. Login resets the session ID and CSRF token. The default idle limit is 30 minutes and absolute limit eight hours; background QR polling does not keep a session alive. Role/account status is refreshed from the database on each request. Resetting a password or disabling an account invalidates prior sessions through `auth_version`.

Login, registration, reset, verification, scanning, cover updates and sensitive reauthentication use persistent database rate limits. Keys are HMAC-derived from the application key and identities/IPs. Proxy forwarding headers are not trusted by the application; configure real client addresses at a trusted web-server boundary if needed.

Event-row locks serialize booking, cancellation, refill, capacity editing, moderation and admission. There is one consistent event-first lock order. A ticket is validated again inside the lock and the booking can be checked in once. Unassigned staff and other attendees cannot access private tickets or guest data. Disabled accounts cannot redeem tickets.

SQL values use prepared statements; public output is escaped. Developer table and column names come only from literal allowlists. Reset/verification tokens are cryptographically random, stored as SHA-256 hashes, expire, and are consumed only on POST. Uploaded images are size/dimension/MIME checked and re-encoded with GD into new JPEGs in private storage. SVG uploads and path traversal are rejected.

CSP restricts scripts/styles to the site, blocks object embedding and framing, and allows camera use only for this origin. Security headers include nosniff, no-referrer and optional HSTS. Application responses are not cached. Sensitive paths are denied by the Apache and development-server rules; Nginx needs its equivalent rules installed.

## Migration and operational requirements

- Run `composer install --no-dev`, configure a private random `app_key`, then run the additive `php tools/migrate.php`. Fresh installs import `database.sql` instead.
- Preserve existing database content, the application key and `storage/covers/`; back up before deploying.
- Use HTTPS, correct canonical `base_url`, explicit school email domains, and a production mail transport. Development mail files contain live links and stay private.
- Staff/admin roles are provisioned through a trusted administration process; public registration grants neither role.
- Database audit records are useful application evidence but are not an immutable/WORM audit system. Apply operational retention, backups and forwarding as needed. Expired tokens/mail files and unreferenced uploaded covers need an appropriate retention policy.
- QR rotation does not prevent someone relaying a currently valid code. Physical identity/admission procedures remain an operator responsibility.

Real SMTP delivery, physical phone camera permissions and an online payment gateway were not exercised against external providers. The scanner decoder and the actual displayed QR were checked together in Chromium; the upload, mail-token and offline-payment application flows were tested locally. This is not a claim of an independent penetration test or a guarantee of zero vulnerabilities.

## Reference material

- [OWASP Authentication Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html)
- [OWASP CSRF Prevention Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html)
- [OWASP Session Management Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html)
- [PHP password_hash documentation](https://www.php.net/manual/en/function.password-hash.php)
