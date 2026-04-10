<?php declare(strict_types=1);
$statusLabels = ['new'=>'New','reviewing'=>'Under Review','shortlisted'=>'Shortlisted','interviewed'=>'Interviewed','offered'=>'Offer Extended','rejected'=>'Not Selected','hired'=>'Hired!','withdrawn'=>'Withdrawn'];
?>
<div class="careers-hero py-4">
    <div class="container-fluid px-3 px-lg-5">
        <h1 class="fw-bold mb-0"><i class="bi bi-send me-2"></i>My Applications</h1>
        <p class="opacity-75 mb-0 mt-1">Track the status of your job applications and job bank submission.</p>
    </div>
</div>

<div class="container-fluid px-3 px-lg-5 py-4">
    <?php if (empty($applications)): ?>
        <div class="section-card p-5 text-center text-muted" style="max-width:600px;margin:auto">
            <i class="bi bi-inbox fs-1 d-block mb-3 opacity-25"></i>
            <h5>No applications yet</h5>
            <p class="small">Browse our open positions and apply to opportunities that match your profile.</p>
            <a href="<?= e(url('/careers')); ?>" class="btn btn-danger">Browse Jobs</a>
        </div>
    <?php else: ?>
        <div class="d-flex flex-column gap-3" style="max-width:900px">
            <?php foreach ($applications as $app): ?>
            <div class="section-card p-4">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <h6 class="fw-bold mb-0">
                                <?= $app['job_id'] ? e((string)$app['job_title']) : '<span class="text-muted">General Job Bank</span>'; ?>
                            </h6>
                            <?php if ($app['job_id'] === null): ?>
                                <span class="badge text-bg-info px-2 py-1 small">Job Bank</span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($app['company_name'])): ?>
                            <div class="text-muted small mb-1"><i class="bi bi-building me-1"></i><?= e((string)$app['company_name']); ?>
                            <?php if (!empty($app['location_city'])): ?> — <?= e((string)$app['location_city']); ?><?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($app['job_type'])): ?>
                            <span class="badge text-bg-light border small"><?= e(ucwords(str_replace('_',' ',$app['job_type']))); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="text-end">
                        <div class="mb-2">
                            <span class="badge rounded-pill badge-status-<?= e($app['status']); ?> px-3 py-2 fs-6">
                                <?= e($statusLabels[$app['status']] ?? ucfirst($app['status'])); ?>
                            </span>
                        </div>
                        <div class="small text-muted">Applied <?= e(date('d M Y', strtotime((string)$app['submitted_at']))); ?></div>
                    </div>
                </div>

                <!-- Status progress bar -->
                <?php
                $statuses = ['new','reviewing','shortlisted','interviewed','offered','hired'];
                $currentIdx = array_search($app['status'], $statuses);
                $isRejected  = $app['status'] === 'rejected';
                $isWithdrawn = $app['status'] === 'withdrawn';
                ?>
                <?php if (!$isRejected && !$isWithdrawn): ?>
                <div class="mt-3">
                    <div class="d-flex justify-content-between align-items-center gap-1">
                        <?php foreach ($statuses as $i => $s): ?>
                            <?php $done = $currentIdx !== false && $i <= $currentIdx; ?>
                            <div class="flex-grow-1 text-center" style="font-size:.68rem">
                                <div class="rounded-pill mb-1 mx-1" style="height:6px;background:<?= $done ? '#FF3D33' : '#dee2e6'; ?>"></div>
                                <span class="text-muted d-none d-md-block"><?= e($statusLabels[$s] ?? $s); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php elseif ($isRejected): ?>
                    <div class="mt-3 small text-danger"><i class="bi bi-x-circle me-1"></i>This application was not progressed.</div>
                <?php endif; ?>

                <!-- Actions -->
                <div class="d-flex gap-2 mt-3">
                    <?php if ($app['job_id'] && !empty($app['job_slug'])): ?>
                        <a href="<?= e(url('/careers/jobs/' . $app['job_slug'])); ?>" class="btn btn-sm btn-outline-secondary">View Job</a>
                    <?php endif; ?>
                    <?php if (!in_array($app['status'], ['hired','rejected','withdrawn'], true)): ?>
                        <form method="post" action="<?= e(url('/careers/my-applications/' . $app['id'] . '/withdraw')); ?>"
                              onsubmit="return confirm('Withdraw this application?')">
                            <?= csrf_field(); ?>
                            <button class="btn btn-sm btn-outline-danger">Withdraw</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
