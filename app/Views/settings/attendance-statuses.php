<?php declare(strict_types=1); ?>
<?php require base_path('app/Views/partials/settings-nav.php'); ?>
<div class="row g-4">
    <div class="col-12 col-xl-4">
        <div class="card content-card h-100">
            <div class="card-body p-4">
                <h5 class="mb-1">Add Attendance Status</h5>
                <p class="text-muted small mb-4">Create reusable attendance outcomes for manual attendance and future imports.</p>
                <form method="post" action="<?= e(url('/settings/attendance-statuses')); ?>" class="row g-3">
                    <?= csrf_field(); ?>
                    <div class="col-12"><label class="form-label">Status name <span class="text-danger">*</span></label><input type="text" name="name" class="form-control" maxlength="100" value="<?= e((string) old('name', '')); ?>" placeholder="Present" required></div>
                    <div class="col-md-6"><label class="form-label">Code <span class="text-danger">*</span></label><input type="text" name="code" class="form-control text-uppercase" maxlength="50" value="<?= e((string) old('code', '')); ?>" placeholder="PRESENT" required></div>
                    <div class="col-md-6"><label class="form-label">Badge color <span class="text-danger">*</span></label><select name="color_class" class="form-select" required><?php foreach (($colorOptions ?? []) as $value => $label): ?><option value="<?= e((string) $value); ?>" <?= (string) old('color_class', 'secondary') === (string) $value ? 'selected' : ''; ?>><?= e((string) $label); ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-6"><label class="form-label">Counts as present</label><select name="counts_as_present" class="form-select"><option value="0" <?= (string) old('counts_as_present', '0') === '0' ? 'selected' : ''; ?>>No</option><option value="1" <?= (string) old('counts_as_present', '0') === '1' ? 'selected' : ''; ?>>Yes</option></select></div>
                    <div class="col-md-6"><label class="form-label">Counts as absent</label><select name="counts_as_absent" class="form-select"><option value="0" <?= (string) old('counts_as_absent', '0') === '0' ? 'selected' : ''; ?>>No</option><option value="1" <?= (string) old('counts_as_absent', '0') === '1' ? 'selected' : ''; ?>>Yes</option></select></div>
                    <div class="col-12"><label class="form-label">Status</label><select name="is_active" class="form-select"><option value="1" <?= (string) old('is_active', '1') === '1' ? 'selected' : ''; ?>>Active</option><option value="0" <?= (string) old('is_active', '1') === '0' ? 'selected' : ''; ?>>Inactive</option></select></div>
                    <div class="col-12"><button type="submit" class="btn btn-primary w-100">Save Attendance Status</button></div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-8">
        <div class="card content-card">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start gap-3 mb-3"><div><h5 class="mb-1">Attendance Statuses</h5><p class="text-muted mb-0">Standardize the attendance outcomes used by manual attendance, reports, and future imports.</p></div><span class="badge text-bg-light"><?= e((string) count($statuses ?? [])); ?> total</span></div>
                <?php if (($statuses ?? []) === []): ?>
                    <div class="empty-state">No attendance statuses are available yet.</div>
                <?php else: ?>
                    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Status</th><th>Color</th><th>Present</th><th>Absent</th><th>Usage</th><th>State</th></tr></thead><tbody><?php foreach ($statuses as $status): ?><tr><td><div class="fw-semibold"><?= e((string) $status['name']); ?></div><div class="small text-muted"><?= e((string) $status['code']); ?></div></td><td><span class="badge text-bg-<?= e((string) ($status['color_class'] ?? 'secondary')); ?>"><?= e((string) ($status['color_class'] ?? 'secondary')); ?></span></td><td><?= (int) $status['counts_as_present'] === 1 ? 'Yes' : 'No'; ?></td><td><?= (int) $status['counts_as_absent'] === 1 ? 'Yes' : 'No'; ?></td><td><?= e((string) $status['usage_count']); ?></td><td><span class="badge <?= (int) $status['is_active'] === 1 ? 'text-bg-success' : 'text-bg-secondary'; ?>"><?= e((string) ((int) $status['is_active'] === 1 ? 'Active' : 'Inactive')); ?></span></td></tr><?php endforeach; ?></tbody></table></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>