<?php declare(strict_types=1); ?>
<?php
$headcount = (int) ($stats['headcount'] ?? 0);
$pendingApprovals = (int) ($stats['pendingApprovals'] ?? 0);
$onboardingOpen = (int) ($stats['onboardingOpen'] ?? 0);
$documentsExpiring = (int) ($stats['documentsExpiring'] ?? 0);

$attentionItems = [
    [
        'label' => 'Pending leave approvals',
        'value' => $pendingApprovals,
        'href' => url('/leave/approvals'),
        'icon' => 'bi-hourglass-split',
        'tone' => $pendingApprovals > 0 ? 'warning' : 'calm',
        'note' => $pendingApprovals > 0 ? 'Approval activity needs review across the platform.' : 'No leave approvals are waiting.',
    ],
    [
        'label' => 'Open onboarding records',
        'value' => $onboardingOpen,
        'href' => url('/onboarding'),
        'icon' => 'bi-person-plus',
        'tone' => $onboardingOpen > 0 ? 'brand' : 'calm',
        'note' => $onboardingOpen > 0 ? 'Monitor cross-company onboarding progress.' : 'No onboarding backlog is open.',
    ],
    [
        'label' => 'Expiring employee documents',
        'value' => $documentsExpiring,
        'href' => url('/documents/expiring'),
        'icon' => 'bi-file-earmark-medical',
        'tone' => $documentsExpiring > 0 ? 'danger' : 'calm',
        'note' => $documentsExpiring > 0 ? 'Compliance-related records need attention soon.' : 'No near-term document risk detected.',
    ],
];
?>

<section class="ops-hero card content-card mb-4">
    <div class="card-body p-4">
        <div class="row g-4 align-items-start">
            <div class="col-xl-8">
                <div class="ops-kicker">Platform Control Center</div>
                <h2 class="ops-hero-title mb-3">See the system at a glance and jump directly into control actions.</h2>
                <p class="ops-hero-copy mb-4">The super admin dashboard should prioritize operational control: user access, company structure, approvals, compliance, and platform-level reporting.</p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?= e(url('/admin/users')); ?>" class="btn btn-primary"><i class="bi bi-person-gear"></i> User Access</a>
                    <a href="<?= e(url('/admin/roles')); ?>" class="btn btn-outline-secondary"><i class="bi bi-shield-check"></i> Roles</a>
                    <a href="<?= e(url('/reports')); ?>" class="btn btn-outline-secondary"><i class="bi bi-bar-chart"></i> Reports</a>
                    <a href="<?= e(url('/settings')); ?>" class="btn btn-outline-secondary"><i class="bi bi-gear"></i> Settings</a>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="ops-hero-panel">
                    <div class="ops-hero-panel-label">Platform Snapshot</div>
                    <div class="ops-hero-panel-value"><?= e((string) $headcount); ?></div>
                    <div class="ops-hero-panel-text">Employee records currently represented across the system.</div>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="row g-4 mb-4">
    <div class="col-md-6 col-xl-3">
        <a href="<?= e(url('/employees')); ?>" class="card dashboard-card text-decoration-none">
            <div class="card-body">
                <span>Headcount</span>
                <h3><?= e((string) $headcount); ?></h3>
                <small class="text-muted">Company-wide employee records</small>
            </div>
        </a>
    </div>
    <div class="col-md-6 col-xl-3">
        <a href="<?= e(url('/leave/approvals')); ?>" class="card dashboard-card text-decoration-none">
            <div class="card-body">
                <span>Pending Approvals</span>
                <h3><?= e((string) $pendingApprovals); ?></h3>
                <small class="text-muted">Requests waiting across approval queues</small>
            </div>
        </a>
    </div>
    <div class="col-md-6 col-xl-3">
        <a href="<?= e(url('/onboarding')); ?>" class="card dashboard-card text-decoration-none">
            <div class="card-body">
                <span>Open Onboarding</span>
                <h3><?= e((string) $onboardingOpen); ?></h3>
                <small class="text-muted">Employee onboarding still in progress</small>
            </div>
        </a>
    </div>
    <div class="col-md-6 col-xl-3">
        <a href="<?= e(url('/documents/expiring')); ?>" class="card dashboard-card text-decoration-none">
            <div class="card-body">
                <span>Expiring Documents</span>
                <h3><?= e((string) $documentsExpiring); ?></h3>
                <small class="text-muted">Compliance-sensitive records approaching expiry</small>
            </div>
        </a>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-7">
        <div class="card content-card h-100">
            <div class="card-body p-4">
                <div class="mb-4">
                    <h5 class="mb-1">Needs Attention</h5>
                    <p class="text-muted mb-0">Cross-system items that matter most right now.</p>
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

    <div class="col-xl-5">
        <div class="card content-card h-100">
            <div class="card-body p-4">
                <div class="mb-4">
                    <h5 class="mb-1">Quick Actions</h5>
                    <p class="text-muted mb-0">Platform and governance shortcuts should be immediately accessible.</p>
                </div>
                <div class="row g-3">
                    <div class="col-sm-6">
                        <a href="<?= e(url('/admin/users')); ?>" class="ops-action-tile text-decoration-none">
                            <i class="bi bi-person-gear"></i>
                            <strong>User Access</strong>
                            <span>Open user administration and access controls.</span>
                        </a>
                    </div>
                    <div class="col-sm-6">
                        <a href="<?= e(url('/admin/roles')); ?>" class="ops-action-tile text-decoration-none">
                            <i class="bi bi-shield-check"></i>
                            <strong>Roles & Permissions</strong>
                            <span>Review and maintain permission structure.</span>
                        </a>
                    </div>
                    <div class="col-sm-6">
                        <a href="<?= e(url('/admin/structure')); ?>" class="ops-action-tile text-decoration-none">
                            <i class="bi bi-diagram-3"></i>
                            <strong>Org Structure</strong>
                            <span>Manage structure, companies, and reporting setup.</span>
                        </a>
                    </div>
                    <div class="col-sm-6">
                        <a href="<?= e(url('/settings')); ?>" class="ops-action-tile text-decoration-none">
                            <i class="bi bi-gear"></i>
                            <strong>Platform Settings</strong>
                            <span>Review operational settings and configuration.</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require base_path('app/Views/partials/dashboard-announcements.php'); ?>
