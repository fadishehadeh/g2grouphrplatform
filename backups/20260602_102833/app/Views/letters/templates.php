<?php declare(strict_types=1); ?>
<?php require base_path('app/Views/partials/letters-nav.php'); ?>

<div class="card content-card">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h5 class="mb-1">Letter Templates</h5>
                <p class="text-muted mb-0">Edit the body content for each letter type. Changes apply to all future generated letters.</p>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr><th>Letter Type</th><th>Template</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($templates as $tpl): ?>
                    <tr>
                        <td class="fw-semibold"><?= e((string) $tpl['label']); ?></td>
                        <td>
                            <?php if ($tpl['has_custom']): ?>
                                <span class="badge text-bg-success">Custom</span>
                            <?php else: ?>
                                <span class="badge text-bg-secondary">Default</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?= e(url('/letters/templates/' . $tpl['type'] . '/edit')); ?>" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-pencil"></i> Edit
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
