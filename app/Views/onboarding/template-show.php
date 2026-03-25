<?php declare(strict_types=1); ?>
<?php require base_path('app/Views/partials/workflow-nav.php'); ?>
<?php $activeClass = (int) ($templateDetail['is_active'] ?? 0) === 1 ? 'text-bg-success' : 'text-bg-secondary'; ?>

<div class="card content-card mb-4">
    <div class="card-body p-4 d-flex flex-column flex-md-row justify-content-between align-items-md-start gap-3">
        <div>
            <h5 class="mb-1"><?= e((string) $templateDetail['name']); ?></h5>
            <div class="text-muted mb-2"><?= e((string) (($templateDetail['description'] ?? '') !== '' ? $templateDetail['description'] : 'No description added.')); ?></div>
            <div class="small text-muted">Created <?= e((string) substr((string) ($templateDetail['created_at'] ?? ''), 0, 10)); ?> · By <?= e((string) (($templateDetail['created_by_name'] ?? '') !== '' ? $templateDetail['created_by_name'] : 'System')); ?> · <?= e((string) count($tasks ?? [])); ?> task(s)</div>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <span class="badge <?= e($activeClass); ?>"><?= e((int) ($templateDetail['is_active'] ?? 0) === 1 ? 'Active' : 'Inactive'); ?></span>
            <a href="<?= e(url('/onboarding/templates')); ?>" class="btn btn-outline-secondary">Back to Templates</a>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-4">
        <div class="card content-card h-100">
            <div class="card-body p-4">
                <h5 class="mb-2">Add Template Task</h5>
                <p class="text-muted small mb-4">Use this page to enrich template tasks with assignee roles, required flags, and ordering.</p>
                <form method="post" action="<?= e(url('/onboarding/templates/' . $templateDetail['id'] . '/tasks')); ?>">
                    <?= csrf_field(); ?>
                    <div class="mb-3"><label class="form-label">Task Name *</label><input type="text" name="new_task_name" class="form-control" value="<?= e((string) old('new_task_name', '')); ?>" required></div>
                    <div class="mb-3"><label class="form-label">Description</label><textarea name="new_description" class="form-control" rows="3"><?= e((string) old('new_description', '')); ?></textarea></div>
                    <div class="mb-3"><label class="form-label">Assignee Role</label><select name="new_assignee_role_id" class="form-select"><option value="">Unassigned</option><?php foreach (($roles ?? []) as $role): ?><option value="<?= e((string) $role['id']); ?>" <?= (string) old('new_assignee_role_id', '') === (string) $role['id'] ? 'selected' : ''; ?>><?= e((string) $role['name']); ?></option><?php endforeach; ?></select></div>
                    <div class="row g-3"><div class="col-md-6"><label class="form-label">Required</label><select name="new_is_required" class="form-select"><?php foreach (['1' => 'Required', '0' => 'Optional'] as $value => $label): ?><option value="<?= e($value); ?>" <?= (string) old('new_is_required', '1') === $value ? 'selected' : ''; ?>><?= e($label); ?></option><?php endforeach; ?></select></div><div class="col-md-6"><label class="form-label">Sort Order</label><input type="number" min="1" name="new_sort_order" class="form-control" value="<?= e((string) old('new_sort_order', (string) ($nextSortOrder ?? 1))); ?>"></div></div>
                    <button type="submit" class="btn btn-primary mt-4 w-100">Add Task</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-xl-8">
        <div class="card content-card">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center gap-3 mb-4">
                    <div>
                        <h5 class="mb-1">Configured Tasks</h5>
                        <p class="text-muted mb-0">Existing tasks can be updated inline without changing the overall onboarding flow.</p>
                    </div>
                </div>

                <?php if (($tasks ?? []) === []): ?>
                    <div class="empty-state">No tasks exist for this template yet.</div>
                <?php else: ?>
                    <div class="d-grid gap-3">
                        <?php foreach ($tasks as $task): ?>
                            <div class="border rounded-4 p-3">
                                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-start gap-2 mb-3">
                                    <div>
                                        <div class="fw-semibold"><?= e((string) $task['task_name']); ?></div>
                                        <div class="small text-muted">Code <?= e((string) $task['task_code']); ?> · Role <?= e((string) (($task['role_name'] ?? '') !== '' ? $task['role_name'] : 'Unassigned')); ?> · <?= e((int) ($task['is_required'] ?? 0) === 1 ? 'Required' : 'Optional'); ?></div>
                                    </div>
                                    <span class="badge text-bg-light border">Sort <?= e((string) ($task['sort_order'] ?? 1)); ?></span>
                                </div>
                                <form method="post" action="<?= e(url('/onboarding/templates/tasks/' . $task['id'] . '/update')); ?>" class="row g-2">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="template_id" value="<?= e((string) $templateDetail['id']); ?>">
                                    <div class="col-md-4"><label class="form-label small text-muted">Task Name</label><input type="text" name="task_name" class="form-control form-control-sm" value="<?= e((string) $task['task_name']); ?>" required></div>
                                    <div class="col-md-4"><label class="form-label small text-muted">Description</label><input type="text" name="description" class="form-control form-control-sm" value="<?= e((string) ($task['description'] ?? '')); ?>"></div>
                                    <div class="col-md-4"><label class="form-label small text-muted">Assignee Role</label><select name="assignee_role_id" class="form-select form-select-sm"><option value="">Unassigned</option><?php foreach (($roles ?? []) as $role): ?><option value="<?= e((string) $role['id']); ?>" <?= (string) ($task['assignee_role_id'] ?? '') === (string) $role['id'] ? 'selected' : ''; ?>><?= e((string) $role['name']); ?></option><?php endforeach; ?></select></div>
                                    <div class="col-md-3"><label class="form-label small text-muted">Required</label><select name="is_required" class="form-select form-select-sm"><?php foreach (['1' => 'Required', '0' => 'Optional'] as $value => $label): ?><option value="<?= e($value); ?>" <?= (string) ($task['is_required'] ?? 1) === $value ? 'selected' : ''; ?>><?= e($label); ?></option><?php endforeach; ?></select></div>
                                    <div class="col-md-3"><label class="form-label small text-muted">Sort Order</label><input type="number" min="1" name="sort_order" class="form-control form-control-sm" value="<?= e((string) ($task['sort_order'] ?? 1)); ?>"></div>
                                    <div class="col-md-6 d-flex align-items-end"><button type="submit" class="btn btn-sm btn-outline-primary w-100">Save Task</button></div>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>