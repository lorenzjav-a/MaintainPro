# MaintainPro production deployment

MaintainPro 1.0.0 requires PHP 8.2+, MariaDB 10.6+ or MySQL 8.0+, HTTPS, and a production SMTP service. Runtime PHP extensions are `PDO`, `pdo_mysql`, `mbstring`, `openssl`, `json`, `fileinfo`, `session`, `Phar`, and `dom`. Browser assets, PHPMailer and the PDF renderer are bundled locally.

### Reports & Insights PDF deployment

Reports use bundled Dompdf 3.1.6 (including its dependency autoloader and DejaVu fonts) under `vendor/dompdf`. No Composer installation or internet access is required on Hostinger or during a download. See `docs/REPORTING.md` for the pinned package checksum, dependency versions and upgrade process. Deploy the complete release archive, not only `reports.php`: the builder includes `reports-pdf.php`, reporting helpers, the new browser script and the full PDF dependency tree.

Enable `dom` and `mbstring` in Hostinger's PHP extensions. `zlib` is recommended for compression; GD is not required by this vector-SVG report. The PHP system temporary directory must be writable: a private `maintainpro-pdf-cache` child stores font/cache intermediates, never persistent downloadable reports. Keep `vendor` blocked by the existing Apache/Nginx rules. PDF generation has no remote resources, PHP execution or embedded JavaScript.

PDFs contain every matching concern or fail explicitly; they never silently truncate. Capacity checks use the host's `memory_limit`, first before collecting export rows and again before rendering with the actual HTML size. When PHP is configured with unlimited memory, a conservative 512 MB application budget protects the host. On smaller shared-hosting plans, narrow the filters when prompted. There is no fixed record-count cutoff. Do not raise memory limits from web requests. Hostinger production download/time-limit checks must be completed after deployment; local tests do not establish hosting capacity. No schema migration is needed for reporting.

## 1. Prepare and back up

1. Back up the current application release and database to private administrator storage.
2. Keep database backups outside the web root, encrypt them at rest, restrict access, and apply a documented retention period such as 30 daily and 12 monthly copies.
3. Set `APP_MAINTENANCE=true` when a migration needs an interruption. CLI setup and health checks remain available.
4. Build the release with `php tools/build-release.php`. Deploy only the inspected ZIP produced under the private `.data/releases` directory. The builder keeps the newest three local release ZIPs by default; set `RELEASE_RETENTION` to a value from 1 to 20 when operations policy requires a different local retention count.

The release excludes real uploads, databases, logs, sessions, local mail configuration, tests, `.git`, `.data`, and backups. Never distribute a production database as demo data. Demo data must use invented identities, reports, addresses, messages, photos, and freshly generated demo password hashes.

## 2. Database and least privilege

Create the database with `utf8mb4`. Use an installation account for schema changes if the host separates migration privileges. The runtime account must be scoped to this database and must not receive `SUPER`, `FILE`, `PROCESS`, `CREATE USER`, `GRANT OPTION`, or global privileges.

```sql
CREATE DATABASE maintainpro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'maintainpro_app'@'127.0.0.1' IDENTIFIED BY 'GENERATE_A_LONG_RANDOM_PASSWORD';
GRANT SELECT, INSERT, UPDATE, DELETE ON maintainpro.* TO 'maintainpro_app'@'127.0.0.1';
```

The migration account additionally needs `CREATE`, `ALTER`, `INDEX`, `REFERENCES`, and `DROP` on `maintainpro.*` while migrations run. Remove migration privileges afterward when operations policy allows it. Production startup rejects `root` and blank database passwords.

## 3. Environment

Configure process environment values from `.env.example`; MaintainPro does not load a web-readable `.env` file. Generate independent `APP_KEY` and `APP_SETUP_KEY` values with at least 32 random characters. Set `APP_ENV=production`, `APP_DEBUG=false`, an HTTPS `APP_URL`, `APP_FORCE_HTTPS=true`, and strong database/SMTP credentials. Enable `APP_TRUST_PROXY` only behind a trusted reverse proxy that overwrites `X-Forwarded-Proto`. Rotate a secret immediately if it is exposed; removing it from the latest file does not invalidate it.

Use authenticated SMTP with TLS/STARTTLS and valid certificate verification. Mail failures show safe messages and leave accounts unverified. Test mail from the CLI without printing credentials.

## 4. Server and files

Use `deployment/apache-vhost.conf.example` or `deployment/nginx.conf.example` as a reviewed starting point. Redirect HTTP to HTTPS, disable directory listing, deny hidden files and private directories, and deny PHP/script execution in uploads. Application source and configuration should be owned by the deployment account and not writable by the web server. Grant the web server write access only to `uploads/evidence`, `uploads/profiles`, `.data/logs`, and the system session directory. Typical directory/file modes are `0750`/`0640`; do not use `0777`.

Coordinate the application 1 MB image limit with `post_max_size=2M`, `upload_max_filesize=2M`, and a server request limit near 1.6 MB. Set `display_errors=Off`, `log_errors=On`, and rotate `.data/logs/maintainpro.log` using logrotate or the hosting platform. Logs and backups must never be web accessible.

## 5. Deploy and migrate

1. Enable maintenance mode when required.
2. Deploy the new release beside the current release.
3. Apply production environment settings and writable directory ownership.
4. Run `php database/setup.php` with migration credentials. Never edit production tables manually as the normal workflow.
5. Run `php tools/health-check.php` and `php tools/audit-uploads.php`, then run the repository integrity/verification suite from a nonproduction checkout. The upload audit is read-only; recover reported missing files from a trusted backup and review orphans before deleting anything.
6. Confirm HTTPS redirect, HSTS, secure/HttpOnly/SameSite cookies, CSP, error pages, SMTP, sign in, authorization, uploads, backup reauthentication, and responsive UI.
7. Switch traffic and disable maintenance mode.

Historical migration files and checksums are immutable. Add a new migration for later schema changes.

## 6. Scheduled work

Run only supported CLI jobs. Example cron entries:

```cron
*/15 * * * * cd /var/www/maintainpro && /usr/bin/php tools/notify-deadlines.php >/dev/null 2>&1
17 2 * * * cd /var/www/maintainpro && /usr/bin/php tools/cleanup-security.php >/dev/null 2>&1
```

The cleanup job removes expired verification/reset and throttling records, not audit history. Use the hosting platform for encrypted database backups; do not trigger system maintenance from normal web requests.

## 7. Rollback

Keep the previous application release available. If deployment fails, restore the previous code and environment first. Restore the database only when a migration makes the older release incompatible and a reviewed restore is required. A database restore can erase concerns submitted after the backup, so never perform an automatic destructive rollback. MaintainPro exposes no public database restore endpoint.

## 8. Post deployment checklist

- HTTPS and certificates valid; HTTP redirects; HSTS present.
- Production debug off; safe 500 and database outage responses confirmed.
- Dedicated nonroot database account and strong password active.
- SMTP TLS and sender identity verified; no secrets in the release.
- Secure, HttpOnly, SameSite cookies and 45 minute staff/2 hour resident inactivity limits confirmed.
- CSRF, IDOR, role, tracking credential, private evidence, and upload execution tests pass.
- `.git`, `.env`, `.data`, config, database, tools, tests, uploads, logs, dumps, and backups are denied.
- Migrations and integrity checks pass; cron and private backup retention configured.
- Backup generation requires system administrator access, CSRF, and the current password.
- Dark/light presentation and desktop, tablet, and mobile layouts verified.
