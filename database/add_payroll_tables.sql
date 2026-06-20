-- Migration: Payroll module tables + module toggle setting
-- Run this ONCE against hr_system database

-- -------------------------------------------------------
-- 1. Module toggle in settings table
-- -------------------------------------------------------
INSERT INTO `settings` (`category_name`, `setting_key`, `setting_value`, `value_type`)
VALUES ('modules', 'payroll_enabled', 'false', 'boolean')
ON DUPLICATE KEY UPDATE `id` = `id`;

-- -------------------------------------------------------
-- 2. salary_structures
-- One active structure per employee at a time (effective_to NULL = active).
-- App logic closes the prior active row before inserting a new one.
-- -------------------------------------------------------
CREATE TABLE `salary_structures` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `employee_id`         BIGINT UNSIGNED NOT NULL,
    `basic_salary`        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `housing_allowance`   DECIMAL(12,2) NULL DEFAULT NULL,
    `transport_allowance` DECIMAL(12,2) NULL DEFAULT NULL,
    `other_allowances`    DECIMAL(12,2) NULL DEFAULT NULL,
    `currency`            VARCHAR(10) NOT NULL DEFAULT 'QAR',
    `effective_from`      DATE NOT NULL,
    `effective_to`        DATE NULL DEFAULT NULL,
    `created_by`          BIGINT UNSIGNED NOT NULL,
    `created_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ss_employee` (`employee_id`),
    KEY `idx_ss_effective` (`employee_id`, `effective_to`),
    CONSTRAINT `fk_ss_employee`   FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ss_created_by` FOREIGN KEY (`created_by`)  REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- 3. payroll_runs
-- One draft/finalized run per company per calendar month.
-- -------------------------------------------------------
CREATE TABLE `payroll_runs` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`   BIGINT UNSIGNED NOT NULL,
    `period_month` TINYINT UNSIGNED NOT NULL COMMENT '1–12',
    `period_year`  SMALLINT UNSIGNED NOT NULL,
    `status`       ENUM('draft','finalized') NOT NULL DEFAULT 'draft',
    `run_by`       BIGINT UNSIGNED NOT NULL,
    `finalized_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_run_period` (`company_id`, `period_month`, `period_year`),
    CONSTRAINT `fk_pr_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`),
    CONSTRAINT `fk_pr_run_by`  FOREIGN KEY (`run_by`)     REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- 4. payroll_run_items
-- One row per employee per run. Salary values are snapshotted at run time —
-- do NOT re-join to salary_structures after a run is finalized.
-- -------------------------------------------------------
CREATE TABLE `payroll_run_items` (
    `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `payroll_run_id`       BIGINT UNSIGNED NOT NULL,
    `employee_id`          BIGINT UNSIGNED NOT NULL,
    `basic_salary`         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `housing_allowance`    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `transport_allowance`  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `other_allowances`     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `deductions`           DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Unpaid-leave deduction auto-calculated at run time; overrideable',
    `gross_total`          DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'basic + all allowances',
    `net_total`            DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'gross_total - deductions',
    `is_manually_adjusted` TINYINT(1) NOT NULL DEFAULT 0,
    `notes`                TEXT NULL DEFAULT NULL,
    `created_at`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_item_employee` (`payroll_run_id`, `employee_id`),
    KEY `idx_pri_employee` (`employee_id`),
    CONSTRAINT `fk_pri_run`      FOREIGN KEY (`payroll_run_id`) REFERENCES `payroll_runs`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pri_employee` FOREIGN KEY (`employee_id`)    REFERENCES `employees`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
