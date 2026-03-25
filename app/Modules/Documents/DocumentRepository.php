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

    public function listDocuments(string $search = '', string $expiryFilter = 'all'): array
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

    public function employeeDocuments(int $employeeId): array
    {
        return $this->database->fetchAll(
            "SELECT ed.id, ed.title, ed.document_number, ed.original_file_name, ed.file_size, ed.issue_date, ed.expiry_date,
                    ed.visibility_scope, ed.status, ed.created_at,
                    dc.name AS category_name, dc.code AS category_code,
                    CASE WHEN ed.expiry_date IS NULL THEN NULL ELSE DATEDIFF(ed.expiry_date, CURDATE()) END AS days_until_expiry
             FROM employee_documents ed
             INNER JOIN document_categories dc ON dc.id = ed.category_id
             WHERE ed.employee_id = :employee_id AND ed.is_current = 1
             ORDER BY CASE WHEN ed.expiry_date IS NULL THEN 1 ELSE 0 END ASC, ed.expiry_date ASC, ed.created_at DESC",
            ['employee_id' => $employeeId]
        );
    }

    public function findDocument(int $documentId): ?array
    {
        return $this->database->fetch(
            "SELECT ed.id, ed.employee_id, ed.title, ed.original_file_name, ed.stored_file_name, ed.file_path,
                    ed.file_extension, ed.mime_type, ed.file_size, ed.visibility_scope, ed.status
             FROM employee_documents ed
             WHERE ed.id = :id AND ed.is_current = 1
             LIMIT 1",
            ['id' => $documentId]
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
            $database->execute(
                'INSERT INTO employee_documents (
                    employee_id, category_id, title, document_number, original_file_name, stored_file_name, file_path,
                    file_extension, mime_type, file_size, issue_date, expiry_date, version_no, is_current,
                    visibility_scope, status, uploaded_by
                 ) VALUES (
                    :employee_id, :category_id, :title, :document_number, :original_file_name, :stored_file_name, :file_path,
                    :file_extension, :mime_type, :file_size, :issue_date, :expiry_date, 1, 1,
                    :visibility_scope, :status, :uploaded_by
                 )',
                [
                    'employee_id' => $employeeId,
                    'category_id' => (int) $data['category_id'],
                    'title' => $data['title'],
                    'document_number' => $this->nullable($data['document_number'] ?? null),
                    'original_file_name' => $fileMeta['original_file_name'],
                    'stored_file_name' => $fileMeta['stored_file_name'],
                    'file_path' => $fileMeta['file_path'],
                    'file_extension' => $this->nullable($fileMeta['file_extension'] ?? null),
                    'mime_type' => $this->nullable($fileMeta['mime_type'] ?? null),
                    'file_size' => (int) ($fileMeta['file_size'] ?? 0),
                    'issue_date' => $this->nullable($data['issue_date'] ?? null),
                    'expiry_date' => $this->nullable($data['expiry_date'] ?? null),
                    'visibility_scope' => $data['visibility_scope'],
                    'status' => 'active',
                    'uploaded_by' => $actorId,
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

    private function nullable(mixed $value): mixed
    {
        return $value === '' || $value === null ? null : $value;
    }
}