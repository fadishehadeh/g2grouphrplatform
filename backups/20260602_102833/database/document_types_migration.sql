-- Document Types Migration
-- Run once. Safe to ignore ALTER TABLE error if document_type_id column already exists.

CREATE TABLE IF NOT EXISTS document_types (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100)    NOT NULL,
    category_id     BIGINT UNSIGNED NOT NULL,
    requires_expiry TINYINT(1)      NOT NULL DEFAULT 0,
    is_active       TINYINT(1)      NOT NULL DEFAULT 1,
    sort_order      INT             NOT NULL DEFAULT 0,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_doctype_category FOREIGN KEY (category_id) REFERENCES document_categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add type reference to employee_documents (nullable for backward compatibility)
ALTER TABLE employee_documents ADD COLUMN document_type_id BIGINT UNSIGNED NULL AFTER category_id;

-- Ensure the standard categories exist (uses INSERT … SELECT to avoid duplicates)
INSERT INTO document_categories (name, code, requires_expiry, is_active)
SELECT 'Identity Documents', 'IDENTITY', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM document_categories WHERE code = 'IDENTITY');

INSERT INTO document_categories (name, code, requires_expiry, is_active)
SELECT 'Work Authorization', 'WORK_AUTH', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM document_categories WHERE code = 'WORK_AUTH');

INSERT INTO document_categories (name, code, requires_expiry, is_active)
SELECT 'Health & Medical', 'HEALTH', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM document_categories WHERE code = 'HEALTH');

INSERT INTO document_categories (name, code, requires_expiry, is_active)
SELECT 'Education & Qualifications', 'EDUCATION', 0, 1
WHERE NOT EXISTS (SELECT 1 FROM document_categories WHERE code = 'EDUCATION');

INSERT INTO document_categories (name, code, requires_expiry, is_active)
SELECT 'Employment Documents', 'EMPLOYMENT', 0, 1
WHERE NOT EXISTS (SELECT 1 FROM document_categories WHERE code = 'EMPLOYMENT');

INSERT INTO document_categories (name, code, requires_expiry, is_active)
SELECT 'Other', 'OTHER', 0, 1
WHERE NOT EXISTS (SELECT 1 FROM document_categories WHERE code = 'OTHER');

-- Seed default document types
INSERT INTO document_types (name, category_id, requires_expiry, is_active, sort_order)
SELECT 'Passport', id, 1, 1, 10 FROM document_categories WHERE code = 'IDENTITY' LIMIT 1;

INSERT INTO document_types (name, category_id, requires_expiry, is_active, sort_order)
SELECT 'Qatar ID (QID)', id, 1, 1, 20 FROM document_categories WHERE code = 'IDENTITY' LIMIT 1;

INSERT INTO document_types (name, category_id, requires_expiry, is_active, sort_order)
SELECT 'Driving License', id, 1, 1, 30 FROM document_categories WHERE code = 'IDENTITY' LIMIT 1;

INSERT INTO document_types (name, category_id, requires_expiry, is_active, sort_order)
SELECT 'Visa', id, 1, 1, 40 FROM document_categories WHERE code = 'IDENTITY' LIMIT 1;

INSERT INTO document_types (name, category_id, requires_expiry, is_active, sort_order)
SELECT 'Work Permit / RP', id, 1, 1, 10 FROM document_categories WHERE code = 'WORK_AUTH' LIMIT 1;

INSERT INTO document_types (name, category_id, requires_expiry, is_active, sort_order)
SELECT 'NOC Letter', id, 0, 1, 20 FROM document_categories WHERE code = 'WORK_AUTH' LIMIT 1;

INSERT INTO document_types (name, category_id, requires_expiry, is_active, sort_order)
SELECT 'Health Insurance Card', id, 1, 1, 10 FROM document_categories WHERE code = 'HEALTH' LIMIT 1;

INSERT INTO document_types (name, category_id, requires_expiry, is_active, sort_order)
SELECT 'Medical Fitness Certificate', id, 1, 1, 20 FROM document_categories WHERE code = 'HEALTH' LIMIT 1;

INSERT INTO document_types (name, category_id, requires_expiry, is_active, sort_order)
SELECT 'Degree Certificate', id, 0, 1, 10 FROM document_categories WHERE code = 'EDUCATION' LIMIT 1;

INSERT INTO document_types (name, category_id, requires_expiry, is_active, sort_order)
SELECT 'Diploma', id, 0, 1, 20 FROM document_categories WHERE code = 'EDUCATION' LIMIT 1;

INSERT INTO document_types (name, category_id, requires_expiry, is_active, sort_order)
SELECT 'Transcript', id, 0, 1, 30 FROM document_categories WHERE code = 'EDUCATION' LIMIT 1;

INSERT INTO document_types (name, category_id, requires_expiry, is_active, sort_order)
SELECT 'Experience Letter', id, 0, 1, 10 FROM document_categories WHERE code = 'EMPLOYMENT' LIMIT 1;

INSERT INTO document_types (name, category_id, requires_expiry, is_active, sort_order)
SELECT 'Offer Letter', id, 0, 1, 20 FROM document_categories WHERE code = 'EMPLOYMENT' LIMIT 1;

INSERT INTO document_types (name, category_id, requires_expiry, is_active, sort_order)
SELECT 'Employment Contract', id, 0, 1, 30 FROM document_categories WHERE code = 'EMPLOYMENT' LIMIT 1;

INSERT INTO document_types (name, category_id, requires_expiry, is_active, sort_order)
SELECT 'Salary Certificate', id, 0, 1, 40 FROM document_categories WHERE code = 'EMPLOYMENT' LIMIT 1;

INSERT INTO document_types (name, category_id, requires_expiry, is_active, sort_order)
SELECT 'Bank Account Details', id, 0, 1, 10 FROM document_categories WHERE code = 'OTHER' LIMIT 1;

INSERT INTO document_types (name, category_id, requires_expiry, is_active, sort_order)
SELECT 'Other Document', id, 0, 1, 99 FROM document_categories WHERE code = 'OTHER' LIMIT 1;
