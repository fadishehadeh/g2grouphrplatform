<?php declare(strict_types=1); ?>
<?php
$activeEmployees = (int) ($overview['active_employees'] ?? 0);
$onLeaveEmployees = 0;
$otherEmployees = 0;

foreach (($statusDistribution ?? []) as $row) {
    $count = (int) ($row['total_employees'] ?? 0);

    if ((string) ($row['employee_status'] ?? '') === 'on_leave') {
        $onLeaveEmployees += $count;
        continue;
    }

    if ((string) ($row['employee_status'] ?? '') !== 'active') {
        $otherEmployees += $count;
    }
}
?>
<?php require base_path('app/Views/partials/reports-nav.php'); ?>
<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl-3"><div class="card content-card h-100"><div class="card-body"><div class="text-muted small">Employees</div><div class="display-6 fw-semibold"><?= e((string) ($overview['total_employees'] ?? 0)); ?></div><div class="small text-muted mt-2"><?= e((string) ($scopeLabel ?? '')); ?></div></div></div></div>
    <div class="col-md-6 col-xl-3"><div class="card content-card h-100"><div class="card-body"><div class="text-muted small">Active</div><div class="display-6 fw-semibold"><?= e((string) $activeEmployees); ?></div></div></div></div>
    <div class="col-md-6 col-xl-3"><div class="card content-card h-100"><div class="card-body"><div class="text-muted small">On Leave</div><div class="display-6 fw-semibold"><?= e((string) $onLeaveEmployees); ?></div></div></div></div>
    <div class="col-md-6 col-xl-3"><div class="card content-card h-100"><div class="card-body"><div class="text-muted small">Other Statuses</div><div class="display-6 fw-semibold"><?= e((string) $otherEmployees); ?></div></div></div></div>
</div>

<div class="card content-card mb-4"><div class="card-body p-4"><form method="get" action="<?= e(url('/reports/headcount')); ?>" class="row g-3 align-items-end"><div class="col-lg-8"><label class="form-label">Search</label><input type="text" name="q" class="form-control" placeholder="Employee, email, department, or job title..." value="<?= e((string) ($search ?? '')); ?>"></div><div class="col-md-4 col-lg-2"><label class="form-label">Status</label><select name="status" class="form-select"><option value="all">All statuses</option><?php foreach (['draft' => 'Draft', 'active' => 'Active', 'on_leave' => 'On Leave', 'inactive' => 'Inactive', 'resigned' => 'Resigned', 'terminated' => 'Terminated', 'archived' => 'Archived'] as $value => $label): ?><option value="<?= e($value); ?>" <?= (string) ($status ?? 'all') === $value ? 'selected' : ''; ?>><?= e($label); ?></option><?php endforeach; ?></select></div><div class="col-md-4 col-lg-2 d-flex gap-2"><button type="submit" class="btn btn-outline-secondary w-100">Filter</button><a href="<?= e(url('/reports/headcount')); ?>" class="btn btn-outline-light border w-100">Reset</a></div></form></div></div>

<div class="row g-4">
    <div class="col-lg-4"><div class="card content-card h-100"><div class="card-body p-4"><h5 class="mb-3">Status Distribution</h5><?php if (($statusDistribution ?? []) === []): ?><div class="empty-state">No employee status data is available.</div><?php else: ?><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Status</th><th class="text-end">Employees</th></tr></thead><tbody><?php foreach ($statusDistribution as $row): ?><tr><td><?= e(ucwords(str_replace('_', ' ', (string) $row['employee_status']))); ?></td><td class="text-end fw-semibold"><?= e((string) $row['total_employees']); ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div></div></div>
    <div class="col-lg-8"><div class="card content-card h-100"><div class="card-body p-4"><div class="d-flex justify-content-between align-items-center mb-3"><h5 class="mb-0">Employee Directory</h5><span class="text-muted small"><?= e((string) ($scopeLabel ?? '')); ?></span></div><?php if (($employees ?? []) === []): ?><div class="empty-state">No employees matched the selected filters.</div><?php else: ?><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Employee</th><th>Department</th><th>Job Title</th><th>Joined</th><th>Status</th></tr></thead><tbody><?php foreach ($employees as $row): ?><tr><td><div class="fw-semibold"><?= e((string) $row['employee_name']); ?></div><div class="small text-muted"><?= e((string) $row['employee_code']); ?> · <?= e((string) $row['work_email']); ?></div></td><td><?= e((string) $row['department_name']); ?></td><td><?= e((string) $row['job_title_name']); ?></td><td><?= e((string) (($row['joining_date'] ?? null) !== null ? $row['joining_date'] : '—')); ?></td><td><span class="badge text-bg-light border"><?= e(ucwords(str_replace('_', ' ', (string) $row['employee_status']))); ?></span></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div></div></div>
</div>