-- ============================================================
-- PII encryption migration
-- Widens columns that will hold libsodium-encrypted values.
-- Encrypted output: base64( 24-byte nonce + ciphertext + 16-byte MAC )
-- Worst-case stored length ≈ ceil((plaintext + 40) / 3) * 4
-- VARCHAR(500) comfortably covers any of these fields.
--
-- Run once against the live database BEFORE deploying the PHP
-- changes that call encrypt_field() / decrypt_field().
--
-- Safe to run on an empty table; also safe on a populated table
-- IF you run the companion PHP backfill script immediately after.
-- ============================================================

-- employees table
ALTER TABLE employees
    MODIFY COLUMN phone           VARCHAR(500)  NULL    COMMENT 'Encrypted: libsodium secretbox',
    MODIFY COLUMN alternate_phone VARCHAR(500)  NULL    COMMENT 'Encrypted: libsodium secretbox',
    MODIFY COLUMN personal_email  VARCHAR(500)  NULL    COMMENT 'Encrypted: libsodium secretbox',
    MODIFY COLUMN date_of_birth   VARCHAR(500)  NULL    COMMENT 'Encrypted: libsodium secretbox (YYYY-MM-DD plaintext)',
    MODIFY COLUMN id_number       VARCHAR(500)  NULL    COMMENT 'Encrypted: libsodium secretbox',
    MODIFY COLUMN passport_number VARCHAR(500)  NULL    COMMENT 'Encrypted: libsodium secretbox';

-- job_seeker_profiles table
ALTER TABLE job_seeker_profiles
    MODIFY COLUMN phone           VARCHAR(500)  NULL    COMMENT 'Encrypted: libsodium secretbox',
    MODIFY COLUMN mobile          VARCHAR(500)  NULL    COMMENT 'Encrypted: libsodium secretbox',
    MODIFY COLUMN whatsapp_number VARCHAR(500)  NULL    COMMENT 'Encrypted: libsodium secretbox',
    MODIFY COLUMN date_of_birth   VARCHAR(500)  NULL    COMMENT 'Encrypted: libsodium secretbox (YYYY-MM-DD plaintext)';
