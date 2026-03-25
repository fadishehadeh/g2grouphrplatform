<?php declare(strict_types=1); ?>
<?php require base_path('app/Views/partials/leave-nav.php'); ?>
<div class="row g-4">
    <div class="col-xl-4">
        <div class="card content-card h-100">
            <div class="card-body p-4">
                <h5 class="mb-2">Add Holiday</h5>
                <p class="text-muted small mb-4">Create public, company, or branch holidays used for leave planning and calendar visibility.</p>
                <form method="post" action="<?= e(url('/admin/leave/holidays')); ?>">
                    <?= csrf_field(); ?>
                    <div class="mb-3"><label class="form-label">Company *</label><select name="company_id" class="form-select" required><option value="">Select company</option><?php foreach (($companies ?? []) as $company): ?><option value="<?= e((string) $company['id']); ?>" <?= (string) old('company_id', '') === (string) $company['id'] ? 'selected' : ''; ?>><?= e((string) $company['name']); ?></option><?php endforeach; ?></select></div>
                    <div class="mb-3"><label class="form-label">Branch</label><select name="branch_id" class="form-select"><option value="">All / none</option><?php foreach (($branches ?? []) as $branch): ?><option value="<?= e((string) $branch['id']); ?>" <?= (string) old('branch_id', '') === (string) $branch['id'] ? 'selected' : ''; ?>><?= e((string) ($branch['company_name'] . ' — ' . $branch['name'])); ?></option><?php endforeach; ?></select><div class="form-text">Required when holiday type is set to Branch.</div></div>
                    <div class="mb-3"><label class="form-label">Holiday Name *</label><input type="text" name="name" class="form-control" value="<?= e((string) old('name', '')); ?>" maxlength="150" required></div>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Date *</label><input type="date" name="holiday_date" class="form-control" value="<?= e((string) old('holiday_date', '')); ?>" required></div>
                        <div class="col-md-6"><label class="form-label">Type *</label><select name="holiday_type" class="form-select" required><?php foreach (['public' => 'Public', 'company' => 'Company', 'branch' => 'Branch'] as $value => $label): ?><option value="<?= e($value); ?>" <?= (string) old('holiday_type', 'public') === $value ? 'selected' : ''; ?>><?= e($label); ?></option><?php endforeach; ?></select></div>
                        <div class="col-12"><label class="form-label">Recurring Annually</label><select name="is_recurring" class="form-select"><?php foreach ([1 => 'Yes', 0 => 'No'] as $value => $label): ?><option value="<?= e((string) $value); ?>" <?= (string) old('is_recurring', '1') === (string) $value ? 'selected' : ''; ?>><?= e($label); ?></option><?php endforeach; ?></select></div>
                    </div>
                    <div class="mt-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3" maxlength="255"><?= e((string) old('description', '')); ?></textarea></div>
                    <button type="submit" class="btn btn-primary w-100 mt-4">Save Holiday</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-xl-8">
        <div class="card content-card">
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                    <div>
                        <h5 class="mb-1">Holiday Calendar</h5>
                        <p class="text-muted mb-0">Review configured holidays across companies and branches.</p>
                    </div>
                    <form method="get" action="<?= e(url('/admin/leave/holidays')); ?>" class="d-flex gap-2">
                        <input type="text" name="q" class="form-control" placeholder="Search holidays..." value="<?= e((string) ($search ?? '')); ?>">
                        <button type="submit" class="btn btn-outline-secondary">Search</button>
                    </form>
                </div>
                <?php if (($holidays ?? []) === []): ?>
                    <div class="empty-state">No holidays found for the current search.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Name</th><th>Date</th><th>Type</th><th>Company</th><th>Branch</th><th>Recurring</th><th>Description</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($holidays as $holiday): ?>
                                <tr>
                                    <td><?= e((string) $holiday['name']); ?></td>
                                    <td><?= e((string) $holiday['holiday_date']); ?></td>
                                    <td><span class="badge text-bg-light"><?= e(ucfirst((string) $holiday['holiday_type'])); ?></span></td>
                                    <td><?= e((string) $holiday['company_name']); ?></td>
                                    <td><?= e((string) (($holiday['branch_name'] ?? '') !== '' ? $holiday['branch_name'] : 'All branches')); ?></td>
                                    <td><?= e((int) ($holiday['is_recurring'] ?? 0) === 1 ? 'Yes' : 'No'); ?></td>
                                    <td><?= e((string) (($holiday['description'] ?? '') !== '' ? $holiday['description'] : '—')); ?></td>
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