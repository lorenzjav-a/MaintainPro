# Account reporting and weekly concerns

Implemented September 28, 2026 using the existing PHP pages, session authentication, complaint table, category catalog and shared stylesheet.

## Reporting and accounts

- Residents can register and sign in again. Existing active resident accounts work with their existing credentials and password recovery.
- Residents, officials and personnel use the same `report-concern.php` form. Dashboard, sidebar and mobile navigation include **Report Concern**.
- Staff keep their roles and permissions. **My reported concerns** is separate from personnel's assigned work queue. Ownership does not grant work or administration permissions.
- Guest reporting and private tracking remain available. When a signed-in account posts to the public submission endpoint, it uses the account submission path and allowance too.
- Account reporters open their concerns from their workspace and can respond to information requests without a guest tracking code.

## Daily allowance

`ComplaintStore::submissionAllowance()` counts existing complaints by `resident_id` during the current Asia/Manila calendar day. `submitAccount()` checks that count and inserts the concern inside the existing transaction, after acquiring the shared counter lock. The fourth submission is rejected even when requests arrive concurrently or use different sessions/endpoints. Anonymous concerns count, and changing role does not change the account ID. Follow-ups are not new concerns. Midnight starts a new allowance without a scheduled reset or duplicate counter table.

Guest requests retain the existing separate IP-based abuse controls; the per-account allowance applies to signed-in accounts.

## Identity and compatibility

The existing `complaints.resident_id` stores the authenticated submitter for every role. The role is read from `users` for identified reports. Account/role values submitted by the browser are ignored.

The existing JSON `complaints.payload` gains `isAnonymous`. No new tables, SQL columns, ALTER statements or data migration are needed. Older rows with an account ID default to identified; older guest rows default to anonymous. Existing records, schema migrations, evidence and tracking codes are retained.

`presentConcern()` is the shared presentation boundary for pages, concern API responses and CSV exports. It replaces anonymous reporter labels with **Anonymous**, suppresses their role and removes the private owner ID and timeline actor IDs. Identified reports show the stored account's name and current role. Initial anonymous timeline entries never contain the account identity. Evidence is served through the existing authorized endpoint using opaque filenames. A reporter's own account header still identifies their signed-in session.

Staff work assignments remain independently recorded and authorized. As with the existing guest form, users are reminded to avoid identifying details in their own text and photos. The private SQL backup continues to include internal accountability data and remains official-only.

## Weekly calculation and recommendations

`ConcernInsights::week()` defines Monday 00:00 through the following Monday 00:00 in Asia/Manila. The dashboard shows the inclusive Monday–Sunday date label. `weeklyComplaints()` reads existing records in this interval; `ConcernInsights::weekly()` groups by the existing category and concern type, sorts by report count, breaks ties alphabetically and returns up to five groups. All roles, guests, anonymous reports and statuses count. Linked reports remain individual submitted reports for this statistic.

The weekly section reuses `.panel`, `.panel-title`, `.section-title`, `.form-label`, `.form-text`, Bootstrap buttons, shared fonts and mobile styles. Related links reuse `complaints.php` with week/category/type filters; officials see the contributing reports, their locations, dates, priorities, statuses and permitted submitter labels. Weekly pages/API data remain official-only.

Official suggested solutions are centralized in `ConcernCatalog::OFFICIAL_SOLUTIONS`, alongside the existing category and keypoint catalog. Every category/keypoint pair has exactly three category-specific actions. Weekly analysis counts keypoints in each category/type group, ranks them by safety attention, frequency and catalog order, removes duplicate actions, and returns exactly three final solutions. Electrical, immediate-danger and power-line hazards take precedence; recurring keypoints or recurring history reserve a preventive action. A type-aware category fallback covers reports submitted without keypoints.

Resident safety guidance remains separate in `ConcernCatalog::suggestions()` because it tells residents what to do while waiting and never instructs them to perform staff work. The official solutions only support decisions: they do not save a priority, assign a team, create work records, spend resources, resolve a concern or change its status. No external API or database change is used.

## File map

| Files | Change |
| --- | --- |
| `database/database.php` | Daily and weekly queries, owner visibility for listings/evidence, resident recovery |
| `includes/store.php`, `includes/domain.php` | Account submission, allowance, identity projection, role permissions and account follow-ups |
| `includes/insights.php` | Week boundaries, top-five grouping and staff planning advice |
| `includes/page.php`, `includes/view.php` | Own-report scope, related-week guard/filter and report action |
| `report-concern.php`, `public-api.php`, `api.php` | Shared authenticated form, endpoint routing, weekly API and anonymous-safe exports |
| `login.php`, `includes/components/user-form.php`, `includes/public-layout.php` | Resident registration/sign-in and consistent account choices/copy |
| `index.php`, `complaints.php`, `complaint.php` | Daily usage, weekly summary, own reports and submitter labels |
| `includes/layout/sidebar.php`, `includes/layout/mobile.php` | Reporting and own-report navigation |
| `includes/components/submission-allowance.php`, `weekly-concerns.php` | Shared new dashboard sections |
| `includes/components/complaint-table.php`, `complaint-actions.php`, `stats.php`, `banner.php` | Report labels, owner follow-ups and resident dashboard |
| `assets/css/app.css`, `assets/js/public.js` | Shared form sizing and reuse of the existing authenticated submission handler |
| `tests/account-reporting.php`, `tests/account-reporting-http.php`, `tests/browser.cjs` | Limits, concurrency, privacy, roles, weekly results and responsive UI coverage |
| `tests/workflow.php`, `tests/store.php`, `tests/http.php`, `tools/verify.php` | Updated expectations and integration into verification |

## Verification

Run `C:\xampp\php\php.exe tools\verify.php` and `C:\xampp\php\php.exe tests\browser.php` with MySQL running. Tests create disposable databases, isolated sessions and a local SMTP inbox. No real accounts, reports or emails are created.

New checks cover all three roles; submission slots 0–3; anonymous counts; concurrent requests; role changes; calendar-day and week boundaries; identified/anonymous HTML, JSON and CSV; spoofed identity fields; work-queue separation; owner follow-ups; resident recovery; weekly counts and related filters; and desktop/mobile typography and layout. The existing workflow, assignments, notification, evidence, recurrence, account, SMTP and recovery suites also run.

Verified on September 28, 2026:

| Suite | Result |
| --- | --- |
| PHP syntax | 85 files passed |
| SQL placement | Passed; new queries are in `database/database.php` |
| Workflow | 161 checks passed |
| Store | 51 checks passed |
| Existing staff insights | 56 checks passed |
| Evidence/storage workflows | 62 checks passed |
| Account reporting and concurrency | 103 checks passed |
| Password recovery | 42 checks passed |
| HTTP, permissions and privacy | 668 checks passed |
| Headless browser, forms and responsive layout | 100 checks passed |

Total: 1,243 functional/browser checks. Desktop/mobile screenshots of reporting, registration and the weekly panel were reviewed under `tests/tmp/`. The password-recovery suite's simulated mail-failure diagnostic is expected. Normal workspace records were not changed.
