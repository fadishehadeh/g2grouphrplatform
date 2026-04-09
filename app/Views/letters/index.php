<?php declare(strict_types=1); ?>
<?php require base_path('app/Views/partials/letters-nav.php'); ?>

<?php
use App\Modules\Letters\LetterRepository;

$isAdmin = (bool) ($isAdmin ?? false);
?>

<?php if ($isAdmin): ?>
<div class="card content-card mb-3">
    <div class="card-body p-3">
        <form method="get" action="<?= e(url('/letters/admin')); ?>" class="row g-2 align-items-end">
            <div class="col-auto">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <option value="pending"  <?= ($filterStatus ?? '') === 'pending'  ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?= ($filterStatus ?? '') === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="rejected" <?= ($filterStatus ?? '') === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
            </div>
            <div class="col-auto">
                <select name="letter_type" class="form-select form-select-sm">
                    <option value="">All Types</option>
                    <option value="salary_certificate"      <?= ($filterLetterType ?? '') === 'salary_certificate'      ? 'selected' : ''; ?>>Salary Certificate</option>
                    <option value="employment_certificate"  <?= ($filterLetterType ?? '') === 'employment_certificate'  ? 'selected' : ''; ?>>Employment Certificate</option>
                    <option value="experience_letter"       <?= ($filterLetterType ?? '') === 'experience_letter'       ? 'selected' : ''; ?>>Experience Letter</option>
                    <option value="noc"                     <?= ($filterLetterType ?? '') === 'noc'                     ? 'selected' : ''; ?>>No Objection Certificate</option>
                    <option value="bank_letter"             <?= ($filterLetterType ?? '') === 'bank_letter'             ? 'selected' : ''; ?>>Bank Confirmation Letter</option>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-outline-secondary btn-sm">Filter</button>
                <a href="<?= e(url('/letters/admin')); ?>" class="btn btn-outline-light border btn-sm">Clear</a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card content-card">
    <div class="card-body p-0">
        <?php if (($letters ?? []) === []): ?>
            <div class="empty-state p-5 text-center text-muted">
                <i class="bi bi-envelope-paper fs-1 d-block mb-3"></i>
                <?= $isAdmin ? 'No letter requests found.' : 'You have not requested any letters yet.'; ?>
                <?php if (!$isAdmin && can('letters.request')): ?>
                    <div class="mt-3">
                        <a href="<?= e(url('/letters/request')); ?>" class="btn btn-primary btn-sm">Request a Letter</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <?php if ($isAdmin): ?><th>Employee</th><?php endif; ?>
                            <th>Letter Type</th>
                            <th>Purpose</th>
                            <th>Requested</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($letters as $row): ?>
                        <?php $statusVal = (string) ($row['status'] ?? 'pending'); ?>
                        <tr>
                            <td class="text-muted small"><?= e((string) $row['id']); ?></td>
                            <?php if ($isAdmin): ?>
                            <td>
                                <div class="fw-semibold"><?= e((string) ($row['employee_name'] ?? '')); ?></div>
                                <div class="small text-muted"><?= e((string) ($row['employee_code'] ?? '')); ?> · <?= e((string) ($row['job_title_name'] ?? '')); ?></div>
                            </td>
                            <?php endif; ?>
                            <td><?= e(LetterRepository::letterTypeLabel((string) $row['letter_type'])); ?></td>
                            <td><?= e((string) ($row['purpose'] ?? '—')); ?></td>
                            <td class="small text-muted"><?= e((string) ($row['created_at'] ?? '')); ?></td>
                            <td>
                                <span class="badge <?= $statusVal === 'approved' ? 'text-bg-success' : ($statusVal === 'rejected' ? 'text-bg-danger' : 'text-bg-warning'); ?>">
                                    <?= e(ucfirst($statusVal)); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($statusVal === 'approved'): ?>
                                    <a href="<?= e(url('/letters/' . (int) $row['id'] . '/view')); ?>" class="btn btn-sm btn-outline-success">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                <?php elseif ($isAdmin && $statusVal === 'pending'): ?>
                                    <a href="<?= e(url('/letters/' . (int) $row['id'])); ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil-square"></i> Generate
                                    </a>
                                <?php elseif ($isAdmin): ?>
                                    <a href="<?= e(url('/letters/' . (int) $row['id'])); ?>" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small"><?= $statusVal === 'rejected' ? 'Rejected' : 'Pending'; ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
