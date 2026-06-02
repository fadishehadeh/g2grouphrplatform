<?php declare(strict_types=1);
$current = basename(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
$navItems = [
    ['url' => '/careers/profile',              'icon' => 'bi-grid',          'label' => 'Overview'],
    ['url' => '/careers/profile/personal',     'icon' => 'bi-person',        'label' => 'Personal Info'],
    ['url' => '/careers/profile/professional', 'icon' => 'bi-briefcase',     'label' => 'Professional'],
    ['url' => '/careers/profile/experience',   'icon' => 'bi-buildings',     'label' => 'Work Experience'],
    ['url' => '/careers/profile/education',    'icon' => 'bi-mortarboard',   'label' => 'Education'],
    ['url' => '/careers/profile/skill',        'icon' => 'bi-tools',         'label' => 'Skills'],
    ['url' => '/careers/profile/language',     'icon' => 'bi-translate',     'label' => 'Languages'],
    ['url' => '/careers/profile/certification','icon' => 'bi-patch-check',   'label' => 'Certifications'],
    ['url' => '/careers/profile/project',      'icon' => 'bi-kanban',        'label' => 'Projects'],
    ['url' => '/careers/profile/award',        'icon' => 'bi-trophy',        'label' => 'Awards'],
    ['url' => '/careers/profile/volunteer',    'icon' => 'bi-heart',         'label' => 'Volunteer'],
    ['url' => '/careers/profile/reference',    'icon' => 'bi-people',        'label' => 'References'],
    ['url' => '/careers/profile/publication',  'icon' => 'bi-book',          'label' => 'Publications'],
];
?>
<div class="section-card p-3">
    <nav class="profile-nav d-flex flex-column gap-1">
        <?php foreach ($navItems as $item):
            $isActive = rtrim($_SERVER['REQUEST_URI'] ?? '', '/') === rtrim(parse_url(url($item['url']), PHP_URL_PATH), '/');
        ?>
            <a href="<?= e(url($item['url'])); ?>" class="nav-link px-3 py-2 <?= $isActive ? 'active' : ''; ?>">
                <i class="<?= e($item['icon']); ?> me-2"></i><?= e($item['label']); ?>
            </a>
        <?php endforeach; ?>
    </nav>
</div>
