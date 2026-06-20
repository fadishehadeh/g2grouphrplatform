-- Migration: JWT refresh tokens table for revocable API authentication
-- Run after setting up the main schema.

CREATE TABLE IF NOT EXISTS `refresh_tokens` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`      BIGINT UNSIGNED NOT NULL,
    `token_hash`   VARCHAR(64)     NOT NULL COMMENT 'SHA-256 hex of the raw refresh token',
    `device_label` VARCHAR(255)    NULL     DEFAULT NULL,
    `expires_at`   DATETIME        NOT NULL,
    `revoked_at`   DATETIME        NULL     DEFAULT NULL,
    `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_rt_token_hash` (`token_hash`),
    KEY `idx_rt_user` (`user_id`),
    CONSTRAINT `fk_rt_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
