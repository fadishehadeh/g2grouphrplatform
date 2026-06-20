-- Add replacement_employee_id column to leave_requests table
-- This allows employees to assign a replacement when submitting leave requests

ALTER TABLE leave_requests
ADD COLUMN replacement_employee_id BIGINT UNSIGNED NULL AFTER employee_id;

-- Add foreign key constraint
ALTER TABLE leave_requests
ADD CONSTRAINT fk_leave_requests_replacement_employee
FOREIGN KEY (replacement_employee_id) REFERENCES employees(id) ON DELETE SET NULL;
