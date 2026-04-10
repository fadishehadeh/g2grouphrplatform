<?php declare(strict_types=1);
$fullName = trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? ''));
$hasCv = !empty($profile['cv_file_path']);
$expCount = count($sections['experience'] ?? []);
$skillCount = count($sections['skill'] ?? []);
?>
<div class="careers-hero py-4">
    <div class="container-fluid px-3 px-lg-5">
        <nav aria-label="breadcrumb"><ol class="breadcrumb mb-2" style="--bs-breadcrumb-divider-color:rgba(255,255,255,.5)">
            <li class="breadcrumb-item"><a href="<?= e(url('/careers')); ?>" class="text-white-50">Jobs</a></li>
            <li class="breadcrumb-item"><a href="<?= e(url('/careers/jobs/' . $job['slug'])); ?>" class="text-white-50"><?= e((string)$job['title']); ?></a></li>
            <li class="breadcrumb-item active text-white">Apply</li>
        </ol></nav>
        <h1 class="fw-bold mb-0">Apply — <?= e((string)$job['title']); ?></h1>
    </div>
</div>

<div class="container-fluid px-3 px-lg-5 py-4" style="max-width:900px">
    <div class="row g-4">

        <!-- Application summary -->
        <div class="col-md-4">
            <div class="section-card p-4 mb-3">
                <h6 class="fw-bold mb-3 text-danger"><i class="bi bi-briefcase me-2"></i>Applying For</h6>
                <div class="fw-semibold"><?= e((string)$job['title']); ?></div>
                <?php if ($job['company_name']): ?><div class="text-muted small"><?= e((string)$job['company_name']); ?></div><?php endif; ?>
                <?php if ($job['location_city']): ?><div class="text-muted small"><i class="bi bi-geo-alt me-1"></i><?= e((string)$job['location_city']); ?></div><?php endif; ?>
            </div>

            <div class="section-card p-4 mb-3">
                <h6 class="fw-bold mb-3 text-danger"><i class="bi bi-person me-2"></i>Your Profile</h6>
                <div class="fw-semibold"><?= $fullName !== '' ? e($fullName) : e($seeker['username']); ?></div>
                <div class="text-muted small"><?= e($seeker['email']); ?></div>
                <?php if (!empty($profile['current_job_title'])): ?>
                    <div class="small mt-2"><i class="bi bi-briefcase me-1"></i><?= e((string)$profile['current_job_title']); ?></div>
                <?php endif; ?>
                <div class="small mt-2 text-muted">
                    <i class="bi bi-buildings me-1"></i><?= $expCount; ?> experience<?= $expCount !== 1 ? 's' : ''; ?> &nbsp;·&nbsp;
                    <i class="bi bi-tools me-1"></i><?= $skillCount; ?> skill<?= $skillCount !== 1 ? 's' : ''; ?>
                </div>
                <?php if ($hasCv): ?>
                    <div class="mt-2 text-success small"><i class="bi bi-file-earmark-check me-1"></i>CV on file</div>
                <?php else: ?>
                    <div class="mt-2 text-warning small"><i class="bi bi-exclamation-triangle me-1"></i>No CV uploaded yet
                        <a href="<?= e(url('/careers/profile')); ?>" class="ms-1">Upload</a>
                    </div>
                <?php endif; ?>
                <a href="<?= e(url('/careers/profile')); ?>" class="btn btn-sm btn-outline-secondary w-100 mt-3">Update Profile</a>
            </div>
        </div>

        <!-- Application form -->
        <div class="col-md-8">
            <div class="section-card p-4 p-md-5">
                <h5 class="fw-bold mb-4 border-bottom pb-3 text-danger"><i class="bi bi-send me-2"></i>Submit Application</h5>

                <?php if (!$hasCv): ?>
                <div class="alert alert-warning mb-4">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>Tip:</strong> Employers can download your uploaded CV. <a href="<?= e(url('/careers/profile')); ?>">Upload your CV now</a> for a stronger application.
                </div>
                <?php endif; ?>

                <form method="post" action="<?= e(url('/careers/apply/' . $job['id'])); ?>">
                    <?= csrf_field(); ?>
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Cover Letter <span class="text-muted fw-normal">(optional but recommended)</span></label>
                        <textarea name="cover_letter" class="form-control" rows="8"
                                  placeholder="Dear Hiring Manager,&#10;&#10;I am excited to apply for the <?= e((string)$job['title']); ?> position...&#10;&#10;Explain why you're a great fit, highlight relevant experience, and mention what excites you about this role."><?= e((string) old('cover_letter')); ?></textarea>
                    </div>

                    <div class="alert alert-light border small mb-4">
                        <i class="bi bi-info-circle me-2"></i>
                        By submitting, you agree to share your full profile and any uploaded CV with <?= !empty($job['company_name']) ? e((string)$job['company_name']) : 'the employer'; ?>.
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-danger fw-semibold px-4 py-2">
                            <i class="bi bi-send me-2"></i>Submit Application
                        </button>
                        <a href="<?= e(url('/careers/jobs/' . $job['slug'])); ?>" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
