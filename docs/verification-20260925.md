# System repairs and verification — September 25, 2026

The Personnel error was caused by an incomplete change to photo storage: the application called missing `insertEvidence()` and `evidenceForActor()` database operations. The matching evidence table was also missing. Work updates with a photo could therefore fail with HTTP 500. The repair preserves existing records and the current PHP pages, authentication, and design.

## Repairs

- Completed the database operations used by evidence, managed locations, paged concern reads, metrics, linked reports, blocked work, audit history, and backups. All 61 database calls from the store now have implementations.
- Added transactional file cleanup: when saving a work update fails, its new photo is removed and its database changes roll back. Successful evidence retains uploader, time, concern, stage, and its timeline reference.
- Restored photo display in timelines after images move out of concern JSON. Existing inline images remain readable. Authorization follows individual assignment, including after reassignment.
- Corrected blocked-work field names, decision values, date input, and validation limits. Personnel see official instructions; resuming work or reassigning it clears the active block while preserving history.
- Connected reporter responses to the staff detail page. Follow-ups require the original tracking credentials and an outstanding information request; they return the concern to assessment. Stale tracking results no longer leave a previous follow-up form visible.
- Connected linked-report summaries, disabled separate work forms on linked reports, and excluded them from pending-work counts. Public tracking follows primary progress without exposing the primary reference, private address, staff identity, internal notes, or photos.
- Connected managed locations to the report/edit forms. Existing free-text reporting continues until locations are configured. Inactive locations cannot receive new reports; existing records retain their history and remain editable by officials.
- Made existing management operations reachable through dedicated, official-only settings, audit, blocked-work, and backup pages. Backup downloads require POST and CSRF validation.
- Verified earlier account fixes: public browser dependencies load, account creation stays inside the application, and stale session tokens can be refreshed without discarding drafts or automatically replaying writes. Stale concern versions and changed account identities remain protected.
- Reused the shared fonts, palette, layouts, labels, panels, and mobile navigation. Updated shared panel spacing and form-label widths.

## Database and files

Applied `database/migrations/20260925_workflow_storage.sql` to the existing local database after saving `.data/before-workflow-fix-20260925.sql`.

The migration adds only:

- `concern_evidence`: protected file metadata, uploader, concern, stage, dimensions, MIME type, size, and time.
- `locations`: managed Purok/Sitio names, order, availability, and timestamps.

It inserts no sample data and removes no existing tables or records. Linking and blocked-work metadata reuse the concern JSON and timeline. Existing hashed tracking tokens and account roles remain unchanged. The earlier `20260924_account_audit.sql` migration provides `audit_logs`.

New pages: `settings.php`, `audit.php`, `blocked.php`, `backup.php`.

Main repaired files: `database/database.php`, `includes/store.php`, `includes/evidence-storage.php`, `complaint.php`, shared concern action/follow-up components, public/shared layouts, `includes/view.php`, `assets/js/public.js`, and `assets/css/app.css`. `.gitignore` now excludes evidence uploads. Test application logs and photos are isolated from production.

New regression suites: `tests/workflow-storage.php` and `tests/extended-http.php` (included by the HTTP suite). The browser, workflow, HTTP, database helper, and verification runner were extended accordingly.

## Results

| Verification | Passed |
| --- | ---: |
| First-party PHP syntax | 81 files |
| SQL boundary | 79 scanned PHP files |
| Workflow and structured validation | 161 checks |
| Store, accounts, tracking, abuse controls | 51 checks |
| Notifications, workload, recurrence, priority, evidence | 56 checks |
| Storage rollback, follow-ups, blocks, links, locations, audit, backup restore | 62 checks |
| Password-reset OTP, expiry, throttling, session revocation | 42 checks |
| HTTP endpoints, pages, permissions, privacy, SMTP success/failure | 406 checks |
| Full desktop/mobile browser workflows | 79 checks |
| Focused account/session HTTP regressions | 34 checks |
| Focused account/session browser regressions | 16 checks |

**907 functional checks**, plus syntax and SQL-boundary validation. The backup was restored into an empty disposable database and checked for working authentication, records, links, evidence references, and repeatable migrations. PHP application logs and browser errors were checked. Screenshots are in ignored `tests/tmp/`.

Read-only checks against XAMPP Apache returned HTTP 200 for landing, reporting, tracking, login, Bootstrap, SweetAlert, and application CSS. Private code, logs, migration files, and raw uploads returned HTTP 403.

All test accounts, concerns, SMTP messages, and photo uploads used disposable resources. No test accounts or concerns were created in the live database. Expected SMTP failure messages in the password-reset test come from simulated delivery failures.

## Running the checks

```powershell
C:\xampp\php\php.exe tools\verify.php
C:\xampp\php\php.exe tests\browser.php
C:\xampp\php\php.exe tests\http.php --accounts-only
C:\xampp\php\php.exe tests\browser.php --accounts-only
```

Keep MySQL running. Browser checks use installed Chrome and the existing Node-compatible runtime. The Windows sandbox may require approval to launch the isolated browser.

## Configuration and practical limits

- Refresh an already-open Personnel page before retrying a failed update. No XAMPP restart is required for these PHP changes.
- On another installation, run `C:\xampp\php\php.exe database\setup.php` before using the updated application.
- Real Gmail delivery was **not tested**. SMTP success and failure were verified against the local test inbox. Keep Gmail credentials in ignored `config/mail.local.php` or the existing `BR_SMTP_*` environment settings; see [configuration](configuration.md). `tools/check-mail.php` checks connection/authentication without sending a message. An assignment remains saved when delivery fails.
- Add the barangay's actual location names under Workspace settings when ready. No locations were invented or seeded.
- SQL backups contain private records and credential hashes. Store them privately and restore only into an empty database. Back up `uploads/evidence` separately; the SQL contains file references, not the image bytes.
- Functional testing covers the scenarios above; it does not claim production-scale load testing or delivery through an external email provider.
