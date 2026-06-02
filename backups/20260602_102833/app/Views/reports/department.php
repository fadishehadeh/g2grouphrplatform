<?php declare(strict_types=1); ?>
<?php
$largestDepartment = $departments[0]['department_name'] ?? '—';
$largestDepartmentCount = (int) ($departments[0]['total_employees'] ?? 0);
?>
<?php require base_path('app/Views/partials/reports-nav.php'); ?>
<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="card content-card h-100"><div class="card-body"><div class="text-muted small">Departments</div><div class="display-6 fw-semibold"><?= e((string) count($departments ?? [])); ?></div><div class="small text-muted mt-2"><?= e((string) ($scopeLabel ?? '')); ?></div></div></div></div>
    <div class="col-md-4"><div class="card content-card h-100"><div class="card-body"><div class="text-muted small">Employees in Scope</div><div class="display-6 fw-semibold"><?= e((string) ($overview['total_employees'] ?? 0)); ?></div></div></div></div>
    <div class="col-md-4"><div class="card content-card h-100"><div class="card-body"><div class="text-muted small">Largest Department</div><div class="fw-semibold fs-5"><?= e((string) $largestDepartment); ?></div><div class="small text-muted mt-2"><?= e((string) $largestDepartmentCount); ?> employee(s)</div></div></div></div>
</div>

<div class="card content-card"><div class="card-body p-4"><div class="d-flex justify-content-between align-items-center mb-3"><div><h5 class="mb-1">Department Distribution</h5><p class="text-muted mb-0">Employee totals grouped by department with a simple active/on-leave/other split.</p></div><span class="text-muted small"><?= e((string) ($scopeLabel ?? '')); ?></span></div><?php if (($departments ?? []) === []): ?><div class="empty-state">No department data is available yet.</div><?php else: ?><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Department</th><th class="text-end">Total</th><th class="text-end">Active</th><th class="text-end">On Leave</th><th class="text-end">Other</th></tr></thead><tbody><?php foreach ($departments as $row): ?><tr><td><?= e((string) $row['department_name']); ?></td><td class="text-end fw-semibold"><?= e((string) $row['total_employees']); ?></td><td class="text-end"><?= e((string) $row['active_employees']); ?></td><td class="text-end"><?= e((string) $row['on_leave_employees']); ?></td><td class="text-end"><?= e((string) $row['other_employees']); ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div></div>