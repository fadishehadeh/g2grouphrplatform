-- Employee Intake Form — staging tables
-- Run against hr_system database before deploying the Intake module.

USE hr_system;

CREATE TABLE IF NOT EXISTS employee_intake_submissions (
    id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    token                VARCHAR(64)     NOT NULL COMMENT 'bin2hex(random_bytes(32)) — used in HR review URL',

    -- Personal info (plain text)
    first_name           VARCHAR(100)    NOT NULL,
    middle_name          VARCHAR(100)    NULL,
    last_name            VARCHAR(100)    NOT NULL,
    gender               ENUM('male','female','other','prefer_not_to_say') NULL,
    marital_status       ENUM('single','married','divorced','widowed','other') NULL,
    nationality          VARCHAR(100)    NULL,
    second_nationality   VARCHAR(100)    NULL,

    -- PII — encrypted with encrypt_field() (same cipher as employees table)
    personal_email       VARCHAR(500)    NULL,
    phone                VARCHAR(500)    NULL,
    alternate_phone      VARCHAR(500)    NULL,
    date_of_birth        VARCHAR(500)    NULL,
    id_number            VARCHAR(500)    NULL,
    passport_number      VARCHAR(500)    NULL,

    -- Address (plain text)
    address_line_1       VARCHAR(255)    NULL,
    address_line_2       VARCHAR(255)    NULL,
    city                 VARCHAR(100)    NULL,
    state                VARCHAR(100)    NULL,
    country              VARCHAR(100)    NULL,
    postal_code          VARCHAR(20)     NULL,

    -- Workflow
    status               ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    submitted_at         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at          DATETIME        NULL,
    reviewed_by          BIGINT UNSIGNED NULL COMMENT 'users.id of HR reviewer — no FK to avoid cascade issues',
    rejection_reason     TEXT            NULL,
    approved_employee_id BIGINT UNSIGNED NULL COMMENT 'employees.id set on approval — no FK constraint',

    -- Audit
    submitter_ip         VARCHAR(45)     NULL,
    submitter_ua         VARCHAR(500)    NULL,
    created_at           TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_intake_token (token),
    KEY idx_intake_status    (status),
    KEY idx_intake_submitted (submitted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS employee_intake_contacts (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    submission_id   BIGINT UNSIGNED NOT NULL,
    full_name       VARCHAR(150)    NOT NULL,
    relationship    VARCHAR(100)    NOT NULL,
    phone           VARCHAR(30)     NOT NULL,
    alternate_phone VARCHAR(30)     NULL,
    email           VARCHAR(150)    NULL,
    is_primary      TINYINT(1)      NOT NULL DEFAULT 0,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_intake_contacts_sub (submission_id),
    CONSTRAINT fk_intake_contacts_submission
        FOREIGN KEY (submission_id) REFERENCES employee_intake_submissions(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS employee_intake_documents (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    submission_id       BIGINT UNSIGNED NOT NULL,
    document_type_id    BIGINT UNSIGNED NULL,
    category_id         BIGINT UNSIGNED NULL COMMENT 'denormalised from document_type at save time',
    title               VARCHAR(150)    NOT NULL,
    document_number     VARCHAR(100)    NULL,
    original_file_name  VARCHAR(255)    NOT NULL,
    stored_file_name    VARCHAR(255)    NOT NULL,
    file_path           VARCHAR(255)    NOT NULL COMMENT 'relative: storage/uploads/intake/{id}/...',
    file_extension      VARCHAR(20)     NULL,
    mime_type           VARCHAR(100)    NULL,
    file_size           BIGINT UNSIGNED NULL,
    issue_date          DATE            NULL,
    expiry_date         DATE            NULL,
    created_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_intake_documents_sub (submission_id),
    CONSTRAINT fk_intake_documents_submission
        FOREIGN KEY (submission_id) REFERENCES employee_intake_submissions(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
