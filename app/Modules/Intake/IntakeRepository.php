<?php

declare(strict_types=1);

namespace App\Modules\Intake;

use App\Core\Database;
use App\Modules\Documents\DocumentRepository;
use App\Modules\Employees\EmployeeRepository;
use RuntimeException;

final class IntakeRepository
{
    private Database $database;
    private ?bool $submissionIdentificationsAvailable = null;

    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    // ─── Public form ──────────────────────────────────────────────────────────

    public function createSubmission(
        array  $personalData,
        array  $contacts,
        array  $docsMeta,
        string $submitterIp,
        string $submitterUa
    ): int {
        return $this->database->transaction(function (Database $db) use ($personalData, $contacts, $docsMeta, $submitterIp, $submitterUa): int {
            $token = $this->generateToken();

            $db->execute(
                'INSERT INTO employee_intake_submissions (
                    token, first_name, middle_name, last_name, gender, marital_status,
                    nationality, second_nationality, company_id,
                    personal_email, phone, alternate_phone, date_of_birth, id_number, passport_number,
                    address_line_1, address_line_2, city, state, country, postal_code,
                    submitter_ip, submitter_ua, submitted_at
                 ) VALUES (
                    :token, :first_name, :middle_name, :last_name, :gender, :marital_status,
                    :nationality, :second_nationality, :company_id,
                    :personal_email, :phone, :alternate_phone, :date_of_birth, :id_number, :passport_number,
                    :address_line_1, :address_line_2, :city, :state, :country, :postal_code,
                    :submitter_ip, :submitter_ua, NOW()
                 )',
                [
                    'token'              => $token,
                    'first_name'         => (string) $personalData['first_name'],
                    'middle_name'        => $this->ns($personalData['middle_name'] ?? null),
                    'last_name'          => (string) $personalData['last_name'],
                    'gender'             => $this->ns($personalData['gender'] ?? null),
                    'marital_status'     => $this->ns($personalData['marital_status'] ?? null),
                    'nationality'        => $this->ns($personalData['nationality'] ?? null),
                    'second_nationality' => $this->ns($personalData['second_nationality'] ?? null),
                    'company_id'         => $this->ni($personalData['company_id'] ?? null),
                    'personal_email'     => encrypt_field($this->ns(isset($personalData['personal_email']) ? strtolower($personalData['personal_email']) : null)),
                    'phone'              => encrypt_field($this->ns($personalData['phone'] ?? null)),
                    'alternate_phone'    => encrypt_field($this->ns($personalData['alternate_phone'] ?? null)),
                    'date_of_birth'      => encrypt_field($this->ns($personalData['date_of_birth'] ?? null)),
                    'id_number'          => encrypt_field($this->ns($personalData['id_number'] ?? null)),
                    'passport_number'    => encrypt_field($this->ns($personalData['passport_number'] ?? null)),
                    'address_line_1'     => $this->ns($personalData['address_line_1'] ?? null),
                    'address_line_2'     => $this->ns($personalData['address_line_2'] ?? null),
                    'city'               => $this->ns($personalData['city'] ?? null),
                    'state'              => $this->ns($personalData['state'] ?? null),
                    'country'            => $this->ns($personalData['country'] ?? null),
                    'postal_code'        => $this->ns($personalData['postal_code'] ?? null),
                    'submitter_ip'       => $submitterIp,
                    'submitter_ua'       => mb_substr($submitterUa, 0, 500),
                ]
            );

            $submissionId = (int) $db->lastInsertId();

            foreach ($contacts as $c) {
                $name  = trim((string) ($c['full_name'] ?? ''));
                $phone = trim((string) ($c['phone'] ?? ''));
                if ($name === '' && $phone === '') {
                    continue;
                }
                $db->execute(
                    'INSERT INTO employee_intake_contacts
                     (submission_id, full_name, relationship, phone, alternate_phone, email, is_primary)
                     VALUES (:sid, :name, :rel, :phone, :alt, :email, :primary)',
                    [
                        'sid'     => $submissionId,
                        'name'    => $name,
                        'rel'     => trim((string) ($c['relationship'] ?? '')),
                        'phone'   => $phone,
                        'alt'     => $this->ns($c['alternate_phone'] ?? null),
                        'email'   => $this->ns($c['email'] ?? null),
                        'primary' => isset($c['is_primary']) && (string) $c['is_primary'] === '1' ? 1 : 0,
                    ]
                );
            }

            foreach ($docsMeta as $doc) {
                $db->execute(
                    'INSERT INTO employee_intake_documents
                     (submission_id, document_type_id, category_id, title, document_number,
                      original_file_name, stored_file_name, file_path,
                      file_extension, mime_type, file_size, issue_date, expiry_date)
                     VALUES
                     (:sid, :type_id, :cat_id, :title, :doc_num,
                      :orig, :stored, :path,
                      :ext, :mime, :size, :issue, :expiry)',
                    [
                        'sid'     => $submissionId,
                        'type_id' => $this->ni($doc['document_type_id'] ?? null),
                        'cat_id'  => $this->ni($doc['category_id'] ?? null),
                        'title'   => (string) $doc['title'],
                        'doc_num' => $this->ns($doc['document_number'] ?? null),
                        'orig'    => (string) $doc['original_file_name'],
                        'stored'  => (string) $doc['stored_file_name'],
                        'path'    => (string) $doc['file_path'],
                        'ext'     => $this->ns($doc['file_extension'] ?? null),
                        'mime'    => $this->ns($doc['mime_type'] ?? null),
                        'size'    => (int) ($doc['file_size'] ?? 0),
                        'issue'   => $this->ns($doc['issue_date'] ?? null),
                        'expiry'  => $this->ns($doc['expiry_date'] ?? null),
                    ]
                );
            }

            return $submissionId;
        });
    }

    // ─── HR review queries ────────────────────────────────────────────────────

    public function findByToken(string $token): ?array
    {
        return $this->database->fetch(
            'SELECT s.*, CONCAT_WS(\' \', u.first_name, u.last_name) AS reviewer_name
             FROM employee_intake_submissions s
             LEFT JOIN users u ON u.id = s.reviewed_by
             WHERE s.token = :token
             LIMIT 1',
            ['token' => $token]
        ) ?: null;
    }

    public function listSubmissions(string $status = 'pending', string $search = '', int $page = 1, int $perPage = 25): array
    {
        $where  = [];
        $params = [];

        if ($status !== 'all') {
            $where[]          = 's.status = :status';
            $params['status'] = $status;
        }

        if ($search !== '') {
            $where[]          = '(s.first_name LIKE :q OR s.last_name LIKE :q)';
            $params['q']      = '%' . $search . '%';
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $offset      = ($page - 1) * $perPage;

        return $this->database->fetchAll(
            "SELECT s.id, s.token, s.first_name, s.last_name, s.nationality,
                    s.status, s.submitted_at, s.reviewed_at,
                    CONCAT_WS(' ', u.first_name, u.last_name) AS reviewer_name
             FROM employee_intake_submissions s
             LEFT JOIN users u ON u.id = s.reviewed_by
             {$whereClause}
             ORDER BY s.submitted_at DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
    }

    public function countSubmissions(string $status = 'pending', string $search = ''): int
    {
        $where  = [];
        $params = [];

        if ($status !== 'all') {
            $where[]          = 'status = :status';
            $params['status'] = $status;
        }

        if ($search !== '') {
            $where[]     = '(first_name LIKE :q OR last_name LIKE :q)';
            $params['q'] = '%' . $search . '%';
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        return (int) ($this->database->fetchValue(
            "SELECT COUNT(*) FROM employee_intake_submissions {$whereClause}",
            $params
        ) ?? 0);
    }

    public function statusCounts(): array
    {
        $rows = $this->database->fetchAll(
            "SELECT status, COUNT(*) AS cnt FROM employee_intake_submissions GROUP BY status"
        );

        $counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['cnt'];
        }

        return $counts;
    }

    public function contactsBySubmission(int $submissionId): array
    {
        return $this->database->fetchAll(
            'SELECT * FROM employee_intake_contacts WHERE submission_id = :sid ORDER BY is_primary DESC, id ASC',
            ['sid' => $submissionId]
        );
    }

    public function documentsBySubmission(int $submissionId): array
    {
        return $this->database->fetchAll(
            'SELECT d.*, dt.name AS type_name, dc.name AS category_name
             FROM employee_intake_documents d
             LEFT JOIN document_types dt ON dt.id = d.document_type_id
             LEFT JOIN document_categories dc ON dc.id = d.category_id
             WHERE d.submission_id = :sid
             ORDER BY d.id ASC',
            ['sid' => $submissionId]
        );
    }

    // ─── Approval ─────────────────────────────────────────────────────────────

    public function approveSubmission(
        string             $token,
        array              $employmentData,
        int                $actorId,
        EmployeeRepository $employeeRepo,
        DocumentRepository $documentRepo
    ): int {
        return $this->database->transaction(function (Database $db) use ($token, $employmentData, $actorId, $employeeRepo, $documentRepo): int {
            $submission = $db->fetch(
                'SELECT * FROM employee_intake_submissions WHERE token = :token AND status = :status LIMIT 1',
                ['token' => $token, 'status' => 'pending']
            );

            if ($submission === null) {
                throw new RuntimeException('Submission not found or already processed.');
            }

            $submissionId = (int) $submission['id'];

            $createData = [
                'employee_code'       => $employeeRepo->nextEmployeeCode(),
                'first_name'          => $submission['first_name'],
                'middle_name'         => $submission['middle_name'],
                'last_name'           => $submission['last_name'],
                'gender'              => $submission['gender'],
                'marital_status'      => $submission['marital_status'],
                'nationality'         => $submission['nationality'],
                'second_nationality'  => $submission['second_nationality'],
                'personal_email'      => decrypt_field($submission['personal_email']),
                'phone'               => decrypt_field($submission['phone']),
                'alternate_phone'     => decrypt_field($submission['alternate_phone']),
                'date_of_birth'       => decrypt_field($submission['date_of_birth']),
                'id_number'           => decrypt_field($submission['id_number']),
                'passport_number'     => decrypt_field($submission['passport_number']),
                'work_email'          => strtolower(trim((string) $employmentData['work_email'])),
                'company_id'          => (int) $employmentData['company_id'],
                'branch_id'           => $this->ni($employmentData['branch_id'] ?? null),
                'department_id'       => $this->ni($employmentData['department_id'] ?? null),
                'team_id'             => $this->ni($employmentData['team_id'] ?? null),
                'job_title_id'        => $this->ni($employmentData['job_title_id'] ?? null),
                'designation_id'      => $this->ni($employmentData['designation_id'] ?? null),
                'manager_employee_id' => $this->ni($employmentData['manager_employee_id'] ?? null),
                'employment_type'     => (string) $employmentData['employment_type'],
                'joining_date'        => $this->ns($employmentData['joining_date'] ?? null),
                'employee_status'     => (string) ($employmentData['employee_status'] ?? 'active'),
                'contract_type'       => null,
                'probation_start_date'=> null,
                'probation_end_date'  => null,
                'notes'               => null,
            ];

            $employeeId = $employeeRepo->createEmployee($createData, $actorId);

            // Move profile photo if one was uploaded with the form
            if (!empty($submission['profile_photo_path'])) {
                $photoOldAbs  = base_path($submission['profile_photo_path']);
                $photoDir     = base_path('storage/uploads/photos');
                if (!is_dir($photoDir)) {
                    mkdir($photoDir, 0775, true);
                }
                $photoExt    = pathinfo($submission['profile_photo_path'], PATHINFO_EXTENSION);
                $photoName   = 'employee_' . $employeeId . '_' . date('YmdHis') . '.' . $photoExt;
                $photoRelPath = 'storage/uploads/photos/' . $photoName;
                if (is_file($photoOldAbs)) {
                    rename($photoOldAbs, base_path($photoRelPath));
                    $employeeRepo->updatePhotoPath($employeeId, $photoRelPath);
                }
            }

            $contacts = $this->contactsBySubmission($submissionId);
            $employeeRepo->saveEmergencyContacts($employeeId, $contacts);

            // Migrate identifications
            $this->migrateIdentificationsToEmployee($submissionId, $employeeId);

            $stagedDocs  = $this->documentsBySubmission($submissionId);
            $employeeDir = base_path('storage/uploads/documents/employee_' . $employeeId);

            if (!is_dir($employeeDir)) {
                mkdir($employeeDir, 0775, true);
            }

            foreach ($stagedDocs as $doc) {
                $oldAbs      = base_path($doc['file_path']);
                $newName     = date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . ($doc['file_extension'] ?? 'bin');
                $newRelPath  = 'storage/uploads/documents/employee_' . $employeeId . '/' . $newName;
                $newAbs      = base_path($newRelPath);

                if (!rename($oldAbs, $newAbs)) {
                    throw new RuntimeException('Failed to move document: ' . $doc['original_file_name']);
                }

                $documentRepo->createDocument(
                    $employeeId,
                    [
                        'category_id'      => $doc['category_id'],
                        'document_type_id' => $doc['document_type_id'],
                        'title'            => $doc['title'],
                        'document_number'  => $doc['document_number'],
                        'issue_date'       => $doc['issue_date'],
                        'expiry_date'      => $doc['expiry_date'],
                        'visibility_scope' => 'hr',
                    ],
                    [
                        'original_file_name' => $doc['original_file_name'],
                        'stored_file_name'   => $newName,
                        'file_path'          => $newRelPath,
                        'file_extension'     => $doc['file_extension'],
                        'mime_type'          => $doc['mime_type'],
                        'file_size'          => $doc['file_size'],
                    ],
                    $actorId
                );
            }

            $db->execute(
                'UPDATE employee_intake_submissions
                 SET status = :status, reviewed_at = NOW(), reviewed_by = :reviewer, approved_employee_id = :emp_id
                 WHERE id = :id',
                [
                    'status'   => 'approved',
                    'reviewer' => $actorId,
                    'emp_id'   => $employeeId,
                    'id'       => $submissionId,
                ]
            );

            return $employeeId;
        });
    }

    public function updatePhotoPath(int $submissionId, string $path): void
    {
        $this->database->execute(
            'UPDATE employee_intake_submissions SET profile_photo_path = :path WHERE id = :id',
            ['path' => $path, 'id' => $submissionId]
        );
    }

    public function rejectSubmission(string $token, string $reason, int $actorId): void
    {
        $this->database->execute(
            'UPDATE employee_intake_submissions
             SET status = :status, reviewed_at = NOW(), reviewed_by = :reviewer, rejection_reason = :reason
             WHERE token = :token AND status = :pending',
            [
                'status'   => 'rejected',
                'reviewer' => $actorId,
                'reason'   => $reason ?: null,
                'token'    => $token,
                'pending'  => 'pending',
            ]
        );
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function ns(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;
        return ($value === null || $value === '') ? null : (string) $value;
    }

    private function ni(mixed $value): ?int
    {
        return ($value === null || $value === '') ? null : (int) $value;
    }

    // ─── Identification Type Support ──────────────────────────────────────────

    public function getIdentificationTypes(): array
    {
        return $this->database->fetchAll(
            'SELECT id, code, name, country, is_active
             FROM identification_types
             WHERE is_active = 1
             ORDER BY sort_order ASC, name ASC'
        );
    }

    public function saveIdentifications(int $submissionId, array $identifications): void
    {
        if (empty($identifications) || !$this->supportsSubmissionIdentifications()) {
            return;
        }

        $this->database->transaction(function (Database $db) use ($submissionId, $identifications): void {
            // Delete existing identifications for this submission
            $db->execute(
                'DELETE FROM intake_submission_identifications WHERE submission_id = :sid',
                ['sid' => $submissionId]
            );

            // Insert new identifications
            foreach ($identifications as $id) {
                if (empty($id['type_id']) || empty($id['number'])) {
                    continue;
                }

                $db->execute(
                    'INSERT INTO intake_submission_identifications
                     (submission_id, identification_type_id, id_number, is_primary, issue_date, expiry_date)
                     VALUES (:sid, :type_id, :id_number, :primary, :issue_date, :expiry_date)',
                    [
                        'sid'         => $submissionId,
                        'type_id'     => (int) $id['type_id'],
                        'id_number'   => encrypt_field(trim((string) $id['number'])),
                        'primary'     => isset($id['is_primary']) && (string) $id['is_primary'] === '1' ? 1 : 0,
                        'issue_date'  => $this->ns($id['issue_date'] ?? null),
                        'expiry_date' => $this->ns($id['expiry_date'] ?? null),
                    ]
                );
            }
        });
    }

    public function getIdentifications(int $submissionId): array
    {
        if (!$this->supportsSubmissionIdentifications()) {
            return [];
        }

        return $this->database->fetchAll(
            'SELECT
                isi.id,
                isi.identification_type_id,
                it.code as type_code,
                it.name as type_name,
                it.country,
                isi.id_number,
                isi.is_primary,
                isi.issue_date,
                isi.expiry_date
             FROM intake_submission_identifications isi
             JOIN identification_types it ON it.id = isi.identification_type_id
             WHERE isi.submission_id = :sid
             ORDER BY isi.is_primary DESC, isi.created_at ASC',
            ['sid' => $submissionId]
        );
    }

    public function migrateIdentificationsToEmployee(int $submissionId, int $employeeId): void
    {
        if (!$this->supportsSubmissionIdentifications()) {
            return;
        }

        $identifications = $this->getIdentifications($submissionId);

        if (empty($identifications)) {
            return;
        }

        $this->database->transaction(function (Database $db) use ($employeeId, $identifications): void {
            // Delete existing employee identifications
            $db->execute(
                'DELETE FROM employee_identifications WHERE employee_id = :eid',
                ['eid' => $employeeId]
            );

            // Insert migrated identifications
            foreach ($identifications as $id) {
                $db->execute(
                    'INSERT INTO employee_identifications
                     (employee_id, identification_type_id, id_number, is_primary, issue_date, expiry_date)
                     VALUES (:eid, :type_id, :id_number, :primary, :issue_date, :expiry_date)',
                    [
                        'eid'        => $employeeId,
                        'type_id'    => $id['identification_type_id'],
                        'id_number'  => $id['id_number'], // already encrypted
                        'primary'    => $id['is_primary'],
                        'issue_date' => $id['issue_date'],
                        'expiry_date' => $id['expiry_date'],
                    ]
                );
            }
        });
    }

    private function supportsSubmissionIdentifications(): bool
    {
        if ($this->submissionIdentificationsAvailable !== null) {
            return $this->submissionIdentificationsAvailable;
        }

        $tableName = $this->database->fetchValue('SELECT DATABASE()');
        if (!is_string($tableName) || $tableName === '') {
            return $this->submissionIdentificationsAvailable = false;
        }

        $exists = $this->database->fetchValue(
            'SELECT COUNT(*)
             FROM information_schema.tables
             WHERE table_schema = :schema AND table_name = :table_name',
            [
                'schema'     => $tableName,
                'table_name' => 'intake_submission_identifications',
            ]
        );

        return $this->submissionIdentificationsAvailable = ((int) $exists) > 0;
    }
}
