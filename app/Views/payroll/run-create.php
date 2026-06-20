<?php declare(strict_types=1); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">Create Payroll Run</h4>
        <p class="text-muted mb-0">Select a company and period to generate a draft payroll run from current salary structures.</p>
    </div>
    <a href="<?= e(url('/payroll/runs')); ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<div class="card content-card" style="max-width:520px">
    <div class="card-body p-4">
        <form method="post" action="<?= e(url('/payroll/runs/create')); ?>">
            <?= csrf_field(); ?>

            <div class="mb-3">
                <label class="form-label fw-semibold required-label">Company</label>
                <select name="company_id" class="form-select" required>
                    <option value="">— Select company —</option>
                    <?php foreach ($companies as $c): ?>
                    <option value="<?= e((string) $c['id']); ?>">
                        <?= e((string) $c['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="row g-3 mb-3">
                <div class="col">
                    <label class="form-label fw-semibold required-label">Month</label>
                    <select name="month" class="form-select" required>
                        <option value="">— Month —</option>
                        <?php
                        $months = ['January','February','March','April','May','June',
                                   'July','August','September','October','November','December'];
                        $curMonth = (int) date('n');
                        foreach ($months as $i => $m):
                            $num = $i + 1;
                        ?>
                        <option value="<?= $num; ?>" <?= $num === $curMonth ? 'selected' : ''; ?>>
                            <?= e($m); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col">
                    <label class="form-label fw-semibold required-label">Year</label>
                    <select name="year" class="form-select" required>
                        <?php
                        $curYear = (int) date('Y');
                        for ($y = $curYear; $y >= $curYear - 3; $y--):
                        ?>
                        <option value="<?= $y; ?>" <?= $y === $curYear ? 'selected' : ''; ?>><?= $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>

            <div class="alert alert-info py-2 mb-3">
                <i class="bi bi-info-circle me-1"></i>
                The run will snapshot each employee's current salary structure. You can adjust individual line items before finalising.
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Generate Draft</button>
                <a href="<?= e(url('/payroll/runs')); ?>" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
