<?php declare(strict_types=1); ?>
<?php require base_path('app/Views/partials/document-nav.php'); ?>

<div class="card content-card mb-4">
    <div class="card-body p-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <h5 class="mb-1">Edit Document Type</h5>
            <p class="text-muted mb-0"><?= e((string) $type['name']); ?> · <?= e((string) $type['category_name']); ?></p>
        </div>
        <a href="<?= e(url('/documents/types')); ?>" class="btn btn-outline-secondary">Back to Document Types</a>
    </div>
</div>

<div class="row g-4 justify-content-center">
    <div class="col-xl-6">
        <div class="card content-card">
            <div class="card-body p-4">
                <form method="post" action="<?= e(url('/documents/types/' . (int) $type['id'] . '/edit')); ?>">
                    <?= csrf_field(); ?>

                    <div class="mb-3">
                        <label class="form-label">Name *</label>
                        <input type="text" name="name" class="form-control"
                               value="<?= e((string) old('name', (string) $type['name'])); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Category *</label>
                        <select name="category_id" class="form-select" required>
                            <option value="">Select category</option>
                            <?php foreach (($categories ?? []) as $cat): ?>
                                <option value="<?= e((string) $cat['id']); ?>"
                                    <?= (string) old('category_id', (string) $type['category_id']) === (string) $cat['id'] ? 'selected' : ''; ?>>
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
                                    <?= (string) old('requires_expiry', (string) $type['requires_expiry']) === (string) $value ? 'selected' : ''; ?>>
                                    <?= e($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Sort Order</label>
                        <input type="number" name="sort_order" class="form-control"
                               value="<?= e((string) old('sort_order', (string) $type['sort_order'])); ?>" min="0">
                        <div class="form-text">Lower numbers appear first within the same category.</div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Status</label>
                        <select name="is_active" class="form-select">
                            <?php foreach ([1 => 'Active', 0 => 'Inactive'] as $value => $label): ?>
                                <option value="<?= e((string) $value); ?>"
                                    <?= (string) old('is_active', (string) $type['is_active']) === (string) $value ? 'selected' : ''; ?>>
                                    <?= e($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Inactive types are hidden from the upload form.</div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Save Changes</button>
                </form>
            </div>
        </div>
    </div>
</div>
