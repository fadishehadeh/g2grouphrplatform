<?php declare(strict_types=1); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">Salary Structures</h4>
        <p class="text-muted mb-0">Manage per-employee salary structures. Changes take effect on the specified date.</p>
    </div>
</div>

<?php if (!empty($companies)): ?>
<form method="get" action="<?= e(url('/payroll/salary-structures')); ?>" class="row g-2 mb-4">
    <div class="col-auto">
        <select name="company_id" class="form-select" onchange="this.form.submit()">
            <?php foreach ($companies as $c): ?>
            <option value="<?= e((string) $c['id']); ?>" <?= (int) $c['id'] === $companyId ? 'selected' : ''; ?>>
                <?= e((string) $c['name']); ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col">
        <div class="input-group">
            <input type="text" name="q" class="form-control" placeholder="Search employee…" value="<?= e($search); ?>">
            <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-search"></i></button>
            <?php if ($search !== ''): ?>
            <a href="<?= e(url('/payroll/salary-structures?company_id=' . $companyId)); ?>" class="btn btn-outline-secondary">Clear</a>
            <?php endif; ?>
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
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Job Title</th>
                        <th>Basic Salary</th>
                        <th>Currency</th>
                        <th>Effective From</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($employees)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            <?= $companyId === 0 ? 'Select a company to view employees.' : 'No employees found.'; ?>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($employees as $emp): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= e((string) $emp['full_name']); ?></div>
                            <small class="text-muted"><?= e((string) $emp['employee_code']); ?></small>
                        </td>
                        <td><?= e((string) ($emp['department_name'] ?? '—')); ?></td>
                        <td><?= e((string) ($emp['job_title_name'] ?? '—')); ?></td>
                        <td>
                            <?php if ($emp['basic_salary'] !== null): ?>
                            <?= e(number_format((float) $emp['basic_salary'], 2)); ?>
                            <?php else: ?>
                            <span class="text-muted">Not set</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e((string) ($emp['currency'] ?? '—')); ?></td>
                        <td><?= $emp['effective_from'] ? e(date('d M Y', strtotime((string) $emp['effective_from']))) : '—'; ?></td>
                        <td>
                            <?php if ($emp['employee_status'] === 'active'): ?>
                            <span class="badge bg-success">Active</span>
                            <?php else: ?>
                            <span class="badge bg-warning text-dark"><?= e((string) $emp['employee_status']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <a href="<?= e(url('/payroll/salary-structures/' . $emp['employee_id'] . '/edit')); ?>"
                               class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil"></i> <?= $emp['structure_id'] ? 'Edit' : 'Set'; ?>
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
