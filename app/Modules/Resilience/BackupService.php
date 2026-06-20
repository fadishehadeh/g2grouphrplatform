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

            $status = $this->resolveRunStatus($artifacts);
            $summary = $this->buildRunSummary($artifacts);
            $this->repository->completeRun($runId, $status, $summary, new \DateTimeImmutable('now'));

            $run = $this->repository->findRun($runId);
            if ($run === null) {
                throw new RuntimeException('Backup run history could not be loaded after completion.');
            }

            $tokens = $this->generateLinksForRun($run, $initiatedByUserId, '');
            $this->sendRunEmail($run, $tokens);
            $this->cleanupExpiredArtifacts();
            $this->repository->purgeExpiredTokens();

            return [
                'run' => $this->repository->findRun($runId),
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

    private function sendRunEmail(array $run, array $tokens): void
    {
        $recipients = $this->repository->superAdminRecipients();
        if ($recipients === []) {
            return;
        }

        $notifications = new NotificationRepository($this->database);
        $subject = match ((string) ($run['status'] ?? 'failed')) {
            'success' => 'Daily backup completed successfully',
            'partial' => 'Daily backup completed with warnings',
            default => 'Daily backup failed',
        };

        $artifactMap = [];
        foreach (($run['artifacts'] ?? []) as $artifact) {
            $artifactMap[(string) $artifact['artifact_type']] = $artifact;
        }

        $databaseArtifact = $artifactMap['database'] ?? null;
        $uploadsArtifact = $artifactMap['uploads'] ?? null;
        $completedAt = (string) ($run['completed_at'] ?? $run['started_at'] ?? '');

        $bodyHtml = '<p>The daily resilience backup has finished.</p>';
        $bodyHtml .= '<table style="width:100%;border-collapse:collapse;font-size:14px;margin:16px 0;">';
        $bodyHtml .= '<tr><td style="padding:8px;border:1px solid #ddd;background:#f8f9fa;font-weight:600;">Run ID</td><td style="padding:8px;border:1px solid #ddd;">#' . e((string) $run['id']) . '</td></tr>';
        $bodyHtml .= '<tr><td style="padding:8px;border:1px solid #ddd;background:#f8f9fa;font-weight:600;">Status</td><td style="padding:8px;border:1px solid #ddd;">' . e(ucfirst((string) $run['status'])) . '</td></tr>';
        $bodyHtml .= '<tr><td style="padding:8px;border:1px solid #ddd;background:#f8f9fa;font-weight:600;">Completed</td><td style="padding:8px;border:1px solid #ddd;">' . e($completedAt) . '</td></tr>';
        $bodyHtml .= '</table>';
        $bodyHtml .= $this->artifactEmailBlock('Database Backup', $databaseArtifact, $tokens['database'] ?? null);
        $bodyHtml .= $this->artifactEmailBlock('Uploads Backup', $uploadsArtifact, $tokens['uploads'] ?? null);

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

    private function artifactEmailBlock(string $label, ?array $artifact, ?string $token): string
    {
        if ($artifact === null) {
            return '<p><strong>' . e($label) . ':</strong> No artifact record was created.</p>';
        }

        $html = '<div style="margin:18px 0;padding:16px;border:1px solid #e5e7eb;border-radius:8px;">';
        $html .= '<h3 style="margin:0 0 12px;font-size:16px;">' . e($label) . '</h3>';
        $html .= '<p style="margin:0 0 8px;"><strong>Status:</strong> ' . e(ucfirst((string) $artifact['status'])) . '</p>';

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

        $html .= '</div>';

        return $html;
    }

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

    private function resolveRunStatus(array $artifacts): string
    {
        $statuses = array_values(array_map(
            static fn (array $artifact): string => (string) ($artifact['status'] ?? 'failed'),
            $artifacts
        ));

        if ($statuses !== [] && count(array_unique($statuses)) === 1 && $statuses[0] === 'success') {
            return 'success';
        }

        if (in_array('success', $statuses, true)) {
            return 'partial';
        }

        return 'failed';
    }

    private function buildRunSummary(array $artifacts): string
    {
        $parts = [];
        foreach ($artifacts as $type => $artifact) {
            if (($artifact['status'] ?? '') === 'success') {
                $parts[] = ucfirst((string) $type) . ' backup ready (' . $this->formatBytes((int) ($artifact['size_bytes'] ?? 0)) . ')';
            } else {
                $parts[] = ucfirst((string) $type) . ' backup failed';
            }
        }

        return implode('; ', $parts);
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
