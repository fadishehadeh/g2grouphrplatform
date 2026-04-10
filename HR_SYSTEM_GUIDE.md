# G2 Group HR Platform — Complete Guide

> A full-featured Human Resources Management System with a public-facing Careers Portal. Built in PHP with role-based access control and a separate recruitment database.

---

## Table of Contents

1. [System Overview](#system-overview)
2. [User Roles & Access Levels](#user-roles--access-levels)
3. [HR Platform Walkthrough](#hr-platform-walkthrough)
   - [Authentication](#authentication)
   - [Dashboard](#dashboard)
   - [Employee Management](#employee-management)
   - [Leave Management](#leave-management)
   - [Documents & Compliance](#documents--compliance)
   - [Letters & Certificates](#letters--certificates)
   - [Announcements & Notifications](#announcements--notifications)
   - [Onboarding Workflow](#onboarding-workflow)
   - [Offboarding Workflow](#offboarding-workflow)
   - [Organizational Structure](#organizational-structure)
   - [Reports & Analytics](#reports--analytics)
   - [Attendance & Scheduling](#attendance--scheduling)
   - [Admin Panel](#admin-panel)
   - [Profile & Password](#profile--password)
4. [Careers Portal Walkthrough](#careers-portal-walkthrough)
   - [Public Job Board](#public-job-board)
   - [Job Seeker Registration & Login](#job-seeker-registration--login)
   - [Job Seeker Dashboard](#job-seeker-dashboard)
   - [Building Your Profile](#building-your-profile)
   - [Applying for Jobs](#applying-for-jobs)
   - [Tracking Applications](#tracking-applications)
5. [HR Management of Jobs & Recruitment](#hr-management-of-jobs--recruitment)
6. [Permissions Reference](#permissions-reference)

---

## System Overview

The G2 Group HR Platform consists of two interconnected applications:

| Application | URL | Audience |
|---|---|---|
| HR Management System | `/public/` | Internal staff (employees, managers, HR, admins) |
| Careers Portal | `/public/careers` | External job seekers (public) |

The two apps share a **Jobs** data bridge — HR creates job postings in the main system, which immediately appear on the public careers portal. Applications submitted by job seekers flow back into the HR system for review.

**Technology stack:** PHP (custom MVC), MySQL (two separate databases: `hr_system` and `hr_careers`), Bootstrap 5, DataTables, PhpSpreadsheet (Excel), TCPDF (PDF).

---

## User Roles & Access Levels

The system uses **Role-Based Access Control (RBAC)** with granular per-permission assignment.

### Super Admin
Full, unrestricted access to every feature and setting in the system including user management, role/permission assignment, and all HR operations.

### HR Admin
Full HR operations access including all employee data, leave management, document oversight, job posting, recruitment, onboarding/offboarding workflows, announcements, reports, and system configuration. Cannot manage user accounts or roles.

### Manager
Team-scoped access. Can approve or reject leave requests for their team, view team-level reports, and access their own employee record and documents.

### Employee
Self-service access. Can view their own profile, submit leave requests, upload personal documents, request letters/certificates, and read announcements.

### Job Seeker *(Careers Portal only)*
External users with a completely separate login. Can browse jobs, build a profile, apply to postings, and track application status. Has no access to the HR system.

---

## HR Platform Walkthrough

### Authentication

**Login** — Navigate to the system URL. Enter your username or email address and password. Active accounts proceed directly to the role-appropriate dashboard.

**Forgot Password** — Click "Forgot Password" on the login page, enter your email address, and a secure reset link is sent. The link is time-limited and single-use.

**Account Status** — Accounts flagged as inactive by an admin cannot log in. Contact your HR administrator if access is denied.

---

### Dashboard

Each role sees a tailored dashboard upon login.

**Employee Dashboard**
- Leave balance summary (remaining days per leave type)
- Upcoming holidays
- Recent announcements
- Quick links to submit leave or request a letter

**Manager Dashboard**
- Pending leave approvals from team members
- Team headcount overview
- Recent announcements
- Quick access to team reports

**HR Admin Dashboard**
- Company-wide headcount
- Pending leave requests requiring action
- Documents expiring soon
- Recent announcements and activity summary
- Quick access to all HR modules

**Super Admin Dashboard**
- All HR Admin widgets, plus
- Active users count
- System health indicators
- Quick link to Admin Panel

---

### Employee Management

*Available to: HR Admin, Super Admin*

**Employee List** (`Employees` in sidebar)
The main employee directory displays all active staff with their name, ID, department, job title, and status. Use the search bar or column filters to narrow results. Archived employees are hidden by default.

**Creating an Employee**
Click `+ New Employee`. Fill in:
- Personal details (name, date of birth, gender, nationality, second nationality)
- Contact information
- Employment details (department, team, job title, designation, branch, hire date, employment type, status)
- Insurance details (optional)
- Reporting manager

After saving, use the `Send Credentials` action to email the new employee their username and a temporary password.

**Editing an Employee**
Click any employee name to open their profile, then click `Edit`. All fields can be updated. Every change is automatically logged in the employee history.

**Employee Profile View**
The full profile page shows all employee data organised into tabs: personal info, employment info, emergency contacts, documents, notes, and change history.

**Archiving an Employee**
Use the `Archive` action on an employee. Archived employees are removed from active lists but retained in the database for historical reporting.

**Bulk Import**
Go to `Employees → Import`. Download the Excel template, fill it in following the guide displayed on screen, then upload the file. The system validates each row and reports any errors before importing.

**Export**
Use the `Export Excel` or `Export PDF` buttons on the employee list to download the current filtered view.

---

### Leave Management

**For Employees**

*My Leave Requests*
View all your submitted leave requests with their current status (pending, approved, rejected, cancelled).

*Submit Leave Request*
Click `+ New Request`. Select:
- Leave type (annual, sick, emergency, etc.)
- Start and end date
- Reason
- Attach supporting documents if required

The system automatically calculates the number of working days, respecting configured weekends and public holidays.

*My Balances*
View your remaining entitlement for each leave type for the current year.

*Leave Calendar*
A monthly calendar view showing approved leave across the team (visibility depends on role).

---

**For Managers**

*Leave Approvals*
Pending requests from your team appear in the Approvals queue. Click any request to review the details, then choose `Approve` or `Reject` (with a mandatory reason for rejection).

*Team Requests*
See all historical requests from your team in one filtered view.

---

**For HR Admin / Super Admin**

*All Requests*
Full company-wide view of every leave request with filters by employee, department, leave type, status, and date range. Export to Excel or PDF.

*Leave Types*
Create and configure leave types:
- Name and description
- Days entitlement per year
- Whether carry-forward is allowed (and the cap)
- Whether it requires documentation
- Whether it accrues monthly

*Leave Policies*
Define rules per employee group — e.g., probationary employees have a capped entitlement. Attach policy rules with specific conditions.

*Holidays*
Manage the company's public holiday calendar by year. Add recurring or one-off dates.

*Weekend Settings*
Configure which days of the week are non-working days for leave calculation purposes.

*Assign Balances*
Manually adjust an employee's leave balance for any leave type and year (useful for corrections or special grants).

---

### Documents & Compliance

*Available to: All roles (with different scopes)*

**Employees** can upload personal documents (IDs, certificates, contracts) and view their own document history.

**HR Admins** can view all employee documents, manage document categories, and monitor expiry dates.

**Document Upload**
Select the employee (if HR) or go to your own profile, then click `Upload Document`. Choose:
- Document category
- Expiry date (if applicable)
- File (PDF, image, or Office document)

**Document Categories**
HR can create custom categories (Passport, Visa, Work Permit, Contract, Certificate, etc.) to organise the document library.

**Expiring Documents**
The `Expiring Documents` report shows all documents approaching their expiry date within a configurable window. Used to trigger renewals before documents lapse.

**Document Alerts**
The system can send automated email alerts to HR when documents are about to expire.

---

### Letters & Certificates

**Employees** can request official letters directly from the system.

*Request a Letter*
Go to `Letters → Request Letter`. Choose the letter type:
- Relief Letter
- Joining Letter
- Salary Certificate
- Employment Certificate
- Experience Letter
- Custom request

Add any notes for the HR team, then submit. You'll receive a notification when the letter is ready.

*My Letters*
View all your requests and download approved letters as PDF.

---

**HR Admin** manages incoming requests and generates letters from templates.

*All Requests*
A queue of all pending and historical letter requests. Review and click `Generate` to produce a PDF from the system template, or `Reject` with a reason.

*Generated Letters*
Download or re-download any previously generated letter.

---

### Announcements & Notifications

**Announcements**
Company-wide or targeted communications posted by HR.

- All authenticated users can read announcements
- HR Admins can create, edit, and attach files to announcements
- Announcements can be sent via email at publication time
- Each announcement is marked as read individually per user

**Notifications**
In-system alerts triggered by actions (leave approval, document expiry warning, letter ready, etc.).

- The notification bell in the top bar shows the unread count
- Click a notification to navigate directly to the relevant record
- Mark individual or all notifications as read

---

### Onboarding Workflow

*Available to: HR Admin, Super Admin*

Structured task-based checklists to guide new employees through their first days.

**Templates**
Create reusable onboarding templates (e.g., "Software Engineer Onboarding", "Sales Onboarding"). Each template contains an ordered list of tasks with descriptions, due-day offsets, and responsible parties.

**Starting an Onboarding Process**
From an employee's profile or the Onboarding list, click `Create Onboarding`. Select a template and start date. The system generates a personalised task list with calculated due dates.

**Tracking Progress**
The onboarding detail view shows all tasks in a checklist format. Mark tasks complete as they are done. Progress is visible at a glance with a completion percentage.

---

### Offboarding Workflow

*Available to: HR Admin, Super Admin*

Mirrors the onboarding workflow but for departing employees.

**Starting an Offboarding Process**
Initiate from the employee profile when they have submitted or been given a notice. Set the last working day.

**Task Tracking**
Manage exit tasks such as access revocation, badge return, and final payslip. Each task can be ticked off as complete.

**Asset Returns**
Log every company asset the employee must return (laptop, phone, access card, etc.) and mark each as received.

---

### Organizational Structure

*Available to: HR Admin, Super Admin*

Manage the hierarchy of the business.

**Companies** — Top-level legal entities. Add name, registration details, and logo.

**Branches** — Physical office locations under a company.

**Departments** — Functional divisions (Engineering, Finance, HR, etc.) assigned to a branch or company.

**Teams** — Sub-groups within departments.

**Job Titles** — The list of official position names used when creating employees.

**Designations** — Seniority or grade labels (Junior, Senior, Lead, Director, etc.).

**Reporting Lines** — Define manager-to-employee reporting relationships used by the leave approval workflow.

---

### Reports & Analytics

*Available to: HR Admin, Super Admin (team-level for Manager)*

All reports support filtering and can be exported to Excel or PDF.

| Report | Description |
|---|---|
| Headcount | Total active employees, filterable by department, branch, employment type, gender |
| Department Breakdown | Employee count and composition per department |
| New Joiners | Employees hired within a selected date range |
| Exits | Archived employees with exit dates and reasons |
| Leave Usage | Leave days taken per employee or department, by leave type and period |
| Expiring Documents | Documents expiring within a configurable window |
| Audit Logs | Full activity trail — who did what and when (Super Admin only) |

---

### Attendance & Scheduling

*Available to: HR Admin, Super Admin*

**Shifts**
Define named work shifts with start time, end time, and break duration. Example: `Morning Shift 08:00–17:00`.

**Work Schedules**
Group shifts into weekly schedules, specifying which days each shift applies. Example: `Standard 5-Day Week` with the morning shift Monday through Friday.

**Assigning Employees to Schedules**
From `Settings → Attendance → Assignments`, attach employees to a schedule with an effective date. The schedule drives working-day calculations for leave.

**Recording Attendance**
Log daily attendance for employees, applying a status (Present, Absent, Late, Half Day, etc.) using the configured status types. Attendance records can be reviewed and corrected.

**Attendance Statuses**
Customise the list of valid attendance codes and their display labels.

---

### Admin Panel

*Available to: Super Admin only*

**User Management**
- View all system user accounts
- Create a new user account (separate from creating an employee record)
- Edit user details (name, email, role, status)
- Activate or deactivate accounts
- Send welcome emails with credentials

**Role Management**
- View all system roles
- Create custom roles with any combination of permissions
- Edit the permissions assigned to any existing role
- View a matrix of all roles and their assigned permissions

Roles are assigned to users; permissions are assigned to roles. Changing a role's permissions immediately affects all users holding that role.

---

### Profile & Password

Every logged-in user can access their own profile page from the top-right user menu.

- View your personal and employment details
- Change your password (requires current password confirmation)

---

## Careers Portal Walkthrough

The Careers Portal is a fully independent public-facing application at `/public/careers`. It runs on a separate database and authentication system.

---

### Public Job Board

No login required.

**Jobs Listing Page** (`/careers/jobs`)
Browse all open job postings. Each listing card shows:
- Job title
- Department / Category
- Location (city, country)
- Employment type (Full-time, Part-time, Contract, Internship, Freelance)
- Experience level
- Application deadline
- Featured badge (if marked by HR)

**Search & Filter**
- Keyword search (matches title and description)
- Filter by job category
- Filter by employment type
- Filter by experience level

**Job Detail Page**
Click any job to see the full posting:
- Full description
- Key responsibilities
- Requirements and qualifications
- Required skills
- Education requirements
- Salary range (if HR chose to display it)
- Number of open positions
- Benefits

An `Apply Now` button is displayed. Clicking it redirects to login/register if not authenticated, or directly to the application form if already logged in.

---

### Job Seeker Registration & Login

**Register**
Click `Register` or `Apply Now` on any job. Fill in:
- Full name
- Email address
- Username
- Password and confirmation

After submitting, an OTP (one-time password) is sent to your email for verification.

**OTP Verification**
Enter the 6-digit code sent to your email. The code expires after a short window. Use `Resend OTP` if it did not arrive.

**Login**
Use your email or username and password. If OTP is enabled on your account, you will be prompted for a code after entering credentials.

**Forgot Password**
The forgot password flow sends a reset link to your registered email address.

---

### Job Seeker Dashboard

After logging in you land on your personal dashboard showing:

- Total applications submitted
- Applications currently under review
- Applications shortlisted
- Applications rejected
- Quick links to browse jobs, view applications, and complete your profile
- A prompt to complete your profile (shown if profile is incomplete)

---

### Building Your Profile

A complete profile significantly increases your visibility to recruiters. Access via `My Profile` in the navigation.

#### Personal Information

| Field | Description |
|---|---|
| Profile Photo | Upload a headshot (JPG/PNG) |
| Date of Birth | Used for age verification where required |
| Gender | |
| Nationality | |
| Passport / National ID | Optional |
| Phone & Mobile | |
| WhatsApp Number | |
| Full Address | Street, city, state, country, postal code |
| LinkedIn | Profile URL |
| GitHub | Profile URL |
| Portfolio / Website | Personal or portfolio URL |

#### Professional Information

| Field | Description |
|---|---|
| Professional Summary | A short bio or objective statement |
| Current Job Title | Your present role |
| Current Employer | Your present company |
| Years of Experience | Total professional experience |
| Expected Salary | With currency selection |
| Notice Period | How soon you can start |
| Availability Date | Earliest start date |
| Open to Relocation | Yes / No |
| Open to Travel | Yes / No |
| Employment Type Preferences | Select all that apply: Full-time, Part-time, Contract, Freelance, Internship |

#### CV Sections

Add, edit, reorder, and delete entries in 10 different CV categories:

**Experience**
Record each position you have held:
- Job title, company name
- Employment type (Full-time, Part-time, Contract, etc.)
- Location
- Start and end dates (or mark as current)
- Description of responsibilities and achievements

**Education**
For each qualification:
- Degree type (High School, Bachelor's, Master's, PhD, etc.)
- Field of study
- Institution name
- Grade / GPA
- Start and end dates
- Description

**Skills**
List professional skills with proficiency levels:
- Beginner, Elementary, Intermediate, Advanced, Expert

**Languages**
Languages you speak with fluency:
- Basic, Conversational, Professional, Fluent, Native

**Certifications**
- Certificate name, issuing organisation
- Issue date and expiry date
- Credential ID and verification URL

**Projects**
- Project name and description
- Project URL
- Technologies used
- Start and end dates

**Awards & Achievements**
- Award name, issuing organisation
- Date received
- Description

**Volunteer Work**
- Organisation name, cause
- Role and description
- Dates

**References**
- Referee name, job title, company
- Email and phone
- Relationship

**Publications**
- Publication title, publisher
- Publication date
- URL or DOI
- Description

**Reordering Sections**
Drag sections to change the display order on your profile. The order you set here is the order recruiters will see.

**CV Upload**
Upload your full CV as a file (PDF recommended) under `My Profile`. You can update it at any time; the previous version is replaced.

---

### Applying for Jobs

**Applying to a Specific Job**
1. Find the job on the jobs board
2. Click `Apply Now`
3. On the application form, write a cover letter (optional but recommended)
4. Attach a document if required
5. Submit

You cannot apply to the same job twice. Once submitted, the application status is tracked in `My Applications`.

**Job Bank Submission**
If you don't see a suitable opening, you can submit your profile to the **Job Bank** — a general talent pool that HR can search when new positions arise. Go to `My Dashboard → Submit to Job Bank` or use the dedicated link.

---

### Tracking Applications

**My Applications** (`/careers/my-applications`)

A table of every application you have submitted showing:
- Job title and company
- Date applied
- Current status

**Application Statuses**

| Status | Meaning |
|---|---|
| New | Received, not yet reviewed |
| Reviewing | HR is reviewing your application |
| Shortlisted | You have been shortlisted for further consideration |
| Interviewed | An interview has taken or is being scheduled |
| Offered | A job offer has been made |
| Hired | You have accepted and joined |
| Rejected | Not selected for this position |
| Withdrawn | You withdrew the application |

**Withdrawing an Application**
You can withdraw a pending application (status: New or Reviewing) at any time by clicking `Withdraw` next to the listing in My Applications.

---

## HR Management of Jobs & Recruitment

*Available to: HR Admin, Super Admin*

This section covers the HR side of the recruitment pipeline.

### Job Postings

**Creating a Job Posting** (`Jobs → New Job`)
Fill in all posting details:
- Job title, category, department
- Employment type and experience level
- Minimum and maximum years of experience
- Required skills (free-text tags)
- Education requirements
- Location (city, country)
- Number of open positions
- Salary range and currency (toggle whether to show this publicly)
- Application deadline
- Mark as Featured (displays highlighted on the portal)
- Status: Draft (not visible) → Open (live on portal) → Paused → Closed

**Managing Postings**
The Jobs list shows all postings with their current status and applicant count. Edit, pause, re-open, or close a posting at any time.

**Job Categories**
Manage the taxonomy of job categories under `Jobs → Categories`. Each category groups related postings on the portal.

### Managing Applicants

**Applicant List** (`Jobs → [Job Title] → Applicants`)
See every applicant for a specific posting with their name, application date, and current status. Sort and filter by status.

**Applicant Profile**
Click any applicant to view their full submission:
- All profile data (personal, professional, CV sections)
- Uploaded CV (download link)
- Cover letter
- Application status history with timestamps

**Updating Status**
Change the applicant's status to move them through the pipeline (Reviewing → Shortlisted → Interviewed → Offered → Hired or Rejected). Each change is logged with a timestamp.

**HR Notes & Rating**
Add internal notes and a numerical rating to an applicant's profile. Notes are visible to HR only, not to the applicant.

**Job Bank**
Access the pool of general applicants who submitted to the talent pool rather than a specific job. Browse, search, and review profiles for future sourcing.

**Export**
Download the full applicant list for any job posting as a report.

---

## Permissions Reference

| Permission Key | Description |
|---|---|
| `dashboard.view` | Access the dashboard |
| `employee.view_all` | View all employee records |
| `employee.view_self` | View own employee record |
| `employee.create` | Create new employee records |
| `employee.edit` | Edit employee records |
| `employee.archive` | Archive employees |
| `leave.view_self` | View own leave data |
| `leave.submit` | Submit leave requests |
| `leave.approve_team` | Approve/reject team leave |
| `leave.manage_types` | Create and edit leave types and policies |
| `documents.upload_self` | Upload own documents |
| `documents.view_self` | View own documents |
| `documents.manage_all` | Manage all employee documents |
| `announcements.view` | Read announcements |
| `announcements.manage` | Create and edit announcements |
| `reports.view_team` | View team-level reports |
| `reports.view_hr` | View all HR reports and audit logs |
| `settings.manage` | Manage system settings |
| `audit.view` | View the audit log |
| `onboarding.manage` | Manage onboarding workflows |
| `offboarding.manage` | Manage offboarding workflows |
| `notifications.view_self` | View own notifications |
| `structure.manage` | Manage org structure |

---

*G2 Group HR Platform — Internal Documentation*
*Last updated: April 2026*
