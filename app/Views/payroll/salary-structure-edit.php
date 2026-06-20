<?php declare(strict_types=1); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">Salary Structure — <?= e((string) $employee['full_name']); ?></h4>
        <p class="text-muted mb-0">
            <?= e((string) $employee['employee_code']); ?>
            <?php if (!empty($employee['department_name'])): ?>
            &middot; <?= e((string) $employee['department_name']); ?>
            <?php endif; ?>
            <?php if (!empty($employee['job_title_name'])): ?>
            &middot; <?= e((string) $employee['job_title_name']); ?>
            <?php endif; ?>
        </p>
    </div>
    <a href="<?= e(url('/payroll/salary-structures?company_id=' . $employee['company_id'])); ?>"
       class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<!-- Current / new structure form -->
<div class="card content-card mb-4">
    <div class="card-body p-4">
        <h5 class="mb-3">
            <?= $current ? 'Update Salary Structure' : 'Set Salary Structure'; ?>
        </h5>
        <?php if ($current): ?>
        <div class="alert alert-info py-2 mb-3">
            <i class="bi bi-info-circle me-1"></i>
            Saving will close the current structure (effective <?= e(date('d M Y', strtotime((string) $current['effective_from']))); ?>)
            and start the new one from the date you specify below.
        </div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('/payroll/salary-structures/' . $employee['id'] . '/edit')); ?>">
            <?= csrf_field(); ?>

            <div class="row g-3">

                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold required-label">Basic Salary</label>
                    <input type="number" name="basic_salary" class="form-control"
                           step="0.01" min="0"
                           value="<?= e((string) ($current['basic_salary'] ?? '')); ?>"
                           required>
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold">Housing Allowance</label>
                    <input type="number" name="housing_allowance" class="form-control"
                           step="0.01" min="0"
                           value="<?= e((string) ($current['housing_allowance'] ?? '')); ?>">
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold">Transport Allowance</label>
                    <input type="number" name="transport_allowance" class="form-control"
                           step="0.01" min="0"
                           value="<?= e((string) ($current['transport_allowance'] ?? '')); ?>">
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold">Other Allowances</label>
                    <input type="number" name="other_allowances" class="form-control"
                           step="0.01" min="0"
                           value="<?= e((string) ($current['other_allowances'] ?? '')); ?>">
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold required-label">Currency</label>
                    <select name="currency" class="form-select">
                        <?php foreach (['QAR', 'USD', 'EUR', 'GBP', 'AED', 'SAR', 'KWD', 'BHD', 'OMR', 'JOD'] as $cur): ?>
                        <option value="<?= e($cur); ?>" <?= ($current['currency'] ?? 'QAR') === $cur ? 'selected' : ''; ?>>
                            <?= e($cur); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold required-label">Effective From</label>
                    <input type="date" name="effective_from" class="form-control"
                           value="<?= e(date('Y-m-01')); ?>"
                           required>
                    <div class="form-text">Typically the first day of the month.</div>
                </div>

            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Save Structure</button>
                <a href="<?= e(url('/payroll/salary-structures?company_id=' . $employee['company_id'])); ?>"
                   class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<!-- History -->
<?php if (!empty($history)): ?>
<div class="card content-card">
    <div class="card-body p-0">
        <div class="px-4 pt-3 pb-2">
            <h6 class="mb-0">Structure History</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Basic</th>
                        <th>Housing</th>
                        <th>Transport</th>
                        <th>Other</th>
                        <th>Currency</th>
                        <th>Effective From</th>
                        <th>Effective To</th>
                        <th>Set By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $row): ?>
                    <tr <?= $row['effective_to'] === null ? 'class="table-success"' : ''; ?>>
                        <td><?= e(number_format((float) $row['basic_salary'], 2)); ?></td>
                        <td><?= e(number_format((float) ($row['housing_allowance'] ?? 0), 2)); ?></td>
                        <td><?= e(number_format((float) ($row['transport_allowance'] ?? 0), 2)); ?></td>
                        <td><?= e(number_format((float) ($row['other_allowances'] ?? 0), 2)); ?></td>
                        <td><?= e((string) $row['currency']); ?></td>
                        <td><?= e(date('d M Y', strtotime((string) $row['effective_from']))); ?></td>
                        <td>
                            <?php if ($row['effective_to'] === null): ?>
                            <span class="badge bg-success">Current</span>
                            <?php else: ?>
                            <?= e(date('d M Y', strtotime((string) $row['effective_to']))); ?>
                            <?php endif; ?>
                        </td>
                        <td><small class="text-muted"><?= e((string) ($row['created_by_email'] ?? '—')); ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>
