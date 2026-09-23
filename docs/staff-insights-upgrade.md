# MaintainPro: five integrated staff features

Implemented on September 21, 2026 in the existing multi-page PHP application. Existing anonymous reporting, private tracking, staff authentication, OTP recovery, assessment, assignment emails, Solution Library, reports and account management remain in place. The centered public form and existing workspace typography are preserved.

## 1. Files created

- `notifications.php` — staff notification center with older-page navigation.
- `assets/js/notifications.js` — bell updates, read actions, priority selection helper.
- `config/features.php` — recurrence window/thresholds and due-soon interval.
- `includes/insights.php` — understandable priority, workload, recurrence and evidence rules.
- `includes/notifications.php` — reusable transactional event notifications and deadline sweep.
- `includes/components/notification-menu.php`
- `includes/components/priority-recommendation.php`
- `includes/components/workload-table.php`
- `includes/components/recurring-issues.php`
- `includes/components/concern-insights.php`
- `includes/components/evidence.php`
- `database/migrations/20260921_staff_insights.sql`
- `tools/notify-deadlines.php` — optional scheduled deadline check.
- `tests/features.php` — disposable-database regression coverage.
- This implementation report.

## 2. Files modified

- `api.php` — authenticated notification feed and CSRF-protected read actions.
- `database/database.php`, `database/setup.php` — prepared queries, indexed aggregates and migration loading.
- `includes/domain.php`, `includes/store.php` — recommendation snapshots, decisions, evidence stages, optional deadlines and notification hooks.
- `includes/page.php`, `includes/view.php` — notification page guards, small navigation-count query and bell icon.
- `includes/layout/header.php`, `sidebar.php`, `topbar.php` — script, navigation and bell.
- `includes/components/complaint-actions.php` — workload-aware assignment, optional deadline and priority acceptance controls.
- `complaint.php` — recurrence/priority information, Before & After, staged evidence and decision/deadline history.
- `index.php`, `reports.php`, `users.php` — recurring groups, comparison reports and current workload.
- `assets/css/app.css` — components that reuse the existing design and responsive layout.
- `tests/http.php`, `tests/browser.cjs`, `tools/verify.php` — API, page, browser and feature verification.
- `README.md`, `docs/architecture.md`, `docs/configuration.md`, `docs/testing.md` — usage and maintenance notes.

Files changed by earlier anonymous-reporting/UI tasks are still present; this list describes this five-feature update only. Bundled dependencies, credentials and existing account data were not replaced.

## 3. SQL/database changes

The migration has already been applied to the local `maintainpro` database. A pre-migration backup is in the protected, Git-ignored `.data/before-staff-insights-20260921.sql` file. No demo records were inserted into the workspace database.

`database/migrations/20260921_staff_insights.sql` is re-runnable on XAMPP MariaDB. It adds:

- `notifications`: persistent user-owned messages, concern reference, safe target, read status/timestamps and per-user event uniqueness.
- `feature_alerts`: delivery cooldowns for recurring alerts and the shared deadline sweep. It does **not** store recurrence/workload counts.
- Four generated columns on the existing `complaints` table: `assigned_user_id`, `concern_type`, `recurrence_key`, `due_at`. They derive from existing JSON and cannot drift from that source.
- Indexes for assignment/status, recurrence/date, created date, status/deadline and notification owner/unread/recent access.

Priority recommendations, official decision snapshots, optional deadlines and evidence stages extend the existing concern JSON/timeline. There is no duplicate image table or second copy of workload totals. Existing concern/account columns and records are retained. Generated columns also cover older structured records without rewriting their histories.

For another existing installation, back up its database and run:

```powershell
C:\xampp\php\php.exe database\setup.php
```

For manual import, apply the previous anonymous migration first if needed, then this migration. `database/setup.php --schema` prints the full schema plus both migrations; `--migration` prints both migrations. The new migration uses MariaDB generated-column and `IF NOT EXISTS` syntax; another database engine/version may need an adjusted DDL script.

## 4–5. Notifications and events

The bell shows an unread badge and the five newest messages. `notifications.php` shows 30 at a time, with older-page links. Users can mark one/all as read or open a linked concern. Clicking an ordinary notification link marks it read first. The bell refreshes every minute while the page is visible.

Officials receive new concerns (with High/Urgent explicitly identified as **recommended** priority), assignment changes, work starts, progress, completion, additional-action/material statuses, reopened work, overdue assignments and recurrence threshold alerts. Personnel receive assignment/reassignment, changed official priority or instructions, returned/reopened completed work, due-soon and overdue alerts.

Concern event notifications commit atomically with their workflow change. Events have per-user unique keys, so deadline sweeps/retries do not duplicate messages. Messages omit exact addresses, internal instructions, emails and evidence. Historical links are checked against current assignment ownership; a former assignee goes to their work queue. Direct concern/evidence URL guards still apply.

Notification APIs require an active staff account with completed password setup. Read actions require CSRF and match the logged-in owner. Anonymous users and inactive accounts cannot access the feed. No messages are stored in localStorage.

## 6–7. Workload and recommended personnel

Workload is queried from the database each time:

- **Active:** currently `Assigned` or `In Progress`, owned by the named personnel account. Waiting/material statuses are progress activities within `In Progress`, so they count.
- **Completed:** currently `Resolved` or `Verified` (displayed Closed).
- Closed/resolved/rejected/referred records do not count as active. Reopening clears the assignee until the official reassesses and assigns again.
- **Available:** 0–2 active; **Moderate:** 3–5; **High Workload:** 6+.

