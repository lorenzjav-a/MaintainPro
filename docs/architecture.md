# Project structure

MaintainPro is a multi-page PHP application. Root PHP files are public page and API entry points so bookmarks and existing URLs continue to work. Shared code, assets, configuration, dependencies and development tools each have a dedicated directory.

```text
MaintainPro/
├── *.php                  Public pages, auth.php, api.php and development router
├── assets/
│   ├── css/app.css        Readable application stylesheet
│   ├── js/app.js          Workspace forms and small interactive enhancements
│   ├── js/auth.js         Login, registration and password recovery
│   ├── images/favicon.svg
│   └── vendor/            Bootstrap CSS and SweetAlert browser library
├── config/                Database settings and private SMTP configuration
├── database/              All SQL in database.php; CLI entry point in setup.php
├── docs/                  Architecture, configuration, testing and requirements
├── includes/
│   ├── bootstrap.php      PHP sessions and security response headers
│   ├── page.php           Authentication and page access checks
│   ├── view.php           Escaping, icons, formatting and view helpers
│   ├── domain.php         Complaint workflow and validation
│   ├── store.php          Account rules and workflow orchestration; no SQL
│   ├── mail.php           Password-recovery mail integration
│   ├── layout/            Shared header, sidebar, top bar, mobile dock and footer
│   └── components/        Reusable dashboard, complaint and account components
├── vendor/phpmailer/      PHP mail dependency, including license and version
├── tools/                 CLI verification and SMTP diagnostics
├── tests/
│   ├── support/           Disposable database and local SMTP test helpers
│   └── tmp/               Ignored, generated browser screenshots and profiles
└── .data/                 Protected legacy SQLite data; not used by the app
```

## Pages and access

| Page | Purpose | Access |
| --- | --- | --- |
| `index.php` | Dashboard for the current role | Completed accounts |
| `complaints.php` | All Complaints / My Complaints / Work Queue | Visible complaint records |
| `history.php` | Resolution attempts, verification, rejections and referrals | Visible complaint records |
| `complaint.php?id=BR-1` | Full record, timeline, photos and permitted actions | Reporting resident, assigned team or official |
| `new-complaint.php` | Report a concern | Resident |
| `reports.php` | Reports and insights | Official |
| `solutions.php` | Verified solutions and reference cases | Official |
| `users.php` | Account management | Official |
| `user-create.php` | Create an account; display its temporary password once | Official |
| `user-edit.php?id=USER_ID` | Update role, team or active status | Official |
| `profile.php` | Own name, email and password | Completed accounts |
| `login.php` | Sign-in, resident registration, setup and recovery | Public; signed-in accounts are redirected appropriately |

## Request flow

1. A workspace page calls `br_page()` in `includes/page.php` before writing HTML.
2. `includes/bootstrap.php` starts the normal `maintainpro` PHP session. `br_actor()` checks the active account and its authentication version.
3. Page guards redirect anonymous or temporary-password users, reject unauthorized roles with HTTP 403, and use `ComplaintWorkflow::visible()` to scope records. Missing or inaccessible complaints return HTTP 404.
4. PHP renders the shared layout and page content. Links use normal PHP URLs. Searches and filters use GET parameters, so refresh, Back/Forward and bookmarks preserve them.
5. JavaScript submits JSON actions to `api.php` or `auth.php` with the CSRF header. The server independently checks permissions and validates the action.
6. Complaint writes include the version originally rendered on the page. A conflict returns HTTP 409; the user can keep the draft or reload the latest record. Old drafts are never automatically retried with a fresh version.

`includes/store.php` validates inputs and enforces account and workflow rules. It calls named methods on `MaintainProDatabase` in `database/database.php`; that file owns every SQL statement, the schema and PDO transactions. `config/database.php` contains connection settings and the connection factory only. CLI setup and test helpers also call the central database file. Test fixture writes and database removal are restricted to disposable test database names and the CLI.

The database transaction locks the complaint counter before writes to preserve complaint numbering, setup rules and consistent version checks. Passwords and recovery codes are hashed. Account creation shows a generated temporary password only in its creation response; it is not kept in browser storage and is cleared from the page on departure.

There is no SPA page renderer or shared feature modal. JavaScript handles form submission, confirmations, image previews, the mobile menu and alerts. Browse/search pages work without JavaScript; account actions and submissions require it. Sensitive forms explicitly use POST even if JavaScript fails.

## File organization rules

- Put browser assets in `assets/css`, `assets/js` or `assets/images` and update both login and workspace layout references.
- Keep third-party browser files in `assets/vendor` and PHP dependencies in `vendor`. Preserve licenses and upstream source files.
- Put configuration in `config`; keep `config/mail.local.php` ignored by Git. Use `config/mail.example.php` as the template.
- Put executable maintenance scripts in `tools` and reusable test infrastructure in `tests/support`. Every SQL statement must stay in `database/database.php`, including schema changes and test fixture queries; callers use named methods. `tests/sql-boundary.php` checks this convention during verification.
- Keep reusable markup in `includes/layout` or `includes/components`; keep page access checks before any output.
- The `config`, `includes`, `database`, `vendor`, `tools` and `tests` directories deny web access through `.htaccess`. The development router enforces equivalent restrictions. `assets/vendor` is intentionally public.
- Avoid editing bundled dependencies for formatting. `.editorconfig` establishes UTF-8, LF and indentation conventions for project code.

## Cleanup completed

The root JavaScript, stylesheet and icon were moved into `assets`. Database/SMTP settings moved into `config`; the existing private SMTP file was preserved byte for byte. PHPMailer moved from `includes/PHPMailer` to `vendor/phpmailer` with all supplied source, license and metadata unchanged. SMTP diagnostics moved into `tools`, and test helpers moved into `tests/support`.

The unused Bootstrap JavaScript bundle was removed. Twenty-seven unused CSS selectors for old modals, demo controls and loading/error placeholders were removed. The remaining stylesheet was formatted without changing declaration order. Active classes with old demo/modal names were renamed to workspace/form names. The former one-time refactor report was consolidated into these maintained architecture and testing guides.

Existing PHP page URLs, complaint IDs, database schema and account data are preserved. No database migration is needed. The old SQLite file remains protected as legacy data rather than being deleted during source cleanup.
