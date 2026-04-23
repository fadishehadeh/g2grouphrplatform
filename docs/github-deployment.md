# GitHub Auto-Deployment Setup Guide

This guide sets up automatic deployment from GitHub to your cPanel server. Every time you push to the `main` branch, the latest code is pulled automatically.

## Prerequisites

- SSH access to your cPanel server
- Git installed on your server (usually available on shared hosting)
- A GitHub repository with your HR System code
- A webhook secret key for secure communication

## Step 1: Prepare Your GitHub Repository

```bash
# Initialize git locally (if not already done)
git init
git remote add origin https://github.com/yourusername/hr-system.git
git add .
git commit -m "Initial commit"
git branch -M main
git push -u origin main
```

## Step 2: SSH into Your Server

```bash
ssh username@yourdomain.com
cd /home/youruser/hr2  # Your project directory
```

## Step 3: Clone Repository on Server

```bash
# Initialize bare repo or clone
git init
git remote add origin https://github.com/yourusername/hr-system.git
git fetch origin
git checkout -b main origin/main
```

## Step 4: Generate Webhook Secret

Generate a secure random string (at least 32 characters):

**On Linux/Mac:**
```bash
openssl rand -hex 32
```

**On Windows PowerShell:**
```powershell
[System.Convert]::ToBase64String((1..32 | ForEach-Object { [byte](Get-Random -Min 0 -Max 256) }))
```

## Step 5: Add Webhook Secret to .env

SSH into your server and edit `.env`:

```bash
nano .env
```

Add or update:
```
GITHUB_WEBHOOK_SECRET=your_generated_secret_here
```

Save (Ctrl+X, Y, Enter in nano)

## Step 6: Set File Permissions

```bash
chmod 755 deploy.php
chmod 755 storage/
chmod -R 755 storage/
```

## Step 7: Configure GitHub Webhook

1. Go to your GitHub repository
2. Click **Settings** → **Webhooks** → **Add webhook**
3. Fill in:
   - **Payload URL:** `https://yourdomain.com/hr2/deploy.php` (adjust for your domain/subdirectory)
   - **Content type:** `application/json`
   - **Secret:** Paste your webhook secret from Step 4
   - **Which events?** Select "Push events"
   - Check "Active"
4. Click **Add webhook**

## Step 8: Test the Webhook

1. Go to the webhook settings page
2. Click the webhook you just created
3. Scroll to **Recent Deliveries**
4. Click on the most recent delivery
5. Click **Redeliver**
6. Check the **Response** tab — should show `200`

You can also check the deployment log on your server:

```bash
tail storage/deployments.log
```

## Step 9: Deploy!

Now when you push to `main`:

```bash
git add .
git commit -m "Your changes"
git push origin main
```

GitHub will automatically trigger the webhook, and your server will pull the latest code.

## Monitoring Deployments

View the deployment log on your server:

```bash
tail -f storage/deployments.log
```

Or via cPanel File Manager: `hr2/storage/deployments.log`

## Troubleshooting

### Webhook shows error or doesn't trigger

- Check that `https://yourdomain.com/hr2/deploy.php` is accessible in a browser
- Verify `GITHUB_WEBHOOK_SECRET` is set in `.env`
- Check deployment log: `tail storage/deployments.log`

### "403 Forbidden" in webhook response

- Webhook secret mismatch — regenerate secret and update both GitHub and `.env`
- Check that secret is exactly the same (no extra spaces)

### "Permission denied" when git pull runs

- Check file permissions: `chmod 755 deploy.php`
- Ensure PHP can execute shell commands
- Check server's PHP `safe_mode` / `disable_functions` allows `shell_exec`

### Git pull shows "Permission denied (publickey)"

- You need to set up SSH keys on the server for passwordless git authentication:

```bash
ssh-keygen -t rsa -b 4096  # Press Enter for defaults
cat ~/.ssh/id_rsa.pub      # Copy this output
```

Then add the public key to GitHub:
1. GitHub **Settings** → **SSH and GPG keys** → **New SSH key**
2. Paste the key

### Still failing?

Check if git is installed:
```bash
git --version
```

Check PHP error logs in cPanel for any fatal errors.

## Security Considerations

- The webhook verifies GitHub's signature using HMAC-SHA256
- Only deploy on push to `main` branch
- All deployments are logged to `storage/deployments.log`
- Webhook endpoint (`deploy.php`) is public but signature-protected
- Use HTTPS for your domain (webhook requires it)

## Advanced: Manual Deployment Without Webhook

If webhooks don't work, you can manually SSH and run:

```bash
cd /home/youruser/hr2
git pull origin main
```

## Notes

- The deployment script logs all activity to `storage/deployments.log`
- Failed deployments are logged with error details
- Only `main` branch deployments trigger auto-pull
- All other branches are skipped automatically
