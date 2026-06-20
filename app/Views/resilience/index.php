<?php declare(strict_types=1); ?>
<?php
$b2Enabled = filter_var(config('app.b2.enabled', false), FILTER_VALIDATE_BOOLEAN);

$latestStatus    = (string) ($latestRun['status'] ?? 'unknown');
$latestArtifacts = $latestRun['artifacts'] ?? [];
$artifactMap     = [];
foreach ($latestArtifacts as $artifact) {
    $artifactMap[(string) $artifact['artifact_type']] = $artifact;
}
$databaseArtifact = $artifactMap['database'] ?? null;
$uploadsArtifact  = $artifactMap['uploads'] ?? null;

$formatBytes = static function (?int $bytes): string {
    $value = (int) ($bytes ?? 0);
    if ($value <= 0) { return '0 B'; }
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $power = (int) floor(log($value, 1024));
    $power = min($power, count($units) - 1);
    return number_format($value / (1024 ** $power), $power === 0 ? 0 : 2) . ' ' . $units[$power];
};

// Determine latest run's B2 sync state for the summary card
$latestB2Status = '—';
if ($b2Enabled && $latestRun !== null) {
    $b2Ok = true;
    $b2Applicable = false;
    foreach ($latestArtifacts as $a) {
        if (($a['status'] ?? '') === 'success') {
            $b2Applicable = true;
            if ((int) ($a['b2_uploaded'] ?? 0) !== 1) {
                $b2Ok = false;
            }
        }
    }
    if ($b2Applicable) {
        $latestB2Status = $b2Ok ? 'Synced' : 'Sync failed';
    } else {
        $latestB2Status = 'No artifacts';
    }
}
?>

<section class="card content-card mb-4">
    <div class="card-body p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <h1 class="h3 mb-1">Resilience Console</h1>
                <p class="text-muted mb-0">Daily backup status, secure download links, and retention monitoring for super admins only.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <form method="post" action="<?= e(url('/admin/resilience/run-now')); ?>">
                    <?= csrf_field(); ?>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-play-circle"></i> Run Backup Now
                    </button>
                </form>
                <?php if ($latestRun !== null): ?>
                <form method="post" action="<?= e(url('/admin/resilience/backups/' . (int) $latestRun['id'] . '/links')); ?>">
                    <?= csrf_field(); ?>
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-link-45deg"></i> Generate New Download Links
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php if ($b2Enabled): ?>
<div class="alert alert-success py-2 mb-3 d-flex align-items-center gap-2">
    <i class="bi bi-cloud-check-fill"></i>
    <span>Off-server backup is <strong>enabled</strong> (Backblaze B2). Artifacts are synced after each run and cleaned up after <?= e((string) (int) config('app.backups.retention_days', 30)); ?> days.</span>
</div>
<?php else: ?>
<div class="alert alert-secondary py-2 mb-3 d-flex align-items-center gap-2">
    <i class="bi bi-cloud-slash"></i>
    <span>Off-server backup is <strong>disabled</strong>. Enable B2 from <strong>Settings &gt; System Settings</strong> or keep using the <code>.env</code> fallback.</span>
