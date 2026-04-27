<?php declare(strict_types=1); ?>
<?php
$pageHeaderTitle = (string) ($pageHeaderTitle ?? ($pageTitle ?? ''));
$pageHeaderDescription = (string) ($pageHeaderDescription ?? '');
$pageHeaderChips = is_array($pageHeaderChips ?? null) ? $pageHeaderChips : [];
$pageHeaderActions = is_array($pageHeaderActions ?? null) ? $pageHeaderActions : [];
?>

<section class="page-header-block card content-card mb-4">
    <div class="card-body p-4">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-3">
            <div class="page-header-copy">
                <?php if ($pageHeaderTitle !== ''): ?>
                    <h1 class="page-header-title mb-2"><?= e($pageHeaderTitle); ?></h1>
                <?php endif; ?>
                <?php if ($pageHeaderDescription !== ''): ?>
                    <p class="page-header-description mb-0"><?= e($pageHeaderDescription); ?></p>
                <?php endif; ?>

                <?php if ($pageHeaderChips !== []): ?>
                    <div class="page-header-chips">
                        <?php foreach ($pageHeaderChips as $chip): ?>
                            <?php
                            $label = (string) ($chip['label'] ?? '');
                            if ($label === '') {
                                continue;
                            }
                            $tone = (string) ($chip['tone'] ?? 'neutral');
                            ?>
                            <span class="page-header-chip page-header-chip-<?= e($tone); ?>"><?= e($label); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($pageHeaderActions !== []): ?>
                <div class="page-header-actions">
                    <?php foreach ($pageHeaderActions as $action): ?>
                        <?php
                        $label = (string) ($action['label'] ?? '');
                        $href = (string) ($action['href'] ?? '#');
                        $class = (string) ($action['class'] ?? 'btn btn-outline-secondary');
                        $icon = (string) ($action['icon'] ?? '');
                        if ($label === '') {
                            continue;
                        }
                        ?>
                        <a href="<?= e($href); ?>" class="<?= e($class); ?>">
                            <?php if ($icon !== ''): ?><i class="bi <?= e($icon); ?>"></i> <?php endif; ?><?= e($label); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
