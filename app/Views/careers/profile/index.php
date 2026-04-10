<?php declare(strict_types=1);
$fullName = trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? ''));
$scoreColor = $score >= 80 ? 'success' : ($score >= 50 ? 'warning' : 'danger');

$navItems = [
    'personal'      => ['icon' => 'bi-person',          'label' => 'Personal Info'],
    'professional'  => ['icon' => 'bi-briefcase',       'label' => 'Professional'],
    'experience'    => ['icon' => 'bi-buildings',       'label' => 'Work Experience'],
    'education'     => ['icon' => 'bi-mortarboard',     'label' => 'Education'],
    'skill'         => ['icon' => 'bi-tools',           'label' => 'Skills'],
    'language'      => ['icon' => 'bi-translate',       'label' => 'Languages'],
    'certification' => ['icon' => 'bi-patch-check',     'label' => 'Certifications'],
    'project'       => ['icon' => 'bi-kanban',          'label' => 'Projects'],
    'award'         => ['icon' => 'bi-trophy',          'label' => 'Awards'],
    'volunteer'     => ['icon' => 'bi-heart',           'label' => 'Volunteer'],
    'reference'     => ['icon' => 'bi-people',          'label' => 'References'],
    'publication'   => ['icon' => 'bi-book',            'label' => 'Publications'],
];
?>

