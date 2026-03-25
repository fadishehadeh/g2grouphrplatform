<?php declare(strict_types=1); ?>
<?php require base_path('app/Views/partials/workflow-nav.php'); ?>

<div class="row g-4">
    <div class="col-xl-4">
        <div class="card content-card h-100">
            <div class="card-body p-4">
                <h5 class="mb-2">Add Onboarding Template</h5>
                <p class="text-muted small mb-4">Use one task per line to quickly define reusable onboarding checklists.</p>
                <form method="post" action="<?= e(url('/onboarding/templates')); ?>">
                    <?= csrf_field(); ?>
                    <div class="mb-3"><label class="form-label">Template Name *</label><input type="text" name="name" class="form-control" value="<?= e((string) old('name', '')); ?>" required></div>
                    <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3"><?= e((string) old('description', '')); ?></textarea></div>
                    <div class="mb-3"><label class="form-label">Checklist Tasks *</label><textarea name="task_lines" class="form-control" rows="8" placeholder="Create system account&#10;Collect identity documents&#10;Manager orientation" required><?= e((string) old('task_lines', '')); ?></textarea></div>
                    <div class="mb-3"><label class="form-label">Status</label><select name="is_active" class="form-select"><?php foreach ([1 => 'Active', 0 => 'Inactive'] as $value => $label): ?><option value="<?= e((string) $value); ?>" <?= (string) old('is_active', '1') === (string) $value ? 'selected' : ''; ?>><?= e($label); ?></option><?php endforeach; ?></select></div>
                    <button type="submit" class="btn btn-primary w-100">Save Template</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-xl-8">
        <div class="card content-card">
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                    <div>
                        <h5 class="mb-1">Configured Templates</h5>
                        <p class="text-muted mb-0">Templates can be reused from the employee profile to start onboarding instantly.</p>
                    </div>
                    <form method="get" action="<?= e(url('/onboarding/templates')); ?>" class="d-flex gap-2">
                        <input type="text" name="q" class="form-control" placeholder="Search templates..." value="<?= e((string) ($search ?? '')); ?>">
                        <button type="submit" class="btn btn-outline-secondary">Search</button>
                    </form>
                </div>

                <?php if (($templates ?? []) === []): ?>
                    <div class="empty-state">No onboarding templates have been created yet.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Name</th><th>Description</th><th>Tasks</th><th>Status</th><th>Created</th><th></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($templates as $template): ?>
                                <tr>
                                    <td class="fw-semibold"><?= e((string) $template['name']); ?></td>
                                    <td><?= e((string) (($template['description'] ?? '') !== '' ? $template['description'] : '—')); ?></td>
                                    <td><?= e((string) ($template['task_count'] ?? 0)); ?></td>
                                    <td><span class="badge <?= (int) ($template['is_active'] ?? 0) === 1 ? 'text-bg-success' : 'text-bg-secondary'; ?>"><?= e((string) ((int) ($template['is_active'] ?? 0) === 1 ? 'Active' : 'Inactive')); ?></span></td>
                                    <td><?= e((string) (($template['created_at'] ?? '') !== '' ? substr((string) $template['created_at'], 0, 10) : '—')); ?></td>
                                    <td class="text-end"><a href="<?= e(url('/onboarding/templates/' . $template['id'])); ?>" class="btn btn-sm btn-outline-primary">View</a></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
