# HR System App Description

## Overview

This repository contains a custom PHP-based Human Resources platform with two connected applications:

1. An internal **HR Management System** for employees, managers, HR staff, and super administrators.
2. A public-facing **Careers Portal** for external candidates.

The platform is designed to centralize employee administration, leave management, documents, HR communications, onboarding and offboarding workflows, reporting, and recruitment. The careers side extends the system into a candidate-facing experience where job seekers can create profiles, apply for vacancies, and track applications.

The two applications are connected through the jobs and recruitment flow:

- HR users create and manage jobs from the internal system.
- Published jobs appear on the careers portal.
- Candidate applications are stored in the careers domain and surfaced back into the HR recruitment workflow.

## Primary Business Purpose

The app is intended to act as a single operational layer for people operations. It replaces fragmented HR processes such as:

- maintaining employee records in spreadsheets,
- handling leave manually through email,
- storing HR documents in ad hoc folders,
- issuing employee letters manually,
- tracking onboarding and offboarding informally,
- managing recruitment in disconnected tools.

The system combines internal HR administration and external recruitment into one codebase while keeping their runtime entry points and authentication flows separated.

## Application Surfaces

### 1. Internal HR App

Primary web entry point: `public/index.php`

Main audience:

- Super Admin
- HR Admin
- Manager
- Employee

This area includes authenticated business operations for HR and employee self-service.

### 2. Careers Portal

Primary web entry point: `public-careers/index.php`

Main audience:

- Public visitors
- Registered candidates / job seekers

This area handles job discovery, candidate registration, candidate profile management, and job applications.

## Core User Roles

### Super Admin

Has unrestricted access to the whole HR platform, including:

- user management,
- role and permission assignment,
- organization structure,
- HR operations,
- system settings,
- audit-oriented oversight.

### HR Admin

Has broad operational HR access, typically including:

- employee management,
- leave administration,
- document oversight,
- onboarding and offboarding,
- jobs and applicant review,
- announcements,
- reports,
- attendance setup and configuration.

### Manager

Has team-scoped access, focused on:

- direct reports,
- leave approvals,
- team visibility,
- team-level reporting.

### Employee

Has self-service access, typically for:

- own profile,
- own leave requests,
- own documents,
- letters,
- notifications,
- announcements.

### Job Seeker

Uses the careers portal only. This role is isolated from the internal HR application and can:

- browse open positions,
- register and verify an account,
- maintain a professional profile,
- upload a CV,
- apply for jobs,
- track application status.

## Functional Scope

## Authentication and Access Control

The app uses a custom authentication layer backed by sessions and role-based permissions.

Key characteristics:

- login supports username or email,
- account status is enforced,
- role permissions are resolved dynamically from role-to-permission mappings,
- authenticated routes are protected through middleware,
- there is support for OTP-based login completion and password reset flows,
- API requests can use token-authenticated in-memory user context,
- separate session identity is used for the careers app.

## Dashboard Layer

The dashboard is role-aware and acts as the landing surface after login. Depending on the user role, it can expose:

- headcount summaries,
- pending leave approvals,
- recent announcements,
- expiring documents,
- onboarding and offboarding activity,
- self-service shortcuts,
- team insights.

## Employee Management

The internal app provides a full employee management workflow, including:

- employee directory,
- employee profile pages,
- employee create and edit forms,
- archive handling,
- history and change tracking,
- organization assignment,
- manager assignment,
- employee import flows,
- employee-specific document access.

Employee data appears to be central to the rest of the platform and is reused by leave, documents, reporting, onboarding, and offboarding.

## Leave Management

The leave module supports both employee self-service and HR operations.

Main capabilities:

- leave request submission,
- leave approvals,
- leave balances,
- leave types,
- leave policies,
- holiday calendars,
- weekend configuration,
- request history,
- approval trail,
- calendar-based visibility.

This module is designed to support role-aware access, where employees see their own data, managers see team requests, and HR sees organization-wide activity.

## Documents and Compliance

The documents module provides structured storage and tracking for employee-related files.

Included capabilities:

