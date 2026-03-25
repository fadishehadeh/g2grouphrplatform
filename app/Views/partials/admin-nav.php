<?php declare(strict_types=1); ?>
<?php $activeSection = $activeSection ?? ''; ?>
<div class="card content-card mb-4">
    <div class="card-body p-3 d-flex flex-wrap gap-2">
        <a href="<?= e(url('/admin/users')); ?>" class="btn btn-sm <?= $activeSection === 'users' ? 'btn-primary' : 'btn-outline-secondary'; ?>">User Access</a>
        <a href="<?= e(url('/admin/roles')); ?>" class="btn btn-sm <?= $activeSection === 'roles' ? 'btn-primary' : 'btn-outline-secondary'; ?>">Roles & Permissions</a>
    </div>
</div>