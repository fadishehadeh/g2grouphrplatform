<?php declare(strict_types=1); ?>
<?php
$totalRequests = 0;
$approvedDays = 0.0;
$pendingRequests = 0;

foreach (($summary ?? []) as $row) {
    $totalRequests += (int) ($row['total_requests'] ?? 0);

    if ((string) ($row['status'] ?? '') === 'approved') {
        $approvedDays += (float) ($row['total_days'] ?? 0);
    }

    if (in_array((string) ($row['status'] ?? ''), ['submitted', 'pending_manager', 'pending_hr'], true)) {
        $pendingRequests += (int) ($row['total_requests'] ?? 0);
    }
}
?>
<?php require base_path('app/Views/partials/reports-nav.php'); ?>
<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="card content-card h-100"><div class="card-body"><div class="text-muted small">Requests</div><div class="display-6 fw-semibold"><?= e((string) $totalRequests); ?></div><div class="small text-muted mt-2"><?= e((string) ($scopeLabel ?? '')); ?></div></div></div></div>
    <div class="col-md-4"><div class="card content-card h-100"><div class="card-body"><div class="text-muted small">Approved Days</div><div class="display-6 fw-semibold"><?= e(number_format($approvedDays, 2)); ?></div></div></div></div>
    <div class="col-md-4"><div class="card content-card h-100"><div class="card-body"><div class="text-muted small">Pending Requests</div><div class="display-6 fw-semibold"><?= e((string) $pendingRequests); ?></div></div></div></div>
</div>

<div class="card content-card mb-4"><div class="card-body p-4"><form method="get" action="<?= e(url('/reports/leave-usage')); ?>" class="row g-3 align-items-end"><div class="col-lg-4"><label class="form-label">Search</label><input type="text" name="q" class="form-control" placeholder="Employee, leave type, or department..." value="<?= e((string) ($search ?? '')); ?>"></div><div class="col-md-4 col-lg-2"><label class="form-label">Status</label><select name="status" class="form-select"><option value="all">All statuses</option><?php foreach (['draft' => 'Draft', 'submitted' => 'Submitted', 'pending_manager' => 'Pending Manager', 'pending_hr' => 'Pending HR', 'approved' => 'Approved', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled', 'withdrawn' => 'Withdrawn'] as $value => $label): ?><option value="<?= e($value); ?>" <?= (string) ($status ?? 'all') === $value ? 'selected' : ''; ?>><?= e($label); ?></option><?php endforeach; ?></select></div><div class="col-md-4 col-lg-2"><label class="form-label">From</label><input type="date" name="from_date" class="form-control" value="<?= e((string) ($fromDate ?? '')); ?>"></div><div class="col-md-4 col-lg-2"><label class="form-label">To</label><input type="date" name="to_date" class="form-control" value="<?= e((string) ($toDate ?? '')); ?>"></div><div class="col-md-4 col-lg-2 d-flex gap-2"><button type="submit" class="btn btn-outline-secondary w-100">Filter</button><a href="<?= e(url('/reports/leave-usage')); ?>" class="btn btn-outline-light border w-100">Reset</a></div></form></div></div>

<div class="row g-4">
    <div class="col-lg-4"><div class="card content-card h-100"><div class="card-body p-4"><h5 class="mb-3">Status Summary</h5><?php if (($summary ?? []) === []): ?><div class="empty-state">No leave requests matched the selected filters.</div><?php else: ?><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Status</th><th class="text-end">Requests</th><th class="text-end">Days</th></tr></thead><tbody><?php foreach ($summary as $row): ?><tr><td><?= e(ucwords(str_replace('_', ' ', (string) $row['status']))); ?></td><td class="text-end"><?= e((string) $row['total_requests']); ?></td><td class="text-end"><?= e((string) $row['total_days']); ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div></div></div>
    <div class="col-lg-8"><div class="card content-card h-100"><div class="card-body p-4"><h5 class="mb-3">Leave Requests</h5><?php if (($requests ?? []) === []): ?><div class="empty-state">No leave usage rows matched the selected filters.</div><?php else: ?><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Employee</th><th>Leave Type</th><th>Dates</th><th class="text-end">Days</th><th>Status</th></tr></thead><tbody><?php foreach ($requests as $row): ?><tr><td><div class="fw-semibold"><?= e((string) $row['employee_name']); ?></div><div class="small text-muted"><?= e((string) $row['employee_code']); ?> · <?= e((string) $row['department_name']); ?></div></td><td><?= e((string) $row['leave_type_name']); ?></td><td><div><?= e((string) $row['start_date']); ?> → <?= e((string) $row['end_date']); ?></div><div class="small text-muted">Submitted: <?= e((string) (($row['submitted_at'] ?? null) !== null ? $row['submitted_at'] : '—')); ?></div></td><td class="text-end"><?= e((string) $row['days_requested']); ?></td><td><span class="badge text-bg-light border"><?= e(ucwords(str_replace('_', ' ', (string) $row['status']))); ?></span></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div></div></div>
</div>