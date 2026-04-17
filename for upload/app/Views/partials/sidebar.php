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
    <nav class="sidebar-nav" id="sidebarNavAccordion">

        <a href="<?= e(url('/dashboard')); ?>" class="sidebar-link<?= $currentPath === '/dashboard' ? ' active' : ''; ?>">
            <i class="bi bi-grid"></i> Dashboard
        </a>

        <?php /* DEBUG */ echo '<!-- DBG role=' . e($user['role_code'] ?? 'NULL') . ' emp_view_all=' . (can('employee.view_all') ? '1' : '0') . ' reports_hr=' . (can('reports.view_hr') ? '1' : '0') . ' perms=' . implode(',', $user['permissions'] ?? []) . ' -->'; ?>
        <?php if (can('employee.view_all')): ?>

        <?php
            $peopleId = $nextId();
            $peopleOpen = $groupOpen(['/employees', '/onboarding', '/offboarding']);
        ?>
        <div class="sidebar-group">
            <a class="sidebar-group-toggle<?= $peopleOpen === 'show' ? '' : ' collapsed'; ?>" href="#<?= e($peopleId); ?>" data-bs-toggle="collapse" aria-expanded="<?= $peopleOpen === 'show' ? 'true' : 'false'; ?>">
                <i class="bi bi-people"></i> People
                <i class="bi bi-chevron-down sidebar-chevron ms-auto"></i>
            </a>
            <div class="collapse <?= e($peopleOpen); ?>" id="<?= e($peopleId); ?>" data-bs-parent="#sidebarNavAccordion">
                <a href="<?= e(url('/employees')); ?>" class="sidebar-sublink"><i class="bi bi-person-lines-fill"></i> Employees</a>
                <a href="<?= e(url('/employees/org-chart')); ?>" class="sidebar-sublink"><i class="bi bi-diagram-3"></i> Org Chart</a>
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
            <a class="sidebar-group-toggle<?= $accessOpen === 'show' ? '' : ' collapsed'; ?>" href="#<?= e($accessId); ?>" data-bs-toggle="collapse" aria-expanded="<?= $accessOpen === 'show' ? 'true' : 'false'; ?>">
                <i class="bi bi-shield-lock"></i> Access &amp; Org
                <i class="bi bi-chevron-down sidebar-chevron ms-auto"></i>
            </a>
            <div class="collapse <?= e($accessOpen); ?>" id="<?= e($accessId); ?>" data-bs-parent="#sidebarNavAccordion">
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
            <a class="sidebar-group-toggle<?= $leaveHrOpen === 'show' ? '' : ' collapsed'; ?>" href="#<?= e($leaveHrId); ?>" data-bs-toggle="collapse" aria-expanded="<?= $leaveHrOpen === 'show' ? 'true' : 'false'; ?>">
                <i class="bi bi-calendar-check"></i> Leave
                <i class="bi bi-chevron-down sidebar-chevron ms-auto"></i>
            </a>
            <div class="collapse <?= e($leaveHrOpen); ?>" id="<?= e($leaveHrId); ?>" data-bs-parent="#sidebarNavAccordion">
                <a href="<?= e(url('/leave/approvals')); ?>" class="sidebar-sublink"><i class="bi bi-calendar-check"></i> Leave Management</a>
            </div>
        </div>

        <?php
            $docsHrId = $nextId();
            $docsHrOpen = $groupOpen(['/documents']);
        ?>
        <div class="sidebar-group">
            <a class="sidebar-group-toggle<?= $docsHrOpen === 'show' ? '' : ' collapsed'; ?>" href="#<?= e($docsHrId); ?>" data-bs-toggle="collapse" aria-expanded="<?= $docsHrOpen === 'show' ? 'true' : 'false'; ?>">
                <i class="bi bi-folder2-open"></i> Documents
                <i class="bi bi-chevron-down sidebar-chevron ms-auto"></i>
            </a>
            <div class="collapse <?= e($docsHrOpen); ?>" id="<?= e($docsHrId); ?>" data-bs-parent="#sidebarNavAccordion">
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
            <a class="sidebar-group-toggle<?= $recruOpen === 'show' ? '' : ' collapsed'; ?>" href="#<?= e($recruId); ?>" data-bs-toggle="collapse" aria-expanded="<?= $recruOpen === 'show' ? 'true' : 'false'; ?>">
                <i class="bi bi-briefcase"></i> Recruitment
                <i class="bi bi-chevron-down sidebar-chevron ms-auto"></i>
            </a>
            <div class="collapse <?= e($recruOpen); ?>" id="<?= e($recruId); ?>" data-bs-parent="#sidebarNavAccordion">
                <a href="<?= e(url('/admin/jobs')); ?>" class="sidebar-sublink"><i class="bi bi-briefcase"></i> Jobs &amp; Careers</a>
            </div>
        </div>

        <?php endif; ?>

        <?php if (can('leave.approve_team') && !can('employee.view_all')): ?>
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
            <a class="sidebar-group-toggle<?= $myOpen === 'show' ? '' : ' collapsed'; ?>" href="#<?= e($myId); ?>" data-bs-toggle="collapse" aria-expanded="<?= $myOpen === 'show' ? 'true' : 'false'; ?>">
                <i class="bi bi-person-circle"></i> My Space
                <i class="bi bi-chevron-down sidebar-chevron ms-auto"></i>
            </a>
            <div class="collapse <?= e($myOpen); ?>" id="<?= e($myId); ?>" data-bs-parent="#sidebarNavAccordion">
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
            <a class="sidebar-group-toggle<?= $commsOpen === 'show' ? '' : ' collapsed'; ?>" href="#<?= e($commsId); ?>" data-bs-toggle="collapse" aria-expanded="<?= $commsOpen === 'show' ? 'true' : 'false'; ?>">
                <i class="bi bi-chat-dots"></i> Communications
                <i class="bi bi-chevron-down sidebar-chevron ms-auto"></i>
            </a>
            <div class="collapse <?= e($commsOpen); ?>" id="<?= e($commsId); ?>" data-bs-parent="#sidebarNavAccordion">
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
<script>
(function () {
    var nav = document.getElementById('sidebarNavAccordion');
    if (!nav) return;

    nav.querySelectorAll('.sidebar-group-toggle').forEach(function (toggle) {
        // Remove Bootstrap's data-api so it doesn't double-fire
        toggle.removeAttribute('data-bs-toggle');

        toggle.addEventListener('click', function (e) {
            e.preventDefault();

            var href = this.getAttribute('href');
            if (!href) return;
            var target = document.querySelector(href);
            if (!target) return;

            var isOpen = target.classList.contains('show');

            // Close every other open group
            nav.querySelectorAll('.collapse.show').forEach(function (el) {
                if (el !== target) {
                    el.classList.remove('show');
                    var tog = nav.querySelector('[href="#' + el.id + '"]');
                    if (tog) { tog.setAttribute('aria-expanded', 'false'); tog.classList.add('collapsed'); }
                }
            });

            // Toggle this one
            if (isOpen) {
                target.classList.remove('show');
                this.setAttribute('aria-expanded', 'false');
                this.classList.add('collapsed');
            } else {
                target.classList.add('show');
                this.setAttribute('aria-expanded', 'true');
                this.classList.remove('collapsed');
            }
        });
    });
}());
</script>
