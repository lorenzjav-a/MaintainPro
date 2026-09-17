# Testing MaintainPro

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

`tests/sql-boundary.php` scans first-party PHP string literals and embedded markup for SQL outside `database/database.php`, and rejects separate `.sql` sources. Database fixtures use guarded methods in that central file. Private local configuration, generated files and bundled dependencies are excluded from the scan.

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
2. In the Resident profile, register or sign in to a resident account.
3. In the Personnel profile, sign in and replace the temporary password when prompted.
4. Keep all profiles open. Submit a concern as Resident, assess and assign as Official, start work and resolve as Personnel, then verify or reopen as Resident. Refresh the other profile's page after each action to load the latest record.

The header shows the signed-in name and role; the workspace bar also shows the personnel team. Logging out of one profile leaves the other profiles signed in. Manual testing uses real accounts and saves records in the configured database.

Ordinary tabs/windows within one profile share a session. Multiple incognito windows in one browser generally share an incognito session. Different localhost ports do not isolate cookies. Use separate profiles or browsers for independent accounts.

There is no custom session system, role switch or impersonation endpoint. Normal login, hashing, CSRF checks, temporary-password rules and authorization remain active.

## Last verified organization pass

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
