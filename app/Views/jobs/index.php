<?php declare(strict_types=1);
$statusColors = ['draft'=>'secondary','open'=>'success','closed'=>'danger','paused'=>'warning'];
$jobTypes = ['full_time'=>'Full-Time','part_time'=>'Part-Time','contract'=>'Contract','internship'=>'Internship','freelance'=>'Freelance'];
?>
<div class="row g-4">
    <!-- Stats -->
    <div class="col-12">
        <div class="row g-3">
            <?php $stats = [['label'=>'Total Jobs','val'=>$counts['total_jobs'],'icon'=>'bi-briefcase','color'=>'primary'],['label'=>'Open','val'=>$counts['open_jobs'],'icon'=>'bi-door-open','color'=>'success'],['label'=>'Applications','val'=>$counts['total_applications'],'icon'=>'bi-send','color'=>'info'],['label'=>'New','val'=>$counts['new_applications'],'icon'=>'bi-bell','color'=>'warning'],['label'=>'Job Bank','val'=>$counts['job_bank'],'icon'=>'bi-bank','color'=>'secondary'],['label'=>'Seekers','val'=>$counts['total_seekers'],'icon'=>'bi-people','color'=>'dark']]; ?>
            <?php foreach ($stats as $s): ?>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="card content-card text-center py-3">
                    <div class="fs-3 fw-bold text-<?= $s['color']; ?>"><?= $s['val']; ?></div>
                    <div class="small text-muted"><?= $s['label']; ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Filters + Job list -->
    <div class="col-12">
        <div class="card content-card">
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                    <h5 class="mb-0"><i class="bi bi-briefcase me-2 text-danger"></i>Job Postings</h5>
                    <div class="d-flex gap-2">
                        <a href="<?= e(url('/admin/jobs/applicants')); ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-people me-1"></i>All Applicants</a>
                        <a href="<?= e(url('/admin/jobs/job-bank')); ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-bank me-1"></i>Job Bank</a>
                        <a href="<?= e(url('/admin/jobs/categories')); ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-tags me-1"></i>Categories</a>
                        <a href="<?= e(url('/admin/jobs/create')); ?>" class="btn btn-danger btn-sm"><i class="bi bi-plus-lg me-1"></i>Post Job</a>
                    </div>
                </div>

                <!-- Filters -->
                <form method="get" action="<?= e(url('/admin/jobs')); ?>" class="row g-2 mb-4">
                    <div class="col-12 col-md-4"><input type="text" name="q" class="form-control" placeholder="Search title, company..." value="<?= e((string)($filters['q'] ?? '')); ?>"></div>
                    <div class="col-6 col-md-2">
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <?php foreach (['draft','open','paused','closed'] as $s): ?><option value="<?= $s; ?>" <?= ($filters['status'] ?? '') === $s ? 'selected' : ''; ?>><?= ucfirst($s); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <select name="category_id" class="form-select">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?><option value="<?= $cat['id']; ?>" <?= ($filters['category_id'] ?? '') == $cat['id'] ? 'selected' : ''; ?>><?= e((string)$cat['name']); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <select name="job_type" class="form-select">
                            <option value="">All Types</option>
                            <?php foreach ($jobTypes as $v => $l): ?><option value="<?= e($v); ?>" <?= ($filters['job_type'] ?? '') === $v ? 'selected' : ''; ?>><?= e($l); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2 d-flex gap-1">
                        <button class="btn btn-outline-secondary flex-grow-1">Search</button>
                        <a href="<?= e(url('/admin/jobs')); ?>" class="btn btn-outline-secondary"><i class="bi bi-x"></i></a>
                    </div>
                </form>

                <?php if (empty($jobs)): ?>
                    <div class="empty-state">No jobs found. <a href="<?= e(url('/admin/jobs/create')); ?>">Post the first one</a>.</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Job</th><th>Category</th><th>Type</th><th>Status</th><th>Applications</th><th>Deadline</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($jobs as $job): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold">
                                    <?php if ($job['is_featured']): ?><i class="bi bi-star-fill text-warning me-1"></i><?php endif; ?>
                                    <a href="<?= e(url('/admin/jobs/' . $job['id'])); ?>" class="text-decoration-none"><?= e((string)$job['title']); ?></a>
                                </div>
                                <div class="small text-muted"><?= e((string)($job['company_name'] ?? '')); ?> <?= !empty($job['location_city']) ? '· ' . e((string)$job['location_city']) : ''; ?></div>
                            </td>
                            <td class="small"><?= e((string)($job['category_name'] ?? '—')); ?></td>
                            <td class="small"><?= e($jobTypes[$job['job_type']] ?? $job['job_type']); ?></td>
                            <td><span class="badge text-bg-<?= $statusColors[$job['status']] ?? 'secondary'; ?>"><?= ucfirst($job['status']); ?></span></td>
                            <td><a href="<?= e(url('/admin/jobs/applicants?job_id=' . $job['id'])); ?>" class="badge text-bg-light border text-decoration-none"><?= (int)$job['application_count']; ?></a></td>
                            <td class="small <?= !empty($job['deadline']) && strtotime((string)$job['deadline']) < time() + 7*86400 ? 'text-danger' : 'text-muted'; ?>">
                                <?= !empty($job['deadline']) ? e(date('d M Y', strtotime((string)$job['deadline']))) : '—'; ?>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="<?= e(url('/admin/jobs/' . $job['id'])); ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                                    <a href="<?= e(url('/admin/jobs/' . $job['id'] . '/edit')); ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
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
