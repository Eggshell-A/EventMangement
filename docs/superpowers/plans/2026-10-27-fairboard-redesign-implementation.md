# Fairboard Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver a mobile-first four-role Fairboard UI, complete every Week 3/4 worklog requirement, and make all visible actions agree with server-side authorization.

**Architecture:** Keep the existing PHP 8 server-rendered application and MySQL database. Introduce small shared modules for security, booking policy, event access and reusable view components, then migrate each role page onto the same design system. Database changes are additive and supplied both in the fresh schema and an idempotent migration.

**Tech Stack:** PHP 8, PDO/MySQL, HTML5, CSS, plain JavaScript, PHP CLI tests, agent-browser.

**Spec:** `docs/superpowers/specs/2026-10-27-fairboard-ui-redesign-design.md`

## Global Constraints

- Preserve PHP 8, MySQL, server-rendered HTML, CSS and plain JavaScript; do not add a large front-end framework.
- Product UI copy remains English; design and final audit documentation remains Traditional Chinese.
- Use a mobile-first, low-data-capable interface and do not copy competitor branding or sold-out wording.
- Roles remain `user`, `creator`, `staff`, `admin`; public registration exposes only user and creator.
- Payments, stored cards, seat selection and public Staff profiles remain out of scope.
- All state changes require server-side authorization and CSRF validation.

## Review Focus

- Two users attempt to take the final place concurrently: exactly one booking succeeds and capacity is not exceeded. Covered in Task 3 transaction tests.
- A Staff user supplies an unassigned event ID manually: read and write requests return 403 without changing data. Covered in Task 2 access tests.
- Capacity changes after waitlist refills: the refill allowance remains based on original capacity and previously used refills. Covered in Task 3 policy tests.
- A disabled account has an old live session: the next guarded request signs it out and denies access. Covered in Task 1 security tests.
- Search and filters receive empty, unknown or malformed values: the public page remains usable and safely falls back to supported values. Covered in Task 4 query tests.

---

### Task 1: Security foundation and account state

**Files:**
- Create: `includes/security.php`
- Create: `tests/security_test.php`
- Modify: `includes/bootstrap.php`
- Modify: `includes/header.php`
- Modify: `login.php`
- Modify: all PHP files containing state-changing forms
- Modify: `database.sql`
- Create: `database/migrate_redesign.sql`

**Interfaces:**
- Produces: `csrfToken(): string`, `csrfField(): string`, `requireValidCsrf(): void`, `touchSession(): void`, `recordLoginFailure(string $key): void`, `loginAllowed(string $key): bool`.
- Produces database fields `users.is_active`, `users.failed_login_count`, `users.last_failed_login_at`.

- [ ] **Step 1: Write failing security tests** for stable session CSRF tokens, invalid-token rejection, idle-session expiry, throttled repeated login failure, and disabled-session rejection.
- [ ] **Step 2: Run `php tests/security_test.php`** and confirm it fails because security helpers do not exist.
- [ ] **Step 3: Add schema and migration fields** for account activity and login failure tracking.
- [ ] **Step 4: Implement security helpers and bootstrap enforcement** with HttpOnly, SameSite=Lax cookies and a 30-minute idle timeout.
- [ ] **Step 5: Add CSRF hidden fields and validation** to every POST action, including logout, booking, cancellation, event edit, check-in and admin actions.
- [ ] **Step 6: Implement login throttling and active-account checks** without revealing whether an email exists.
- [ ] **Step 7: Run `php tests/security_test.php` and PHP syntax checks**; expect all PASS.
- [ ] **Step 8: Commit** with `feat: add Fairboard security foundation`.

### Task 2: Role-consistent event and account administration

**Files:**
- Create: `includes/event_access.php`
- Create: `tests/event_access_test.php`
- Modify: `includes/helpers.php`
- Modify: `includes/role_access.php`
- Modify: `event_form.php`
- Modify: `staff.php`
- Modify: `attendees.php`
- Modify: `checkin.php`
- Modify: `admin.php`
- Modify: `admin_action.php`
- Modify: `database.sql`
- Modify: `database/migrate_redesign.sql`

**Interfaces:**
- Produces: `canEditEvent(PDO $pdo, array $user, array $event): bool`, `canManageAttendees(PDO $pdo, array $user, array $event): bool`.
- Produces Admin actions `assign_staff`, `unassign_staff`, `toggle_account`.
- Consumes Task 1 CSRF and active-account enforcement.

