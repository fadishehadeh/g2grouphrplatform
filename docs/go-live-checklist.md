# Go-Live Checklist

## Environment
- Confirm PHP `8.0.30+`, MySQL, Apache `mod_rewrite`, and HTTPS are available.
- Point Apache to the `public/` directory or use `http://localhost/HR%20System/public` during XAMPP testing.
- Set production values for `APP_URL`, `APP_ENV`, and database credentials.
- Confirm `public/assets/images/greydoha.gif` is present and the brand color renders correctly.

## Database
- Import `database/schema.sql` and optional `database/seed.sql` into the target database.
- Verify required tables exist, especially `users`, `password_resets`, `user_sessions`, and `email_queue`.
- Create a fresh superadmin password and confirm it satisfies the password policy.
- Back up the database before the cutover.

## Security
- Enable HTTPS before public access so `Secure` cookies and HSTS are effective.
- Review the configured CSP, Permissions-Policy, and Referrer-Policy values in `config/app.php`.
- Confirm session idle timeout and password reset expiry values are appropriate for production.
- Restrict filesystem permissions for `storage/` or document upload locations.
- Disable debug output in production.

## Application Validation
- Run PHP lint or `powershell -ExecutionPolicy Bypass -File scripts/check.ps1`.
- Run `C:\xampp\php\php.exe composer.phar test` and `powershell -ExecutionPolicy Bypass -File scripts/check-reset.ps1`.
- Verify login/logout with `superadmin`, `manager1`, and `employee1`.
- Verify the forgot/reset password flow works with your chosen mail or preview setup using a password that satisfies the current policy.
- Open key module pages: Dashboard, Employees, Leave, Documents, Reports, Settings.
- Confirm role boundaries still block employee/manager access to admin-only pages.

## Operations Readiness
- Configure outbound mail handling for queued reset emails if SMTP is required.
- Confirm backup/restore procedure for database and uploaded documents.
- Confirm Apache and PHP error logs are writable and monitored.
- Keep `composer.lock` and deployment docs with the release package.

## Final Cutover
- Announce the maintenance/cutover window.
- Snapshot files + database.
- Deploy files, import schema changes, and clear browser cache.
- Re-run smoke checks after cutover.
- Hand over admin credentials and rollback instructions.