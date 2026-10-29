# Security/account release verification — 29 October 2026

Base: `dc3d0b616fc292f4f39f52d559bb525cb133a405` (`HEIHEI-git/EventMangement/main`). Tests used synthetic accounts in a dedicated `eventlink_defense_test` database. No production database or external infrastructure was modified.

| Check | Result |
|---|---|
| Existing HTTP regression (`tests/integration.py`) | 162 assertions passed |
| MFA, invitations, deletion and defenses (`tests/defense_integration.py`) | 57 assertions passed |
| Chromium UI/QR (`tests/browser.cjs`) | 80 assertions passed; no JavaScript/CSP console errors |
| Existing security units | 34 assertions passed |
| TOTP/encryption/recovery units | 26 assertions passed, including all six RFC 6238 Appendix B SHA-1 vectors |
| Cover storage | 6 assertions passed: owner/global quota, replacement, referenced-file retention, orphan cleanup, traversal refusal |
| Existing role matrix | Passed |
| Migration | Both legacy fixture schemas migrated twice with existing records preserved; fresh import creates 14 tables |
| Syntax | All application/test PHP files passed; application and browser-test JavaScript parsed; Python suites compiled |
| Composer | Lock synchronized without dependency version changes; validate passed with the pre-existing missing-license warning; audit reported no known advisories |

Runtime: Ubuntu 24.04 test packages, PHP 8.3.6 with Ubuntu security revisions, Sodium, MariaDB 10.11.14/InnoDB, four PHP HTTP workers, Playwright 1.62.1 and Chrome for Testing headless shell 156.0.8078.73. A full Chrome process could not start under this environment's Unix-socket restriction; the supported headless shell completed the browser checks.

## Security behaviors verified

- Password alone cannot access an MFA-enabled admin session. Admin/staff without an authenticator are restricted to enrollment. Admin cannot turn off MFA.
- Actual authenticator QR decodes to `otpauth://totp/` with SHA1, six digits and a 30-second period. A separately calculated TOTP completes enrollment and login. Enrollment codes cannot be replayed.
- Recovery codes are shown once and hashed in the DB; concurrent use in two sessions succeeds exactly once. Encrypted secret tampering is rejected.
- Staff invitation creation is admin-only. Invitation GET has no account-creation side effect; its token is hashed and cannot be consumed twice. Invitees must enroll MFA.
- Unverified attendees cannot book. Repeated creator cancellation does not resend notifications. Mutation limits return 429 and Retry-After.
- Deletion requires exact `Delete` and the current password; enabled MFA is also required by the shared reauthentication guard. Deletion removes identity, invalidates another live session, and promotes the next waiting attendee. Creator events must be resolved first; admin self-deletion and reactivation of a deleted account are blocked.
- SQL explorer shows authorized names/email while withholding password hashes; arbitrary table identifiers are refused. Existing ownership, CSRF, XSS/URL, session, booking race and rotating-QR regressions continue to pass.
- Learning/Sports/Social/Other select their cover automatically in the form, decode as images, and are used by the existing poster route. Deletion and MFA enrollment fit a 390px viewport; existing public screens also pass at 320px.

## Reproduce

Use a fresh local development config pointing to a disposable database whose name ends in `_test`. Import `database.sql`, set a random app key and `base_url=http://127.0.0.1:8080`, enable developer tools only for the synthetic demo admin (ID 4 in a fresh DB), then:

```sh
composer install --no-interaction --no-plugins
composer test
php tests/migration.php
php tests/cover_storage_test.php
EVENTLINK_DEMO_PASSWORD='FairboardDemo!2026' php tools/seed_demo.php
PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:8080 router.php
# In a separate terminal, with requests and Pillow installed:
EVENTLINK_TEST_ALLOW_MUTATION=1 python3 tests/defense_integration.py
EVENTLINK_TEST_ALLOW_MUTATION=1 python3 tests/integration.py
# With Playwright and its browser installed:
node tests/browser.cjs
```

The fixture bridge refuses HTTP and non-development/non-test DBs. Legacy role regressions provision synthetic recovery codes through that bridge; the separate defense suite performs real enrollment and independently computes TOTP values, including replay/concurrency tests. Test fixture credentials are deliberately public and must never be used in production.

## Not claimed by these results

No physical Google/Microsoft Authenticator device was available; interoperability was checked against official setup documentation, RFC vectors and actual QR decoding. SMTP delivery was tested with the private development log transport, not an external mailbox. Ubuntu production Apache/FPM, MariaDB 11.8, Cloudflare/WAF/firewall settings, backup restoration, external alert routing and GitHub branch protection still require environment-specific validation. No DDoS test or public-target penetration test was performed. See [deployment and all 39 threat mappings](DEFENSE_39_JA.md), including the residual synchronous reset-email timing issue.
