# HR System — cPanel Setup Guide

## 1. Upload & Extract

- Upload `HR-System-Package.zip` to your hosting via **File Manager** or FTP
- Extract to a folder, e.g. `/home/youruser/hr2/`

## 2. Domain Root

**Option A — Subdirectory (e.g. `yourdomain.com/hr2`):**
- No domain change needed, the root `.htaccess` handles routing to `public/` automatically

**Option B — Dedicated domain/subdomain:**
- **cPanel → Domains** → Set the **Document Root** to `/home/youruser/hr2/public`

## 3. Create Database

- **cPanel → MySQL Databases** → Create a new database + user
- Assign the user to the database with **ALL PRIVILEGES**
- **cPanel → phpMyAdmin** → Select the new database → **Import** → upload `database/hr_system_full.sql`

### Database files reference

| File | Contents |
|---|---|
| `hr_system_full.sql` | Full dump — schema + data (roles, permissions, admin user). **Use this for deployment.** |
| `schema.sql` | Structure only (CREATE TABLE statements) |
| `seed.sql` | Seed data only (roles, permissions, admin account) |

## 4. Configure Environment

- Copy `.env.example` to `.env` in the project root
- Edit `.env` with your actual values:

```
APP_NAME="HR Management System"

# IMPORTANT: If the app is in a subdirectory, include it in the URL
# Example: https://www.greydoha.com/hr2
APP_URL=https://yourdomain.com/hr2

DB_HOST=localhost
DB_DATABASE=cpaneluser_hr_system
DB_USERNAME=cpaneluser_hruser
DB_PASSWORD=your_password
```

> **Note:** `APP_URL` must include the subdirectory path (e.g. `/hr2`) if the app is not at the domain root. The router uses this to match URLs correctly.

## 5. Set Permissions

```bash
chmod 755 storage/
chmod 755 storage/uploads/
```

## 6. PHP Version

- **cPanel → MultiPHP Manager** → Select PHP **8.0+** for your domain
- Required extensions: `pdo`, `pdo_mysql`, `mbstring`, `openssl`

## 7. Email (Optional)

Edit `.env` to enable email notifications:

```
MAIL_ENABLED=true
MAIL_TRANSPORT=smtp
MAIL_HOST=mail.yourdomain.com
MAIL_PORT=465
MAIL_ENCRYPTION=ssl
MAIL_USERNAME=hr@yourdomain.com
MAIL_PASSWORD=your_email_password
MAIL_FROM_ADDRESS=hr@yourdomain.com
MAIL_FROM_NAME="HR Management System"
```

Then add a cron job to process the email queue:

- **cPanel → Cron Jobs** → Add (every minute):

```
* * * * * /usr/local/bin/php /home/youruser/hr2/scripts/process-email-queue.php >> /home/youruser/logs/email.log 2>&1
```

## 8. Login

- Go to `https://yourdomain.com/hr2/`  (not `/hr2/public/index.php`)
- **Username:** `admin`
- **Password:** `admin@123`

> Change the admin password immediately after first login.

## Troubleshooting

| Problem | Fix |
|---|---|
| **404 on all pages** | Make sure `APP_URL` in `.env` includes the subdirectory (e.g. `/hr2`) |
| **URL shows `/public/index.php`** | Re-upload the root `.htaccess` — it rewrites to `public/` internally |
| **500 error** | Check PHP version is 8.0+, and database credentials in `.env` are correct |
| **Blank page** | Enable error display temporarily: add `APP_DEBUG=true` in `.env` |

