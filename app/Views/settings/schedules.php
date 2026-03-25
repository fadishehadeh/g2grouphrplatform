<?php declare(strict_types=1); ?>
<?php require base_path('app/Views/partials/settings-nav.php'); ?>
<div class="row g-4">
    <div class="col-12 col-xl-5">
        <div class="card content-card h-100">
            <div class="card-body p-4">
                <h5 class="mb-1">Add Work Schedule</h5>
                <p class="text-muted small mb-4">Define weekly hour targets and day-by-day shift mappings for one company at a time.</p>
                <form method="post" action="<?= e(url('/settings/schedules')); ?>" class="row g-3">
                    <?= csrf_field(); ?>
                    <div class="col-12"><label class="form-label">Company <span class="text-danger">*</span></label><select name="company_id" class="form-select" required><option value="">Select company</option><?php foreach (($companies ?? []) as $company): ?><option value="<?= e((string) $company['id']); ?>" <?= (string) old('company_id', '') === (string) $company['id'] ? 'selected' : ''; ?>><?= e((string) $company['name']); ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-8"><label class="form-label">Schedule name <span class="text-danger">*</span></label><input type="text" name="name" class="form-control" maxlength="150" value="<?= e((string) old('name', '')); ?>" placeholder="Standard Monday-Friday" required></div>
                    <div class="col-md-4"><label class="form-label">Code <span class="text-danger">*</span></label><input type="text" name="code" class="form-control text-uppercase" maxlength="50" value="<?= e((string) old('code', '')); ?>" placeholder="STD_MF" required></div>
                    <div class="col-md-6"><label class="form-label">Weekly hours <span class="text-danger">*</span></label><input type="number" name="weekly_hours" class="form-control" min="0.01" max="168" step="0.01" value="<?= e((string) old('weekly_hours', '40')); ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Status</label><select name="is_active" class="form-select"><option value="1" <?= (string) old('is_active', '1') === '1' ? 'selected' : ''; ?>>Active</option><option value="0" <?= (string) old('is_active', '1') === '0' ? 'selected' : ''; ?>>Inactive</option></select></div>
                    <div class="col-12">
                        <div class="border rounded p-3">
                            <div class="fw-semibold mb-2">Day mapping</div>
                            <div class="small text-muted mb-3">Mark each day as working or off. Working days must use a shift from the selected company.</div>
                            <?php foreach (($dayNames ?? []) as $dayNumber => $dayLabel): ?>
                                <div class="row g-2 align-items-center mb-2">
                                    <div class="col-sm-4"><label class="form-label mb-sm-0"><?= e((string) $dayLabel); ?></label></div>
                                    <div class="col-sm-3"><select name="day_<?= e((string) $dayNumber); ?>_is_working" class="form-select form-select-sm"><option value="1" <?= (string) old('day_' . $dayNumber . '_is_working', in_array($dayNumber, [1, 2, 3, 4, 5], true) ? '1' : '0') === '1' ? 'selected' : ''; ?>>Working</option><option value="0" <?= (string) old('day_' . $dayNumber . '_is_working', in_array($dayNumber, [1, 2, 3, 4, 5], true) ? '1' : '0') === '0' ? 'selected' : ''; ?>>Off</option></select></div>
                                    <div class="col-sm-5"><select name="day_<?= e((string) $dayNumber); ?>_shift_id" class="form-select form-select-sm"><option value="">Select shift</option><?php foreach (($shiftOptions ?? []) as $shift): ?><option value="<?= e((string) $shift['id']); ?>" <?= (string) old('day_' . $dayNumber . '_shift_id', '') === (string) $shift['id'] ? 'selected' : ''; ?>><?= e((string) $shift['name']); ?></option><?php endforeach; ?></select></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-12"><button type="submit" class="btn btn-primary w-100">Save Schedule</button></div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-7">
        <div class="card content-card">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start gap-3 mb-3"><div><h5 class="mb-1">Work Schedules</h5><p class="text-muted mb-0">Monitor available schedules, day mappings, and how widely they are assigned across employees.</p></div><span class="badge text-bg-light"><?= e((string) count($schedules ?? [])); ?> total</span></div>
                <?php if (($schedules ?? []) === []): ?>
                    <div class="empty-state">No work schedules have been configured yet.</div>
                <?php else: ?>
                    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Schedule</th><th>Company</th><th>Weekly Hours</th><th>Days</th><th>Assignments</th><th>Status</th></tr></thead><tbody><?php foreach ($schedules as $schedule): ?><tr><td><div class="fw-semibold"><?= e((string) $schedule['name']); ?></div><div class="small text-muted"><?= e((string) $schedule['code']); ?></div></td><td><?= e((string) ($schedule['company_name'] ?? '—')); ?></td><td><?= e((string) $schedule['weekly_hours']); ?></td><td><div><?= e((string) $schedule['working_day_count']); ?> working day(s)</div><div class="small text-muted"><?= e((string) ($schedule['day_summary'] ?? '—')); ?></div></td><td><?= e((string) $schedule['assignment_count']); ?></td><td><span class="badge <?= (int) $schedule['is_active'] === 1 ? 'text-bg-success' : 'text-bg-secondary'; ?>"><?= e((string) ((int) $schedule['is_active'] === 1 ? 'Active' : 'Inactive')); ?></span></td></tr><?php endforeach; ?></tbody></table></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>