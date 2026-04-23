<?php declare(strict_types=1); ?>
<?php require base_path('app/Views/partials/document-nav.php'); ?>
<div class="row g-4">
    <div class="col-xl-4">
        <div class="card content-card h-100">
            <div class="card-body p-4">
                <h5 class="mb-2">Add Document Type</h5>
                <p class="text-muted small mb-4">Define specific document types employees can upload. Each type is linked to a category and controls whether an expiry date is required.</p>
                <form method="post" action="<?= e(url('/documents/types')); ?>">
                    <?= csrf_field(); ?>
                    <div class="mb-3">
                        <label class="form-label">Name *</label>
                        <input type="text" name="name" class="form-control"
                               value="<?= e((string) old('name', '')); ?>" required
                               placeholder="e.g. Passport, Qatar ID">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Category *</label>
                        <select name="category_id" class="form-select" required>
                            <option value="">Select category</option>
                            <?php foreach (($categories ?? []) as $cat): ?>
                                <option value="<?= e((string) $cat['id']); ?>"
                                    <?= (string) old('category_id', '') === (string) $cat['id'] ? 'selected' : ''; ?>>
                                    <?= e((string) $cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Requires Expiry Date</label>
                        <select name="requires_expiry" class="form-select">
                            <?php foreach ([1 => 'Yes — expiry date is mandatory', 0 => 'No — expiry date is optional'] as $value => $label): ?>
                                <option value="<?= e((string) $value); ?>"
                                    <?= (string) old('requires_expiry', '0') === (string) $value ? 'selected' : ''; ?>>
                                    <?= e($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Sort Order</label>
                        <input type="number" name="sort_order" class="form-control"
                               value="<?= e((string) old('sort_order', '0')); ?>" min="0">
                        <div class="form-text">Lower numbers appear first within the same category.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="is_active" class="form-select">
                            <?php foreach ([1 => 'Active', 0 => 'Inactive'] as $value => $label): ?>
                                <option value="<?= e((string) $value); ?>"
                                    <?= (string) old('is_active', '1') === (string) $value ? 'selected' : ''; ?>>
                                    <?= e($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Save Document Type</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-xl-8">
        <div class="card content-card">
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                    <div>
                        <h5 class="mb-1">Configured Document Types</h5>
                        <p class="text-muted mb-0">These types appear in the document upload form, grouped by category.</p>
                    </div>
                    <form method="get" action="<?= e(url('/documents/types')); ?>" class="d-flex gap-2">
                        <input type="text" name="q" class="form-control" placeholder="Search types..."
                               value="<?= e((string) ($search ?? '')); ?>">
                        <button type="submit" class="btn btn-outline-secondary">Search</button>
                    </form>
                </div>
                <?php if (($types ?? []) === []): ?>
                    <div class="empty-state">No document types found. Add one using the form on the left.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Category</th>
                                    <th>Expiry Required</th>
                                    <th>Order</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($types as $type): ?>
                                <tr>
                                    <td class="fw-semibold"><?= e((string) $type['name']); ?></td>
                                    <td class="text-muted small"><?= e((string) $type['category_name']); ?></td>
                                    <td>
                                        <?php if ((int) $type['requires_expiry'] === 1): ?>
                                            <span class="badge text-bg-warning">Required</span>
                                        <?php else: ?>
                                            <span class="badge text-bg-secondary">Optional</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted"><?= e((string) $type['sort_order']); ?></td>
                                    <td>
                                        <span class="badge <?= (int) $type['is_active'] === 1 ? 'text-bg-success' : 'text-bg-secondary'; ?>">
                                            <?= (int) $type['is_active'] === 1 ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="<?= e(url('/documents/types/' . (int) $type['id'] . '/edit')); ?>"
                                           class="btn btn-outline-secondary btn-sm">Edit</a>
                                    </td>
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
