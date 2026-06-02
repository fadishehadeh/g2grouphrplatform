<?php declare(strict_types=1); ?>
<?php
$statusValue = (string) ($employee['employee_status'] ?? 'draft');
$statusBadge = match ($statusValue) {
    'active' => 'text-bg-success',
    'on_leave' => 'text-bg-warning',
    'terminated', 'resigned' => 'text-bg-danger',
    'archived' => 'text-bg-dark',
    default => 'text-bg-secondary',
};
$isArchived = (($employee['archived_at'] ?? null) !== null) || $statusValue === 'archived';
$displayValue = static function (mixed $value): string {
    if ($value === null || $value === '') {
        return '—';
    }

    return ucwords(str_replace('_', ' ', (string) $value));
};
?>
<div class="card content-card mb-4"><div class="card-body p-4 d-flex flex-column flex-lg-row justify-content-between gap-3"><div><div class="small text-muted mb-2">Employee History</div><h4 class="mb-1"><?= e(trim((string) (($employee['first_name'] ?? '') . ' ' . ($employee['middle_name'] ?? '') . ' ' . ($employee['last_name'] ?? '')))); ?></h4><div class="text-muted"><?= e((string) ($employee['employee_code'] ?? '')); ?> · <?= e((string) ($employee['job_title_name'] ?? 'Unassigned')); ?></div></div><div class="text-lg-end"><span class="badge <?= $statusBadge; ?> mb-2"><?= e(ucwords(str_replace('_', ' ', $statusValue))); ?></span><div class="small text-muted">Status entries: <?= e((string) count($statusHistory ?? [])); ?></div><div class="small text-muted">Activity entries: <?= e((string) count($historyLogs ?? [])); ?></div><?php if ($isArchived && ($employee['archived_at'] ?? null) !== null): ?><div class="small text-muted">Archived at: <?= e((string) $employee['archived_at']); ?></div><?php endif; ?></div></div></div></div>

<div class="card content-card mb-4"><div class="card-body p-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3"><div><h5 class="mb-1">Timeline & Change Log</h5><p class="text-muted mb-0">Review employee status movement and the change records captured for this profile.</p></div><div class="d-flex gap-2 flex-wrap"><a href="<?= e(url('/employees/' . $employee['id'])); ?>" class="btn btn-outline-secondary">Back to Profile</a><?php if (can('employee.archive') && !$isArchived): ?><a href="<?= e(url('/employees/' . $employee['id'] . '/archive')); ?>" class="btn btn-outline-danger">Archive Employee</a><?php endif; ?></div></div></div>

<div class="row g-4">
    <div class="col-xl-5"><div class="card content-card h-100"><div class="card-body p-4"><h5 class="mb-3">Status Timeline</h5><?php if (($statusHistory ?? []) === []): ?><div class="empty-state">No employee status transitions have been recorded yet.</div><?php else: ?><div class="d-grid gap-3"><?php foreach ($statusHistory as $row): ?><div class="border rounded-4 p-3"><div class="d-flex justify-content-between gap-3 mb-2"><div><div class="fw-semibold"><?= e($displayValue($row['new_status'] ?? '')); ?></div><div class="small text-muted">Effective <?= e((string) ($row['effective_date'] ?? '—')); ?></div></div><div class="small text-muted text-end"><?= e((string) (($row['changed_by_name'] ?? '') !== '' ? $row['changed_by_name'] : 'System')); ?></div></div><div class="small"><strong>Previous:</strong> <?= e($displayValue($row['previous_status'] ?? null)); ?></div><div class="small"><strong>Remarks:</strong> <?= e((string) (($row['remarks'] ?? '') !== '' ? $row['remarks'] : '—')); ?></div><div class="small text-muted mt-2">Logged <?= e((string) ($row['created_at'] ?? '—')); ?></div></div><?php endforeach; ?></div><?php endif; ?></div></div></div>
    <div class="col-xl-7"><div class="card content-card h-100"><div class="card-body p-4"><h5 class="mb-3">Activity Log</h5><?php if (($historyLogs ?? []) === []): ?><div class="empty-state">No employee change records have been logged yet.</div><?php else: ?><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>When</th><th>Action</th><th>Field</th><th>Old Value</th><th>New Value</th><th>Actor</th></tr></thead><tbody><?php foreach ($historyLogs as $row): ?><tr><td><div class="fw-semibold"><?= e((string) ($row['created_at'] ?? '—')); ?></div></td><td><?= e($displayValue($row['action_name'] ?? '')); ?></td><td><?= e($displayValue($row['field_name'] ?? null)); ?></td><td class="small"><?= e((string) (($row['old_value'] ?? '') !== '' ? $row['old_value'] : '—')); ?></td><td class="small"><?= e((string) (($row['new_value'] ?? '') !== '' ? $row['new_value'] : '—')); ?></td><td><?= e((string) (($row['actor_name'] ?? '') !== '' ? $row['actor_name'] : 'System')); ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div></div></div>
</div>