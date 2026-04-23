# cPanel Git Version Control Setup - Simplified Guide

Your setup: `https://hr.greydoha.com/` deployed to `/greykktq/platform/`

---

## ✅ STEP-BY-STEP SETUP

### STEP 1: Generate Your Webhook Secret (Do This First)

**On your local computer, open PowerShell:**

```powershell
$secret = [System.Convert]::ToBase64String((1..32 | ForEach-Object { [byte](Get-Random -Min 0 -Max 256) }))
Write-Host $secret
```

**Copy the output** — this is your `GITHUB_WEBHOOK_SECRET` (you'll need it later)

Example: `BQkL9Zm2xP3qRvW5sT8uY2hJ4kM6nO9p0qA1bC2dE3f=`

---

### STEP 2: Login to cPanel

1. Go to: `https://your-cpanel-domain.com:2083` (or your cPanel URL)
2. Login with your cPanel username & password

---

### STEP 3: Open File Manager

1. In cPanel, find **File Manager** (usually under "Files" section)
2. Click **File Manager**
3. Navigate to `/greykktq/platform/` folder
4. You should see: `app/`, `public-hr/`, `deploy.php`, `.env.example`, etc.

---

### STEP 4: Create/Edit `.env` File

1. **Locate `.env`** in `/greykktq/platform/`
   - If it doesn't exist, right-click → **Create New File** → name it `.env`

2. **Edit `.env`:**
   - Right-click `.env` → **Edit**
   - Add/update these lines:

```env
APP_NAME="HR Management System"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://hr.greydoha.com
APP_TIMEZONE=Asia/Qatar

DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=greykktq_hr_system
DB_USERNAME=greykktq_hruser
DB_PASSWORD=your_actual_password_here

GITHUB_WEBHOOK_SECRET=BQkL9Zm2xP3qRvW5sT8uY2hJ4kM6nO9p0qA1bC2dE3f=
```

   - Replace `GITHUB_WEBHOOK_SECRET` with your secret from **STEP 1**
   - Replace `DB_*` with your actual database credentials
   - Click **Save**

---

### STEP 5: Set File Permissions

1. In File Manager, select `deploy.php`
2. Right-click → **Change Permissions**
3. Set to: `755` (or check: Read ✓, Write ✓, Execute ✓ for Owner)
4. Click **Change Permissions**

Repeat for folder: `storage/`
- Right-click `storage/` → **Change Permissions** → `755`

---

### STEP 6: Initialize Git on Server (SSH Required)

**You need SSH access. In cPanel:**

1. Go to **SSH Access** (under "Advanced" section)
2. Click **Manage SSH Keys**
3. If no key exists:
   - Click **Generate a New Key**
   - Keyname: `github-deploy`
   - Password: (leave empty or create one)
   - Click **Generate Key**

4. **Authorize the public key:**
   - Find the key you just created
   - Click **Manage Authorization** → **Authorize**

5. **Connect via SSH** (from your local computer):

```bash
ssh -i your_key_path.pem greykktq@hr.greydoha.com
```

Or use a tool like **PuTTY** (Windows) with your key.

---

### STEP 7: Run Git Commands (via SSH)

Once connected via SSH, run these commands:

```bash
# Go to your project folder
cd /greykktq/platform

# Initialize git
git init
git remote add origin https://github.com/yourusername/hr-system.git
git fetch origin
git checkout -b main origin/main

# Set permissions
chmod 755 deploy.php
chmod 755 storage/
```

That's it! Git is now initialized on your server.

---

### STEP 8: Setup GitHub Webhook

1. **Go to GitHub** (github.com)
2. Open your `hr-system` repository
3. Click **Settings** (top right)
4. Click **Webhooks** (left sidebar)
5. Click **Add webhook**

Fill in:

| Field | Value |
|-------|-------|
| **Payload URL** | `https://hr.greydoha.com/deploy.php` |
| **Content type** | `application/json` |
| **Secret** | `BQkL9Zm2xP3qRvW5sT8uY2hJ4kM6nO9p0qA1bC2dE3f=` (your secret from STEP 1) |
| **Which events?** | Select **Push events** only |
| **Active** | ✓ Check the box |

6. Click **Add webhook**

---

## 🎉 YOU'RE DONE!

Now whenever you push to `main` branch:

```bash
git add .
git commit -m "Your changes"
git push origin main
```

✅ GitHub automatically deploys to `https://hr.greydoha.com/`

---

## 🔍 How to Check If It Worked

**Option A: Check in cPanel**

1. File Manager → navigate to `/greykktq/platform/storage/`
2. Right-click `deployments.log` → **Edit**
3. You should see deployment entries with timestamps

**Option B: Via SSH**

```bash
ssh greykktq@hr.greydoha.com
tail -f /greykktq/platform/storage/deployments.log
```

Then make a test push and watch it deploy in real-time.

---

## ❌ Troubleshooting

| Problem | Fix |
|---------|-----|
| **Webhook shows red X or 403** | Check `GITHUB_WEBHOOK_SECRET` in `.env` matches GitHub webhook secret exactly |
| **404 when visiting deploy.php** | Check `deploy.php` exists in `/greykktq/platform/` |
| **Permission denied error in log** | Run `chmod 755 deploy.php storage/` again via SSH |
| **"Git not found" error** | SSH into server, check: `git --version` — may need hosting support |
| **Nothing happens after push** | Check GitHub webhook "Recent Deliveries" tab for error details |

---

## 📚 Summary

| Step | Where | What |
|------|-------|------|
| 1 | PowerShell | Generate webhook secret |
| 2-3 | cPanel | Login & open File Manager |
| 4 | cPanel File Manager | Edit `.env` with secret & database |
| 5 | cPanel File Manager | Set `deploy.php` & `storage/` permissions to 755 |
| 6-7 | SSH Terminal | Initialize git & authorize keys |
| 8 | GitHub | Add webhook pointing to `https://hr.greydoha.com/deploy.php` |

🚀 **Result:** Auto-deploy on every push to `main`!
