# Release Notes

## Current System Release Summary

This document summarizes the main implemented updates and enhancements completed across the recent work cycle.

## 1. Employee Import Upgrade

Implemented:

- `employee_code` is no longer required in import files
- `work_email` can be blank
- Blank optional fields are allowed on create
- Missing employee codes are auto-generated using sequential code logic
- Multiple blank-code rows receive unique generated codes in one import
- Existing employee matching now checks:
  - employee code
  - work email
  - same name + company as a review trigger
- Same-name matches are not auto-updated
- Conservative review/confirmation import flow added
- Import help/template behavior updated

## 2. Structure-Aware Employee Import

Implemented support to work more flexibly with imported employee master data, including handling organizational records needed during employee loading.

## 3. Employee Directory Improvements

Implemented:

- Collapsible advanced filters
- Expanded directory filter coverage
- Missing items audit page with matching filter behavior
- Employee name can now be clicked to edit
- Employee action buttons cleaned up
- Edit button now shows proper text instead of icon-only state

## 4. Missing Items Audit Tool

Implemented:

- Audit screen to identify missing employee fields/documents
- Filtering support aligned with employee directory patterns

## 5. Leave Approval Simplification

Implemented routing update for new leave requests:

- Direct manager approval when a valid manager user exists
- Fallback to HR admin when no valid manager approver exists
- No extra workflow-driven escalation step for new requests

## 6. Annual Leave Rule Update

Implemented:

- Special annual leave logic based on `joining_date`
- 2026 leave baseline
- 22 days for employees who joined before 2026
- 1.8333 accrual per eligible month for 2026 joiners
- Accrual starts from the first full month after joining
- 3-month annual leave request restriction from joining date
- Annual carry-forward up to 5 days
- Carry-forward expiry after March 31
- Separate annual calculation path from generic leave type balance assignment

## 7. Annual Leave Used-Days Import

Implemented:

- Annual used-days import by `employee_code + used_days`
- Alternate support for Excel layout using `Employee Name + Used Balance`
- Imported values replace stored used totals for the selected year
- Review handling for ambiguous name matches

## 8. Leave Management Admin Tools

Implemented:

- Admin “Add Leave for Employee” flow
- Direct-approved admin leave entries
- Employee leave detail screen
- Admin editing of approved leave records
- Leave history and balance detail on employee leave page
- Days calculated automatically from leave dates and sessions
- Seed action for common leave types

## 9. Default Leave Types

Implemented idempotent default seed for:

- Annual Leave
- Sick Leave
- Emergency Leave
- Maternity Leave
- Unpaid Leave

## 10. Leave Balances View Improvements

Implemented:

- Pagination
- Active employees only in leave balances
- Dedicated employee management links from balances
- Manual balance correction removed from balances screen

## 11. Session and UX Adjustments

Implemented:

- Session timeout alignment updates
- Better employee and leave admin navigation behavior

## 12. Resilience / Backup Module

Implemented:

- Super-admin-only resilience console
- Backup history table
- Database backup artifact tracking
- Uploads backup artifact tracking
- Secure backup download links
- Expiring token-based backup downloads
- Manual “Run Now” backup action
- Download link refresh action
- Daily backup email notifications
- 30-day retention configuration
- 7-day link expiry configuration

## 13. Backup Email Upgrade

Implemented:

- Email now includes secure download links
- Separate status reporting for database and uploads artifacts
- Handles partial success/failure states
- Works with current mail flow

## 14. Mail Queue / Mailjet Compatibility Fix

Implemented:

- Mail queue processing adjustment to work with current Mailjet-based configuration

## 15. Dashboard / Preview Work

Added isolated visual preview artifacts in `public/` for dashboard and employee page concept validation without changing production UI.

## 16. Latest Small UI Patch

Implemented:

- Removed `Manual Balance Correction`
- Fixed employee list action button layout
- Added clickable employee name edit access

## Notes

- These notes summarize the major functional changes completed in the recent implementation cycle.
- They are not a commit-by-commit changelog.
