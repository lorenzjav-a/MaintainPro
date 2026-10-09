# Testing MaintainPro

## October 9, 2026 non-blocking information requests

Requesting additional reporter information is tracked independently from the operational concern status. Officials can continue assessment, assign specific personnel, and proceed with work while the response is pending. A later reporter response is appended to the timeline without undoing assignment or work progress. Existing concerns stored with the legacy `Returned for Information` status remain compatible and can resume assessment or assignment.

## October 9, 2026 tracking access verification

Tracking access is restored in the landing hero, public header, authentication pages, every account dashboard, and the shared workspace navigation. Guests continue to use their reference and private tracking code. Residents, officials, and personnel can open their own reported concerns without a code, including anonymous account reports. The signed-in tracking page retains the private-code path for guest submissions and uses a single concern conversation launcher that clears the mobile dock.

Verification passed **124 PHP syntax checks**, SQL-boundary validation across 123 PHP files, **1,989 functional/HTTP checks**, and **517 isolated Chrome checks**. Coverage includes role-specific entry points, account-scoped tracking, guest privacy, active navigation, conversations, light/dark themes, and desktop/mobile layouts. Screenshots were reviewed under `tests/tmp/`. All fixtures use disposable databases; no live accounts or concerns were created. The browser action-plan assertion now waits for the completed page to load before checking its saved version.

## October 8, 2026 production-readiness verification

The current cleanup validation passed **118 PHP syntax checks**, SQL-boundary validation across 116 PHP files, **1,846 functional/HTTP checks**, and **394 isolated Chrome checks** covering desktop and mobile layouts. The browser run also verifies that assessment, assignment, progress, blocked-work decisions, resolution, and closure return users to the workflow section they were using. Generated screenshots remain ignored under `tests/tmp/` and may be removed after review.

