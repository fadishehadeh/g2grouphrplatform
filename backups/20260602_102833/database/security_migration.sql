-- ============================================================
-- Security migration: OTP + login lockout for HR users
-- Run once against the hr_system database
-- ============================================================

USE hr_system;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS login_attempts   TINYINT UNSIGNED NOT NULL DEFAULT 0         COMMENT 'Failed password attempts since last success',
    ADD COLUMN IF NOT EXISTS locked_until     DATETIME         NULL                        COMMENT 'Account locked until this timestamp',
    ADD COLUMN IF NOT EXISTS otp_code         VARCHAR(6)       NULL                        COMMENT 'Current pending OTP code',
    ADD COLUMN IF NOT EXISTS otp_expires_at   DATETIME         NULL                        COMMENT 'OTP expiry timestamp',
    ADD COLUMN IF NOT EXISTS otp_attempts     TINYINT UNSIGNED NOT NULL DEFAULT 0         COMMENT 'Wrong OTP guesses for current code',
    ADD COLUMN IF NOT EXISTS otp_sent_count   TINYINT UNSIGNED NOT NULL DEFAULT 0         COMMENT 'OTPs sent in current window',
    ADD COLUMN IF NOT EXISTS otp_sent_window_start DATETIME    NULL                        COMMENT 'Start of OTP send-rate window',
    ADD COLUMN IF NOT EXISTS last_login_at    DATETIME         NULL                        COMMENT 'Last successful login timestamp';
