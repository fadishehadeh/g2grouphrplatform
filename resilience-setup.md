# Resilience Setup

This document records the live resilience and off-server backup setup for the HR Management System.

## Scope

The resilience flow currently covers:

- local database backups
- local uploads backups
- secure backup download links from the admin panel
- off-server backup sync to Backblaze B2
- daily retention cleanup

## Required Database Change

Run this once on the live database:

```sql
ALTER TABLE `backup_artifacts`
    ADD COLUMN `b2_uploaded`     TINYINT(1)   NOT NULL DEFAULT 0    AFTER `checksum_sha256`,
    ADD COLUMN `b2_object_key`   VARCHAR(500) NULL     DEFAULT NULL  AFTER `b2_uploaded`,
    ADD COLUMN `b2_upload_error` TEXT         NULL     DEFAULT NULL  AFTER `b2_object_key`;
```

## Required Files

These B2-related files must be present on the live server:

- `app/Modules/Resilience/B2BackupUploader.php`
- `app/Modules/Resilience/BackupRepository.php`
- `app/Modules/Resilience/BackupService.php`
- `app/Views/resilience/index.php`
- `config/app.php`

## `.env` Block

Use this in the live `.env`:

```env
# ── Backups ──────────────────────────────────
BACKUP_STORAGE_DIR=storage/backups
BACKUP_RETENTION_DAYS=30
BACKUP_LINK_TTL_DAYS=7

# ── B2 ───────────────────────────────────────
B2_ENABLED=true
B2_KEY_ID=your_key_id
B2_APPLICATION_KEY=your_application_key
B2_BUCKET_NAME=g2hrbackup
B2_ENDPOINT=s3.us-east-005.backblazeb2.com
```

Notes:

- `B2_BUCKET_NAME` must match the real bucket exactly.
- The bucket should be `Private`.
- Use a bucket-scoped Backblaze application key, not a broad master key.
- The app uses the native B2 API for upload logic.

## Cron Jobs

Daily backup:

```bash
/usr/local/bin/php /home/greykktq/platform/scripts/run-daily-backup.php >/dev/null 2>&1
```

Mail queue processor:

```bash
/usr/local/bin/php /home/greykktq/platform/scripts/process-email-queue.php >/dev/null 2>&1
```

Recommended schedule:

- backup: once daily, typically `00:00`
- email queue: every `5` minutes

## Manual Test

Run a manual backup from SSH:

```bash
/usr/local/bin/php /home/greykktq/platform/scripts/run-daily-backup.php
```

Or run it from the admin panel:

- `Resilience Console`
- `Run Backup Now`

## Success Checklist

After a successful run, verify all of the following:

1. `/admin/resilience` shows:
   - `Database Backup = Success`
   - `Uploads Backup = Success`
   - `Off-Server Sync = Synced`
2. A new run appears in `Backup History`.
3. Download links are generated for the local backup artifacts.
4. The B2 bucket contains:
   - `database/...`
   - `uploads/...`
5. Backup email notification is received by the super admin target.

## Verification SQL

Use this query to confirm the latest artifact sync state:

```sql
SELECT
    id,
    backup_run_id,
    artifact_type,
    status,
    b2_uploaded,
    b2_object_key,
    b2_upload_error,
    created_at
FROM backup_artifacts
ORDER BY id DESC
LIMIT 10;
```

Expected for a successful synced run:

- `status = success`
- `b2_uploaded = 1`
- `b2_object_key` is not `NULL`
- `b2_upload_error` is `NULL`

## Common Failures

### Local backup succeeds but off-server sync is partial

Likely causes:

- wrong `B2_KEY_ID`
- wrong `B2_APPLICATION_KEY`
- wrong bucket name
- application key does not have access to the bucket
- PHP CLI missing `curl`
- temporary outbound connection problem

### B2 upload rejected: method not allowed: PUT

Cause:

- old uploader version was deployed

Fix:

- replace `app/Modules/Resilience/B2BackupUploader.php` with the corrected version that uploads with `POST`

### CLI PHP missing cURL

Check:

```bash
/usr/local/bin/php -m | grep curl
```

If nothing is returned, enable `curl` for the CLI PHP runtime.

## Retention Behavior

- local backup files are retained for `30` days
- B2 remote files are also cleaned up after `30` days
- secure download links expire after `7` days

## Operational Notes

- Off-server sync is additive. Local backup remains the primary source of truth.
- If local backup succeeds and B2 sync fails, the run becomes `Partial`.
- The resilience screen is super-admin only.
- Remove temporary diagnostic scripts from live after testing.

