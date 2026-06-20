<?php

declare(strict_types=1);

namespace App\Modules\Resilience;

use RuntimeException;
use Throwable;

/**
 * Uploads backup artifacts to Backblaze B2 using the B2 native API v2 over cURL.
 * No Composer dependency required — plain cURL only.
 *
 * Errors are caught internally and returned as structured results; nothing propagates
 * out to the caller, so a B2 failure can never block the local backup process.
 */
final class B2BackupUploader
{
    private const AUTH_URL = 'https://api.backblazeb2.com/b2api/v2/b2_authorize_account';

    private string $keyId;
    private string $appKey;
    private string $bucketName;
    private int $timeout;

    private ?string $authToken  = null;
    private ?string $apiUrl     = null;
    private ?string $accountId  = null;
    private ?string $bucketId   = null;

    public function __construct(string $keyId, string $appKey, string $bucketName, int $timeout = 60)
    {
        $this->keyId      = $keyId;
        $this->appKey     = $appKey;
        $this->bucketName = $bucketName;
        $this->timeout    = max(10, $timeout);
    }

    /**
     * Upload a local file to B2 under the given remote key (path in bucket).
     *
     * @return array{ok: bool, object_key: string|null, error: string|null}
     */
    public function upload(string $localPath, string $remoteKey): array
    {
        try {
            if (!is_file($localPath) || !is_readable($localPath)) {
                return $this->failure('Local file not found or not readable: ' . $localPath);
            }

            $this->authorize();
            $this->resolveBucketId();

            $uploadMeta = $this->getUploadUrl();
            $uploadUrl  = (string) ($uploadMeta['uploadUrl'] ?? '');
            $uploadAuth = (string) ($uploadMeta['authorizationToken'] ?? '');

            if ($uploadUrl === '' || $uploadAuth === '') {
                return $this->failure('B2 did not return a valid upload URL or token.');
            }

            $fileSize = filesize($localPath);
            $sha1     = sha1_file($localPath);

            if ($fileSize === false || $sha1 === false) {
                return $this->failure('Could not read file size or compute SHA1 for: ' . $localPath);
            }

            $fp = fopen($localPath, 'rb');
            if ($fp === false) {
                return $this->failure('Could not open file for reading: ' . $localPath);
            }

            try {
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL            => $uploadUrl,
                    CURLOPT_CUSTOMREQUEST  => 'POST',
                    CURLOPT_UPLOAD         => true,
                    CURLOPT_INFILE         => $fp,
                    CURLOPT_INFILESIZE     => $fileSize,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => $this->timeout,
                    CURLOPT_HTTPHEADER     => [
                        'Authorization: '    . $uploadAuth,
                        'X-Bz-File-Name: '  . rawurlencode($remoteKey),
                        'Content-Type: application/octet-stream',
                        'Content-Length: '  . $fileSize,
                        'X-Bz-Content-Sha1: ' . $sha1,
                    ],
                ]);

                $raw      = curl_exec($ch);
                $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlErr  = curl_error($ch);
                curl_close($ch);
            } finally {
                fclose($fp);
            }

            if ($curlErr !== '') {
                return $this->failure('cURL error during upload: ' . $curlErr);
            }

            if ($httpCode < 200 || $httpCode >= 300) {
                $body = is_string($raw) ? $this->decodeJson($raw) : [];
                $msg  = (string) ($body['message'] ?? $body['code'] ?? 'HTTP ' . $httpCode);
                return $this->failure('B2 upload rejected: ' . $msg);
            }

