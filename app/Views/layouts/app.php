<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#FF3D33">
    <title><?= e($title ?? config('app.brand.display_name', config('app.name'))); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(asset('css/app.css')); ?>" rel="stylesheet">
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= e(asset('js/app.js')); ?>"></script>
</body>
</html>