- document upload,
- employee-specific document records,
- document categories and types,
- expiry tracking,
- expiring-document reporting,
- self-service document access,
- HR-wide document oversight.

This module is clearly intended for compliance-heavy records such as IDs, permits, contracts, and similar HR documents.

## Letters and Certificates

The platform supports employee-requested letters and HR-managed issuance.

Typical workflow:

- employee submits a request,
- HR reviews and either generates or rejects,
- generated letters can be delivered as downloadable output,
- templates are managed centrally.

This feature reduces manual document preparation for common HR letters.

## Announcements and Notifications

The communication layer includes:

- organization announcements,
- read/unread tracking,
- in-app notifications,
- notification counts in the top navigation,
- workflow-triggered alerts.

This supports both one-to-many communications and event-based system feedback.

## Onboarding

The onboarding module is task-oriented and template-driven.

It supports:

- onboarding templates,
- employee-specific onboarding plans,
- task lists with progress tracking,
- due-date workflows,
- visibility into completion state.

This structure is useful for standardizing new-hire processes across teams.

## Offboarding

The offboarding module mirrors onboarding but for employee exits.

It supports:

- offboarding records,
- exit workflows,
- clearance tracking,
- asset-return tracking,
- task completion monitoring.

This is useful for controlled employee separation and operational handover.

## Organizational Structure

The structure domain models the business hierarchy and reference data used across HR processes.

Managed entities include:

- companies,
- branches,
- departments,
- teams,
- job titles,
- designations,
- reporting lines.

These records feed employee assignment, approvals, reporting, and recruitment classification.

## Reports and Analytics

The app includes operational HR reporting such as:

- headcount,
- department distribution,
- new joiners,
- exits,
- leave usage,
- documents,
- audit-oriented views.

Exports are supported through spreadsheet and PDF tooling present in the repository dependencies.

## Attendance and Scheduling

The settings and attendance area provides foundational attendance configuration, including:

- shifts,
- schedules,
- attendance statuses,
- attendance records,
- attendance assignments.

This appears to be a lighter operational layer compared to employee and leave management, but it is integrated enough to support scheduling and attendance-related views.

## Recruitment and Careers

The jobs and careers flow spans both applications.

Internal HR capabilities include:

- job posting creation,
- job category management,
- applicant review,
- applicant status updates,
- recruitment oversight.

Candidate-facing capabilities include:

- public jobs listing,
- job detail pages,
- registration and login,
- OTP verification,
- profile completion,
- CV sections,
- applications history,
- application withdrawal where allowed.

This makes the platform both an HRIS-style tool and a lightweight applicant tracking system.

## Candidate Profile Model

The careers portal supports a richer candidate profile than a simple resume upload. Based on the existing views and documentation, candidate records can include:

- personal information,
- professional summary,
- experience,
- education,
- skills,
- languages,
- certifications,
- projects,
- awards,
- volunteer work,
- references,
- publications,
- uploaded CV.

This gives recruiters a structured profile model rather than relying only on attached files.

## Architecture

## Stack

The codebase uses:

- PHP with a custom MVC-style application structure,
- MySQL-compatible relational storage,
- Bootstrap 5 for UI,
- Bootstrap Icons,
- PhpSpreadsheet for Excel export/import workflows,
- TCPDF for PDF generation,
- PHPUnit for tests.

## Runtime Design

The app is not based on a large framework such as Laravel or Symfony. Instead, it uses a custom application core that provides:

- bootstrapping,
- config loading,
- routing,
- request capture,
- response handling,
- session management,
- database access,
- authentication,
- CSRF protection,
- middleware dispatch.

Core classes under `app/Core` implement this foundation.

## Routing Model

Routes are registered in separate route files under `routes/`, grouped by domain:

- `auth.php`
- `web.php`
- `admin.php`
- `structure.php`
- `employees.php`
- `leaves.php`
- `documents.php`
- `onboarding.php`
- `offboarding.php`
- `announcements.php`
- `reports.php`
- `settings.php`
- `letters.php`
- `careers.php`
- `jobs.php`
- `api.php`

