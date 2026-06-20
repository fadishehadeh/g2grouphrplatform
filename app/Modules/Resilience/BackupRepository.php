<?php

declare(strict_types=1);

namespace App\Modules\Resilience;

use App\Core\Database;

final class BackupRepository
{
    private Database $database;

    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    public function createRun(?int $initiatedByUserId, string $triggerSource, \DateTimeImmutable $startedAt): int
    {
        $this->database->execute(
            'INSERT INTO backup_runs (initiated_by_user_id, trigger_source, status, started_at)
             VALUES (:initiated_by_user_id, :trigger_source, :status, :started_at)',
            [
                'initiated_by_user_id' => $initiatedByUserId,
                'trigger_source' => $triggerSource,
                'status' => 'running',
                'started_at' => $startedAt->format('Y-m-d H:i:s'),
            ]
        );

        return (int) $this->database->lastInsertId();
    }

    public function completeRun(int $runId, string $status, ?string $summaryMessage, \DateTimeImmutable $completedAt): void
    {
        $this->database->execute(
            'UPDATE backup_runs
             SET status = :status,
                 summary_message = :summary_message,
                 completed_at = :completed_at
             WHERE id = :id',
            [
                'id' => $runId,
                'status' => $status,
                'summary_message' => $summaryMessage,
                'completed_at' => $completedAt->format('Y-m-d H:i:s'),
            ]
        );
    }

    public function createArtifact(int $runId, string $artifactType, array $data): int
    {
        $this->database->execute(
            'INSERT INTO backup_artifacts
                (backup_run_id, artifact_type, status, relative_path, file_name, size_bytes, checksum_sha256, error_message, deleted_at)
             VALUES
                (:backup_run_id, :artifact_type, :status, :relative_path, :file_name, :size_bytes, :checksum_sha256, :error_message, :deleted_at)',
            [
                'backup_run_id' => $runId,
                'artifact_type' => $artifactType,
                'status' => $data['status'],
                'relative_path' => $data['relative_path'] ?? null,
                'file_name' => $data['file_name'] ?? null,
                'size_bytes' => $data['size_bytes'] ?? null,
                'checksum_sha256' => $data['checksum_sha256'] ?? null,
                'error_message' => $data['error_message'] ?? null,
                'deleted_at' => $data['deleted_at'] ?? null,
            ]
        );

        return (int) $this->database->lastInsertId();
    }

    public function markArtifactDeleted(int $artifactId): void
    {
        $this->database->execute(
            'UPDATE backup_artifacts SET deleted_at = NOW() WHERE id = :id',
            ['id' => $artifactId]
        );
    }

    public function findArtifact(int $artifactId): ?array
    {
        return $this->database->fetch(
            'SELECT ba.*, br.status AS run_status, br.started_at, br.completed_at
             FROM backup_artifacts ba
             INNER JOIN backup_runs br ON br.id = ba.backup_run_id
             WHERE ba.id = :id
             LIMIT 1',
            ['id' => $artifactId]
        );
    }

