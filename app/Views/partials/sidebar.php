<?php
declare(strict_types=1);
$user = auth()->user();
$profileUrl   = !empty($user['employee_id']) ? url('/employees/' . $user['employee_id']) : '#';
$documentUrl  = !empty($user['employee_id']) ? url('/employees/' . $user['employee_id'] . '/documents/upload') : '#';
$currentPath  = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
$currentPath  = $currentPath === false ? '/' : $currentPath;

// Helper: return 'show' if any path in $paths starts the current URL
$groupOpen = static function (array $paths) use ($currentPath): string {
    foreach ($paths as $p) {
        if ($p !== '/' && str_starts_with($currentPath, $p)) {
            return 'show';
        }
    }
    return '';
};

$collapseId = 0;
$nextId = static function () use (&$collapseId): string {
    return 'sidebarGroup' . (++$collapseId);
};
?>
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

        {{-- Dashboard: always visible standalone link --}}
        <a href="<?= e(url('/dashboard')); ?>" class="sidebar-link<?= $currentPath === '/dashboard' ? ' active' : ''; ?>">
            <i class="bi bi-grid"></i> Dashboard
        </a>

        <?php if (has_role(['super_admin', 'hr_only'])): ?>

        <?php
            $peopleId = $nextId();
            $peopleOpen = $groupOpen(['/employees', '/onboarding', '/offboarding']);
        ?>
        <div class="sidebar-group">
            <button class="sidebar-group-toggle collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?= e($peopleId); ?>" aria-expanded="<?= $peopleOpen === 'show' ? 'true' : 'false'; ?>">
                <i class="bi bi-people"></i> People
                <i class="bi bi-chevron-down sidebar-chevron ms-auto"></i>
            </button>
            <div class="collapse <?= e($peopleOpen); ?>" id="<?= e($peopleId); ?>">
                <a href="<?= e(url('/employees')); ?>" class="sidebar-sublink"><i class="bi bi-person-lines-fill"></i> Employees</a>
                <?php if (can('onboarding.manage')): ?>
                <a href="<?= e(url('/onboarding')); ?>" class="sidebar-sublink"><i class="bi bi-person-plus"></i> Onboarding</a>
                <?php endif; ?>
                <?php if (can('offboarding.manage')): ?>
                <a href="<?= e(url('/offboarding')); ?>" class="sidebar-sublink"><i class="bi bi-box-arrow-right"></i> Offboarding</a>
                <?php endif; ?>
            </div>
        </div>

        <?php
            $accessId = $nextId();
            $accessOpen = $groupOpen(['/admin/users', '/admin/roles', '/admin/companies', '/admin/structure']);
        ?>
        <div class="sidebar-group">
            <button class="sidebar-group-toggle collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?= e($accessId); ?>" aria-expanded="<?= $accessOpen === 'show' ? 'true' : 'false'; ?>">
                <i class="bi bi-shield-lock"></i> Access &amp; Org
                <i class="bi bi-chevron-down sidebar-chevron ms-auto"></i>
            </button>
            <div class="collapse <?= e($accessOpen); ?>" id="<?= e($accessId); ?>">
                <a href="<?= e(url('/admin/users')); ?>"     class="sidebar-sublink"><i class="bi bi-person-gear"></i> User Access</a>
                <a href="<?= e(url('/admin/roles')); ?>"     class="sidebar-sublink"><i class="bi bi-shield-check"></i> Roles &amp; Permissions</a>
                <a href="<?= e(url('/admin/companies')); ?>" class="sidebar-sublink"><i class="bi bi-building"></i> Companies</a>
                <a href="<?= e(url('/admin/structure')); ?>" class="sidebar-sublink"><i class="bi bi-diagram-3"></i> Structure</a>
            </div>
        </div>

        <?php
            $leaveHrId = $nextId();
            $leaveHrOpen = $groupOpen(['/leave']);
        ?>
        <div class="sidebar-group">
            <button class="sidebar-group-toggle collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?= e($leaveHrId); ?>" aria-expanded="<?= $leaveHrOpen === 'show' ? 'true' : 'false'; ?>">
                <i class="bi bi-calendar-check"></i> Leave
                <i class="bi bi-chevron-down sidebar-chevron ms-auto"></i>
            </button>
            <div class="collapse <?= e($leaveHrOpen); ?>" id="<?= e($leaveHrId); ?>">
                <a href="<?= e(url('/leave/approvals')); ?>" class="sidebar-sublink"><i class="bi bi-calendar-check"></i> Leave Management</a>
            </div>
        </div>

        <?php
            $docsHrId = $nextId();
            $docsHrOpen = $groupOpen(['/documents']);
        ?>
        <div class="sidebar-group">
            <button class="sidebar-group-toggle collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?= e($docsHrId); ?>" aria-expanded="<?= $docsHrOpen === 'show' ? 'true' : 'false'; ?>">
                <i class="bi bi-folder2-open"></i> Documents
                <i class="bi bi-chevron-down sidebar-chevron ms-auto"></i>
            </button>
            <div class="collapse <?= e($docsHrOpen); ?>" id="<?= e($docsHrId); ?>">
                <a href="<?= e(url('/documents')); ?>"            class="sidebar-sublink"><i class="bi bi-folder2-open"></i> HR Documents</a>
                <a href="<?= e(url('/documents/categories')); ?>" class="sidebar-sublink"><i class="bi bi-tags"></i> Categories</a>
                <a href="<?= e(url('/documents/expiring')); ?>"   class="sidebar-sublink"><i class="bi bi-exclamation-circle"></i> Expiring</a>
            </div>
        </div>

        <?php
            $recruId = $nextId();
            $recruOpen = $groupOpen(['/admin/jobs', '/careers']);
        ?>
        <div class="sidebar-group">
            <button class="sidebar-group-toggle collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?= e($recruId); ?>" aria-expanded="<?= $recruOpen === 'show' ? 'true' : 'false'; ?>">
                <i class="bi bi-briefcase"></i> Recruitment
                <i class="bi bi-chevron-down sidebar-chevron ms-auto"></i>
            </button>
            <div class="collapse <?= e($recruOpen); ?>" id="<?= e($recruId); ?>">
                <a href="<?= e(url('/admin/jobs')); ?>" class="sidebar-sublink"><i class="bi bi-briefcase"></i> Jobs &amp; Careers</a>
            </div>
        </div>

        <?php endif; ?>

        <?php if (can('leave.approve_team') && !has_role(['super_admin', 'hr_only'])): ?>
        <a href="<?= e(url('/leave/approvals')); ?>" class="sidebar-link"><i class="bi bi-check2-square"></i> Approvals</a>
        <?php endif; ?>

        <?php
            $selfLinks = [];
            if (!empty($user['employee_id']) && can('employee.view_self')) { $selfLinks[] = true; }
            if (!empty($user['employee_id']) && can('leave.view_self'))     { $selfLinks[] = true; }
            if (can('leave.submit'))                                         { $selfLinks[] = true; }
            if (!empty($user['employee_id']) && (can('documents.view_self') || can('documents.upload_self'))) { $selfLinks[] = true; }
        ?>
        <?php if (!empty($selfLinks)): ?>
        <?php
            $myId = $nextId();
            $myOpen = $groupOpen(['/leave/my', '/leave/request', '/employees/' . ($user['employee_id'] ?? 0)]);
        ?>
        <div class="sidebar-group">
            <button class="sidebar-group-toggle collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?= e($myId); ?>" aria-expanded="<?= $myOpen === 'show' ? 'true' : 'false'; ?>">
                <i class="bi bi-person-circle"></i> My Space
                <i class="bi bi-chevron-down sidebar-chevron ms-auto"></i>
            </button>
            <div class="collapse <?= e($myOpen); ?>" id="<?= e($myId); ?>">
                <?php if (!empty($user['employee_id']) && can('employee.view_self')): ?>
                <a href="<?= e($profileUrl); ?>" class="sidebar-sublink"><i class="bi bi-person"></i> My Profile</a>
                <?php endif; ?>
                <?php if (!empty($user['employee_id']) && can('leave.view_self')): ?>
                <a href="<?= e(url('/leave/my')); ?>" class="sidebar-sublink"><i class="bi bi-calendar-plus"></i> My Leave</a>
                <?php endif; ?>
                <?php if (can('leave.submit')): ?>
                <a href="<?= e(url('/leave/request')); ?>" class="sidebar-sublink"><i class="bi bi-plus-square"></i> Request Leave</a>
                <?php endif; ?>
                <?php if (!empty($user['employee_id']) && (can('documents.view_self') || can('documents.upload_self'))): ?>
                <a href="<?= e($documentUrl); ?>" class="sidebar-sublink"><i class="bi bi-folder"></i> My Documents</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (can('letters.request') || can('letters.manage') || can('announcements.view') || can('announcements.manage') || can('notifications.view_self')): ?>
        <?php
            $commsId = $nextId();
            $commsOpen = $groupOpen(['/letters', '/announcements', '/notifications']);
        ?>
        <div class="sidebar-group">
            <button class="sidebar-group-toggle collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?= e($commsId); ?>" aria-expanded="<?= $commsOpen === 'show' ? 'true' : 'false'; ?>">
                <i class="bi bi-chat-dots"></i> Communications
                <i class="bi bi-chevron-down sidebar-chevron ms-auto"></i>
            </button>
            <div class="collapse <?= e($commsOpen); ?>" id="<?= e($commsId); ?>">
                <?php if (can('letters.request') || can('letters.manage')): ?>
                <a href="<?= e(url(can('letters.manage') ? '/letters/admin' : '/letters/my')); ?>" class="sidebar-sublink"><i class="bi bi-envelope-paper"></i> Letters</a>
                <?php endif; ?>
                <?php if (can('announcements.view') || can('announcements.manage')): ?>
                <a href="<?= e(url('/announcements')); ?>" class="sidebar-sublink"><i class="bi bi-megaphone"></i> Announcements</a>
                <?php endif; ?>
                <?php if (can('notifications.view_self')): ?>
                <a href="<?= e(url('/notifications')); ?>" class="sidebar-sublink"><i class="bi bi-bell"></i> Notifications</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (can('reports.view_hr') || can('reports.view_team')): ?>
        <a href="<?= e(url('/reports')); ?>" class="sidebar-link<?= str_starts_with($currentPath, '/reports') ? ' active' : ''; ?>">
            <i class="bi bi-bar-chart"></i> Reports
        </a>
        <?php endif; ?>

        <?php if (can('settings.manage')): ?>
        <a href="<?= e(url('/settings')); ?>" class="sidebar-link<?= str_starts_with($currentPath, '/settings') ? ' active' : ''; ?>">
            <i class="bi bi-gear"></i> Settings
        </a>
        <?php endif; ?>

    </nav>
    <div class="sidebar-user small text-white-50">
        Signed in as <?= e(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')); ?>
    </div>
</aside>
