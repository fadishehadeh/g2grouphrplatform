-- ============================================================
-- Careers Portal Database (hr_careers)
-- Run this against a SEPARATE database from hr_system
-- ============================================================

CREATE DATABASE IF NOT EXISTS hr_careers
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE hr_careers;

-- ------------------------------------------------------------
-- 1. Job Seeker Accounts
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS job_seekers (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username            VARCHAR(60)  NOT NULL UNIQUE,
    email               VARCHAR(191) NOT NULL UNIQUE,
    password_hash       VARCHAR(255) NOT NULL,
    email_verified_at   DATETIME     NULL DEFAULT NULL,
    is_active           TINYINT(1)   NOT NULL DEFAULT 1,
    otp_code            VARCHAR(6)   NULL DEFAULT NULL,
    otp_expires_at      DATETIME     NULL DEFAULT NULL,
    otp_attempts        TINYINT      NOT NULL DEFAULT 0,
    otp_sent_count      TINYINT      NOT NULL DEFAULT 0,
    otp_sent_window_start DATETIME   NULL DEFAULT NULL,
    last_login_at       DATETIME     NULL DEFAULT NULL,
    created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 2. Job Seeker Profiles (one per seeker)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS job_seeker_profiles (
    id                          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_seeker_id               INT UNSIGNED NOT NULL UNIQUE,
    -- Personal
    first_name                  VARCHAR(100) NULL,
    last_name                   VARCHAR(100) NULL,
    middle_name                 VARCHAR(100) NULL,
    date_of_birth               DATE         NULL,
    gender                      ENUM('male','female','other','prefer_not_to_say') NULL,
    nationality                 VARCHAR(100) NULL,
    second_nationality          VARCHAR(100) NULL,
    -- Contact
    phone                       VARCHAR(30)  NULL,
    mobile                      VARCHAR(30)  NULL,
    whatsapp_number             VARCHAR(30)  NULL,
    address_line_1              VARCHAR(191) NULL,
    address_line_2              VARCHAR(191) NULL,
    city                        VARCHAR(100) NULL,
    state                       VARCHAR(100) NULL,
    country                     VARCHAR(100) NULL,
    postal_code                 VARCHAR(20)  NULL,
    -- Online presence
    linkedin_url                VARCHAR(255) NULL,
    portfolio_url               VARCHAR(255) NULL,
    github_url                  VARCHAR(255) NULL,
    website_url                 VARCHAR(255) NULL,
    -- Professional
    professional_summary        TEXT         NULL,
    current_job_title           VARCHAR(191) NULL,
    current_employer            VARCHAR(191) NULL,
    years_of_experience         DECIMAL(4,1) NULL,
    expected_salary             DECIMAL(12,2) NULL,
    salary_currency             VARCHAR(10)  NULL DEFAULT 'USD',
    notice_period_days          SMALLINT     NULL,
    available_from              DATE         NULL,
    willing_to_relocate         TINYINT(1)   NOT NULL DEFAULT 0,
    willing_to_travel           TINYINT(1)   NOT NULL DEFAULT 0,
    employment_type_preference  JSON         NULL COMMENT 'Array: full_time, part_time, contract, freelance, internship',
    -- Files
    photo_path                  VARCHAR(255) NULL,
    cv_file_path                VARCHAR(255) NULL,
    cv_original_name            VARCHAR(255) NULL,
    cv_uploaded_at              DATETIME     NULL,
    -- Timestamps
    created_at                  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_profile_seeker FOREIGN KEY (job_seeker_id) REFERENCES job_seekers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 3. CV Sections (all repeatable blocks in one table)
-- type: experience | education | skill | language | certification
--       project | award | volunteer | reference | publication
-- data JSON schema per type:
--   experience:    {employment_type, location, description}
--   education:     {degree, field_of_study, grade, description}
--   skill:         {level: beginner|intermediate|advanced|expert}
--   language:      {proficiency: basic|conversational|professional|fluent|native}
--   certification: {credential_id, credential_url, expiry_date}
--   project:       {url, technologies, description}
--   award:         {issuer, description}
--   volunteer:     {organization, cause, description}
--   reference:     {name, title, company, email, phone, relationship}
--   publication:   {publisher, url, description}
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS job_seeker_sections (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_seeker_id   INT UNSIGNED NOT NULL,
    type            ENUM('experience','education','skill','language','certification',
                         'project','award','volunteer','reference','publication') NOT NULL,
    title           VARCHAR(191) NULL COMMENT 'Job title / degree / skill name / language / cert name',
    subtitle        VARCHAR(191) NULL COMMENT 'Company / institution / issuer / skill category',
    data            JSON         NULL,
    start_date      DATE         NULL,
    end_date        DATE         NULL,
    is_current      TINYINT(1)   NOT NULL DEFAULT 0,
    display_order   SMALLINT     NOT NULL DEFAULT 0,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_sections_seeker_type (job_seeker_id, type),
    CONSTRAINT fk_section_seeker FOREIGN KEY (job_seeker_id) REFERENCES job_seekers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 4. Job Categories (HR managed)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS job_categories (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    slug        VARCHAR(100) NOT NULL UNIQUE,
    description TEXT         NULL,
    icon        VARCHAR(60)  NULL DEFAULT 'bi-briefcase',
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order  SMALLINT     NOT NULL DEFAULT 0,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO job_categories (name, slug, icon, sort_order) VALUES
('Information Technology',  'information-technology',  'bi-laptop',           1),
('Finance & Accounting',    'finance-accounting',      'bi-cash-stack',       2),
('Human Resources',         'human-resources',         'bi-people',           3),
('Sales & Marketing',       'sales-marketing',         'bi-graph-up-arrow',   4),
('Engineering',             'engineering',             'bi-gear-wide-connected', 5),
('Operations',              'operations',              'bi-boxes',            6),
('Customer Service',        'customer-service',        'bi-headset',          7),
('Legal & Compliance',      'legal-compliance',        'bi-shield-check',     8),
('Administration',          'administration',          'bi-building',         9),
('Other',                   'other',                   'bi-three-dots',      99);

-- ------------------------------------------------------------
-- 5. Jobs (HR-created postings)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS jobs (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_category_id         INT UNSIGNED NULL,
    title                   VARCHAR(191) NOT NULL,
    slug                    VARCHAR(191) NOT NULL UNIQUE,
    -- Denormalized company/dept (no FK to hr_system DB)
    company_name            VARCHAR(191) NULL,
    branch_name             VARCHAR(191) NULL,
    department_name         VARCHAR(191) NULL,
    location_city           VARCHAR(100) NULL,
    location_country        VARCHAR(100) NULL,
    -- Classification
    job_type                ENUM('full_time','part_time','contract','internship','freelance') NOT NULL DEFAULT 'full_time',
    experience_level        ENUM('entry','junior','mid','senior','lead','executive') NOT NULL DEFAULT 'mid',
    min_experience_years    TINYINT      NULL,
    max_experience_years    TINYINT      NULL,
    -- Compensation
    min_salary              DECIMAL(12,2) NULL,
    max_salary              DECIMAL(12,2) NULL,
    salary_currency         VARCHAR(10)  NULL DEFAULT 'USD',
    salary_visible          TINYINT(1)   NOT NULL DEFAULT 0,
    -- Content
    description             TEXT         NULL,
    requirements            TEXT         NULL,
    responsibilities        TEXT         NULL,
    benefits                TEXT         NULL,
    skills_required         JSON         NULL,
    education_required      VARCHAR(191) NULL,
    -- Logistics
    positions_count         TINYINT      NOT NULL DEFAULT 1,
    deadline                DATE         NULL,
    status                  ENUM('draft','open','closed','paused') NOT NULL DEFAULT 'draft',
    is_featured             TINYINT(1)   NOT NULL DEFAULT 0,
    views_count             INT UNSIGNED NOT NULL DEFAULT 0,
    -- HR ownership (stores hr_system user id — no FK, different DB)
    created_by_hr_user_id   INT UNSIGNED NULL,
    -- Timestamps
    created_at              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    published_at            DATETIME     NULL,
    closed_at               DATETIME     NULL,
    INDEX idx_jobs_status (status),
    INDEX idx_jobs_category (job_category_id),
    INDEX idx_jobs_type (job_type),
    INDEX idx_jobs_deadline (deadline),
    CONSTRAINT fk_job_category FOREIGN KEY (job_category_id) REFERENCES job_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 6. Job Applications (job-specific OR general bank job_id=NULL)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS job_applications (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_seeker_id           INT UNSIGNED NOT NULL,
    job_id                  INT UNSIGNED NULL COMMENT 'NULL = general job bank submission',
    cover_letter            TEXT         NULL,
    status                  ENUM('new','reviewing','shortlisted','interviewed',
                                 'offered','rejected','hired','withdrawn') NOT NULL DEFAULT 'new',
    hr_notes                TEXT         NULL,
    hr_rating               TINYINT      NULL COMMENT '1-5 stars',
    reviewed_by_hr_user_id  INT UNSIGNED NULL,
    reviewed_at             DATETIME     NULL,
    submitted_at            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_application (job_seeker_id, job_id),
    INDEX idx_app_status (status),
    INDEX idx_app_job (job_id),
    INDEX idx_app_seeker (job_seeker_id),
    INDEX idx_app_submitted (submitted_at),
    CONSTRAINT fk_app_seeker FOREIGN KEY (job_seeker_id) REFERENCES job_seekers(id) ON DELETE CASCADE,
    CONSTRAINT fk_app_job    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 7. Application Status History (full audit trail)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS application_status_history (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id          INT UNSIGNED NOT NULL,
    old_status              VARCHAR(30)  NULL,
    new_status              VARCHAR(30)  NOT NULL,
    changed_by_hr_user_id   INT UNSIGNED NULL,
    notes                   TEXT         NULL,
    changed_at              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_hist_app (application_id),
    CONSTRAINT fk_hist_app FOREIGN KEY (application_id) REFERENCES job_applications(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
