<?php declare(strict_types=1); ?>
<?php require base_path('app/Views/partials/structure-nav.php'); ?>
<div class="row g-4">
    <div class="col-xl-4">
        <div class="card content-card h-100">
            <div class="card-body p-4">
                <h5 class="mb-2">Add <?= e($pageTitle ?? $title ?? 'Record'); ?></h5>
                <p class="text-muted small mb-4"><?= e($description ?? ''); ?></p>
                <form method="post" action="<?= e(url($formAction)); ?>">
                    <?= csrf_field(); ?>
                    <?php foreach ($formFields as $field): ?>
                        <?php $value = old($field['name'], $field['value'] ?? ''); ?>
                        <div class="mb-3">
                            <label class="form-label"><?= e($field['label']); ?><?= !empty($field['required']) ? ' *' : ''; ?></label>
                            <?php if (($field['type'] ?? 'text') === 'select'): ?>
                                <select name="<?= e($field['name']); ?>" class="form-select" <?= !empty($field['required']) ? 'required' : ''; ?>>
                                    <option value="">Select <?= e($field['label']); ?></option>
                                    <?php foreach (($field['options'] ?? []) as $optionValue => $optionLabel): ?>
                                        <option value="<?= e((string) $optionValue); ?>" <?= (string) $value === (string) $optionValue ? 'selected' : ''; ?>><?= e($optionLabel); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif (($field['type'] ?? 'text') === 'textarea'): ?>
                                <textarea name="<?= e($field['name']); ?>" class="form-control" rows="3"><?= e((string) $value); ?></textarea>
                            <?php else: ?>
                                <input type="<?= e($field['type'] ?? 'text'); ?>" name="<?= e($field['name']); ?>" class="form-control" value="<?= e((string) $value); ?>" <?= !empty($field['required']) ? 'required' : ''; ?>>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <button type="submit" class="btn btn-primary w-100">Save <?= e($pageTitle ?? $title ?? 'Record'); ?></button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-xl-8">
        <div class="card content-card">
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                    <div>
                        <h5 class="mb-1"><?= e($pageTitle ?? $title ?? 'Records'); ?></h5>
                        <p class="text-muted mb-0"><?= e($description ?? ''); ?></p>
                    </div>
                    <form method="get" action="<?= e(url($formAction)); ?>" class="d-flex flex-column flex-md-row gap-2">
                        <input type="text" name="q" class="form-control" placeholder="Search records..." value="<?= e((string) ($search ?? '')); ?>">
                        <?php foreach (($filters ?? []) as $filter): ?>
                            <?php if (($filter['type'] ?? 'select') === 'select'): ?>
                                <?php $filterValue = (string) ($filter['value'] ?? ''); ?>
                                <select name="<?= e($filter['name']); ?>" class="form-select">
                                    <?php foreach (($filter['options'] ?? []) as $optionValue => $optionLabel): ?>
                                        <option value="<?= e((string) $optionValue); ?>" <?= $filterValue === (string) $optionValue ? 'selected' : ''; ?>><?= e((string) $optionLabel); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <button type="submit" class="btn btn-outline-secondary">Search</button>
                    </form>
                </div>
                <?php if ($items === []): ?>
                    <div class="empty-state">No records found yet for this section.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead><tr><?php foreach ($columns as $label): ?><th><?= e($label); ?></th><?php endforeach; ?><?php if (($activeSection ?? '') === 'companies'): ?><th></th><?php endif; ?></tr></thead>
                            <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <?php foreach ($columns as $key => $label): ?>
                                        <td>
                                            <?php $cell = $item[$key] ?? ''; ?>
                                            <?php if ($key === 'status'): ?>
                                                <span class="badge <?= $cell === 'active' ? 'text-bg-success' : 'text-bg-secondary'; ?>"><?= e((string) $cell); ?></span>
                                            <?php elseif ($key === 'name' && ($activeSection ?? '') === 'companies' && isset($item['id'])): ?>
                                                <a href="<?= e(url('/admin/companies/' . (int) $item['id'])); ?>" class="fw-semibold text-decoration-none"><?= e((string) $cell); ?></a>
                                            <?php else: ?>
                                                <?= e((string) $cell); ?>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                    <?php if (($activeSection ?? '') === 'companies' && isset($item['id'])): ?>
                                        <td><a href="<?= e(url('/admin/companies/' . (int) $item['id'])); ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-gear"></i> Manage</a></td>
                                    <?php endif; ?>
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