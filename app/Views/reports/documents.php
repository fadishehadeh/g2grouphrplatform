<?php declare(strict_types=1); ?>
<?php
$expiredCount = 0;
$expiringCount = 0;
$missingExpiryCount = 0;

foreach (($documents ?? []) as $row) {
    if (($row['expiry_date'] ?? null) === null && (int) ($row['requires_expiry'] ?? 0) === 1) {
        $missingExpiryCount++;
        continue;
    }

    $daysUntilExpiry = $row['days_until_expiry'] ?? null;

    if ($daysUntilExpiry === null) {
        continue;
    }

    if ((int) $daysUntilExpiry < 0) {
        $expiredCount++;
    } elseif ((int) $daysUntilExpiry <= (int) ($days ?? 30)) {
        $expiringCount++;
    }
}
?>
<?php require base_path('app/Views/partials/reports-nav.php'); ?>
<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="card content-card h-100"><div class="card-body"><div class="text-muted small">Expiring</div><div class="display-6 fw-semibold"><?= e((string) $expiringCount); ?></div><div class="small text-muted mt-2">Within <?= e((string) ($days ?? 30)); ?> day(s)</div></div></div></div>
    <div class="col-md-4"><div class="card content-card h-100"><div class="card-body"><div class="text-muted small">Expired</div><div class="display-6 fw-semibold"><?= e((string) $expiredCount); ?></div></div></div></div>
    <div class="col-md-4"><div class="card content-card h-100"><div class="card-body"><div class="text-muted small">Missing Expiry</div><div class="display-6 fw-semibold"><?= e((string) $missingExpiryCount); ?></div><div class="small text-muted mt-2"><?= e((string) ($scopeLabel ?? '')); ?></div></div></div></div>
</div>

<div class="card content-card mb-4"><div class="card-body p-4"><form method="get" action="<?= e(url('/reports/documents')); ?>" class="row g-3 align-items-end"><div class="col-lg-5"><label class="form-label">Search</label><input type="text" name="q" class="form-control" placeholder="Employee, document, category, or number..." value="<?= e((string) ($search ?? '')); ?>"></div><div class="col-md-4 col-lg-3"><label class="form-label">Expiry Filter</label><select name="expiry" class="form-select"><option value="all" <?= (string) ($expiryFilter ?? 'expiring') === 'all' ? 'selected' : ''; ?>>All current documents</option><option value="expiring" <?= (string) ($expiryFilter ?? 'expiring') === 'expiring' ? 'selected' : ''; ?>>Expiring soon</option><option value="expired" <?= (string) ($expiryFilter ?? 'expiring') === 'expired' ? 'selected' : ''; ?>>Expired</option><option value="missing_expiry" <?= (string) ($expiryFilter ?? 'expiring') === 'missing_expiry' ? 'selected' : ''; ?>>Missing expiry</option></select></div><div class="col-md-4 col-lg-2"><label class="form-label">Days</label><input type="number" name="days" min="1" max="365" class="form-control" value="<?= e((string) ($days ?? 30)); ?>"></div><div class="col-md-4 col-lg-2 d-flex gap-2"><button type="submit" class="btn btn-outline-secondary w-100">Filter</button><a href="<?= e(url('/reports/documents')); ?>" class="btn btn-outline-light border w-100">Reset</a></div></form></div></div>

<div class="card content-card"><div class="card-body p-4"><h5 class="mb-3">Document Directory</h5><?php if (($documents ?? []) === []): ?><div class="empty-state">No documents matched the selected filters.</div><?php else: ?><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Employee</th><th>Document</th><th>Issue / Expiry</th><th>Status</th></tr></thead><tbody><?php foreach ($documents as $row): ?><tr><td><div class="fw-semibold"><?= e((string) $row['employee_name']); ?></div><div class="small text-muted"><?= e((string) $row['employee_code']); ?></div></td><td><div><?= e((string) $row['title']); ?></div><div class="small text-muted"><?= e((string) $row['category_name']); ?><?= (($row['document_number'] ?? null) !== null && $row['document_number'] !== '') ? ' · ' . e((string) $row['document_number']) : ''; ?></div></td><td><div>Issued: <?= e((string) (($row['issue_date'] ?? null) !== null ? $row['issue_date'] : '—')); ?></div><div class="small text-muted">Expiry: <?= e((string) (($row['expiry_date'] ?? null) !== null ? $row['expiry_date'] : '—')); ?><?php if (($row['days_until_expiry'] ?? null) !== null): ?> · <?= e((string) $row['days_until_expiry']); ?> day(s)<?php endif; ?></div></td><td><span class="badge text-bg-light border"><?= e(ucwords(str_replace('_', ' ', (string) $row['status']))); ?></span><?php if (($row['expiry_date'] ?? null) === null && (int) ($row['requires_expiry'] ?? 0) === 1): ?><div class="small text-warning mt-1">Expiry required</div><?php elseif (($row['days_until_expiry'] ?? null) !== null && (int) $row['days_until_expiry'] < 0): ?><div class="small text-danger mt-1">Expired</div><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div></div>