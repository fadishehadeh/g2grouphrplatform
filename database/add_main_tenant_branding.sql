-- Migration: Add main tenant branding columns to companies table
-- Run this ONCE against hr_system database

ALTER TABLE `companies`
    ADD COLUMN `is_main_tenant`    TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`,
    ADD COLUMN `parent_company_id` BIGINT UNSIGNED NULL AFTER `is_main_tenant`,
    ADD COLUMN `brand_color`       VARCHAR(7) NULL AFTER `parent_company_id`,
    ADD COLUMN `tagline`           VARCHAR(255) NULL AFTER `brand_color`,
    ADD COLUMN `logo_path_white`   VARCHAR(255) NULL AFTER `tagline`;

ALTER TABLE `companies`
    ADD CONSTRAINT `fk_companies_parent`
        FOREIGN KEY (`parent_company_id`) REFERENCES `companies`(`id`)
        ON DELETE SET NULL ON UPDATE CASCADE;

-- Insert G2 Group as the main tenant row.
-- logo_path and logo_path_white left NULL so the app falls back to .env / config values
-- until branding is configured via the System Settings page.
INSERT INTO `companies` (`name`, `code`, `is_main_tenant`, `parent_company_id`, `status`)
VALUES ('G2 Group', 'G2-MAIN', 1, NULL, 'active');

-- Link all existing sub-companies to the new main tenant row.
-- The sub-query alias works around MySQL's limitation on self-referencing updates.
UPDATE `companies`
SET `parent_company_id` = (
    SELECT `id` FROM (
        SELECT `id` FROM `companies` WHERE `is_main_tenant` = 1 LIMIT 1
    ) AS _t
)
WHERE `is_main_tenant` = 0;
