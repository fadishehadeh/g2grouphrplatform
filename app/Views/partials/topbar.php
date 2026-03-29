<?php declare(strict_types=1); $user = auth()->user(); $profileUrl = !empty($user['employee_id']) ? url('/employees/' . $user['employee_id']) : '#'; $notificationCount = notification_unread_count(); ?>
<header class="topbar">
    <div class="d-flex align-items-center gap-3">
        <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="Open menu"><i class="bi bi-list"></i></button>
        <div>
            <h4 class="mb-1"><?= e($pageTitle ?? 'Dashboard'); ?></h4>
            <small class="text-muted">Welcome, <?= e($user['first_name'] ?? 'User'); ?>.</small>
        </div>
    </div>
    <div class="d-flex align-items-center gap-3">
        <?php if (can('notifications.view_self')): ?>
            <a href="<?= e(url('/notifications')); ?>" class="btn btn-light position-relative">
                <i class="bi bi-bell"></i>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= e((string) $notificationCount); ?></span>
            </a>
        <?php else: ?>
            <button type="button" class="btn btn-light" disabled>
                <i class="bi bi-bell"></i>
            </button>
        <?php endif; ?>
        <div class="dropdown">
            <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" type="button">
                <?= e($user['role_name'] ?? 'User'); ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="<?= e(url('/profile')); ?>">My Profile</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="post" action="<?= e(url('/logout')); ?>" class="px-3">
                        <?= csrf_field(); ?>
                        <button type="submit" class="btn btn-sm btn-danger w-100">Logout</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>