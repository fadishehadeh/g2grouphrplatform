<?php declare(strict_types=1); ?>
<?php require base_path('app/Views/partials/leave-nav.php'); ?>

<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl-3"><div class="card content-card h-100"><div class="card-body"><div class="text-muted small">Employees</div><div class="display-6 fw-semibold"><?= e((string) ($stats['employees'] ?? 0)); ?></div><div class="small text-muted mt-2"><?= e((string) ($scope['label'] ?? '')); ?></div></div></div></div>
    <div class="col-md-6 col-xl-3"><div class="card content-card h-100"><div class="card-body"><div class="text-muted small">Leave Types</div><div class="display-6 fw-semibold"><?= e((string) ($stats['leave_types'] ?? 0)); ?></div></div></div></div>
    <div class="col-md-6 col-xl-3"><div class="card content-card h-100"><div class="card-body"><div class="text-muted small">Total Balance</div><div class="display-6 fw-semibold"><?= e(number_format((float) ($stats['available_total'] ?? 0), 2)); ?></div></div></div></div>
    <div class="col-md-6 col-xl-3"><div class="card content-card h-100"><div class="card-body"><div class="text-muted small">Used Balance</div><div class="display-6 fw-semibold"><?= e(number_format((float) ($stats['used_total'] ?? 0), 2)); ?></div></div></div></div>
</div>

