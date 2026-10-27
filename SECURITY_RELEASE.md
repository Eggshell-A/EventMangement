> Historical record from 22 October 2026. Current implementation and verification: [release notes](docs/RELEASE.md) and [test report](docs/TEST_REPORT.md). Use the test commands in [README](README.md#tests) for the current version.

# Fairboard / EventLink security restoration

Updated: 22 October 2026. Base: Eggshell-A/EventMangement main commit
`f26023444283e4ee5ea5f412eaf297914907e401`.

The 12 October security work originally targeted
`b13db694bf645781c2e01836aa7ecf9745b919b2`; that patch no longer applies directly.
This version integrates it with the current user, creator, staff and admin
roles, assigned-staff editing, school-email verification, creator plans,
booking confirmation, public event banner and FIFO waitlist promotion.
The existing PDO TLS/CA configuration remains supported.

## Restored controls

| Area | Behavior |
|---|---|
| CSRF | A bootstrap guard validates every POST handled by the application bootstrap. Login, registration, logout, staff editing, school verification, booking and check-in forms include a 32-byte random token. |
| Sessions | Cookie-only, strict IDs, HttpOnly, SameSite=Lax, ID/token rotation, 30-minute inactivity and 8-hour absolute expiry. Database roles refresh on each request. |
| Login limits | Persistent counters: 5 attempts per account and 30 per IP in 15 minutes. Successful login clears only the account counter. Blocked requests return 429 and Retry-After. |
| Registration | 10 attempts per IP in 15 minutes. Only user and creator roles can self-register. New passwords require 8–16 characters, uppercase, lowercase, a number and a symbol, with bcrypt cost 12. |
| Booking integrity | Booking, waiting, cancellation, check-in and capacity writers share the event-first lock order. Status UPDATE operations take the event row lock. |
| Waitlist | Cancellation promotes the first eligible waiting user transactionally. Failed promotion rolls back cancellation. Duplicate membership is prevented; claiming added capacity removes the waiting entry atomically. |
| Access control | Creator ownership and staff assignments remain enforced. Role/assignment removal takes effect on subsequent requests. School verification and creator-plan updates commit or roll back together. |
| Inputs and errors | Scalar POST fields, IDs, dates, text lengths and capacity bounds are validated. Native PDO prepared statements are enabled. SQL details are hidden behind a generic 503 response. |
| Browser protection | Bootstrapped responses send CSP, nosniff, DENY, same-origin Referrer-Policy and no-store. Poster printing uses an external script compatible with CSP. |

Authentication buckets hold SHA-256 digests rather than raw email/IP values.
Forwarding headers are not trusted as the client IP. Configure REMOTE_ADDR at
the web server when using a trusted proxy.

## Existing installation

1. Back up code and data and pause application writes during the update.
2. Select the existing database and import `migrations/001_security_hardening.sql`.
   It adds only auth_rate_limits and is repeatable. Do not re-import database.sql
   into an existing database.
3. Deploy the updated source/assets, retaining config.php and installed dependencies.
   Composer package versions have not changed. New installations use database.sql,
   config.example.php and composer install.
4. Use PHP 8.1 or later with pdo_mysql and mbstring. Existing configuration files
   use security defaults when the new options are absent.
5. Reload open forms and verify login, assigned-staff editing, approval,
   school-email verification, booking, cancellation and check-in.

Without the new table, login and registration return 503 rather than bypassing
rate limits. Uploading code to GitHub does not apply SQL to a running database.

Apply the exported patch from a clean checkout of the base commit above:

```sh
git apply --check /path/to/EventMangement_security_20261022.patch
git apply /path/to/EventMangement_security_20261022.patch
```

If main has moved, review conflicts before deployment. The patch excludes local
configuration, credentials, vendor files and demo videos.

## HTTPS and verification limits

After configuring production TLS and HTTP-to-HTTPS redirection, set base_url
to the real HTTPS URL and session_secure to true. Enable hsts_max_age only
after checking HTTPS. Local HTTP uses session_secure=false and hsts_max_age=0.

The current run passed **113 HTTP/database security checks**, all **31 PHP
syntax checks**, and the existing role-access tests. See [TESTING.md](TESTING.md)
and security_test_results.json. It used PHP 8.3.6 and MariaDB 10.11.14 with
synthetic accounts in an isolated temporary database, without production data.

Browser execution/printing, production HTTPS and database TLS were not exercised.
PHP 8.5.4 was not available for this run. The standalone photo.php AI image demo
does not include the application bootstrap and is outside these shared controls.
Password reset, email delivery, individual ticket QR codes and broader traffic
limiting remain separate work.
