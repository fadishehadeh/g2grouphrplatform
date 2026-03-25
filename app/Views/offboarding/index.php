<?php declare(strict_types=1); ?>
<?php require base_path('app/Views/partials/workflow-nav.php'); ?>
<?php $statusClasses = ['draft' => 'text-bg-secondary', 'pending' => 'text-bg-secondary', 'in_progress' => 'text-bg-primary', 'completed' => 'text-bg-success', 'cancelled' => 'text-bg-danger']; ?>
<?php $clearanceClasses = ['pending' => 'text-bg-secondary', 'partial' => 'text-bg-warning', 'cleared' => 'text-bg-success']; ?>

<div class="card content-card mb-4">
    <div class="card-body p-4">
        <h5 class="mb-1">Offboarding Records</h5>
        <p class="text-muted mb-0">Monitor employee exits, asset returns, and departmental clearance in one place.</p>
    </div>
</div>

<div class="card content-card">
    <div class="card-body p-4">
        <form method="get" action="<?= e(url('/offboarding')); ?>" class="row g-3 mb-4">
            <div class="col-md-8"><input type="text" name="q" class="form-control" placeholder="Search by employee, code, or reason..." value="<?= e((string) ($search ?? '')); ?>"></div>
            <div class="col-md-2"><select name="status" class="form-select"><?php foreach (['all' => 'All statuses', 'draft' => 'Draft', 'pending' => 'Pending', 'in_progress' => 'In Progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $value => $label): ?><option value="<?= e($value); ?>" <?= (string) ($status ?? 'all') === $value ? 'selected' : ''; ?>><?= e($label); ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2 d-grid"><button type="submit" class="btn btn-outline-secondary">Filter</button></div>
        </form>

        <?php if (($records ?? []) === []): ?>
            <div class="empty-state">No offboarding records matched the selected filters yet. Start offboarding from an employee profile.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Employee</th><th>Type</th><th>Exit Date</th><th>Status</th><th>Clearance</th><th>Tasks</th><th>Assets</th><th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($records as $record): ?>
                        <tr>
                            <td><div class="fw-semibold"><?= e((string) $record['employee_name']); ?></div><div class="small text-muted"><?= e((string) $record['employee_code']); ?></div></td>
                            <td><?= e(ucwords(str_replace('_', ' ', (string) $record['record_type']))); ?></td>
                            <td><div><?= e((string) $record['exit_date']); ?></div><div class="small text-muted">Last day <?= e((string) (($record['last_working_date'] ?? '') !== '' ? $record['last_working_date'] : '—')); ?></div></td>
                            <td><span class="badge <?= e($statusClasses[(string) $record['status']] ?? 'text-bg-secondary'); ?>"><?= e(ucwords(str_replace('_', ' ', (string) $record['status']))); ?></span></td>
                            <td><span class="badge <?= e($clearanceClasses[(string) $record['clearance_status']] ?? 'text-bg-secondary'); ?>"><?= e(ucwords((string) $record['clearance_status'])); ?></span></td>
                            <td><?= e((string) ($record['total_tasks'] ?? 0)); ?></td>
                            <td><?= e((string) ($record['total_assets'] ?? 0)); ?></td>
                            <td class="text-end"><a href="<?= e(url('/offboarding/' . $record['id'])); ?>" class="btn btn-sm btn-outline-primary">View</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>