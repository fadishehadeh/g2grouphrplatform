# Live Update Description

This update includes 2 view-file changes only.

## Included Changes

### 1. Leave Balances
File: `app/Views/leaves/balances.php`

- Removed the `Manual Balance Correction` section.
- Kept the rest of the leave balances page unchanged.

### 2. Employee Directory
File: `app/Views/employees/index.php`

- Employee name is now clickable and opens the employee edit page.
- Fixed the action buttons layout.
- Changed the edit button from icon-only to a proper `Edit` button with better spacing.

## Live Upload Steps

Replace these files on the live server:

- `app/Views/leaves/balances.php`
- `app/Views/employees/index.php`

After upload:

1. Refresh the page with `Ctrl + F5`
2. If changes do not appear immediately, log out and log in again
3. If the server uses PHP opcode cache, wait briefly or restart PHP if available

## SQL Changes

No SQL update is required for this change.

## Expected Result

- `Manual Balance Correction` no longer appears on the leave balances page
- Employee names in the directory can be clicked to edit
- The employee list `Edit` button displays correctly with proper spacing
