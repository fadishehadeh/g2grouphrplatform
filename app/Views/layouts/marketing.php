<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#FF3D33">
    <meta name="description" content="A complete HR operations platform for employee records, leave, documents, onboarding, offboarding, recruitment, and reporting.">
    <title><?= e($title ?? config('app.brand.display_name', config('app.name'))); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="<?= e(asset('css/bootstrap.min.css')); ?>" rel="stylesheet">
    <link href="<?= e(asset('css/bootstrap-icons.min.css')); ?>" rel="stylesheet">
    <link href="<?= e(asset('css/app.css')); ?>" rel="stylesheet">
    <link href="<?= e(asset('css/marketing.css')); ?>" rel="stylesheet">
</head>
<body class="marketing-body">
    <?php require base_path('app/Views/partials/flash.php'); ?>
    <?= $content; ?>
    <script src="<?= e(asset('js/bootstrap.bundle.min.js')); ?>"></script>
</body>
</html>
