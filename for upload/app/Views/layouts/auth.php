<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#FF3D33">
    <title><?= e($title ?? config('app.brand.display_name', config('app.name'))); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= e(asset('css/app.css')); ?>?v=<?= filemtime(base_path('public/assets/css/app.css')); ?>" rel="stylesheet">
</head>
<body class="auth-body">
    <div class="container-fluid min-vh-100">
        <div class="row min-vh-100">
            <div class="col-lg-6 d-none d-lg-flex auth-brand-panel">
                <div class="m-auto text-white px-5 auth-brand-copy">
                    <img src="<?= e(asset('images/g2group-white.svg')); ?>" alt="<?= e((string) config('app.brand.display_name', config('app.name'))); ?>" class="auth-logo" style="max-width:144px;width:144px;height:auto;display:block;margin:0 auto 1.5rem auto;">
                    <h1 class="display-6 fw-bold mb-2">HR Management System</h1>
                    <p class="lead mb-3">People operations platform</p>
                    <p class="mb-0 text-white-50">Centralized employee lifecycle, leave workflows, documents, onboarding, approvals, and HR reporting.</p>
                </div>
            </div>
            <div class="col-lg-6 d-flex align-items-center justify-content-center bg-white">
                <div class="auth-card-wrapper w-100">
                    <?php require base_path('app/Views/partials/flash.php'); ?>
                    <?= $content; ?>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>