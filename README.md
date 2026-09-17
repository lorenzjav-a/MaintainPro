# MaintainPro

PHP complaint management with local accounts and persistent MySQL/MariaDB storage. Public pages use normal PHP URLs, shared layouts and the existing MaintainPro design.

## Run with XAMPP

1. Start **Apache** and **MySQL** in the XAMPP Control Panel.
2. On a new installation, run `C:\xampp\php\php.exe database\setup.php` from the project folder. The database on this PC is already configured.
3. Open **http://localhost/MaintainPro/**. An empty installation prompts you to create the first barangay-official account.
4. Residents register from the sign-in page. Officials create personnel and additional official accounts under **User management → Create account**.

PHP 8.1+ requires `pdo_mysql`, `mbstring`, `openssl`, sessions and image metadata support. The test suites also use `curl` and `dom`. XAMPP supplies these. Bootstrap CSS, SweetAlert and PHPMailer are bundled locally; no build step or CDN connection is required.

For PHP's local development server, keep MySQL running and use:

```powershell
C:\xampp\php\php.exe -S 127.0.0.1:8080 router.php
```

## Accounts and workflow

- **Residents** report concerns, suggest solutions, add requested information and verify or reopen completed work.
- **Barangay officials** assess reports, set priority, recommend actions, assign teams and manage accounts.
- **Barangay personnel** start assigned work, add progress notes and record resolutions. Personnel accounts require an assigned team.

The normal journey is **Submitted → Under Review → Assigned → In Progress → Resolved → Verified**. The app also supports reopening, returns for information, rejection, referral, photos, search, filters, history, reports, CSV export and a verified solution library.

Public registration creates residents only. The first official is created during initial setup; subsequent officials and personnel are issued accounts by an existing official. Generated temporary passwords appear once on `user-create.php`. Copy the details before leaving or refreshing and share them privately. Users must replace the temporary password before entering the workspace. Staff invitations are not emailed; password recovery uses an emailed OTP.

Account permissions, CSRF tokens, password hashing, login throttling, transaction integrity and stale-edit protection are enforced by the server. Independent accounts can be tested simultaneously using separate browser profiles, as described in the testing guide.

## Project folders

| Location | Contents |
| --- | --- |
| Root `*.php` | Public pages, JSON API, authentication endpoint and development router |
| `assets/css`, `assets/js`, `assets/images` | Application styles, scripts and images |
| `assets/vendor` | Browser dependencies |
| `config` | Database settings, SMTP template and ignored private mail settings |
| `database/database.php` | All SQL: queries, transactions, schema, setup operations and test fixtures |
| `database/setup.php` | CLI setup; `--schema` prints the schema for manual import |
| `includes` | Sessions, page guards, account and workflow rules, mail integration and shared views |
| `vendor/phpmailer` | PHP mail dependency and upstream license |
| `tools` | Verification runner and SMTP diagnostic command |
| `tests`, `tests/support` | Regression suites and isolated test infrastructure |
| `docs` | Architecture, setup, testing and requirements |

Private directories are blocked through Apache rules and the development router. The old `.data/barangayresolve.sqlite` is preserved as protected legacy data and is not used by the application. No database migration is required for the folder organization.

## Documentation

- [Architecture and page map](docs/architecture.md)
- [Database and SMTP configuration](docs/configuration.md)
- [Verification and simultaneous account testing](docs/testing.md)
- [Requirements and workflow scope](docs/requirements.md)

The private mail configuration now lives in **`config/mail.local.php`**. The existing file was moved without changing its contents.

## Verify changes

```powershell
C:\xampp\php\php.exe tools\verify.php
# Include the headless browser checks:
C:\xampp\php\php.exe tools\verify.php --browser
```

Tests use disposable databases and a local SMTP inbox. They preserve the normal workspace data and send no real email. Browsing and filtering work without JavaScript; submissions and account actions require it.
