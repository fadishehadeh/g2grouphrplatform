<?php declare(strict_types=1);
$jobTypes = ['full_time'=>'Full-Time','part_time'=>'Part-Time','contract'=>'Contract','internship'=>'Internship','freelance'=>'Freelance'];
$expLevels = ['entry'=>'Entry Level','junior'=>'Junior','mid'=>'Mid Level','senior'=>'Senior','lead'=>'Lead','executive'=>'Executive'];
?>
<div class="careers-hero">
    <div class="container-fluid px-3 px-lg-5 py-2">
        <h1 class="fw-bold mb-2"><i class="bi bi-briefcase me-2"></i>Job Openings</h1>
        <p class="mb-4 opacity-75">Find your next opportunity and apply directly with your profile.</p>
        <!-- Search bar -->
        <form method="get" action="<?= e(url('/careers')); ?>" class="row g-2">
            <div class="col-12 col-md-5">
                <input type="text" name="q" class="form-control form-control-lg" placeholder="Search job title, company..."
                       value="<?= e((string)($filters['q'] ?? '')); ?>">
            </div>
            <div class="col-6 col-md-2">
                <select name="job_type" class="form-select form-select-lg">
                    <option value="">All Types</option>
                    <?php foreach ($jobTypes as $v => $l): ?>
                        <option value="<?= e($v); ?>" <?= ($filters['job_type'] ?? '') === $v ? 'selected' : ''; ?>><?= e($l); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="experience_level" class="form-select form-select-lg">
                    <option value="">All Levels</option>
                    <?php foreach ($expLevels as $v => $l): ?>
                        <option value="<?= e($v); ?>" <?= ($filters['experience_level'] ?? '') === $v ? 'selected' : ''; ?>><?= e($l); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <button class="btn btn-danger btn-lg w-100"><i class="bi bi-search me-1"></i> Search</button>
            </div>
        </form>
    </div>
</div>

<div class="container-fluid px-3 px-lg-5 py-4">
    <div class="row g-4">

        <!-- Category filter sidebar -->
        <div class="col-lg-3">
            <div class="section-card p-3 mb-3">
                <h6 class="fw-bold mb-3 px-1">Browse by Category</h6>
                <div class="d-flex flex-column gap-1">
                    <a href="<?= e(url('/careers')); ?>" class="nav-link px-3 py-2 d-flex justify-content-between align-items-center <?= empty($filters['category']) ? 'active text-danger fw-semibold' : ''; ?>">
                        <span><i class="bi bi-grid me-2"></i>All Categories</span>
                        <span class="badge text-bg-light"><?= count($jobs); ?></span>
                    </a>
                    <?php foreach ($categories as $cat): ?>
                        <?php
                        $catJobs = array_filter($jobs, fn($j) => $j['job_category_id'] == $cat['id']);
                        $isActive = ($filters['category'] ?? '') === $cat['slug'];
                        ?>
                        <a href="<?= e(url('/careers?category=' . urlencode($cat['slug']))); ?>"
                           class="nav-link px-3 py-2 d-flex justify-content-between align-items-center <?= $isActive ? 'active text-danger fw-semibold' : ''; ?>">
                            <span><i class="<?= e((string)$cat['icon']); ?> me-2"></i><?= e((string)$cat['name']); ?></span>
                            <span class="badge text-bg-light"><?= (int)$cat['job_count']; ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Job listing -->
        <div class="col-lg-9">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <p class="text-muted mb-0"><?= count($jobs); ?> position<?= count($jobs) !== 1 ? 's' : ''; ?> found</p>
                <?php if (!empty(array_filter($filters))): ?>
                    <a href="<?= e(url('/careers')); ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-circle me-1"></i>Clear filters</a>
                <?php endif; ?>
            </div>

            <?php if (empty($jobs)): ?>
                <div class="section-card p-5 text-center text-muted">
                    <i class="bi bi-search fs-1 d-block mb-3 opacity-25"></i>
                    <h5>No positions found</h5>
                    <p class="small">Try broadening your search or <a href="<?= e(url('/careers')); ?>">view all jobs</a>.</p>
                    <?php if ($seeker): ?>
                        <form method="post" action="<?= e(url('/careers/job-bank')); ?>" class="mt-3">
                            <?= csrf_field(); ?>
                            <p class="small text-muted">Want to be considered for future openings?</p>
                            <button class="btn btn-danger btn-sm">Submit Profile to Job Bank</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($jobs as $job): ?>
                    <div class="col-12">
                        <div class="job-card p-4 position-relative">
                            <?php if ($job['is_featured']): ?>
                                <span class="featured-ribbon badge text-bg-warning"><i class="bi bi-star-fill me-1"></i>Featured</span>
                            <?php endif; ?>
                            <div class="row align-items-center g-3">
                                <div class="col-md-8">
                                    <h5 class="fw-bold mb-1">
                                        <a href="<?= e(url('/careers/jobs/' . $job['slug'])); ?>" class="text-decoration-none text-dark">
                                            <?= e((string)$job['title']); ?>
                                        </a>
                                    </h5>
                                    <div class="text-muted small mb-2">
                                        <?php if (!empty($job['company_name'])): ?><i class="bi bi-building me-1"></i><?= e((string)$job['company_name']); ?><?php endif; ?>
                                        <?php if (!empty($job['location_city'])): ?> &nbsp;·&nbsp; <i class="bi bi-geo-alt me-1"></i><?= e((string)$job['location_city']); ?><?= !empty($job['location_country']) ? ', ' . e((string)$job['location_country']) : ''; ?><?php endif; ?>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2">
                                        <span class="badge text-bg-light border"><?= e($jobTypes[$job['job_type']] ?? $job['job_type']); ?></span>
                                        <span class="badge text-bg-light border"><?= e($expLevels[$job['experience_level']] ?? $job['experience_level']); ?></span>
                                        <?php if (!empty($job['category_name'])): ?><span class="badge text-bg-light border"><i class="<?= e((string)($job['category_icon'] ?? 'bi-briefcase')); ?> me-1"></i><?= e((string)$job['category_name']); ?></span><?php endif; ?>
                                        <?php if ($job['salary_visible'] && $job['min_salary']): ?>
                                            <span class="badge text-bg-light border text-success">
                                                <i class="bi bi-cash me-1"></i>
                                                <?= e((string)$job['salary_currency']); ?> <?= number_format((float)$job['min_salary']); ?>
                                                <?= $job['max_salary'] ? '–' . number_format((float)$job['max_salary']) : '+'; ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($job['deadline']): ?>
                                            <span class="badge text-bg-light border <?= strtotime((string)$job['deadline']) < time() + 7*86400 ? 'text-danger' : ''; ?>">
                                                <i class="bi bi-clock me-1"></i>Deadline: <?= e(date('d M Y', strtotime((string)$job['deadline']))); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-4 text-md-end">
                                    <a href="<?= e(url('/careers/jobs/' . $job['slug'])); ?>" class="btn btn-danger btn-sm mb-1">View & Apply</a>
                                    <div class="small text-muted"><?= (int)$job['views_count']; ?> views</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
