<?php declare(strict_types=1); ?>
<?php require base_path('app/Views/partials/workflow-nav.php'); ?>
<?php $statusClasses = ['pending' => 'text-bg-secondary', 'in_progress' => 'text-bg-primary', 'completed' => 'text-bg-success', 'cancelled' => 'text-bg-danger']; ?>

<div class="card content-card mb-4">
    <div class="card-body p-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <h5 class="mb-1">Onboarding Records</h5>
            <p class="text-muted mb-0">Track employee onboarding progress and complete checklist tasks from a single queue.</p>
        </div>
        <a href="<?= e(url('/onboarding/templates')); ?>" class="btn btn-outline-primary">Manage Templates</a>
    </div>
</div>

<div class="card content-card">
    <div class="card-body p-4">
        <form method="get" action="<?= e(url('/onboarding')); ?>" class="row g-3 mb-4">
            <div class="col-md-8"><input type="text" name="q" class="form-control" placeholder="Search by employee code, name, or template..." value="<?= e((string) ($search ?? '')); ?>"></div>
            <div class="col-md-2"><select name="status" class="form-select"><?php foreach (['all' => 'All statuses', 'pending' => 'Pending', 'in_progress' => 'In Progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $value => $label): ?><option value="<?= e($value); ?>" <?= (string) ($status ?? 'all') === $value ? 'selected' : ''; ?>><?= e($label); ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2 d-grid"><button type="submit" class="btn btn-outline-secondary">Filter</button></div>
        </form>

        <?php if (($records ?? []) === []): ?>
            <div class="empty-state">No onboarding records matched the selected filters yet. Start onboarding from an employee profile.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Employee</th><th>Template</th><th>Start</th><th>Due</th><th>Status</th><th>Progress</th><th>Tasks</th><th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($records as $record): ?>
                        <tr>
                            <td><div class="fw-semibold"><?= e((string) $record['employee_name']); ?></div><div class="small text-muted"><?= e((string) $record['employee_code']); ?></div></td>
                            <td><?= e((string) (($record['template_name'] ?? '') !== '' ? $record['template_name'] : 'Manual')); ?></td>
                            <td><?= e((string) (($record['start_date'] ?? '') !== '' ? $record['start_date'] : '—')); ?></td>
                            <td><?= e((string) (($record['due_date'] ?? '') !== '' ? $record['due_date'] : '—')); ?></td>
                            <td><span class="badge <?= e($statusClasses[(string) $record['status']] ?? 'text-bg-secondary'); ?>"><?= e(ucwords(str_replace('_', ' ', (string) $record['status']))); ?></span></td>
                            <td><div class="fw-semibold"><?= e(number_format((float) ($record['progress_percent'] ?? 0), 0)); ?>%</div></td>
                            <td><?= e((string) ($record['done_tasks'] ?? 0)); ?>/<?= e((string) ($record['total_tasks'] ?? 0)); ?></td>
                            <td class="text-end"><a href="<?= e(url('/onboarding/' . $record['id'])); ?>" class="btn btn-sm btn-outline-primary">View</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>