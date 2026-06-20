<?php declare(strict_types=1); ?>
<?php
$latestStatus = (string) ($latestRun['status'] ?? 'unknown');
$latestArtifacts = $latestRun['artifacts'] ?? [];
$artifactMap = [];
foreach ($latestArtifacts as $artifact) {
    $artifactMap[(string) $artifact['artifact_type']] = $artifact;
}
$databaseArtifact = $artifactMap['database'] ?? null;
$uploadsArtifact = $artifactMap['uploads'] ?? null;
$formatBytes = static function (?int $bytes): string {
    $value = (int) ($bytes ?? 0);
    if ($value <= 0) {
        return '0 B';
    }
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $power = (int) floor(log($value, 1024));
    $power = min($power, count($units) - 1);
    return number_format($value / (1024 ** $power), $power === 0 ? 0 : 2) . ' ' . $units[$power];
};
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

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card dashboard-card h-100">
            <div class="card-body">
                <span>Latest Run</span>
                <h3><?= e($latestRun !== null ? '#' . (string) $latestRun['id'] : '—'); ?></h3>
                <small class="text-muted"><?= e($latestRun['completed_at'] ?? $latestRun['started_at'] ?? 'No backups yet'); ?></small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card dashboard-card h-100">
            <div class="card-body">
                <span>Database Backup</span>
                <h3><?= e($databaseArtifact !== null ? ucfirst((string) $databaseArtifact['status']) : '—'); ?></h3>
                <small class="text-muted"><?= e($databaseArtifact !== null ? $formatBytes(isset($databaseArtifact['size_bytes']) ? (int) $databaseArtifact['size_bytes'] : 0) : 'No artifact'); ?></small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card dashboard-card h-100">
            <div class="card-body">
                <span>Uploads Backup</span>
                <h3><?= e($uploadsArtifact !== null ? ucfirst((string) $uploadsArtifact['status']) : '—'); ?></h3>
                <small class="text-muted"><?= e($uploadsArtifact !== null ? $formatBytes(isset($uploadsArtifact['size_bytes']) ? (int) $uploadsArtifact['size_bytes'] : 0) : 'No artifact'); ?></small>
            </div>
        </div>
    </div>
</div>

<section class="card content-card">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
                <h5 class="mb-1">Backup History</h5>
                <p class="text-muted mb-0">Artifacts stay on the server for 30 days. Download links expire after 7 days and require a signed-in super admin.</p>
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
                            $dbArtifact = $byType['database'] ?? null;
                            $upArtifact = $byType['uploads'] ?? null;
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
                                    <?php if ($dbArtifact !== null): ?>
                                        <div class="fw-semibold"><?= e(ucfirst((string) $dbArtifact['status'])); ?></div>
                                        <div class="small text-muted"><?= e($formatBytes(isset($dbArtifact['size_bytes']) ? (int) $dbArtifact['size_bytes'] : 0)); ?></div>
                                        <div class="small text-muted">
                                            <?= !empty($dbArtifact['latest_active_token_expires_at']) ? 'Link live until ' . e((string) $dbArtifact['latest_active_token_expires_at']) : 'No active link'; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($upArtifact !== null): ?>
                                        <div class="fw-semibold"><?= e(ucfirst((string) $upArtifact['status'])); ?></div>
                                        <div class="small text-muted"><?= e($formatBytes(isset($upArtifact['size_bytes']) ? (int) $upArtifact['size_bytes'] : 0)); ?></div>
                                        <div class="small text-muted">
                                            <?= !empty($upArtifact['latest_active_token_expires_at']) ? 'Link live until ' . e((string) $upArtifact['latest_active_token_expires_at']) : 'No active link'; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
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
