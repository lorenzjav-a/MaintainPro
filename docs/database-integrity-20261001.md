# Database integrity audit — October 1, 2026

## Architecture and scope

Root pages and the three API entry points call shared page/session guards, then `ComplaintStore` and its domain helpers. `MaintainProDatabase` owns application SQL, transactions, schema setup, backup SQL and test fixtures. Importable migrations remain in `database/migrations`. The audit followed account, guest, concern, assignment, notification, evidence, solution, weekly plan, feedback, reporting, tracking, recovery and backup paths, along with the security guards and regression suites.

The live `maintainpro` database, the September 29 phpMyAdmin export, all five historical migrations, the migration ledger and the application error log were inspected. The previous dump repair remains at `maintainpro_fixed.sql`; it is a private data export and is ignored by Git. Apache and the development router deny direct `.sql` access.

## Schema map

All 19 application tables now use InnoDB and `utf8mb4_unicode_ci`. Unless stated below, textual foreign-key IDs are `VARCHAR(64)` and use `utf8mb4_unicode_ci`; integer rule IDs on both sides are `BIGINT UNSIGNED`. Every foreign key uses the parent primary key and has a supporting child index. All use the default restrictive `ON UPDATE` behavior.

| Table | Primary / unique keys | Other indexes | Relationships and checks |
| --- | --- | --- | --- |
| `users` | `id`; unique `email` | — | `role` is `resident`, `official` or `personnel`. |
| `complaints` | `id` | `complaints_resident`, `complaints_team`, `idx_assignment_status`, `idx_recurrence_date`, `idx_created`, `idx_status_due`, `idx_concern_match`, `idx_reporter_date` | Nullable `resident_id → users.id`, restrict delete. JSON check on `payload`. Stored generated `assigned_user_id`, `concern_type`, `due_at`, `category_name`, `purok_key`, `street_normalized`, `recurrence_key`. |
| `audit_logs` | auto `id` | `audit_created`, `actor_id` | Nullable `actor_id → users.id`, set null. JSON check on `changes`. |
| `concern_evidence` | `id`; unique `file_path` | `evidence_concern`, `uploaded_by` | `complaint_id → complaints.id`, cascade; nullable `uploaded_by → users.id`, set null. |
| `concern_feedback` | `complaint_id` | `feedback_recent`, `reporter_id` | `complaint_id → complaints.id`, cascade; nullable `reporter_id → users.id`, set null. Rating check 1–5. |
| `concern_tracking` | `complaint_id`; unique `token_hash` | — | `complaint_id → complaints.id`, cascade. |
| `duplicate_dismissals` | (`complaint_id`, `candidate_id`) | `candidate_id`, `dismissed_by` | Both complaint IDs cascade; nullable `dismissed_by → users.id`, set null. |
| `notifications` | auto `id`; unique (`user_id`, `event_key`) | `idx_notification_inbox`, `idx_notification_recent`, `related_concern_id` | `user_id → users.id`, cascade; nullable `related_concern_id → complaints.id`, set null. |
| `official_solution_rules` | auto `id`; unique `official_rule_slot` | `created_by`, `updated_by` | Nullable `created_by` and `updated_by → users.id`, set null. `sort_order` 1–3 and `active` 0/1 checks. |
| `password_resets` | `id`; unique `user_id` | — | `user_id → users.id`, cascade. |
| `weekly_action_plans` | auto `id`; unique `request_key` | `plans_week_status`, `plans_assignee`, `solution_rule_id`, `created_by` | Nullable `solution_rule_id → official_solution_rules.id`, `assigned_user_id` and `created_by → users.id`; all set null. `status` is Planned/Ongoing/Completed/Cancelled. |
| `solution_rules` | (`category`, `concern_type`) | — | JSON check on `actions`; resident guidance data. |
| `locations` | auto `id`; unique `name` | — | Managed Purok/Sitio registry; legacy free text is supported. |
| `login_attempts` | auto `id` | `attempts_bucket`, `attempts_time` | Login throttle. |
| `password_reset_requests` | auto `id` | `reset_requests_bucket`, `reset_requests_time` | Recovery throttle. |
| `public_attempts` | auto `id` | `public_attempt_bucket`, `public_attempt_time` | Guest report/track throttle. |
| `feature_alerts` | `alert_key` | — | Alert cooldown. |
| `schema_migrations` | `version` | — | SHA-256 migration checksum and applied time. |
| `settings` | `name` | — | Counter and setup flags. |

`complaints.assigned_user_id` is derived from JSON and indexed for workload queries. Application validation checks that an assignee is active personnel before writing. MariaDB's generated-column foreign-key restrictions make the JSON-derived value unsuitable for the `ON DELETE SET NULL` relationship used by ordinary assignment columns; it is checked for orphans during audits instead.

## Confirmed issues and causes

