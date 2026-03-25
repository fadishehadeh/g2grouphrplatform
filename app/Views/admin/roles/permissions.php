<?php declare(strict_types=1); ?>
<?php require base_path('app/Views/partials/admin-nav.php'); ?>
<?php $selected = old('permission_ids', $selectedPermissionIds ?? []); $selected = is_array($selected) ? array_map('strval', $selected) : []; ?>
<div class="card content-card mb-4"><div class="card-body p-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3"><div><h5 class="mb-1">Permission Assignment</h5><p class="text-muted mb-0">Configure access for <strong><?= e((string) ($role['name'] ?? 'Role')); ?></strong> (<?= e((string) ($role['code'] ?? '')); ?>).</p></div><a href="<?= e(url('/admin/roles')); ?>" class="btn btn-outline-secondary">Back to Roles</a></div></div>
<form method="post" action="<?= e(url('/admin/roles/' . ($role['id'] ?? '') . '/permissions')); ?>">
    <?= csrf_field(); ?>
    <div class="row g-4">
        <?php foreach (($permissionGroups ?? []) as $module => $permissions): ?>
            <div class="col-12">
                <div class="card content-card"><div class="card-body p-4"><div class="d-flex justify-content-between align-items-center mb-3"><h6 class="mb-0"><?= e(ucwords(str_replace('_', ' ', (string) $module))); ?></h6><span class="badge text-bg-light"><?= e((string) count($permissions)); ?> item(s)</span></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th style="width: 64px;">Allow</th><th>Permission</th><th>Description</th></tr></thead><tbody><?php foreach ($permissions as $permission): ?><tr><td><input type="checkbox" class="form-check-input" name="permission_ids[]" value="<?= e((string) $permission['id']); ?>" <?= in_array((string) $permission['id'], $selected, true) ? 'checked' : ''; ?>></td><td><div class="fw-semibold"><?= e((string) $permission['action_name']); ?></div><div class="small text-muted"><?= e((string) $permission['code']); ?></div></td><td><?= e((string) ($permission['description'] ?? '')); ?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="card content-card mt-4"><div class="card-body p-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3"><p class="text-muted small mb-0">Save the permission matrix to replace the current assignments for this role.</p><button type="submit" class="btn btn-primary">Save Permissions</button></div></div>
</form>