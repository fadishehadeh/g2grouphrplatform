<?php declare(strict_types=1); ?>
<?php require base_path('app/Views/partials/leave-nav.php'); ?>
<div class="card content-card mb-4">
    <div class="card-body p-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <h5 class="mb-1">Leave Balance Overview</h5>
            <p class="text-muted mb-0">Track available balances and monitor your leave request history.</p>
        </div>
        <?php if (can('leave.submit')): ?>
            <a href="<?= e(url('/leave/request')); ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Request Leave</a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4 mb-4">
    <?php if (($balances ?? []) === []): ?>
        <div class="col-12"><div class="empty-state">No leave balances are available for the current year.</div></div>
    <?php else: ?>
        <?php foreach ($balances as $balance): ?>
            <div class="col-md-4">
                <div class="profile-stat h-100">
                    <div class="metric-label"><?= e((string) $balance['leave_type_name']); ?></div>
                    <h3 class="mb-2"><?= e((string) $balance['closing_balance']); ?></h3>
                    <div class="small text-muted">Used: <?= e((string) $balance['used_amount']); ?> · Accrued: <?= e((string) $balance['accrued']); ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="card content-card">
    <div class="card-body p-4">
        <h5 class="mb-1">Leave Requests</h5>
        <p class="text-muted mb-4">Recent leave submissions and their current approval status.</p>
        <?php if (($requests ?? []) === []): ?>
            <div class="empty-state">No leave requests have been submitted yet.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Type</th><th>Dates</th><th>Days</th><th>Status</th><th>Submitted</th><th>Notes</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($requests as $leaveRequest): ?>
                        <tr>
                            <td><?= e((string) $leaveRequest['leave_type_name']); ?></td>
                            <td><?= e((string) $leaveRequest['start_date']); ?> → <?= e((string) $leaveRequest['end_date']); ?></td>
                            <td><?= e((string) $leaveRequest['days_requested']); ?></td>
                            <td><span class="badge <?= in_array($leaveRequest['status'], ['approved'], true) ? 'text-bg-success' : (in_array($leaveRequest['status'], ['rejected'], true) ? 'text-bg-danger' : 'text-bg-warning'); ?>"><?= e((string) $leaveRequest['status']); ?></span></td>
                            <td><?= e((string) (($leaveRequest['submitted_at'] ?? '') !== '' ? $leaveRequest['submitted_at'] : '—')); ?></td>
                            <td><?= e((string) (($leaveRequest['rejection_reason'] ?? '') !== '' ? $leaveRequest['rejection_reason'] : $leaveRequest['reason'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>