| Severity | Finding | Evidence and cause | Repair |
| --- | --- | --- | --- |
| P1 | Schema ledger was ahead of the actual schema | `20260929_system_upgrade` was recorded, but only 2 of 18 intended foreign keys existed. A phpMyAdmin import can apply DDL and ledger rows separately because MariaDB DDL implicitly commits. | New idempotent migration adds missing relationships and checks, and setup verifies the final schema even when the repair marker is already recorded. |
| P1 | Mixed collations | Live database default and `official_solution_rules`/`solution_rules` columns used `utf8mb4_general_ci`; other tables used `utf8mb4_unicode_ci`. An official-rule/user join reproduced error 1267. The historical log's notification join already succeeded against the schema found at audit time, so its exact earlier column state cannot be reconstructed. | Convert legacy textual columns and table/database defaults before adding foreign keys. No query-level collation workarounds. |
| P1 | Missing integrity checks | The live complaints JSON check, solution JSON check and two official-rule checks were absent despite earlier migration history. | Restore checks after validating existing rows. |
| P2 | Preflight could fail on mixed-collation joins | The old orphan queries used ordinary string equality before normalization. | Preflight uses conservative byte comparisons, checks all 18 relationships and refuses orphan data before adding constraints. |
| P2 | Partial legacy bootstrap could fail before migration preflight | Setup created `password_resets` with a foreign key before normalizing an existing `users` table in `utf8mb4_general_ci`; a disposable partial import reproduced MariaDB error 1005/150. | Normalize an existing legacy `users` table before creating dependent bootstrap tables. A regression case preserves the account row and completes all migrations. |
| P3 | Private SQL artifact was not ignored | `maintainpro_fixed.sql` contains real data and credential hashes. | Ignore this exact local export; retain HTTP denial. |
| P2 | Backup import depended on connection charset | Application-generated SQL backup lacked a `SET NAMES` header. | Add an explicit UTF-8 connection setting to the backup SQL. |

No data rows were changed to satisfy a constraint. The logged notification query's current join, assignment join and official-rule/user join all execute after the repair. An overlapping `complaints_resident` and `idx_reporter_date` left prefix was retained because query and migration behavior are established; it is a possible future index-tuning candidate, not a correctness failure.

## Migration and recovery

`20261001_database_integrity_repair.sql` is new. Historical migration files and their checksums were not edited. The CLI runner preflights types, orphan values, JSON, official-rule values and unique-key collisions under the target collation. It normalizes safe legacy tables, runs guarded DDL, then verifies table options, all 18 relationships and delete rules, generated complaint fields, key indexes and checks. The repair can resume after interrupted DDL. On a healthy database with the repair marker, setup verifies without rebuilding tables; if an imported dump has the marker but lacks schema objects, setup reruns the guarded repair.

MariaDB `ALTER TABLE` is not a transaction rollback boundary. A protected pre-repair snapshot is at `.data/before-integrity-repair-20261001.sql`. Restore that snapshot only to an empty database if recovery is needed; inspect and reconcile any writes made after the snapshot before switching the application to it. Future existing installations should also take a private backup before running setup.

## Data preservation and validation

Before/after `CHECKSUM TABLE ... EXTENDED` values matched for all nine tables: `users` 1135280632, `complaints` 522011416, `concern_tracking` 3594793833, `notifications` 2933380909, `audit_logs` 2216128987, `concern_evidence` 3099437714, `official_solution_rules` 4095629591, `weekly_action_plans` 0 and `concern_feedback` 0. The three account roles and two guest concerns remain. No passwords, roles, ownership, tracking rows or evidence relationships were changed.

The corrected export imported into an empty MariaDB 10.4 database whose default was `latin1`. A clone of the live schema and a pre-upgrade snapshot both upgraded successfully. Repeating setup succeeded. The repaired live database has 19 InnoDB tables, 18 foreign keys, six checks and no textual columns outside `utf8mb4_unicode_ci`. The error log had no new entries after repair.

Core verification passed with 98 PHP syntax checks, SQL-boundary validation, 939 HTTP checks and the other functional suites. The new integrity suite checks orphan refusal, invalid JSON refusal, mixed collation repair, partial imports, data checksums and repeat setup. Chromium required running the browser suite outside the sandbox; all 110 browser checks passed. Tests use disposable databases and loopback mail only.

## Remaining considerations

The local SMTP configuration exists but external delivery was not tested; `tools/check-mail.php` checks authentication without sending an email. Large SQL backups and CSV exports still materialize substantial data in PHP memory; consider streaming if the dataset grows. The prior 1267 log entries are historical and remain for diagnosis.

MariaDB documents that [DDL implicitly commits transactions](https://mariadb.com/docs/server/reference/sql-statements/transactions/sql-statements-that-cause-an-implicit-commit), that [`CONVERT TO CHARACTER SET` converts existing text columns](https://mariadb.com/docs/server/reference/data-types/string-data-types/character-sets/setting-character-sets-and-collations), and that [generated-column foreign keys have restricted delete/update actions](https://mariadb.com/docs/server/reference/sql-statements/data-definition/create/generated-columns). These rules informed the migration order and backup requirement.
