<?php declare(strict_types=1); ?>
<div class="row g-4 mb-4">
    <div class="col-md-4"><a href="<?= e(url('/employees')); ?>" class="card dashboard-card text-decoration-none"><div class="card-body"><span>Team Members</span><h3><?= e((string) $stats['teamMembers']); ?></h3></div></a></div>
    <div class="col-md-4"><a href="<?= e(url('/leave/approvals')); ?>" class="card dashboard-card text-decoration-none"><div class="card-body"><span>Pending Approvals</span><h3><?= e((string) $stats['pendingApprovals']); ?></h3></div></a></div>
    <div class="col-md-4"><a href="<?= e(url('/leave/calendar')); ?>" class="card dashboard-card text-decoration-none"><div class="card-body"><span>Upcoming Leave</span><h3>0</h3></div></a></div>
</div>
<?php require base_path('app/Views/partials/dashboard-announcements.php'); ?>
<div class="card border-0 shadow-sm"><div class="card-body"><h5>Manager Workspace</h5><p class="text-muted mb-0">This area will show direct reports, leave conflicts, and quick approval actions for your team.</p></div></div>