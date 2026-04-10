<?php declare(strict_types=1); ?>
<div class="row g-4">
    <div class="col-xl-4">
        <div class="card content-card h-100">
            <div class="card-body p-4">
                <h5 class="mb-4">Add Job Category</h5>
                <form method="post" action="<?= e(url('/admin/jobs/categories')); ?>">
                    <?= csrf_field(); ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Category Name *</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Slug</label>
                        <input type="text" name="slug" class="form-control" placeholder="auto-generated if empty">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Bootstrap Icon Class</label>
                        <input type="text" name="icon" class="form-control" value="bi-briefcase" placeholder="e.g. bi-laptop">
                        <div class="form-text">Browse icons at <a href="https://icons.getbootstrap.com" target="_blank">icons.getbootstrap.com</a></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Sort Order</label>
                        <input type="number" name="sort_order" class="form-control" value="0" min="0">
                    </div>
                    <button type="submit" class="btn btn-danger w-100">Add Category</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-xl-8">
        <div class="card content-card">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="mb-0">Job Categories</h5>
                    <a href="<?= e(url('/admin/jobs')); ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Jobs</a>
                </div>
                <?php if (empty($categories)): ?>
                    <div class="empty-state">No categories yet.</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Category</th><th>Icon</th><th>Jobs</th><th>Order</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><i class="<?= e((string)$cat['icon']); ?> me-2 text-danger"></i><?= e((string)$cat['name']); ?></div>
                                <?php if ($cat['description']): ?><div class="small text-muted"><?= e(mb_strimwidth((string)$cat['description'], 0, 80, '...')); ?></div><?php endif; ?>
                                <div class="small text-muted"><code><?= e((string)$cat['slug']); ?></code></div>
                            </td>
                            <td><code><?= e((string)$cat['icon']); ?></code></td>
                            <td><span class="badge text-bg-light"><?= (int)$cat['job_count']; ?></span></td>
                            <td><?= (int)$cat['sort_order']; ?></td>
                            <td><span class="badge text-bg-<?= $cat['is_active'] ? 'success' : 'secondary'; ?>"><?= $cat['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
                            <td>
                                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editCat<?= $cat['id']; ?>">Edit</button>
                                <form method="post" action="<?= e(url('/admin/jobs/categories/' . $cat['id'] . '/delete')); ?>"
                                      class="d-inline" onsubmit="return confirm('Delete category?')">
                                    <?= csrf_field(); ?>
                                    <button class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            </td>
                        </tr>

                        <!-- Edit modal -->
                        <div class="modal fade" id="editCat<?= $cat['id']; ?>" tabindex="-1">
                            <div class="modal-dialog"><div class="modal-content">
                                <div class="modal-header"><h5 class="modal-title">Edit Category</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
                                <form method="post" action="<?= e(url('/admin/jobs/categories/' . $cat['id'] . '/edit')); ?>">
                                    <?= csrf_field(); ?>
                                    <div class="modal-body">
                                        <div class="mb-3"><label class="form-label">Name *</label><input type="text" name="name" class="form-control" value="<?= e((string)$cat['name']); ?>" required></div>
                                        <div class="mb-3"><label class="form-label">Slug</label><input type="text" name="slug" class="form-control" value="<?= e((string)$cat['slug']); ?>"></div>
                                        <div class="mb-3"><label class="form-label">Icon</label><input type="text" name="icon" class="form-control" value="<?= e((string)$cat['icon']); ?>"></div>
                                        <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"><?= e((string)$cat['description']); ?></textarea></div>
                                        <div class="mb-3"><label class="form-label">Sort Order</label><input type="number" name="sort_order" class="form-control" value="<?= (int)$cat['sort_order']; ?>"></div>
                                        <div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" id="actv<?= $cat['id']; ?>" <?= $cat['is_active'] ? 'checked' : ''; ?>><label class="form-check-label" for="actv<?= $cat['id']; ?>">Active</label></div>
                                    </div>
                                    <div class="modal-footer"><button class="btn btn-danger">Save</button><button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button></div>
                                </form>
                            </div></div>
                        </div>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
