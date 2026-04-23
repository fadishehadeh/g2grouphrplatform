<?php declare(strict_types=1); ?>
<div class="card content-card mb-4">
    <div class="card-body p-3 d-flex flex-wrap gap-2 module-nav">
        <?php if (can('reports.view_hr') || can('reports.view_team')): ?>
            <a href="<?= e(url('/reports')); ?>" class="btn btn-outline-secondary btn-sm">Overview</a>
            <a href="<?= e(url('/reports/headcount')); ?>" class="btn btn-outline-secondary btn-sm">Headcount</a>
            <a href="<?= e(url('/reports/department')); ?>" class="btn btn-outline-secondary btn-sm">Departments</a>
            <a href="<?= e(url('/reports/leave-usage')); ?>" class="btn btn-outline-secondary btn-sm">Leave Usage</a>
            <a href="<?= e(url('/reports/new-joiners')); ?>" class="btn btn-outline-secondary btn-sm">New Joiners</a>
            <a href="<?= e(url('/reports/exits')); ?>" class="btn btn-outline-secondary btn-sm">Exits</a>
            <a href="<?= e(url('/reports/documents')); ?>" class="btn btn-outline-secondary btn-sm">Documents</a>
        <?php endif; ?>
        <?php if (can('audit.view')): ?>
            <a href="<?= e(url('/reports/audit')); ?>" class="btn btn-outline-secondary btn-sm">Audit Logs</a>
        <?php endif; ?>
    </div>
</div>
