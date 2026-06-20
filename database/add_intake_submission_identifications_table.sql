-- Create intake_submission_identifications table
CREATE TABLE IF NOT EXISTS intake_submission_identifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    submission_id BIGINT UNSIGNED NOT NULL,
    identification_type_id BIGINT UNSIGNED NOT NULL,
    id_number LONGTEXT NOT NULL COMMENT 'AES-256-CBC encrypted',
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    issue_date DATE,
    expiry_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (submission_id) REFERENCES employee_intake_submissions(id) ON DELETE CASCADE,
    FOREIGN KEY (identification_type_id) REFERENCES identification_types(id) ON DELETE RESTRICT,
    INDEX idx_submission (submission_id),
    INDEX idx_type (identification_type_id),
    INDEX idx_primary (is_primary)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
