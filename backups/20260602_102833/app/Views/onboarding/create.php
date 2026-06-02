<?php declare(strict_types=1); ?>
<?php require base_path('app/Views/partials/workflow-nav.php'); ?>

<div class="card content-card mb-4">
    <div class="card-body p-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <h5 class="mb-1">Start Onboarding</h5>
                <p class="text-muted mb-0">Prepare onboarding for <?= e((string) $employee['first_name']); ?> by selecting a reusable checklist template.</p>
            </div>
            <a href="<?= e(url('/employees/' . $employee['id'])); ?>" class="btn btn-outline-secondary">Back to Profile</a>
        </div>
    </div>
</div>

<?php if ($existingRecord !== null): ?>
    <div class="alert alert-info d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>This employee already has an onboarding record in <strong><?= e(ucwords(str_replace('_', ' ', (string) $existingRecord['status']))); ?></strong> status.</div>
        <a href="<?= e(url('/onboarding/' . $existingRecord['id'])); ?>" class="btn btn-sm btn-outline-primary">View Existing Record</a>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card content-card h-100">
            <div class="card-body p-4">
                <h5 class="mb-3">Employee Snapshot</h5>
                <div class="mb-2"><strong>Name:</strong><br><?= e(trim((string) (($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? '')))); ?></div>
                <div class="mb-2"><strong>Code:</strong><br><?= e((string) ($employee['employee_code'] ?? '—')); ?></div>
                <div class="mb-2"><strong>Department:</strong><br><?= e((string) (($employee['department_name'] ?? '') !== '' ? $employee['department_name'] : '—')); ?></div>
                <div><strong>Joining Date:</strong><br><?= e((string) (($employee['joining_date'] ?? '') !== '' ? $employee['joining_date'] : '—')); ?></div>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card content-card">
            <div class="card-body p-4">
                <?php if (($templates ?? []) === []): ?>
                    <div class="empty-state">No active onboarding templates are available yet. Create one first from the templates page.</div>
                    <a href="<?= e(url('/onboarding/templates')); ?>" class="btn btn-outline-primary mt-3">Go to Templates</a>
                <?php else: ?>
                    <form method="post" action="<?= e(url('/onboarding/create/' . $employee['id'])); ?>">
                        <?= csrf_field(); ?>
                        <div class="mb-3"><label class="form-label">Template *</label><select name="template_id" class="form-select" required <?php if ($existingRecord !== null): ?>disabled<?php endif; ?>><option value="">Select template</option><?php foreach ($templates as $template): ?><option value="<?= e((string) $template['id']); ?>" <?= (string) old('template_id', '') === (string) $template['id'] ? 'selected' : ''; ?>><?= e((string) $template['name']); ?></option><?php endforeach; ?></select></div>
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">Start Date</label><input type="date" name="start_date" class="form-control" value="<?= e((string) old('start_date', (string) ($employee['joining_date'] ?? ''))); ?>" <?php if ($existingRecord !== null): ?>disabled<?php endif; ?>></div>
                            <div class="col-md-6"><label class="form-label">Due Date</label><input type="date" name="due_date" class="form-control" value="<?= e((string) old('due_date', '')); ?>" <?php if ($existingRecord !== null): ?>disabled<?php endif; ?>></div>
                        </div>
                        <?php if ($existingRecord === null): ?><button type="submit" class="btn btn-primary mt-4">Create Onboarding Record</button><?php endif; ?>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>