The main internal entry point loads all of these route files. The careers entry point loads only the careers routes, which keeps the public-facing surface narrower.

## Module Organization

Business logic is grouped into modules under `app/Modules`, including:

- `Admin`
- `Announcements`
- `Api`
- `Auth`
- `Careers`
- `Dashboard`
- `Documents`
- `Employees`
- `Jobs`
- `Leave`
- `Letters`
- `Notifications`
- `Offboarding`
- `Onboarding`
- `Profile`
- `Reports`
- `Settings`
- `Structure`

Views are organized under `app/Views`, and the app uses shared layouts and partials for the authenticated shell and other reusable UI components.

## Data Separation

The existing documentation and runtime entry points indicate that the platform uses separate data domains for:

- the internal HR system,
- the careers portal.

This separation is useful because internal employee records and external candidate records usually have different access, retention, and workflow concerns.

## Security Characteristics

The application includes several deliberate security controls:

- session-based authentication,
- role and permission enforcement,
- middleware-protected routes,
- CSRF protection,
- password hashing verification,
- account status enforcement,
- configurable login lockout settings,
- password reset expiry,
- content security policy,
- referrer policy,
- permissions policy,
- frame protection headers,
- HSTS when running over HTTPS.

There is also evidence of PII-focused work in the database migration set, including encryption-related migrations.

## Frontend and UX Model

The internal UI uses a shared app shell with:

- a left sidebar,
- a topbar,
- flash messaging,
- notification access,
- responsive content cards,
- module-level navigation components.

The interface is built around operational data-entry and review screens rather than marketing pages. The recent CSS structure also shows an intentional responsive layer for mobile and tablet use.

## File and Directory Highlights

Important repository areas:

- `app/Core` - framework-like application core
- `app/Modules` - domain controllers and repositories
- `app/Views` - UI templates
- `config` - app and database configuration
- `routes` - route registration
- `public` - internal HR web root
- `public-careers` - careers portal web root
- `public/assets` - shared CSS, JS, and image assets
- `database` - schema, seed, and migration SQL files
- `storage` - uploaded files and generated assets
- `tests` - PHPUnit and utility test scripts

## Integrations and Outputs

The repository indicates support for several operational outputs and integrations:

- email sending,
- notification workflows,
- password reset emails,
- OTP delivery,
- Excel export/import,
- PDF exports and generated letters,
- document uploads,
- candidate CV storage.

These are core to how the platform interacts with users and administrators.

## Typical End-to-End Workflows

### Internal Employee Lifecycle

1. HR creates or imports an employee.
2. User access is assigned.
3. The employee logs in and uses self-service features.
4. HR manages documents, leave, and profile updates over time.
5. Onboarding or offboarding workflows are tracked as needed.

### Leave Workflow

1. Employee submits a leave request.
2. Manager or HR reviews it depending on policy and hierarchy.
3. Approval status is recorded.
4. Balances and calendar visibility are updated.
5. Notifications inform the relevant users.

### Recruitment Workflow

1. HR creates a job posting internally.
2. The job is published to the careers portal.
3. Candidates register and build profiles.
4. Candidates apply to jobs.
5. HR reviews applicants and updates statuses.
6. Recruitment outcomes can feed hiring and onboarding.

## Operational Strengths

Based on the current codebase and documentation, the app’s main strengths are:

- broad HR process coverage in one system,
- integrated internal HR and public recruitment flow,
- custom RBAC rather than only coarse role checks,
- separate internal and careers entry points,
- structured employee and candidate data,
- export and document generation support,
- practical workflow modules for onboarding and offboarding.

## Current Product Identity

At a product level, this app is best described as:

> A custom HR operations and recruitment platform that combines employee management, leave, documents, communications, reporting, onboarding, offboarding, and a public careers portal in a single PHP codebase.

It is not only a basic employee directory. It is an operational HR platform with self-service, workflow, compliance, and recruitment capabilities.

## Suggested Use of This Document

This file is suitable as:

- a high-level technical overview for developers,
- a product description for internal documentation,
- a handoff summary for maintainers,
- a starting point for README or project wiki content.
