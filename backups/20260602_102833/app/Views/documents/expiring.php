<?php declare(strict_types=1); ?>
<?php require base_path('app/Views/partials/document-nav.php'); ?>
<?php
$pageHeaderTitle = 'Expiring and Expired Documents';
$pageHeaderDescription = 'Monitor renewal risk across employee documents and act before records become overdue.';
$pageHeaderChips = [
    ['label' => 'Window: next ' . (int) ($days ?? 30) . ' days', 'tone' => 'neutral'],
    ['label' => count($documents ?? []) . ' matching documents', 'tone' => (($documents ?? []) === []) ? 'calm' : 'warning'],
];
require base_path('app/Views/partials/page-header.php');
?>
<div class="card content-card mb-4">
    <div class="card-body p-4">
        <div class="employee-filter-bar">
            <div class="row g-2 align-items-end">
                <div class="col-lg-6">
                    <form method="get" action="<?= e(url('/documents/expiring')); ?>" class="row g-2 align-items-end">
                        <div class="col-sm-8">
                            <label class="form-label">Alert Window</label>
                            <select name="days" class="form-select"><?php foreach ([7, 15, 30, 60, 90] as $window): ?><option value="<?= e((string) $window); ?>" <?= (int) ($days ?? 30) === $window ? 'selected' : ''; ?>>Next <?= e((string) $window); ?> days</option><?php endforeach; ?></select>
                        </div>
                        <div class="col-sm-4">
                            <button type="submit" class="btn btn-outline-secondary w-100">Apply</button>
                        </div>
                    </form>
                </div>
                <div class="col-lg-6">
                    <form id="documentExpiryAlertForm" method="post" action="<?= e(url('/documents/send-expiry-alerts')); ?>" onsubmit="return confirm('Send 30-day expiry alert emails to all HR/Admin users for documents without an existing alert? This cannot be undone.');" class="justify-content-lg-end d-flex">
                        <?= csrf_field(); ?>
                        <button type="submit" class="btn btn-warning"><i class="bi bi-bell"></i> Send 30-Day Alerts to HR</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card content-card">
    <div class="card-body p-4">
        <?php if (($documents ?? []) === []): ?>
            <div class="empty-state">No documents are expiring in the selected time window.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Employee</th><th>Category</th><th>Title</th><th>Document #</th><th>File</th><th>Expiry</th><th>Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($documents as $document): ?>
                        <?php $daysUntilExpiry = (int) ($document['days_until_expiry'] ?? 0); ?>
                        <tr>
                            <td><div class="fw-semibold"><?= e((string) $document['employee_name']); ?></div><div class="small text-muted"><?= e((string) $document['employee_code']); ?></div></td>
                            <td><?= e((string) $document['category_name']); ?></td>
                            <td><?= e((string) $document['title']); ?></td>
                            <td><?= e((string) (($document['document_number'] ?? '') !== '' ? $document['document_number'] : '—')); ?></td>
                            <td><div><?= e((string) $document['original_file_name']); ?></div><a href="<?= e(url('/documents/' . $document['id'] . '/download')); ?>" class="btn btn-link btn-sm px-0" target="_blank" rel="noopener">Open / Download</a></td>
                            <td><?= e((string) $document['expiry_date']); ?></td>
                            <td><span class="badge <?= $daysUntilExpiry < 0 ? 'text-bg-danger' : ($daysUntilExpiry <= 7 ? 'text-bg-warning' : 'text-bg-secondary'); ?>"><?php if ($daysUntilExpiry < 0): ?>Expired <?= e((string) abs($daysUntilExpiry)); ?> day(s) ago<?php else: ?>Expires in <?= e((string) $daysUntilExpiry); ?> day(s)<?php endif; ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