            return ['ok' => true, 'object_key' => $remoteKey, 'error' => null];
        } catch (Throwable $e) {
            return $this->failure($e->getMessage());
        }
    }

    /**
     * Validate credentials and bucket access without uploading any file.
     *
     * @return array{bucket_name: string, bucket_id: string}
     */
    public function validateConnection(): array
    {
        $this->authorize();
        $this->resolveBucketId();

        return [
            'bucket_name' => $this->bucketName,
            'bucket_id' => (string) $this->bucketId,
        ];
    }

    /**
     * Delete files in the bucket whose upload timestamp is older than $retentionDays.
     * Silently swallows errors — retention cleanup failure must never crash anything.
     */
    public function deleteFilesOlderThan(int $retentionDays): void
    {
        try {
            $this->authorize();
            $this->resolveBucketId();

            $cutoffMs = (int) (microtime(true) * 1000) - ($retentionDays * 86400 * 1000);
            $startFileName = null;

            do {
                $body = [
                    'bucketId'     => $this->bucketId,
                    'maxFileCount' => 1000,
                ];
                if ($startFileName !== null) {
                    $body['startFileName'] = $startFileName;
                }

                $response = $this->apiPost('/b2api/v2/b2_list_file_names', $body);
                $files    = $response['files'] ?? [];

                foreach ($files as $file) {
                    $ts = (int) ($file['uploadTimestamp'] ?? PHP_INT_MAX);
                    if ($ts < $cutoffMs) {
                        try {
                            $this->apiPost('/b2api/v2/b2_delete_file_version', [
                                'fileId'   => $file['fileId'],
                                'fileName' => $file['fileName'],
                            ]);
                        } catch (Throwable $e) {
                            error_log('[B2 Cleanup] Failed to delete ' . ($file['fileName'] ?? '') . ': ' . $e->getMessage());
                        }
                    }
                }

                $startFileName = $response['nextFileName'] ?? null;
            } while ($startFileName !== null && $files !== []);
        } catch (Throwable $e) {
            error_log('[B2 Cleanup] Retention cleanup failed: ' . $e->getMessage());
        }
    }

    // ------------------------------------------------------------------
    // Internal API helpers
    // ------------------------------------------------------------------

    private function authorize(): void
    {
        if ($this->authToken !== null) {
            return;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => self::AUTH_URL,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Basic ' . base64_encode($this->keyId . ':' . $this->appKey),
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
        ]);

        $raw      = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr !== '') {
            throw new RuntimeException('B2 auth cURL error: ' . $curlErr);
        }

        $body = $this->decodeJson(is_string($raw) ? $raw : '');

        if ($httpCode !== 200) {
            $msg = (string) ($body['message'] ?? $body['code'] ?? 'HTTP ' . $httpCode);
            throw new RuntimeException('B2 authorization failed: ' . $msg);
        }

        $this->authToken = (string) ($body['authorizationToken'] ?? '');
        $this->apiUrl    = rtrim((string) ($body['apiUrl'] ?? ''), '/');
        $this->accountId = (string) ($body['accountId'] ?? '');

        if ($this->authToken === '' || $this->apiUrl === '') {
            throw new RuntimeException('B2 authorization response was missing required fields.');
        }
    }

    private function resolveBucketId(): void
    {
        if ($this->bucketId !== null) {
            return;
        }

        $response = $this->apiGet('/b2api/v2/b2_list_buckets', [
            'accountId'  => $this->accountId,
            'bucketName' => $this->bucketName,
        ]);

        $buckets = $response['buckets'] ?? [];
        if (empty($buckets)) {
            throw new RuntimeException('B2 bucket not found: ' . $this->bucketName);
        }

        $this->bucketId = (string) ($buckets[0]['bucketId'] ?? '');
        if ($this->bucketId === '') {
            throw new RuntimeException('B2 bucket ID was empty for bucket: ' . $this->bucketName);
        }
    }

    private function getUploadUrl(): array
    {
        return $this->apiPost('/b2api/v2/b2_get_upload_url', [
            'bucketId' => $this->bucketId,
        ]);
    }

    private function apiPost(string $path, array $payload): array
    {
        $url  = $this->apiUrl . $path;
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_HTTPHEADER     => [
                'Authorization: ' . $this->authToken,
                'Content-Type: application/json',
                'Content-Length: ' . strlen((string) $json),
            ],
        ]);

        $raw      = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr !== '') {
            throw new RuntimeException('B2 API cURL error on ' . $path . ': ' . $curlErr);
        }

        $body = $this->decodeJson(is_string($raw) ? $raw : '');

        if ($httpCode < 200 || $httpCode >= 300) {
            $msg = (string) ($body['message'] ?? $body['code'] ?? 'HTTP ' . $httpCode);
            throw new RuntimeException('B2 API error on ' . $path . ': ' . $msg);
        }

        return $body;
    }

    private function apiGet(string $path, array $params = []): array
    {
        $url = $this->apiUrl . $path;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_HTTPHEADER     => [
                'Authorization: ' . $this->authToken,
            ],
        ]);

        $raw      = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr !== '') {
            throw new RuntimeException('B2 API cURL error on ' . $path . ': ' . $curlErr);
        }

        $body = $this->decodeJson(is_string($raw) ? $raw : '');

        if ($httpCode < 200 || $httpCode >= 300) {
            $msg = (string) ($body['message'] ?? $body['code'] ?? 'HTTP ' . $httpCode);
            throw new RuntimeException('B2 API error on ' . $path . ': ' . $msg);
        }

        return $body;
    }

    private function decodeJson(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /** @return array{ok: false, object_key: null, error: string} */
    private function failure(string $message): array
    {
        error_log('[B2 Upload] ' . $message);
        return ['ok' => false, 'object_key' => null, 'error' => $message];
    }
}
