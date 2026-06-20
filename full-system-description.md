# HR Management System

## Overview

This application is a role-based HR management platform for internal HR operations, employee self-service, manager approvals, document control, recruitment, onboarding, offboarding, reporting, and resilience/backup monitoring.

The platform currently includes the following major areas:

- Authentication and account access
- Employee master records
- Organization structure and reporting lines
- Leave management
- Employee documents
- Onboarding
- Offboarding
- Recruitment and careers portal
- Letters and announcements
- Reports
- Settings and attendance-related configuration
- Resilience and backup monitoring
- Internal API access

## User Roles

The app currently distinguishes at least these role experiences:

- `super_admin`
- `hr_only`
- `manager`
- `employee`

Access is controlled through roles, permissions, and middleware.

## Main Modules

### 1. Authentication

Routes:

- `/login`
- `/otp`
- `/forgot-password`
- `/reset-password/{token}`
- `/logout`

Features:

- Login
- OTP verification and resend
- Password reset flow
- Session-based access control

### 2. Dashboard

Route:

- `/dashboard`

Dashboard variants are rendered by role:

- Super admin dashboard
- HR admin dashboard
- Manager dashboard
- Employee dashboard

Dashboard data includes:

- Headcount
- Pending approvals
- Open onboarding records
- Expiring documents
- Team member count for managers
- Employee leave balance for self-service users
- Recent announcements
- Latest backup overview for super admins

### 3. Employee Management

Routes include:

- `/employees`
- `/employees/org-chart`
- `/employees/create`
- `/employees/import`
- `/employees/import/confirm`
- `/employees/import-template`
- `/employees/missing-items`
- `/employees/{id}`
- `/employees/{id}/history`
- `/employees/{id}/archive`
- `/employees/{id}/edit`
- `/employees/{id}/insurance`
- `/employees/{id}/delete`
- `/employees/{id}/send-access`

Features:

- Employee directory
- Employee profile view
- Employee create/edit
- Employee archive
- Employee delete with confirmation
- Org chart
- Employee history
- Insurance data updates
- Send access to employee
- Export employee data to Excel/PDF

Import features implemented:

- Excel employee import
- Optional `employee_code`
- Optional blank `work_email`
- Auto-generated employee code when blank
- Conservative existing-match detection
- Review/confirm flow for ambiguous imports
- Blank-preserve update behavior
- Import template download

Directory enhancements currently present:

- Search
- Collapsible advanced filters
- Filter by code, status, company, branch, department, team, job title, designation, manager, employment type, contract type, access account state, joining date exact/from/to
- Missing items audit page

### 4. Structure and Organization

Routes include:

- `/admin/structure`
- `/admin/companies`
- `/admin/branches`
- `/admin/departments`
- `/admin/teams`
- `/admin/job-titles`
- `/admin/designations`
- `/admin/reporting-lines`

Features:

- Company management
- Branch management
- Department management
- Team management
- Job title management
- Designation management
- Reporting line management

The employee import and employee records now support structure growth by allowing missing structure values to be added into the system workflow.

### 5. Leave Management

Routes include:

- `/leave/my`
- `/leave/balances`
- `/leave/requests`
- `/leave/request`
- `/leave/calendar`
- `/leave/approvals`
- `/admin/leave/create`
- `/admin/leave/employees/{id}`
- `/admin/leave/requests/{id}/edit`
- `/admin/leave/types`
- `/admin/leave/policies`
- `/admin/leave/holidays`
- `/admin/leave/weekends`
- `/admin/leave/balances/assign`
- `/admin/leave/balances/recalculate-annual`
- `/admin/leave/balances/import-annual-used`
- `/admin/leave/balances/import-annual-used/confirm`

Current leave features include:

- Employee self-service leave request submission
- Leave approvals
- Leave request listing and detail
- Leave calendar
- Leave balances view
- Leave types management
- Leave policy/rule management
- Holidays management
- Weekends management
- Leave export to Excel/PDF

Annual leave enhancements implemented:

- Annual leave treated as a special rule using `annual_leave`
- 2026 annual leave logic
- Pre-2026 joiners receive full 22 days for 2026
- 2026 joiners accrue 1.8333 days per eligible month
- Accrual starts from the first full month after joining
- 3-month annual leave request restriction from joining date
- Carry forward up to 5 days until March 31 of next year
- Annual carry-forward shown separately in calculations
- Annual leave used-days import
- Support for importing annual used days from:
  - `employee_code + used_days`
  - `Employee Name + Used Balance`

Approval routing currently simplified for new leave requests:

- Direct manager approves if valid manager account exists
- If no valid manager is assigned, request goes to HR admins
- No second HR step after manager approval for the simplified route

Admin leave management features:

- HR/admin can create leave directly for employees
- Admin leave is directly approved
- Admin leave uses from/to dates and current system day calculation
- Dedicated employee leave detail page
- Employee leave page shows:
  - employee summary
  - leave balances by type
  - annual balance detail
  - leave history