<?php if ($importErrors = app()->session()->getFlash('import_errors')): ?>
    <div class="alert alert-warning mb-4">
        <h6 class="alert-heading"><i class="bi bi-exclamation-triangle"></i> Import Errors</h6>
        <ul class="mb-0 small">
            <?php foreach ($importErrors as $error): ?>
                <li><?= e((string) $error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card content-card mb-4">
    <div class="card-body p-4">
        <div class="row g-3 align-items-end">
            <form method="get" action="<?= e(url('/leave/balances')); ?>" class="row g-3 align-items-end col-12">
                <div class="col-lg-5"><label class="form-label">Search</label><input type="text" name="q" class="form-control" placeholder="Employee, email, company, or department..." value="<?= e((string) ($search ?? '')); ?>"></div>
                <div class="col-md-3 col-lg-2"><label class="form-label">Year</label><input type="number" name="year" id="filterYear" class="form-control" min="2000" max="2100" value="<?= e((string) ($year ?? date('Y'))); ?>"></div>
                <div class="col-md-3 col-lg-2"><label class="form-label">Status</label><input type="text" class="form-control" value="Active only" disabled></div>
                <div class="col-lg-2 d-flex gap-2"><button type="submit" class="btn btn-outline-secondary w-100">Filter</button><a href="<?= e(url('/leave/balances')); ?>" class="btn btn-outline-light border w-100">Reset</a></div>
            </form>

            <?php if (can('leave.manage_types')): ?>
                <div class="col-12">
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="<?= e(url('/admin/leave/create')); ?>" class="btn btn-outline-dark">
                            <i class="bi bi-calendar-plus"></i> Add Leave for Employee
                        </a>

                        <form method="post" action="<?= e(url('/admin/leave/balances/assign')); ?>" class="d-flex gap-2" onsubmit="return confirm('This assigns non-annual leave balances to all active employees for the selected year. Continue?');">
                            <?= csrf_field(); ?>
                            <input type="number" name="year" class="form-control" style="width:110px" min="2000" max="2100" value="<?= e((string) ($year ?? date('Y'))); ?>">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-people"></i> Assign Non-Annual Balances</button>
                        </form>

                        <form method="post" action="<?= e(url('/admin/leave/balances/recalculate-annual')); ?>" class="d-flex gap-2" onsubmit="return confirm('This recalculates annual leave for all active employees for the selected year using joining date, carry-forward, and 2026 accrual rules. Continue?');">
                            <?= csrf_field(); ?>
                            <input type="number" name="year" class="form-control" style="width:110px" min="2000" max="2100" value="<?= e((string) ($year ?? date('Y'))); ?>">
                            <button type="submit" class="btn btn-outline-primary"><i class="bi bi-arrow-repeat"></i> Recalculate Annual Leave</button>
                        </form>
                    </div>
                </div>

                <div class="col-12">
                    <form method="post" action="<?= e(url('/admin/leave/balances/import-annual-used')); ?>" enctype="multipart/form-data" class="row g-2 align-items-end">
                        <?= csrf_field(); ?>
                        <div class="col-md-2"><label class="form-label">Import Year</label><input type="number" name="year" class="form-control" min="2000" max="2100" value="<?= e((string) ($year ?? date('Y'))); ?>"></div>
                        <div class="col-md-5"><label class="form-label">Annual Used-Days File</label><input type="file" name="import_file" class="form-control" accept=".xlsx,.xls" required></div>
                        <div class="col-md-5">
                            <button type="submit" class="btn btn-outline-dark">Import Annual Used Days</button>
                            <div class="small text-muted mt-2">Supported columns: <code>employee_code</code> + <code>used_days</code>, or your Excel format <code>Employee Name</code> + <code>Used Balance</code>. Imported values replace the annual used total for the selected year.</div>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card content-card">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Leave Balances</h5>
            <span class="text-muted small"><?= e((string) ($scope['label'] ?? '')); ?></span>
        </div>

        <?php if (($balances ?? []) === []): ?>
            <div class="empty-state">
                <p>No employee leave balances found.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Employee Name</th>
                            <th>Total Balance</th>
                            <th>Used Balance</th>
                            <th>Remaining Balance</th>
                            <?php if (can('leave.manage_types')): ?>
                                <th></th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($balances as $balance): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold">
                                        <?php if (can('leave.manage_types')): ?>
                                            <a href="<?= e(url('/admin/leave/employees/' . (int) $balance['employee_id'] . '?year=' . (int) ($year ?? date('Y')))); ?>" class="text-decoration-none"><?= e((string) $balance['employee_name']); ?></a>
                                        <?php else: ?>
                                            <?= e((string) $balance['employee_name']); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="small text-muted"><?= e((string) $balance['employee_code']); ?> &middot; <?= e((string) $balance['work_email']); ?></div>
                                    <div class="small text-muted"><?= e((string) (($balance['department_name'] ?? null) !== null ? $balance['department_name'] : 'No department')); ?><?= (($balance['company_name'] ?? '') !== '') ? ' · ' . e((string) $balance['company_name']) : ''; ?></div>
                                </td>
                                <td class="fw-semibold"><?= e(number_format((float) ($balance['total_balance'] ?? 0), 2)); ?></td>
                                <td><?= e(number_format((float) ($balance['used_amount'] ?? 0), 2)); ?></td>
                                <td class="fw-semibold"><?= e(number_format((float) ($balance['closing_balance'] ?? 0), 2)); ?></td>
                                <?php if (can('leave.manage_types')): ?>
                                    <td class="text-end"><a href="<?= e(url('/admin/leave/employees/' . (int) $balance['employee_id'] . '?year=' . (int) ($year ?? date('Y')))); ?>" class="btn btn-outline-secondary btn-sm">Edit</a></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php
                $page = (int) ($page ?? 1);
                $totalPages = (int) ($totalPages ?? 1);
                $total = (int) ($total ?? 0);
                $perPage = (int) ($perPage ?? 25);
                $query = array_filter([
                    'q' => (string) ($search ?? ''),
                    'year' => (string) ($year ?? date('Y')),
                ], fn($value) => (string) $value !== '');
                $showing = min($perPage, max(0, $total - ($page - 1) * $perPage));
                $from = $total > 0 ? ($page - 1) * $perPage + 1 : 0;
            ?>
            <?php if ($totalPages > 1): ?>
                <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                    <small class="text-muted">Showing <?= $from ?>-<?= $from + $showing - 1 ?> of <?= $total ?> active employees</small>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?= e(url('/leave/balances?' . http_build_query(array_merge($query, ['page' => $page - 1])))); ?>">&lsaquo;</a>
                            </li>
                            <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                                <li class="page-item <?= $p === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="<?= e(url('/leave/balances?' . http_build_query(array_merge($query, ['page' => $p])))); ?>"><?= $p; ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?= e(url('/leave/balances?' . http_build_query(array_merge($query, ['page' => $page + 1])))); ?>">&rsaquo;</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php else: ?>
                <div class="mt-3"><small class="text-muted"><?= $total ?> active employee<?= $total !== 1 ? 's' : ''; ?></small></div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
