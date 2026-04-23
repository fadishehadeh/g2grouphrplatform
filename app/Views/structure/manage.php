<?php declare(strict_types=1); ?>
<?php require base_path('app/Views/partials/structure-nav.php'); ?>
<div class="row g-4">
    <div class="col-xl-4">
        <div class="card content-card h-100">
            <div class="card-body p-4">
                <h5 class="mb-2">Add <?= e($pageTitle ?? $title ?? 'Record'); ?></h5>
                <p class="text-muted small mb-4"><?= e($description ?? ''); ?></p>
                <?php $hasFileField = !empty(array_filter($formFields, fn($f) => ($f['type'] ?? '') === 'file')); ?>
                <form method="post" action="<?= e(url($formAction)); ?>"<?= $hasFileField ? ' enctype="multipart/form-data"' : ''; ?>>
                    <?= csrf_field(); ?>
                    <?php foreach ($formFields as $field): ?>
                        <?php $value = old($field['name'], $field['value'] ?? ''); ?>
                        <div class="mb-3">
                            <label class="form-label"><?= e($field['label']); ?><?= !empty($field['required']) ? ' *' : ''; ?></label>
                            <?php if (($field['type'] ?? 'text') === 'select'): ?>
                                <select name="<?= e($field['name']); ?>" class="form-select" <?= !empty($field['required']) ? 'required' : ''; ?>>
                                    <option value="">Select <?= e($field['label']); ?></option>
                                    <?php foreach (($field['options'] ?? []) as $optionValue => $optionLabel): ?>
                                        <option value="<?= e((string) $optionValue); ?>" <?= (string) $value === (string) $optionValue ? 'selected' : ''; ?>><?= e($optionLabel); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif (($field['type'] ?? 'text') === 'textarea'): ?>
                                <textarea name="<?= e($field['name']); ?>" class="form-control" rows="3"><?= e((string) $value); ?></textarea>
                            <?php elseif (($field['type'] ?? 'text') === 'file'): ?>
                                <input type="file" name="<?= e($field['name']); ?>" class="form-control" accept="<?= e($field['accept'] ?? '*/*'); ?>" <?= !empty($field['required']) ? 'required' : ''; ?>>
                                <?php if (!empty($field['hint'])): ?>
                                    <div class="form-text"><?= e($field['hint']); ?></div>
                                <?php endif; ?>
                            <?php else: ?>
                                <input type="<?= e($field['type'] ?? 'text'); ?>" name="<?= e($field['name']); ?>" class="form-control" value="<?= e((string) $value); ?>" <?= !empty($field['required']) ? 'required' : ''; ?> <?= !empty($field['placeholder']) ? 'placeholder="' . e($field['placeholder']) . '"' : ''; ?>>
                                <?php if (!empty($field['hint'])): ?>
                                    <div class="form-text"><?= e($field['hint']); ?></div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <button type="submit" class="btn btn-primary w-100">Save <?= e($pageTitle ?? $title ?? 'Record'); ?></button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-xl-8">
        <div class="card content-card">
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                    <div>
                        <h5 class="mb-1"><?= e($pageTitle ?? $title ?? 'Records'); ?></h5>
                        <p class="text-muted mb-0"><?= e($description ?? ''); ?></p>
                    </div>
                    <form method="get" action="<?= e(url($formAction)); ?>" class="d-flex flex-column flex-md-row gap-2">
                        <input type="text" name="q" class="form-control" placeholder="Search records..." value="<?= e((string) ($search ?? '')); ?>">
                        <?php foreach (($filters ?? []) as $filter): ?>
                            <?php if (($filter['type'] ?? 'select') === 'select'): ?>
                                <?php $filterValue = (string) ($filter['value'] ?? ''); ?>
                                <select name="<?= e($filter['name']); ?>" class="form-select">
                                    <?php foreach (($filter['options'] ?? []) as $optionValue => $optionLabel): ?>
                                        <option value="<?= e((string) $optionValue); ?>" <?= $filterValue === (string) $optionValue ? 'selected' : ''; ?>><?= e((string) $optionLabel); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <button type="submit" class="btn btn-outline-secondary">Search</button>
                    </form>
                </div>
                <?php
                    $editableSections = ['branches', 'departments', 'teams', 'job_titles', 'designations'];
                    $supportsEdit = in_array($activeSection ?? '', $editableSections, true);
                ?>
                <?php if ($items === []): ?>
                    <div class="empty-state">No records found yet for this section.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead><tr><?php foreach ($columns as $label): ?><th><?= e($label); ?></th><?php endforeach; ?><?php if (($activeSection ?? '') === 'companies' || $supportsEdit): ?><th></th><?php endif; ?></tr></thead>
                            <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <?php foreach ($columns as $key => $label): ?>
                                        <td>
                                            <?php $cell = $item[$key] ?? ''; ?>
                                            <?php if ($key === 'status'): ?>
                                                <span class="badge <?= $cell === 'active' ? 'text-bg-success' : 'text-bg-secondary'; ?>"><?= e((string) $cell); ?></span>
                                            <?php elseif ($key === 'name' && ($activeSection ?? '') === 'companies' && isset($item['id'])): ?>
                                                <div class="d-flex align-items-center gap-2">
                                                    <?php if (!empty($item['logo_path']) && is_file(base_path('public-hr/' . ltrim((string) $item['logo_path'], '/')))):  ?>
                                                        <img src="<?= e(url('/' . ltrim((string) $item['logo_path'], '/'))); ?>" alt="" style="height:28px;width:28px;object-fit:contain;border-radius:3px;flex-shrink:0;">
                                                    <?php else: ?>
                                                        <span style="height:28px;width:28px;display:inline-block;border-radius:3px;background:#f1f3f5;flex-shrink:0;"></span>
                                                    <?php endif; ?>
                                                    <a href="<?= e(url('/admin/companies/' . (int) $item['id'])); ?>" class="fw-semibold text-decoration-none"><?= e((string) $cell); ?></a>
                                                </div>
                                            <?php else: ?>
                                                <?= e((string) $cell); ?>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                    <?php if (($activeSection ?? '') === 'companies' && isset($item['id'])): ?>
                                        <td><a href="<?= e(url('/admin/companies/' . (int) $item['id'])); ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-gear"></i> Manage</a></td>
                                    <?php elseif ($supportsEdit && isset($item['id'])): ?>
                                        <td>
                                            <button type="button" class="btn btn-outline-secondary btn-sm edit-record-btn"
                                                data-id="<?= e((string) $item['id']); ?>"
                                                data-name="<?= e((string) ($item['name'] ?? '')); ?>"
                                                data-code="<?= e((string) ($item['code'] ?? '')); ?>"
                                                data-status="<?= e((string) ($item['status'] ?? 'active')); ?>"
                                                data-description="<?= e((string) ($item['description'] ?? '')); ?>"
                                                data-branch-id="<?= e((string) ($item['branch_id'] ?? '')); ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                        </td>
                                    <?php endif; ?>
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

