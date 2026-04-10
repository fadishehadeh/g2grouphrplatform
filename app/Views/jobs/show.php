<?php declare(strict_types=1);
$statusColors = ['draft'=>'secondary','open'=>'success','closed'=>'danger','paused'=>'warning'];
$appStatuses = ['new'=>'New','reviewing'=>'Reviewing','shortlisted'=>'Shortlisted','interviewed'=>'Interviewed','offered'=>'Offered','rejected'=>'Rejected','hired'=>'Hired','withdrawn'=>'Withdrawn'];
$jobTypes = ['full_time'=>'Full-Time','part_time'=>'Part-Time','contract'=>'Contract','internship'=>'Internship','freelance'=>'Freelance'];
$skills = is_string($job['skills_required'] ?? null) ? (json_decode((string)$job['skills_required'], true) ?? []) : ($job['skills_required'] ?? []);
?>
<div class="row g-4">
    <!-- Header -->
    <div class="col-12">
        <div class="card content-card">
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-start gap-3">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <h4 class="mb-0 fw-bold"><?= e((string)$job['title']); ?></h4>
                            <?php if ($job['is_featured']): ?><span class="badge text-bg-warning"><i class="bi bi-star-fill"></i> Featured</span><?php endif; ?>
                            <span class="badge text-bg-<?= $statusColors[$job['status']] ?? 'secondary'; ?>"><?= ucfirst($job['status']); ?></span>
                        </div>
                        <div class="text-muted small">
                            <?= e((string)($job['company_name'] ?? '')); ?>
                            <?php if ($job['location_city']): ?> · <i class="bi bi-geo-alt me-1"></i><?= e((string)$job['location_city']); ?><?php endif; ?>
                            · <i class="bi bi-eye me-1"></i><?= (int)$job['views_count']; ?> views
                            · <?= count($applications); ?> application<?= count($applications) !== 1 ? 's' : ''; ?>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <!-- Status change -->
                        <form method="post" action="<?= e(url('/admin/jobs/' . $job['id'] . '/status')); ?>" class="d-flex gap-2">
                            <?= csrf_field(); ?>
                            <select name="status" class="form-select form-select-sm">
                                <?php foreach (['draft'=>'Draft','open'=>'Open','paused'=>'Paused','closed'=>'Closed'] as $v=>$l): ?>
                                    <option value="<?= $v; ?>" <?= $job['status'] === $v ? 'selected' : ''; ?>><?= $l; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-sm btn-outline-primary">Update</button>
                        </form>
                        <a href="<?= e(url('/admin/jobs/' . $job['id'] . '/edit')); ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil me-1"></i>Edit</a>
                        <a href="<?= e(url('/careers/jobs/' . $job['slug'])); ?>" target="_blank" class="btn btn-sm btn-outline-success"><i class="bi bi-box-arrow-up-right me-1"></i>Public</a>
                        <a href="<?= e(url('/admin/jobs')); ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Applicants table -->
    <div class="col-lg-8">
        <div class="card content-card">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-people me-2 text-danger"></i>Applicants</h6>
                    <a href="<?= e(url('/admin/jobs/applicants/export?job_id=' . $job['id'])); ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-download me-1"></i>Export CSV
                    </a>
                </div>
                <?php if (empty($applications)): ?>
                    <div class="empty-state">No applications yet.</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0 table-hover">
                        <thead><tr><th>Applicant</th><th>Status</th><th>Rating</th><th>Applied</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($applications as $app): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= e(trim(($app['first_name'] ?? '') . ' ' . ($app['last_name'] ?? '')) ?: $app['username']); ?></div>
                                <div class="small text-muted"><?= e((string)$app['email']); ?></div>
                                <?php if ($app['current_job_title']): ?><div class="small text-muted"><?= e((string)$app['current_job_title']); ?></div><?php endif; ?>
                            </td>
                            <td>
                                <span class="badge text-bg-<?= ['new'=>'primary','reviewing'=>'warning','shortlisted'=>'info','interviewed'=>'secondary','offered'=>'success','rejected'=>'danger','hired'=>'success','withdrawn'=>'light'][$app['status']] ?? 'secondary'; ?>">
                                    <?= e($appStatuses[$app['status']] ?? $app['status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($app['hr_rating']): ?>
                                    <?= str_repeat('★', (int)$app['hr_rating']); ?><?= str_repeat('☆', 5 - (int)$app['hr_rating']); ?>
                                <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                            </td>
                            <td class="small text-muted"><?= e(date('d M Y', strtotime((string)$app['submitted_at']))); ?></td>
                            <td><a href="<?= e(url('/admin/jobs/applicants/' . $app['id'])); ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye me-1"></i>View</a></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Job info sidebar -->
    <div class="col-lg-4">
        <div class="card content-card p-4">
            <h6 class="fw-bold mb-3 border-bottom pb-2">Job Details</h6>
            <dl class="mb-0 small">
                <dt class="text-muted">Category</dt><dd><?= e((string)($job['category_name'] ?? '—')); ?></dd>
                <dt class="text-muted">Type</dt><dd><?= e($jobTypes[$job['job_type']] ?? $job['job_type']); ?></dd>
                <dt class="text-muted">Experience</dt><dd><?= ucfirst($job['experience_level']); ?><?= ($job['min_experience_years'] !== null) ? ' · ' . $job['min_experience_years'] . '–' . ($job['max_experience_years'] ?? '∞') . ' yrs' : ''; ?></dd>
                <?php if ($job['min_salary']): ?><dt class="text-muted">Salary</dt><dd><?= e((string)$job['salary_currency']); ?> <?= number_format((float)$job['min_salary']); ?><?= $job['max_salary'] ? '–'.number_format((float)$job['max_salary']) : '+'; ?></dd><?php endif; ?>
                <dt class="text-muted">Positions</dt><dd><?= (int)$job['positions_count']; ?></dd>
                <dt class="text-muted">Deadline</dt><dd><?= !empty($job['deadline']) ? e(date('d M Y', strtotime((string)$job['deadline']))) : '—'; ?></dd>
                <dt class="text-muted">Published</dt><dd><?= !empty($job['published_at']) ? e(date('d M Y', strtotime((string)$job['published_at']))) : 'Not yet'; ?></dd>
                <?php if (!empty($skills)): ?>
                <dt class="text-muted mt-2">Required Skills</dt>
                <dd><div class="d-flex flex-wrap gap-1"><?php foreach ($skills as $sk): ?><span class="badge text-bg-light border"><?= e((string)$sk); ?></span><?php endforeach; ?></div></dd>
                <?php endif; ?>
            </dl>
        </div>
    </div>
</div>