<div class="careers-hero">
    <div class="container-fluid px-3 px-lg-5">
        <div class="d-flex align-items-center gap-4">
            <?php if (!empty($profile['photo_path']) && is_file(base_path((string)$profile['photo_path']))): ?>
                <img src="<?= e(url('/' . ltrim((string)$profile['photo_path'], '/'))); ?>"
                     class="rounded-circle border border-3 border-white" style="width:80px;height:80px;object-fit:cover">
            <?php else: ?>
                <div class="rounded-circle bg-danger d-flex align-items-center justify-content-center border border-3 border-white"
                     style="width:80px;height:80px;font-size:2rem;color:#fff;flex-shrink:0">
                    <?= e(strtoupper(substr($fullName !== '' ? $fullName : ($seeker['username'] ?? 'U'), 0, 1))); ?>
                </div>
            <?php endif; ?>
            <div>
                <h1 class="mb-1 fw-bold"><?= $fullName !== '' ? e($fullName) : e($seeker['username'] ?? 'My Profile'); ?></h1>
                <?php if (!empty($profile['current_job_title'])): ?>
                    <p class="mb-0 opacity-75"><?= e((string)$profile['current_job_title']); ?><?= !empty($profile['current_employer']) ? ' at ' . e((string)$profile['current_employer']) : ''; ?></p>
                <?php endif; ?>
                <div class="d-flex align-items-center gap-3 mt-2">
                    <small class="opacity-75">Completeness: <strong><?= $score; ?>%</strong></small>
                    <div class="progress score-bar" style="width:120px">
                        <div class="progress-bar bg-<?= $scoreColor; ?>" style="width:<?= $score; ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-3 px-lg-5 py-4">
    <div class="row g-4">

        <!-- Left nav -->
        <div class="col-lg-3">
            <div class="section-card p-3">
                <nav class="profile-nav d-flex flex-column gap-1">
                    <?php foreach ($navItems as $slug => $item): ?>
                        <a href="<?= e(url('/careers/profile/' . $slug)); ?>" class="nav-link px-3 py-2">
                            <i class="<?= e($item['icon']); ?> me-2"></i><?= e($item['label']); ?>
                            <?php if (!empty($sections[$slug])): ?>
                                <span class="badge text-bg-success float-end small"><?= count($sections[$slug]); ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </div>

            <!-- Upload boxes -->
            <div class="section-card p-4 mt-3">
                <h6 class="fw-bold mb-3"><i class="bi bi-upload me-2 text-danger"></i>Files</h6>

                <!-- Photo -->
                <div class="mb-4">
                    <p class="small text-muted mb-2">Profile Photo (JPG/PNG, max 2 MB)</p>
                    <?php if (!empty($profile['photo_path']) && is_file(base_path((string)$profile['photo_path']))): ?>
                        <img src="<?= e(url('/' . ltrim((string)$profile['photo_path'], '/'))); ?>"
                             class="rounded mb-2" style="max-height:80px;max-width:100%;object-fit:contain;border:1px solid #dee2e6">
                    <?php endif; ?>
                    <form method="post" action="<?= e(url('/careers/profile/photo')); ?>" enctype="multipart/form-data">
                        <?= csrf_field(); ?>
                        <input type="file" name="photo" class="form-control form-control-sm mb-2" accept="image/jpeg,image/png,image/webp" required>
                        <button class="btn btn-sm btn-outline-danger w-100">Upload Photo</button>
                    </form>
                </div>

                <!-- CV -->
                <div>
                    <p class="small text-muted mb-2">CV File (PDF/DOC/DOCX, max 10 MB)</p>
                    <?php if (!empty($profile['cv_file_path'])): ?>
                        <div class="alert alert-success py-2 px-3 small mb-2 d-flex align-items-center gap-2">
                            <i class="bi bi-file-earmark-check"></i>
                            <span><?= e((string)($profile['cv_original_name'] ?? 'CV uploaded')); ?></span>
                        </div>
                    <?php endif; ?>
                    <form method="post" action="<?= e(url('/careers/profile/cv')); ?>" enctype="multipart/form-data">
                        <?= csrf_field(); ?>
                        <input type="file" name="cv_file" class="form-control form-control-sm mb-2" accept=".pdf,.doc,.docx" required>
                        <button class="btn btn-sm btn-outline-danger w-100">
                            <?= !empty($profile['cv_file_path']) ? 'Replace CV' : 'Upload CV'; ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Profile overview -->
        <div class="col-lg-9">

            <!-- Summary card -->
            <?php if (!empty($profile['professional_summary'])): ?>
            <div class="section-card p-4 mb-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-file-person me-2 text-danger"></i>Professional Summary</h5>
                    <a href="<?= e(url('/careers/profile/professional')); ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                </div>
                <p class="mb-0 text-muted"><?= nl2br(e((string)$profile['professional_summary'])); ?></p>
            </div>
            <?php endif; ?>

            <?php
            $sectionConfig = [
                'experience'    => ['icon' => 'bi-buildings',   'label' => 'Work Experience',   'addUrl' => '/careers/profile/experience'],
                'education'     => ['icon' => 'bi-mortarboard', 'label' => 'Education',         'addUrl' => '/careers/profile/education'],
                'skill'         => ['icon' => 'bi-tools',       'label' => 'Skills',            'addUrl' => '/careers/profile/skill'],
                'language'      => ['icon' => 'bi-translate',   'label' => 'Languages',         'addUrl' => '/careers/profile/language'],
                'certification' => ['icon' => 'bi-patch-check', 'label' => 'Certifications',    'addUrl' => '/careers/profile/certification'],
                'project'       => ['icon' => 'bi-kanban',      'label' => 'Projects',          'addUrl' => '/careers/profile/project'],
                'award'         => ['icon' => 'bi-trophy',      'label' => 'Awards',            'addUrl' => '/careers/profile/award'],
                'volunteer'     => ['icon' => 'bi-heart',       'label' => 'Volunteer Work',    'addUrl' => '/careers/profile/volunteer'],
                'reference'     => ['icon' => 'bi-people',      'label' => 'References',        'addUrl' => '/careers/profile/reference'],
                'publication'   => ['icon' => 'bi-book',        'label' => 'Publications',      'addUrl' => '/careers/profile/publication'],
            ];
            foreach ($sectionConfig as $sType => $sCfg):
                $items = $sections[$sType] ?? [];
                if (empty($items)) continue;
            ?>
            <div class="section-card p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0"><i class="<?= e($sCfg['icon']); ?> me-2 text-danger"></i><?= e($sCfg['label']); ?></h6>
                    <a href="<?= e(url($sCfg['addUrl'])); ?>" class="btn btn-sm btn-outline-danger">Manage</a>
                </div>
                <?php if ($sType === 'skill'): ?>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($items as $sk):
                            $d = is_string($sk['data']) ? (json_decode($sk['data'], true) ?? []) : ($sk['data'] ?? []);
                            $level = $d['level'] ?? '';
                            $colors = ['beginner'=>'secondary','intermediate'=>'info','advanced'=>'primary','expert'=>'danger'];
                            $c = $colors[$level] ?? 'secondary';
                        ?>
                            <span class="badge text-bg-<?= $c; ?> px-3 py-2"><?= e((string)$sk['title']); ?><?= $level ? ' · ' . ucfirst($level) : ''; ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php elseif ($sType === 'language'): ?>
                    <div class="d-flex flex-wrap gap-3">
                        <?php foreach ($items as $lang):
                            $d = is_string($lang['data']) ? (json_decode($lang['data'], true) ?? []) : ($lang['data'] ?? []);
                        ?>
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-globe text-danger"></i>
                                <span class="fw-semibold"><?= e((string)$lang['title']); ?></span>
                                <?php if (!empty($d['proficiency'])): ?><span class="text-muted small"><?= e(ucfirst($d['proficiency'])); ?></span><?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($items as $item):
                            $d = is_string($item['data']) ? (json_decode($item['data'], true) ?? []) : ($item['data'] ?? []);
                        ?>
                        <div class="border-start border-danger border-3 ps-3">
                            <div class="fw-semibold"><?= e((string)$item['title']); ?></div>
                            <?php if (!empty($item['subtitle'])): ?><div class="text-muted small"><?= e((string)$item['subtitle']); ?></div><?php endif; ?>
                            <?php if (!empty($item['start_date'])): ?>
                                <div class="small text-muted">
                                    <?= e(date('M Y', strtotime((string)$item['start_date']))); ?>
                                    <?php if ($item['is_current']): ?>
                                        — <span class="text-success">Present</span>
                                    <?php elseif (!empty($item['end_date'])): ?>
                                        — <?= e(date('M Y', strtotime((string)$item['end_date']))); ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <?php
                            $desc = $d['description'] ?? $d['issuer'] ?? $d['credential_id'] ?? '';
                            if ($desc): ?>
                                <p class="small mb-0 mt-1 text-muted"><?= e(mb_strimwidth((string)$desc, 0, 200, '...')); ?></p>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <!-- Empty state -->
            <?php $hasAny = !empty(array_filter($sections)); if (!$hasAny && empty($profile['professional_summary'])): ?>
            <div class="section-card p-5 text-center">
                <i class="bi bi-person-lines-fill fs-1 d-block mb-3 text-muted opacity-25"></i>
                <h5 class="text-muted">Your profile is empty</h5>
                <p class="text-muted small">Start by adding your personal information and work experience.</p>
                <a href="<?= e(url('/careers/profile/personal')); ?>" class="btn btn-danger">Get Started</a>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>
