-- Letters Module Migration
-- Run this against your hr_system database to enable the Letter Generation feature.

USE hr_system;

-- -----------------------------------------------------------------------
-- 1. Table: letter_requests
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS letter_requests (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id    BIGINT UNSIGNED NOT NULL,
    letter_type    ENUM('salary_certificate','employment_certificate','experience_letter','noc','bank_letter') NOT NULL,
    purpose        VARCHAR(255) NULL,
    notes          TEXT NULL,
    status         ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    rejection_reason TEXT NULL,
    salary_amount  DECIMAL(15,2) NULL,
    additional_info TEXT NULL,
    letter_content LONGTEXT NULL,
    generated_at   DATETIME NULL,
    generated_by   BIGINT UNSIGNED NULL,
    requested_by   BIGINT UNSIGNED NOT NULL,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_letter_requests_employee  FOREIGN KEY (employee_id)  REFERENCES employees(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_letter_requests_generated FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_letter_requests_requested FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    KEY idx_letter_requests_employee_id (employee_id),
    KEY idx_letter_requests_status      (status),
    KEY idx_letter_requests_letter_type (letter_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- 2. Permissions
-- -----------------------------------------------------------------------
INSERT IGNORE INTO permissions (module_name, action_name, code, description) VALUES
('letters', 'request', 'letters.request', 'Request HR letters (salary certificate, NOC, etc.)'),
('letters', 'manage',  'letters.manage',  'View and generate employee letters');

-- -----------------------------------------------------------------------
-- 3. Role assignments
--    Role IDs (from seed.sql):
--      1 = super_admin  2 = hr_admin  3 = manager  4 = employee
-- -----------------------------------------------------------------------

-- super_admin and hr_admin get letters.manage (and also letters.request)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r, permissions p
WHERE r.code IN ('super_admin', 'hr_admin')
  AND p.code IN ('letters.manage', 'letters.request');

-- manager and employee get letters.request
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r, permissions p
WHERE r.code IN ('manager', 'employee')
  AND p.code = 'letters.request';