<?php if ($supportsEdit ?? false): ?>
<div class="modal fade" id="editRecordModal" tabindex="-1" aria-labelledby="editRecordModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editRecordModalLabel">Edit Record</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" id="editRecordForm">
                <?= csrf_field(); ?>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name *</label>
                        <input type="text" name="name" id="editName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Code</label>
                        <input type="text" name="code" id="editCode" class="form-control" placeholder="Leave blank to auto-generate.">
                        <div class="form-text">Leave blank to auto-generate.</div>
                    </div>
                    <?php if (($activeSection ?? '') === 'departments' && !empty($branchOptions)): ?>
                    <div class="mb-3">
                        <label class="form-label">Branch</label>
                        <select name="branch_id" id="editBranchId" class="form-select">
                            <option value="">— No branch —</option>
                            <?php foreach ($branchOptions as $opt): ?>
                                <option value="<?= e((string) $opt['value']); ?>"><?= e((string) $opt['label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="editDescription" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" id="editStatus" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
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
document.querySelectorAll('.edit-record-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('editName').value = this.dataset.name;
        document.getElementById('editCode').value = this.dataset.code;
        document.getElementById('editStatus').value = this.dataset.status || 'active';
        var descEl = document.getElementById('editDescription');
        if (descEl) descEl.value = this.dataset.description || '';
        var branchEl = document.getElementById('editBranchId');
        if (branchEl) branchEl.value = this.dataset.branchId || '';
        document.getElementById('editRecordForm').action = '<?= e(url($formAction)); ?>/' + this.dataset.id + '/update';
        new bootstrap.Modal(document.getElementById('editRecordModal')).show();
    });
});
</script>
<?php endif; ?>