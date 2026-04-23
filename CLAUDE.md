# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This Is

A custom PHP MVC HR management system with two separate front-ends served from the same codebase:
- **HR System** (`public-hr/` document root) — internal staff portal (hr.greydoha.com)
- **Careers Portal** (`public-careers/` document root) — public job board (careers.greydoha.com)

Each has its own `index.php` entry point that sets `APP_URL` before bootstrapping the shared `app/`.

## Running the Application

Requires XAMPP (Apache + MySQL). No build step — assets are static files. Start Apache and MySQL, then navigate to `http://localhost/HR System/public-hr/` or `/public-careers/`.

## Tests

```bash
composer test          # PHPUnit via phpunit.xml
```

## Cron Jobs (Required for Full Functionality)

```bash
php scripts/process-email-queue.php    # Every minute — processes queued emails
php scripts/process-escalations.php   # Periodic — escalation workflows
```

## Architecture

### Request Lifecycle

```
public-hr/index.php → bootstrap.php → Router → Middleware → Controller → View
```

- **Router** (`app/Core/Router.php`): Parses URI, matches against route files in `routes/`, runs middleware pipeline, dispatches to controller method.
- **Route files** (`routes/`): Each module has its own file (`leaves.php`, `employees.php`, etc.). Syntax: `$router->get('/path/{param}', [Controller::class, 'method'], [Middleware::class])`.
- **Controllers** (`app/Modules/*/Controllers/`): Extend `app/Core/Controller.php`, receive the `Application` instance via constructor.
- **Views** (`app/Views/`): Plain PHP templates. Use `$this->render('path/to/view', $data)` from controllers.

### Databases

Two MySQL databases configured separately:
- `hr_system` — main app (config via `config/database.php` and `.env`)
- `hr_systemcareers` — careers portal (config via `config/careers_db.php`)

Schema files: `database/hr_system_full.sql` (main) and `database/careers_migration.sql`. Run these plus any migration files in `database/` prefixed with dates when setting up.

Database access uses `$app->database()` which returns a PDO wrapper. Always use prepared statements — no raw query interpolation.

### Authentication & Permissions

Session-based auth in `app/Core/Auth.php`. Login flow: verify password hash → regenerate session → cache user in session. Optional OTP path sends a code via Mailjet SMTP before completing login.

RBAC: `roles` → `role_permissions` → `permissions` tables. The `PermissionMiddleware` checks `module_name.action_name` pairs. `RoleMiddleware` checks role codes directly.

Lockout: 5 failed attempts → 15-minute lockout (configurable in `config/app.php` under `security`).

### Encryption

Sensitive PII fields are AES-256-CBC encrypted at rest. Use the global helpers `encrypt_field()` / `decrypt_field()` from `app/Support/Encryption.php`. The `ENCRYPTION_KEY` in `.env` must be a 64-character hex string. Never store plaintext PII in those columns.

### Key Helpers (`app/Support/helpers.php`)

Global functions available everywhere: `env()`, `config()`, `auth()`, `url()`, `flash()`, `csrf_field()`, `e()` (XSS escape). Use these rather than accessing superglobals directly.

### Audit Logging

Every controller has `$this->auditLog($action, $entityType, $entityId, $details)`. Call it on any data-mutating operation so the `audit_logs` table stays complete.

### Module Layout

Each module under `app/Modules/<Name>/` follows:
```
Controllers/
Models/ (or Repositories/)
Views/
```

The `Structure` module handles the company/branch/department hierarchy that most other modules reference. `Admin` manages users, roles, and permissions.

### Frontend

Bootstrap 5, DataTables for grids, TCPDF for PDF generation, PhpSpreadsheet for Excel export. No npm/webpack — all libraries are included as static assets in `public-hr/assets/` and `public-careers/assets/`.

## Environment Configuration

Copy `.env.example` to `.env`. Critical values:
- `DB_*` — database credentials for both databases
- `ENCRYPTION_KEY` — 64-char hex, never change after data exists
- `MAIL_*` — Mailjet SMTP for OTP and notifications
- `APP_URL` — overridden per entry point, but set a sensible default
- `RECAPTCHA_*` — Google reCAPTCHA for public-facing forms
