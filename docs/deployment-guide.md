# Deployment Guide

## Local XAMPP Run

1. Copy the repository to `C:\xampp\htdocs\HR System`.
2. Ensure Apache and MySQL are running from XAMPP Control Panel.
3. Create the database, for example `hr_system`.
4. Import `database/schema.sql`, then `database/seed.sql` if you want sample data.
5. Access the app at `http://localhost/HR%20System/public`.

## Configuration

The app reads configuration through `env()` with defaults in:

- `config/app.php`
- `config/database.php`

If you are not injecting environment variables via Apache or Windows, update those defaults before deployment.

Recommended production values:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://your-domain.example`
- `SESSION_IDLE_TIMEOUT=7200`
- `PASSWORD_RESET_EXPIRY_MINUTES=60`
- `MAIL_ENABLED=true` when a real mail sender is ready

## Apache Notes

- Enable `mod_rewrite`.
- Prefer a virtual host whose document root is the repository's `public/` directory.
- If you cannot use a virtual host, the current `public/.htaccess` supports the `/public` entry path.
- Enable HTTPS in production so secure cookies and HSTS can apply.

## Dependency / Test Setup

From the repository root:

- Install PHP dependencies: `C:\xampp\php\php.exe composer.phar install`
- Run PHPUnit: `C:\xampp\php\php.exe composer.phar test`
- Run the smoke harness: `powershell -ExecutionPolicy Bypass -File scripts/check.ps1`
- Run the reset-flow harness: `powershell -ExecutionPolicy Bypass -File scripts/check-reset.ps1`

## Mail / Password Reset

- With `MAIL_ENABLED=false`, forgot-password shows a local preview link after submission.
- With `MAIL_ENABLED=true`, reset emails are queued in `email_queue`.
- The current demo credential `Admin@123` is a legacy seed password and does not satisfy the stronger reset-password policy; set fresh production passwords that are at least 10 characters and include uppercase, lowercase, number, and symbol.
- Make sure your mail transport or queue worker is in place before production use.

## Production Verification

After deployment, verify:

1. login works for superadmin and at least one employee/manager account
2. the sidebar shows the Grey Doha branding and logo
3. major module pages return `200`
4. forgot/reset password works end-to-end
5. uploaded document download links remain accessible only to authorized users

## Rollback

- Restore the previous release files.
- Restore the pre-deployment database backup.
- Re-test login and dashboard access.