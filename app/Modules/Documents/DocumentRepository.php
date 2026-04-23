<?php

declare(strict_types=1);

namespace App\Modules\Documents;

use App\Core\Database;

final class DocumentRepository
{
    private Database $database;

    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    public function listCategories(string $search = ''): array
    {
        $sql = 'SELECT id, name, code, requires_expiry, is_active
                FROM document_categories
                WHERE 1 = 1';
        $params = [];

        if ($search !== '') {
            $sql .= ' AND (name LIKE :search_name OR code LIKE :search_code)';
            $searchValue = '%' . $search . '%';
            $params['search_name'] = $searchValue;
            $params['search_code'] = $searchValue;
        }

        $sql .= ' ORDER BY is_active DESC, name ASC';

        return $this->database->fetchAll($sql, $params);
    }

    public function createCategory(array $data): void
    {
        $this->database->execute(
            'INSERT INTO document_categories (name, code, requires_expiry, is_active)
             VALUES (:name, :code, :requires_expiry, :is_active)',
            [
                'name' => $data['name'],
                'code' => $data['code'],
                'requires_expiry' => (int) ($data['requires_expiry'] ?? 0),
                'is_active' => (int) ($data['is_active'] ?? 1),
            ]
        );
    }

    public function activeCategories(): array
    {
        return $this->database->fetchAll(
            'SELECT id, name, code, requires_expiry
             FROM document_categories
             WHERE is_active = 1
             ORDER BY name ASC'
        );
    }

    public function findCategory(int $id): ?array
    {
        return $this->database->fetch(
            'SELECT id, name, code, requires_expiry, is_active
             FROM document_categories
             WHERE id = :id
             LIMIT 1',
            ['id' => $id]
        );
    }

    // ── Document Types ──────────────────────────────────────────────────────

