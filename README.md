# MaintainPro

PHP complaint management with local accounts and persistent MySQL/MariaDB storage. The workspace starts empty, with no sample accounts, seeded complaints, or demo role switching.

## Run with XAMPP

1. Start **Apache** and **MySQL** in the XAMPP Control Panel.
2. On a new installation, run `C:\xampp\php\php.exe database\setup.php` from the project folder. The database on this PC has already been created.
3. Open **http://localhost/MaintainPro/** and create your first barangay-official account.
4. Residents can register from the sign-in page. Officials create personnel and additional official accounts under **User management**.

## Accounts and dashboards

MaintainPro has three account types: resident, barangay official, and barangay personnel. Public registration always creates a resident account. The first official is created only during initial setup; existing officials create additional officials and personnel through **User management → Create account**. Officials can also issue a resident account when needed. Personnel must have an assigned team.

When an official creates an account, MaintainPro generates a temporary password and displays it once in the creation dialog. Copy the details before closing it and share them privately with the account holder. Only a password hash is stored; the password cannot be retrieved from the account list. Staff invitations are not emailed. If the temporary password is lost, the account holder can use email OTP recovery when SMTP is configured.

The new account must sign in and replace its temporary password before accessing complaints, exports, account management, or profile settings. Successful replacement revokes earlier temporary-password sessions. Existing accounts and public resident registrations keep their normal sign-in flow. Email OTP recovery also satisfies the initial password-change requirement.

After sign-in:

- **Resident dashboard:** personal active reports, resolutions awaiting verification, verified reports, and reporting history.
- **Administrative dashboard:** new reports, assessment queue, urgent concerns, reopened concerns, team workload, and user management.
- **Personnel work queue:** assigned and in-progress tasks, sorted by priority and then oldest first; separate views show work awaiting resident verification and verified outcomes.

Account lists show **Password change required** until a newly issued account completes that step. On an existing installation, run `C:\xampp\php\php.exe database\setup.php` to add the onboarding field without changing existing accounts. This migration has already been applied on this PC.

The default database is **maintainpro**, available in **http://localhost/phpmyadmin/**. Connection defaults match local XAMPP: host `127.0.0.1`, port `3306`, username `root`, empty password.
To use different credentials, set `BR_DB_HOST`, `BR_DB_PORT`, `BR_DB_NAME`, `BR_DB_USER`, and `BR_DB_PASSWORD` in the PHP process environment, then restart Apache. Defaults are in `database/database.php`.

`database/setup.php` creates the configured database and imports `database/schema.sql`. It can be rerun without deleting records or resetting complaint IDs. For manual phpMyAdmin setup, create an empty database named `maintainpro` with collation `utf8mb4_unicode_ci`, select it, and import `database/schema.sql`.

The InnoDB tables are:

- `users`: accounts, password hashes, roles, teams, and session versions.
- `complaints`: ownership, assignment, status, version, and a JSON payload containing details, photos, and timeline history.
- `settings`: the next complaint number, initially 1.
- `login_attempts`: sign-in throttling records.
- `password_resets`: hashed email codes and single-use password-reset grants.
- `password_reset_requests`: email and IP request limits.

The old `.data/barangayresolve.sqlite` file is no longer used. Browser sessions from the former demo cannot grant account access.

## Workflow

1. A resident signs in and reports a concern, optionally adding a suggested solution and photo.
2. An official reviews it, saves a recommendation and priority, and assigns a team.
3. Personnel in that team sign in, start work, add progress notes, and record the resolution.
4. The reporting resident verifies the result or reopens the complaint with feedback.

The app also supports returns for information, rejection, referral, search and filters, dashboards, resolution history, CSV exports, and category-based comparisons with previous verified cases.
Account permissions, CSRF tokens, password hashing, login throttling, and stale-edit detection are enforced on the server. Database transactions serialize writes to protect first-account setup, permissions, complaint numbering, and version checks.

Bootstrap and SweetAlert are included in `assets/vendor`; no Node.js build or CDN connection is needed. PHP 8.1+ needs `pdo_mysql`, `mbstring`, `openssl`, sessions, and image metadata support. XAMPP supplies these. Photos accept JPEG, PNG, or WebP up to the application's 1 MB limit. Email is used for password recovery; other email/SMS notifications and identity verification are not implemented.

## Gmail and password recovery

