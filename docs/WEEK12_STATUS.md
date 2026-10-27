> Historical milestone status. Current features and limitations are recorded in [RELEASE.md](RELEASE.md).

# Week 1-2 status

Verdicts below are against the Log Task 2 four-role model (user, creator, staff, admin). They are not a claim that the old three-role demo failed its own tests.

## Week 1 checklist

This is the cloned EventLink tree before the week 2 role edit.

| Check | Verdict | Evidence |
|---|---|---|
| Four roles user, creator, staff, admin | Fail | `users.role` was `attendee`, `organiser`, `admin`. No staff role. |
| `roles` lookup table | Fail | Not in `database.sql`. |
| `staff_assignments` | Fail | Not in the schema or the PHP pages. |
| Creator owns events and can open Create event | Pass | `dashboard.php` and `event_form.php` already filter and insert `events.organiser_id`. The UI called that person an organiser. |
| Public list is approved future events | Pass | `index.php` query. |
| Booking, cancel, waitlist, poster, QR | Pass | Those files were present. They were not part of the role gap. |
| Admin is not a public registration choice | Pass | `register.php` allowed only attendee and organiser. |
| Staff page and role-based login home | Fail | No `staff.php`. Login always redirected to `index.php`. |
| Import runs inside Aiven `defaultdb` | Fail | `database.sql` executed `CREATE DATABASE` and `USE eventlink`. |
| CSRF, rate limit, session timeout | Not done | Not present. Not scored as week 1 passes. |

## Week 2 checklist

This is the tree after the role edit.

| Check | Verdict | Evidence |
|---|---|---|
| Schema roles, four-value `users.role`, `staff_assignments` | Pass in files | `database.sql`. `CREATE DATABASE` / `USE` are comments. |
| Idempotent migration attendee to user, organiser to creator | Pass as a script. Live Aiven `defaultdb` was empty, so `database.sql` was applied instead of the alter script | `database/migrate_week12.sql` remains for an existing EventLink database. |
| Page matrix with no database dependency | Pass | `php tests/role_access_test.php`. |
| Login uses `roleHome` | Pass | `login.php`. |
| Public registration is user or creator only | Pass | `register.php` rejects any other posted role. |
| Nav and visible role words | Pass | Header brand Fairboard. Creator, user, staff, and admin links. Booked-people lists still say attendees. |
| `staff.php` lists assigned creators' events only | Pass in code | Empty copy is "No events are assigned to you." No verification buttons. Not clicked in a browser this run. |
| Admin approval queue kept, plus read-only users | Pass in code | `admin.php`. No create, disable, reset, or verify actions. |
| Aiven SSL options without a real password in Git | Pass | `includes/bootstrap.php` and `config.example.php`. Local `config.php` is gitignored and connects with `ca.pem` and SSL verify on. |
| Seed four users and one staff assignment | Pass on Aiven `defaultdb` | `database/seed_week12.sql`. Four accounts, password check passed, one staff-to-creator assignment. |
| Booking, waitlist, poster, QR still present | Pass | Those files were not removed. `organiser_id` was not renamed. |
| CSRF, rate limits, session timeout | Not done | Still absent. Do not mark them complete. |
| Week 3 booking and school-email work | Not done | Out of this change. |