- Admin can edit approved leave records in place

Default leave type seeding currently supports:

- Annual Leave
- Sick Leave
- Emergency Leave
- Maternity Leave
- Unpaid Leave

### 6. Documents

Routes include:

- `/documents`
- `/documents/categories`
- `/documents/types`
- `/documents/expiring`
- `/documents/send-expiry-alerts`
- `/documents/{id}/download`
- `/documents/dl/{token}`
- `/documents/{id}/edit`
- `/employees/{id}/documents/upload`

Features:

- HR document directory
- Document categories
- Document types
- Admin upload
- Employee upload
- Expiring document monitoring
- Expiry alert sending
- Tokenized secure downloads
- Edit/update document metadata

### 7. Onboarding

Routes include:

- `/onboarding`
- `/onboarding/templates`
- `/onboarding/templates/{id}`
- `/onboarding/create/{employeeId}`
- `/onboarding/{id}`

Features:

- Onboarding record management
- Onboarding templates
- Template tasks
- Employee onboarding task tracking

### 8. Offboarding

Routes include:

- `/offboarding`
- `/offboarding/create/{employeeId}`
- `/offboarding/{id}`

Features:

- Offboarding record creation
- Task management
- Asset return management

### 9. Recruitment and Careers

Admin routes:

- `/admin/jobs`
- `/admin/jobs/categories`
- `/admin/jobs/applicants`
- `/admin/jobs/job-bank`

Public/candidate routes:

- `/careers`
- `/careers/jobs/{slug}`
- `/careers/register`
- `/careers/login`
- `/careers/dashboard`
- `/careers/my-applications`
- `/careers/apply/{jobId}`
- `/careers/job-bank`
- `/careers/profile`

Features:

- Job postings
- Job categories
- Applicant pipeline
- Job bank
- Candidate portal
- Candidate authentication and OTP
- Candidate profile builder
- CV and photo upload
- Application submission and withdrawal

### 10. Intake / Employee Registration

Routes include:

- `/employee-registration`
- `/employee-registration/success`
- `/employee-registration/review`
- `/employee-registration/review/{token}`

Features:

- Public/controlled employee registration intake
- HR review queue
- Approve/reject intake submissions
- Submitted document preview/download

### 11. Letters, Announcements, Notifications

Routes include:

- `/letters/my`
- `/letters/request`
- `/letters/admin`
- `/letters/templates`
- `/announcements`
- `/notifications`

Features:

- Letter request workflow
- Letter admin processing
- Letter templates
- PDF generation/download
- Announcements
- Announcement email sending
- User notifications

### 12. Reports

Routes include:

- `/reports`
- `/reports/headcount`
- `/reports/department`
- `/reports/leave-usage`
- `/reports/new-joiners`
- `/reports/exits`
- `/reports/documents`
- `/reports/audit`

Features:

- HR reporting dashboard
- Headcount reports
- Department reports
- Leave usage reports
- New joiners report
- Exits report
- Documents report
- Audit report
- Excel/PDF export on supported reports

### 13. Settings

Routes include:

- `/settings`
- `/settings/attendance`
- `/settings/attendance/records`
- `/settings/attendance/assignments`
- `/settings/shifts`
- `/settings/schedules`
- `/settings/attendance-statuses`

Features:

- General settings
- Attendance records
- Attendance assignments
- Shift setup
- Schedule setup
- Attendance status setup

### 14. Resilience / Backups

Routes include:

- `/admin/resilience`
- `/admin/resilience/run-now`
- `/admin/resilience/backups/{runId}/links`
- `/admin/resilience/backups/download/{token}`

Super-admin-only features:

- Backup status console
- Backup history
- Manual run-now action
- Daily backup execution support
- Secure expiring download links
- Database backup artifact tracking
- Uploads backup artifact tracking
- 30-day retention logic
- 7-day download link expiry
- Mail notification after backup runs

Backup scope currently includes:

- Database dump
- Upload storage areas used by the app

### 15. API and Tokens

Routes include:

- `/api/v1/employees`
- `/api/v1/employees/{id}`
- `/api/v1/leave/requests`
- `/api/v1/leave/balances`
- `/api/v1/leave/pending`
- `/profile/api-tokens`

Features:

- Personal API tokens
- Employee API endpoints
- Leave API endpoints

## UI / UX Enhancements Already Added

- Collapsible filters in employee pages
- Leave balances pagination
- Active-only leave balances view
- Clickable employee names to edit in employee directory
- Improved employee list action buttons
- Dedicated preview pages for visual concept work in `public/`

## Security / Access Control

The system currently uses:

- Role middleware
- Auth middleware
- Account status middleware
- CSRF validation
- Token-based secure downloads for sensitive files
- Super-admin-only resilience tools

## Notes

- This document describes the current implemented application scope based on the present codebase structure and recent completed updates.
- Some internal helper classes, background scripts, and low-level support services are intentionally not listed line by line here.
