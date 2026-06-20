<?php declare(strict_types=1); ?>
<div class="card content-card mb-4">
    <div class="card-body p-3 d-flex flex-wrap gap-2 module-nav">
        <a href="<?= e(url('/settings')); ?>" class="btn btn-outline-secondary btn-sm">General Settings</a>
        <a href="<?= e(url('/settings/attendance')); ?>" class="btn btn-outline-secondary btn-sm">Attendance Overview</a>
        <a href="<?= e(url('/settings/attendance/records')); ?>" class="btn btn-outline-secondary btn-sm">Attendance Register</a>
        <a href="<?= e(url('/settings/attendance/assignments')); ?>" class="btn btn-outline-secondary btn-sm">Assignments</a>
        <a href="<?= e(url('/settings/shifts')); ?>" class="btn btn-outline-secondary btn-sm">Shifts</a>
        <a href="<?= e(url('/settings/schedules')); ?>" class="btn btn-outline-secondary btn-sm">Schedules</a>
        <a href="<?= e(url('/settings/attendance-statuses')); ?>" class="btn btn-outline-secondary btn-sm">Statuses</a>
        <?php if (has_role('super_admin')): ?>
        <a href="<?= e(url('/settings/system')); ?>" class="btn btn-outline-secondary btn-sm">System Settings</a>
        <?php endif; ?>
    </div>
</div>