- [ ] **Step 1: Write failing role tests** covering Creator ownership, assigned/unassigned Staff, Admin boundaries, Staff attendee/check-in access and Staff inability to create an event.
- [ ] **Step 2: Run `php tests/event_access_test.php`** and verify the new interfaces fail.
- [ ] **Step 3: Implement shared event-access helpers** and use them for both page rendering and write actions.
- [ ] **Step 4: Repair Staff workflow** so every visible Edit, Promotion, Attendees and Check-in link reaches an authorized page.
- [ ] **Step 5: Add Admin account activation and Staff assignment actions** using uniqueness constraints and role validation.
- [ ] **Step 6: Run role tests plus `php tests/role_access_test.php`**; expect all PASS.
- [ ] **Step 7: Commit** with `feat: align four-role workflows`.

### Task 3: Booking, FIFO and 10% refill policy

**Files:**
- Create: `includes/booking_policy.php`
- Create: `tests/booking_policy_test.php`
- Create: `tests/booking_integration_test.php`
- Modify: `booking_action.php`
- Modify: `cancel_booking.php`
- Modify: `event.php`
- Modify: `my_bookings.php`
- Modify: `booked.php`
- Modify: `database.sql`
- Modify: `database/migrate_redesign.sql`

**Interfaces:**
- Produces: `refillLimit(int $originalCapacity): int`, `canRefill(int $used, int $originalCapacity): bool`, `bookingState(array $event, ?array $booking, ?array $waiting): string`.
- Produces database fields `events.original_capacity`, `events.refill_count`.
- Consumes Task 1 CSRF and Task 2 role access.

- [ ] **Step 1: Write failing pure policy tests** for capacity 100 allowing ten refills and rejecting the eleventh, capacities below ten allowing one, and capacity increases not resetting allowance.
- [ ] **Step 2: Write failing integration tests** for final-place concurrency, ownership-protected cancellation, FIFO promotion and exhausted-refill behavior.
- [ ] **Step 3: Run both booking tests** and verify expected failures.
- [ ] **Step 4: Add original-capacity and refill-count schema/migration fields** with safe values for existing events.
- [ ] **Step 5: Implement transaction-safe booking and cancellation** using `SELECT ... FOR UPDATE`, FIFO ordering and atomic refill accounting.
- [ ] **Step 6: Expose Booked and Waiting states** on event, success and My activity pages without leaking the next person's identity.
- [ ] **Step 7: Run booking tests** and expect all PASS.
- [ ] **Step 8: Commit** with `feat: enforce booking and refill policy`.

### Task 4: Event content model and public discovery

**Files:**
- Create: `includes/event_catalog.php`
- Create: `tests/event_catalog_test.php`
- Modify: `index.php`
- Modify: `event.php`
- Modify: `event_form.php`
- Modify: `database.sql`
- Modify: `database/migrate_redesign.sql`
- Modify: `database/seed_week3.sql`
- Create: `uploads/.gitignore`

**Interfaces:**
- Produces: `eventCategories(): array`, `normaliseCatalogFilters(array $query): array`, `catalogWhere(array $filters): array{sql:string,params:array}`.
- Produces event fields `category`, `poster_path`, `social_url`, and statuses `draft`, `pending`, `approved`, `rejected`, `cancelled`, `removed`.

- [ ] **Step 1: Write failing catalog tests** for supported categories, malformed filters, past-event exclusion and approved-only public results.
- [ ] **Step 2: Run `php tests/event_catalog_test.php`** and verify failure.
- [ ] **Step 3: Add event fields and status migration** without losing existing pending/approved/rejected/removed records.
- [ ] **Step 4: Extend event create/edit** with draft/review actions, category, validated HTTP(S) social URL and safe image upload.
- [ ] **Step 5: Replace automatic banner with searchable catalog** supporting keyword, date, category and low-data GET parameters.
- [ ] **Step 6: Update event details** with consistent content hierarchy and state labels.
- [ ] **Step 7: Run catalog tests and PHP syntax checks**; expect all PASS.
- [ ] **Step 8: Commit** with `feat: add event discovery and publishing workflow`.

### Task 5: Fairboard design system and public/User UI

**Files:**
- Modify: `assets/style.css`
- Modify: `assets/home.js`
- Modify: `includes/header.php`
- Modify: `includes/footer.php`
- Modify: `index.php`
- Modify: `event.php`
- Modify: `booked.php`
- Modify: `my_bookings.php`
- Modify: `login.php`
- Modify: `register.php`

