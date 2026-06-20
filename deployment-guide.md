# Deployment Guide

## Purpose

This guide explains how to deploy the HR Management System to a new server using a full codebase backup and database dump.

For the approved clean-install runbook for a new cPanel server with empty data, see [new-server-install-plan.md](new-server-install-plan.md).

## 1. Deployment Package

A full deployment should include:

- Full application files
- `vendor/` if Composer is not available on the server
- Uploaded files and storage data required by the app
- Database SQL dump
- `.env` file adjusted for the destination server

If you are deploying from a full backup, make sure it includes:

- application code
- database schema and data
- user-uploaded files
- document uploads
- announcement attachments
- any backup/storage directories the app depends on

## 2. Typical Server Requirements

Recommended baseline:

- PHP 8.x
- MySQL / MariaDB
- Apache or LiteSpeed with PHP support
- Write access for storage/upload directories
- Cron access for scheduled tasks
- SMTP or Mailjet-enabled outbound mail

## 3. Main Deployment Folders

Your application root should contain folders similar to:

- `app/`
- `config/`
- `database/`
- `public/`
- `routes/`
- `scripts/`
- `storage/` or equivalent upload/backup directories if used in your package
- `vendor/`

The public web root must point to the app’s public entry point setup being used by the project.

## 4. Upload the Application

1. Create the destination folder on the server.
2. Upload the full application backup.
3. Extract it in the target location.
4. Confirm that `public/index.php` and route files are present.

## 5. Configure `.env`

At minimum configure:

- `APP_NAME`
- `APP_ENV`
- `APP_DEBUG`
- `APP_URL`
- `APP_TIMEZONE`
- `DB_HOST`
- `DB_PORT`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`
- mail settings
- session settings
- encryption key
- backup settings if resilience module is used

Typical values already used in this project include:

- `APP_URL`
- `MAIL_TRANSPORT`
- `MAIL_HOST`
- `MAIL_PORT`
- `MAIL_ENCRYPTION`
- `MAIL_USERNAME`
- `MAIL_PASSWORD`
- `MAIL_FROM_ADDRESS`
- `MAIL_FROM_NAME`
- `SESSION_IDLE_TIMEOUT`
- `SESSION_COOKIE_LIFETIME`
- `ENCRYPTION_KEY`
- `BACKUP_STORAGE_DIR`
- `BACKUP_RETENTION_DAYS`
- `BACKUP_LINK_TTL_DAYS`

Important:

- `APP_URL` must match the live domain exactly
- Use the target server’s database credentials
- Keep `ENCRYPTION_KEY` consistent if existing encrypted/token data must remain usable

## 6. Import the Database

1. Create the database on the new server.
2. Create the DB user and assign privileges.
3. Import the SQL dump with phpMyAdmin or command line.

If your package includes extra SQL patches, import them after the base dump only if they are not already applied.

## 7. File Permissions

Ensure the web server can write to the required runtime folders.

Usually this includes:

- uploads directories
- backup directories
- cache/temp directories if used
- logs directories if used

If file uploads or backups fail after deployment, permissions are the first thing to check.

## 8. Confirm Public Entry Point

This project loads routes from `public/index.php`.

Make sure:

- the live domain points to the correct application
- the app files are in the expected directory
- the web server is serving the project through the correct entry point

If a new route returns `404` even after files exist:

- confirm the updated route file is uploaded
- confirm `public/index.php` includes that route file
- confirm you uploaded to the real live app directory, not an old copy

## 9. Mail Setup

This project uses mail configuration from `.env`.

If using Mailjet SMTP:

- keep the correct `MAIL_HOST`
- keep the correct port and encryption
- use valid Mailjet SMTP credentials

After deployment, test:

- login OTP or password reset
- announcement emails
- backup emails
- any queued mail process

## 10. Cron Jobs

If the resilience pack is active, set cron jobs for:

### Daily backup

Example:

```bash
/usr/local/bin/php /home/USERNAME/APP_PATH/scripts/run-daily-backup.php >/dev/null 2>&1
```

Suggested schedule:

- once daily, for example `0 0 * * *`

### Email queue processing

Example:

```bash
/usr/local/bin/php /home/USERNAME/APP_PATH/scripts/process-email-queue.php >/dev/null 2>&1
```

Suggested schedule:

- every 5 minutes, for example `*/5 * * * *`

Adjust the paths to your real server path.

## 11. Post-Deployment Checks

After deployment test:

1. Login works
2. Dashboard opens
3. Employee directory loads
4. Leave balances load
5. Employee import page opens
6. Documents can be opened/downloaded
7. Announcements and notifications load
8. Careers site opens if enabled
9. Resilience console opens for super admin
10. Backup script can run manually

## 12. Backup / Resilience Checks

If resilience is deployed:

1. Open `/admin/resilience`
2. Click `Run Now`
3. Confirm a run record appears
4. Confirm database/uploads status is shown
5. Confirm download links can be refreshed
6. Confirm the daily email arrives

## 13. Common Problems

### 404 after upload

Check:

- correct live directory
- updated route file uploaded
- `public/index.php` includes the route file
- correct domain points to correct app

### UI changes not appearing

Check:

- browser hard refresh
- PHP opcache delay
- uploaded to the real live app

### Backup email not sent

Check:

- mail credentials
- queue processor cron
- backup script output
- server mail/network restrictions

### Backup run is partial

Usually one artifact succeeded and the other failed.

Check:

- backup directories exist
- file permissions
- source upload paths exist
- SQL dump tools are available

### Leave or import behavior looks old

Check:

- right PHP view/controller files were replaced
- opcache was refreshed
- matching SQL changes were already applied if required

## 14. Recommended Deployment Order

1. Upload code
2. Upload storage/uploads package
3. Configure `.env`
4. Import database
5. Fix permissions
6. Test login
7. Test main modules
8. Configure cron jobs
9. Run a manual backup
10. Confirm mail delivery

## 15. Final Note

For incremental updates, only upload the changed files plus any required SQL patch.

For a fresh server deployment, always use:

- full codebase
- full database dump
- full uploads/storage package
- correct `.env`
