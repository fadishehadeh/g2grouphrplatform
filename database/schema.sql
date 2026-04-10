CREATE DATABASE IF NOT EXISTS hr_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hr_system;

CREATE TABLE IF NOT EXISTS roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    is_system TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    module_name VARCHAR(100) NOT NULL,
    action_name VARCHAR(100) NOT NULL,
    code VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_permissions_module_action (module_name, action_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id BIGINT UNSIGNED NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    status ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
    must_change_password TINYINT(1) NOT NULL DEFAULT 0,
    last_login_at DATETIME NULL,
    last_password_change_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    KEY idx_users_role_id (role_id),
    KEY idx_users_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
    role_id BIGINT UNSIGNED NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash VARCHAR(255) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    KEY idx_password_resets_user_id (user_id),
    KEY idx_password_resets_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    session_id VARCHAR(128) NOT NULL UNIQUE,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    last_activity_at DATETIME NOT NULL,
    expires_at DATETIME NULL,
    revoked_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_sessions_user FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    KEY idx_user_sessions_user_id (user_id),
    KEY idx_user_sessions_last_activity (last_activity_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS companies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    legal_name VARCHAR(200) NULL,
    registration_number VARCHAR(100) NULL,
    tax_number VARCHAR(100) NULL,
    code VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(150) NULL,
    phone VARCHAR(30) NULL,
    address_line_1 VARCHAR(255) NULL,
    address_line_2 VARCHAR(255) NULL,
    city VARCHAR(100) NULL,
    state VARCHAR(100) NULL,
    country VARCHAR(100) NULL,
    postal_code VARCHAR(20) NULL,
    timezone VARCHAR(100) NOT NULL DEFAULT 'UTC',
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS branches (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    code VARCHAR(50) NOT NULL,
    email VARCHAR(150) NULL,
    phone VARCHAR(30) NULL,
    address_line_1 VARCHAR(255) NULL,
    address_line_2 VARCHAR(255) NULL,
    city VARCHAR(100) NULL,
    state VARCHAR(100) NULL,
    country VARCHAR(100) NULL,
    postal_code VARCHAR(20) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_branches_company FOREIGN KEY (company_id) REFERENCES companies(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    UNIQUE KEY uq_branches_company_code (company_id, code),
    UNIQUE KEY uq_branches_company_name (company_id, name),
    KEY idx_branches_company_id (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS departments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NULL,
    parent_department_id BIGINT UNSIGNED NULL,
    name VARCHAR(150) NOT NULL,
    code VARCHAR(50) NOT NULL,
    description VARCHAR(255) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_departments_company FOREIGN KEY (company_id) REFERENCES companies(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_departments_branch FOREIGN KEY (branch_id) REFERENCES branches(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_departments_parent FOREIGN KEY (parent_department_id) REFERENCES departments(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    UNIQUE KEY uq_departments_company_code (company_id, code),
    UNIQUE KEY uq_departments_company_name (company_id, name),
    KEY idx_departments_branch_id (branch_id),
    KEY idx_departments_parent_id (parent_department_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS teams (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    department_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    code VARCHAR(50) NOT NULL,
    description VARCHAR(255) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_teams_department FOREIGN KEY (department_id) REFERENCES departments(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    UNIQUE KEY uq_teams_department_code (department_id, code),
    UNIQUE KEY uq_teams_department_name (department_id, name),
    KEY idx_teams_department_id (department_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS job_titles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    code VARCHAR(50) NOT NULL UNIQUE,
    level_rank INT NOT NULL DEFAULT 1,
    description VARCHAR(255) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS designations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    code VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS approval_workflows (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    module_code VARCHAR(50) NOT NULL,
    name VARCHAR(150) NOT NULL,
    company_id BIGINT UNSIGNED NULL,
    description VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_approval_workflows_company FOREIGN KEY (company_id) REFERENCES companies(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_approval_workflows_created_by FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    KEY idx_approval_workflows_module_code (module_code),
    KEY idx_approval_workflows_company_id (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS approval_workflow_steps (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workflow_id BIGINT UNSIGNED NOT NULL,
    step_order INT NOT NULL,
    approver_type ENUM('manager','hr_admin','specific_role','specific_user') NOT NULL,
    role_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NULL,
    is_required TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_approval_workflow_steps_workflow FOREIGN KEY (workflow_id) REFERENCES approval_workflows(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_approval_workflow_steps_role FOREIGN KEY (role_id) REFERENCES roles(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_approval_workflow_steps_user FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    UNIQUE KEY uq_workflow_step (workflow_id, step_order),
    KEY idx_approval_workflow_steps_role_id (role_id),
    KEY idx_approval_workflow_steps_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employees (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL UNIQUE,
    employee_code VARCHAR(30) NOT NULL UNIQUE,
    company_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NULL,
    department_id BIGINT UNSIGNED NULL,
    team_id BIGINT UNSIGNED NULL,
    job_title_id BIGINT UNSIGNED NULL,
    designation_id BIGINT UNSIGNED NULL,
    manager_employee_id BIGINT UNSIGNED NULL,
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100) NULL,
    last_name VARCHAR(100) NOT NULL,
    work_email VARCHAR(150) NOT NULL,
    personal_email VARCHAR(150) NULL,
    phone VARCHAR(30) NULL,
    alternate_phone VARCHAR(30) NULL,
    date_of_birth DATE NULL,
    gender ENUM('male','female','other','prefer_not_to_say') NULL,
    marital_status ENUM('single','married','divorced','widowed','other') NULL,
    nationality VARCHAR(100) NULL,
    second_nationality VARCHAR(100) NULL,
    address_line_1 VARCHAR(255) NULL,
    address_line_2 VARCHAR(255) NULL,
    city VARCHAR(100) NULL,
    state VARCHAR(100) NULL,
    country VARCHAR(100) NULL,
    postal_code VARCHAR(20) NULL,
    id_number VARCHAR(100) NULL,
    passport_number VARCHAR(100) NULL,
    employment_type ENUM('full_time','part_time','contract','intern','temporary') NOT NULL DEFAULT 'full_time',
    contract_type VARCHAR(100) NULL,
    joining_date DATE NULL,
    probation_start_date DATE NULL,
    probation_end_date DATE NULL,
    employee_status ENUM('draft','active','on_leave','inactive','resigned','terminated','archived') NOT NULL DEFAULT 'draft',
    profile_photo VARCHAR(255) NULL,
    notes TEXT NULL,
    archived_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_employees_user FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_employees_company FOREIGN KEY (company_id) REFERENCES companies(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_employees_branch FOREIGN KEY (branch_id) REFERENCES branches(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_employees_department FOREIGN KEY (department_id) REFERENCES departments(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_employees_team FOREIGN KEY (team_id) REFERENCES teams(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_employees_job_title FOREIGN KEY (job_title_id) REFERENCES job_titles(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_employees_designation FOREIGN KEY (designation_id) REFERENCES designations(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_employees_manager FOREIGN KEY (manager_employee_id) REFERENCES employees(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_employees_created_by FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_employees_updated_by FOREIGN KEY (updated_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    KEY idx_employees_company_id (company_id),
    KEY idx_employees_branch_id (branch_id),
    KEY idx_employees_department_id (department_id),
    KEY idx_employees_team_id (team_id),
    KEY idx_employees_job_title_id (job_title_id),
    KEY idx_employees_manager_employee_id (manager_employee_id),
    KEY idx_employees_status (employee_status),
    KEY idx_employees_joining_date (joining_date),
    KEY idx_employees_name (first_name, last_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employee_reporting_lines (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL,
    manager_employee_id BIGINT UNSIGNED NOT NULL,
    relationship_type ENUM('line_manager','dotted_line','leave_approver') NOT NULL DEFAULT 'line_manager',
    priority_order INT NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    effective_from DATE NOT NULL,
    effective_to DATE NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_employee_reporting_employee FOREIGN KEY (employee_id) REFERENCES employees(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_employee_reporting_manager FOREIGN KEY (manager_employee_id) REFERENCES employees(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_employee_reporting_created_by FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    KEY idx_employee_reporting_employee (employee_id),
    KEY idx_employee_reporting_manager (manager_employee_id),
    KEY idx_employee_reporting_type (relationship_type),
    KEY idx_employee_reporting_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employee_emergency_contacts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    relationship VARCHAR(100) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    alternate_phone VARCHAR(30) NULL,
    email VARCHAR(150) NULL,
    address VARCHAR(255) NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_employee_emergency_contacts_employee FOREIGN KEY (employee_id) REFERENCES employees(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    KEY idx_employee_emergency_contacts_employee_id (employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employee_notes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL,
    note_type ENUM('general','warning','hr_note','manager_note') NOT NULL DEFAULT 'general',
    note_text TEXT NOT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_employee_notes_employee FOREIGN KEY (employee_id) REFERENCES employees(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_employee_notes_created_by FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    KEY idx_employee_notes_employee_id (employee_id),
    KEY idx_employee_notes_type (note_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employee_status_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL,
    previous_status VARCHAR(50) NULL,
    new_status VARCHAR(50) NOT NULL,
    effective_date DATE NOT NULL,
    remarks VARCHAR(255) NULL,
    changed_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_employee_status_history_employee FOREIGN KEY (employee_id) REFERENCES employees(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_employee_status_history_changed_by FOREIGN KEY (changed_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    KEY idx_employee_status_history_employee_id (employee_id),
    KEY idx_employee_status_history_effective_date (effective_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employee_history_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL,
    actor_user_id BIGINT UNSIGNED NULL,
    action_name VARCHAR(100) NOT NULL,
    field_name VARCHAR(100) NULL,
    old_value TEXT NULL,
    new_value TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_employee_history_logs_employee FOREIGN KEY (employee_id) REFERENCES employees(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_employee_history_logs_actor FOREIGN KEY (actor_user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    KEY idx_employee_history_logs_employee_id (employee_id),
    KEY idx_employee_history_logs_actor_user_id (actor_user_id),
    KEY idx_employee_history_logs_action_name (action_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS document_categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    code VARCHAR(50) NOT NULL UNIQUE,
    requires_expiry TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employee_documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL,
    category_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(150) NOT NULL,
    document_number VARCHAR(100) NULL,
    original_file_name VARCHAR(255) NOT NULL,
    stored_file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_extension VARCHAR(20) NULL,
    mime_type VARCHAR(100) NULL,
    file_size BIGINT UNSIGNED NULL,
    issue_date DATE NULL,
    expiry_date DATE NULL,
    version_no INT NOT NULL DEFAULT 1,
    is_current TINYINT(1) NOT NULL DEFAULT 1,
    visibility_scope ENUM('employee','manager','hr','admin') NOT NULL DEFAULT 'hr',
    status ENUM('active','replaced','archived') NOT NULL DEFAULT 'active',
    uploaded_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_employee_documents_employee FOREIGN KEY (employee_id) REFERENCES employees(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_employee_documents_category FOREIGN KEY (category_id) REFERENCES document_categories(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_employee_documents_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    KEY idx_employee_documents_employee_id (employee_id),
    KEY idx_employee_documents_category_id (category_id),
    KEY idx_employee_documents_expiry_date (expiry_date),
    KEY idx_employee_documents_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employee_document_versions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_document_id BIGINT UNSIGNED NOT NULL,
    version_no INT NOT NULL,
    original_file_name VARCHAR(255) NOT NULL,
    stored_file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NULL,
    file_size BIGINT UNSIGNED NULL,
    uploaded_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_employee_document_versions_document FOREIGN KEY (employee_document_id) REFERENCES employee_documents(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_employee_document_versions_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    UNIQUE KEY uq_document_version (employee_document_id, version_no),
    KEY idx_employee_document_versions_uploaded_by (uploaded_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS document_alerts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_document_id BIGINT UNSIGNED NOT NULL,
    alert_type ENUM('30_days','15_days','7_days','expired') NOT NULL,
    alert_date DATE NOT NULL,
    sent_to_user_id BIGINT UNSIGNED NULL,
    status ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_document_alerts_document FOREIGN KEY (employee_document_id) REFERENCES employee_documents(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_document_alerts_sent_to FOREIGN KEY (sent_to_user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    UNIQUE KEY uq_document_alert (employee_document_id, alert_type, alert_date),
    KEY idx_document_alerts_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS leave_types (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    is_paid TINYINT(1) NOT NULL DEFAULT 1,
    requires_balance TINYINT(1) NOT NULL DEFAULT 1,
    requires_attachment TINYINT(1) NOT NULL DEFAULT 0,
    requires_hr_approval TINYINT(1) NOT NULL DEFAULT 0,
    allow_half_day TINYINT(1) NOT NULL DEFAULT 0,
    default_days DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    carry_forward_allowed TINYINT(1) NOT NULL DEFAULT 0,
    carry_forward_limit DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    notice_days_required INT NOT NULL DEFAULT 0,
    max_days_per_request DECIMAL(6,2) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS leave_policies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    company_id BIGINT UNSIGNED NULL,
    description VARCHAR(255) NULL,
    accrual_frequency ENUM('none','monthly','quarterly','yearly') NOT NULL DEFAULT 'yearly',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_leave_policies_company FOREIGN KEY (company_id) REFERENCES companies(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_leave_policies_created_by FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    KEY idx_leave_policies_company_id (company_id),
    KEY idx_leave_policies_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS leave_policy_rules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    leave_policy_id BIGINT UNSIGNED NOT NULL,
    leave_type_id BIGINT UNSIGNED NOT NULL,
    department_id BIGINT UNSIGNED NULL,
    job_title_id BIGINT UNSIGNED NULL,
    employment_type ENUM('full_time','part_time','contract','intern','temporary') NULL,
    annual_allocation DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    accrual_rate_monthly DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    carry_forward_limit DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    max_consecutive_days DECIMAL(6,2) NULL,
    min_service_months INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_leave_policy_rules_policy FOREIGN KEY (leave_policy_id) REFERENCES leave_policies(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_leave_policy_rules_type FOREIGN KEY (leave_type_id) REFERENCES leave_types(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_leave_policy_rules_department FOREIGN KEY (department_id) REFERENCES departments(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_leave_policy_rules_job_title FOREIGN KEY (job_title_id) REFERENCES job_titles(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    KEY idx_leave_policy_rules_policy_id (leave_policy_id),
    KEY idx_leave_policy_rules_type_id (leave_type_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS weekend_settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NULL,
    day_of_week TINYINT UNSIGNED NOT NULL,
    is_weekend TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_weekend_settings_company FOREIGN KEY (company_id) REFERENCES companies(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_weekend_settings_branch FOREIGN KEY (branch_id) REFERENCES branches(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    UNIQUE KEY uq_weekend_setting (company_id, branch_id, day_of_week),
    KEY idx_weekend_settings_day_of_week (day_of_week)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS holidays (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NULL,
    name VARCHAR(150) NOT NULL,
    holiday_date DATE NOT NULL,
    holiday_type ENUM('public','company','branch') NOT NULL DEFAULT 'public',
    is_recurring TINYINT(1) NOT NULL DEFAULT 0,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_holidays_company FOREIGN KEY (company_id) REFERENCES companies(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_holidays_branch FOREIGN KEY (branch_id) REFERENCES branches(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    KEY idx_holidays_date (holiday_date),
    KEY idx_holidays_company_branch (company_id, branch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS leave_balances (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL,
    leave_type_id BIGINT UNSIGNED NOT NULL,
    balance_year YEAR NOT NULL,
    opening_balance DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    accrued DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    used_amount DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    adjusted_amount DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    closing_balance DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_leave_balances_employee FOREIGN KEY (employee_id) REFERENCES employees(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_leave_balances_type FOREIGN KEY (leave_type_id) REFERENCES leave_types(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    UNIQUE KEY uq_leave_balance_year (employee_id, leave_type_id, balance_year),
    KEY idx_leave_balances_year (balance_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS leave_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL,
    leave_type_id BIGINT UNSIGNED NOT NULL,
    workflow_id BIGINT UNSIGNED NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    start_session ENUM('full','first_half','second_half') NOT NULL DEFAULT 'full',
    end_session ENUM('full','first_half','second_half') NOT NULL DEFAULT 'full',
    days_requested DECIMAL(6,2) NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('draft','submitted','pending_manager','pending_hr','approved','rejected','cancelled','withdrawn') NOT NULL DEFAULT 'submitted',
    current_step_order INT NOT NULL DEFAULT 1,
    rejection_reason VARCHAR(255) NULL,
    submitted_at DATETIME NULL,
    decided_at DATETIME NULL,
    cancelled_at DATETIME NULL,
    withdrawn_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_leave_requests_employee FOREIGN KEY (employee_id) REFERENCES employees(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_leave_requests_type FOREIGN KEY (leave_type_id) REFERENCES leave_types(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_leave_requests_workflow FOREIGN KEY (workflow_id) REFERENCES approval_workflows(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    KEY idx_leave_requests_employee_id (employee_id),
    KEY idx_leave_requests_type_id (leave_type_id),
    KEY idx_leave_requests_status (status),
    KEY idx_leave_requests_dates (start_date, end_date),
    KEY idx_leave_requests_submitted_at (submitted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS leave_request_attachments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    leave_request_id BIGINT UNSIGNED NOT NULL,
    original_file_name VARCHAR(255) NOT NULL,
    stored_file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NULL,
    file_size BIGINT UNSIGNED NULL,
    uploaded_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_leave_request_attachments_request FOREIGN KEY (leave_request_id) REFERENCES leave_requests(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_leave_request_attachments_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    KEY idx_leave_request_attachments_request_id (leave_request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS leave_approvals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    leave_request_id BIGINT UNSIGNED NOT NULL,
    step_order INT NOT NULL,
    approver_user_id BIGINT UNSIGNED NULL,
    approver_role_id BIGINT UNSIGNED NULL,
    decision ENUM('pending','approved','rejected','skipped') NOT NULL DEFAULT 'pending',
    comments VARCHAR(255) NULL,
    acted_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_leave_approvals_request FOREIGN KEY (leave_request_id) REFERENCES leave_requests(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_leave_approvals_approver_user FOREIGN KEY (approver_user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_leave_approvals_approver_role FOREIGN KEY (approver_role_id) REFERENCES roles(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    KEY idx_leave_approvals_request_id (leave_request_id),
    KEY idx_leave_approvals_approver_user_id (approver_user_id),
    KEY idx_leave_approvals_decision (decision)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS leave_accrual_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL,
    leave_type_id BIGINT UNSIGNED NOT NULL,
    balance_year YEAR NOT NULL,
    amount DECIMAL(6,2) NOT NULL,
    entry_type ENUM('accrual','carry_forward','adjustment','deduction','reversal') NOT NULL,
    reference_type VARCHAR(100) NULL,
    reference_id BIGINT UNSIGNED NULL,
    remarks VARCHAR(255) NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_leave_accrual_logs_employee FOREIGN KEY (employee_id) REFERENCES employees(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_leave_accrual_logs_type FOREIGN KEY (leave_type_id) REFERENCES leave_types(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_leave_accrual_logs_created_by FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    KEY idx_leave_accrual_logs_employee_year (employee_id, balance_year),
    KEY idx_leave_accrual_logs_type_year (leave_type_id, balance_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS announcements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_announcements_created_by FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_announcements_updated_by FOREIGN KEY (updated_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    KEY idx_announcements_status (status),
    KEY idx_announcements_period (starts_at, ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS announcement_targets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    announcement_id BIGINT UNSIGNED NOT NULL,
    target_type ENUM('all','role','department','branch','employee') NOT NULL DEFAULT 'all',
    target_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_announcement_targets_announcement FOREIGN KEY (announcement_id) REFERENCES announcements(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    KEY idx_announcement_targets_announcement_id (announcement_id),
    KEY idx_announcement_targets_target (target_type, target_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS announcement_reads (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    announcement_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    read_at DATETIME NOT NULL,
    CONSTRAINT fk_announcement_reads_announcement FOREIGN KEY (announcement_id) REFERENCES announcements(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_announcement_reads_user FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    UNIQUE KEY uq_announcement_read (announcement_id, user_id),
    KEY idx_announcement_reads_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    notification_type VARCHAR(100) NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    reference_type VARCHAR(100) NULL,
    reference_id BIGINT UNSIGNED NULL,
    action_url VARCHAR(255) NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    read_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    KEY idx_notifications_user_read (user_id, is_read),
    KEY idx_notifications_type (notification_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_queue (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    to_email VARCHAR(150) NOT NULL,
    subject VARCHAR(200) NOT NULL,
    body_html MEDIUMTEXT NULL,
    body_text MEDIUMTEXT NULL,
    related_type VARCHAR(100) NULL,
    related_id BIGINT UNSIGNED NULL,
    status ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
    attempts INT NOT NULL DEFAULT 0,
    last_error TEXT NULL,
    scheduled_at DATETIME NULL,
    sent_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_email_queue_user FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    KEY idx_email_queue_status (status),
    KEY idx_email_queue_scheduled_at (scheduled_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS onboarding_checklist_templates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NULL,
    name VARCHAR(150) NOT NULL,
    description VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_onboarding_templates_company FOREIGN KEY (company_id) REFERENCES companies(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_onboarding_templates_created_by FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    KEY idx_onboarding_templates_company_id (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS onboarding_template_tasks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template_id BIGINT UNSIGNED NOT NULL,
    task_name VARCHAR(150) NOT NULL,
    task_code VARCHAR(50) NOT NULL,
    description VARCHAR(255) NULL,
    sort_order INT NOT NULL DEFAULT 1,
    assignee_role_id BIGINT UNSIGNED NULL,
    is_required TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_onboarding_template_tasks_template FOREIGN KEY (template_id) REFERENCES onboarding_checklist_templates(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_onboarding_template_tasks_role FOREIGN KEY (assignee_role_id) REFERENCES roles(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    UNIQUE KEY uq_onboarding_template_task_code (template_id, task_code),
    KEY idx_onboarding_template_tasks_sort_order (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employee_onboarding (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL,
    template_id BIGINT UNSIGNED NULL,
    start_date DATE NULL,
    due_date DATE NULL,
    status ENUM('pending','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
    progress_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    completed_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_employee_onboarding_employee FOREIGN KEY (employee_id) REFERENCES employees(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_employee_onboarding_template FOREIGN KEY (template_id) REFERENCES onboarding_checklist_templates(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_employee_onboarding_created_by FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    UNIQUE KEY uq_employee_onboarding_employee (employee_id),
    KEY idx_employee_onboarding_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employee_onboarding_tasks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_onboarding_id BIGINT UNSIGNED NOT NULL,
    template_task_id BIGINT UNSIGNED NULL,
    task_name VARCHAR(150) NOT NULL,
    description VARCHAR(255) NULL,
    assigned_to_user_id BIGINT UNSIGNED NULL,
    status ENUM('pending','in_progress','completed','waived') NOT NULL DEFAULT 'pending',
    due_date DATE NULL,
    completed_at DATETIME NULL,
    completed_by BIGINT UNSIGNED NULL,
    remarks VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_employee_onboarding_tasks_onboarding FOREIGN KEY (employee_onboarding_id) REFERENCES employee_onboarding(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_employee_onboarding_tasks_template_task FOREIGN KEY (template_task_id) REFERENCES onboarding_template_tasks(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_employee_onboarding_tasks_assigned_to FOREIGN KEY (assigned_to_user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_employee_onboarding_tasks_completed_by FOREIGN KEY (completed_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    KEY idx_employee_onboarding_tasks_onboarding_id (employee_onboarding_id),
    KEY idx_employee_onboarding_tasks_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS policy_acknowledgements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL,
    document_title VARCHAR(150) NOT NULL,
    document_version VARCHAR(50) NOT NULL,
    acknowledged_at DATETIME NOT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_policy_acknowledgements_employee FOREIGN KEY (employee_id) REFERENCES employees(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    KEY idx_policy_acknowledgements_employee_id (employee_id),
    KEY idx_policy_acknowledgements_acknowledged_at (acknowledged_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS offboarding_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL,
    record_type ENUM('resignation','termination','retirement','contract_end','absconding','other') NOT NULL,
    notice_date DATE NULL,
    exit_date DATE NOT NULL,
    last_working_date DATE NULL,
    reason VARCHAR(255) NULL,
    remarks TEXT NULL,
    status ENUM('draft','pending','in_progress','completed','cancelled') NOT NULL DEFAULT 'draft',
    clearance_status ENUM('pending','partial','cleared') NOT NULL DEFAULT 'pending',
    initiated_by BIGINT UNSIGNED NULL,
    approved_by BIGINT UNSIGNED NULL,
    completed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_offboarding_records_employee FOREIGN KEY (employee_id) REFERENCES employees(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_offboarding_records_initiated_by FOREIGN KEY (initiated_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_offboarding_records_approved_by FOREIGN KEY (approved_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    KEY idx_offboarding_records_employee_id (employee_id),
    KEY idx_offboarding_records_status (status),
    KEY idx_offboarding_records_exit_date (exit_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS offboarding_tasks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    offboarding_record_id BIGINT UNSIGNED NOT NULL,
    task_name VARCHAR(150) NOT NULL,
    department_id BIGINT UNSIGNED NULL,
    assigned_to_user_id BIGINT UNSIGNED NULL,
    status ENUM('pending','in_progress','completed','waived') NOT NULL DEFAULT 'pending',
    due_date DATE NULL,
    completed_at DATETIME NULL,
    remarks VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_offboarding_tasks_record FOREIGN KEY (offboarding_record_id) REFERENCES offboarding_records(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_offboarding_tasks_department FOREIGN KEY (department_id) REFERENCES departments(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_offboarding_tasks_assigned_to FOREIGN KEY (assigned_to_user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    KEY idx_offboarding_tasks_record_id (offboarding_record_id),
    KEY idx_offboarding_tasks_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS asset_return_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    offboarding_record_id BIGINT UNSIGNED NOT NULL,
    asset_name VARCHAR(150) NOT NULL,
    asset_code VARCHAR(50) NULL,
    quantity INT NOT NULL DEFAULT 1,
    return_status ENUM('pending','returned','missing','waived') NOT NULL DEFAULT 'pending',
    remarks VARCHAR(255) NULL,
    checked_by BIGINT UNSIGNED NULL,
    checked_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_asset_return_items_record FOREIGN KEY (offboarding_record_id) REFERENCES offboarding_records(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_asset_return_items_checked_by FOREIGN KEY (checked_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    KEY idx_asset_return_items_record_id (offboarding_record_id),
    KEY idx_asset_return_items_return_status (return_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shifts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    code VARCHAR(50) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    late_grace_minutes INT NOT NULL DEFAULT 0,
    half_day_minutes INT NULL,
    is_night_shift TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_shifts_company FOREIGN KEY (company_id) REFERENCES companies(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    UNIQUE KEY uq_shifts_company_code (company_id, code),
    KEY idx_shifts_company_id (company_id),
    KEY idx_shifts_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS work_schedules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    code VARCHAR(50) NOT NULL,
    weekly_hours DECIMAL(6,2) NOT NULL DEFAULT 40.00,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_work_schedules_company FOREIGN KEY (company_id) REFERENCES companies(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    UNIQUE KEY uq_work_schedules_company_code (company_id, code),
    KEY idx_work_schedules_company_id (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS work_schedule_days (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    work_schedule_id BIGINT UNSIGNED NOT NULL,
    day_of_week TINYINT UNSIGNED NOT NULL,
    shift_id BIGINT UNSIGNED NULL,
    is_working_day TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_work_schedule_days_schedule FOREIGN KEY (work_schedule_id) REFERENCES work_schedules(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_work_schedule_days_shift FOREIGN KEY (shift_id) REFERENCES shifts(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    UNIQUE KEY uq_work_schedule_day (work_schedule_id, day_of_week),
    KEY idx_work_schedule_days_shift_id (shift_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employee_schedule_assignments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL,
    work_schedule_id BIGINT UNSIGNED NOT NULL,
    shift_id BIGINT UNSIGNED NULL,
    effective_from DATE NOT NULL,
    effective_to DATE NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_employee_schedule_assignments_employee FOREIGN KEY (employee_id) REFERENCES employees(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_employee_schedule_assignments_schedule FOREIGN KEY (work_schedule_id) REFERENCES work_schedules(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_employee_schedule_assignments_shift FOREIGN KEY (shift_id) REFERENCES shifts(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    KEY idx_employee_schedule_assignments_employee_id (employee_id),
    KEY idx_employee_schedule_assignments_dates (effective_from, effective_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attendance_statuses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(50) NOT NULL UNIQUE,
    color_class VARCHAR(50) NULL,
    counts_as_present TINYINT(1) NOT NULL DEFAULT 0,
    counts_as_absent TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attendance_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL,
    attendance_date DATE NOT NULL,
    shift_id BIGINT UNSIGNED NULL,
    status_id BIGINT UNSIGNED NULL,
    clock_in_time DATETIME NULL,
    clock_out_time DATETIME NULL,
    minutes_late INT NOT NULL DEFAULT 0,
    source ENUM('manual','device','import','system') NOT NULL DEFAULT 'manual',
    remarks VARCHAR(255) NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_attendance_records_employee FOREIGN KEY (employee_id) REFERENCES employees(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_attendance_records_shift FOREIGN KEY (shift_id) REFERENCES shifts(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_attendance_records_status FOREIGN KEY (status_id) REFERENCES attendance_statuses(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_attendance_records_created_by FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    UNIQUE KEY uq_attendance_employee_date (employee_id, attendance_date),
    KEY idx_attendance_records_status_id (status_id),
    KEY idx_attendance_records_date (attendance_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT NULL,
    value_type ENUM('string','integer','boolean','json','text') NOT NULL DEFAULT 'string',
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_settings_updated_by FOREIGN KEY (updated_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    UNIQUE KEY uq_settings_category_key (category_name, setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    module_name VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100) NOT NULL,
    entity_id BIGINT UNSIGNED NULL,
    action_name VARCHAR(100) NOT NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_logs_user FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    KEY idx_audit_logs_module_name (module_name),
    KEY idx_audit_logs_entity (entity_type, entity_id),
    KEY idx_audit_logs_action_name (action_name),
    KEY idx_audit_logs_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;