<?php declare(strict_types=1);
$statusColors = ['new'=>'primary','reviewing'=>'warning','shortlisted'=>'info','interviewed'=>'secondary','offered'=>'success','rejected'=>'danger','hired'=>'success','withdrawn'=>'light'];
$statusList = ['new','reviewing','shortlisted','interviewed','offered','rejected','hired','withdrawn'];
$jobTypes = ['full_time'=>'Full-Time','part_time'=>'Part-Time','contract'=>'Contract','internship'=>'Internship','freelance'=>'Freelance'];
$expLevels = ['entry'=>'Entry','junior'=>'Junior','mid'=>'Mid','senior'=>'Senior','lead'=>'Lead','executive'=>'Exec'];
$exportUrl = url('/admin/jobs/applicants/export?' . http_build_query($filters));
?>
<div class="row g-4">
    <div class="col-12">
        <div class="card content-card">
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                    <h5 class="mb-0">
                        <i class="bi bi-people me-2 text-danger"></i>
                        <?= e($title ?? 'All Applicants'); ?>
                        <span class="badge text-bg-secondary ms-2"><?= count($applications); ?></span>
                    </h5>
                    <div class="d-flex gap-2">
                        <a href="<?= e($exportUrl); ?>" class="btn btn-sm btn-outline-success"><i class="bi bi-download me-1"></i>Export CSV</a>
                        <?php if (!($isBankView ?? false)): ?>
                        <a href="<?= e(url('/admin/jobs/job-bank')); ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-bank me-1"></i>Job Bank</a>
                        <?php endif; ?>
                        <a href="<?= e(url('/admin/jobs')); ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-briefcase me-1"></i>Jobs</a>
                    </div>
                </div>

                <!-- Filters -->
                <form method="get" action="" class="mb-4">
                    <div class="row g-2">
                        <div class="col-md-3"><input type="text" name="q" class="form-control" placeholder="Name, email, job title..." value="<?= e((string)($filters['q'] ?? '')); ?>"></div>
                        <?php if (!($isBankView ?? false)): ?>
                        <div class="col-md-2">
                            <select name="job_id" class="form-select">
                                <option value="">All Jobs</option>
                                <?php foreach ($jobs as $j): ?><option value="<?= $j['id']; ?>" <?= ($filters['job_id'] ?? '') == $j['id'] ? 'selected' : ''; ?>><?= e((string)$j['title']); ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="col-md-2">
                            <select name="status" class="form-select">
                                <option value="">All Status</option>
                                <?php foreach ($statusList as $s): ?><option value="<?= $s; ?>" <?= ($filters['status'] ?? '') === $s ? 'selected' : ''; ?>><?= ucfirst($s); ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-1">
                            <select name="rating" class="form-select">
                                <option value="">★ All</option>
                                <?php for ($i=5;$i>=1;$i--): ?><option value="<?= $i; ?>" <?= ($filters['rating'] ?? '') == $i ? 'selected' : ''; ?>><?= str_repeat('★',$i); ?></option><?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="category_id" class="form-select">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?><option value="<?= $cat['id']; ?>" <?= ($filters['category_id'] ?? '') == $cat['id'] ? 'selected' : ''; ?>><?= e((string)$cat['name']); ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex gap-1">
                            <button class="btn btn-outline-secondary flex-grow-1">Filter</button>
                            <a href="" class="btn btn-outline-secondary"><i class="bi bi-x"></i></a>
                        </div>
                    </div>
                    <div class="row g-2 mt-1">
                        <div class="col-md-2"><input type="text" name="nationality" class="form-control form-control-sm" placeholder="Nationality" value="<?= e((string)($filters['nationality'] ?? '')); ?>"></div>
                        <div class="col-md-2"><input type="text" name="country" class="form-control form-control-sm" placeholder="Country of Residence" value="<?= e((string)($filters['country'] ?? '')); ?>"></div>
                        <div class="col-md-1"><input type="number" name="min_exp" class="form-control form-control-sm" placeholder="Min Yrs" min="0" value="<?= e((string)($filters['min_exp'] ?? '')); ?>"></div>
                        <div class="col-md-1"><input type="number" name="max_exp" class="form-control form-control-sm" placeholder="Max Yrs" min="0" value="<?= e((string)($filters['max_exp'] ?? '')); ?>"></div>
                        <div class="col-md-2"><input type="date" name="date_from" class="form-control form-control-sm" value="<?= e((string)($filters['date_from'] ?? '')); ?>"></div>
                        <div class="col-md-2"><input type="date" name="date_to" class="form-control form-control-sm" value="<?= e((string)($filters['date_to'] ?? '')); ?>"></div>
                        <div class="col-md-2">
                            <select name="relocate" class="form-select form-select-sm">
                                <option value="">Relocate: Any</option>
                                <option value="1" <?= ($filters['relocate'] ?? '') === '1' ? 'selected' : ''; ?>>Willing to Relocate</option>
                                <option value="0" <?= ($filters['relocate'] ?? '') === '0' ? 'selected' : ''; ?>>Not Willing</option>
                            </select>
                        </div>
                    </div>
                </form>

                <?php if (empty($applications)): ?>
                    <div class="empty-state">No applicants match the selected filters.</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0 table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Applicant</th>
                                <th>Applied For</th>
                                <th>Experience</th>
                                <th>Status</th>
                                <th>Rating</th>
                                <th>Applied</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($applications as $app): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <?php if (!empty($app['photo_path']) && is_file(base_path((string)$app['photo_path']))): ?>
                                        <img src="<?= e(url('/' . ltrim((string)$app['photo_path'], '/'))); ?>"
                                             class="rounded-circle" style="width:36px;height:36px;object-fit:cover">
                                    <?php else: ?>
                                        <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center text-white"
                                             style="width:36px;height:36px;font-size:.8rem;flex-shrink:0">
                                            <?= strtoupper(substr(($app['first_name'] ?? $app['username'] ?? '?'), 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="fw-semibold small"><?= e(trim(($app['first_name'] ?? '') . ' ' . ($app['last_name'] ?? '')) ?: $app['username']); ?></div>
                                        <div class="text-muted" style="font-size:.75rem"><?= e((string)$app['email']); ?></div>
                                        <?php if ($app['nationality']): ?><div style="font-size:.72rem" class="text-muted"><?= e((string)$app['nationality']); ?><?= $app['country'] ? ' · ' . e((string)$app['country']) : ''; ?></div><?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="small">
                                <?php if ($app['job_id']): ?>
                                    <a href="<?= e(url('/admin/jobs/' . $app['job_id'])); ?>" class="text-decoration-none"><?= e((string)$app['job_title']); ?></a>
                                <?php else: ?>
                                    <span class="badge text-bg-info">Job Bank</span>
                                <?php endif; ?>
                                <?php if ($app['category_name']): ?><div class="text-muted" style="font-size:.75rem"><?= e((string)$app['category_name']); ?></div><?php endif; ?>
                            </td>
                            <td class="small">
                                <?php if ($app['years_of_experience'] !== null): ?><?= e((string)$app['years_of_experience']); ?> yrs<?php endif; ?>
                                <?php if ($app['current_job_title']): ?><div class="text-muted" style="font-size:.75rem"><?= e(mb_strimwidth((string)$app['current_job_title'], 0, 30, '...')); ?></div><?php endif; ?>
                            </td>
                            <td><span class="badge text-bg-<?= $statusColors[$app['status']] ?? 'secondary'; ?>"><?= ucfirst($app['status']); ?></span></td>
                            <td>
                                <?php if ($app['hr_rating']): ?>
                                    <span class="text-warning"><?= str_repeat('★', (int)$app['hr_rating']); ?></span>
                                <?php else: ?><span class="text-muted small">—</span><?php endif; ?>
                            </td>
                            <td class="small text-muted"><?= e(date('d M Y', strtotime((string)$app['submitted_at']))); ?></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="<?= e(url('/admin/jobs/applicants/' . $app['id'])); ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                                    <?php if (!empty($app['cv_file_path'])): ?>
                                        <a href="<?= e(url('/admin/jobs/applicants/' . $app['id'] . '/cv')); ?>" class="btn btn-sm btn-outline-success"><i class="bi bi-download"></i></a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
