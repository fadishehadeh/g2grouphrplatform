<?php declare(strict_types=1); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">Payroll Runs</h4>
        <p class="text-muted mb-0">Create and manage monthly payroll runs per company.</p>
    </div>
    <a href="<?= e(url('/payroll/runs/create')); ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> New Run
    </a>
</div>

<?php if (!empty($companies)): ?>
<form method="get" action="<?= e(url('/payroll/runs')); ?>" class="mb-4">
    <div class="row g-2">
        <div class="col-auto">
            <select name="company_id" class="form-select" onchange="this.form.submit()">
                <?php foreach ($companies as $c): ?>
                <option value="<?= e((string) $c['id']); ?>" <?= (int) $c['id'] === $companyId ? 'selected' : ''; ?>>
                    <?= e((string) $c['name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</form>
<?php endif; ?>

<div class="card content-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Period</th>
                        <th>Status</th>
                        <th>Employees</th>
                        <th>Run By</th>
                        <th>Finalised At</th>
                        <th>Created</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($runs)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <?= $companyId === 0 ? 'Select a company above.' : 'No payroll runs yet. <a href="' . e(url('/payroll/runs/create')) . '">Create the first one</a>.'; ?>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($runs as $run): ?>
                    <?php
                        $monthName = date('F', mktime(0, 0, 0, (int) $run['period_month'], 1));
                        $isDraft   = $run['status'] === 'draft';
                    ?>
                    <tr>
                        <td class="fw-semibold">
                            <?= e($monthName . ' ' . $run['period_year']); ?>
                        </td>
                        <td>
                            <?php if ($isDraft): ?>
                            <span class="badge bg-warning text-dark">Draft</span>
                            <?php else: ?>
                            <span class="badge bg-success">Finalised</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e((string) ($run['item_count'] ?? 0)); ?></td>
                        <td><small class="text-muted"><?= e((string) ($run['run_by_email'] ?? '—')); ?></small></td>
                        <td>
                            <?= $run['finalized_at'] ? e(date('d M Y', strtotime((string) $run['finalized_at']))) : '—'; ?>
                        </td>
                        <td><small class="text-muted"><?= e(date('d M Y', strtotime((string) $run['created_at']))); ?></small></td>
                        <td class="text-end">
                            <a href="<?= e(url('/payroll/runs/' . $run['id'])); ?>"
                               class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i> View
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
