# MaintainPro system upgrade — September 29, 2026

This upgrade preserves the PHP/MariaDB application, existing URLs, role permissions, resident guidance, guest tracking, workload balancing, evidence workflow and shared Bootstrap/SweetAlert presentation. The existing project, schema, migrations, authentication, reporting and verification infrastructure were reviewed before implementation.

## Changes delivered

| Requested area | Implementation |
| --- | --- |
| Database repair | New guarded migration adds missing foreign keys and JSON checks, aligns resident solution collation, adds useful generated/indexed matching fields, and creates structured feature tables. Preflight rejects invalid legacy references without deleting records. |
| Concern pagination | Prepared SQL count/page queries preserve search, category, status, priority, team, personnel and role scopes; lists fetch only the requested page. Dashboard counts and groups use SQL aggregates. |
| Single concern | Detail and legacy evidence lookup retrieve the requested authorized concern directly. Guest tracking continues to require its reference/token. |
| First official setup | Browser setup requires a random environment key, zero officials, rate limiting and an unset persistent completion marker. Existing installations are permanently marked initialized. |
| More information | Account reporters receive dashboard instructions and can respond from their concern; guests retain reference/tracking instructions. Staff views do not expose private account IDs. |
| Full backup | Official-only, CSRF-protected ZIP download contains SQL, referenced evidence and restore instructions. Credentials, config, reset grants, sessions and temporary files are excluded. Existing SQL download remains available. |
| Recurrence | Matching uses category, type, stable Purok ID and normalized street, with a normalized name fallback for older reports. Street/St/St. and case/spacing variations match. |
| Possible duplicates | Explainable open/recent candidates appear for officials, with View, Link and Keep Separate. Dismissals persist; officials make every linking decision. Existing manual linking remains available even without a suggestion. |
| Official action library | Separate database library with three editable ordered slots per category/type/keypoint, active switches, audit history and stale-edit protection. Existing resident guidance is unchanged. |
| Weekly action plans | Select a weekly suggested action, choose title, notes, team, optional personnel and target date. Plans retain their solution snapshot, creator, week, status, completion timestamp and outcome; dashboard and paged history show progress. |
| Reporter notifications | The existing in-app system now sends account owners important concern updates with event deduplication. Guest reports remain tracking-only. Anonymous account reporting keeps the owner's identity private. |
| Suggested target dates | Central priority windows provide optional dates during assessment/assignment. Officials can accept or edit the date; existing overdue alerts remain active. |
| Resolution feedback | One optional 1–5 rating/comment from the authenticated owner after resolution/closure; staff cannot rate work they performed. Official reports show average, count, category breakdown and recent comments. |
| Public transparency | Optional public page shows aggregate counts, categories, registered Puroks and monthly trends. Free-text/private locations, identities, notes and evidence are excluded. |

The optional staff map/heatmap is deferred. This upgrade does not introduce a coordinates workflow or external map dependency.

## Database and data preservation

New migration: `database/migrations/20260929_system_upgrade.sql`. Earlier applied migration files were not edited. Migration checksum verification now tolerates LF/CRLF line-ending differences only; changed SQL content is still rejected. `.gitattributes` keeps migration SQL in LF format.

New tables:

- `duplicate_dismissals`: concern pair, deciding official and timestamp.
- `official_solution_rules`: category/type/keypoint, action text, slot, activation, authorship, timestamps and version.
- `weekly_action_plans`: source rule and immutable selection snapshot, title/notes, team/personnel, creator, week, target, status/completion/outcome, timestamps, edit version and idempotency key.
- `concern_feedback`: unique concern, reporter, rating, comment and timestamps.

`complaints` gains generated `category_name`, `purok_key` and `street_normalized` fields; `recurrence_key` is updated to use normalized structured values. Matching and reporter/date indexes support filtering. Missing relationships for reporter ownership, tracking, notifications, password resets, audits and evidence are checked; dependent feature tables use explicit foreign keys and appropriate cascade/set-null rules. `complaints.payload` and `solution_rules.actions` receive guarded JSON validation, and `solution_rules` uses `utf8mb4_unicode_ci`.

The local migration was applied successfully. The original two accounts, one concern, tracking record, four notifications and two audit entries were preserved; concern content and account/tracking/notification/audit checksums matched before and after. The official library was initialized with 558 existing built-in actions. A protected, ignored pre-migration SQL snapshot is stored at `.data/before-system-upgrade-20260929.sql`.

Run `C:\xampp\php\php.exe database\setup.php` when updating another installation. No migration step remains for this local database. Fresh installations need `APP_SETUP_KEY`; existing installations do not. SLA windows are configurable in `config/features.php`. Full ZIP restoration requires SMTP/password recovery because exported passwords are intentionally disabled. See [configuration](configuration.md).

## File inventory

New application files:

- `action-plans.php`, `official-solutions.php`, `transparency.php`.
- `includes/planning.php`.
- `includes/components/action-plan-list.php`, `possible-duplicates.php`, `resolution-feedback.php`, `sla-recommendation.php`.
- `database/migrations/20260929_system_upgrade.sql`, `.gitattributes`.

Updated application files:

- Core data/workflow: `database/database.php`, `includes/store.php`, `includes/domain.php`, `includes/insights.php`, `includes/notifications.php`, `includes/page.php`, `includes/view.php`, `api.php`, `evidence.php`, `config/features.php`.
- Pages and navigation: `admin.php`, `backup.php`, `complaint.php`, `index.php`, `login.php`, `reports.php`, `settings.php`, `includes/public-layout.php`, `includes/layout/header.php`, `includes/layout/sidebar.php`.
- Shared UI: `assets/js/app.js`, `includes/components/complaint-actions.php`, `complaint-table.php`, `recurring-issues.php`, `resident-followups.php`, `weekly-concerns.php`.

New tests: `tests/system-upgrade.php`, `tests/system-upgrade-http.php`. Updated tests/runner: `tests/browser.cjs`, `tests/browser.php`, `tests/extended-http.php`, `tests/http.php`, `tools/verify.php`. Documentation: this report, `README.md`, `docs/architecture.md`, `docs/configuration.md` and `docs/testing.md`.

The active shared stylesheet remains `assets/css/app.css`; new pages reuse its layout, typography, panels, forms, colors and responsive rules.

## Verification

Regression suites use disposable databases, isolated uploads and a loopback SMTP inbox. No test accounts or concerns are created in the live database. Coverage includes all four reporting/workflow contexts, role/CSRF guards, daily limits, evidence, blocked work, notifications, recurrence/duplicates, library edits, plan creation/completion/history, feedback, SQL pagination, recovery, backups/restoration and audit records.

The migration is exercised on existing disposable data and executed again to check idempotence. ZIP SQL is restored into a separate disposable database, evidence contents are checked, guest codes remain usable and original account passwords are rejected after restoration. Stale library/plan submissions and unauthorized feedback are rejected server-side.

Desktop/mobile browser checks cover shared navigation, all role workflows, official action reordering, plan creation/completion and aggregate transparency. Screenshots are generated in ignored `tests/tmp/`; the new desktop action-plan and mobile library/transparency pages were visually reviewed. The optional real external SMTP service is not exercised; delivery tests use only the local inbox.

Run `tools/verify.php` for syntax and server checks, and `tests/browser.php` for isolated Chrome checks. Final run totals are recorded in [testing](testing.md#latest-system-verification).
