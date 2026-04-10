<?php declare(strict_types=1);
$jobTypes  = ['full_time'=>'Full-Time','part_time'=>'Part-Time','contract'=>'Contract','internship'=>'Internship','freelance'=>'Freelance'];
$expLevels = ['entry'=>'Entry Level','junior'=>'Junior','mid'=>'Mid Level','senior'=>'Senior','lead'=>'Lead','executive'=>'Executive'];
$skills = is_string($job['skills_required'] ?? null) ? (json_decode((string)$job['skills_required'], true) ?? []) : ($job['skills_required'] ?? []);
?>
<div class="careers-hero py-4">
    <div class="container-fluid px-3 px-lg-5">
        <nav aria-label="breadcrumb"><ol class="breadcrumb mb-2" style="--bs-breadcrumb-divider-color:rgba(255,255,255,.5)">
            <li class="breadcrumb-item"><a href="<?= e(url('/careers')); ?>" class="text-white-50">Jobs</a></li>
            <li class="breadcrumb-item active text-white"><?= e((string)$job['title']); ?></li>
        </ol></nav>
        <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
            <div>
                <h1 class="fw-bold mb-1"><?= e((string)$job['title']); ?></h1>
                <div class="text-white-50">
                    <?php if ($job['company_name']): ?><i class="bi bi-building me-1"></i><?= e((string)$job['company_name']); ?>&nbsp;&nbsp;<?php endif; ?>
                    <?php if ($job['location_city']): ?><i class="bi bi-geo-alt me-1"></i><?= e((string)$job['location_city']); ?><?= $job['location_country'] ? ', ' . e((string)$job['location_country']) : ''; ?><?php endif; ?>
                </div>
            </div>
            <div class="d-flex flex-column align-items-end gap-2">
                <?php if ($hasApplied): ?>
                    <span class="badge text-bg-success fs-6 px-3 py-2"><i class="bi bi-check-circle me-1"></i>Applied</span>
                <?php elseif ($seeker): ?>
                    <a href="<?= e(url('/careers/apply/' . $job['id'])); ?>" class="btn btn-danger btn-lg px-4">Apply Now</a>
                <?php else: ?>
                    <a href="<?= e(url('/careers/login')); ?>" class="btn btn-danger btn-lg px-4">Sign In to Apply</a>
                    <small class="text-white-50">or <a href="<?= e(url('/careers/register')); ?>" class="text-white">create an account</a></small>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-3 px-lg-5 py-4">
    <div class="row g-4">
        <!-- Details sidebar -->
        <div class="col-lg-4 order-lg-2">
            <div class="section-card p-4 mb-4">
                <h6 class="fw-bold mb-3 border-bottom pb-2">Job Details</h6>
                <dl class="mb-0">
                    <dt class="small text-muted mb-1">Job Type</dt>
                    <dd class="mb-3"><span class="badge text-bg-light border"><?= e($jobTypes[$job['job_type']] ?? ucfirst((string)$job['job_type'])); ?></span></dd>

                    <dt class="small text-muted mb-1">Experience Level</dt>
                    <dd class="mb-3"><span class="badge text-bg-light border"><?= e($expLevels[$job['experience_level']] ?? ucfirst((string)$job['experience_level'])); ?></span></dd>

                    <?php if ($job['min_experience_years'] !== null || $job['max_experience_years'] !== null): ?>
                    <dt class="small text-muted mb-1">Experience Required</dt>
                    <dd class="mb-3"><?= $job['min_experience_years'] ?? 0; ?>–<?= $job['max_experience_years'] ?? '∞'; ?> years</dd>
                    <?php endif; ?>

                    <?php if ($job['salary_visible'] && $job['min_salary']): ?>
                    <dt class="small text-muted mb-1">Salary</dt>
                    <dd class="mb-3 text-success fw-semibold">
                        <?= e((string)$job['salary_currency']); ?> <?= number_format((float)$job['min_salary']); ?>
                        <?= $job['max_salary'] ? ' – ' . number_format((float)$job['max_salary']) : '+'; ?>
                    </dd>
                    <?php endif; ?>

                    <?php if ($job['positions_count'] > 1): ?>
                    <dt class="small text-muted mb-1">Open Positions</dt>
                    <dd class="mb-3"><?= (int)$job['positions_count']; ?></dd>
                    <?php endif; ?>

                    <?php if ($job['deadline']): ?>
                    <dt class="small text-muted mb-1">Application Deadline</dt>
                    <dd class="mb-3 <?= strtotime((string)$job['deadline']) < time() + 7*86400 ? 'text-danger fw-semibold' : ''; ?>">
                        <i class="bi bi-clock me-1"></i><?= e(date('d M Y', strtotime((string)$job['deadline']))); ?>
                    </dd>
                    <?php endif; ?>

                    <?php if ($job['education_required']): ?>
                    <dt class="small text-muted mb-1">Education</dt>
                    <dd class="mb-3"><?= e((string)$job['education_required']); ?></dd>
                    <?php endif; ?>

                    <?php if ($job['category_name']): ?>
                    <dt class="small text-muted mb-1">Category</dt>
                    <dd class="mb-3"><?= e((string)$job['category_name']); ?></dd>
                    <?php endif; ?>
                </dl>
            </div>

            <?php if (!empty($skills)): ?>
            <div class="section-card p-4 mb-4">
                <h6 class="fw-bold mb-3 border-bottom pb-2">Required Skills</h6>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($skills as $skill): ?>
                        <span class="badge text-bg-light border px-3 py-2"><?= e((string)$skill); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Apply CTA -->
            <?php if (!$hasApplied && $seeker): ?>
            <div class="section-card p-4">
                <a href="<?= e(url('/careers/apply/' . $job['id'])); ?>" class="btn btn-danger w-100 fw-semibold py-3">
                    <i class="bi bi-send me-2"></i>Apply for This Position
                </a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Main content -->
        <div class="col-lg-8 order-lg-1">
            <?php if (!empty($job['description'])): ?>
            <div class="section-card p-4 mb-4">
                <h5 class="fw-bold border-bottom pb-3 mb-3 text-danger"><i class="bi bi-file-text me-2"></i>About the Role</h5>
                <div style="line-height:1.8"><?= nl2br(e((string)$job['description'])); ?></div>
            </div>
            <?php endif; ?>

            <?php if (!empty($job['responsibilities'])): ?>
            <div class="section-card p-4 mb-4">
                <h5 class="fw-bold border-bottom pb-3 mb-3 text-danger"><i class="bi bi-list-check me-2"></i>Responsibilities</h5>
                <div style="line-height:1.8"><?= nl2br(e((string)$job['responsibilities'])); ?></div>
            </div>
            <?php endif; ?>

            <?php if (!empty($job['requirements'])): ?>
            <div class="section-card p-4 mb-4">
                <h5 class="fw-bold border-bottom pb-3 mb-3 text-danger"><i class="bi bi-check2-square me-2"></i>Requirements</h5>
                <div style="line-height:1.8"><?= nl2br(e((string)$job['requirements'])); ?></div>
            </div>
            <?php endif; ?>

            <?php if (!empty($job['benefits'])): ?>
            <div class="section-card p-4 mb-4">
                <h5 class="fw-bold border-bottom pb-3 mb-3 text-danger"><i class="bi bi-gift me-2"></i>Benefits & Perks</h5>
                <div style="line-height:1.8"><?= nl2br(e((string)$job['benefits'])); ?></div>
            </div>
            <?php endif; ?>

            <!-- Final CTA -->
            <div class="text-center py-2">
                <?php if ($hasApplied): ?>
                    <div class="alert alert-success d-inline-flex align-items-center gap-2 px-4 py-3">
                        <i class="bi bi-check-circle-fill fs-5"></i>
                        You have already applied for this position.
                        <a href="<?= e(url('/careers/my-applications')); ?>" class="ms-2 btn btn-sm btn-success">Track Application</a>
                    </div>
                <?php elseif ($seeker): ?>
                    <a href="<?= e(url('/careers/apply/' . $job['id'])); ?>" class="btn btn-danger btn-lg px-5">
                        <i class="bi bi-send me-2"></i>Apply Now
                    </a>
                <?php else: ?>
                    <p class="text-muted mb-2">You need an account to apply.</p>
                    <a href="<?= e(url('/careers/register')); ?>" class="btn btn-danger me-2">Create Account</a>
                    <a href="<?= e(url('/careers/login')); ?>" class="btn btn-outline-secondary">Sign In</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
