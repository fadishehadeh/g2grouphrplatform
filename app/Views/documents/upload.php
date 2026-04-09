<?php declare(strict_types=1); ?>
<?php require base_path('app/Views/partials/document-nav.php'); ?>
<div class="card content-card mb-4">
    <div class="card-body p-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <h5 class="mb-1"><?= e(trim((string) (($employee['first_name'] ?? '') . ' ' . ($employee['middle_name'] ?? '') . ' ' . ($employee['last_name'] ?? '')))); ?></h5>
            <p class="text-muted mb-0"><?= e((string) ($employee['employee_code'] ?? '')); ?> · Upload and monitor current employee documents.</p>
        </div>
        <?php if (can('documents.manage_all')): ?>
            <a href="<?= e(url('/documents')); ?>" class="btn btn-outline-secondary">Back to Document Center</a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-4">
        <div class="card content-card h-100">
            <div class="card-body p-4">
                <h5 class="mb-2">Upload Document</h5>
                <p class="text-muted small mb-4">Accepted formats: PDF, PNG, JPG, JPEG, DOC, DOCX. Maximum file size: 5 MB.</p>
                <?php if (!($canUpload ?? false)): ?>
                    <div class="alert alert-info mb-0">You can review current document metadata here, but you do not have upload permission for this employee.</div>
                <?php else: ?>
                    <form method="post" action="<?= e(url('/employees/' . $employee['id'] . '/documents/upload')); ?>" enctype="multipart/form-data">
                        <?= csrf_field(); ?>
                        <div class="mb-3"><label class="form-label">Category *</label><select name="category_id" class="form-select" required><option value="">Select category</option><?php foreach (($categories ?? []) as $category): ?><option value="<?= e((string) $category['id']); ?>" <?= (string) old('category_id', '') === (string) $category['id'] ? 'selected' : ''; ?>><?= e((string) $category['name']); ?> (<?= e((string) $category['code']); ?>)<?= (int) ($category['requires_expiry'] ?? 0) === 1 ? ' · Expiry required' : ''; ?></option><?php endforeach; ?></select></div>
                        <div class="mb-3"><label class="form-label">Title *</label><input type="text" name="title" class="form-control" value="<?= e((string) old('title', '')); ?>" required></div>
                        <div class="mb-3"><label class="form-label">Document Number</label><input type="text" name="document_number" class="form-control" value="<?= e((string) old('document_number', '')); ?>"></div>
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">Issue Date</label><input type="date" name="issue_date" class="form-control" value="<?= e((string) old('issue_date', '')); ?>"></div>
                            <div class="col-md-6"><label class="form-label">Expiry Date</label><input type="date" name="expiry_date" class="form-control" value="<?= e((string) old('expiry_date', '')); ?>"></div>
                            <div class="col-12">
                                <label class="form-label">Visibility *</label>
                                <select name="visibility_scope" class="form-select" required>
                                    <option value="employee" <?= (string) old('visibility_scope', 'hr') === 'employee' ? 'selected' : ''; ?>>Employee only (employee can see)</option>
                                    <option value="manager" <?= (string) old('visibility_scope', 'hr') === 'manager' ? 'selected' : ''; ?>>Manager + above</option>
                                    <option value="hr" <?= (string) old('visibility_scope', 'hr') === 'hr' ? 'selected' : ''; ?>>HR + Admin only</option>
                                    <option value="admin" <?= (string) old('visibility_scope', 'hr') === 'admin' ? 'selected' : ''; ?>>Admin only</option>
                                </select>
                                <div class="form-text">Controls who can view and download this document.</div>
                            </div>
                            <div class="col-12"><label class="form-label">File *</label><input type="file" name="document_file" class="form-control" accept=".pdf,.png,.jpg,.jpeg,.doc,.docx" required></div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 mt-4">Upload Document</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-xl-8">
        <div class="card content-card">
            <div class="card-body p-4">
                <h5 class="mb-1">Current Documents</h5>
                <p class="text-muted mb-4">This list shows the current active version for each uploaded document.</p>
                <?php if (($documents ?? []) === []): ?>
                    <div class="empty-state">No documents have been uploaded for this employee yet.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Category</th><th>Title</th><th>File</th><th>Expiry</th><th>Visibility</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($documents as $document): ?>
                                <?php $days = $document['days_until_expiry']; ?>
                                <tr>
                                    <td><?= e((string) $document['category_name']); ?></td>
                                    <td><div class="fw-semibold"><?= e((string) $document['title']); ?></div><div class="small text-muted"><?= e((string) (($document['document_number'] ?? '') !== '' ? $document['document_number'] : 'No number')); ?></div></td>
                                    <td><div><?= e((string) $document['original_file_name']); ?></div><div class="small text-muted"><?= e(number_format(((int) ($document['file_size'] ?? 0)) / 1024, 1)); ?> KB</div><a href="<?= e(url('/documents/' . $document['id'] . '/download')); ?>" class="btn btn-link btn-sm px-0" target="_blank" rel="noopener">Open / Download</a></td>
                                    <td><?php if (($document['expiry_date'] ?? null) === null): ?><span class="text-muted">—</span><?php else: ?><div><?= e((string) $document['expiry_date']); ?></div><div class="small <?= $days !== null && (int) $days < 0 ? 'text-danger' : 'text-muted'; ?>"><?php if ((int) $days < 0): ?>Expired <?= e((string) abs((int) $days)); ?> day(s) ago<?php else: ?><?= e((string) $days); ?> day(s) left<?php endif; ?></div><?php endif; ?></td>
                                    <td><?php
                                        $scopeClass = match ((string) $document['visibility_scope']) {
                                            'admin' => 'text-bg-danger',
                                            'hr' => 'text-bg-warning',
                                            'manager' => 'text-bg-info',
                                            default => 'text-bg-secondary',
                                        };
                                        $scopeLabel = match ((string) $document['visibility_scope']) {
                                            'admin' => 'Admin only',
                                            'hr' => 'HR + Admin',
                                            'manager' => 'Manager+',
                                            default => 'Employee',
                                        };
                                    ?><span class="badge <?= e($scopeClass); ?>"><?= e($scopeLabel); ?></span></td>
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