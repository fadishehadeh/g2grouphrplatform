-- Photo, Company Logo & Insurance Migration
USE hr_system;

-- 1. Company logo
ALTER TABLE companies ADD COLUMN logo_path VARCHAR(255) NULL AFTER postal_code;

-- 2. Insurance cards
CREATE TABLE IF NOT EXISTS employee_insurance (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id    BIGINT UNSIGNED NOT NULL,
    provider_name  VARCHAR(150) NULL,
    policy_number  VARCHAR(100) NULL,
    card_number    VARCHAR(100) NULL,
    member_id      VARCHAR(100) NULL,
    coverage_type  VARCHAR(100) NULL,
    has_insurance  TINYINT(1) NOT NULL DEFAULT 0,
    start_date     DATE NULL,
    expiry_date    DATE NULL,
    notes          TEXT NULL,
    created_by     BIGINT UNSIGNED NULL,
    updated_by     BIGINT UNSIGNED NULL,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_insurance_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_insurance_created  FOREIGN KEY (created_by)  REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_insurance_updated  FOREIGN KEY (updated_by)  REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    KEY idx_insurance_employee_id (employee_id),
    KEY idx_insurance_expiry_date (expiry_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
