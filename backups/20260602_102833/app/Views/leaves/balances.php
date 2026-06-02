<?php declare(strict_types=1); ?>
<?php require base_path('app/Views/partials/leave-nav.php'); ?>
<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl-3"><div class="card content-card h-100"><div class="card-body"><div class="text-muted small">Employees</div><div class="display-6 fw-semibold"><?= e((string) ($stats['employees'] ?? 0)); ?></div><div class="small text-muted mt-2"><?= e((string) ($scope['label'] ?? '')); ?></div></div></div></div>
    <div class="col-md-6 col-xl-3"><div class="card content-card h-100"><div class="card-body"><div class="text-muted small">Leave Types</div><div class="display-6 fw-semibold"><?= e((string) ($stats['leave_types'] ?? 0)); ?></div></div></div></div>
    <div class="col-md-6 col-xl-3"><div class="card content-card h-100"><div class="card-body"><div class="text-muted small">Available Days</div><div class="display-6 fw-semibold"><?= e(number_format((float) ($stats['available_total'] ?? 0), 2)); ?></div></div></div></div>
    <div class="col-md-6 col-xl-3"><div class="card content-card h-100"><div class="card-body"><div class="text-muted small">Used Days</div><div class="display-6 fw-semibold"><?= e(number_format((float) ($stats['used_total'] ?? 0), 2)); ?></div></div></div></div>
</div>

<div class="card content-card mb-4"><div class="card-body p-4">
    <div class="row g-3 align-items-end">
        <form method="get" action="<?= e(url('/leave/balances')); ?>" class="row g-3 align-items-end col-12">
            <div class="col-lg-5"><label class="form-label">Search</label><input type="text" name="q" class="form-control" placeholder="Employee, email, company, department, or leave type..." value="<?= e((string) ($search ?? '')); ?>"></div>
            <div class="col-md-3 col-lg-2"><label class="form-label">Year</label><input type="number" name="year" id="filterYear" class="form-control" min="2000" max="2100" value="<?= e((string) ($year ?? date('Y'))); ?>"></div>
            <div class="col-md-3 col-lg-2"><label class="form-label">Status</label><select name="status" class="form-select"><option value="all">All statuses</option><?php foreach (['draft' => 'Draft', 'active' => 'Active', 'on_leave' => 'On Leave', 'inactive' => 'Inactive', 'resigned' => 'Resigned', 'terminated' => 'Terminated', 'archived' => 'Archived'] as $value => $label): ?><option value="<?= e($value); ?>" <?= (string) ($status ?? 'all') === $value ? 'selected' : ''; ?>><?= e($label); ?></option><?php endforeach; ?></select></div>
            <div class="col-lg-2 d-flex gap-2"><button type="submit" class="btn btn-outline-secondary w-100">Filter</button><a href="<?= e(url('/leave/balances')); ?>" class="btn btn-outline-light border w-100">Reset</a></div>
        </form>
        <?php if (can('leave.manage_types')): ?>
        <div class="col-12 d-flex gap-2 flex-wrap">
            <form method="post" action="<?= e(url('/admin/leave/balances/assign')); ?>" class="d-flex gap-2" onsubmit="return confirm('This will create missing balance records for all active employees for the selected year. Existing records will not be changed. Continue?');">
                <?= csrf_field(); ?>
                <input type="number" name="year" class="form-control" style="width:110px" min="2000" max="2100" value="<?= e((string) ($year ?? date('Y'))); ?>">
                <button type="submit" class="btn btn-primary"><i class="bi bi-people"></i> Assign Balances to All Employees</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div></div>

<div class="card content-card"><div class="card-body p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Balance Directory</h5>
        <span class="text-muted small"><?= e((string) ($scope['label'] ?? '')); ?></span>
    </div>
    <?php if (($balances ?? []) === []): ?>
        <div class="empty-state">
            <p>No leave balances found.</p>
            <?php if (can('leave.manage_types')): ?>
                <p class="text-muted small">Use <strong>Assign Balances to All Employees</strong> above to create balance records for all active employees based on each leave type's default days.</p>
            <?php endif; ?>
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr>
                <th>Employee</th>
                <th>Leave Type</th>
                <th>Company</th>
                <th>Opening</th>
                <th>Accrued</th>
                <th>Used</th>
                <th>Adjusted</th>
                <th>Closing</th>
                <th>Status</th>
                <?php if (can('leave.manage_types')): ?><th></th><?php endif; ?>
            </tr></thead>
            <tbody>
            <?php foreach ($balances as $balance): ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= e((string) $balance['employee_name']); ?></div>
                        <div class="small text-muted"><?= e((string) $balance['employee_code']); ?> · <?= e((string) $balance['work_email']); ?></div>
                        <div class="small text-muted"><?= e((string) (($balance['department_name'] ?? null) !== null ? $balance['department_name'] : 'No department')); ?></div>
                    </td>
                    <td>
                        <div class="fw-semibold"><?= e((string) $balance['leave_type_name']); ?></div>
                        <div class="small text-muted"><?= e((string) $balance['leave_type_code']); ?></div>
                    </td>
                    <td><?= e((string) $balance['company_name']); ?></td>
                    <td><?= e(number_format((float) $balance['opening_balance'], 2)); ?></td>
                    <td><?= e(number_format((float) $balance['accrued'], 2)); ?></td>
                    <td><?= e(number_format((float) $balance['used_amount'], 2)); ?></td>
                    <td><?= e(number_format((float) $balance['adjusted_amount'], 2)); ?></td>
                    <td class="fw-semibold"><?= e(number_format((float) $balance['closing_balance'], 2)); ?></td>
                    <td><span class="badge text-bg-light border"><?= e(ucwords(str_replace('_', ' ', (string) $balance['employee_status']))); ?></span></td>
                    <?php if (can('leave.manage_types')): ?>
                    <td>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#adj-<?= e((string) $balance['employee_id']); ?>-<?= e((string) $balance['leave_type_id']); ?>">Adjust</button>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php if (can('leave.manage_types')): ?>
                <tr class="collapse" id="adj-<?= e((string) $balance['employee_id']); ?>-<?= e((string) $balance['leave_type_id']); ?>">
                    <td colspan="10" class="bg-light">
                        <form method="post" action="<?= e(url('/admin/leave/balances/adjust')); ?>" class="row g-2 align-items-end p-2">
                            <?= csrf_field(); ?>
                            <input type="hidden" name="employee_id" value="<?= e((string) $balance['employee_id']); ?>">
                            <input type="hidden" name="leave_type_id" value="<?= e((string) $balance['leave_type_id']); ?>">
                            <input type="hidden" name="year" value="<?= e((string) $balance['balance_year']); ?>">
                            <div class="col-auto"><label class="form-label small mb-1">Opening Balance</label><input type="number" name="opening_balance" step="0.5" class="form-control form-control-sm" style="width:100px" value="<?= e(number_format((float) $balance['opening_balance'], 2)); ?>"></div>
                            <div class="col-auto"><label class="form-label small mb-1">Adjustment (+/−)</label><input type="number" name="adjustment" step="0.5" class="form-control form-control-sm" style="width:100px" value="0"></div>
                            <div class="col-auto"><button type="submit" class="btn btn-sm btn-primary">Save</button></div>
                            <div class="col-auto text-muted small mt-2">Closing = Opening + Accrued − Used + Adjusted</div>
                        </form>
                    </td>
                </tr>
                <?php endif; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div></div>
