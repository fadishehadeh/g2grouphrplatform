<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Employee Registration'); ?> — <?= e(\App\Support\Branding::name()); ?></title>
    <link href="<?= e(asset('css/bootstrap.min.css')); ?>" rel="stylesheet">
    <link href="<?= e(asset('css/bootstrap-icons.min.css')); ?>" rel="stylesheet">
    <style>
        body { background: #f0f2f5; min-height: 100vh; }
        .intake-topbar { background: #1a1a2e; border-bottom: 3px solid #0d6efd; }
        .intake-topbar .navbar-brand { font-weight: 700; color: #fff !important; letter-spacing: .4px; }
        .intake-card { border: 1px solid #dee2e6; border-radius: 12px; background: #fff; box-shadow: 0 2px 12px rgba(0,0,0,.06); }
        .step-indicator .step { display: flex; flex-direction: column; align-items: center; flex: 1; }
        .step-indicator .step-circle {
            width: 36px; height: 36px; border-radius: 50%; border: 2px solid #dee2e6;
            background: #fff; display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: .85rem; color: #6c757d; transition: all .25s;
        }
        .step-indicator .step-circle.active   { background: #0d6efd; border-color: #0d6efd; color: #fff; }
        .step-indicator .step-circle.done     { background: #198754; border-color: #198754; color: #fff; }
        .step-indicator .step-label { font-size: .72rem; color: #6c757d; margin-top: 4px; text-align: center; }
        .step-indicator .step-label.active { color: #0d6efd; font-weight: 600; }
        .step-indicator .step-connector { flex: 1; height: 2px; background: #dee2e6; margin-top: 17px; }
        .step-indicator .step-connector.done { background: #198754; }
        .wizard-panel { display: none; }
        .wizard-panel.active { display: block; }
        .doc-row { border: 1px solid #e9ecef; border-radius: 8px; padding: 1rem; margin-bottom: .75rem; background: #fafafa; }
        .contact-row { border: 1px solid #e9ecef; border-radius: 8px; padding: 1rem; margin-bottom: .75rem; background: #fafafa; }
        @media (max-width: 575.98px) {
            .step-label { display: none; }
            .intake-card { border-radius: 8px; }
        }
    </style>
</head>
<body>

<nav class="navbar intake-topbar px-3 px-lg-4 py-2">
    <span class="navbar-brand">
        <img src="<?= e(\App\Support\Branding::logoUrl()); ?>" alt="Logo" height="30" class="me-2">
        Employee Registration
    </span>
</nav>

<main class="py-4 px-2">
    <?php
    $flash_error   = flash('error');
    $flash_success = flash('success');
    ?>
    <?php if ($flash_error || $flash_success): ?>
    <div class="container" style="max-width:860px">
        <?php if ($flash_error): ?><div class="alert alert-danger alert-dismissible" role="alert"><?= e((string) $flash_error); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
        <?php if ($flash_success): ?><div class="alert alert-success alert-dismissible" role="alert"><?= e((string) $flash_success); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
    </div>
    <?php endif; ?>
    <?= $content; ?>
</main>

<footer class="py-3 text-center text-muted small border-top bg-white mt-4">
    &copy; <?= date('Y'); ?> <?= e(\App\Support\Branding::name()); ?>
</footer>

<script src="<?= e(asset('js/bootstrap.bundle.min.js')); ?>"></script>
</body>
</html>
