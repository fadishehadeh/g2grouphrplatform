USE hr_system;

-- Admin credentials: admin / admin@123
-- Password hash generated via password_hash('admin@123', PASSWORD_BCRYPT)

INSERT INTO roles (name, code, description, is_system) VALUES
('Super Admin', 'super_admin', 'Full system access', 1),
('HR Only', 'hr_only', 'Full HR operations with exclusive confidential document access', 1),
('Manager', 'manager', 'Line manager access', 1),
('Employee', 'employee', 'Self-service employee access', 1);

INSERT INTO permissions (module_name, action_name, code, description) VALUES
('dashboard', 'view', 'dashboard.view', 'View dashboard'),
('employee', 'view_all', 'employee.view_all', 'View all employees'),
('employee', 'view_self', 'employee.view_self', 'View own employee profile'),
('employee', 'create', 'employee.create', 'Create employee records'),
('employee', 'edit', 'employee.edit', 'Edit employee records'),
('employee', 'archive', 'employee.archive', 'Archive employee records'),
('employee', 'delete', 'employee.delete', 'Permanently delete employee records'),
('leave', 'view_self', 'leave.view_self', 'View own leave requests'),
('leave', 'submit', 'leave.submit', 'Submit leave requests'),
('leave', 'approve_team', 'leave.approve_team', 'Approve team leave requests'),
('leave', 'manage_types', 'leave.manage_types', 'Manage leave types and rules'),
('documents', 'upload_self', 'documents.upload_self', 'Upload own documents'),
('documents', 'view_self', 'documents.view_self', 'View own documents'),
('documents', 'manage_all', 'documents.manage_all', 'Manage all employee documents'),
('announcements', 'view', 'announcements.view', 'View announcements'),
('announcements', 'manage', 'announcements.manage', 'Manage announcements'),
('reports', 'view_team', 'reports.view_team', 'View team reports'),
('reports', 'view_hr', 'reports.view_hr', 'View HR reports'),
('settings', 'manage', 'settings.manage', 'Manage system settings'),
('audit', 'view', 'audit.view', 'View audit logs'),
('onboarding', 'manage', 'onboarding.manage', 'Manage onboarding'),
('offboarding', 'manage', 'offboarding.manage', 'Manage offboarding'),
('notifications', 'view_self', 'notifications.view_self', 'View own notifications'),
('structure', 'manage', 'structure.manage', 'Manage branches, departments, teams and job titles');

INSERT INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions;

INSERT INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions WHERE code IN (
    'dashboard.view','employee.view_all','employee.create','employee.edit','employee.archive','employee.delete',
    'leave.view_self','leave.submit','leave.manage_types','documents.manage_all',
    'announcements.view','announcements.manage','reports.view_hr','settings.manage',
    'audit.view','onboarding.manage','offboarding.manage','notifications.view_self','structure.manage'
);

INSERT INTO role_permissions (role_id, permission_id)
SELECT 3, id FROM permissions WHERE code IN (
    'dashboard.view','employee.view_self','leave.view_self','leave.submit','leave.approve_team',
    'documents.upload_self','documents.view_self','announcements.view','reports.view_team','notifications.view_self'
);

INSERT INTO role_permissions (role_id, permission_id)
SELECT 4, id FROM permissions WHERE code IN (
    'dashboard.view','employee.view_self','leave.view_self','leave.submit','documents.upload_self',
    'documents.view_self','announcements.view','notifications.view_self'
);

INSERT INTO users (role_id, username, email, password_hash, first_name, last_name, status, must_change_password) VALUES
(1, 'admin', 'admin@system.local', '$2y$10$d7bHKbB/TWIviY9gmnQTfO3568FWb4zh4amMru0D/cFZWp5f8AKoy', 'System', 'Admin', 'active', 0);