-- Off-server backup tracking columns (Backblaze B2)
-- Run once against hr_system database after deploying the B2 backup feature.

ALTER TABLE `backup_artifacts`
    ADD COLUMN `b2_uploaded`     TINYINT(1)   NOT NULL DEFAULT 0    AFTER `checksum_sha256`,
    ADD COLUMN `b2_object_key`   VARCHAR(500) NULL     DEFAULT NULL  AFTER `b2_uploaded`,
    ADD COLUMN `b2_upload_error` TEXT         NULL     DEFAULT NULL  AFTER `b2_object_key`;
