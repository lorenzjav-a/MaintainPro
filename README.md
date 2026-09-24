# MaintainPro

PHP community concern management with anonymous reporting, authenticated staff and persistent MySQL/MariaDB storage. Pages use normal PHP URLs, shared layouts and the existing MaintainPro design.

Staff features now include persistent in-app notifications, workload-aware assignment recommendations, 90-day recurring-issue detection, explainable priority recommendations and a staged Before & After evidence viewer. See the [implementation and setup report](docs/staff-insights-upgrade.md) and the [September 25 system repairs and verification](docs/verification-20260925.md).

## Run with XAMPP

1. Start **Apache** and **MySQL** in the XAMPP Control Panel.
2. On a new installation, run `C:\xampp\php\php.exe database\setup.php` from the project folder. The database on this PC is already configured.
3. Open **http://localhost/MaintainPro/** for the public landing page. On an empty installation, use **Staff login** to create the first official account.
4. Residents use **Report a Concern** without registering. Officials create personnel and additional official accounts under **User management → Create account**.

PHP 8.1+ requires `pdo_mysql`, `mbstring`, `openssl`, sessions and image metadata support. The test suites also use `curl` and `dom`. XAMPP supplies these. Bootstrap CSS, SweetAlert and PHPMailer are bundled locally; no build step or CDN connection is required.

For PHP's local development server, keep MySQL running and use:

```powershell
C:\xampp\php\php.exe -S 127.0.0.1:8080 router.php
```

## Accounts and workflow

- **Guests** select category, type and key points; enter a private location; optionally attach details/photo; read three temporary steps to follow while waiting for staff; save the generated reference, tracking code and guidance. No solution selection is required.
- **Barangay officials** assess, edit, prioritize, recommend, assign/reassign individual personnel, manage the Solution Library and accounts, and review/close/reopen completed work.
- **Personnel** see only individually assigned concerns, choose structured work updates and attach required image evidence. Accounts require a name, valid email and team.

The normal journey is **Submitted → Under Review → Assigned → In Progress → Resolved → Closed / reviewed**. The final state retains the internal `Verified` value for compatibility. Officials can request more information; the reporter responds using the original reference and tracking code. Rejection, referral, reopening, history, reports and CSV export remain available.

Personnel can report blocked work and receive official instructions. Officials can link reports describing the same issue, manage Purok/Sitio choices in **Workspace settings**, review **Audit history**, and download a database backup. Linked reports retain their original tracking codes and follow the primary concern's progress.

Resident registration and sign-in are retired; old account rows and concerns are preserved. Generated staff temporary passwords appear once on `user-create.php` and must be replaced at first sign-in. Share those credentials privately. Assignment notifications and password-reset OTPs use PHPMailer; staff invitations are not emailed.

Account permissions, CSRF tokens, password hashing, login throttling, transaction integrity and stale-edit protection are enforced by the server. Independent accounts can be tested simultaneously using separate browser profiles, as described in the testing guide.

## Project folders

| Location | Contents |
| --- | --- |
| Root `*.php` | Public pages, JSON API, authentication endpoint and development router |
| `assets/css`, `assets/js`, `assets/images` | Application styles, scripts and images |
| `assets/vendor` | Browser dependencies |
| `config` | Database settings, SMTP template and ignored private mail settings |
| `database/database.php` | SQL queries, transactions, schema, setup operations and guarded test fixtures |
| `database/migrations` | Importable SQL migrations, applied by the central database layer |
| `database/setup.php` | CLI setup; `--schema` prints the schema for manual import |
| `includes` | Sessions, page guards, account and workflow rules, mail integration and shared views |
| `uploads/evidence` | Protected evidence files; ignored by Git and served only through the authorized evidence endpoint |
| `vendor/phpmailer` | PHP mail dependency and upstream license |
| `tools` | Verification runner and SMTP diagnostic command |
| `tests`, `tests/support` | Regression suites and isolated test infrastructure |
| `docs` | Architecture, setup, testing and requirements |

Private directories are blocked through Apache rules and the development router. The old `.data/barangayresolve.sqlite` is preserved as protected legacy data and is not used by the application. The anonymous-report migration has been applied to this PC. Run `database/setup.php` when updating another installation. Existing team-only concerns require an official to select an individual assignee before personnel can access them.

## Documentation

- [Architecture and page map](docs/architecture.md)
- [Database and SMTP configuration](docs/configuration.md)
- [Verification and simultaneous account testing](docs/testing.md)
- [Requirements and workflow scope](docs/requirements.md)
- [Anonymous reporting upgrade, file inventory and setup](docs/anonymous-upgrade.md)
- [Temporary resident guidance and compatibility](docs/resident-guidance.md)

The private mail configuration now lives in **`config/mail.local.php`**. The existing file was moved without changing its contents.

## Verify changes

```powershell
C:\xampp\php\php.exe tools\verify.php
# Include the headless browser checks:
C:\xampp\php\php.exe tools\verify.php --browser
```

Tests use disposable databases and a local SMTP inbox. They preserve the normal workspace data and send no real email. Browsing and filtering work without JavaScript; submissions and account actions require it.
