<?php declare(strict_types=1); ?>
<?php require base_path('app/Views/partials/document-nav.php'); ?>
<?php
$hasTypes     = ($documentTypes ?? []) !== [];
$hasEmployees = ($employees ?? []) !== [];
$canUpload    = $hasTypes && $hasEmployees;
?>
<div class="card content-card mb-4">
    <div class="card-body p-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <h5 class="mb-1">HR Document Center</h5>
                <p class="text-muted mb-0">Review current employee documents, missing-expiry gaps, and upcoming renewals.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= e(url('/documents/expiring')); ?>" class="btn btn-outline-secondary">Expiring Report</a>
                <?php if ($canUpload): ?>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
                        Upload Document
                    </button>
                <?php elseif (!$hasTypes): ?>
                    <a href="<?= e(url('/documents/types')); ?>" class="btn btn-warning">
                        Setup Document Types First
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card content-card">
    <div class="card-body p-4">
        <form method="get" action="<?= e(url('/documents')); ?>" class="row g-3 mb-4">
            <div class="col-md-7">
                <input type="text" name="q" class="form-control"
                       placeholder="Search by employee, category, title, or number..."
                       value="<?= e((string) ($search ?? '')); ?>">
            </div>
            <div class="col-md-3">
                <select name="expiry" class="form-select">
                    <?php foreach (['all' => 'All documents', 'expiring' => 'Expiring in 30 days', 'expired' => 'Expired', 'missing_expiry' => 'Missing expiry on required category'] as $value => $label): ?>
                        <option value="<?= e($value); ?>" <?= (string) ($expiryFilter ?? 'all') === $value ? 'selected' : ''; ?>>
                            <?= e($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-outline-secondary">Filter</button>
            </div>
        </form>

        <?php if (($documents ?? []) === []): ?>
            <div class="empty-state">No documents matched the selected filters.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Employee</th><th>Category</th><th>Title</th><th>Number</th><th>Expiry</th><th>Visibility</th><th>File</th><th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($documents as $document): ?>
                        <?php $days = $document['days_until_expiry']; ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= e((string) $document['employee_name']); ?></div>
                                <div class="small text-muted"><?= e((string) $document['employee_code']); ?></div>
                            </td>
                            <td><?= e((string) $document['category_name']); ?></td>
                            <td>
                                <div class="fw-semibold"><?= e((string) $document['title']); ?></div>
                                <div class="small text-muted"><?= e((string) ($document['status'] ?? 'active')); ?></div>
                            </td>
                            <td><?= e((string) (($document['document_number'] ?? '') !== '' ? $document['document_number'] : '—')); ?></td>
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
                                    'hr'      => 'HR only',
                                    'manager' => 'Manager+',
                                    'hr_only' => 'HR Only',
                                    default   => 'Employee',
                                };
                            ?><span class="badge <?= e($scopeClass); ?>"><?= e($scopeLabel); ?></span></td>
                            <td>
                                <div><?= e((string) $document['original_file_name']); ?></div>
                                <div class="small text-muted"><?= e(number_format(((int) ($document['file_size'] ?? 0)) / 1024, 1)); ?> KB</div>
                                <a href="<?= e(url('/documents/' . $document['id'] . '/download')); ?>" class="btn btn-link btn-sm px-0" target="_blank" rel="noopener">Open / Download</a>
                            </td>
                            <td>
                                <a href="<?= e(url('/documents/' . $document['id'] . '/edit')); ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($canUpload): ?>