    public function listDocumentTypes(string $search = ''): array
    {
        $sql = 'SELECT dt.id, dt.name, dt.category_id, dt.requires_expiry, dt.is_active, dt.sort_order,
                       dc.name AS category_name, dc.code AS category_code
                FROM document_types dt
                INNER JOIN document_categories dc ON dc.id = dt.category_id
                WHERE 1 = 1';
        $params = [];

        if ($search !== '') {
            $sql .= ' AND (dt.name LIKE :search OR dc.name LIKE :search_cat)';
            $params['search']     = '%' . $search . '%';
            $params['search_cat'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY dt.is_active DESC, dc.name ASC, dt.sort_order ASC, dt.name ASC';

        return $this->database->fetchAll($sql, $params);
    }

    public function activeDocumentTypes(): array
    {
        return $this->database->fetchAll(
            'SELECT dt.id, dt.name, dt.category_id, dt.requires_expiry,
                    dc.name AS category_name, dc.code AS category_code
             FROM document_types dt
             INNER JOIN document_categories dc ON dc.id = dt.category_id
             WHERE dt.is_active = 1
             ORDER BY dc.name ASC, dt.sort_order ASC, dt.name ASC'
        );
    }

    public function findDocumentType(int $id): ?array
    {
        return $this->database->fetch(
            'SELECT dt.id, dt.name, dt.category_id, dt.requires_expiry, dt.is_active, dt.sort_order,
                    dc.name AS category_name
             FROM document_types dt
             INNER JOIN document_categories dc ON dc.id = dt.category_id
             WHERE dt.id = :id LIMIT 1',
            ['id' => $id]
        );
    }

    public function createDocumentType(array $data): void
    {
        $this->database->execute(
            'INSERT INTO document_types (name, category_id, requires_expiry, is_active, sort_order)
             VALUES (:name, :category_id, :requires_expiry, :is_active, :sort_order)',
            [
                'name'            => $data['name'],
                'category_id'     => (int) $data['category_id'],
                'requires_expiry' => (int) ($data['requires_expiry'] ?? 0),
                'is_active'       => (int) ($data['is_active'] ?? 1),
                'sort_order'      => (int) ($data['sort_order'] ?? 0),
            ]
        );
    }

    public function updateDocumentType(int $id, array $data): void
    {
        $this->database->execute(
            'UPDATE document_types
             SET name = :name, category_id = :category_id,
                 requires_expiry = :requires_expiry, is_active = :is_active, sort_order = :sort_order
             WHERE id = :id',
            [
                'name'            => $data['name'],
                'category_id'     => (int) $data['category_id'],
                'requires_expiry' => (int) ($data['requires_expiry'] ?? 0),
                'is_active'       => (int) ($data['is_active'] ?? 1),
                'sort_order'      => (int) ($data['sort_order'] ?? 0),
                'id'              => $id,
            ]
        );
    }

    public function listDocuments(string $search = '', string $expiryFilter = 'all', string $viewerRoleCode = 'employee'): array
    {
        $sql = "SELECT ed.id, ed.employee_id, ed.title, ed.document_number, ed.original_file_name, ed.file_size,
                       ed.issue_date, ed.expiry_date, ed.visibility_scope, ed.status, ed.created_at,
                       dc.name AS category_name, dc.code AS category_code, dc.requires_expiry,
                       e.employee_code, CONCAT_WS(' ', e.first_name, e.middle_name, e.last_name) AS employee_name,
                       CASE WHEN ed.expiry_date IS NULL THEN NULL ELSE DATEDIFF(ed.expiry_date, CURDATE()) END AS days_until_expiry
                FROM employee_documents ed
                INNER JOIN employees e ON e.id = ed.employee_id
                INNER JOIN document_categories dc ON dc.id = ed.category_id
                WHERE ed.is_current = 1";

        // hr_only documents are exclusively visible to the hr_only role
        if ($viewerRoleCode !== 'hr_only') {
            $sql .= " AND ed.visibility_scope != 'hr_only'";
        }
        $params = [];

        if ($search !== '') {
            $sql .= " AND (
                ed.title LIKE :search_title OR ed.document_number LIKE :search_number OR ed.original_file_name LIKE :search_file
                OR dc.name LIKE :search_category OR e.employee_code LIKE :search_employee_code
                OR CONCAT_WS(' ', e.first_name, e.middle_name, e.last_name) LIKE :search_employee_name
            )";
            $searchValue = '%' . $search . '%';
            $params['search_title'] = $searchValue;
            $params['search_number'] = $searchValue;
            $params['search_file'] = $searchValue;
            $params['search_category'] = $searchValue;
            $params['search_employee_code'] = $searchValue;
            $params['search_employee_name'] = $searchValue;
        }

        if ($expiryFilter === 'expiring') {
            $sql .= " AND ed.expiry_date IS NOT NULL AND ed.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
        } elseif ($expiryFilter === 'expired') {
            $sql .= " AND ed.expiry_date IS NOT NULL AND ed.expiry_date < CURDATE()";
        } elseif ($expiryFilter === 'missing_expiry') {
            $sql .= " AND dc.requires_expiry = 1 AND ed.expiry_date IS NULL";
        }

        $sql .= ' ORDER BY CASE WHEN ed.expiry_date IS NULL THEN 1 ELSE 0 END ASC, ed.expiry_date ASC, ed.created_at DESC';

        return $this->database->fetchAll($sql, $params);
    }

    public function employeeDocuments(int $employeeId, string $viewerRoleCode = 'employee'): array
    {
        // Build the visibility filter based on role.
        // hr_only scope is exclusive — only the hr_only role can see those documents, not even super_admin.
        if ($viewerRoleCode === 'hr_only') {
            $scopeCondition = "1 = 1"; // hr_only sees everything including hr_only-scoped documents
        } elseif ($viewerRoleCode === 'super_admin') {
            $scopeCondition = "ed.visibility_scope != 'hr_only'"; // super_admin sees all except hr_only-scoped
        } elseif ($viewerRoleCode === 'manager') {
            $scopeCondition = "ed.visibility_scope IN ('employee', 'manager', 'hr')";
        } else {
            $scopeCondition = "ed.visibility_scope = 'employee'";
        }

        return $this->database->fetchAll(
            "SELECT ed.id, ed.title, ed.document_number, ed.original_file_name, ed.file_size, ed.issue_date, ed.expiry_date,
                    ed.visibility_scope, ed.status, ed.created_at,
                    dc.name AS category_name, dc.code AS category_code,
                    CASE WHEN ed.expiry_date IS NULL THEN NULL ELSE DATEDIFF(ed.expiry_date, CURDATE()) END AS days_until_expiry
             FROM employee_documents ed
             INNER JOIN document_categories dc ON dc.id = ed.category_id
             WHERE ed.employee_id = :employee_id AND ed.is_current = 1 AND " . $scopeCondition . "
             ORDER BY CASE WHEN ed.expiry_date IS NULL THEN 1 ELSE 0 END ASC, ed.expiry_date ASC, ed.created_at DESC",
            ['employee_id' => $employeeId]
        );
    }

    public function findDocument(int $documentId): ?array
    {
        return $this->database->fetch(
            "SELECT ed.id, ed.employee_id, ed.category_id, ed.document_type_id, ed.title, ed.document_number,
                    ed.issue_date, ed.expiry_date,
                    ed.original_file_name, ed.stored_file_name, ed.file_path,
                    ed.file_extension, ed.mime_type, ed.file_size, ed.visibility_scope, ed.status,
                    CONCAT_WS(' ', e.first_name, e.middle_name, e.last_name) AS employee_name,
                    e.employee_code,
                    dt.name AS document_type_name, dt.requires_expiry AS type_requires_expiry
             FROM employee_documents ed
             INNER JOIN employees e ON e.id = ed.employee_id
             LEFT JOIN document_types dt ON dt.id = ed.document_type_id
             WHERE ed.id = :id AND ed.is_current = 1
             LIMIT 1",
            ['id' => $documentId]
        );
    }

    public function updateDocumentDetails(int $documentId, array $data, ?int $actorId): void
    {
        $typeId = ($data['document_type_id'] ?? '') !== '' ? (int) $data['document_type_id'] : null;

        $this->database->execute(
            "UPDATE employee_documents SET
                category_id       = :category_id,
                document_type_id  = :document_type_id,
                title             = :title,
                document_number   = :document_number,
                issue_date        = :issue_date,
                expiry_date       = :expiry_date,
                visibility_scope  = :visibility_scope,
                status            = :status
             WHERE id = :id AND is_current = 1",
            [
                'category_id'      => (int) $data['category_id'],
                'document_type_id' => $typeId,
                'title'            => (string) $data['title'],
                'document_number'  => ($data['document_number'] ?? '') !== '' ? (string) $data['document_number'] : null,
                'issue_date'       => ($data['issue_date'] ?? '') !== '' ? (string) $data['issue_date'] : null,
                'expiry_date'      => ($data['expiry_date'] ?? '') !== '' ? (string) $data['expiry_date'] : null,
                'visibility_scope' => (string) $data['visibility_scope'],
                'status'           => in_array((string) ($data['status'] ?? ''), ['active', 'expired', 'revoked'], true) ? (string) $data['status'] : 'active',
                'id'               => $documentId,
            ]
        );
    }

    public function expiringDocuments(int $days = 30): array
    {
        return $this->database->fetchAll(
            "SELECT ed.id, ed.employee_id, ed.title, ed.document_number, ed.original_file_name, ed.expiry_date,
                    dc.name AS category_name, e.employee_code,
                    CONCAT_WS(' ', e.first_name, e.middle_name, e.last_name) AS employee_name,
                    DATEDIFF(ed.expiry_date, CURDATE()) AS days_until_expiry
             FROM employee_documents ed
             INNER JOIN document_categories dc ON dc.id = ed.category_id
             INNER JOIN employees e ON e.id = ed.employee_id
             WHERE ed.is_current = 1
               AND ed.expiry_date IS NOT NULL
               AND DATEDIFF(ed.expiry_date, CURDATE()) <= :days
             ORDER BY ed.expiry_date ASC, employee_name ASC",
            ['days' => $days]
        );
    }

    public function createDocument(int $employeeId, array $data, array $fileMeta, ?int $actorId): int
    {
        return $this->database->transaction(function (Database $database) use ($employeeId, $data, $fileMeta, $actorId): int {
            $typeId = ($data['document_type_id'] ?? '') !== '' ? (int) $data['document_type_id'] : null;

            $database->execute(
                'INSERT INTO employee_documents (
                    employee_id, category_id, document_type_id, title, document_number,
                    original_file_name, stored_file_name, file_path,
                    file_extension, mime_type, file_size, issue_date, expiry_date, version_no, is_current,
                    visibility_scope, status, uploaded_by
                 ) VALUES (
                    :employee_id, :category_id, :document_type_id, :title, :document_number,
                    :original_file_name, :stored_file_name, :file_path,
                    :file_extension, :mime_type, :file_size, :issue_date, :expiry_date, 1, 1,
                    :visibility_scope, :status, :uploaded_by
                 )',
                [
                    'employee_id'      => $employeeId,
                    'category_id'      => (int) $data['category_id'],
                    'document_type_id' => $typeId,
                    'title'            => $data['title'],
                    'document_number'  => $this->nullable($data['document_number'] ?? null),
                    'original_file_name' => $fileMeta['original_file_name'],
                    'stored_file_name'   => $fileMeta['stored_file_name'],
                    'file_path'          => $fileMeta['file_path'],
                    'file_extension'     => $this->nullable($fileMeta['file_extension'] ?? null),
                    'mime_type'          => $this->nullable($fileMeta['mime_type'] ?? null),
                    'file_size'          => (int) ($fileMeta['file_size'] ?? 0),
                    'issue_date'         => $this->nullable($data['issue_date'] ?? null),
                    'expiry_date'        => $this->nullable($data['expiry_date'] ?? null),
                    'visibility_scope'   => $data['visibility_scope'],
                    'status'             => 'active',
                    'uploaded_by'        => $actorId,
                ]
            );

            $documentId = (int) $database->lastInsertId();

            $database->execute(
                'INSERT INTO employee_document_versions (
                    employee_document_id, version_no, original_file_name, stored_file_name, file_path,
                    mime_type, file_size, uploaded_by
                 ) VALUES (
                    :employee_document_id, 1, :original_file_name, :stored_file_name, :file_path,
                    :mime_type, :file_size, :uploaded_by
                 )',
                [
                    'employee_document_id' => $documentId,
                    'original_file_name' => $fileMeta['original_file_name'],
                    'stored_file_name' => $fileMeta['stored_file_name'],
                    'file_path' => $fileMeta['file_path'],
                    'mime_type' => $this->nullable($fileMeta['mime_type'] ?? null),
                    'file_size' => (int) ($fileMeta['file_size'] ?? 0),
                    'uploaded_by' => $actorId,
                ]
            );

            return $documentId;
        });
    }

    /**
     * Documents expiring within $days days where a 30_days alert has NOT been sent yet.
     */
    public function documentsNeedingAlerts(int $days = 30): array
    {
        return $this->database->fetchAll(
            "SELECT ed.id, ed.employee_id, ed.title, ed.document_number, ed.expiry_date,
                    dc.name AS category_name, e.employee_code, u.email AS employee_email,
                    CONCAT_WS(' ', e.first_name, e.middle_name, e.last_name) AS employee_name,
                    DATEDIFF(ed.expiry_date, CURDATE()) AS days_until_expiry
             FROM employee_documents ed
             INNER JOIN document_categories dc ON dc.id = ed.category_id
             INNER JOIN employees e ON e.id = ed.employee_id
             LEFT JOIN users u ON u.id = e.user_id
             WHERE ed.is_current = 1
               AND ed.expiry_date IS NOT NULL
               AND DATEDIFF(ed.expiry_date, CURDATE()) BETWEEN 0 AND :days
               AND NOT EXISTS (
                   SELECT 1 FROM document_alerts da
                   WHERE da.employee_document_id = ed.id
                     AND da.alert_type = '30_days'
                     AND da.status = 'sent'
               )
             ORDER BY ed.expiry_date ASC",
            ['days' => $days]
        );
    }

    /**
     * Get active user IDs and emails for HR admin and Super admin roles.
     */
    public function hrAndAdminRecipients(): array
    {
        return $this->database->fetchAll(
            "SELECT u.id AS user_id, u.email
             FROM users u
             WHERE u.status = 'active'
               AND u.role_id IN (
                   SELECT id FROM roles WHERE code IN ('super_admin', 'hr_only')
               )
               AND u.email IS NOT NULL AND u.email <> ''"
        );
    }

    public function recordAlertSent(int $documentId, string $alertType, int $userId): void
    {
        $this->database->execute(
            "INSERT INTO document_alerts (employee_document_id, alert_type, alert_date, sent_to_user_id, status)
             VALUES (:doc_id, :alert_type, CURDATE(), :user_id, 'sent')",
            [
                'doc_id' => $documentId,
                'alert_type' => $alertType,
                'user_id' => $userId,
            ]
        );
    }

    /**
     * Record a document view or download event in document_access_logs.
     */
    public function logAccess(int $documentId, int $employeeId, ?int $userId, string $accessType, string $ipAddress, string $userAgent): void
    {
        $this->database->execute(
            'INSERT INTO document_access_logs (document_id, employee_id, accessed_by_user_id, access_type, ip_address, user_agent)
             VALUES (:document_id, :employee_id, :user_id, :access_type, :ip_address, :user_agent)',
            [
                'document_id' => $documentId,
                'employee_id' => $employeeId,
                'user_id'     => $userId,
                'access_type' => $accessType,
                'ip_address'  => $ipAddress !== '' ? $ipAddress : null,
                'user_agent'  => $userAgent !== '' ? substr($userAgent, 0, 255) : null,
            ]
        );
    }

    /**
     * Return document access log entries, optionally filtered by document or employee.
     */
    public function accessLogs(int $documentId = 0, int $employeeId = 0, int $limit = 100): array
    {
        $sql = 'SELECT dal.id, dal.access_type, dal.ip_address, dal.created_at,
                       ed.title AS document_title,
                       CONCAT_WS(\' \', u.first_name, u.last_name) AS accessed_by_name,
                       u.email AS accessed_by_email
                FROM document_access_logs dal
                LEFT JOIN employee_documents ed ON ed.id = dal.document_id
                LEFT JOIN users u ON u.id = dal.accessed_by_user_id
                WHERE 1 = 1';
        $params = [];

        if ($documentId > 0) {
            $sql .= ' AND dal.document_id = :document_id';
            $params['document_id'] = $documentId;
        }

        if ($employeeId > 0) {
            $sql .= ' AND dal.employee_id = :employee_id';
            $params['employee_id'] = $employeeId;
        }

        $sql .= ' ORDER BY dal.created_at DESC LIMIT ' . max(1, min($limit, 500));

        return $this->database->fetchAll($sql, $params);
    }

    // ── File access tokens ──────────────────────────────────────────────────

    /**
     * Generate a signed, single-use, time-limited token for a document download.
     * Default TTL: 15 minutes.
     */
    public function createAccessToken(int $documentId, ?int $userId, string $ipAddress, int $ttlMinutes = 15): string
    {
        $token     = bin2hex(random_bytes(32)); // 64 hex chars
        $expiresAt = date('Y-m-d H:i:s', time() + ($ttlMinutes * 60));

        $this->database->execute(
            'INSERT INTO file_access_tokens (token, document_id, created_by_user_id, expires_at, ip_address)
             VALUES (:token, :document_id, :user_id, :expires_at, :ip_address)',
            [
                'token'       => $token,
                'document_id' => $documentId,
                'user_id'     => $userId,
                'expires_at'  => $expiresAt,
                'ip_address'  => $ipAddress !== '' ? $ipAddress : null,
            ]
        );

        return $token;
    }

    /**
     * Validate and consume a file access token.
     * Returns the document_id on success, null if invalid/expired/already used.
     */
    public function consumeAccessToken(string $token): ?int
    {
        $row = $this->database->fetch(
            'SELECT id, document_id, expires_at, used_at
             FROM file_access_tokens
             WHERE token = :token LIMIT 1',
            ['token' => $token]
        );

        if ($row === null) {
            return null;
        }

        // Already used
        if ($row['used_at'] !== null) {
            return null;
        }

        // Expired
        if (strtotime((string) $row['expires_at']) < time()) {
            return null;
        }

        // Mark as used immediately (single-use)
        $this->database->execute(
            'UPDATE file_access_tokens SET used_at = NOW() WHERE id = :id',
            ['id' => (int) $row['id']]
        );

        return (int) $row['document_id'];
    }

    /**
     * Purge tokens older than 24 hours (housekeeping, call from cron).
     */
    public function purgeExpiredTokens(): int
    {
        $this->database->execute(
            'DELETE FROM file_access_tokens WHERE expires_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)'
        );

        return 1; // affected rows not returned by execute() in this driver
    }

    private function nullable(mixed $value): mixed
    {
        return $value === '' || $value === null ? null : $value;
    }
}