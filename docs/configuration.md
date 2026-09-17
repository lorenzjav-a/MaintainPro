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

The command creates the configured database using the schema in `database/database.php` without inserting sample accounts or complaints. It can be rerun without deleting records or resetting complaint IDs. For older installations it adds `users.must_change_password` when missing. This field already exists on the current PC; the folder organization requires no migration.

All SQL, including the schema, installation commands and test fixture queries, lives in `database/database.php`. Connection settings remain in `config/database.php`. `includes/store.php` calls named database methods and handles validation, authorization and workflows.

For manual setup, run `C:\xampp\php\php.exe database\setup.php --schema` to print the schema without connecting to MySQL. Create the configured database in phpMyAdmin with collation `utf8mb4_unicode_ci`, select it and run the printed schema. The former separate `schema.sql` was consolidated into the database file.

| Table | Purpose |
| --- | --- |
| `users` | Accounts, password hashes, roles, teams and authentication versions |
| `complaints` | Ownership, team, status, edit version and JSON record with photos/timeline |
| `settings` | Complaint number counter and write-serialization lock |
| `login_attempts` | Sign-in throttling |
| `password_resets` | Hashed email codes and single-use reset grants |
| `password_reset_requests` | Email and IP recovery request limits |

`.data/barangayresolve.sqlite` is legacy data and is not read by the application. It remains protected from browser access. Existing sessions from the old demo cannot grant access.

## Gmail and password recovery

PHPMailer 7.0.2 is bundled in `vendor/phpmailer`, with its license and version metadata. It is used to email a one-time password-reset code. Existing passwords and staff invitations are never emailed.

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

## Recovery behavior

Codes expire after 10 minutes and allow five attempts. Verification grants last another 10 minutes and remain in the requesting browser's server session. Only hashes are stored. Resending invalidates the previous challenge. Limits are one request per email per 60 seconds, three per email per 15 minutes and ten per client IP per 15 minutes.

Registered and unknown addresses receive the same public request response. Inactive and unknown accounts receive no email. Missing sender configuration produces a temporary-unavailability message. SMTP failures invalidate the challenge and write a generic PHP diagnostic without exposing email addresses, codes or credentials.

Successful resets revoke previous login sessions and preserve complaint data. The account's current email and authentication version are checked again before changing its password. Resetting also satisfies the initial temporary-password replacement requirement. When troubleshooting, use the CLI check and inspect the recipient's spam folder.
