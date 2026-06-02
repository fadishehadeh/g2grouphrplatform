<?php declare(strict_types=1); ?>
<?php
use App\Modules\Letters\LetterRepository;
require base_path('app/Views/partials/letters-nav.php');
$statusVal = (string) ($letter['status'] ?? 'pending');
$isAdmin   = (bool) ($isAdmin ?? false);
?>

<div class="card content-card mb-4">
    <div class="card-body p-4">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
            <div>
                <div class="small text-muted mb-1">Letter Request #<?= e((string) $letter['id']); ?></div>
                <h4 class="mb-1"><?= e(LetterRepository::letterTypeLabel((string) $letter['letter_type'])); ?></h4>
                <div class="text-muted"><?= e((string) ($letter['employee_name'] ?? '')); ?> · <?= e((string) ($letter['employee_code'] ?? '')); ?></div>
            </div>
            <div class="text-lg-end">
                <span class="badge <?= $statusVal === 'approved' ? 'text-bg-success' : ($statusVal === 'rejected' ? 'text-bg-danger' : 'text-bg-warning'); ?> mb-2">
                    <?= e(ucfirst($statusVal)); ?>
                </span>
                <div class="small text-muted">Requested: <?= e((string) ($letter['created_at'] ?? '')); ?></div>
                <?php if (!empty($letter['generated_at'])): ?>
                    <div class="small text-muted">Generated: <?= e((string) $letter['generated_at']); ?> by <?= e((string) ($letter['generated_by_name'] ?? '')); ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-8">
        <!-- Employee Details -->
        <div class="card content-card mb-4">
            <div class="card-body p-4">
                <h5 class="mb-3">Employee Details</h5>
                <div class="row g-3 small">
                    <div class="col-md-6"><div class="text-muted">Full Name</div><div class="fw-semibold"><?= e((string) ($letter['employee_name'] ?? '—')); ?></div></div>
                    <div class="col-md-6"><div class="text-muted">Employee Code</div><div class="fw-semibold"><?= e((string) ($letter['employee_code'] ?? '—')); ?></div></div>
                    <div class="col-md-6"><div class="text-muted">Job Title</div><div class="fw-semibold"><?= e((string) ($letter['job_title_name'] ?? '—')); ?></div></div>
                    <div class="col-md-6"><div class="text-muted">Department</div><div class="fw-semibold"><?= e((string) ($letter['department_name'] ?? '—')); ?></div></div>
                    <div class="col-md-6"><div class="text-muted">Company</div><div class="fw-semibold"><?= e((string) ($letter['company_name'] ?? '—')); ?></div></div>
                    <div class="col-md-6"><div class="text-muted">Branch</div><div class="fw-semibold"><?= e((string) ($letter['branch_name'] !== '' ? $letter['branch_name'] : '—')); ?></div></div>
                    <div class="col-md-6"><div class="text-muted">Joining Date</div><div class="fw-semibold"><?= e((string) ($letter['joining_date'] ?? '—')); ?></div></div>
                    <div class="col-md-6"><div class="text-muted">Employment Type</div><div class="fw-semibold"><?= e(ucwords(str_replace('_', ' ', (string) ($letter['employment_type'] ?? '—')))); ?></div></div>
                </div>
                <?php if (!empty($letter['purpose'])): ?>
                    <hr>
                    <div class="small"><span class="text-muted">Purpose:</span> <?= e((string) $letter['purpose']); ?></div>
                <?php endif; ?>
                <?php if (!empty($letter['notes'])): ?>
                    <div class="small mt-1"><span class="text-muted">Employee Notes:</span> <?= nl2br(e((string) $letter['notes'])); ?></div>
                <?php endif; ?>
                <?php if ($statusVal === 'rejected' && !empty($letter['rejection_reason'])): ?>
                    <hr>
                    <div class="small text-danger"><span class="fw-semibold">Rejection Reason:</span> <?= e((string) $letter['rejection_reason']); ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <?php if ($isAdmin): ?>
        <!-- Generate / Regenerate Form -->
        <div class="card content-card mb-4">
            <div class="card-body p-4">
                <h6 class="mb-3"><?= $statusVal === 'approved' ? 'Regenerate Letter' : 'Generate Letter'; ?></h6>
                <form method="post" action="<?= e(url('/letters/' . (int) $letter['id'] . '/generate')); ?>">
                    <?= csrf_field(); ?>
                    <?php if (in_array((string) $letter['letter_type'], ['salary_certificate', 'bank_letter'], true)): ?>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold" for="salary_amount">Monthly Basic Salary</label>
                        <input type="number" name="salary_amount" id="salary_amount" class="form-control form-control-sm"
                               step="0.01" min="0" placeholder="e.g. 5000.00"
                               value="<?= e((string) ($letter['salary_amount'] ?? '')); ?>">
                        <div class="form-text">Leave blank to omit salary from the letter.</div>
                    </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold" for="additional_info">Additional Information</label>
                        <textarea name="additional_info" id="additional_info" class="form-control form-control-sm" rows="3"
                                  placeholder="Any extra paragraph to include in the letter..."><?= e((string) ($letter['additional_info'] ?? '')); ?></textarea>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-file-earmark-check"></i> <?= $statusVal === 'approved' ? 'Regenerate Letter' : 'Generate &amp; Approve'; ?>
                        </button>
                        <?php if ($statusVal === 'approved'): ?>
                        <a href="<?= e(url('/letters/' . (int) $letter['id'] . '/view')); ?>" class="btn btn-outline-success btn-sm">
                            <i class="bi bi-file-earmark-text"></i> View / Print
                        </a>
                        <a href="<?= e(url('/letters/' . (int) $letter['id'] . '/download')); ?>" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-download"></i> Download PDF
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
                <?php if ($statusVal === 'pending'): ?>
                <hr>
                <form method="post" action="<?= e(url('/letters/' . (int) $letter['id'] . '/reject')); ?>" onsubmit="return confirm('Reject this letter request?');">
                    <?= csrf_field(); ?>
                    <div class="mb-2">
                        <textarea name="reason" class="form-control form-control-sm" rows="2" placeholder="Rejection reason (required)" required></textarea>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-x-circle"></i> Reject</button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Change Status -->
        <div class="card content-card mb-4">
            <div class="card-body p-4">
                <h6 class="mb-3">Change Status</h6>
                <form method="post" action="<?= e(url('/letters/' . (int) $letter['id'] . '/status')); ?>">
                    <?= csrf_field(); ?>
                    <div class="d-flex gap-2">
                        <select name="status" class="form-select form-select-sm">
                            <?php foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled'] as $val => $label): ?>
                                <option value="<?= e($val); ?>" <?= $statusVal === $val ? 'selected' : ''; ?>><?= e($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-outline-secondary btn-sm text-nowrap">Update</button>
                    </div>
                </form>
            </div>
        </div>
        <?php elseif ($statusVal === 'approved'): ?>
        <div class="card content-card mb-4">
            <div class="card-body p-4">
                <div class="d-grid gap-2">
                    <a href="<?= e(url('/letters/' . (int) $letter['id'] . '/view')); ?>" class="btn btn-success btn-sm">
                        <i class="bi bi-file-earmark-text"></i> View / Print Letter
                    </a>
                    <a href="<?= e(url('/letters/' . (int) $letter['id'] . '/download')); ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-download"></i> Download PDF
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="card content-card">
            <div class="card-body p-4">
                <div class="d-grid">
                    <a href="<?= e(url($isAdmin ? '/letters/admin' : '/letters/my')); ?>" class="btn btn-outline-secondary btn-sm">
                        Back to Requests
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
