<?php declare(strict_types=1); ?>
<?php require base_path('app/Views/partials/leave-nav.php'); ?>
<div class="row g-4">
    <div class="col-xl-4">
        <div class="card content-card h-100">
            <div class="card-body p-4">
                <h5 class="mb-2">Add Weekend Setting</h5>
                <p class="text-muted small mb-4">Configure which weekdays count as weekends at company level or for a specific branch.</p>
                <form method="post" action="<?= e(url('/admin/leave/weekends')); ?>">
                    <?= csrf_field(); ?>
                    <div class="mb-3"><label class="form-label">Company *</label><select name="company_id" class="form-select" required><option value="">Select company</option><?php foreach (($companies ?? []) as $company): ?><option value="<?= e((string) $company['id']); ?>" <?= (string) old('company_id', '') === (string) $company['id'] ? 'selected' : ''; ?>><?= e((string) $company['name']); ?></option><?php endforeach; ?></select></div>
                    <div class="mb-3"><label class="form-label">Branch</label><select name="branch_id" class="form-select"><option value="">All / none</option><?php foreach (($branches ?? []) as $branch): ?><option value="<?= e((string) $branch['id']); ?>" <?= (string) old('branch_id', '') === (string) $branch['id'] ? 'selected' : ''; ?>><?= e((string) ($branch['company_name'] . ' — ' . $branch['name'])); ?></option><?php endforeach; ?></select><div class="form-text">Leave blank to apply the setting across all branches in the company.</div></div>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Day *</label><select name="day_of_week" class="form-select" required><option value="">Select day</option><?php foreach (($weekendDays ?? []) as $value => $label): ?><option value="<?= e((string) $value); ?>" <?= (string) old('day_of_week', '') === (string) $value ? 'selected' : ''; ?>><?= e((string) $label); ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-6"><label class="form-label">Weekend Flag *</label><select name="is_weekend" class="form-select" required><?php foreach ([1 => 'Weekend', 0 => 'Working day'] as $value => $label): ?><option value="<?= e((string) $value); ?>" <?= (string) old('is_weekend', '1') === (string) $value ? 'selected' : ''; ?>><?= e((string) $label); ?></option><?php endforeach; ?></select></div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 mt-4">Save Weekend Setting</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-xl-8">
        <div class="card content-card">
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                    <div>
                        <h5 class="mb-1">Weekend Settings</h5>
                        <p class="text-muted mb-0">Review which weekdays are treated as weekends for each company or branch.</p>
                    </div>
                    <form method="get" action="<?= e(url('/admin/leave/weekends')); ?>" class="d-flex gap-2">
                        <input type="text" name="q" class="form-control" placeholder="Search settings..." value="<?= e((string) ($search ?? '')); ?>">
                        <button type="submit" class="btn btn-outline-secondary">Search</button>
                    </form>
                </div>
                <?php if (($weekendSettings ?? []) === []): ?>
                    <div class="empty-state">No weekend settings found for the current search.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Company</th><th>Branch</th><th>Day</th><th>Weekend</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($weekendSettings as $setting): ?>
                                <tr>
                                    <td><?= e((string) $setting['company_name']); ?></td>
                                    <td><?= e((string) (($setting['branch_name'] ?? '') !== '' ? $setting['branch_name'] : 'All branches')); ?></td>
                                    <td><?= e((string) $setting['day_name']); ?></td>
                                    <td><span class="badge <?= (int) ($setting['is_weekend'] ?? 0) === 1 ? 'text-bg-success' : 'text-bg-secondary'; ?>"><?= e((int) ($setting['is_weekend'] ?? 0) === 1 ? 'Weekend' : 'Working day'); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>