</div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <div class="<?= $b2Enabled ? 'col-md-3' : 'col-md-4'; ?>">
        <div class="card dashboard-card h-100">
            <div class="card-body">
                <span>Latest Run</span>
                <h3><?= e($latestRun !== null ? '#' . (string) $latestRun['id'] : '—'); ?></h3>
                <small class="text-muted"><?= e($latestRun['completed_at'] ?? $latestRun['started_at'] ?? 'No backups yet'); ?></small>
            </div>
        </div>
    </div>
    <div class="<?= $b2Enabled ? 'col-md-3' : 'col-md-4'; ?>">
        <div class="card dashboard-card h-100">
            <div class="card-body">
                <span>Database Backup</span>
                <h3><?= e($databaseArtifact !== null ? ucfirst((string) $databaseArtifact['status']) : '—'); ?></h3>
                <small class="text-muted"><?= e($databaseArtifact !== null ? $formatBytes(isset($databaseArtifact['size_bytes']) ? (int) $databaseArtifact['size_bytes'] : 0) : 'No artifact'); ?></small>
            </div>
        </div>
    </div>
    <div class="<?= $b2Enabled ? 'col-md-3' : 'col-md-4'; ?>">
        <div class="card dashboard-card h-100">
            <div class="card-body">
                <span>Uploads Backup</span>
                <h3><?= e($uploadsArtifact !== null ? ucfirst((string) $uploadsArtifact['status']) : '—'); ?></h3>
                <small class="text-muted"><?= e($uploadsArtifact !== null ? $formatBytes(isset($uploadsArtifact['size_bytes']) ? (int) $uploadsArtifact['size_bytes'] : 0) : 'No artifact'); ?></small>
            </div>
        </div>
    </div>
    <?php if ($b2Enabled): ?>
    <div class="col-md-3">
        <div class="card dashboard-card h-100">
            <div class="card-body">
                <span>Off-Server Sync</span>
                <h3 class="<?= $latestB2Status === 'Synced' ? 'text-success' : ($latestB2Status === 'Sync failed' ? 'text-danger' : ''); ?>">
                    <?= e($latestB2Status); ?>
                </h3>
                <small class="text-muted">Backblaze B2</small>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<section class="card content-card">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
                <h5 class="mb-1">Backup History</h5>
                <p class="text-muted mb-0">Artifacts stay on the server for <?= e((string) (int) config('app.backups.retention_days', 30)); ?> days. Download links expire after <?= e((string) (int) config('app.backups.link_ttl_days', 7)); ?> days and require a signed-in super admin.</p>
            </div>
        </div>

        <?php if ($runs === []): ?>
        <div class="alert alert-info mb-0">No backup history has been recorded yet.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Run</th>
                        <th>Status</th>
                        <th>Database</th>
                        <th>Uploads</th>
                        <?php if ($b2Enabled): ?><th>Off-Server</th><?php endif; ?>
                        <th>Completed</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($runs as $run): ?>
                    <?php
                    $byType = [];
                    foreach (($run['artifacts'] ?? []) as $artifact) {
                        $byType[(string) $artifact['artifact_type']] = $artifact;
                    }
                    $dbArt = $byType['database'] ?? null;
                    $upArt = $byType['uploads'] ?? null;

                    // Determine per-run B2 status for the Off-Server column
                    $runB2Html = '—';
                    if ($b2Enabled) {
                        $runArtifacts = $run['artifacts'] ?? [];
                        $hasLocal  = false;
                        $allSynced = true;
                        $anyFailed = false;
                        foreach ($runArtifacts as $a) {
                            if (($a['status'] ?? '') === 'success') {
                                $hasLocal = true;
                                if ((int) ($a['b2_uploaded'] ?? 0) === 1) {
                                    // ok
                                } else {
                                    $allSynced = false;
                                    if (!empty($a['b2_upload_error'])) {
                                        $anyFailed = true;
                                    }
                                }
                            }
                        }
                        if (!$hasLocal) {
                            $runB2Html = '<span class="text-muted small">No local artifacts</span>';
                        } elseif ($allSynced) {
                            $runB2Html = '<span class="badge text-bg-success"><i class="bi bi-cloud-check"></i> Synced</span>';
                        } elseif ($anyFailed) {
                            $runB2Html = '<span class="badge text-bg-danger"><i class="bi bi-exclamation-triangle"></i> Sync failed</span>';
                        } else {
                            $runB2Html = '<span class="badge text-bg-warning text-dark"><i class="bi bi-cloud-slash"></i> Not synced</span>';
                        }
                    }
                    ?>
                    <tr>
                        <td>
                            <div class="fw-semibold">#<?= e((string) $run['id']); ?></div>
                            <div class="small text-muted"><?= e(ucfirst((string) $run['trigger_source'])); ?></div>
                        </td>
                        <td>
                            <span class="badge text-bg-<?= e(($run['status'] ?? '') === 'success' ? 'success' : (($run['status'] ?? '') === 'partial' ? 'warning' : 'danger')); ?>">
                                <?= e(ucfirst((string) $run['status'])); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($dbArt !== null): ?>
                            <div class="fw-semibold"><?= e(ucfirst((string) $dbArt['status'])); ?></div>
                            <div class="small text-muted"><?= e($formatBytes(isset($dbArt['size_bytes']) ? (int) $dbArt['size_bytes'] : 0)); ?></div>
                            <div class="small text-muted">
                                <?= !empty($dbArt['latest_active_token_expires_at']) ? 'Link live until ' . e((string) $dbArt['latest_active_token_expires_at']) : 'No active link'; ?>
                            </div>
                            <?php else: ?>
                            <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($upArt !== null): ?>
                            <div class="fw-semibold"><?= e(ucfirst((string) $upArt['status'])); ?></div>
                            <div class="small text-muted"><?= e($formatBytes(isset($upArt['size_bytes']) ? (int) $upArt['size_bytes'] : 0)); ?></div>
                            <div class="small text-muted">
                                <?= !empty($upArt['latest_active_token_expires_at']) ? 'Link live until ' . e((string) $upArt['latest_active_token_expires_at']) : 'No active link'; ?>
                            </div>
                            <?php else: ?>
                            <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <?php if ($b2Enabled): ?>
                        <td><?= $runB2Html; ?></td>
                        <?php endif; ?>
                        <td><?= e((string) ($run['completed_at'] ?? $run['started_at'] ?? '—')); ?></td>
                        <td class="text-end">
                            <form method="post" action="<?= e(url('/admin/resilience/backups/' . (int) $run['id'] . '/links')); ?>" class="d-inline">
                                <?= csrf_field(); ?>
                                <button type="submit" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-arrow-repeat"></i> Refresh Links
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</section>
