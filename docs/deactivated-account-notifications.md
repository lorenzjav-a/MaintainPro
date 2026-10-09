# Deactivated account notifications

MaintainPro retains deactivated accounts and their concern history. It does not automatically delete accounts.

## Lifecycle

- A System Admin deactivation records the exact server timestamp and the acting administrator.
- A deactivation period receives a unique sequence number. Reactivation clears the current timestamp and acting administrator but preserves the sequence, notifications, audit entries, and historical work records.
- Reactivation does not restore concern or action-plan assignments that were reassigned during deactivation.
- Inactive accounts that predate this feature keep an unknown deactivation date. MaintainPro does not infer a date and does not alert on those legacy records.

## Notification job

Run this CLI-only command at least once daily:

```shell
php tools/notify-deactivated-accounts.php
```

The job finds accounts that have been continuously deactivated for at least seven complete 24-hour days. It creates an in-app notification for every currently active, email-verified System Admin. Each notification links to the matching User Management account.

The `(recipient, event_key)` database uniqueness rule and the deactivation sequence make retries and concurrent job runs safe. A delayed run still alerts for eligible accounts. A newly appointed System Admin can receive the current-period alert on a later run, while recipients already notified do not receive a duplicate.

For Hostinger, create a **PHP** Cron Job and enter the script's absolute path, for example:

```text
/home/ACCOUNT/domains/DOMAIN/public_html/tools/notify-deactivated-accounts.php
```

Hostinger schedules cron jobs in UTC. For example, `01:11 UTC` runs at `09:11` in Asia/Manila. Apply the database migration first with `php database/setup.php`. The cron user needs read access to the application and the same database configuration used by the web application. The job requires no public web permission and returns a nonzero process status if configuration or database access fails.
