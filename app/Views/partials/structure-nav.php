<?php declare(strict_types=1); ?>
<?php
$links = [
    'overview' => ['/admin/structure', 'Overview'],
    'companies' => ['/admin/companies', 'Companies'],
    'branches' => ['/admin/branches', 'Branches'],
    'departments' => ['/admin/departments', 'Departments'],
    'teams' => ['/admin/teams', 'Teams'],
    'job_titles' => ['/admin/job-titles', 'Job Titles'],
    'designations' => ['/admin/designations', 'Designations'],
    'reporting_lines' => ['/admin/reporting-lines', 'Reporting Lines'],
];
?>
<ul class="nav nav-pills section-nav mb-4 gap-2">
    <?php foreach ($links as $key => $link): ?>
        <li class="nav-item">
            <a class="nav-link <?= ($activeSection ?? '') === $key ? 'active' : ''; ?>" href="<?= e(url($link[0])); ?>"><?= e($link[1]); ?></a>
        </li>
    <?php endforeach; ?>
</ul>