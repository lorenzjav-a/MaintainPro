# MaintainPro Phase 1 upgrade — October 1, 2026

## Scope and architecture

The existing PHP pages and JSON APIs continue to use the shared session/CSRF guards, `ComplaintStore` for workflow and permissions, `MaintainProDatabase` for prepared SQL, and additive MariaDB migrations. Bootstrap and `assets/css/app.css` remain the visual source of truth. This release completes the requested Phase 1 critical fixes; later operations and expansion phases remain separate work.

## Changes

- Resident registration now creates an unverified account, sends a six-digit email code, limits verification attempts and resends, and blocks sign-in until verification. IP and email registration buckets limit abuse. Existing accounts retain access.
- Assigned personnel can open `my-action-plans.php`, start a plan, record notes and private image evidence, and complete it with an outcome. Assignment, changes, approaching/overdue dates, and completion use deduplicated notifications. Version checks and audit records cover writes.
- Deactivating personnel or changing their role/team checks active concerns and action plans. When work exists, an administrator must choose an eligible replacement; reassignment and account update commit together.
- `is_system_admin` separates sensitive account, location, audit, backup, and settings functions from ordinary official work. Existing officials are granted administrator access by migration; new officials start without it. The first setup official is an administrator. An administrator cannot remove their own access, and the last active administrator is protected.
- `php tools/build-release.php` writes a ZIP under private `.data/releases`. It includes application pages, assets, migrations, PHPMailer, Apache rules and the deadline CLI, while omitting local mail settings, tests, data, dumps and logs.

## Database

New migrations: `20261001_resident_email_verification`, `20261001_staff_plan_progress`, and `20261001_system_capabilities`. They add `users.email_verified`, `users.is_system_admin`, `registration_verifications`, `registration_attempts`, and `action_plan_progress`, with indexes and foreign keys for the new access paths. Historical migrations were not changed.

Before live migration, a private single-transaction backup was saved as `.data/before-phase1-20261001.sql`. The live setup runner completed and the migration ledger contains all three new versions. Pre/post checksums for eight unaffected core tables match; the `users` checksum changed because columns were added. Existing role/account counts remain one resident, one official and one personnel, all active and verified; the official has administrator access. No sample users or concerns were inserted into the live database.

## Verification and setup

`php tools/verify.php` passes PHP lint, SQL boundaries, disposable database workflows, registration, action plans, administrator rules and HTTP/loopback SMTP checks. The Chromium suite passes 113 browser checks. `tools/build-release.php` produces a valid ZIP with all migrations and protected evidence storage.

Configure SMTP using `config/mail.example.php` as the guide and keep `config/mail.local.php` private. External delivery depends on those credentials; the automated email tests use a loopback SMTP server. Continue to schedule `tools/notify-deadlines.php` for deadline notifications. The new action-plan due alerts run through that existing sweep. To package a production release, run `php tools/build-release.php` and deploy the resulting ZIP without the private `.data` directory.
