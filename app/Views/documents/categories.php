<?php declare(strict_types=1); ?>
<?php require base_path('app/Views/partials/document-nav.php'); ?>
<div class="row g-4">
    <div class="col-xl-4">
        <div class="card content-card h-100">
            <div class="card-body p-4">
                <h5 class="mb-2">Add Document Category</h5>
                <p class="text-muted small mb-4">Define reusable document types and whether they require expiry tracking.</p>
                <form method="post" action="<?= e(url('/documents/categories')); ?>">
                    <?= csrf_field(); ?>
                    <div class="mb-3"><label class="form-label">Name *</label><input type="text" name="name" class="form-control" value="<?= e((string) old('name', '')); ?>" required></div>
                    <div class="mb-3"><label class="form-label">Code *</label><input type="text" name="code" class="form-control" value="<?= e((string) old('code', '')); ?>" required></div>
                    <div class="mb-3"><label class="form-label">Requires Expiry</label><select name="requires_expiry" class="form-select"><?php foreach ([1 => 'Yes', 0 => 'No'] as $value => $label): ?><option value="<?= e((string) $value); ?>" <?= (string) old('requires_expiry', '0') === (string) $value ? 'selected' : ''; ?>><?= e($label); ?></option><?php endforeach; ?></select></div>
                    <div class="mb-3"><label class="form-label">Status</label><select name="is_active" class="form-select"><?php foreach ([1 => 'Active', 0 => 'Inactive'] as $value => $label): ?><option value="<?= e((string) $value); ?>" <?= (string) old('is_active', '1') === (string) $value ? 'selected' : ''; ?>><?= e($label); ?></option><?php endforeach; ?></select></div>
                    <button type="submit" class="btn btn-primary w-100">Save Category</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-xl-8">
        <div class="card content-card">
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                    <div>
                        <h5 class="mb-1">Configured Categories</h5>
                        <p class="text-muted mb-0">Keep document classification consistent across onboarding and employee record updates.</p>
                    </div>
                    <form method="get" action="<?= e(url('/documents/categories')); ?>" class="d-flex gap-2">
                        <input type="text" name="q" class="form-control" placeholder="Search categories..." value="<?= e((string) ($search ?? '')); ?>">
                        <button type="submit" class="btn btn-outline-secondary">Search</button>
                    </form>
                </div>
                <?php if (($categories ?? []) === []): ?>
                    <div class="empty-state">No document categories found for the current search.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Name</th><th>Code</th><th>Requires Expiry</th><th>Status</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td><?= e((string) $category['name']); ?></td>
                                    <td><?= e((string) $category['code']); ?></td>
                                    <td><?= e((string) ((int) $category['requires_expiry'] === 1 ? 'Yes' : 'No')); ?></td>
                                    <td><span class="badge <?= (int) $category['is_active'] === 1 ? 'text-bg-success' : 'text-bg-secondary'; ?>"><?= e((string) ((int) $category['is_active'] === 1 ? 'Active' : 'Inactive')); ?></span></td>
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