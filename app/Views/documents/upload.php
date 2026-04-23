<?php declare(strict_types=1); ?>
<?php require base_path('app/Views/partials/document-nav.php'); ?>
<?php
// Build type data for JS: id => {name, category_id, category_name, requires_expiry}
$typeMap = [];
foreach (($documentTypes ?? []) as $t) {
    $typeMap[(int) $t['id']] = [
        'name'            => $t['name'],
        'category_id'     => (int) $t['category_id'],
        'category_name'   => $t['category_name'],
        'requires_expiry' => (int) $t['requires_expiry'],
    ];
}
$selectedTypeId = (int) old('document_type_id', '0');
?>
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
                <?php if (!($documentTypesReady ?? true)): ?>
                    <div class="alert alert-warning mb-3">
                        <strong>Setup required:</strong> The document types table is missing. Please run
                        <code>database/document_types_migration.sql</code> in phpMyAdmin, then reload this page.
                    </div>
                <?php endif; ?>
                <?php if (!($canUpload ?? false)): ?>
                    <div class="alert alert-info mb-0">You can review current document metadata here, but you do not have upload permission for this employee.</div>
                <?php else: ?>
                    <form method="post" action="<?= e(url('/employees/' . $employee['id'] . '/documents/upload')); ?>" enctype="multipart/form-data">
                        <?= csrf_field(); ?>

                        <div class="mb-3">
                            <label class="form-label">Document Type *</label>
                            <select name="document_type_id" id="doc-type-select" class="form-select" required>
                                <option value="">Select document type...</option>
                                <?php
                                $currentGroup = '';
                                foreach (($documentTypes ?? []) as $t):
                                    if ($t['category_name'] !== $currentGroup):
                                        if ($currentGroup !== '') echo '</optgroup>';
                                        $currentGroup = $t['category_name'];
                                        echo '<optgroup label="' . e($currentGroup) . '">';
                                    endif;
                                ?>
                                <option value="<?= e((string) $t['id']); ?>"
                                    data-name="<?= e((string) $t['name']); ?>"
                                    data-category="<?= e((string) $t['category_name']); ?>"
                                    data-requires-expiry="<?= (int) $t['requires_expiry']; ?>"
                                    <?= $selectedTypeId === (int) $t['id'] ? 'selected' : ''; ?>>
                                    <?= e((string) $t['name']); ?>
                                </option>
                                <?php endforeach; ?>
                                <?php if ($currentGroup !== '') echo '</optgroup>'; ?>
                            </select>
                            <div id="doc-type-hint" class="form-text text-muted"></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Title *</label>
                            <input type="text" name="title" id="doc-title" class="form-control"
                                   value="<?= e((string) old('title', '')); ?>" required
                                   placeholder="Auto-filled from document type, or enter manually">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Document Number</label>
                            <input type="text" name="document_number" class="form-control"
                                   value="<?= e((string) old('document_number', '')); ?>">
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Issue Date</label>
                                <input type="date" name="issue_date" class="form-control"
                                       value="<?= e((string) old('issue_date', '')); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" id="expiry-label">Expiry Date</label>
                                <input type="date" name="expiry_date" id="expiry-date" class="form-control"
                                       value="<?= e((string) old('expiry_date', '')); ?>">
                                <div id="expiry-hint" class="form-text"></div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Visibility *</label>
                            <select name="visibility_scope" class="form-select" required>
                                <option value="employee" <?= (string) old('visibility_scope', 'hr') === 'employee' ? 'selected' : ''; ?>>Employee only (employee can see)</option>
                                <option value="manager"  <?= (string) old('visibility_scope', 'hr') === 'manager'  ? 'selected' : ''; ?>>Manager + above</option>
                                <option value="hr"       <?= (string) old('visibility_scope', 'hr') === 'hr'       ? 'selected' : ''; ?>>HR only</option>
                                <option value="admin"    <?= (string) old('visibility_scope', 'hr') === 'admin'    ? 'selected' : ''; ?>>Admin only</option>
                                <?php if (has_role(['hr_only'])): ?>
                                <option value="hr_only"  <?= (string) old('visibility_scope', 'hr') === 'hr_only'  ? 'selected' : ''; ?>>HR Only — Confidential (HR Only role exclusively)</option>
                                <?php endif; ?>
                            </select>
                            <div class="form-text">Controls who can view and download this document.</div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">File *</label>
                            <input type="file" name="document_file" class="form-control"
                                   accept=".pdf,.png,.jpg,.jpeg,.doc,.docx" required>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Upload Document</button>
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
                                <th>Type / Category</th><th>Title</th><th>File</th><th>Expiry</th><th>Visibility</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($documents as $document): ?>
                                <?php $days = $document['days_until_expiry']; ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= e((string) $document['category_name']); ?></div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?= e((string) $document['title']); ?></div>
                                        <div class="small text-muted"><?= e((string) (($document['document_number'] ?? '') !== '' ? $document['document_number'] : 'No number')); ?></div>
                                    </td>
                                    <td>
                                        <div><?= e((string) $document['original_file_name']); ?></div>
                                        <div class="small text-muted"><?= e(number_format(((int) ($document['file_size'] ?? 0)) / 1024, 1)); ?> KB</div>
                                        <a href="<?= e(url('/documents/' . $document['id'] . '/download')); ?>" class="btn btn-link btn-sm px-0" target="_blank" rel="noopener">Open / Download</a>
                                    </td>
                                    <td>
                                        <?php if (($document['expiry_date'] ?? null) === null): ?>
                                            <span class="text-muted">—</span>
                                        <?php else: ?>
                                            <div><?= e((string) $document['expiry_date']); ?></div>
                                            <div class="small <?= $days !== null && (int) $days < 0 ? 'text-danger' : 'text-muted'; ?>">
                                                <?php if ((int) $days < 0): ?>Expired <?= e((string) abs((int) $days)); ?> day(s) ago
                                                <?php else: ?><?= e((string) $days); ?> day(s) left<?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php
                                        $scopeClass = match ((string) $document['visibility_scope']) {
                                            'admin'   => 'text-bg-danger',
                                            'hr'      => 'text-bg-warning',
                                            'manager' => 'text-bg-info',
                                            'hr_only' => 'text-bg-dark',
                                            default   => 'text-bg-secondary',
                                        };
                                        $scopeLabel = match ((string) $document['visibility_scope']) {
                                            'admin'   => 'Admin only',
                                            'hr'      => 'HR + Admin',
                                            'manager' => 'Manager+',
                                            'hr_only' => 'HR Only',
                                            default   => 'Employee',
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

<script>
(function () {
    var typeSelect  = document.getElementById('doc-type-select');
    var titleInput  = document.getElementById('doc-title');
    var expiryInput = document.getElementById('expiry-date');
    var expiryLabel = document.getElementById('expiry-label');
    var expiryHint  = document.getElementById('expiry-hint');
    var typeHint    = document.getElementById('doc-type-hint');

    if (!typeSelect) return;

    function applyType(option) {
        if (!option || option.value === '') {
            expiryLabel.textContent = 'Expiry Date';
            expiryInput.removeAttribute('required');
            expiryHint.textContent = '';
            expiryHint.className = 'form-text';
            typeHint.textContent = '';
            return;
        }

        var name     = option.dataset.name || '';
        var category = option.dataset.category || '';
        var reqExp   = parseInt(option.dataset.requiresExpiry, 10) === 1;

        // Auto-fill title only if it is still empty or matches a previous auto-fill
        if (titleInput.value === '' || titleInput.dataset.autofilled === 'true') {
            titleInput.value = name;
            titleInput.dataset.autofilled = 'true';
        }

        typeHint.textContent = 'Category: ' + category;

        if (reqExp) {
            expiryLabel.textContent = 'Expiry Date *';
            expiryInput.setAttribute('required', 'required');
            expiryHint.textContent = 'Required for this document type.';
            expiryHint.className = 'form-text text-danger';
        } else {
            expiryLabel.textContent = 'Expiry Date';
            expiryInput.removeAttribute('required');
            expiryHint.textContent = 'Optional for this document type.';
            expiryHint.className = 'form-text text-muted';
        }
    }

    titleInput.addEventListener('input', function () {
        titleInput.dataset.autofilled = 'false';
    });

    typeSelect.addEventListener('change', function () {
        applyType(typeSelect.options[typeSelect.selectedIndex]);
    });

    // Apply on page load (handles old_input re-population)
    applyType(typeSelect.options[typeSelect.selectedIndex]);
}());
</script>
