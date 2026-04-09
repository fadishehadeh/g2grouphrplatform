<?php declare(strict_types=1); ?>
<?php require base_path('app/Views/partials/document-nav.php'); ?>
<div class="card content-card mb-4">
    <div class="card-body p-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <h5 class="mb-1">HR Document Center</h5>
                <p class="text-muted mb-0">Review current employee documents, missing-expiry gaps, and upcoming renewals.</p>
            </div>
            <a href="<?= e(url('/documents/expiring')); ?>" class="btn btn-outline-primary">View Expiring Report</a>
        </div>
    </div>
</div>

<div class="card content-card">
    <div class="card-body p-4">
        <form method="get" action="<?= e(url('/documents')); ?>" class="row g-3 mb-4">
            <div class="col-md-7"><input type="text" name="q" class="form-control" placeholder="Search by employee, category, title, or number..." value="<?= e((string) ($search ?? '')); ?>"></div>
            <div class="col-md-3"><select name="expiry" class="form-select"><?php foreach (['all' => 'All documents', 'expiring' => 'Expiring in 30 days', 'expired' => 'Expired', 'missing_expiry' => 'Missing expiry on required category'] as $value => $label): ?><option value="<?= e($value); ?>" <?= (string) ($expiryFilter ?? 'all') === $value ? 'selected' : ''; ?>><?= e($label); ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2 d-grid"><button type="submit" class="btn btn-outline-secondary">Filter</button></div>
        </form>

        <?php if (($documents ?? []) === []): ?>
            <div class="empty-state">No documents matched the selected filters.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Employee</th><th>Category</th><th>Title</th><th>Number</th><th>Expiry</th><th>Visibility</th><th>File</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($documents as $document): ?>
                        <?php $days = $document['days_until_expiry']; ?>
                        <tr>
                            <td><div class="fw-semibold"><?= e((string) $document['employee_name']); ?></div><div class="small text-muted"><?= e((string) $document['employee_code']); ?></div></td>
                            <td><?= e((string) $document['category_name']); ?></td>
                            <td><div class="fw-semibold"><?= e((string) $document['title']); ?></div><div class="small text-muted"><?= e((string) ($document['status'] ?? 'active')); ?></div></td>
                            <td><?= e((string) (($document['document_number'] ?? '') !== '' ? $document['document_number'] : '—')); ?></td>
                            <td>
                                <?php if (($document['expiry_date'] ?? null) === null): ?>
                                    <span class="text-muted">—</span>
                                <?php else: ?>
                                    <div><?= e((string) $document['expiry_date']); ?></div>
                                    <div class="small <?= $days !== null && (int) $days < 0 ? 'text-danger' : 'text-muted'; ?>"><?php if ((int) $days < 0): ?>Expired <?= e((string) abs((int) $days)); ?> day(s) ago<?php else: ?><?= e((string) $days); ?> day(s) left<?php endif; ?></div>
                                <?php endif; ?>
                            </td>
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
                            <td><div><?= e((string) $document['original_file_name']); ?></div><div class="small text-muted"><?= e(number_format(((int) ($document['file_size'] ?? 0)) / 1024, 1)); ?> KB</div><a href="<?= e(url('/documents/' . $document['id'] . '/download')); ?>" class="btn btn-link btn-sm px-0" target="_blank" rel="noopener">Open / Download</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>