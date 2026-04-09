<?php declare(strict_types=1); ?>
<div class="card content-card mb-4">
    <div class="card-body p-3 d-flex flex-wrap gap-2">
        <?php if (can('letters.request')): ?>
            <a href="<?= e(url('/letters/my')); ?>" class="btn btn-outline-secondary btn-sm">My Letters</a>
            <a href="<?= e(url('/letters/request')); ?>" class="btn btn-outline-secondary btn-sm">Request a Letter</a>
        <?php endif; ?>
        <?php if (can('letters.manage')): ?>
            <a href="<?= e(url('/letters/admin')); ?>" class="btn btn-outline-secondary btn-sm">All Requests</a>
        <?php endif; ?>
    </div>
</div>
