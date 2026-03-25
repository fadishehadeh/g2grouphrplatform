<?php declare(strict_types=1); ?>
<?php require base_path('app/Views/partials/settings-nav.php'); ?>
<div class="row g-4">
    <div class="col-12 col-xl-4">
        <div class="card content-card h-100">
            <div class="card-body p-4">
                <h5 class="mb-1">Add Shift</h5>
                <p class="text-muted small mb-4">Create reusable shift definitions per company for schedules and attendance processing.</p>
                <form method="post" action="<?= e(url('/settings/shifts')); ?>" class="row g-3">
                    <?= csrf_field(); ?>
                    <div class="col-12"><label class="form-label">Company <span class="text-danger">*</span></label><select name="company_id" class="form-select" required><option value="">Select company</option><?php foreach (($companies ?? []) as $company): ?><option value="<?= e((string) $company['id']); ?>" <?= (string) old('company_id', '') === (string) $company['id'] ? 'selected' : ''; ?>><?= e((string) $company['name']); ?></option><?php endforeach; ?></select></div>
                    <div class="col-12"><label class="form-label">Shift name <span class="text-danger">*</span></label><input type="text" name="name" class="form-control" maxlength="150" value="<?= e((string) old('name', '')); ?>" placeholder="General Shift" required></div>
                    <div class="col-md-6"><label class="form-label">Code <span class="text-danger">*</span></label><input type="text" name="code" class="form-control text-uppercase" maxlength="50" value="<?= e((string) old('code', '')); ?>" placeholder="GEN_SHIFT" required></div>
                    <div class="col-md-6"><label class="form-label">Late grace (min)</label><input type="number" name="late_grace_minutes" class="form-control" min="0" value="<?= e((string) old('late_grace_minutes', '0')); ?>"></div>
                    <div class="col-md-6"><label class="form-label">Start time <span class="text-danger">*</span></label><input type="time" name="start_time" class="form-control" value="<?= e((string) old('start_time', '')); ?>" required></div>
                    <div class="col-md-6"><label class="form-label">End time <span class="text-danger">*</span></label><input type="time" name="end_time" class="form-control" value="<?= e((string) old('end_time', '')); ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Half day minutes</label><input type="number" name="half_day_minutes" class="form-control" min="0" value="<?= e((string) old('half_day_minutes', '')); ?>" placeholder="240"></div>
                    <div class="col-md-6"><label class="form-label">Shift type</label><select name="is_night_shift" class="form-select"><option value="0" <?= (string) old('is_night_shift', '0') === '0' ? 'selected' : ''; ?>>Day shift</option><option value="1" <?= (string) old('is_night_shift', '0') === '1' ? 'selected' : ''; ?>>Night shift</option></select></div>
                    <div class="col-12"><label class="form-label">Status</label><select name="is_active" class="form-select"><option value="1" <?= (string) old('is_active', '1') === '1' ? 'selected' : ''; ?>>Active</option><option value="0" <?= (string) old('is_active', '1') === '0' ? 'selected' : ''; ?>>Inactive</option></select></div>
                    <div class="col-12"><button type="submit" class="btn btn-primary w-100">Save Shift</button></div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-8">
        <div class="card content-card">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start gap-3 mb-3"><div><h5 class="mb-1">Shift Library</h5><p class="text-muted mb-0">Review configured shifts, linked schedules, and active employee assignments.</p></div><span class="badge text-bg-light"><?= e((string) count($shifts ?? [])); ?> total</span></div>
                <?php if (($shifts ?? []) === []): ?>
                    <div class="empty-state">No shifts have been configured yet.</div>
                <?php else: ?>
                    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Shift</th><th>Company</th><th>Timing</th><th>Rules</th><th>Usage</th><th>Status</th></tr></thead><tbody><?php foreach ($shifts as $shift): ?><tr><td><div class="fw-semibold"><?= e((string) $shift['name']); ?></div><div class="small text-muted"><?= e((string) $shift['code']); ?></div></td><td><?= e((string) ($shift['company_name'] ?? '—')); ?></td><td><div><?= e((string) $shift['start_time']); ?> → <?= e((string) $shift['end_time']); ?></div><?php if ((int) $shift['is_night_shift'] === 1): ?><div class="small text-muted">Night shift</div><?php endif; ?></td><td><div>Grace: <?= e((string) $shift['late_grace_minutes']); ?> min</div><div class="small text-muted">Half day: <?= e((string) (($shift['half_day_minutes'] ?? null) !== null ? $shift['half_day_minutes'] : '—')); ?> min</div></td><td><div><?= e((string) $shift['schedule_count']); ?> schedule(s)</div><div class="small text-muted"><?= e((string) $shift['assignment_count']); ?> active assignment(s)</div></td><td><span class="badge <?= (int) $shift['is_active'] === 1 ? 'text-bg-success' : 'text-bg-secondary'; ?>"><?= e((string) ((int) $shift['is_active'] === 1 ? 'Active' : 'Inactive')); ?></span></td></tr><?php endforeach; ?></tbody></table></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>