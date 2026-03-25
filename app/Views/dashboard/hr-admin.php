<?php declare(strict_types=1); ?>
<div class="row g-4 mb-4">
    <div class="col-md-3"><a href="<?= e(url('/employees')); ?>" class="card dashboard-card text-decoration-none"><div class="card-body"><span>Headcount</span><h3><?= e((string) $stats['headcount']); ?></h3></div></a></div>
    <div class="col-md-3"><a href="<?= e(url('/leave/approvals')); ?>" class="card dashboard-card text-decoration-none"><div class="card-body"><span>Pending Approvals</span><h3><?= e((string) $stats['pendingApprovals']); ?></h3></div></a></div>
    <div class="col-md-3"><a href="<?= e(url('/onboarding')); ?>" class="card dashboard-card text-decoration-none"><div class="card-body"><span>Open Onboarding</span><h3><?= e((string) $stats['onboardingOpen']); ?></h3></div></a></div>
    <div class="col-md-3"><a href="<?= e(url('/documents/expiring')); ?>" class="card dashboard-card text-decoration-none"><div class="card-body"><span>Expiring Documents</span><h3><?= e((string) $stats['documentsExpiring']); ?></h3></div></a></div>
</div>
<?php require base_path('app/Views/partials/dashboard-announcements.php'); ?>
<div class="card border-0 shadow-sm"><div class="card-body"><h5>HR Operations Overview</h5><p class="text-muted mb-0">Next iterations will connect employee lifecycle, leave queues, document expiry alerts, and policy management here.</p></div></div>