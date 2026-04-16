<?php declare(strict_types=1); ?>
<?php require base_path('app/Views/partials/document-nav.php'); ?>

<div class="card content-card mb-4">
    <div class="card-body p-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <h5 class="mb-1">Edit Document Details</h5>
            <p class="text-muted mb-0">
                <?= e((string) $document['employee_name']); ?> · <?= e((string) $document['employee_code']); ?> · <?= e((string) $document['original_file_name']); ?>
            </p>
        </div>
        <a href="<?= e(url('/documents')); ?>" class="btn btn-outline-secondary">Back to Document Center</a>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-7">
        <div class="card content-card">
            <div class="card-body p-4">
                <form method="post" action="<?= e(url('/documents/' . (int) $document['id'] . '/edit')); ?>">
                    <?= csrf_field(); ?>

                    <div class="mb-3">
                        <label class="form-label">Category *</label>
                        <select name="category_id" class="form-select" required>
                            <option value="">Select category</option>
                            <?php foreach (($categories ?? []) as $category): ?>
                                <option value="<?= e((string) $category['id']); ?>"
                                    <?= (string) old('category_id', (string) $document['category_id']) === (string) $category['id'] ? 'selected' : ''; ?>>
                                    <?= e((string) $category['name']); ?> (<?= e((string) $category['code']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Title *</label>
                        <input type="text" name="title" class="form-control"
                               value="<?= e((string) old('title', (string) $document['title'])); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Document Number</label>
                        <input type="text" name="document_number" class="form-control"
                               value="<?= e((string) old('document_number', (string) ($document['document_number'] ?? ''))); ?>">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Issue Date</label>
                            <input type="date" name="issue_date" class="form-control"
                                   value="<?= e((string) old('issue_date', (string) ($document['issue_date'] ?? ''))); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Expiry Date</label>
                            <input type="date" name="expiry_date" class="form-control"
                                   value="<?= e((string) old('expiry_date', (string) ($document['expiry_date'] ?? ''))); ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Visibility *</label>
                        <select name="visibility_scope" class="form-select" required>
                            <option value="employee" <?= (string) old('visibility_scope', (string) $document['visibility_scope']) === 'employee' ? 'selected' : ''; ?>>Employee only</option>
                            <option value="manager"  <?= (string) old('visibility_scope', (string) $document['visibility_scope']) === 'manager'  ? 'selected' : ''; ?>>Manager + above</option>
                            <option value="hr"       <?= (string) old('visibility_scope', (string) $document['visibility_scope']) === 'hr'       ? 'selected' : ''; ?>>HR + Admin only</option>
                            <option value="admin"    <?= (string) old('visibility_scope', (string) $document['visibility_scope']) === 'admin'    ? 'selected' : ''; ?>>Admin only</option>
                            <?php if (($viewerRoleCode ?? '') === 'hr_only'): ?>
                            <option value="hr_only"  <?= (string) old('visibility_scope', (string) $document['visibility_scope']) === 'hr_only'  ? 'selected' : ''; ?>>HR Only — Confidential</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="active"  <?= (string) old('status', (string) ($document['status'] ?? 'active')) === 'active'  ? 'selected' : ''; ?>>Active</option>
                            <option value="expired" <?= (string) old('status', (string) ($document['status'] ?? 'active')) === 'expired' ? 'selected' : ''; ?>>Expired</option>
                            <option value="revoked" <?= (string) old('status', (string) ($document['status'] ?? 'active')) === 'revoked' ? 'selected' : ''; ?>>Revoked</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Save Changes</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card content-card">
            <div class="card-body p-4">
                <h6 class="mb-3">Document File</h6>
                <div class="mb-2"><span class="text-muted small">Filename:</span><br><?= e((string) $document['original_file_name']); ?></div>
                <div class="mb-2"><span class="text-muted small">Size:</span><br><?= e(number_format(((int) ($document['file_size'] ?? 0)) / 1024, 1)); ?> KB</div>
                <div class="mb-3"><span class="text-muted small">Type:</span><br><?= e(strtoupper((string) ($document['file_extension'] ?? ''))); ?></div>
                <a href="<?= e(url('/documents/' . (int) $document['id'] . '/download')); ?>" class="btn btn-outline-secondary w-100" target="_blank" rel="noopener">
                    <i class="bi bi-download me-1"></i> Open / Download File
                </a>
                <p class="text-muted small mt-3 mb-0">To replace the file, delete this document and re-upload.</p>
            </div>
        </div>
    </div>
</div>
