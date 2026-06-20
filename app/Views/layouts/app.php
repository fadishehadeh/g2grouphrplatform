<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#FF3D33">
    <title><?= e($title ?? \App\Support\Branding::name()); ?></title>
    <link href="<?= e(asset('css/bootstrap.min.css')); ?>" rel="stylesheet">
    <link href="<?= e(asset('css/bootstrap-icons.min.css')); ?>" rel="stylesheet">
    <link href="<?= e(asset('css/app.css')); ?>" rel="stylesheet">
    <?php $__bc = \App\Support\Branding::brandColor(); if ($__bc !== '' && $__bc !== '#ff3d33'): ?>
    <style>:root { --brand-primary: <?= e($__bc); ?>; }</style>
    <?php endif; ?>
</head>
<body class="app-body">
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="app-shell">
        <?php require base_path('app/Views/partials/sidebar.php'); ?>
        <div class="app-main">
            <?php require base_path('app/Views/partials/topbar.php'); ?>
            <main class="app-content">
                <?php require base_path('app/Views/partials/flash.php'); ?>
                <?= $content; ?>
            </main>
            <?php require base_path('app/Views/partials/footer.php'); ?>
        </div>
    </div>
    <script src="<?= e(asset('js/bootstrap.bundle.min.js')); ?>"></script>
    <script src="<?= e(asset('js/app.js')); ?>"></script>
</body>
</html>