<?php
// Build type map for JS
$typeMapJson = '{}';
$typeMapArr  = [];
foreach (($documentTypes ?? []) as $t) {
    $typeMapArr[(int) $t['id']] = [
        'name'            => $t['name'],
        'requires_expiry' => (int) $t['requires_expiry'],
        'category_name'   => $t['category_name'],
    ];
}
$typeMapJson = json_encode($typeMapArr, JSON_HEX_TAG);
?>
<!-- Upload Document Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="uploadModalLabel">Upload Document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="<?= e(url('/documents/admin-upload')); ?>" enctype="multipart/form-data">
                <?= csrf_field(); ?>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Employee *</label>
                            <select name="employee_id" id="modal-employee" class="form-select" required>
                                <option value="">Select employee...</option>
                                <?php foreach (($employees ?? []) as $emp): ?>
                                    <option value="<?= e((string) $emp['id']); ?>">
                                        <?= e((string) $emp['full_name']); ?> (<?= e((string) $emp['employee_code']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Document Type *</label>
                            <select name="document_type_id" id="modal-doc-type" class="form-select" required>
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
                                    data-requires-expiry="<?= (int) $t['requires_expiry']; ?>">
                                    <?= e((string) $t['name']); ?>
                                </option>
                                <?php endforeach; ?>
                                <?php if ($currentGroup !== '') echo '</optgroup>'; ?>
                            </select>
                            <div id="modal-type-hint" class="form-text text-muted"></div>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Title *</label>
                            <input type="text" name="title" id="modal-title" class="form-control" required
                                   placeholder="Auto-filled from document type, or enter manually">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Document Number</label>
                            <input type="text" name="document_number" class="form-control">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Issue Date</label>
                            <input type="date" name="issue_date" class="form-control">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label" id="modal-expiry-label">Expiry Date</label>
                            <input type="date" name="expiry_date" id="modal-expiry" class="form-control">
                            <div id="modal-expiry-hint" class="form-text"></div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Visibility *</label>
                            <select name="visibility_scope" class="form-select" required>
                                <option value="employee">Employee only</option>
                                <option value="manager">Manager + above</option>
                                <option value="hr" selected>HR only</option>
                                <option value="admin">Admin only</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">File *</label>
                            <input type="file" name="document_file" class="form-control"
                                   accept=".pdf,.png,.jpg,.jpeg,.doc,.docx" required>
                            <div class="form-text">PDF, PNG, JPG, DOC, DOCX — max 5 MB.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Upload Document</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    var typeSelect   = document.getElementById('modal-doc-type');
    var titleInput   = document.getElementById('modal-title');
    var expiryInput  = document.getElementById('modal-expiry');
    var expiryLabel  = document.getElementById('modal-expiry-label');
    var expiryHint   = document.getElementById('modal-expiry-hint');
    var typeHint     = document.getElementById('modal-type-hint');

    if (!typeSelect) return;

    function applyType(option) {
        if (!option || option.value === '') {
            expiryLabel.textContent = 'Expiry Date';
            expiryInput.removeAttribute('required');
            expiryHint.textContent = '';
            typeHint.textContent = '';
            return;
        }

        var name   = option.dataset.name || '';
        var reqExp = parseInt(option.dataset.requiresExpiry, 10) === 1;

        if (titleInput.value === '' || titleInput.dataset.autofilled === 'true') {
            titleInput.value = name;
            titleInput.dataset.autofilled = 'true';
        }

        typeHint.textContent = reqExp ? 'Expiry date is required for this type.' : 'Expiry date is optional for this type.';

        if (reqExp) {
            expiryLabel.textContent = 'Expiry Date *';
            expiryInput.setAttribute('required', 'required');
            expiryHint.textContent = 'Required.';
            expiryHint.className = 'form-text text-danger';
        } else {
            expiryLabel.textContent = 'Expiry Date';
            expiryInput.removeAttribute('required');
            expiryHint.textContent = '';
            expiryHint.className = 'form-text';
        }
    }

    titleInput.addEventListener('input', function () {
        titleInput.dataset.autofilled = 'false';
    });

    typeSelect.addEventListener('change', function () {
        applyType(typeSelect.options[typeSelect.selectedIndex]);
    });

    // Reset modal fields when it opens
    document.getElementById('uploadModal').addEventListener('show.bs.modal', function () {
        typeSelect.selectedIndex = 0;
        titleInput.value = '';
        titleInput.dataset.autofilled = 'false';
        expiryInput.value = '';
        expiryLabel.textContent = 'Expiry Date';
        expiryInput.removeAttribute('required');
        expiryHint.textContent = '';
        typeHint.textContent = '';
    });
}());
</script>
<?php endif; ?>
