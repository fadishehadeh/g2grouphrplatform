-- ============================================================
-- Migration: Replace hr_admin with hr_only
-- Run this on your live database ONCE.
-- ============================================================
USE hr_system;

-- 1. Add the employee.delete permission if it doesn't exist
INSERT IGNORE INTO permissions (module_name, action_name, code, description)
VALUES ('employee', 'delete', 'employee.delete', 'Permanently delete employee records');

-- 2. Add the new hr_only role
INSERT IGNORE INTO roles (name, code, description, is_system)
VALUES ('HR Only', 'hr_only', 'Full HR operations with exclusive confidential document access', 1);

-- 3. Grant hr_only all the same permissions as hr_admin had, plus employee.delete
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r, permissions p
WHERE r.code = 'hr_only'
  AND p.code IN (
    'dashboard.view','employee.view_all','employee.create','employee.edit','employee.archive','employee.delete',
    'leave.view_self','leave.submit','leave.manage_types','documents.manage_all',
    'announcements.view','announcements.manage','reports.view_hr','settings.manage',
    'audit.view','onboarding.manage','offboarding.manage','notifications.view_self','structure.manage'
  );

-- 4. Give super_admin the new employee.delete permission
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r, permissions p
WHERE r.code = 'super_admin' AND p.code = 'employee.delete';

-- 5. Migrate all users with hr_admin role to hr_only
UPDATE users
SET role_id = (SELECT id FROM roles WHERE code = 'hr_only')
WHERE role_id = (SELECT id FROM roles WHERE code = 'hr_admin');

-- 6. Remove hr_admin role (safe now that no users reference it)
DELETE FROM role_permissions WHERE role_id = (SELECT id FROM roles WHERE code = 'hr_admin');
DELETE FROM roles WHERE code = 'hr_admin';

-- 7. Add hr_only visibility scope to employee_documents
ALTER TABLE employee_documents
    MODIFY COLUMN visibility_scope ENUM('employee','manager','hr','admin','hr_only') NOT NULL DEFAULT 'hr';

-- 8. Add meta_fields to onboarding template tasks (custom sub-fields per task)
ALTER TABLE onboarding_template_tasks
    ADD COLUMN meta_fields JSON NULL COMMENT 'JSON array of custom fields: [{label, key, type, required}]';

-- 9. Add meta_values to employee onboarding tasks (values for custom sub-fields)
ALTER TABLE employee_onboarding_tasks
    ADD COLUMN meta_values JSON NULL COMMENT 'JSON object of custom field values keyed by field key';
