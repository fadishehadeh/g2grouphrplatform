<?php declare(strict_types=1); ?>
<?php
$teamMembers = (int) ($stats['teamMembers'] ?? 0);
$pendingApprovals = (int) ($stats['pendingApprovals'] ?? 0);

$attentionItems = [
    [
        'label' => 'Leave requests waiting for approval',
        'value' => $pendingApprovals,
        'href' => url('/leave/approvals'),
        'icon' => 'bi-check2-square',
        'tone' => $pendingApprovals > 0 ? 'warning' : 'calm',
        'note' => $pendingApprovals > 0 ? 'Your team is waiting on manager review.' : 'No pending team approvals right now.',
    ],
    [
        'label' => 'Direct reports in your team',
        'value' => $teamMembers,
        'href' => url('/employees'),
        'icon' => 'bi-people',
        'tone' => $teamMembers > 0 ? 'brand' : 'calm',
        'note' => $teamMembers > 0 ? 'Open the employee directory to review team records.' : 'No direct reports are assigned yet.',
    ],
];
?>

<section class="ops-hero card content-card mb-4">
    <div class="card-body p-4">
        <div class="row g-4 align-items-start">
            <div class="col-xl-8">
                <div class="ops-kicker">Manager Workspace</div>
                <h2 class="ops-hero-title mb-3">Review team activity and act on approvals quickly.</h2>
                <p class="ops-hero-copy mb-4">The dashboard is organized around the tasks managers perform most often: reviewing leave, checking team records, and staying aligned with HR communication.</p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?= e(url('/leave/approvals')); ?>" class="btn btn-primary"><i class="bi bi-calendar-check"></i> Review Approvals</a>
                    <a href="<?= e(url('/employees')); ?>" class="btn btn-outline-secondary"><i class="bi bi-people"></i> Team Directory</a>
                    <a href="<?= e(url('/leave/calendar')); ?>" class="btn btn-outline-secondary"><i class="bi bi-calendar3"></i> Leave Calendar</a>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="ops-hero-panel">
                    <div class="ops-hero-panel-label">Manager Snapshot</div>
                    <div class="ops-hero-panel-value"><?= e((string) $pendingApprovals); ?></div>
                    <div class="ops-hero-panel-text">Requests currently waiting for your decision.</div>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <a href="<?= e(url('/employees')); ?>" class="card dashboard-card text-decoration-none">
            <div class="card-body">
                <span>Team Members</span>
                <h3><?= e((string) $teamMembers); ?></h3>
                <small class="text-muted">Direct reports linked to you</small>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="<?= e(url('/leave/approvals')); ?>" class="card dashboard-card text-decoration-none">
            <div class="card-body">
                <span>Pending Approvals</span>
                <h3><?= e((string) $pendingApprovals); ?></h3>
                <small class="text-muted">Leave requests awaiting review</small>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="<?= e(url('/leave/calendar')); ?>" class="card dashboard-card text-decoration-none">
            <div class="card-body">
                <span>Planning View</span>
                <h3><?= e((string) $teamMembers); ?></h3>
                <small class="text-muted">Use the calendar to plan upcoming absences</small>
            </div>
        </a>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-6">
        <div class="card content-card h-100">
            <div class="card-body p-4">
                <div class="mb-4">
                    <h5 class="mb-1">Needs Attention</h5>
                    <p class="text-muted mb-0">Priority items that should drive your next action.</p>
                </div>
                <div class="ops-queue-list">
                    <?php foreach ($attentionItems as $item): ?>
                        <a href="<?= e($item['href']); ?>" class="ops-queue-item text-decoration-none">
                            <div class="ops-queue-icon ops-queue-icon-<?= e($item['tone']); ?>">
                                <i class="bi <?= e($item['icon']); ?>"></i>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
                                    <div class="fw-semibold text-dark"><?= e($item['label']); ?></div>
                                    <span class="ops-queue-value"><?= e((string) $item['value']); ?></span>
                                </div>
                                <div class="small text-muted mt-1"><?= e($item['note']); ?></div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-6">
        <div class="card content-card h-100">
            <div class="card-body p-4">
                <div class="mb-4">
                    <h5 class="mb-1">Quick Actions</h5>
                    <p class="text-muted mb-0">Common manager tasks grouped for faster access.</p>
                </div>

                <div class="row g-3">
                    <div class="col-sm-6">
                        <a href="<?= e(url('/leave/approvals')); ?>" class="ops-action-tile text-decoration-none">
                            <i class="bi bi-check2-square"></i>
                            <strong>Approve Leave</strong>
                            <span>Review, approve, or reject pending team requests.</span>
                        </a>
                    </div>
                    <div class="col-sm-6">
                        <a href="<?= e(url('/employees')); ?>" class="ops-action-tile text-decoration-none">
                            <i class="bi bi-person-lines-fill"></i>
                            <strong>Open Team Records</strong>
                            <span>Check employee profiles and core details.</span>
                        </a>
                    </div>
                    <div class="col-sm-6">
                        <a href="<?= e(url('/leave/calendar')); ?>" class="ops-action-tile text-decoration-none">
                            <i class="bi bi-calendar3"></i>
                            <strong>View Calendar</strong>
                            <span>Plan around upcoming absences and workload.</span>
                        </a>
                    </div>
                    <div class="col-sm-6">
                        <a href="<?= e(url('/announcements')); ?>" class="ops-action-tile text-decoration-none">
                            <i class="bi bi-megaphone"></i>
                            <strong>Read Updates</strong>
                            <span>Stay aligned with HR announcements and policy changes.</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require base_path('app/Views/partials/dashboard-announcements.php'); ?>
