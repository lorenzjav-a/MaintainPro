# Testing MaintainPro

## Current anonymous workflow verification

The current suites cover guest reporting and code-based tracking, official administration and individually assigned personnel. They replace the retired resident-signup/self-verification expectations while preserving staff, OTP and authorization regression checks. See [Anonymous upgrade](anonymous-upgrade.md) for the feature and configuration checklist.

On September 21, 2026, PHP syntax, workflow, store/account, OTP and HTTP/page checks passed. Headless Chrome checks passed for desktop/mobile reporting, dependent choices, three suggestions, tracking, staff creation/onboarding, assignment email, photo-required start/progress/resolution, official closure, history, library, profile/account pages, stale-edit drafts, Back/Forward, refresh and session isolation. Screenshots are in `tests/tmp/`. SMTP tests use only a loopback inbox; real Gmail delivery is unconfigured.

| Current suite | Passing checks |
| --- | ---: |
| PHP syntax | 57 files |
| Workflow and evidence | 57 |
| Store, accounts and abuse controls | 43 |
| OTP recovery | 42 |
| HTTP, pages, privacy and local SMTP | 279 |
| Desktop/mobile browser workflows | 39 |

There are 460 functional checks in addition to syntax and SQL-boundary validation. XAMPP Apache returned 200 for the landing/report/track/login pages, 401 for unauthenticated staff API access, and 403 for private configuration, migration and backup files. The live database retained its four original accounts and one original concern.

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
C:\xampp\php\php.exe tests\password-reset.php
C:\xampp\php\php.exe tests\http.php
```

`tests/http.php` also includes `tests/pages.php`. `tests/support/database.php` creates randomly named `maintainpro_test_*` databases and removes only those databases. Test credentials need permission to create and drop test databases. The local XAMPP root account supports this. Tests do not insert records into `maintainpro`.

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

1. In the Official profile, sign in to your existing official account. Use **User management → Create account** to issue a personnel account, assign its team and privately save its temporary password.
2. In the Guest profile, open `landing.php` and report anonymously. Save the reference and tracking code.
3. In the Personnel profile, sign in and replace the temporary password when prompted.
4. Keep all profiles open. Submit as Guest, assess and assign a specific personnel account as Official, start/update/resolve with required evidence as Personnel, then close or reopen as Official. Track safe progress using the Guest profile. Refresh the other profile's page after each action.

The header shows the signed-in name and role; the workspace bar also shows the personnel team. Logging out of one profile leaves the other profiles signed in. Manual testing uses real accounts and saves records in the configured database.

Ordinary tabs/windows within one profile share a session. Multiple incognito windows in one browser generally share an incognito session. Different localhost ports do not isolate cookies. Use separate profiles or browsers for independent accounts.

There is no custom session system, role switch or impersonation endpoint. Normal login, hashing, CSRF checks, temporary-password rules and authorization remain active.

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
