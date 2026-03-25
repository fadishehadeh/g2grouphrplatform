<?php declare(strict_types=1); ?>
<?php require base_path('app/Views/partials/workflow-nav.php'); ?>

<div class="card content-card mb-4">
    <div class="card-body p-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <h5 class="mb-1">Start Offboarding</h5>
            <p class="text-muted mb-0">Create an exit workflow, checklist, and asset-return tracker for <?= e((string) $employee['first_name']); ?>.</p>
        </div>
        <a href="<?= e(url('/employees/' . $employee['id'])); ?>" class="btn btn-outline-secondary">Back to Profile</a>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card content-card h-100">
            <div class="card-body p-4">
                <h5 class="mb-3">Employee Snapshot</h5>
                <div class="mb-2"><strong>Name:</strong><br><?= e(trim((string) (($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? '')))); ?></div>
                <div class="mb-2"><strong>Code:</strong><br><?= e((string) ($employee['employee_code'] ?? '—')); ?></div>
                <div class="mb-2"><strong>Department:</strong><br><?= e((string) (($employee['department_name'] ?? '') !== '' ? $employee['department_name'] : '—')); ?></div>
                <div><strong>Designation:</strong><br><?= e((string) (($employee['designation_name'] ?? '') !== '' ? $employee['designation_name'] : '—')); ?></div>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card content-card">
            <div class="card-body p-4">
                <form method="post" action="<?= e(url('/offboarding/create/' . $employee['id'])); ?>">
                    <?= csrf_field(); ?>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Record Type *</label><select name="record_type" class="form-select" required><?php foreach (['resignation' => 'Resignation', 'termination' => 'Termination', 'retirement' => 'Retirement', 'contract_end' => 'Contract End', 'absconding' => 'Absconding', 'other' => 'Other'] as $value => $label): ?><option value="<?= e($value); ?>" <?= (string) old('record_type', 'resignation') === $value ? 'selected' : ''; ?>><?= e($label); ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-6"><label class="form-label">Initial Status *</label><select name="status" class="form-select" required><?php foreach (['draft' => 'Draft', 'pending' => 'Pending', 'in_progress' => 'In Progress'] as $value => $label): ?><option value="<?= e($value); ?>" <?= (string) old('status', 'pending') === $value ? 'selected' : ''; ?>><?= e($label); ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-4"><label class="form-label">Notice Date</label><input type="date" name="notice_date" class="form-control" value="<?= e((string) old('notice_date', '')); ?>"></div>
                        <div class="col-md-4"><label class="form-label">Exit Date *</label><input type="date" name="exit_date" class="form-control" value="<?= e((string) old('exit_date', '')); ?>" required></div>
                        <div class="col-md-4"><label class="form-label">Last Working Date</label><input type="date" name="last_working_date" class="form-control" value="<?= e((string) old('last_working_date', '')); ?>"></div>
                    </div>
                    <div class="mt-3"><label class="form-label">Reason</label><input type="text" name="reason" class="form-control" value="<?= e((string) old('reason', '')); ?>" placeholder="Short reason for the exit"></div>
                    <div class="mt-3"><label class="form-label">Remarks</label><textarea name="remarks" class="form-control" rows="3"><?= e((string) old('remarks', '')); ?></textarea></div>
                    <div class="mt-3"><label class="form-label">Checklist Tasks</label><textarea name="task_lines" class="form-control" rows="6" placeholder="Collect ID card&#10;Revoke email access&#10;Complete finance clearance"><?= e((string) old('task_lines', '')); ?></textarea><div class="form-text">Optional. Leave blank to use the default offboarding checklist.</div></div>
                    <div class="mt-3"><label class="form-label">Asset Return Items</label><textarea name="asset_lines" class="form-control" rows="4" placeholder="Laptop&#10;Access card&#10;SIM card"><?= e((string) old('asset_lines', '')); ?></textarea></div>
                    <button type="submit" class="btn btn-primary mt-4">Create Offboarding Record</button>
                </form>
            </div>
        </div>
    </div>
</div>