# Installation Guide — Grey Doha HR Platform

This platform runs as two separate applications from a single codebase:

| App | URL | Document Root |
|-----|-----|---------------|
| HR System | https://hr.greydoha.com | `public-hr/` |
| Careers Portal | https://careers.greydoha.com | `public-careers/` |

Both apps share the same codebase, the same `.env`, and connect to their respective databases.

---

## 1. Upload the Code

1. Download the latest code from GitHub (`fadishehadeh/g2grouphrplatform`)
2. Zip the entire project folder
3. In **cPanel → File Manager**, navigate to `/home/greykktq/`
4. Upload and extract the zip — rename the folder to `platform`

Your directory structure on the server will be:

```
/home/greykktq/platform/
    ├── app/
    ├── config/
    ├── database/
    ├── public-hr/          ← document root for hr.greydoha.com
    ├── public-careers/     ← document root for careers.greydoha.com
    ├── routes/
    ├── scripts/
    ├── storage/
    ├── vendor/
    └── .env
```

---

## 2. Point the Subdomains

In **cPanel → Domains** (or Subdomains), set the document roots:

| Subdomain | Document Root |
|-----------|--------------|
| `hr.greydoha.com` | `/home/greykktq/platform/public-hr` |
| `careers.greydoha.com` | `/home/greykktq/platform/public-careers` |

---

## 3. Create the Databases

In **cPanel → MySQL Databases**:

1. Create two databases:
   - `greykktq_hrsystem`
   - `greykktq_hrsystemcareers`
2. Create one database user (e.g. `greykktq_hruser`) with a strong password
3. Add that user to **both** databases with **ALL PRIVILEGES**

### Import the database schemas

In **cPanel → phpMyAdmin**:

- Select `greykktq_hrsystem` → **Import** → upload `database/hr_system_full.sql`
- Select `greykktq_hrsystemcareers` → **Import** → upload `database/careers_migration.sql`

> **If migrating from an existing live database:** export the existing databases from phpMyAdmin first and import those exports instead of the fresh SQL files above. This preserves all existing data.

---

## 4. Configure the Environment

Copy `.env.example` to `.env` in the project root and fill in your values:

```env
# ── Application ──────────────────────────────
APP_NAME="HR Management System"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://hr.greydoha.com
APP_TIMEZONE=Asia/Qatar

# ── HR Database ──────────────────────────────
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=greykktq_hrsystem
DB_USERNAME=greykktq_hruser
DB_PASSWORD=your_db_password

# ── Careers Database ─────────────────────────
CAREERS_DB_DATABASE=greykktq_hrsystemcareers
# CAREERS_DB_HOST, CAREERS_DB_USERNAME, CAREERS_DB_PASSWORD
# default to the DB_* values above if left blank

# ── Mail (Mailjet) ───────────────────────────
MAIL_ENABLED=true
MAIL_TRANSPORT=smtp
MAIL_HOST=in-v3.mailjet.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=your_mailjet_api_key
MAIL_PASSWORD=your_mailjet_secret_key
MAIL_FROM_ADDRESS=hrdoha@greydoha.com
MAIL_FROM_NAME="HR Management System"
LEAVE_ADMIN_EMAIL=fadi.chehade@greydoha.com

# ── Session ──────────────────────────────────
SESSION_NAME=hr_system_session
SESSION_IDLE_TIMEOUT=7200

# ── Encryption ───────────────────────────────
ENCRYPTION_KEY=your_64_char_hex_key
```

> **ENCRYPTION_KEY** must be a 64-character hex string. Keep this value consistent — changing it will break all encrypted PII fields.

---

## 5. Set Folder Permissions

In **cPanel → Terminal** or via SSH:

```bash
chmod 755 /home/greykktq/platform/storage
chmod 755 /home/greykktq/platform/storage/uploads
```

---

## 6. PHP Version

In **cPanel → MultiPHP Manager**, set PHP **8.0 or higher** for both subdomains.

Required extensions: `pdo`, `pdo_mysql`, `mbstring`, `openssl`, `sodium`

---

## 7. SSL Certificates

In **cPanel → SSL/TLS → Let's Encrypt** (or AutoSSL):

- Issue a certificate for `hr.greydoha.com`
- Issue a certificate for `careers.greydoha.com`

---

## 8. Cron Job (Required for Email Queue)

Leave request notifications to managers and HR admins are queued in the database and sent by a background script. Without this cron job those emails will never be delivered.

In **cPanel → Cron Jobs**, add the following job to run every minute:

```
* * * * * /usr/local/bin/php /home/greykktq/platform/scripts/process-email-queue.php >> /home/greykktq/logs/email.log 2>&1
```

> Create the `logs/` directory first if it does not exist.

---

## 9. First Login

| System | URL | Username | Password |
|--------|-----|----------|----------|
| HR System | https://hr.greydoha.com | `admin` | *(your existing password)* |
| Careers Portal | https://careers.greydoha.com | — | *(job seeker self-registration)* |

> If this is a fresh install (not a migration), the default admin credentials are `admin` / `admin@123`. **Change the password immediately after first login.**

---

## 10. Verify Everything Works

- [ ] `https://hr.greydoha.com` loads the HR login page
- [ ] `https://careers.greydoha.com` loads the public job board
- [ ] HR admin login works
- [ ] Leave request submits and manager receives an email notification
- [ ] Letter request submits and HR admins receive an email notification
- [ ] Forgot password sends a reset email
- [ ] Careers portal OTP login sends a code by email
- [ ] Job posted in HR system appears on the careers portal

---

## Troubleshooting

| Problem | Fix |
|---------|-----|
| 404 on all pages | Check that `.htaccess` is uploaded and `mod_rewrite` is enabled |
| 500 error | Set `APP_DEBUG=true` temporarily to see the error, then set back to `false` |
| Blank page | Check PHP version is 8.0+, check `.env` exists and has no syntax errors |
| Emails not sending | Verify `MAIL_ENABLED=true` in `.env` and Mailjet credentials are correct |
| Leave emails not arriving | Check the cron job is running — look at `/home/greykktq/logs/email.log` |
| Encrypted fields blank | `ENCRYPTION_KEY` in `.env` must match the key used when data was originally saved |
| Careers DB not connecting | Add `CAREERS_DB_DATABASE=greykktq_hrsystemcareers` to `.env` |
