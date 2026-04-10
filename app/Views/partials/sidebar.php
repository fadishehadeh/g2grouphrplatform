<?php declare(strict_types=1); $user = auth()->user(); $profileUrl = !empty($user['employee_id']) ? url('/employees/' . $user['employee_id']) : '#'; $documentUrl = !empty($user['employee_id']) ? url('/employees/' . $user['employee_id'] . '/documents/upload') : '#'; ?>
<aside class="sidebar" id="appSidebar">
    <button class="sidebar-close" id="sidebarClose" aria-label="Close menu"><i class="bi bi-x-lg"></i></button>
    <div class="sidebar-brand">
        <img src="<?= e(asset((string) config('app.brand.logo_asset', 'images/g2group.svg'))); ?>" alt="<?= e((string) config('app.brand.display_name', config('app.name'))); ?>" class="brand-logo">
        <div class="sidebar-brand-text">
            <h5>HR Management System</h5>
            <small>People operations platform</small>
        </div>
    </div>
    <nav class="sidebar-nav">
        <a href="<?= e(url('/dashboard')); ?>" class="sidebar-link"><i class="bi bi-grid"></i> Dashboard</a>
        <?php if (has_role(['super_admin', 'hr_admin'])): ?>
            <a href="<?= e(url('/employees')); ?>" class="sidebar-link"><i class="bi bi-people"></i> Employees</a>
            <a href="<?= e(url('/admin/users')); ?>" class="sidebar-link"><i class="bi bi-person-gear"></i> User Access</a>
            <a href="<?= e(url('/admin/roles')); ?>" class="sidebar-link"><i class="bi bi-shield-lock"></i> Roles & Permissions</a>
            <a href="<?= e(url('/admin/companies')); ?>" class="sidebar-link"><i class="bi bi-building"></i> Companies</a>
            <a href="<?= e(url('/admin/structure')); ?>" class="sidebar-link"><i class="bi bi-diagram-3"></i> Structure</a>
            <a href="<?= e(url('/leave/approvals')); ?>" class="sidebar-link"><i class="bi bi-calendar-check"></i> Leave Management</a>
            <a href="<?= e(url('/documents')); ?>" class="sidebar-link"><i class="bi bi-folder2-open"></i> Documents</a>
            <?php if (can('onboarding.manage')): ?><a href="<?= e(url('/onboarding')); ?>" class="sidebar-link"><i class="bi bi-person-plus"></i> Onboarding</a><?php endif; ?>
            <?php if (can('offboarding.manage')): ?><a href="<?= e(url('/offboarding')); ?>" class="sidebar-link"><i class="bi bi-box-arrow-right"></i> Offboarding</a><?php endif; ?>
        <?php endif; ?>
        <?php if (can('leave.approve_team')): ?>
            <a href="<?= e(url('/leave/approvals')); ?>" class="sidebar-link"><i class="bi bi-check2-square"></i> Approvals</a>
        <?php endif; ?>
        <?php if (!empty($user['employee_id']) && can('employee.view_self')): ?>
            <a href="<?= e($profileUrl); ?>" class="sidebar-link"><i class="bi bi-person"></i> My Profile</a>
        <?php endif; ?>
        <?php if (!empty($user['employee_id']) && can('leave.view_self')): ?>
            <a href="<?= e(url('/leave/my')); ?>" class="sidebar-link"><i class="bi bi-calendar-plus"></i> My Leave</a>
        <?php endif; ?>
        <?php if (can('leave.submit')): ?>
            <a href="<?= e(url('/leave/request')); ?>" class="sidebar-link"><i class="bi bi-plus-square"></i> Request Leave</a>
        <?php endif; ?>
        <?php if (!empty($user['employee_id']) && (can('documents.view_self') || can('documents.upload_self'))): ?>
            <a href="<?= e($documentUrl); ?>" class="sidebar-link"><i class="bi bi-folder"></i> My Documents</a>
        <?php endif; ?>
        <?php if (can('letters.request') || can('letters.manage')): ?>
            <a href="<?= e(url(can('letters.manage') ? '/letters/admin' : '/letters/my')); ?>" class="sidebar-link"><i class="bi bi-envelope-paper"></i> Letters</a>
        <?php endif; ?>
        <?php if (can('announcements.view') || can('announcements.manage')): ?>
            <a href="<?= e(url('/announcements')); ?>" class="sidebar-link"><i class="bi bi-megaphone"></i> Announcements</a>
        <?php endif; ?>
        <?php if (can('notifications.view_self')): ?>
            <a href="<?= e(url('/notifications')); ?>" class="sidebar-link"><i class="bi bi-bell"></i> Notifications</a>
        <?php endif; ?>
        <?php if (can('reports.view_hr') || can('reports.view_team')): ?>
            <a href="<?= e(url('/reports')); ?>" class="sidebar-link"><i class="bi bi-bar-chart"></i> Reports</a>
        <?php endif; ?>
        <?php if (has_role(['super_admin', 'hr_admin'])): ?>
            <a href="<?= e(url('/admin/jobs')); ?>" class="sidebar-link"><i class="bi bi-briefcase"></i> Jobs & Careers</a>
        <?php endif; ?>
        <?php if (can('settings.manage')): ?>
            <a href="<?= e(url('/settings')); ?>" class="sidebar-link"><i class="bi bi-gear"></i> Settings</a>
        <?php endif; ?>
    </nav>
    <div class="sidebar-user small text-white-50">
        Signed in as <?= e(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')); ?>
    </div>
</aside>