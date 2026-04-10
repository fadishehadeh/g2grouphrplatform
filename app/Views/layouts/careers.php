<?php declare(strict_types=1);
use App\Support\CareersAuth;
$careersUser = (new CareersAuth(app()->session()))->user();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#FF3D33">
    <title><?= e($title ?? 'Careers Portal'); ?> — <?= e((string) config('app.brand.display_name', 'HR System')); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(asset('css/app.css')); ?>" rel="stylesheet">
    <style>
        .careers-topbar { background: #1a1a2e; border-bottom: 3px solid #FF3D33; }
        .careers-topbar .navbar-brand { font-weight: 700; color: #fff !important; letter-spacing:.5px; }
        .careers-topbar .nav-link { color: rgba(255,255,255,.8) !important; }
        .careers-topbar .nav-link:hover { color: #FF3D33 !important; }
        .careers-topbar .btn-portal { background: #FF3D33; color: #fff; border: none; }
        .careers-topbar .btn-portal:hover { background: #d63327; color: #fff; }
        .careers-hero { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 60%, #0f3460 100%); color: #fff; padding: 56px 0 40px; }
        .careers-hero h1 { font-weight: 800; font-size: 2.4rem; }
        .section-card { border: 1px solid #e9ecef; border-radius: 10px; background: #fff; }
        .profile-nav .nav-link { color: #495057; border-radius: 8px; font-size: .92rem; }
        .profile-nav .nav-link.active, .profile-nav .nav-link:hover { background: #fff3f2; color: #FF3D33; }
        .score-bar { height: 10px; border-radius: 6px; }
        .badge-status-new        { background: #e7f1ff; color: #0066cc; }
        .badge-status-reviewing  { background: #fff3cd; color: #856404; }
        .badge-status-shortlisted{ background: #d1e7dd; color: #0a3622; }
        .badge-status-interviewed{ background: #cff4fc; color: #055160; }
        .badge-status-offered    { background: #d1ecf1; color: #0c5460; }
        .badge-status-rejected   { background: #f8d7da; color: #842029; }
        .badge-status-hired      { background: #d1e7dd; color: #0a3622; font-weight:700; }
        .badge-status-withdrawn  { background: #e9ecef; color: #6c757d; }
        .job-card { border: 1px solid #e9ecef; border-radius: 10px; transition: box-shadow .15s, transform .15s; background: #fff; }
        .job-card:hover { box-shadow: 0 4px 20px rgba(0,0,0,.1); transform: translateY(-2px); }
        .job-card .job-type-badge { font-size: .75rem; }
        .featured-ribbon { position: absolute; top: 12px; right: 12px; }
        @media (max-width: 768px) { .careers-hero h1 { font-size: 1.6rem; } }
    </style>
</head>
<body style="background:#f8f9fa">

<nav class="navbar navbar-expand-lg careers-topbar px-3 px-lg-4">
    <a class="navbar-brand" href="<?= e(url('/careers')); ?>">
        <img src="<?= e(asset((string) config('app.brand.logo_asset', 'images/g2group.svg'))); ?>" alt="Logo" height="32" class="me-2">
        Careers
    </a>
    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#careersNav">
        <span class="navbar-toggler-icon" style="filter:invert(1)"></span>
    </button>
    <div class="collapse navbar-collapse" id="careersNav">
        <ul class="navbar-nav me-auto">
            <li class="nav-item"><a class="nav-link" href="<?= e(url('/careers')); ?>"><i class="bi bi-briefcase me-1"></i>Jobs</a></li>
        </ul>
        <div class="d-flex align-items-center gap-2">
            <?php if ($careersUser): ?>
                <span class="text-white-50 small d-none d-md-inline">Hi, <?= e($careersUser['username']); ?></span>
                <a href="<?= e(url('/careers/dashboard')); ?>" class="btn btn-sm btn-outline-light">Dashboard</a>
                <a href="<?= e(url('/careers/profile')); ?>" class="btn btn-sm btn-outline-light">My Profile</a>
                <form method="post" action="<?= e(url('/careers/logout')); ?>" class="d-inline">
                    <?= csrf_field(); ?>
                    <button class="btn btn-sm btn-portal">Sign Out</button>
                </form>
            <?php else: ?>
                <a href="<?= e(url('/careers/login')); ?>" class="btn btn-sm btn-outline-light">Sign In</a>
                <a href="<?= e(url('/careers/register')); ?>" class="btn btn-sm btn-portal">Create Account</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<main>
    <?php
    $flash_error   = flash('error');
    $flash_success = flash('success');
    $flash_info    = flash('info');
    ?>
    <?php if ($flash_error || $flash_success || $flash_info): ?>
    <div class="container-fluid px-3 px-lg-4 pt-3">
        <?php if ($flash_error): ?><div class="alert alert-danger alert-dismissible mb-0" role="alert"><?= e((string) $flash_error); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
        <?php if ($flash_success): ?><div class="alert alert-success alert-dismissible mb-0" role="alert"><?= e((string) $flash_success); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
        <?php if ($flash_info): ?><div class="alert alert-info alert-dismissible mb-0" role="alert"><?= e((string) $flash_info); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
    </div>
    <?php endif; ?>
    <?= $content; ?>
</main>

<footer class="mt-5 py-4 border-top bg-white text-center text-muted small">
    &copy; <?= date('Y'); ?> <?= e((string) config('app.brand.display_name', 'G2 Group')); ?> — Careers Portal
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(asset('js/app.js')); ?>"></script>
</body>
</html>