The September 28 [account reporting upgrade](account-reporting-upgrade.md#verification) passed 1,243 functional/browser checks and 85 PHP syntax checks. `tools/verify.php` now includes `tests/account-reporting.php`; the HTTP runner includes `tests/account-reporting-http.php`. These cover reporting by every role, anonymity, calendar limits, concurrent requests, weekly rankings and related links. Browser coverage also compares the new form and weekly panel with shared desktop/mobile typography.

## Latest system verification

October 1, 2026: the database integrity repair passed **98 PHP syntax checks**, SQL-boundary validation, the functional and HTTP suites including **10 new integrity checks** and **939 HTTP checks**, plus **110 browser checks**. See the [integrity audit](database-integrity-20261001.md) for the live before/after schema and data verification. The browser suite required execution outside the sandbox on this PC.

September 29, 2026: **1,811 functional/browser checks passed**, plus **96 PHP syntax checks** and SQL-boundary validation across **95 PHP files**. The [system upgrade report](system-upgrade-20260929.md) records the changes, migration, data preservation and setup requirements.

| Current suite | Passing checks |
| --- | ---: |
| Workflow and authorization | 162 |
| Store and accounts | 51 |
| Staff insights | 56 |
| Storage and rollback | 62 |
| Keypoint solutions | 198 |
| Account reporting | 103 |
| System upgrade, migration and ZIP restore | 88 |
| Password recovery | 42 |
| HTTP, privacy and permissions | 939 |
| Desktop/mobile Chrome | 110 |

`tests/system-upgrade.php` is included in the verification runner; `tests/system-upgrade-http.php` is included by the HTTP suite. The recovery suite intentionally tests failed email delivery and prints a generic SMTP diagnostic before passing. Desktop/mobile screenshots of the new library, action plans and transparency page were reviewed. The normal workspace database received no test accounts or concerns.

## Earlier system verification

September 25, 2026: **907 functional checks passed**, plus 81 PHP syntax checks and SQL-boundary validation across 79 PHP files. This includes 406 HTTP checks, 79 complete browser checks, and the separate account/session regressions. The [repair and verification report](verification-20260925.md) lists the failures fixed, suite totals, migration, screenshots, backup restore validation, and external SMTP limitation.

Tests isolate their databases, evidence files, application logs, sessions, and loopback SMTP delivery. No test records are written to the live database. A forced post-upload database failure verifies rollback and file cleanup. HTTP/browser runs also inspect the isolated application log for hidden PHP errors.

## Earlier anonymous workflow verification

The current suites cover guest reporting and code-based tracking, resident registration/email verification/account reporting, official administration, and individually assigned personnel. The anonymous upgrade temporarily retired resident sign-in; the later [account reporting upgrade](account-reporting-upgrade.md) restored it. See [Anonymous upgrade](anonymous-upgrade.md) only for that release's historical feature and configuration notes.

On September 21, 2026, PHP syntax, workflow, store/account, OTP and HTTP/page checks passed. Headless Chrome checks passed for desktop/mobile reporting, dependent choices, three suggestions, tracking, staff creation/onboarding, assignment email, photo-required start/progress/resolution, official closure, history, library, profile/account pages, stale-edit drafts, Back/Forward, refresh and session isolation. Screenshots are in `tests/tmp/`. SMTP tests use only a loopback inbox; real Gmail delivery is unconfigured.

On September 24, 2026, the resident-guidance update passed 68 PHP syntax checks, the SQL boundary check, 161 workflow checks, 51 store checks, 56 staff-insight checks, 42 OTP checks, 325 HTTP/page checks and 57 headless browser checks. Coverage includes removal of solution selection, server-generated resident guidance, preserved snapshots and legacy data, receipt/tracking access, official-plan separation and mobile layout. The browser run required permission outside the Windows sandbox for Chrome's test connection.

| Earlier baseline suite | Passing checks |
| --- | ---: |
| PHP syntax | 57 files |
| Workflow and evidence | 57 |
| Store, accounts and abuse controls | 43 |
| OTP recovery | 42 |
| HTTP, pages, privacy and local SMTP | 279 |
| Desktop/mobile browser workflows | 39 |

That earlier baseline had 460 functional checks in addition to syntax and SQL-boundary validation. XAMPP Apache returned 200 for the landing/report/track/login pages, 401 for unauthenticated staff API access, and 403 for private configuration, migration and backup files. At that time, the live database retained its four original accounts and one original concern.

## Run verification

Start XAMPP MySQL, then run from the project root:

```powershell
C:\xampp\php\php.exe tools\verify.php
```

The runner lints first-party PHP files and runs these suites, stopping on a failure:

```powershell
C:\xampp\php\php.exe tests\sql-boundary.php
C:\xampp\php\php.exe tests\workflow.php
C:\xampp\php\php.exe tests\store.php
C:\xampp\php\php.exe tests\features.php
C:\xampp\php\php.exe tests\workflow-storage.php
C:\xampp\php\php.exe tests\keypoint-solutions.php
C:\xampp\php\php.exe tests\account-reporting.php
C:\xampp\php\php.exe tests\system-upgrade.php
C:\xampp\php\php.exe tests\password-reset.php
C:\xampp\php\php.exe tests\http.php
```

`tests/http.php` also includes `tests/pages.php` and `tests/extended-http.php`. Its `--accounts-only` mode runs `tests/account-pages.php` for the original account creation and stale-session regressions; the browser runner supports the same option. `tests/support/database.php` creates randomly named `maintainpro_test_*` databases and removes only those databases. Test credentials need permission to create and drop test databases. The local XAMPP root account supports this. Tests do not insert records into `maintainpro`.

`tests/sql-boundary.php` scans first-party PHP string literals and embedded markup for SQL outside `database/database.php`. The explicit exception is importable SQL under `database/migrations`. Database fixtures use guarded methods in the central file. Private local configuration, generated files and bundled dependencies are excluded from the scan.

HTTP checks launch their own loopback PHP server and SMTP inbox. PHPMailer sends only to that inbox; no test messages go to real recipients. The password-reset suite intentionally simulates an SMTP failure and prints the expected generic diagnostic. `tests/tmp/` contains generated artifacts and is ignored by Git and blocked from web access.

## Browser checks

The browser runner uses an installed Chrome browser and Node.js 22+ or a compatible Electron runtime. This PC can use the existing VS Code runtime without installing Node. Set executable paths only when needed:

```powershell
$env:BR_TEST_NODE = 'C:\Program Files\nodejs\node.exe'
$env:BR_TEST_CHROME = 'C:\Program Files\Google\Chrome\Application\chrome.exe'
C:\xampp\php\php.exe tests\browser.php
```

To run syntax, server and browser checks together:

```powershell
C:\xampp\php\php.exe tools\verify.php --browser
```

The runner uses a disposable database, loopback PHP server, hidden headless Chrome and isolated browser contexts. It removes its temporary browser profile and test database afterward. Screenshots are generated in `tests/tmp/`; they contain only test accounts and records.

Coverage includes real UI form submission, onboarding, all role dashboards, complaints, assessment/assignment, progress/resolution, reopening/verification, profile updates, account creation/deactivation, stale-edit warnings, Back/Forward, refresh, opening links in another tab, mobile navigation and session isolation. HTTP checks also verify page authorization, local asset references, POST methods for sensitive forms and protection of private directories.

## Use three accounts simultaneously on localhost

Use three separate browser profiles with `http://localhost/MaintainPro/`. For example, create profiles named **MaintainPro Official**, **MaintainPro Resident** and **MaintainPro Personnel**. Separate browsers such as Chrome, Edge and Firefox also work.

1. In the Official profile, sign in to your existing administrator account. Use **User management → Create account** to issue a personnel account and assign its team. Confirm that the account shows **Pending Setup** and that its invitation email arrives.
2. In the Guest profile, open `landing.php` and report anonymously. Save the reference and tracking code.
3. In the Personnel profile, open the single-use invitation link, create a password, and then sign in normally.
4. Keep all profiles open. Submit as Guest, assess and assign a specific personnel account as Official, start/update/resolve with required evidence as Personnel, then close or reopen as Official. Track safe progress using the Guest profile. Refresh the other profile's page after each action.

The header shows the signed-in name and role; the workspace bar also shows the personnel team. Logging out of one profile leaves the other profiles signed in. Manual testing uses real accounts and saves records in the configured database.

Ordinary tabs/windows within one profile share a session. Multiple incognito windows in one browser generally share an incognito session. Different localhost ports do not isolate cookies. Use separate profiles or browsers for independent accounts.

There is no custom session system, role switch or impersonation endpoint. Normal login, invitation-token hashing, CSRF checks and authorization remain active. The temporary-password route remains only for compatible legacy accounts that were already awaiting their first password change.

## Staff features verification — September 21, 2026

The current run passed 69 first-party PHP syntax checks, the SQL boundary check, and these disposable-data suites:

| Suite | Checks |
| --- | --- |
| Workflow and image validation | 57 |
| Store and account security | 43 |
| Notifications, workload, recurrence, priority and evidence | 56 |
| Password recovery | 42 |
| HTTP, pages, CSRF, privacy, SMTP and role guards | 316 |
| Chrome desktop/mobile workflows | 52 |

Total: **566 functional checks**, plus syntax and SQL-boundary verification. The expected SMTP-failure diagnostic in password-reset tests is intentional. No real recipients are contacted: email tests use a loopback inbox. The browser test uses isolated official/personnel/anonymous browser contexts, checks notification badge/dropdown/read/link controls, selects priority recommendations, verifies evidence stages, and checks mobile overflow. Screenshots are saved in ignored `tests/tmp/` and were visually reviewed.

Run `C:\xampp\php\php.exe tools\verify.php` for PHP/API checks and `C:\xampp\php\php.exe tests\browser.php` for Chrome checks. `tools/verify.php --browser` runs both. Tests create and remove only disposable `maintainpro_test_*` databases. See [the implementation report](staff-insights-upgrade.md) for the migration, configuration and remaining scale/email limitations.

## Earlier organization verification

September 18, 2026, on the existing XAMPP installation:

| Suite | Result |
| --- | --- |
| First-party PHP syntax | 48 files passed |
| SQL boundary | All SQL confined to `database/database.php` |
| Workflow | 41 checks passed |
| Store and account security | 69 checks passed |
| Password recovery | 42 checks passed |
| HTTP, page permissions, assets and form methods | 400 checks passed |
| Headless browser workflows and navigation | 41 checks passed |

Desktop and mobile screenshots were reviewed. XAMPP Apache served the moved public assets with HTTP 200 and denied direct access to configuration, PHP dependencies, tools and test support with HTTP 403. The local router returned HTTP 404 for protected paths. Git whitespace checks passed. No database migration or edits to normal workspace records were needed.
