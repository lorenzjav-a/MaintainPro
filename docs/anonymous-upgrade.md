# Anonymous concern reporting upgrade — September 21, 2026

The existing multi-page MaintainPro application was extended in place. Its Bootstrap design, MySQL records, staff login, OTP recovery, temporary passwords, version checks, assessment, priority, history, referrals, reports and historical Solution Library remain.

## Files created

- `landing.php`: public introduction and report/track/staff links.
- `report-concern.php`: structured anonymous reporting and one-time tracking receipt.
- `track.php`: private-code tracking form and safe status display.
- `public-api.php`: CSRF-protected suggestions, submission and tracking endpoints.
- `concern.php`, `concerns.php`: compatible aliases for the existing detail/list pages.
- `evidence.php`: authenticated image delivery with generated filenames.
- `includes/concern-catalog.php`: category/type/key-point choices, work choices and expandable recommendation rules.
- `includes/public-layout.php`: public layout and shared structured form fields.
- `assets/js/public.js`: dependent choices, suggestions, receipt download and tracking.
- `database/migrations/20260921_anonymous_concerns.sql`: repeatable SQL migration.
- `docs/anonymous-upgrade.md`: this implementation and setup guide.

## Files modified

- Entry points: `index.php`, `login.php`, `new-complaint.php`, `complaint.php`, `api.php`, `reports.php`, `solutions.php`, `users.php`.
- Backend: `database/database.php`, `database/setup.php`, `includes/domain.php`, `includes/store.php`, `includes/mail.php`, `includes/page.php`, `includes/view.php`.
- Shared UI: `includes/components/banner.php`, `complaint-actions.php`, `complaint-table.php`, `stats.php`, `user-form.php`; `includes/layout/header.php`, `footer.php`, `mobile.php`.
- Assets: `assets/css/app.css`, `assets/js/app.js`.
- Verification: `tests/workflow.php`, `store.php`, `password-reset.php`, `http.php`, `pages.php`, `sql-boundary.php`, `browser.php`, `browser.cjs`.
- Documentation: `README.md`, `docs/configuration.md`, `docs/architecture.md`, `docs/testing.md`, `docs/requirements.md`.

## Database and migration

Applied to the current `maintainpro` database using `database/setup.php`. A private pre-migration backup is `.data/before-anonymous-20260921.sql`; Apache and the development router deny direct access. The original four accounts and one assigned concern were preserved, with no new sample records.

Changes:

1. `complaints.resident_id` is nullable, preserving its foreign key and legacy ownership.
2. `concern_tracking` binds the concern to a unique SHA-256 tracking-code hash.
3. `public_attempts` stores hashed client buckets and timestamps for abuse limits.
4. `solution_rules` stores three curated actions per category/type.

The existing validated JSON payload is reused. Key points and suggestions are **JSON arrays**, work actions are arrays on each timeline event, and `locationDetails` is a named object. `concernType`, `assignedUserId` and `assignedName` are fields. These are structured data, not a delimiter-packed text description. This avoids duplicating the existing record model or unnecessarily adding parallel tables. Tracking secrets are kept outside the case payload and are never included in the staff API.

For another installation, run `C:\xampp\php\php.exe database\setup.php`, or select the database in phpMyAdmin and import `database/migrations/20260921_anonymous_concerns.sql`. `database/setup.php --migration` prints that migration. `--schema` prints the complete fresh-install schema including the migration. Back up before updating an installation; the migration intentionally does not drop legacy records.

## Anonymous resident workflow

Landing → category → dependent concern type → key points → required private purok/sitio, street/path and exact area → optional landmark/details/photo → three suggested actions → submit → save reference and tracking code.

There is no name, email, account, password or title input. The server generates a label such as `Pothole Concern`. References use `CON-YYYY-000123`; the existing global sequence remains monotonic across years. The original resident signup URL redirects to the public report form. Existing resident rows remain for history but cannot sign in or receive new recovery codes.

## Official workflow

Staff login → dashboard/all concerns → read structured points, private location, suggestions and evidence → assess and prioritize → record official recommendation → choose an individual personnel account → review work/evidence → close or reopen.

Officials can edit information/priority at any stage, reassign active work, manage staff names/emails/roles/teams/status, maintain recommendation rules, view all history and export structured analytics. Authorization is checked in the service and API, not just in templates. Existing return-for-information now means staff gather follow-up information; there is no anonymous reporter account to contact. Rejection and referral remain supported.

**Legacy team-only assignments:** an official must choose a specific personnel account. The original team/status/history stay intact. Personnel cannot view a private address solely because they belong to the same team. Reassignment removes the old person's access, returns active work to Assigned and retains previous evidence. Reopened cases need reassessment/reassignment.

## Personnel workflow and evidence

