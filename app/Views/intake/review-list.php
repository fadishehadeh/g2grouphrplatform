<?php declare(strict_types=1); ?>
<div class="app-content-header d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="mb-0 fw-semibold">Employee Registration</h4>
        <p class="text-muted mb-0 small">Review and approve submitted employee registration forms.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= e(url('/employee-registration')); ?>" class="btn btn-outline-primary btn-sm" target="_blank">
            <i class="bi bi-box-arrow-up-right me-1"></i> Open Registration Form
        </a>
    </div>
</div>

<!-- Status tabs -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
        <ul class="nav nav-tabs card-header-tabs">
            <?php foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'] as $key => $label): ?>
            <li class="nav-item">
                <a class="nav-link <?= $statusFilter === $key ? 'active' : ''; ?>"
                   href="<?= e(url('/employee-registration/review?status=' . $key . ($search !== '' ? '&q=' . urlencode($search) : ''))); ?>">
                    <?= e($label); ?>
                    <?php if ($key !== 'all' && isset($counts[$key]) && $counts[$key] > 0): ?>
                    <span class="badge <?= $key === 'pending' ? 'bg-warning text-dark' : ($key === 'approved' ? 'bg-success' : 'bg-danger'); ?> ms-1">
                        <?= (int) $counts[$key]; ?>
                    </span>
                    <?php endif; ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="card-body">
        <!-- Search -->
        <form method="get" action="<?= e(url('/employee-registration/review')); ?>" class="mb-3">
            <input type="hidden" name="status" value="<?= e($statusFilter); ?>">
            <div class="input-group" style="max-width:360px">
                <input type="text" name="q" class="form-control" placeholder="Search by name…" value="<?= e($search); ?>">
                <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
                <?php if ($search !== ''): ?>
                <a href="<?= e(url('/employee-registration/review?status=' . $statusFilter)); ?>" class="btn btn-outline-secondary"><i class="bi bi-x"></i></a>
                <?php endif; ?>
            </div>
        </form>

        <?php if (empty($submissions)): ?>
        <div class="text-center py-5 text-muted">
            <i class="bi bi-inbox" style="font-size:2.5rem"></i>
            <p class="mt-2 mb-0">No <?= e($statusFilter === 'all' ? '' : $statusFilter . ' '); ?>submissions found.</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Nationality</th>
                        <th>Submitted</th>
                        <th>Status</th>
                        <th>Reviewed By</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($submissions as $sub): ?>
                <tr>
                    <td class="fw-semibold">
                        <?= e($sub['first_name'] . ' ' . $sub['last_name']); ?>
                    </td>
                    <td><?= e($sub['nationality'] ?? '—'); ?></td>
                    <td class="text-muted small"><?= e(date('d M Y, H:i', strtotime($sub['submitted_at']))); ?></td>
                    <td>
                        <?php if ($sub['status'] === 'pending'): ?>
                        <span class="badge bg-warning text-dark">Pending</span>
                        <?php elseif ($sub['status'] === 'approved'): ?>
                        <span class="badge bg-success">Approved</span>
                        <?php else: ?>
                        <span class="badge bg-danger">Rejected</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-muted small"><?= e($sub['reviewer_name'] ?? '—'); ?></td>
                    <td>
                        <a href="<?= e(url('/employee-registration/review/' . $sub['token'])); ?>" class="btn btn-sm btn-outline-primary">
                            Review <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <nav class="mt-3">
            <ul class="pagination pagination-sm mb-0">
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : ''; ?>">
                    <a class="page-link" href="<?= e(url('/employee-registration/review?status=' . $statusFilter . '&q=' . urlencode($search) . '&page=' . $p)); ?>">
                        <?= $p; ?>
                    </a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
