# Admin Manual

## Purpose

This manual explains how admins and HR users should operate the main parts of the HR Management System.

For server installation planning on a new cPanel environment with empty data, see [new-server-install-plan.md](new-server-install-plan.md).

## 1. Login and Access

1. Open the app URL.
2. Sign in with your username/email and password.
3. Complete OTP verification if requested.
4. Use the left sidebar to move between modules.

Main admin areas usually appear under:

- `People`
- `Access & Org`
- `Leave`
- `Documents`
- `Recruitment`
- `Communications`
- `Reports`
- `Settings`

Super admins also see:

- `Resilience`

## 2. Employee Directory

Open:

- `People > Employees`

What you can do:

- Search employees
- Open employee profiles
- Edit employees
- Create employees
- Import employees
- Export employee data
- Review missing items

Filtering:

- Use the main search box for quick search
- Use `Show Filters` for advanced filters
- The filters panel opens automatically when filters are active

Useful filters include:

- Employee code
- Status
- Company
- Branch
- Department
- Team
- Job title
- Designation
- Manager
- Employment type
- Contract type
- Access account state
- Joining date

## 3. Employee Import

Open:

- `People > Employees > Import`

Supported behavior:

- `employee_code` can be blank
- `work_email` can be blank
- Blank optional fields are allowed
- New employee codes are auto-generated when missing

Import flow:

1. Upload the Excel file
2. System analyzes rows
3. Review any potential matches/conflicts
4. Confirm create/update/skip actions

Important rules:

- Existing matches by employee code or work email are reviewed
- Same name + same company can trigger review
- Blank fields on updates do not erase existing data

## 4. Employee Profile

From the directory, open an employee profile.

Available actions may include:

- Edit
- Send Access
- Archive
- Delete
- View documents
- View onboarding/offboarding
- View history

Delete notes:

- Deletion requires exact confirmation input
- Use archive when you want to retain records without full deletion

## 5. Missing Items Audit

Open:

- `People > Missing Items Audit`

Use this page to identify:

- Missing employee master fields
- Missing employee documents

This is useful before audits, onboarding completion, or data cleanup.

## 6. Structure Management

Open:

- `Access & Org`

Use these pages to manage:

- Companies
- Branches
- Departments
- Teams
- Job titles
- Designations
- Reporting lines
- User access
- Roles and permissions

Best practice:

- Set up structure records before bulk employee imports when possible
- Keep reporting lines accurate so manager approvals route correctly

## 7. Leave Management

Open:

- `Leave > Leave Management`

Main areas:

- My Leave
- Balances
- Requests
- Calendar
- Request Leave
- Approvals
- Leave Types
- Policies
- Holidays
- Weekends

### 7.1 Leave Balances

Use the balances page to:

- Review employee leave balances
- Filter by year
- Recalculate annual leave
- Assign non-annual balances
- Import annual used days

Important:

- Leave balances page shows active employees only
- Annual leave uses special yearly rules

### 7.2 Annual Leave Rules

Current annual leave behavior:

- Employees who joined before 2026 get full 22 days for 2026
- Employees who joined during 2026 accrue 1.8333 days per eligible month
- Accrual starts from the first full month after joining
- Annual leave requests are blocked during the first 3 months after joining
- Up to 5 unused days may carry forward until March 31 of the next year

### 7.3 Import Annual Used Days

Supported formats:

- `employee_code` + `used_days`
- `Employee Name` + `Used Balance`

Use this for corrective imports where the used total should replace the stored annual used amount for that year.

### 7.4 Add Leave for Employee

Admins/HR can create leave directly for employees.

Use this when:

- HR is recording official leave after the fact
- Employee did not submit through self-service
- A correction should be recorded as an actual leave record, not just a balance adjustment

Fields include:

- Employee
- Leave type
- From date
- To date
- Start session
- End session
- Reason
- Admin note
- Attachment where required

Behavior:

- Admin-created leave is directly approved
- Days are calculated automatically from dates and sessions

### 7.5 Employee Leave Detail Page

From leave balances, open a specific employee.

This page shows:

- Employee summary
- Leave balances by leave type
- Annual leave detail
- Full leave history

Admins can:

- Add a leave record
- Edit an approved leave record

### 7.6 Leave Approvals

For new employee-submitted leave requests:

- If the employee has a valid manager account, the manager approves
- If no valid manager exists, HR admins approve

## 8. Documents

Open:

- `Documents`

Use this area to:

- Upload documents
- Manage categories and types
- Track expiry
- Send expiry alerts
- Download securely

Common use cases:

- Passport/residency tracking
- Contract and HR file storage
- Compliance document monitoring

## 9. Onboarding

Open:

- `People > Onboarding`

Use for:

- Creating onboarding records
- Managing onboarding templates
- Tracking onboarding tasks for new joiners

## 10. Offboarding

Open:

- `People > Offboarding`

Use for:

- Creating offboarding files
- Tracking return tasks
- Tracking asset handover and clearance

## 11. Recruitment and Careers

Open:

- `Recruitment > Jobs & Careers`

Use for:

- Creating jobs
- Managing job categories
- Reviewing applicants
- Managing the job bank

Candidate-side portal supports:

- Registration
- Login
- Profile creation
- CV upload
- Job applications

## 12. Letters

Open:

- `Communications > Letters`

Use for:

- Employee letter requests
- HR/admin letter processing
- Letter template management
- Viewing and downloading generated letters

## 13. Announcements and Notifications

Open:

- `Communications > Announcements`
- `Communications > Notifications`

Use for:

- Publishing internal announcements
- Sending announcement emails
- Reviewing system/user notifications

## 14. Reports

Open:

- `Reports`

Available reporting areas include:

- Headcount
- Department
- Leave usage
- New joiners
- Exits
- Documents
- Audit

Some reports support:

- Excel export
- PDF export

## 15. Settings

Open:

- `Settings`

Use for:

- General settings
- Attendance setup
- Attendance records
- Attendance assignments
- Shifts
- Schedules
- Attendance statuses

## 16. Resilience Console

Super admin only.

Open:

- `Access & Org > Resilience`

Use for:

- Viewing recent backup runs
- Checking database backup status
- Checking uploads backup status
- Refreshing secure download links
- Running a backup immediately

Daily backup emails include:

- Backup run status
- Artifact sizes
- Secure download links

## 17. Recommended Admin Workflow

Suggested order for clean operations:

1. Set up companies and structure
2. Import or create employees
3. Check missing items audit
4. Configure leave types/policies
5. Recalculate annual leave when needed
6. Review approvals and expiring documents regularly
7. Monitor reports and resilience console

## 18. Cautions

- Use employee delete carefully
- Prefer actual admin leave records over manual balance-only fixes
- Keep managers assigned correctly so approvals route properly
- Review import matches instead of forcing same-name updates
