<?php declare(strict_types=1); ?>
<div class="card content-card mb-4">
    <div class="card-body p-3 d-flex flex-wrap gap-2">
        <?php if (can('announcements.view') || can('announcements.manage')): ?>
            <a href="<?= e(url('/announcements')); ?>" class="btn btn-outline-secondary btn-sm">Announcements</a>
        <?php endif; ?>
        <?php if (can('notifications.view_self')): ?>
            <a href="<?= e(url('/notifications')); ?>" class="btn btn-outline-secondary btn-sm">Notifications</a>
        <?php endif; ?>
    </div>
</div>