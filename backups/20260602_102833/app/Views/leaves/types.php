<?php declare(strict_types=1); ?>
<?php require base_path('app/Views/partials/leave-nav.php'); ?>
<div class="row g-4">
    <div class="col-xl-4">
        <div class="card content-card h-100">
            <div class="card-body p-4">
                <h5 class="mb-2">Add Leave Type</h5>
                <p class="text-muted small mb-4">Configure the essential rules used during leave request validation and approvals.</p>
                <form method="post" action="<?= e(url('/admin/leave/types')); ?>">
                    <?= csrf_field(); ?>
                    <div class="mb-3"><label class="form-label">Name *</label><input type="text" name="name" class="form-control" value="<?= e((string) old('name', '')); ?>" required></div>
                    <div class="mb-3"><label class="form-label">Code</label><input type="text" name="code" class="form-control" value="<?= e((string) old('code', '')); ?>" placeholder="Auto-generated"><div class="form-text">Leave blank to auto-generate.</div></div>
                    <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3"><?= e((string) old('description', '')); ?></textarea></div>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Paid Leave</label><select name="is_paid" class="form-select"><?php foreach ([1 => 'Yes', 0 => 'No'] as $value => $label): ?><option value="<?= e((string) $value); ?>" <?= (string) old('is_paid', '1') === (string) $value ? 'selected' : ''; ?>><?= e($label); ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-6"><label class="form-label">Uses Balance</label><select name="requires_balance" class="form-select"><?php foreach ([1 => 'Yes', 0 => 'No'] as $value => $label): ?><option value="<?= e((string) $value); ?>" <?= (string) old('requires_balance', '1') === (string) $value ? 'selected' : ''; ?>><?= e($label); ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-6"><label class="form-label">Attachment Required</label><select name="requires_attachment" class="form-select"><?php foreach ([0 => 'No', 1 => 'Yes'] as $value => $label): ?><option value="<?= e((string) $value); ?>" <?= (string) old('requires_attachment', '0') === (string) $value ? 'selected' : ''; ?>><?= e($label); ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-6"><label class="form-label">HR Approval Required</label><select name="requires_hr_approval" class="form-select"><?php foreach ([0 => 'No', 1 => 'Yes'] as $value => $label): ?><option value="<?= e((string) $value); ?>" <?= (string) old('requires_hr_approval', '0') === (string) $value ? 'selected' : ''; ?>><?= e($label); ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-6"><label class="form-label">Allow Half Day</label><select name="allow_half_day" class="form-select"><?php foreach ([0 => 'No', 1 => 'Yes'] as $value => $label): ?><option value="<?= e((string) $value); ?>" <?= (string) old('allow_half_day', '0') === (string) $value ? 'selected' : ''; ?>><?= e($label); ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-6"><label class="form-label">Carry Forward</label><select name="carry_forward_allowed" class="form-select"><?php foreach ([0 => 'No', 1 => 'Yes'] as $value => $label): ?><option value="<?= e((string) $value); ?>" <?= (string) old('carry_forward_allowed', '0') === (string) $value ? 'selected' : ''; ?>><?= e($label); ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-6"><label class="form-label">Default Days</label><input type="number" step="0.5" min="0" name="default_days" class="form-control" value="<?= e((string) old('default_days', '0')); ?>"></div>
                        <div class="col-md-6"><label class="form-label">Carry Forward Limit</label><input type="number" step="0.5" min="0" name="carry_forward_limit" class="form-control" value="<?= e((string) old('carry_forward_limit', '0')); ?>"></div>
                        <div class="col-md-6"><label class="form-label">Notice Days Required</label><input type="number" min="0" name="notice_days_required" class="form-control" value="<?= e((string) old('notice_days_required', '0')); ?>"></div>
                        <div class="col-md-6"><label class="form-label">Max Days Per Request</label><input type="number" step="0.5" min="0" name="max_days_per_request" class="form-control" value="<?= e((string) old('max_days_per_request', '')); ?>"></div>
                        <div class="col-12"><label class="form-label">Status</label><select name="status" class="form-select"><?php foreach (['active' => 'Active', 'inactive' => 'Inactive'] as $value => $label): ?><option value="<?= e($value); ?>" <?= (string) old('status', 'active') === $value ? 'selected' : ''; ?>><?= e($label); ?></option><?php endforeach; ?></select></div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 mt-4">Save Leave Type</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-xl-8">
        <div class="card content-card">
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                    <div>
                        <h5 class="mb-1">Configured Leave Types</h5>
                        <p class="text-muted mb-0">Core leave policy definitions available to employees.</p>
                    </div>
                    <form method="get" action="<?= e(url('/admin/leave/types')); ?>" class="d-flex gap-2">
                        <input type="text" name="q" class="form-control" placeholder="Search leave types..." value="<?= e((string) ($search ?? '')); ?>">
                        <button type="submit" class="btn btn-outline-secondary">Search</button>
                    </form>
                </div>
                <?php if (($leaveTypes ?? []) === []): ?>
                    <div class="empty-state">No leave types found for the current search.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Name</th><th>Code</th><th>Paid</th><th>Balance</th><th>Attachment</th><th>HR Approval</th><th>Half Day</th><th>Default Days</th><th>Status</th><th></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($leaveTypes as $leaveType): ?>
                                <tr>
                                    <td><?= e((string) $leaveType['name']); ?></td>
                                    <td><?= e((string) $leaveType['code']); ?></td>
                                    <td><?= e((string) ((int) $leaveType['is_paid'] === 1 ? 'Yes' : 'No')); ?></td>
                                    <td><?= e((string) ((int) $leaveType['requires_balance'] === 1 ? 'Yes' : 'No')); ?></td>
                                    <td><?= e((string) ((int) $leaveType['requires_attachment'] === 1 ? 'Yes' : 'No')); ?></td>
                                    <td><?= e((string) ((int) $leaveType['requires_hr_approval'] === 1 ? 'Yes' : 'No')); ?></td>
                                    <td><?= e((string) ((int) $leaveType['allow_half_day'] === 1 ? 'Yes' : 'No')); ?></td>
                                    <td><?= e((string) $leaveType['default_days']); ?></td>
                                    <td><span class="badge <?= ($leaveType['status'] ?? '') === 'active' ? 'text-bg-success' : 'text-bg-secondary'; ?>"><?= e((string) $leaveType['status']); ?></span></td>
                                    <td><button type="button" class="btn btn-outline-secondary btn-sm edit-type-btn" data-id="<?= e((string) $leaveType['id']); ?>" data-name="<?= e((string) $leaveType['name']); ?>" data-code="<?= e((string) $leaveType['code']); ?>" data-status="<?= e((string) $leaveType['status']); ?>"><i class="bi bi-pencil"></i></button></td>
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

<div class="modal fade" id="editTypeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Leave Type</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" id="editTypeForm">
                <?= csrf_field(); ?>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Name *</label><input type="text" name="name" id="editTypeName" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Code *</label><input type="text" name="code" id="editTypeCode" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Status</label><select name="status" id="editTypeStatus" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.querySelectorAll('.edit-type-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('editTypeName').value = this.dataset.name;
        document.getElementById('editTypeCode').value = this.dataset.code;
        document.getElementById('editTypeStatus').value = this.dataset.status || 'active';
        document.getElementById('editTypeForm').action = '<?= e(url('/admin/leave/types')); ?>/' + this.dataset.id + '/update';
        new bootstrap.Modal(document.getElementById('editTypeModal')).show();
    });
});
</script>