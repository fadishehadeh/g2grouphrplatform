<?php declare(strict_types=1); ?>
<?php
$leaveBalance = (float) ($stats['leaveBalance'] ?? 0);
$pendingRequests = (int) ($stats['pendingApprovals'] ?? 0);

$attentionItems = [
    [
        'label' => 'Pending leave requests',
        'value' => $pendingRequests,
        'href' => url('/leave/requests'),
        'icon' => 'bi-hourglass-split',
        'tone' => $pendingRequests > 0 ? 'warning' : 'calm',
        'note' => $pendingRequests > 0 ? 'Requests are still moving through approval.' : 'No requests are pending at the moment.',
    ],
    [
        'label' => 'Current leave balance',
        'value' => (string) $leaveBalance,
        'href' => url('/leave/balances'),
        'icon' => 'bi-wallet2',
        'tone' => $leaveBalance > 0 ? 'brand' : 'calm',
        'note' => 'Open balances to review available leave for the current year.',
    ],
];
?>

<section class="ops-hero card content-card mb-4">
    <div class="card-body p-4">
        <div class="row g-4 align-items-start">
            <div class="col-xl-8">
                <div class="ops-kicker">Employee Self-Service</div>
                <h2 class="ops-hero-title mb-3">Handle your requests, documents, and updates from one place.</h2>
                <p class="ops-hero-copy mb-4">The employee dashboard should make the next action obvious: request leave, track approvals, download letters, and stay on top of company updates.</p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?= e(url('/leave/request')); ?>" class="btn btn-primary"><i class="bi bi-plus-square"></i> Request Leave</a>
                    <a href="<?= e(url('/letters/request')); ?>" class="btn btn-outline-secondary"><i class="bi bi-envelope-paper"></i> Request Letter</a>
                    <a href="<?= e(url('/profile')); ?>" class="btn btn-outline-secondary"><i class="bi bi-person-circle"></i> My Profile</a>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="ops-hero-panel">
                    <div class="ops-hero-panel-label">Available Balance</div>
                    <div class="ops-hero-panel-value"><?= e((string) $leaveBalance); ?></div>
                    <div class="ops-hero-panel-text">Current leave balance available to you this year.</div>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <a href="<?= e(url('/leave/balances')); ?>" class="card dashboard-card text-decoration-none">
            <div class="card-body">
                <span>Leave Balance</span>
                <h3><?= e((string) $leaveBalance); ?></h3>
                <small class="text-muted">Available balance for the current year</small>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="<?= e(url('/leave/requests')); ?>" class="card dashboard-card text-decoration-none">
            <div class="card-body">
                <span>Pending Requests</span>
                <h3><?= e((string) $pendingRequests); ?></h3>
                <small class="text-muted">Submitted requests still in progress</small>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="<?= e(url('/notifications')); ?>" class="card dashboard-card text-decoration-none">
            <div class="card-body">
                <span>Updates</span>
                <h3><?= e((string) $pendingRequests); ?></h3>
                <small class="text-muted">Check notifications and request status updates</small>
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
                    <p class="text-muted mb-0">Your current requests and self-service priorities at a glance.</p>
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
                    <p class="text-muted mb-0">Common self-service actions should not require searching through the navigation.</p>
                </div>

                <div class="row g-3">
                    <div class="col-sm-6">
                        <a href="<?= e(url('/leave/request')); ?>" class="ops-action-tile text-decoration-none">
                            <i class="bi bi-plus-square"></i>
                            <strong>Request Leave</strong>
                            <span>Submit a new leave request for approval.</span>
                        </a>
                    </div>
                    <div class="col-sm-6">
                        <a href="<?= e(url('/letters/request')); ?>" class="ops-action-tile text-decoration-none">
                            <i class="bi bi-envelope-paper"></i>
                            <strong>Request Letter</strong>
                            <span>Submit an employment or salary letter request.</span>
                        </a>
                    </div>
                    <div class="col-sm-6">
                        <a href="<?= e(url('/leave/my')); ?>" class="ops-action-tile text-decoration-none">
                            <i class="bi bi-calendar-check"></i>
                            <strong>My Leave</strong>
                            <span>Review balances, request history, and approvals.</span>
                        </a>
                    </div>
                    <div class="col-sm-6">
                        <a href="<?= e(url('/profile')); ?>" class="ops-action-tile text-decoration-none">
                            <i class="bi bi-person-circle"></i>
                            <strong>My Profile</strong>
                            <span>Open your profile and employee-linked information.</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require base_path('app/Views/partials/dashboard-announcements.php'); ?>
