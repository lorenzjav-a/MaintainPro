# Database and email configuration

## Database

MaintainPro uses MySQL/MariaDB with InnoDB tables. Defaults in `config/database.php` match local XAMPP:

| Setting | Default | Environment override |
| --- | --- | --- |
| Host | `127.0.0.1` | `BR_DB_HOST` |
| Port | `3306` | `BR_DB_PORT` |
| Database | `maintainpro` | `BR_DB_NAME` |
| Username | `root` | `BR_DB_USER` |
| Password | Empty | `BR_DB_PASSWORD` |

Set overrides in the PHP process environment and restart Apache after environment changes. The local database is accessible through `http://localhost/phpmyadmin/`.

For a new installation, run from the project root:

```powershell
C:\xampp\php\php.exe database\setup.php
```

The command creates the configured database and applies `database/migrations/20260921_anonymous_concerns.sql` without sample data. It can be rerun without deleting records or resetting IDs. It also adds `users.must_change_password` if missing. The anonymous migration has already been applied on this PC.

Runtime SQL, installation commands and fixture queries live in `database/database.php`; importable migration scripts live in `database/migrations`. Connection settings remain in `config/database.php`. `includes/store.php` calls named database methods and handles validation, authorization and workflows.

For manual setup, run `C:\xampp\php\php.exe database\setup.php --schema` to print the schema without connecting to MySQL. Create the configured database in phpMyAdmin with collation `utf8mb4_unicode_ci`, select it and run the printed schema. The former separate `schema.sql` was consolidated into the database file.

| Table | Purpose |
| --- | --- |
| `users` | Accounts, password hashes, roles, teams and authentication versions |
| `complaints` | Ownership, team, status, edit version and JSON record with photos/timeline |
| `settings` | Complaint number counter and write-serialization lock |
| `login_attempts` | Sign-in throttling |
| `password_resets` | Hashed email codes and single-use reset grants |
| `password_reset_requests` | Email and IP recovery request limits |
| `concern_tracking` | Concern reference bound to a SHA-256 tracking-code hash |
| `public_attempts` | Hashed client buckets for anonymous report/tracking limits |
| `solution_rules` | Official-curated, public-safe triples of suggested actions |

`.data/barangayresolve.sqlite` is legacy data and is not read by the application. It remains protected from browser access. Existing sessions from the old demo cannot grant access.

## Gmail and password recovery

PHPMailer 7.0.2 is bundled in `vendor/phpmailer`, with its license and version metadata. `includes/mail.php` sends OTP recovery codes and assignment notices. Existing passwords and staff invitations are never emailed. The current local sender configuration is incomplete or invalid; real email needs configuration before use.

1. Enable Google 2-Step Verification and create a [Google App Password](https://support.google.com/accounts/answer/185833) for the sender account, where allowed by that account's policies.
2. Copy `config/mail.example.php` to `config/mail.local.php` if the local file does not exist. The existing configuration on this PC was moved intact during organization.
3. Set `username` to the Gmail sender address and `password` to its App Password. Keep `host` as `smtp.gmail.com`, `port` as `587`, and `encryption` as `tls`. Leave `from_email` blank to use the sender address.
4. Check SMTP authentication without sending email:

```powershell
C:\xampp\php\php.exe tools\check-mail.php
```

Then open **Forgot password?** on the sign-in page, request a code, enter the six-digit email code in the same browser, and choose a new password. Sign in again after resetting.

The sender setup follows [PHPMailer's Gmail example](https://github.com/PHPMailer/PHPMailer/blob/master/examples/gmail.phps). Optional environment overrides are `BR_SMTP_HOST`, `BR_SMTP_PORT`, `BR_SMTP_ENCRYPTION`, `BR_SMTP_USERNAME`, `BR_SMTP_PASSWORD`, `BR_SMTP_FROM_EMAIL` and `BR_SMTP_FROM_NAME`. Local file edits apply on the next request; environment changes require restarting Apache.

The private local file is ignored by Git and blocked from web access. TLS certificate verification remains enabled. Real SMTP connections require authentication and TLS; unauthenticated/unencrypted SMTP is allowed only for a loopback test inbox.

Set optional `BR_APP_URL` in the PHP/Apache environment to the URL staff can reach (for example `http://YOUR-LAN-HOST/MaintainPro` on a trusted local network, or the deployed HTTPS URL). Assignment notices then include a concern link. Do not use localhost for recipients on other PCs. The URL is never derived from an untrusted Host header. Exact locations, internal notes and evidence are omitted from email; staff sign in to view them. Assignment commits before SMTP runs. Failure produces a generic log and an official-facing notice without undoing the assignment.

## Staff notifications and insights

The local staff-insights migration is already applied. For another installation, run `database/setup.php` (or import `database/migrations/20260921_staff_insights.sql` after the anonymous migration). It targets XAMPP MariaDB and preserves existing rows.

`config/features.php` controls the rolling recurrence period (90 days), thresholds (2/3/5) and due-soon interval (24 hours). The assignment form accepts an optional target completion date/time in Asia/Manila. In-app notifications require no SMTP or API keys. An active workspace/feed checks due dates at most once per minute. To generate alerts while users are offline, optionally schedule this command every minute in Windows Task Scheduler:

```powershell
C:\xampp\php\php.exe C:\xampp\htdocs\MaintainPro\tools\notify-deadlines.php
```

The notification bell polls once per minute while visible. Existing Gmail configuration is still needed only for assignment email and OTP delivery. See [staff feature details](staff-insights-upgrade.md) for priority weights, workload logic, recurrence limitations, schema and file inventory.

## Recovery behavior

Codes expire after 10 minutes and allow five attempts. Verification grants last another 10 minutes and remain in the requesting browser's server session. Only hashes are stored. Resending invalidates the previous challenge. Limits are one request per email per 60 seconds, three per email per 15 minutes and ten per client IP per 15 minutes.

Registered and unknown addresses receive the same public request response. Inactive and unknown accounts receive no email. Missing sender configuration produces a temporary-unavailability message. SMTP failures invalidate the challenge and write a generic PHP diagnostic without exposing email addresses, codes or credentials.

Successful resets revoke previous login sessions and preserve complaint data. The account's current email and authentication version are checked again before changing its password. Resetting also satisfies the initial temporary-password replacement requirement. When troubleshooting, use the CLI check and inspect the recipient's spam folder.
