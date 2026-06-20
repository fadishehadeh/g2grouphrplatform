<?php declare(strict_types=1);
$isDraft      = $run['status'] === 'draft';
$monthName    = date('F', mktime(0, 0, 0, (int) $run['period_month'], 1));
$periodLabel  = $monthName . ' ' . $run['period_year'];
$grossTotal   = array_sum(array_column($items, 'gross_total'));
$netTotal     = array_sum(array_column($items, 'net_total'));
$deductTotal  = array_sum(array_column($items, 'deductions'));
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><?= e($periodLabel); ?> Payroll</h4>
        <p class="text-muted mb-0">
            <?= e((string) $run['company_name']); ?>
            &middot;
            <?php if ($isDraft): ?>
            <span class="badge bg-warning text-dark">Draft</span>
            <?php else: ?>
            <span class="badge bg-success">Finalised <?= $run['finalized_at'] ? e(date('d M Y', strtotime((string) $run['finalized_at']))) : ''; ?></span>
            <?php endif; ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= e(url('/payroll/runs')); ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> All Runs
        </a>
        <?php if ($isDraft): ?>
        <a href="<?= e(url('/payroll/runs/' . $run['id'] . '/finalize')); ?>" class="btn btn-success btn-sm">
            <i class="bi bi-lock"></i> Finalise
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Summary cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card content-card text-center py-3">
            <div class="fs-4 fw-bold"><?= count($items); ?></div>
            <div class="text-muted small">Employees</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card content-card text-center py-3">
            <div class="fs-5 fw-bold"><?= e(number_format($grossTotal, 2)); ?></div>
            <div class="text-muted small">Gross Total</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card content-card text-center py-3">
            <div class="fs-5 fw-bold text-danger"><?= e(number_format($deductTotal, 2)); ?></div>
            <div class="text-muted small">Total Deductions</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card content-card text-center py-3">
            <div class="fs-5 fw-bold text-success"><?= e(number_format($netTotal, 2)); ?></div>
            <div class="text-muted small">Net Payable</div>
        </div>
    </div>
</div>

<!-- Line items table -->
<div class="card content-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Employee</th>
                        <th>Department</th>
                        <th class="text-end">Basic</th>
                        <th class="text-end">Housing</th>
                        <th class="text-end">Transport</th>
                        <th class="text-end">Other</th>
                        <th class="text-end">Deductions</th>
                        <th class="text-end">Gross</th>
                        <th class="text-end fw-bold">Net</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                    <tr><td colspan="10" class="text-center py-4 text-muted">No employees found in this run.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($items as $item): ?>
                    <tr <?= $item['is_manually_adjusted'] ? 'class="table-warning"' : ''; ?>>
                        <td>
                            <div class="fw-semibold"><?= e((string) $item['employee_name']); ?></div>
                            <small class="text-muted"><?= e((string) $item['employee_code']); ?></small>
                            <?php if ($item['is_manually_adjusted']): ?>
                            <span class="badge bg-warning text-dark ms-1" title="<?= e((string) ($item['notes'] ?? '')); ?>">Adjusted</span>
                            <?php endif; ?>
                        </td>
                        <td><small><?= e((string) ($item['department_name'] ?? '—')); ?></small></td>
                        <td class="text-end"><?= e(number_format((float) $item['basic_salary'], 2)); ?></td>
                        <td class="text-end"><?= e(number_format((float) $item['housing_allowance'], 2)); ?></td>
                        <td class="text-end"><?= e(number_format((float) $item['transport_allowance'], 2)); ?></td>
                        <td class="text-end"><?= e(number_format((float) $item['other_allowances'], 2)); ?></td>
                        <td class="text-end text-danger"><?= e(number_format((float) $item['deductions'], 2)); ?></td>
                        <td class="text-end"><?= e(number_format((float) $item['gross_total'], 2)); ?></td>
                        <td class="text-end fw-bold"><?= e(number_format((float) $item['net_total'], 2)); ?></td>
                        <td class="text-end">
                            <a href="<?= e(url('/payroll/runs/' . $run['id'] . '/payslip/' . $item['employee_id'])); ?>"
                               class="btn btn-xs btn-outline-secondary" title="Download payslip">
                                <i class="bi bi-file-earmark-pdf"></i>
                            </a>
                            <?php if ($isDraft): ?>
                            <button type="button"
                                    class="btn btn-xs btn-outline-primary ms-1"
                                    data-bs-toggle="modal"
                                    data-bs-target="#editItemModal"
                                    data-item-id="<?= e((string) $item['id']); ?>"
                                    data-basic="<?= e((string) $item['basic_salary']); ?>"
                                    data-housing="<?= e((string) $item['housing_allowance']); ?>"
                                    data-transport="<?= e((string) $item['transport_allowance']); ?>"
                                    data-other="<?= e((string) $item['other_allowances']); ?>"
                                    data-deductions="<?= e((string) $item['deductions']); ?>"
                                    data-notes="<?= e((string) ($item['notes'] ?? '')); ?>"
                                    title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <?php if (!empty($items)): ?>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td colspan="8" class="text-end">Total Net Payable</td>
                        <td class="text-end text-success"><?= e(number_format($netTotal, 2)); ?></td>
                        <td></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<?php if ($isDraft): ?>
<!-- Edit line item modal -->
<div class="modal fade" id="editItemModal" tabindex="-1" aria-labelledby="editItemModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" id="editItemForm">
            <?= csrf_field(); ?>
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editItemModalLabel">Adjust Line Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2 mb-3">
                        <i class="bi bi-info-circle me-1"></i>
                        Gross and net are recalculated automatically from the values you enter.
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Basic Salary</label>
                            <input type="number" name="basic_salary" id="mi_basic" class="form-control" step="0.01" min="0">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Housing Allowance</label>
                            <input type="number" name="housing_allowance" id="mi_housing" class="form-control" step="0.01" min="0">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Transport Allowance</label>
                            <input type="number" name="transport_allowance" id="mi_transport" class="form-control" step="0.01" min="0">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Other Allowances</label>
                            <input type="number" name="other_allowances" id="mi_other" class="form-control" step="0.01" min="0">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Deductions</label>
                            <input type="number" name="deductions" id="mi_deductions" class="form-control" step="0.01" min="0">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Adjustment Note</label>
                            <input type="text" name="notes" id="mi_notes" class="form-control" maxlength="500" placeholder="Reason for adjustment">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Adjustment</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var modal = document.getElementById('editItemModal');
    if (!modal) { return; }
    modal.addEventListener('show.bs.modal', function (e) {
        var btn = e.relatedTarget;
        var itemId = btn.dataset.itemId;
        var form = document.getElementById('editItemForm');
        form.action = '<?= e(url('/payroll/runs/items/')); ?>' + itemId;
        document.getElementById('mi_basic').value      = btn.dataset.basic;
        document.getElementById('mi_housing').value    = btn.dataset.housing;
        document.getElementById('mi_transport').value  = btn.dataset.transport;
        document.getElementById('mi_other').value      = btn.dataset.other;
        document.getElementById('mi_deductions').value = btn.dataset.deductions;
        document.getElementById('mi_notes').value      = btn.dataset.notes;
    });
})();
</script>
<?php endif; ?>
