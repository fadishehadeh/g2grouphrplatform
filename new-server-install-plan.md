# New Server Install Plan

## Summary

This plan covers installing the HR Management System on a **new server** as a **fresh empty environment** on **cPanel shared hosting**.

Deployment shape:

- one shared codebase
- HR app served from `public-hr/`
- Careers portal served from `public-careers/`
- one shared `.env`
- two databases:
  - HR database
  - Careers database

This path does **not** copy current live HR data. It creates a clean system ready for structure setup, employee import, leave setup, and later operational data entry.

## 1. Deployment Package

Use a **full application package**, not a partial live-update ZIP.

The package should include:

- application code
- `vendor/`
- `app/`
- `config/`
- `routes/`
- `scripts/`
- `storage/`
- `public-hr/`
- `public-careers/`
- `database/`
- `.env.example`

Do not reuse the current production `.env` directly. Create a new destination `.env`.

## 2. Server Folder Layout

Recommended target path:

```text
/home/USERNAME/platform/
```

Expected structure:

```text
/home/USERNAME/platform/
├── app/
├── config/
├── database/
├── public-hr/
├── public-careers/
├── routes/
├── scripts/
├── storage/
├── vendor/
└── .env
```

## 3. cPanel Domain Setup

Point the domains or subdomains to these document roots:

- HR app: `/home/USERNAME/platform/public-hr`
- Careers portal: `/home/USERNAME/platform/public-careers`

Do not point the domain to:

- the repo root
- `public/`
- an old copied app folder

## 4. Database Strategy

Create two databases and one DB user with full privileges on both:

- HR database
- Careers database

For this **fresh empty** install, use the clean base SQL path:

1. Import `database/schema.sql` into the HR database
2. Import `database/seed.sql` into the HR database
3. Import `database/careers_migration.sql` into the careers database

Then apply the additional feature migrations required by the current app version, only if they are not already included in the chosen base schema:

- `database/add_resilience_backup_tables.sql`
- `database/add_leave_balance_carry_forward_amount.sql`
- `database/add_replacement_employee_to_leave_requests.sql`
- `database/add_b2_backup_columns.sql`
- `database/add_main_tenant_branding.sql`
- `database/add_payroll_tables.sql`
- any other required additive SQL tied to features you want enabled on day one

Important:

- do not import a live production export if the goal is a clean empty instance
- do not blindly re-run additive SQL if the schema already contains those tables or columns

## 5. `.env` Setup

Create a fresh `.env` from `.env.example`.

Set at minimum:

- `APP_NAME`
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL`
- `APP_TIMEZONE`
- `DB_HOST`
- `DB_PORT`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`
- `CAREERS_DB_DATABASE`
- mail settings
- session settings
- encryption key
- backup settings
- B2 settings if resilience is being enabled

Important notes:

- `APP_URL` must match the destination HR domain exactly
- use a valid 64-character hex `ENCRYPTION_KEY`
- if this is a non-production environment, disable mail or route it to a controlled inbox
- use new server database credentials, not the current production ones

## 6. Filesystem Permissions

Ensure the app can write to its runtime directories, especially:

- `storage/`
- `storage/uploads/`
- backup storage directories
- any logs or temp directories used by cron/scripts

Validate write access before functional testing.

## 7. Cron Jobs

Required cron jobs:

### Email queue

```bash
/usr/local/bin/php /home/USERNAME/platform/scripts/process-email-queue.php >/dev/null 2>&1
```

Recommended schedule:

```text
*/5 * * * *
```

### Daily backup

```bash
/usr/local/bin/php /home/USERNAME/platform/scripts/run-daily-backup.php >/dev/null 2>&1
```

Recommended schedule:

```text
0 0 * * *
```

If this is only a sandbox, the backup cron can wait until the install is validated.

## 8. First Login and Base Setup

After install:

1. log in with the seeded admin account
2. change the default admin password immediately
3. verify branding/settings
4. verify structure pages
5. verify leave module
6. verify resilience console
7. verify payroll/settings pages if those modules are needed
8. verify careers portal if enabled

## 9. Recommended Data Loading Order

Because this is a fresh empty deployment, populate the system in layers:

1. branding and system settings
2. companies / branches / departments / teams
3. job titles / designations
4. employee master import
5. leave types and leave setup
6. leave balances and imports
7. documents and attachments if needed
8. payroll structures if payroll is in scope
9. careers content and jobs if careers is active

## 10. Validation Checklist

Core checks:

- HR login page loads
- Careers portal loads
- admin login works
- dashboard opens
- employee directory opens
- structure pages open
- leave module opens
- settings page saves
- resilience page opens for super admin

Infrastructure checks:

- uploads directory is writable
- email queue script runs
- backup script runs
- B2 sync works if enabled
- no routing 404s caused by bad document roots

Safety checks:

- no old production employee data exists
- no accidental employee emails are sent during setup
- encryption-dependent features work with the configured key

## 11. Assumptions

This saved plan assumes:

- install mode is **fresh empty**
- hosting is **cPanel shared**
- both HR and Careers apps are being installed
- the repo is the source of truth for code
- the new server gets its own `.env`
- live production operational data is **not** cloned during initial setup

