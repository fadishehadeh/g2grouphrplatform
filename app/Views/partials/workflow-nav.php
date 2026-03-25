<?php declare(strict_types=1); ?>
<div class="card content-card mb-4">
    <div class="card-body p-3 d-flex flex-wrap gap-2">
        <?php if (can('onboarding.manage')): ?>
            <a href="<?= e(url('/onboarding')); ?>" class="btn btn-outline-secondary btn-sm">Onboarding</a>
            <a href="<?= e(url('/onboarding/templates')); ?>" class="btn btn-outline-secondary btn-sm">Templates</a>
        <?php endif; ?>
        <?php if (can('offboarding.manage')): ?>
            <a href="<?= e(url('/offboarding')); ?>" class="btn btn-outline-secondary btn-sm">Offboarding</a>
        <?php endif; ?>
    </div>
</div>