<?php declare(strict_types=1);
$monthName   = date('F', mktime(0, 0, 0, (int) $run['period_month'], 1));
$periodLabel = $monthName . ' ' . $run['period_year'];
$netTotal    = array_sum(array_column($items, 'net_total'));
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">Finalise Payroll Run</h4>
        <p class="text-muted mb-0"><?= e($periodLabel); ?> &middot; <?= e((string) $run['company_name']); ?></p>
    </div>
    <a href="<?= e(url('/payroll/runs/' . $run['id'])); ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Back to Run
    </a>
</div>

<div class="card content-card mb-4" style="max-width:560px">
    <div class="card-body p-4">
        <div class="alert alert-warning mb-4">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>This action cannot be undone.</strong> Once finalised, no line items can be edited and the run is locked for payslip generation.
        </div>

        <p><strong>Period:</strong> <?= e($periodLabel); ?></p>
        <p><strong>Company:</strong> <?= e((string) $run['company_name']); ?></p>
        <p><strong>Employees:</strong> <?= count($items); ?></p>
        <p><strong>Total Net Payable:</strong> <?= e(number_format($netTotal, 2)); ?> QAR</p>

        <?php $adjusted = array_filter($items, fn($i) => $i['is_manually_adjusted']); ?>
        <?php if (!empty($adjusted)): ?>
        <div class="alert alert-info py-2 mb-3">
            <i class="bi bi-info-circle me-1"></i>
            <?= count($adjusted); ?> line item(s) were manually adjusted.
        </div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('/payroll/runs/' . $run['id'] . '/finalize')); ?>">
            <?= csrf_field(); ?>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-lock"></i> Confirm Finalise
                </button>
                <a href="<?= e(url('/payroll/runs/' . $run['id'])); ?>" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