Login → replace temporary password if prompted → dashboard/work queue → assigned concern → select work status and action(s) → upload evidence → start/update/complete.

Work statuses include arrival, inspection, materials, ongoing work, temporary repair and completed repair. Notes are optional except when selecting Other. Completion requires Fully repaired. Every start, progress update and resolution requires a server-validated JPEG/PNG/WebP image, up to 1 MiB and 20 megapixels. Empty files, unsupported types, scripts pretending to be images and incorrect claimed MIME types are rejected.

Images reuse the existing private database storage; they are not written as executable webroot files. No original upload filename is trusted or stored. Timeline entries include random `evidenceId`, uploader `actorId`, actor name, time, image, work status and action array, inside the associated concern. `evidence.php` checks the current session, completed password setup and exact concern assignment; it serves only fixed image MIME types with a generated `evidence-RANDOM.ext` filename and restrictive headers. Supporting and completion photos remain visible alongside timeline evidence to authorized staff only.

## Three-solution recommendations

`ConcernCatalog` defines expandable rules by category/type. Selected hazards or access indicators adjust the third action to emphasize appropriate safety assessment. Exactly three actions are generated and recalculated on the server during submission; client-supplied suggestion text is ignored.

Officials can load, edit and save three public-safe actions per type in Solution Library, or restore built-in defaults. Curated overrides are reused by the public form. Historical closed-case solutions remain available privately to officials as reference material; their free-text notes are never automatically exposed to visitors. Suggestions and reporter preference are separate from the official recommendation and personnel's actual work. No paid AI service is used. A future provider can replace the suggestion service while preserving the returned three-action interface.

## Tracking and privacy

Submission returns a random 24-byte (48 hexadecimal character) tracking code once. Only its SHA-256 hash is stored. The receipt can be downloaded locally; the application does not place the secret in URLs, browser storage, cookies or staff records. Losing the code cannot be recovered through identity information because none is collected.

`track.php` requires both reference and code using POST with CSRF. The response explicitly permits only reference, category/type, general status, report/update dates and predefined work-status progress. It excludes exact location, descriptions, photos, staff identities, emails, recommendations and internal notes. Guessing a numeric ID or reference does not reveal a record. Guest reporting is limited to five attempts per client IP per hour; tracking to forty per fifteen minutes. Requests use a honeypot, bounded bodies and server-side validation. These practical limits can affect residents sharing one public connection and should be tuned to deployment needs.

## Email and remaining setup

`includes/mail.php` reuses bundled PHPMailer for notifications and existing OTP resets. Assignment emails contain the recipient's name, reference, category/type, priority and optionally a login-required link. Private location, evidence and internal notes are omitted. Assignments commit before mail is attempted. Failure returns `notification_sent: false`, displays an official-facing warning and writes a generic diagnostic without undoing the work assignment.

The existing `config/mail.local.php` remains ignored and protected. It was not overwritten. Its current settings fail local validation, so real Gmail sending is not ready. Set the sender's Gmail address and App Password there (see `config/mail.example.php`), or use `BR_SMTP_*` environment variables. Use `smtp.gmail.com`, port `587`, encryption `tls`, and an authorized sender. Set optional `BR_APP_URL` to the reachable deployment base URL. Restart Apache after changing process environment variables. Run `C:\xampp\php\php.exe tools\check-mail.php` to check SMTP without sending a message.

No email credentials were invented, exposed or committed. Tests deliver only to the loopback SMTP inbox. Real Gmail delivery still requires valid sender configuration. Existing team-only concerns need individual reassignment, and personnel emails should be reviewed in User management.

The project began with unmerged Git entries for `login.php` and the old root `styles.css`. Conflict markers were removed from the live login page and it uses `assets/css/app.css`. The old stylesheet is retained and unused. No Git commit or merge-index staging was performed; resolve/stage those pre-existing merge entries as part of your normal Git workflow.

## Verification

Run `C:\xampp\php\php.exe tools\verify.php` and `C:\xampp\php\php.exe tests\browser.php`. Suites use isolated databases and local email capture. Coverage includes anonymous submission/location/title/choices/suggestions, secret tracking, staff login/OTP/onboarding, role and direct-URL restrictions, priority/assessment/assignment, successful and failed SMTP, required evidence, completion/closure/reopening, history, structured reports/CSV, rule management, email validation, deactivation, stale writes, mobile layouts, native navigation and isolated sessions.

Desktop/mobile screenshots are in ignored `tests/tmp/`. Browser automation required execution outside the sandbox on this PC; it ran headless and did not modify the live workspace database. The live migration preserved the original row counts.

Final result: 57 first-party PHP files passed lint, SQL-boundary validation passed, and 460 functional checks passed (57 workflow, 43 store/account, 42 OTP, 279 HTTP/page/SMTP, 39 browser). XAMPP public pages and private-file restrictions were also checked directly.
