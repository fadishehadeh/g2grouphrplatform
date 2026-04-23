<?php declare(strict_types=1); $user = auth()->user(); ?>
<div class="card content-card mb-4">
    <div class="card-body p-3 d-flex flex-wrap gap-2 module-nav">
        <?php if (can('documents.manage_all')): ?>
            <a href="<?= e(url('/documents')); ?>" class="btn btn-outline-secondary btn-sm">Document Center</a>
            <a href="<?= e(url('/documents/types')); ?>" class="btn btn-outline-secondary btn-sm">Document Types</a>
            <a href="<?= e(url('/documents/categories')); ?>" class="btn btn-outline-secondary btn-sm">Categories</a>
            <a href="<?= e(url('/documents/expiring')); ?>" class="btn btn-outline-secondary btn-sm">Expiring</a>
        <?php endif; ?>
        <?php if (!empty($user['employee_id']) && (can('documents.view_self') || can('documents.upload_self'))): ?>
            <a href="<?= e(url('/employees/' . $user['employee_id'] . '/documents/upload')); ?>" class="btn btn-outline-secondary btn-sm">My Documents</a>
        <?php endif; ?>
    </div>
</div>
