## HR Management System Page-by-Page Build Plan

### 1. Public / Authentication Pages
- `/login` - secure login form, remember-me option, account status validation.
- `/forgot-password` - request reset link/token.
- `/reset-password` - token validation, password reset, force password policy.
- `/logout` - session destroy and audit log.

### 2. Shared Authenticated Layout
- App shell with sidebar, topbar, breadcrumb, flash alerts, notification bell, profile menu.
- Shared widgets: status badges, filter bars, search boxes, pagination, export button, modal confirmation.

### 3. Dashboard Pages
- `/dashboard` - role-routed dashboard loader.
- `/dashboard/super-admin` - headcount, user status, settings shortcuts, audit widgets.
- `/dashboard/hr-admin` - headcount, pending onboarding, offboarding, expiring docs, leave approvals.
- `/dashboard/manager` - direct reports, pending team requests, team leave calendar, quick approvals.
- `/dashboard/employee` - profile summary, leave balance, request status, announcements, onboarding tasks.

### 4. User / Role Management
- `/admin/users` - list users, filters by role/status, activate/suspend accounts.
- `/admin/users/create` - create account and assign role.
- `/admin/users/{id}/edit` - update account details and role.
- `/admin/roles` - role list and permission matrix.
- `/admin/roles/{id}/permissions` - permission assignment page.

### 5. Organization Structure Pages
- `/admin/companies` - company/entity management.
- `/admin/branches` - branch CRUD with filters.
- `/admin/departments` - department hierarchy management.
- `/admin/teams` - team CRUD mapped to departments.
- `/admin/job-titles` - job title master data.
- `/admin/designations` - designation master data.
- `/admin/reporting-lines` - reporting and approval hierarchy mapping.

### 6. Employee Management Pages
- `/employees` - searchable employee directory with pagination and status filters.
- `/employees/create` - multi-section add employee form.
- `/employees/{id}` - employee profile tabs: overview, work info, contacts, docs, leave, logs.
- `/employees/{id}/edit` - update employee record with role-based field restrictions.
- `/employees/{id}/archive` - archive confirmation workflow.
- `/employees/{id}/history` - change log and status timeline.

### 7. Employee Self-Service Pages
- `/my/profile` - self profile view and allowed-field edit form.
- `/my/documents` - upload/view personal documents.
- `/my/leave` - leave history, balances, and request tracking.
- `/my/notifications` - in-app notification center.
- `/my/settings` - password change and account preferences.

### 8. Manager Portal Pages
- `/manager/team` - direct reports list with summary cards.
- `/manager/team/{employeeId}` - restricted employee profile for reports only.
- `/manager/approvals` - pending leave approvals with approve/reject actions.
- `/manager/calendar` - team leave calendar and overlap warnings.
- `/manager/reports` - team-level summaries and exports.

### 9. Leave Management Pages
- `/leave/types` - leave type setup and policy flags.
- `/leave/policies` - leave policy list and target rules.
- `/leave/holidays` - holiday calendar management.
- `/leave/weekends` - weekend configuration.
- `/leave/balances` - HR leave balance overview and manual adjustments.
- `/leave/request/create` - employee leave request form with balance preview.
- `/leave/requests` - all leave requests list with role-aware filters.
- `/leave/requests/{id}` - request detail, approval trail, attachments, comments.
- `/leave/approvals` - HR/manager action queue.
- `/leave/calendar` - organization/team calendar view.

### 10. Documents Module Pages
- `/documents/categories` - document category management.
- `/documents` - HR document center with expiry filters.
- `/documents/{id}` - preview metadata, version history, download action.
- `/employees/{id}/documents/upload` - employee-specific upload screen.
- `/documents/expiring` - expiring and expired document report.

### 11. Onboarding Module Pages
- `/onboarding/templates` - checklist template management.
- `/onboarding` - onboarding records list with progress indicators.
- `/onboarding/create/{employeeId}` - start onboarding from template.
- `/onboarding/{id}` - onboarding task board and completion actions.

### 12. Offboarding Module Pages
- `/offboarding` - offboarding record list.
- `/offboarding/create/{employeeId}` - resignation/termination intake form.
- `/offboarding/{id}` - checklist, clearance state, asset return tracker.

### 13. Announcements / Notifications Pages
- `/announcements` - role-targeted announcement management.
- `/announcements/create` - publish announcement with target selectors.
- `/notifications` - notification list and mark-as-read actions.

### 14. Reports and Audit Pages
- `/reports/headcount` - headcount and status distribution.
- `/reports/department` - employee distribution by department.
- `/reports/leave-usage` - leave summary by date, department, employee.
- `/reports/new-joiners` - onboarding/new hire report.
- `/reports/exits` - resignations and terminations report.
- `/reports/documents` - expiring document report.
- `/audit-logs` - auditable action trail with filters.

### 15. Settings and Attendance Foundation Pages
- `/settings` - system settings, SMTP placeholders, leave defaults, company preferences.
- `/attendance` - placeholder dashboard for future expansion.
- `/attendance/shifts` - shift CRUD.
- `/attendance/schedules` - work schedules and assigned days.
- `/attendance/statuses` - attendance status setup.

### 16. Suggested Build Order by Screen
1. Login, logout, forgot/reset password.
2. Shared app layout and sidebar.
3. Roles, permissions, users.
4. Companies, branches, departments, teams, job titles.
5. Employee list, create, edit, profile tabs.
6. Employee and manager dashboards.
7. Leave setup, request flow, approvals, balances, calendar.
8. Documents and expiry monitoring.
9. Onboarding and offboarding.
10. Announcements, notifications, reports, audit logs, settings.