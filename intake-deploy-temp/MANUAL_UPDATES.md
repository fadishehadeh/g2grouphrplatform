# Manual Updates Required

These files need to be manually updated. They contain other code that must be preserved.

---

## 1. public-hr/index.php

**Add this line after:** equire BASE_PATH . '/routes/jobs.php';

`php
require BASE_PATH . '/routes/intake.php';
`

---

## 2. app/Views/partials/sidebar.php

**Update  variable:**
`php
$peopleOpen = in_array(current_route_prefix(), ['/employees', '/structures', '/letters', '/employee-registration']);
`

**Add intake nav link** (after Employees link in People section):
`php
<a href="<?= e(url('/employee-registration/review')); ?>" class="sidebar-sublink">
    <i class="bi bi-person-plus-fill"></i> Employee Intake
</a>
`

---

## Deployment Order

1. Run SQL migrations
2. Copy all files from package
3. Manually update public-hr/index.php
4. Manually update app/Views/partials/sidebar.php
5. Test at /employee-registration (public) and /admin/employee-registration/review (HR)
