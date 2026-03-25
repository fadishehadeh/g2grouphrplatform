<?php declare(strict_types=1); ?>
<?php require base_path('app/Views/partials/structure-nav.php'); ?>
<div class="row g-4 mb-4">
    <div class="col-md-4 col-xl-2"><div class="card dashboard-card"><div class="card-body"><span>Companies</span><h3><?= e((string) ($counts['companies'] ?? 0)); ?></h3></div></div></div>
    <div class="col-md-4 col-xl-2"><div class="card dashboard-card"><div class="card-body"><span>Branches</span><h3><?= e((string) ($counts['branches'] ?? 0)); ?></h3></div></div></div>
    <div class="col-md-4 col-xl-2"><div class="card dashboard-card"><div class="card-body"><span>Departments</span><h3><?= e((string) ($counts['departments'] ?? 0)); ?></h3></div></div></div>
    <div class="col-md-4 col-xl-2"><div class="card dashboard-card"><div class="card-body"><span>Teams</span><h3><?= e((string) ($counts['teams'] ?? 0)); ?></h3></div></div></div>
    <div class="col-md-4 col-xl-2"><div class="card dashboard-card"><div class="card-body"><span>Job Titles</span><h3><?= e((string) ($counts['job_titles'] ?? 0)); ?></h3></div></div></div>
    <div class="col-md-4 col-xl-2"><div class="card dashboard-card"><div class="card-body"><span>Designations</span><h3><?= e((string) ($counts['designations'] ?? 0)); ?></h3></div></div></div>
    <div class="col-md-4 col-xl-2"><div class="card dashboard-card"><div class="card-body"><span>Reporting Lines</span><h3><?= e((string) ($counts['reporting_lines'] ?? 0)); ?></h3></div></div></div>
</div>
<div class="card content-card">
    <div class="card-body p-4">
        <h5 class="mb-2">Structure Management Hub</h5>
        <p class="text-muted mb-4">Set up the organization chart before expanding employee onboarding, leave approval routing, and reporting lines.</p>
        <div class="row g-3">
            <div class="col-md-6 col-xl-4"><a class="btn btn-outline-primary w-100 py-3" href="<?= e(url('/admin/companies')); ?>">Manage Companies</a></div>
            <div class="col-md-6 col-xl-4"><a class="btn btn-outline-primary w-100 py-3" href="<?= e(url('/admin/branches')); ?>">Manage Branches</a></div>
            <div class="col-md-6 col-xl-4"><a class="btn btn-outline-primary w-100 py-3" href="<?= e(url('/admin/departments')); ?>">Manage Departments</a></div>
            <div class="col-md-6 col-xl-4"><a class="btn btn-outline-primary w-100 py-3" href="<?= e(url('/admin/teams')); ?>">Manage Teams</a></div>
            <div class="col-md-6 col-xl-4"><a class="btn btn-outline-primary w-100 py-3" href="<?= e(url('/admin/job-titles')); ?>">Manage Job Titles</a></div>
            <div class="col-md-6 col-xl-4"><a class="btn btn-outline-primary w-100 py-3" href="<?= e(url('/admin/designations')); ?>">Manage Designations</a></div>
            <div class="col-md-6 col-xl-4"><a class="btn btn-outline-primary w-100 py-3" href="<?= e(url('/admin/reporting-lines')); ?>">Manage Reporting Lines</a></div>
        </div>
    </div>
</div>