The assignment dropdown includes each active person's team, count and workload label. Recommendation order is relevant team first, then lowest active count, then stable name/ID order. Category-to-team mapping is in `ConcernInsights::relevantTeam()`. If no relevant-team member exists, the least-loaded active person is suggested; their actual team is visible. Recommendations never submit or automatically select an assignment. Officials may choose anyone active. User Management and Reports show Active/Completed/workload tables, including inactive accounts for reference.

## 8–9. Recurring issues

The default rolling period is **90 days**, configurable in `config/features.php`. A group matches **category + concern type + purok/sitio + street**. Case and repeated whitespace are normalized; optional prose, landmarks and exact-area descriptions are not used. Different streets/puroks remain separate. Older unstructured records are not guessed into groups. Similar incidents remain independent records in every status.

Levels: 1 Normal, 2 Repeated, 3–4 Recurring, 5+ High Recurrence. Thresholds are configurable. Official concern details show the count and authorized related-concern links. The dashboard and reports list repeated types/areas and counts, sorted by count, up to 100 groups. Aggregation uses generated indexed keys; only representative rows need location labels, rather than loading all photo payloads into PHP.

An official alert is generated on submission/structured edit when the group reaches Recurring or High Recurrence. Each group/level has a cooldown equal to the detection window; repeated page loads do not create alerts. Changing thresholds does not automatically send retroactive alerts for untouched records.

## 10–11. Priority recommendations and override

`ConcernInsights::priority()` uses category/type and validated key points. For example, electrical type risk starts at 5, an access/service disruption at 2, and routine maintenance at 1. Actual catalog key points add weights: exposed wires +4, blocking access +3, near school +2, high traffic +2, and so on. Every applied weight has a readable reason. Optional descriptions are not scored.

Score bands are Low 0–1, Medium 2–4, High 5–7, Urgent 8+. Immediate danger or visible sparks always recommends Urgent. Rules are local PHP and use no AI API.

Submission persists the recommendation, score, reasons and rule version, while retaining the existing default priority until assessment. **Accept recommendation** selects the suggested priority; the official must still save the form. A manually chosen priority is equally valid. Changed category/type/key-point drafts disable the old acceptance button until those selections are saved and the recommendation is recalculated.

Assessment/edit saves both the current system recommendation and the official decision, including actor, timestamp and whether it differed. The activity timeline retains earlier decisions. Reports show latest recommendation-versus-decision counts and overrides; untouched legacy records are excluded until reviewed.

## 12–13. Evidence and required completion images

The existing private image storage and validated upload path are reused. Events now carry explicit `evidenceType` labels:

- Initial Evidence: anonymous report photo.
- Inspection Evidence: Arrived at location / Inspection completed, or official follow-up inspection.
- Progress Evidence: other work updates.
- Completion Evidence: resolution.

Before & After shows the initial photo and latest completion photo. The evidence gallery preserves **every** staged image, actor, timestamp, work status and notes, including multiple completion attempts. Reopened/open work labels prior completion as an earlier attempt. A missing initial image is shown as missing; follow-up images no longer replace the original photo. Legacy event labels/photos have a compatibility display fallback.

Start, progress and resolution still require valid evidence on the server. Resolution also requires Fully repaired and an action taken. Existing MIME/content checks allow only JPEG/PNG/WebP, maximum 1 MiB and 20 megapixels. Generated evidence IDs and filenames are used; original uploaded filenames are not trusted. The authenticated evidence endpoint checks assignment permissions and delivers fixed image types with its existing protections. No public tracking page receives private images.

## 14. Manual configuration

The five features are ready on this PC. No external key or new dependency is needed.

- Optional rules: edit `config/features.php` for the detection window, recurrence levels or due-soon hours. Priority weights/team preferences are plainly listed in `includes/insights.php`.
- Optional target completion: set it on the assignment form, in **Asia/Manila** time. Empty means no deadline alert. The default due-soon interval is 24 hours. Saved target times also appear in history.
- Deadline checks run at most once per minute when a signed-in workspace page/feed is used. For alerts to be recorded while nobody is browsing, schedule `C:\xampp\php\php.exe C:\xampp\htdocs\MaintainPro\tools\notify-deadlines.php` every minute in Windows Task Scheduler. No task is installed automatically.
- Existing email settings remain separate. Real Gmail delivery still requires valid sender credentials/App Password in the ignored `config/mail.local.php` (or existing `BR_SMTP_*` environment settings). See `docs/configuration.md`. The new in-app notifications work even if SMTP is unavailable. Assignment emails still run after commit; failure does not undo assignment or in-app messages.

## 15. Testing and practical limits

Regression tests use disposable databases, separate local servers, test accounts and a loopback SMTP inbox. They exercise notifications/ownership/CSRF, recommendations and overrides, workload, inactive accounts, rolling recurrence/normalization/isolation/deduplication, deadline alerts, all evidence stages, mandatory valid completion images, privacy, role/direct-URL guards, existing workflows and mobile layouts. Desktop/mobile screenshots are in ignored `tests/tmp/`.

Run `C:\xampp\php\php.exe tools\verify.php` and `C:\xampp\php\php.exe tests\browser.php`. See `docs/testing.md` for the final results.

Practical limits: recurrence is a structured matching signal and does not understand aliases such as “St.” versus “Street”; officials review each incident. Historical completion ownership follows the current assigned account, as the existing workflow does. New indexed aggregates/feed avoid adding full-state reads, but older workspace/detail paths still use the existing JSON/photo loading architecture, which may need pagination/image-storage work at larger scale. No real Gmail message was sent during testing. Existing Git merge-index conflicts for `login.php` and legacy `styles.css` predate this update; runtime files pass checks, but those Git entries still need resolution before committing the wider working tree.
