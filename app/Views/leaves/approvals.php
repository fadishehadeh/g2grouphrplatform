<?php declare(strict_types=1); ?>
<?php require base_path('app/Views/partials/leave-nav.php'); ?>
<div class="row g-4">
    <div class="col-xl-6">
        <div class="card content-card h-100">
            <div class="card-body p-4">
                <h5 class="mb-1">Manager Approval Queue</h5>
                <p class="text-muted mb-4">Requests waiting for line-manager review.</p>
                <?php if (($managerQueue ?? []) === []): ?>
                    <div class="empty-state">No pending manager approvals right now.</div>
                <?php else: ?>
                    <div class="d-grid gap-3">
                        <?php foreach ($managerQueue as $item): ?>
                            <div class="border rounded-4 p-3">
                                <div class="d-flex justify-content-between gap-3 mb-2">
                                    <div>
                                        <div class="fw-semibold"><?= e((string) $item['employee_name']); ?> (<?= e((string) $item['employee_code']); ?>)</div>
                                        <div class="text-muted small"><?= e((string) $item['leave_type_name']); ?> · <?= e((string) $item['days_requested']); ?> days</div>
                                    </div>
                                    <span class="badge text-bg-warning">Pending Manager</span>
                                </div>
                                <div class="small mb-3"><?= e((string) $item['start_date']); ?> → <?= e((string) $item['end_date']); ?></div>
                                <div class="text-muted small mb-3"><?= e((string) $item['reason']); ?></div>
                                <form method="post" action="<?= e(url('/leave/' . $item['id'] . '/approve')); ?>" class="d-grid gap-2">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="queue" value="manager">
                                    <input type="text" name="comments" class="form-control" placeholder="Optional approval note or rejection reason">
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-success btn-sm">Approve</button>
                                        <button type="submit" formaction="<?= e(url('/leave/' . $item['id'] . '/reject')); ?>" class="btn btn-outline-danger btn-sm">Reject</button>
                                    </div>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="card content-card h-100">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center gap-3 mb-4">
                    <div>
                        <h5 class="mb-1">HR Approval Queue</h5>
                        <p class="text-muted mb-0">Requests escalated to HR for final review.</p>
                    </div>
                    <?php if (can('leave.manage_types')): ?>
                        <a href="<?= e(url('/admin/leave/types')); ?>" class="btn btn-outline-secondary btn-sm">Manage Types</a>
                    <?php endif; ?>
                </div>
                <?php if (($hrQueue ?? []) === []): ?>
                    <div class="empty-state">No pending HR approvals right now.</div>
                <?php else: ?>
                    <div class="d-grid gap-3">
                        <?php foreach ($hrQueue as $item): ?>
                            <div class="border rounded-4 p-3">
                                <div class="d-flex justify-content-between gap-3 mb-2">
                                    <div>
                                        <div class="fw-semibold"><?= e((string) $item['employee_name']); ?> (<?= e((string) $item['employee_code']); ?>)</div>
                                        <div class="text-muted small"><?= e((string) $item['leave_type_name']); ?> · <?= e((string) $item['days_requested']); ?> days</div>
                                    </div>
                                    <span class="badge text-bg-primary">Pending HR</span>
                                </div>
                                <div class="small mb-3"><?= e((string) $item['start_date']); ?> → <?= e((string) $item['end_date']); ?></div>
                                <div class="text-muted small mb-3"><?= e((string) $item['reason']); ?></div>
                                <form method="post" action="<?= e(url('/leave/' . $item['id'] . '/approve')); ?>" class="d-grid gap-2">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="queue" value="hr">
                                    <input type="text" name="comments" class="form-control" placeholder="Optional approval note or rejection reason">
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-success btn-sm">Approve</button>
                                        <button type="submit" formaction="<?= e(url('/leave/' . $item['id'] . '/reject')); ?>" class="btn btn-outline-danger btn-sm">Reject</button>
                                    </div>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>