    public function listRecentRuns(int $limit = 20): array
    {
        $limit = max(1, min($limit, 100));
        $runs = $this->database->fetchAll(
            'SELECT br.id, br.trigger_source, br.status, br.summary_message, br.started_at, br.completed_at,
                    CONCAT_WS(\' \', u.first_name, u.last_name) AS initiated_by_name
             FROM backup_runs br
             LEFT JOIN users u ON u.id = br.initiated_by_user_id
             ORDER BY br.started_at DESC
             LIMIT ' . $limit
        );

        if ($runs === []) {
            return [];
        }

        $runIds = array_map(static fn (array $row): int => (int) $row['id'], $runs);
        $placeholders = implode(',', array_fill(0, count($runIds), '?'));
        $artifacts = $this->database->fetchAll(
            "SELECT ba.*,
                    (
                        SELECT MAX(bdt.expires_at)
                        FROM backup_download_tokens bdt
                        WHERE bdt.backup_artifact_id = ba.id
                          AND bdt.expires_at >= NOW()
                    ) AS latest_active_token_expires_at
             FROM backup_artifacts ba
             WHERE ba.backup_run_id IN ({$placeholders})
             ORDER BY ba.created_at ASC",
            $runIds
        );

        $artifactMap = [];
        foreach ($artifacts as $artifact) {
            $artifactMap[(int) $artifact['backup_run_id']][] = $artifact;
        }

        foreach ($runs as &$run) {
            $run['artifacts'] = $artifactMap[(int) $run['id']] ?? [];
        }
        unset($run);

        return $runs;
    }

    public function latestRun(): ?array
    {
        $runs = $this->listRecentRuns(1);

        return $runs[0] ?? null;
    }

    public function findRun(int $runId): ?array
    {
        $run = $this->database->fetch(
            'SELECT br.id, br.trigger_source, br.status, br.summary_message, br.started_at, br.completed_at,
                    CONCAT_WS(\' \', u.first_name, u.last_name) AS initiated_by_name
             FROM backup_runs br
             LEFT JOIN users u ON u.id = br.initiated_by_user_id
             WHERE br.id = :id
             LIMIT 1',
            ['id' => $runId]
        );

        if ($run === null) {
            return null;
        }

        $run['artifacts'] = $this->database->fetchAll(
            'SELECT ba.*,
                    (
                        SELECT MAX(bdt.expires_at)
                        FROM backup_download_tokens bdt
                        WHERE bdt.backup_artifact_id = ba.id
                          AND bdt.expires_at >= NOW()
                    ) AS latest_active_token_expires_at
             FROM backup_artifacts ba
             WHERE ba.backup_run_id = :run_id
             ORDER BY ba.created_at ASC',
            ['run_id' => $runId]
        );

        return $run;
    }

    public function createDownloadToken(int $artifactId, ?int $createdByUserId, string $ipAddress, int $ttlDays = 7): string
    {
        $token = bin2hex(random_bytes(32));
        $expiresAt = (new \DateTimeImmutable('now'))
            ->modify('+' . max(1, $ttlDays) . ' days')
            ->format('Y-m-d H:i:s');

        $this->database->execute(
            'INSERT INTO backup_download_tokens (token, backup_artifact_id, created_by_user_id, ip_address, expires_at)
             VALUES (:token, :backup_artifact_id, :created_by_user_id, :ip_address, :expires_at)',
            [
                'token' => $token,
                'backup_artifact_id' => $artifactId,
                'created_by_user_id' => $createdByUserId,
                'ip_address' => $ipAddress !== '' ? $ipAddress : null,
                'expires_at' => $expiresAt,
            ]
        );

        return $token;
    }

    public function findValidDownloadToken(string $token): ?array
    {
        return $this->database->fetch(
            'SELECT bdt.id AS token_id, bdt.token, bdt.expires_at, bdt.download_count, bdt.last_downloaded_at,
                    ba.id AS artifact_id, ba.backup_run_id, ba.artifact_type, ba.status AS artifact_status,
                    ba.relative_path, ba.file_name, ba.size_bytes, ba.deleted_at,
                    br.status AS run_status
             FROM backup_download_tokens bdt
             INNER JOIN backup_artifacts ba ON ba.id = bdt.backup_artifact_id
             INNER JOIN backup_runs br ON br.id = ba.backup_run_id
             WHERE bdt.token = :token
               AND bdt.expires_at >= NOW()
             LIMIT 1',
            ['token' => $token]
        );
    }

    public function markTokenDownloaded(int $tokenId): void
    {
        $this->database->execute(
            'UPDATE backup_download_tokens
             SET last_downloaded_at = NOW(), download_count = download_count + 1
             WHERE id = :id',
            ['id' => $tokenId]
        );
    }

    public function purgeExpiredTokens(): void
    {
        $this->database->execute('DELETE FROM backup_download_tokens WHERE expires_at < NOW()');
    }

    public function listExpiredArtifactsForCleanup(int $retentionDays): array
    {
        return $this->database->fetchAll(
            'SELECT id, relative_path
             FROM backup_artifacts
             WHERE deleted_at IS NULL
               AND relative_path IS NOT NULL
               AND created_at < DATE_SUB(NOW(), INTERVAL :retention_days DAY)',
            ['retention_days' => max(1, $retentionDays)]
        );
    }

    public function updateArtifactB2Status(int $artifactId, bool $uploaded, ?string $objectKey, ?string $error): void
    {
        $this->database->execute(
            'UPDATE backup_artifacts
             SET b2_uploaded     = :uploaded,
                 b2_object_key   = :object_key,
                 b2_upload_error = :error
             WHERE id = :id',
            [
                'uploaded'   => $uploaded ? 1 : 0,
                'object_key' => $objectKey,
                'error'      => $error !== null ? substr($error, 0, 2000) : null,
                'id'         => $artifactId,
            ]
        );
    }

    public function superAdminRecipients(): array
    {
        return $this->database->fetchAll(
            "SELECT u.id AS user_id, u.email, CONCAT_WS(' ', u.first_name, u.last_name) AS full_name
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE r.code = 'super_admin'
               AND u.status = 'active'
               AND u.email IS NOT NULL
               AND u.email <> ''
             ORDER BY u.id ASC"
        );
    }
}
