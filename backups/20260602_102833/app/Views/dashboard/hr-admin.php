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
        'note' => $pendingApprovals > 0 ? 'Requires manager or HR review.' : 'Approval queue is clear.',
    ],
    [
        'label' => 'Open onboarding tasks',
        'value' => $onboardingOpen,
        'href' => url('/onboarding'),
        'icon' => 'bi-person-plus',
        'tone' => $onboardingOpen > 0 ? 'brand' : 'calm',
        'note' => $onboardingOpen > 0 ? 'New joiners still need activation steps.' : 'No onboarding backlog.',
    ],
    [
        'label' => 'Expiring documents',
        'value' => $documentsExpiring,
        'href' => url('/documents/expiring'),
        'icon' => 'bi-file-earmark-medical',
        'tone' => $documentsExpiring > 0 ? 'danger' : 'calm',
        'note' => $documentsExpiring > 0 ? 'Follow up before records expire.' : 'Document compliance looks healthy.',
    ],
];
?>

<section class="ops-hero card content-card mb-4">
    <div class="card-body p-4">
        <div class="row g-4 align-items-start">
            <div class="col-xl-8">
                <div class="ops-kicker">HR Operations</div>
                <h2 class="ops-hero-title mb-3">Run daily HR operations from one cleaner control center.</h2>
                <p class="ops-hero-copy mb-4">Use the dashboard to move quickly between employee records, approval queues, templates, documents, and reporting without losing the current structure of the system.</p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?= e(url('/employees')); ?>" class="btn btn-primary"><i class="bi bi-people"></i> Employee Directory</a>
                    <a href="<?= e(url('/leave/approvals')); ?>" class="btn btn-outline-secondary"><i class="bi bi-calendar-check"></i> Review Leave</a>
                    <a href="<?= e(url('/letters/admin')); ?>" class="btn btn-outline-secondary"><i class="bi bi-envelope-paper"></i> Letter Requests</a>
                    <a href="<?= e(url('/documents/expiring')); ?>" class="btn btn-outline-secondary"><i class="bi bi-folder2-open"></i> Expiring Documents</a>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="ops-hero-panel">
                    <div class="ops-hero-panel-label">Team Snapshot</div>
                    <div class="ops-hero-panel-value"><?= e((string) $headcount); ?></div>
                    <div class="ops-hero-panel-text">Active employee records currently managed in the system.</div>
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
                <small class="text-muted">Core employee directory</small>
            </div>
        </a>
    </div>
    <div class="col-md-6 col-xl-3">
        <a href="<?= e(url('/leave/approvals')); ?>" class="card dashboard-card text-decoration-none">
            <div class="card-body">
                <span>Pending Approvals</span>
                <h3><?= e((string) $pendingApprovals); ?></h3>
                <small class="text-muted">Leave items awaiting action</small>
            </div>
        </a>
    </div>
    <div class="col-md-6 col-xl-3">
        <a href="<?= e(url('/onboarding')); ?>" class="card dashboard-card text-decoration-none">
            <div class="card-body">
                <span>Open Onboarding</span>
                <h3><?= e((string) $onboardingOpen); ?></h3>
                <small class="text-muted">People not fully onboarded</small>
            </div>
        </a>
    </div>
    <div class="col-md-6 col-xl-3">
        <a href="<?= e(url('/documents/expiring')); ?>" class="card dashboard-card text-decoration-none">
            <div class="card-body">
                <span>Expiring Documents</span>
                <h3><?= e((string) $documentsExpiring); ?></h3>
                <small class="text-muted">Records approaching expiry</small>
            </div>
        </a>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-7">
        <div class="card content-card h-100">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
                    <div>
                        <h5 class="mb-1">Attention Queue</h5>
                        <p class="text-muted mb-0">The most important operational queues, prioritized for quick review.</p>
                    </div>
                    <span class="badge bg-light text-primary">Priority View</span>
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
                    <p class="text-muted mb-0">Common HR tasks should be reachable in one click from the dashboard.</p>
                </div>

                <div class="row g-3">
                    <div class="col-sm-6">
                        <a href="<?= e(url('/employees/create')); ?>" class="ops-action-tile text-decoration-none">
                            <i class="bi bi-person-plus"></i>
                            <strong>Add Employee</strong>
                            <span>Create a new employee record and start setup.</span>
                        </a>
                    </div>
                    <div class="col-sm-6">
                        <a href="<?= e(url('/employees/import')); ?>" class="ops-action-tile text-decoration-none">
                            <i class="bi bi-upload"></i>
                            <strong>Import Records</strong>
                            <span>Bulk upload employee data into the directory.</span>
                        </a>
                    </div>
                    <div class="col-sm-6">
                        <a href="<?= e(url('/letters/templates')); ?>" class="ops-action-tile text-decoration-none">
                            <i class="bi bi-file-earmark-richtext"></i>
                            <strong>Edit Templates</strong>
                            <span>Maintain letter content and formatting.</span>
                        </a>
                    </div>
                    <div class="col-sm-6">
                        <a href="<?= e(url('/reports')); ?>" class="ops-action-tile text-decoration-none">
                            <i class="bi bi-bar-chart"></i>
                            <strong>View Reports</strong>
                            <span>Open reporting and export workflows.</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require base_path('app/Views/partials/dashboard-announcements.php'); ?>

<div class="row g-4">
    <div class="col-xl-6">
        <div class="card content-card h-100">
            <div class="card-body p-4">
                <h5 class="mb-3">Workflow Lanes</h5>
                <div class="ops-lane-list">
                    <div class="ops-lane-item">
                        <div class="ops-lane-title">People Lifecycle</div>
                        <div class="ops-lane-copy">Move cleanly from hiring to onboarding, active employment, and offboarding without losing context between modules.</div>
                    </div>
                    <div class="ops-lane-item">
                        <div class="ops-lane-title">Approvals and Exceptions</div>
                        <div class="ops-lane-copy">Bring leave approvals, urgent document follow-up, and generated letters into a single operational rhythm.</div>
                    </div>
                    <div class="ops-lane-item">
                        <div class="ops-lane-title">Records and Compliance</div>
                        <div class="ops-lane-copy">Keep employee records, documents, and policy-driven actions visible before they become issues.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-6">
        <div class="card content-card h-100">
            <div class="card-body p-4">
                <h5 class="mb-3">What This Sample Improves</h5>
                <div class="ops-improvement-list">
                    <div class="ops-improvement-item"><i class="bi bi-check2-circle"></i><span>Stronger visual hierarchy with a real landing section instead of isolated cards.</span></div>
                    <div class="ops-improvement-item"><i class="bi bi-check2-circle"></i><span>Task-first actions that match how HR works day to day.</span></div>
                    <div class="ops-improvement-item"><i class="bi bi-check2-circle"></i><span>More meaningful workflow grouping without changing the app shell or brand language.</span></div>
                    <div class="ops-improvement-item"><i class="bi bi-check2-circle"></i><span>Reusable dashboard patterns for employees, leave, documents, and letters.</span></div>
                </div>
            </div>
        </div>
    </div>
</div>
