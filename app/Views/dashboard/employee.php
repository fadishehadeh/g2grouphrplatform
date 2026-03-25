<?php declare(strict_types=1); ?>
<div class="row g-4 mb-4">
    <div class="col-md-4"><a href="<?= e(url('/leave/balances')); ?>" class="card dashboard-card text-decoration-none"><div class="card-body"><span>Leave Balance</span><h3><?= e((string) $stats['leaveBalance']); ?></h3></div></a></div>
    <div class="col-md-4"><a href="<?= e(url('/leave/requests')); ?>" class="card dashboard-card text-decoration-none"><div class="card-body"><span>Pending Requests</span><h3><?= e((string) $stats['pendingApprovals']); ?></h3></div></a></div>
    <div class="col-md-4"><a href="<?= e(url('/notifications')); ?>" class="card dashboard-card text-decoration-none"><div class="card-body"><span>Unread Alerts</span><h3>0</h3></div></a></div>
</div>
<?php require base_path('app/Views/partials/dashboard-announcements.php'); ?>
<div class="card border-0 shadow-sm"><div class="card-body"><h5>Employee Self-Service</h5><p class="text-muted mb-0">This dashboard will surface your profile summary, leave balance, requests, documents, announcements, and onboarding tasks.</p></div></div>