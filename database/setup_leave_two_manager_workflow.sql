-- Setup Global Leave Approval Workflow with Two-Manager Approval
-- Run this migration to set up the two-level manager approval system for leave requests

-- 1. Verify department_id column exists in approval_workflows (if not, the code will work with NULL only)
-- ALTER TABLE approval_workflows ADD COLUMN department_id bigint(20) unsigned DEFAULT NULL AFTER company_id;

-- 2. Create Global Leave Approval Workflow (applies to all companies/departments if no override exists)
INSERT INTO approval_workflows (module_code, name, company_id, description, is_active, created_at, updated_at)
VALUES ('leave', 'Default Two-Manager Approval', NULL, 'Global workflow: Level 1 Manager → Level 2 Manager (if exists)', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE is_active = 1, updated_at = NOW();

-- Note: If you get a "DUPLICATE" error, it means a global leave workflow already exists.
-- Get the workflow ID from the insert above or select it:
-- SELECT @workflow_id := id FROM approval_workflows WHERE module_code = 'leave' AND company_id IS NULL AND name LIKE '%Two-Manager%' LIMIT 1;

-- 3. Get the created workflow ID (use this for the steps below)
SET @workflow_id = LAST_INSERT_ID();

-- 4. Verify the workflow was created
-- SELECT @workflow_id;

-- 5. Delete existing steps if rerunning this script
DELETE FROM approval_workflow_steps WHERE workflow_id = @workflow_id;

-- 6. Create Step 1: Level 1 Manager
INSERT INTO approval_workflow_steps (workflow_id, step_order, approver_type, is_required, created_at)
VALUES (@workflow_id, 1, 'manager', 1, NOW());

-- 7. Create Step 2: Level 2 Manager (if exists)
INSERT INTO approval_workflow_steps (workflow_id, step_order, approver_type, is_required, created_at)
VALUES (@workflow_id, 2, 'manager', 1, NOW());

-- 8. Verify the workflow steps were created
-- SELECT * FROM approval_workflow_steps WHERE workflow_id = @workflow_id ORDER BY step_order;

-- 9. Ensure requires_hr_approval is set to 0 for all leave types (optional, but recommended)
-- UPDATE leave_types SET requires_hr_approval = 0 WHERE status = 'active';

-- Done! The two-manager approval workflow is now set up globally.
-- Employees with 2 managers (in employee_reporting_lines) will have requests routed through both managers.
-- Employees with 1 manager will only need approval from that manager.
-- Employees with no managers will go straight to pending_manager status (may need HR action).
