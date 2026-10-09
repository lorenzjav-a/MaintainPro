# MaintainPro full system audit — October 10, 2026

## Scope and safety

This audit reviewed the PHP/MariaDB application, shared layouts and components, JavaScript, design system, authentication and authorization, Turnstile, PHPMailer integration, uploads, reporting/PDF export, database migrations, deployment packaging, and automated tests. Testing used randomly named disposable databases, loopback HTTP/SMTP services, isolated browser contexts, and generated fixture uploads. No production records or external recipients were used.

Every existing `.htaccess` file and `config/mail.local.php` was fingerprinted before the audit and checked again afterward. These protected files were not edited, reformatted, moved, deleted, or included in cleanup work. Private configuration values were not printed or copied into documentation.

## System inventory and boundaries

- Public routes cover the landing page, reporting, tracking, transparency, account authentication, registration, recovery, and invitation setup.
- Authenticated workspaces cover resident reporting/history, personnel concern and action-plan work, official assessment/planning/reporting, internal messaging, notifications, and profiles.
- System-administrator guards separately protect account administration, workspace settings, audit history, backups, and factory reset.
- `database/database.php` remains the only application SQL boundary; migrations remain under `database/migrations`.
- `includes/store.php` and `includes/domain.php` retain orchestration, validation, permissions, workflow transitions, and transaction boundaries.
- `assets/css/app.css` remains the active design system. Segoe UI, existing type tokens, semantic colors, controls, panels, dark mode, and responsive rules remain shared across roles.

## Verification results

The supported `tools/verify.php --browser` runner completed successfully:

- 134 first-party PHP syntax checks
- SQL-boundary validation across 132 PHP files
- 8 Turnstile validation, replay, hostname, action, and outage checks
- 167 workflow, evidence, structured validation, and authorization checks
- 52 store, account, tracking, abuse, and rule checks
- 22 secure invitation checks
- 58 notification, workload, recurrence, priority, and evidence checks
- 65 storage, rollback, follow-up, blocked-work, linking, location, audit, and backup checks
- 198 category/keypoint and weekly-selection checks
- 103 account reporting, privacy, rate-limit, concurrency, and weekly-insight checks
- 88 system upgrade, pagination, planning, feedback, migration, and ZIP restoration checks
- 12 database integrity and migration-rerun checks
- 13 resident email-verification checks
- 19 personnel action-plan checks
- 10 administrator-separation checks
- 13 team-assignment checks
- 14 deactivated-account notification checks
- 42 OTP and password-recovery checks
- 13 profile-photo checks
- 8 factory-reset authorization and cleanup checks
- 12 production configuration and canonical-routing checks
- 52 messaging visibility and authorization checks
- 36 reporting, filtering, and PDF checks
- 1,135 HTTP, page, privacy, SMTP, role, and permission checks
- 617 Chrome checks covering full workflows, browser navigation, light/dark themes, and responsive widths of 375, 390, 430, 768, 1024, 1280, 1440, and 1920px

The expected password-recovery delivery failure was an intentional mocked outage and passed its safe-error-handling assertions. The current application log contained no PHP warnings, fatal errors, or uncaught exceptions.

## Confirmed findings

### 1. Missing protected upload files — high, unresolved pending recovery source

The configured local database references five evidence files, but only two are present. Three evidence files are missing. One profile-photo file reference is also missing, and no profile file is present. Existing stored files are valid; there are no orphan files, duplicate paths, invalid images, temporary files, or test artifacts.

The application and backup code correctly preserve referenced paths and validate stored images. The mismatch is installation data, most consistent with an earlier database restore or deployment where the corresponding protected upload directories were not restored together with the database. The private project inventory contains SQL and release archives but no copy of these missing upload files.

No automatic correction was applied because deleting database references would destroy recovery metadata, and fabricating replacement evidence would be improper. Recover the exact files from an older full-system backup or the prior hosting account, place them at their original protected paths, and rerun `php tools/audit-uploads.php`. If no authentic copy exists, decide on a documented data-loss procedure before changing records.

### 2. Administrator documentation ambiguity — low, fixed

The README described User Management and all Administration areas as available to every barangay official. Runtime guards and tests correctly restrict these functions to officials with `is_system_admin`. The README and configuration guide now describe that distinction accurately; application permissions were not changed.

## UI and accessibility review

The responsive browser sweep found no horizontal overflow, missing controls, broken navigation, clipped dropdowns, or browser exceptions. Shared typography, control heights, focus treatment, touch targets, native disclosures, record-table stacking, light/dark colors, and mobile navigation passed current assertions. The recent weekly concern, action-plan editor, audit-filter, and action-plan-filter refinements use scoped shared-token CSS and preserve their form/API contracts.

Screenshots and computed typography records are generated under ignored `tests/tmp`. These contain disposable fixture data only. Physical-device testing, non-Chromium engines, formal screen-reader testing, and formal WCAG certification remain outside automated coverage.

## Security and integration review

- Turnstile fails closed for invalid, expired/reused, wrong-host, wrong-action, and transport-failure cases.
- Authentication retains CSRF checks, password hashing, login throttling, session/account-version binding, role guards, invitation hashing/expiry/single use, and OTP throttling/expiry.
- Canonical production URLs normalize to `https://www.maintainprosystem.online`; invitation paths and query tokens survive required redirects.
- Email tests use loopback SMTP. No real email was sent and no private mail configuration was altered.
- Evidence and profile endpoints revalidate stored images and enforce ownership/assignment permissions.
- Database writes retain transactions, optimistic versions, idempotency keys, foreign keys, and migration-ledger checks.
- Release rules exclude local mail configuration, `.env`, databases, logs, tests, `.git`, `.data`, backups, uploads, and ZIP artifacts.

## Deployment readiness

Application code and automated workflows are ready for release. Before production deployment, recover or formally disposition the four missing protected upload files, back up the current production database and uploads together, confirm Hostinger PHP 8.2+ extensions, keep `APP_URL` on the canonical `www` HTTPS origin, configure the Turnstile secret only in the server environment, and run `php database/setup.php` through the approved Hostinger maintenance process.

Deployment must not overwrite production `.htaccess` or `config/mail.local.php`. Upload application files selectively or preserve those protected files during extraction. Purge the Hostinger cache after deployment and perform manual production smoke checks without creating real test concerns.
