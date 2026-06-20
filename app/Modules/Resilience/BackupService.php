<?php

declare(strict_types=1);

namespace App\Modules\Resilience;

use App\Core\Application;
use App\Core\Database;
use App\Modules\Notifications\NotificationRepository;
use RuntimeException;
use Throwable;
use ZipArchive;

final class BackupService
{
    private Application $app;
    private Database $database;
    private BackupRepository $repository;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->database = $app->database();
        $this->repository = new BackupRepository($this->database);
    }

    public function repository(): BackupRepository
    {
        return $this->repository;
    }

    public function validateConfiguration(): array
    {
        $configuredDir = trim((string) config('app.backups.storage_dir', 'storage/backups'));
        if ($configuredDir === '') {
            throw new RuntimeException('Backup storage directory is empty.');
        }

        $absoluteDir = $this->absoluteBackupDirectory();
        $checkPath = is_dir($absoluteDir) ? $absoluteDir : dirname($absoluteDir);

        if (!is_dir($checkPath)) {
            throw new RuntimeException('Backup storage parent directory does not exist: ' . $checkPath);
        }

        if (!is_writable($checkPath)) {
            throw new RuntimeException('Backup storage path is not writable: ' . $checkPath);
        }

        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('ZipArchive is required to generate uploads backups.');
        }

        $roots = [
            base_path('storage/uploads'),
            base_path('storage/announcements'),
            base_path('public-hr/assets/uploads'),
        ];

        $availableRoots = array_values(array_filter($roots, static fn (string $path): bool => is_dir($path)));
        if ($availableRoots === []) {
            throw new RuntimeException('None of the uploads backup source directories exist.');
        }

        return [
            'storage_dir' => $configuredDir,
            'absolute_dir' => $absoluteDir,
            'writable_check_path' => $checkPath,
            'source_directories' => $availableRoots,
        ];
    }

    public function runDailyBackup(?int $initiatedByUserId = null, string $triggerSource = 'daily'): array
    {
        $startedAt = new \DateTimeImmutable('now');
        $runId = $this->repository->createRun($initiatedByUserId, $triggerSource, $startedAt);

        $artifacts = [];
        $baseBackupDir = $this->absoluteBackupDirectory();
        $this->ensureDirectory($baseBackupDir);
        $dayDir = $baseBackupDir . DIRECTORY_SEPARATOR . $startedAt->format('Ymd');
        $this->ensureDirectory($dayDir);
        $stamp = $startedAt->format('Ymd_His');

        try {
            // ------------------------------------------------------------------
            // 1. Local backups (existing behaviour — unchanged)
            // ------------------------------------------------------------------
            try {
                $databaseFile = $dayDir . DIRECTORY_SEPARATOR . 'database_' . $stamp . '.sql';
                $this->writeDatabaseDump($databaseFile);
                $artifacts['database'] = $this->persistArtifact($runId, 'database', $databaseFile);
            } catch (Throwable $throwable) {
                $artifacts['database'] = $this->persistArtifact($runId, 'database', null, $throwable);
            }

            try {
                $uploadsFile = $dayDir . DIRECTORY_SEPARATOR . 'uploads_' . $stamp . '.zip';
                $this->writeUploadsArchive($uploadsFile);
                $artifacts['uploads'] = $this->persistArtifact($runId, 'uploads', $uploadsFile);
            } catch (Throwable $throwable) {
                $artifacts['uploads'] = $this->persistArtifact($runId, 'uploads', null, $throwable);
            }

            // ------------------------------------------------------------------
            // 2. Off-server upload to B2 (additive — never blocks or throws)
            // ------------------------------------------------------------------
            $b2Enabled  = $this->isB2Enabled();
            $b2Results  = [];

            if ($b2Enabled) {
                $b2Results = $this->uploadArtifactsToB2($artifacts);
            }

            // ------------------------------------------------------------------
            // 3. Resolve final run status (B2 failure degrades success → partial)
            // ------------------------------------------------------------------
            $status  = $this->resolveRunStatus($artifacts, $b2Enabled ? $b2Results : null);
            $summary = $this->buildRunSummary($artifacts, $b2Enabled ? $b2Results : null);
            $this->repository->completeRun($runId, $status, $summary, new \DateTimeImmutable('now'));

            // ------------------------------------------------------------------
            // 4. Reload run (artifacts now include b2_uploaded columns), send email
            // ------------------------------------------------------------------
            $run = $this->repository->findRun($runId);
            if ($run === null) {
                throw new RuntimeException('Backup run history could not be loaded after completion.');
            }

            $tokens = $this->generateLinksForRun($run, $initiatedByUserId, '');
            $this->sendRunEmail($run, $tokens, $b2Enabled);

            // ------------------------------------------------------------------
            // 5. Local retention cleanup + B2 retention cleanup
            // ------------------------------------------------------------------
            $this->cleanupExpiredArtifacts();
            $this->repository->purgeExpiredTokens();

            if ($b2Enabled) {
                $this->cleanupB2OldFiles();
            }

            return [
                'run'    => $this->repository->findRun($runId),
                'tokens' => $tokens,
            ];
        } catch (Throwable $throwable) {
            $this->repository->completeRun($runId, 'failed', $throwable->getMessage(), new \DateTimeImmutable('now'));
            throw $throwable;
        }
    }

    public function generateLinksForRun(array $run, ?int $createdByUserId, string $ipAddress): array
    {
        $tokens = [];
        $ttlDays = (int) config('app.backups.link_ttl_days', 7);

        foreach (($run['artifacts'] ?? []) as $artifact) {
            if (($artifact['status'] ?? '') !== 'success' || empty($artifact['relative_path'])) {
                continue;
            }

            $tokens[(string) $artifact['artifact_type']] = $this->repository->createDownloadToken(
                (int) $artifact['id'],
                $createdByUserId,
                $ipAddress,
                $ttlDays
            );
        }

        return $tokens;
    }

    // ------------------------------------------------------------------
    // B2 upload helpers
    // ------------------------------------------------------------------

    private function isB2Enabled(): bool
    {
        if (!filter_var(config('app.b2.enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        $keyId  = (string) config('app.b2.key_id', '');
        $appKey = (string) config('app.b2.application_key', '');
        $bucket = (string) config('app.b2.bucket_name', '');

        return $keyId !== '' && $appKey !== '' && $bucket !== '';
    }

    /**
     * Upload each successful local artifact to B2 and persist the result.
     * Never throws — all errors are captured and returned.
     *
     * @return array<string, array{ok: bool, object_key: string|null, error: string|null}>
     */
    private function uploadArtifactsToB2(array $artifacts): array
    {
        $results = [];

        try {
            $uploader = new B2BackupUploader(
                (string) config('app.b2.key_id'),
                (string) config('app.b2.application_key'),
                (string) config('app.b2.bucket_name'),
                60
            );
        } catch (Throwable $e) {
            error_log('[B2 Upload] Failed to initialise uploader: ' . $e->getMessage());
            foreach ($artifacts as $type => $artifact) {
                $results[$type] = ['ok' => false, 'object_key' => null, 'error' => 'Uploader init failed: ' . $e->getMessage()];
            }
            return $results;
        }

        foreach ($artifacts as $type => $artifact) {
            if (($artifact['status'] ?? '') !== 'success' || empty($artifact['relative_path'])) {
                // No successful local artifact to sync — skip without recording an error
                continue;
            }

            $absolutePath = base_path((string) $artifact['relative_path']);
            $remoteKey    = $type . '/' . basename((string) $artifact['relative_path']);

            $result = $uploader->upload($absolutePath, $remoteKey);
            $results[$type] = $result;

            try {
                $this->repository->updateArtifactB2Status(
                    (int) $artifact['id'],
                    $result['ok'],
                    $result['object_key'],
                    $result['error']
                );
            } catch (Throwable $e) {
                error_log('[B2 Upload] Failed to persist B2 status for artifact ' . $artifact['id'] . ': ' . $e->getMessage());
            }
        }

        return $results;
    }

    private function cleanupB2OldFiles(): void
    {
        try {
            $retentionDays = (int) config('app.backups.retention_days', 30);
            $uploader = new B2BackupUploader(
                (string) config('app.b2.key_id'),
                (string) config('app.b2.application_key'),
                (string) config('app.b2.bucket_name'),
                60
            );
            $uploader->deleteFilesOlderThan($retentionDays);
        } catch (Throwable $e) {
            error_log('[B2 Cleanup] Error during retention cleanup: ' . $e->getMessage());
        }
    }

    // ------------------------------------------------------------------
    // Status + summary resolution
    // ------------------------------------------------------------------

    private function resolveRunStatus(array $artifacts, ?array $b2Results = null): string
    {
        $statuses = array_values(array_map(
            static fn (array $artifact): string => (string) ($artifact['status'] ?? 'failed'),
            $artifacts
        ));

        if ($statuses !== [] && count(array_unique($statuses)) === 1 && $statuses[0] === 'success') {
            $localStatus = 'success';
        } elseif (in_array('success', $statuses, true)) {
            $localStatus = 'partial';
        } else {
            $localStatus = 'failed';
        }

        if ($b2Results === null || $b2Results === []) {
            return $localStatus;
        }

        // B2 failure on any artifact that had a successful local backup degrades to partial
        $b2HasFailure = false;
        foreach ($b2Results as $result) {
            if (!($result['ok'] ?? false)) {
                $b2HasFailure = true;
                break;
            }
        }

        if ($localStatus === 'success' && $b2HasFailure) {
            return 'partial';
        }

        return $localStatus;
    }

    private function buildRunSummary(array $artifacts, ?array $b2Results = null): string
    {
        $parts = [];
        foreach ($artifacts as $type => $artifact) {
            if (($artifact['status'] ?? '') === 'success') {
                $parts[] = ucfirst((string) $type) . ' backup ready (' . $this->formatBytes((int) ($artifact['size_bytes'] ?? 0)) . ')';
            } else {
                $parts[] = ucfirst((string) $type) . ' backup failed';
            }
        }

        if ($b2Results !== null && $b2Results !== []) {
            $allOk = true;
            foreach ($b2Results as $result) {
                if (!($result['ok'] ?? false)) {
                    $allOk = false;
                    break;
                }
            }
            $parts[] = 'Off-server sync: ' . ($allOk ? 'success' : 'partial/failed');
        }

        return implode('; ', $parts);
    }

    // ------------------------------------------------------------------
    // Email
    // ------------------------------------------------------------------

    private function sendRunEmail(array $run, array $tokens, bool $b2Enabled = false): void
    {
        $recipients = $this->repository->superAdminRecipients();
        if ($recipients === []) {
            return;
        }

        $notifications = new NotificationRepository($this->database);
        $subject = match ((string) ($run['status'] ?? 'failed')) {
            'success' => 'Daily backup completed successfully',
            'partial' => 'Daily backup completed with warnings',
            default   => 'Daily backup failed',
        };

        $artifactMap = [];
        foreach (($run['artifacts'] ?? []) as $artifact) {
            $artifactMap[(string) $artifact['artifact_type']] = $artifact;
        }

        $databaseArtifact = $artifactMap['database'] ?? null;
        $uploadsArtifact  = $artifactMap['uploads'] ?? null;
        $completedAt = (string) ($run['completed_at'] ?? $run['started_at'] ?? '');

        $bodyHtml  = '<p>The daily resilience backup has finished.</p>';
        $bodyHtml .= '<table style="width:100%;border-collapse:collapse;font-size:14px;margin:16px 0;">';
        $bodyHtml .= '<tr><td style="padding:8px;border:1px solid #ddd;background:#f8f9fa;font-weight:600;">Run ID</td><td style="padding:8px;border:1px solid #ddd;">#' . e((string) $run['id']) . '</td></tr>';
        $bodyHtml .= '<tr><td style="padding:8px;border:1px solid #ddd;background:#f8f9fa;font-weight:600;">Status</td><td style="padding:8px;border:1px solid #ddd;">' . e(ucfirst((string) $run['status'])) . '</td></tr>';
        $bodyHtml .= '<tr><td style="padding:8px;border:1px solid #ddd;background:#f8f9fa;font-weight:600;">Completed</td><td style="padding:8px;border:1px solid #ddd;">' . e($completedAt) . '</td></tr>';
        $bodyHtml .= '</table>';
        $bodyHtml .= $this->artifactEmailBlock('Database Backup', $databaseArtifact, $tokens['database'] ?? null, $b2Enabled);
        $bodyHtml .= $this->artifactEmailBlock('Uploads Backup', $uploadsArtifact, $tokens['uploads'] ?? null, $b2Enabled);

        if (!empty($run['summary_message'])) {
            $bodyHtml .= '<p style="margin-top:18px;"><strong>Summary:</strong> ' . e((string) $run['summary_message']) . '</p>';
        }

        $bodyText = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $bodyHtml)));

        foreach ($recipients as $recipient) {
            $notifications->queueEmail(
                (string) $recipient['email'],
                $subject,
                $bodyHtml,
                $bodyText,
                isset($recipient['user_id']) ? (int) $recipient['user_id'] : null,
                'backup_run',
                (int) $run['id']
            );
        }
    }

    private function artifactEmailBlock(string $label, ?array $artifact, ?string $token, bool $b2Enabled = false): string
    {
        if ($artifact === null) {
            return '<p><strong>' . e($label) . ':</strong> No artifact record was created.</p>';
        }

        $html  = '<div style="margin:18px 0;padding:16px;border:1px solid #e5e7eb;border-radius:8px;">';
        $html .= '<h3 style="margin:0 0 12px;font-size:16px;">' . e($label) . '</h3>';
        $html .= '<p style="margin:0 0 8px;"><strong>Local status:</strong> ' . e(ucfirst((string) $artifact['status'])) . '</p>';

        if (($artifact['status'] ?? '') === 'success') {
            $html .= '<p style="margin:0 0 8px;"><strong>File Size:</strong> ' . e($this->formatBytes((int) ($artifact['size_bytes'] ?? 0))) . '</p>';
            if ($token !== null) {
                $downloadUrl = url('/admin/resilience/backups/download/' . $token);
                $expiresAt = (new \DateTimeImmutable('now'))
                    ->modify('+' . (int) config('app.backups.link_ttl_days', 7) . ' days')
                    ->format('Y-m-d H:i:s');
                $html .= '<p style="margin:0 0 10px;"><strong>Download Link:</strong> <a href="' . e($downloadUrl) . '">' . e($downloadUrl) . '</a></p>';
                $html .= '<p style="margin:0;color:#6b7280;font-size:12px;">This secure link expires after 7 days and requires a signed-in super admin session.</p>';
            } else {
                $html .= '<p style="margin:0 0 10px;color:#92400e;"><strong>Download Link:</strong> could not be generated.</p>';
            }
        } else {
            $html .= '<p style="margin:0;color:#991b1b;"><strong>Error:</strong> ' . e((string) ($artifact['error_message'] ?? 'Backup artifact failed.')) . '</p>';
        }

        // Off-server sync status
        if ($b2Enabled) {
            $html .= '<hr style="margin:12px 0;border:none;border-top:1px solid #e5e7eb;">';
            if ((int) ($artifact['b2_uploaded'] ?? 0) === 1) {
                $html .= '<p style="margin:0;color:#065f46;"><strong>&#9729; Off-server sync:</strong> Synced to Backblaze B2</p>';
            } else {
                $b2Error = (string) ($artifact['b2_upload_error'] ?? '');
                if ($b2Error !== '') {
                    $html .= '<p style="margin:0;color:#991b1b;"><strong>&#9888; Off-server sync:</strong> Failed — ' . e($b2Error) . '</p>';
                } elseif (($artifact['status'] ?? '') !== 'success') {
                    $html .= '<p style="margin:0;color:#92400e;"><strong>&#9729; Off-server sync:</strong> Skipped (local backup did not succeed)</p>';
                } else {
                    $html .= '<p style="margin:0;color:#92400e;"><strong>&#9888; Off-server sync:</strong> Not uploaded</p>';
                }
            }
        } else {
            $html .= '<p style="margin:12px 0 0;color:#6b7280;font-size:12px;"><em>Off-server backup is not configured.</em></p>';
        }

        $html .= '</div>';

        return $html;
    }

    // ------------------------------------------------------------------
    // Existing private methods (unchanged)
    // ------------------------------------------------------------------

    private function persistArtifact(int $runId, string $artifactType, ?string $absolutePath, ?Throwable $failure = null): array
    {
        if ($failure !== null) {
            $artifactId = $this->repository->createArtifact($runId, $artifactType, [
                'status' => 'failed',
                'error_message' => substr($failure->getMessage(), 0, 1000),
            ]);

            $artifact = $this->repository->findArtifact($artifactId);
            if ($artifact === null) {
                throw new RuntimeException('Failed artifact record could not be loaded.');
            }

            return $artifact;
        }

        if ($absolutePath === null || !is_file($absolutePath)) {
            throw new RuntimeException('Backup artifact file was not created.');
        }

        $relativePath = $this->relativeFromBase($absolutePath);
        $artifactId = $this->repository->createArtifact($runId, $artifactType, [
            'status' => 'success',
            'relative_path' => $relativePath,
            'file_name' => basename($absolutePath),
            'size_bytes' => filesize($absolutePath) ?: 0,
            'checksum_sha256' => hash_file('sha256', $absolutePath) ?: null,
        ]);

        $artifact = $this->repository->findArtifact($artifactId);
        if ($artifact === null) {
            throw new RuntimeException('Backup artifact record could not be loaded.');
        }

        return $artifact;
    }

    private function writeDatabaseDump(string $destination): void
    {
        $pdo = $this->database->connection();
        $tables = $this->database->fetchAll('SHOW FULL TABLES WHERE Table_type = :type', ['type' => 'BASE TABLE']);
        $lines = [];
        $lines[] = '-- HR System database backup';
        $lines[] = '-- Generated at ' . date('Y-m-d H:i:s');
        $lines[] = 'SET FOREIGN_KEY_CHECKS=0;';
        $lines[] = '';

        foreach ($tables as $tableRow) {
            $tableName = (string) array_values($tableRow)[0];
            $createRow = $this->database->fetch('SHOW CREATE TABLE `' . str_replace('`', '``', $tableName) . '`');
            if ($createRow === null) {
                continue;
            }

            $createSql = (string) ($createRow['Create Table'] ?? array_values($createRow)[1] ?? '');
            $lines[] = '-- Table structure for `' . $tableName . '`';
            $lines[] = 'DROP TABLE IF EXISTS `' . $tableName . '`;';
            $lines[] = $createSql . ';';
            $lines[] = '';

            $rows = $this->database->fetchAll('SELECT * FROM `' . str_replace('`', '``', $tableName) . '`');
            if ($rows === []) {
                continue;
            }

            $columns = array_map(
                static fn (string $column): string => '`' . str_replace('`', '``', $column) . '`',
                array_keys($rows[0])
            );

            foreach ($rows as $row) {
                $values = [];
                foreach ($row as $value) {
                    if ($value === null) {
                        $values[] = 'NULL';
                    } elseif (is_int($value) || is_float($value)) {
                        $values[] = (string) $value;
                    } else {
                        $values[] = $pdo->quote((string) $value);
                    }
                }

                $lines[] = 'INSERT INTO `' . $tableName . '` (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ');';
            }

            $lines[] = '';
        }

        $lines[] = 'SET FOREIGN_KEY_CHECKS=1;';

        $bytes = file_put_contents($destination, implode("\n", $lines));
        if ($bytes === false) {
            throw new RuntimeException('Database backup file could not be written.');
        }
    }

    private function writeUploadsArchive(string $destination): void
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('ZipArchive is required to create uploads backups.');
        }

        $roots = [
            base_path('storage/uploads'),
            base_path('storage/announcements'),
            base_path('public-hr/assets/uploads'),
        ];

        $zip = new ZipArchive();
        if ($zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Uploads backup archive could not be opened for writing.');
        }

        $added = 0;
        foreach ($roots as $root) {
            if (!is_dir($root)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                /** @var \SplFileInfo $item */
                if (!$item->isFile()) {
                    continue;
                }

                $absolutePath = $item->getPathname();
                $localPath = str_replace('\\', '/', $this->relativeFromBase($absolutePath));
                if ($zip->addFile($absolutePath, $localPath)) {
                    $added++;
                }
            }
        }

        $zip->close();

        if ($added === 0) {
            throw new RuntimeException('Uploads backup archive was created without any files. Check the configured upload directories.');
        }
    }

    private function cleanupExpiredArtifacts(): void
    {
        $retentionDays = (int) config('app.backups.retention_days', 30);
        $artifacts = $this->repository->listExpiredArtifactsForCleanup($retentionDays);

        foreach ($artifacts as $artifact) {
            $relativePath = (string) ($artifact['relative_path'] ?? '');
            if ($relativePath === '') {
                continue;
            }

            $absolutePath = base_path($relativePath);
            if (is_file($absolutePath)) {
                @unlink($absolutePath);
            }

            $this->repository->markArtifactDeleted((int) $artifact['id']);
        }
    }

    private function absoluteBackupDirectory(): string
    {
        $configured = trim((string) config('app.backups.storage_dir', 'storage/backups'));
        return base_path($configured);
    }

    private function ensureDirectory(string $path): void
    {
        if (is_dir($path)) {
            return;
        }

        if (!mkdir($path, 0775, true) && !is_dir($path)) {
            throw new RuntimeException('Directory could not be created: ' . $path);
        }
    }

    private function relativeFromBase(string $absolutePath): string
    {
        $normalizedBase = rtrim(str_replace('\\', '/', base_path()), '/');
        $normalizedPath = str_replace('\\', '/', $absolutePath);

        if (str_starts_with($normalizedPath, $normalizedBase . '/')) {
            return substr($normalizedPath, strlen($normalizedBase) + 1);
        }

        return ltrim($normalizedPath, '/');
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = (int) floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);

        return number_format($bytes / (1024 ** $power), $power === 0 ? 0 : 2) . ' ' . $units[$power];
    }
}