PHPMailer 7.0.2 is bundled in `includes/PHPMailer`, extracted from the supplied `PHPMailer-master.zip` with its license. The supplied PDF and PHP SEASON 2 example demonstrate Gmail SMTP and generated-password delivery. This project uses that SMTP approach to email an OTP before allowing the user to choose a new password. Existing passwords are never emailed.

1. Enable **2-Step Verification** on the Gmail sender account and create a **Google App Password**: https://support.google.com/accounts/answer/185833. App Password availability depends on the Google account and its policies.
2. Edit **`includes/mail.local.php`**. Set `username` to the Gmail sender address and `password` to its App Password (not the normal Google password). The file is prepared on this PC and excluded from Git. On another PC, copy `includes/mail.example.php` to that path first.
3. Keep `host` as `smtp.gmail.com`, `port` as `587`, and `encryption` as `tls`. Leave `from_email` blank to use the sender address. TLS certificate verification remains enabled.
4. Run `C:\xampp\php\php.exe includes\check-mail.php` to check SMTP authentication without sending an email.
5. Open the sign-in page and choose **Forgot password?** Enter an existing account's email, enter the emailed six-digit OTP, then set and confirm a new password. The new-password form becomes available only after successful verification. Sign in again after resetting.

The sender settings follow [PHPMailer's Gmail example](https://github.com/PHPMailer/PHPMailer/blob/master/examples/gmail.phps). Optional environment overrides are `BR_SMTP_HOST`, `BR_SMTP_PORT`, `BR_SMTP_ENCRYPTION`, `BR_SMTP_USERNAME`, `BR_SMTP_PASSWORD`, `BR_SMTP_FROM_EMAIL`, and `BR_SMTP_FROM_NAME`. Local file edits apply on the next request; environment changes require restarting Apache. Real SMTP connections require authentication and TLS. Unauthenticated, unencrypted SMTP is allowed only for a loopback test inbox.

Codes expire after 10 minutes and allow five attempts. Verification grants expire after another 10 minutes and stay in the requesting browser's server session. Only hashes are stored in MySQL. Resending invalidates the previous challenge. Requests are limited to one per email per 60 seconds, three per email per 15 minutes, and ten per client IP per 15 minutes. Successful resets revoke prior login sessions and preserve all complaint data. The registered address and current account version are checked again before changing the password.

For privacy, registered and unknown addresses receive the same request response. Inactive or unknown accounts receive no email. Missing sender configuration returns a temporary-unavailability message. If SMTP delivery fails, the challenge is invalidated and a generic diagnostic is written to the PHP error log; the public response still does not reveal whether the account exists. No OTPs, email bodies, or SMTP passwords are logged. Use `includes/check-mail.php` when diagnosing delivery, and check the recipient's spam folder.

## Verification

With XAMPP MySQL running, execute from the project folder:

```powershell
C:\xampp\php\php.exe tests\workflow.php
C:\xampp\php\php.exe tests\store.php
C:\xampp\php\php.exe tests\password-reset.php
C:\xampp\php\php.exe tests\http.php
```

Database and HTTP tests create randomly named `maintainpro_test_*` databases and remove them afterward. They do not insert test records into `maintainpro`. The HTTP suite starts and stops its own PHP server and SMTP inbox on free loopback ports. PHPMailer sends only to that local test inbox; no test messages go to Gmail or real recipients. Test credentials need permission to create and drop these test databases; the default local XAMPP root account supports this. The password-reset suite deliberately simulates an SMTP failure and prints the corresponding generic diagnostic.

## Project files

- `index.php`, `app.js`, `styles.css`: application interface.
- `auth.php`, `login.php`, `auth-ui.js`: sign-in, registration, and first-account setup.
- `api.php`: account-scoped JSON API and CSV exports.
- `includes/domain.php`: complaint workflow and validation.
- `includes/store.php`: MySQL persistence and account management.
- `database/database.php`: database connection settings.
- `includes/bootstrap.php`: authenticated sessions and response headers.
- `includes/mail.php`, `includes/mail.local.php`: PHPMailer integration and private SMTP settings.
- `includes/check-mail.php`: CLI-only SMTP authentication check.
- `database/schema.sql`, `database/setup.php`: empty schema and setup command.
- `tests/`: workflow, database, and HTTP regression checks.

Private database scripts, application internals, tests, and dot directories are blocked by Apache rules and the PHP development router. To use PHP's development server, run `C:\xampp\php\php.exe -S 127.0.0.1:8080 router.php` with MySQL running.
