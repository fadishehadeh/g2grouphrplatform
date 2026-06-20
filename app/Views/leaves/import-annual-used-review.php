<?php declare(strict_types=1); ?>

<div class="card content-card mb-4">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
            <div>
                <h5 class="mb-1">Review Annual Used-Days Import</h5>
                <p class="text-muted mb-0">Confirm the correct employee for each unresolved row before the remaining used balances are imported.</p>
            </div>
            <a href="<?= e(url('/leave/balances?year=' . (int) ($year ?? date('Y')))); ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Balances
            </a>
        </div>
    </div>
</div>

<?php if ((int) ($updatedCount ?? 0) > 0): ?>
    <div class="alert alert-success">
        <?= e((string) $updatedCount); ?> row(s) were imported automatically. Review the unresolved rows below.
    </div>
<?php endif; ?>

<?php if (($importErrors ?? []) !== []): ?>
    <div class="alert alert-warning">
        <h6 class="alert-heading mb-2"><i class="bi bi-exclamation-triangle"></i> Rows With Errors</h6>
        <ul class="mb-0 small">
            <?php foreach (($importErrors ?? []) as $error): ?>
                <li><?= e((string) $error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" action="<?= e(url('/admin/leave/balances/import-annual-used/confirm')); ?>">
    <?= csrf_field(); ?>
    <div class="card content-card">
        <div class="card-body p-4">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Row</th>
                            <th>Employee Name</th>
                            <th>Used Balance</th>
                            <th>Action</th>
                            <th>Match Employee</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($reviewRows ?? []) as $index => $row): ?>
                            <?php
                            $candidates = is_array($row['candidates'] ?? null) ? $row['candidates'] : [];
                            $defaultAction = $candidates === [] ? 'skip' : 'import';
                            ?>
                            <tr>
                                <td class="fw-semibold"><?= e((string) ($row['row_number'] ?? '')); ?></td>
                                <td><?= e((string) ($row['employee_name'] ?? '')); ?></td>
                                <td><?= e(number_format((float) ($row['used_days'] ?? 0), 2)); ?></td>
                                <td style="min-width: 180px;">
                                    <select name="row_action[<?= e((string) $index); ?>]" class="form-select form-select-sm">
                                        <option value="import" <?= $defaultAction === 'import' ? 'selected' : ''; ?>>Import</option>
                                        <option value="skip" <?= $defaultAction === 'skip' ? 'selected' : ''; ?>>Skip</option>
                                    </select>
                                </td>
                                <td style="min-width: 320px;">
                                    <?php if ($candidates === []): ?>
                                        <span class="text-muted small">No exact employee name match found. Skip this row or add the employee first.</span>
                                    <?php else: ?>
                                        <select name="employee_id[<?= e((string) $index); ?>]" class="form-select form-select-sm">
                                            <option value="">Select employee</option>
                                            <?php foreach ($candidates as $candidate): ?>
                                                <option value="<?= e((string) ($candidate['id'] ?? 0)); ?>">
                                                    <?= e((string) ($candidate['employee_name'] ?? '')); ?>
                                                    <?php if (trim((string) ($candidate['employee_code'] ?? '')) !== ''): ?>
                                                        | <?= e((string) $candidate['employee_code']); ?>
                                                    <?php endif; ?>
                                                    <?php if (trim((string) ($candidate['joining_date'] ?? '')) !== ''): ?>
                                                        | Joining <?= e((string) $candidate['joining_date']); ?>
                                                    <?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="small text-muted mt-1">Choose the correct employee for this row.</div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="<?= e(url('/leave/balances?year=' . (int) ($year ?? date('Y')))); ?>" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Confirm Reviewed Rows</button>
            </div>
        </div>
    </div>
</form>
