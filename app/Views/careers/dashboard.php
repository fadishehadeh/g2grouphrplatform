<?php declare(strict_types=1);
$fullName = trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? ''));
$name = $fullName !== '' ? $fullName : ($seeker['username'] ?? 'there');
$scoreColor = $score >= 80 ? 'success' : ($score >= 50 ? 'warning' : 'danger');
$inBank = false;
foreach ($applications as $app) { if ($app['job_id'] === null) { $inBank = true; break; } }
?>
<div class="careers-hero">
    <div class="container-fluid px-3 px-lg-5">
        <div class="d-flex align-items-center gap-4">
            <?php if (!empty($profile['photo_path']) && is_file(base_path((string)$profile['photo_path']))): ?>
                <img src="<?= e(url('/' . ltrim((string)$profile['photo_path'], '/'))); ?>"
                     class="rounded-circle border border-3 border-white" style="width:72px;height:72px;object-fit:cover">
            <?php else: ?>
                <div class="rounded-circle bg-danger d-flex align-items-center justify-content-center border border-3 border-white"
                     style="width:72px;height:72px;font-size:1.6rem;color:#fff;flex-shrink:0">
                    <?= e(strtoupper(substr($name, 0, 1))); ?>
                </div>
            <?php endif; ?>
            <div>
                <h1 class="mb-1 fw-bold">Welcome back, <?= e($name); ?>!</h1>
                <p class="mb-0 opacity-75">Manage your profile, track applications, and explore opportunities.</p>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-3 px-lg-5 py-4">
    <div class="row g-4">

        <!-- Profile Completeness -->
        <div class="col-12">
            <div class="section-card p-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold mb-0"><i class="bi bi-bar-chart-steps me-2 text-danger"></i>Profile Completeness</h6>
                    <span class="fw-bold text-<?= $scoreColor; ?>"><?= $score; ?>%</span>
                </div>
                <div class="progress score-bar mb-2">
                    <div class="progress-bar bg-<?= $scoreColor; ?>" style="width:<?= $score; ?>%"></div>
                </div>
                <?php if ($score < 100): ?>
                <div class="d-flex flex-wrap gap-2 mt-3 small">
                    <?php if (empty($profile['first_name'])): ?><a href="<?= e(url('/careers/profile/personal')); ?>" class="badge text-bg-light border text-decoration-none"><i class="bi bi-plus-circle me-1"></i>Add Personal Info</a><?php endif; ?>
                    <?php if (empty($profile['professional_summary'])): ?><a href="<?= e(url('/careers/profile/professional')); ?>" class="badge text-bg-light border text-decoration-none"><i class="bi bi-plus-circle me-1"></i>Add Summary</a><?php endif; ?>
                    <?php if (empty($sections['experience'])): ?><a href="<?= e(url('/careers/profile/experience')); ?>" class="badge text-bg-light border text-decoration-none"><i class="bi bi-plus-circle me-1"></i>Add Experience</a><?php endif; ?>
                    <?php if (count($sections['skill'] ?? []) < 3): ?><a href="<?= e(url('/careers/profile/skill')); ?>" class="badge text-bg-light border text-decoration-none"><i class="bi bi-plus-circle me-1"></i>Add Skills</a><?php endif; ?>
                    <?php if (empty($profile['cv_file_path'])): ?><a href="<?= e(url('/careers/profile')); ?>" class="badge text-bg-danger border text-decoration-none"><i class="bi bi-upload me-1"></i>Upload CV (20%)</a><?php endif; ?>
                    <?php if (empty($profile['photo_path'])): ?><a href="<?= e(url('/careers/profile')); ?>" class="badge text-bg-light border text-decoration-none"><i class="bi bi-camera me-1"></i>Add Photo</a><?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Stats row -->
        <div class="col-6 col-md-3">
            <div class="section-card p-3 text-center">
                <div class="fs-2 fw-bold text-danger"><?= count($applications); ?></div>
                <div class="small text-muted">Applications</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="section-card p-3 text-center">
                <?php $active = count(array_filter($applications, fn($a) => !in_array($a['status'], ['rejected','withdrawn','hired']))); ?>
                <div class="fs-2 fw-bold text-primary"><?= $active; ?></div>
                <div class="small text-muted">Active</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="section-card p-3 text-center">
                <?php $shortlisted = count(array_filter($applications, fn($a) => $a['status'] === 'shortlisted')); ?>
                <div class="fs-2 fw-bold text-success"><?= $shortlisted; ?></div>
                <div class="small text-muted">Shortlisted</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="section-card p-3 text-center">
                <div class="fs-2 fw-bold <?= $inBank ? 'text-success' : 'text-muted'; ?>">
                    <?= $inBank ? '<i class="bi bi-check-circle"></i>' : '<i class="bi bi-x-circle"></i>'; ?>
                </div>
                <div class="small text-muted">In Job Bank</div>
            </div>
        </div>

        <!-- My Applications -->
        <div class="col-lg-8">
            <div class="section-card">
                <div class="p-4 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0"><i class="bi bi-send me-2 text-danger"></i>My Applications</h6>
                    <a href="<?= e(url('/careers/my-applications')); ?>" class="btn btn-sm btn-outline-danger">View All</a>
                </div>
                <?php if (empty($applications)): ?>
                    <div class="p-5 text-center text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-3 opacity-25"></i>
                        No applications yet. <a href="<?= e(url('/careers')); ?>">Browse jobs</a> to get started.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light"><tr><th>Job / Type</th><th>Status</th><th>Applied</th></tr></thead>
                            <tbody>
                            <?php foreach (array_slice($applications, 0, 8) as $app): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= $app['job_id'] ? e((string)$app['job_title']) : 'General Job Bank'; ?></div>
                                        <?php if ($app['company_name']): ?><div class="small text-muted"><?= e((string)$app['company_name']); ?></div><?php endif; ?>
                                    </td>
                                    <td><span class="badge rounded-pill badge-status-<?= e($app['status']); ?> px-3 py-2"><?= e(ucfirst($app['status'])); ?></span></td>
                                    <td class="small text-muted"><?= e(date('d M Y', strtotime((string)$app['submitted_at']))); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right sidebar -->
        <div class="col-lg-4">
            <!-- Quick actions -->
            <div class="section-card p-4 mb-4">
                <h6 class="fw-bold mb-3"><i class="bi bi-lightning me-2 text-danger"></i>Quick Actions</h6>
                <div class="d-grid gap-2">
                    <a href="<?= e(url('/careers/profile/personal')); ?>" class="btn btn-outline-secondary btn-sm text-start">
                        <i class="bi bi-person me-2"></i>Edit Personal Info
                    </a>
                    <a href="<?= e(url('/careers/profile/professional')); ?>" class="btn btn-outline-secondary btn-sm text-start">
                        <i class="bi bi-briefcase me-2"></i>Edit Professional Info
                    </a>
                    <a href="<?= e(url('/careers/profile/experience')); ?>" class="btn btn-outline-secondary btn-sm text-start">
                        <i class="bi bi-buildings me-2"></i>Work Experience
                    </a>
                    <a href="<?= e(url('/careers/profile/education')); ?>" class="btn btn-outline-secondary btn-sm text-start">
                        <i class="bi bi-mortarboard me-2"></i>Education
                    </a>
                    <a href="<?= e(url('/careers/profile')); ?>" class="btn btn-outline-secondary btn-sm text-start">
                        <i class="bi bi-upload me-2"></i>Upload / Update CV
                    </a>
                    <a href="<?= e(url('/careers')); ?>" class="btn btn-danger btn-sm text-start">
                        <i class="bi bi-search me-2"></i>Browse Jobs
                    </a>
                </div>
            </div>

            <!-- Job bank -->
            <?php if (!$inBank): ?>
            <div class="section-card p-4">
                <h6 class="fw-bold mb-2"><i class="bi bi-bank me-2 text-danger"></i>Join Our Job Bank</h6>
                <p class="small text-muted mb-3">Submit your profile to our general talent pool so HR can find you even when no specific job is posted.</p>
                <form method="post" action="<?= e(url('/careers/job-bank')); ?>">
                    <?= csrf_field(); ?>
                    <textarea name="cover_letter" class="form-control form-control-sm mb-2" rows="2"
                              placeholder="Optional note to HR..."></textarea>
                    <button class="btn btn-danger btn-sm w-100">Submit to Job Bank</button>
                </form>
            </div>
            <?php else: ?>
            <div class="section-card p-4">
                <div class="text-success text-center py-2">
                    <i class="bi bi-check-circle-fill fs-2 d-block mb-2"></i>
                    <strong>You're in our Job Bank!</strong>
                    <p class="small text-muted mb-0 mt-1">HR can find your profile at any time.</p>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Featured Jobs -->
        <div class="col-12">
            <div class="section-card">
                <div class="p-4 border-bottom">
                    <h6 class="fw-bold mb-0"><i class="bi bi-stars me-2 text-danger"></i>Open Positions</h6>
                </div>
                <div class="row g-3 p-4">
                    <?php foreach ($featuredJobs as $fj): ?>
                    <div class="col-md-6 col-xl-4">
                        <div class="job-card p-3 position-relative">
                            <?php if ($fj['is_featured']): ?><span class="featured-ribbon badge text-bg-warning"><i class="bi bi-star-fill"></i></span><?php endif; ?>
                            <div class="fw-semibold mb-1"><?= e((string)$fj['title']); ?></div>
                            <div class="small text-muted mb-2"><?= e((string)($fj['company_name'] ?? '')); ?> — <?= e((string)($fj['location_city'] ?? '')); ?></div>
                            <div class="d-flex gap-1 flex-wrap mb-2">
                                <span class="badge text-bg-light border job-type-badge"><?= e(str_replace('_',' ',ucfirst($fj['job_type']))); ?></span>
                                <?php if ($fj['category_name']): ?><span class="badge text-bg-light border job-type-badge"><?= e((string)$fj['category_name']); ?></span><?php endif; ?>
                            </div>
                            <a href="<?= e(url('/careers/jobs/' . $fj['slug'])); ?>" class="btn btn-outline-danger btn-sm">View Job</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($featuredJobs)): ?><div class="col-12 text-center text-muted py-3">No open positions at the moment.</div><?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</div>