**Interfaces:**
- Consumes catalog and booking states from Tasks 3-4.
- Produces reusable CSS components for shell, ticket date, cards, status tags, forms, empty states and mobile sticky actions.

- [ ] **Step 1: Capture baseline desktop/mobile screenshots** of public, event, login and booking pages.
- [ ] **Step 2: Implement the bulletin-board/ticket design tokens and responsive shell** with visible keyboard focus and 44px touch targets.
- [ ] **Step 3: Redesign Explore and Event details** around search, consistent cards and one clear booking action.
- [ ] **Step 4: Redesign booking confirmation and My activity** into Upcoming, Waitlist and Past states with QR and cancellation guidance.
- [ ] **Step 5: Redesign authentication forms and errors** with field-adjacent help and preserved values.
- [ ] **Step 6: Use agent-browser at 390px and 1280px** to verify no horizontal scrolling, obscured action or unclear status.
- [ ] **Step 7: Commit** with `feat: redesign public booking experience`.

### Task 6: Creator, Staff and Admin workspaces

**Files:**
- Modify: `dashboard.php`
- Modify: `event_form.php`
- Modify: `staff.php`
- Modify: `admin.php`
- Modify: `attendees.php`
- Modify: `poster.php`
- Modify: `photo.php`
- Modify: `assets/style.css`

**Interfaces:**
- Consumes Tasks 1-4 authorization and content model.
- Produces role-specific overview cards, grouped event lists, accessible data tables and responsive admin sections.

- [ ] **Step 1: Redesign Creator overview** with Upcoming, Pending, Total bookings and Waitlisted metrics and grouped event cards.
- [ ] **Step 2: Redesign event form** into Basics, Date & place, Capacity, Promotion and Review sections without a multi-page dependency.
- [ ] **Step 3: Redesign Staff assigned-events workspace** with only authorized actions and a useful empty state.
- [ ] **Step 4: Redesign Admin workspace** into Review, Accounts, Assignments and History sections, including destructive confirmations.
- [ ] **Step 5: Redesign attendees/check-in and promotion actions**; distinguish Print poster from Generate AI artwork.
- [ ] **Step 6: Use agent-browser with all four seed accounts** to complete each role's primary workflow at desktop and mobile widths.
- [ ] **Step 7: Commit** with `feat: redesign role workspaces`.

### Task 7: Worklog proof, regression verification and modification list

**Files:**
- Create: `docs/WORKLOG_COMPLETION.md`
- Create: `docs/UI_UX_AUDIT_ZH.md`
- Modify: `record/Fairboard-work log.docx` only if a reliable document-preserving edit can mark verified tasks without damaging layout; otherwise leave the source unchanged and use the completion report.

**Interfaces:**
- Consumes all earlier tests and browser evidence.
- Produces requirement-by-requirement Pass/Fail evidence and the requested prioritized modification list.

- [ ] **Step 1: Run every PHP test and syntax-check every non-vendor PHP file**; record exact commands and results.
- [ ] **Step 2: Run database migration on a disposable test database** twice to prove it is idempotent, then load seeds and validate all four accounts.
- [ ] **Step 3: Dogfood the complete application** with agent-browser, documenting reproducible UX or functional issues as they are found and fixing all release-blocking issues.
- [ ] **Step 4: Verify every Week 3/4 row** against current code, tests, database state and browser evidence; do not credit file existence alone.
- [ ] **Step 5: Write Traditional Chinese UI/UX audit** with fixed issues and a prioritized optional improvement list.
- [ ] **Step 6: Run final regression suite and inspect `git diff`** for secrets, unrelated changes and inconsistent copy.
- [ ] **Step 7: Commit** with `docs: prove worklog completion and audit UI`.

## Self-review result

- Spec coverage: all role, public, worklog, security, low-data, mobile and audit requirements map to Tasks 1-7.
- Step scan: each task has a failing-test, implementation and verification cycle; visual tasks use baseline/final browser evidence.
- Type consistency: shared security, access, booking and catalog interfaces are defined once and consumed by later tasks.
- Review focus: concurrency, manual ID attacks, refill accounting, disabled sessions and malformed filters each have an owning test.
- Proportion: the plan chooses file boundaries, interfaces and proof points without transcribing implementation bodies.

