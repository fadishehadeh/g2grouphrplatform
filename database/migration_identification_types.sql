-- Migration: Add support for multiple ID types by country
-- Date: 2026-06-02
-- Purpose: Replace single QID field with flexible multi-country ID support

USE hr_system;

-- 1. Create identification_types table
CREATE TABLE IF NOT EXISTS identification_types (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    country VARCHAR(100) NULL,
    description TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_code (code),
    KEY idx_active_sort (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Create employee_identifications table
CREATE TABLE IF NOT EXISTS employee_identifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL,
    identification_type_id BIGINT UNSIGNED NOT NULL,
    id_number VARCHAR(500) NOT NULL,           -- encrypted via encrypt_field()
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    issue_date DATE NULL,
    expiry_date DATE NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_emp_id_employee FOREIGN KEY (employee_id) REFERENCES employees(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_emp_id_type FOREIGN KEY (identification_type_id) REFERENCES identification_types(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    KEY idx_employee_id (employee_id),
    KEY idx_type_id (identification_type_id),
    KEY idx_primary (is_primary),
    KEY idx_employee_primary (employee_id, is_primary)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Create intake_submission_identifications table
CREATE TABLE IF NOT EXISTS intake_submission_identifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    submission_id BIGINT UNSIGNED NOT NULL,
    identification_type_id BIGINT UNSIGNED NOT NULL,
    id_number VARCHAR(500) NOT NULL,           -- encrypted via encrypt_field()
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    issue_date DATE NULL,
    expiry_date DATE NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_intake_id_submission FOREIGN KEY (submission_id) REFERENCES employee_intake_submissions(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_intake_id_type FOREIGN KEY (identification_type_id) REFERENCES identification_types(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    KEY idx_submission_id (submission_id),
    KEY idx_type_id (identification_type_id),
    KEY idx_primary (is_primary)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Seed identification_types data
INSERT INTO identification_types (code, name, country, description, sort_order) VALUES
    ('QID', 'Qatar National ID', 'Qatar', 'Qatar ID Number', 1),
    ('UAE_ID', 'UAE National ID', 'United Arab Emirates', 'Emirates ID Number', 2),
    ('KSA_ID', 'KSA National ID', 'Saudi Arabia', 'Saudi Arabia ID Number', 3),
    ('BH_ID', 'Bahrain National ID', 'Bahrain', 'Bahrain ID Number', 4),
    ('OM_ID', 'Oman National ID', 'Oman', 'Oman ID Number', 5),
    ('KW_ID', 'Kuwait National ID', 'Kuwait', 'Kuwait ID Number', 6),
    ('EG_ID', 'Egypt National ID', 'Egypt', 'Egypt ID Number', 7),
    ('JO_ID', 'Jordan National ID', 'Jordan', 'Jordan ID Number', 8),
    ('LB_ID', 'Lebanon National ID', 'Lebanon', 'Lebanon ID Number', 9),
    ('PASSPORT', 'Passport', 'International', 'International Passport', 10),
    ('VISA_ID', 'Visa ID / Residence Permit', 'International', 'Visa ID or Residence Permit Number', 11),
    ('DRIVING_LICENSE', 'Driving License', 'International', 'Driving License Number', 12),
    ('OTHER_ID', 'Other National ID', 'International', 'Other form of identification', 20);

-- 5. Migrate existing id_number data to new structure
-- This creates employee_identifications records for all employees with existing id_number
INSERT INTO employee_identifications (employee_id, identification_type_id, id_number, is_primary, created_at)
SELECT
    e.id,
    (SELECT id FROM identification_types WHERE code = 'QID' LIMIT 1),
    e.id_number,
    1,
    NOW()
FROM employees e
WHERE e.id_number IS NOT NULL AND e.id_number != ''
ON DUPLICATE KEY UPDATE id_number = VALUES(id_number);

-- 6. Migration complete
SELECT 'Migration completed: Identification types system initialized' AS status;
