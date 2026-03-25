<?php declare(strict_types=1); ?>
<div class="card content-card mb-4">
    <div class="card-body p-3 d-flex flex-wrap gap-2">
        <?php if (can('leave.view_self')): ?>
            <a href="<?= e(url('/leave/my')); ?>" class="btn btn-outline-secondary btn-sm">My Leave</a>
        <?php endif; ?>
        <?php if (can('leave.view_self') || can('leave.approve_team') || can('leave.manage_types')): ?>
            <a href="<?= e(url('/leave/balances')); ?>" class="btn btn-outline-secondary btn-sm">Balances</a>
            <a href="<?= e(url('/leave/requests')); ?>" class="btn btn-outline-secondary btn-sm">Requests</a>
            <a href="<?= e(url('/leave/calendar')); ?>" class="btn btn-outline-secondary btn-sm">Calendar</a>
        <?php endif; ?>
        <?php if (can('leave.submit')): ?>
            <a href="<?= e(url('/leave/request')); ?>" class="btn btn-outline-secondary btn-sm">Request Leave</a>
        <?php endif; ?>
        <?php if (can('leave.approve_team') || can('leave.manage_types')): ?>
            <a href="<?= e(url('/leave/approvals')); ?>" class="btn btn-outline-secondary btn-sm">Approvals</a>
        <?php endif; ?>
        <?php if (can('leave.manage_types')): ?>
            <a href="<?= e(url('/admin/leave/types')); ?>" class="btn btn-outline-secondary btn-sm">Leave Types</a>
            <a href="<?= e(url('/admin/leave/policies')); ?>" class="btn btn-outline-secondary btn-sm">Policies</a>
            <a href="<?= e(url('/admin/leave/holidays')); ?>" class="btn btn-outline-secondary btn-sm">Holidays</a>
            <a href="<?= e(url('/admin/leave/weekends')); ?>" class="btn btn-outline-secondary btn-sm">Weekends</a>
        <?php endif; ?>
    </div>
</div>