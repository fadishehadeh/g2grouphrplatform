<?php

declare(strict_types=1);

namespace App\Modules\Announcements;

use App\Core\Database;

final class AnnouncementRepository
{
    private Database $database;

    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    public function listAnnouncements(array $user, string $search = '', string $status = 'all', bool $canManage = false): array
    {
        $params = ['read_user_id' => (int) ($user['id'] ?? 0)];
        $conditions = [];
        $sql = $this->baseQuery();

        if ($canManage) {
            if ($status !== 'all') {
                $conditions[] = 'a.status = :status_filter';
                $params['status_filter'] = $status;
            }
        } else {
            $context = $this->viewerContext($user);
            $conditions[] = "a.status = 'published'";
            $conditions[] = '(a.starts_at IS NULL OR a.starts_at <= NOW())';
            $conditions[] = '(a.ends_at IS NULL OR a.ends_at >= NOW())';
            $conditions[] = $this->viewerTargetCondition();
            $params['viewer_role_id'] = $context['role_id'];
            $params['viewer_employee_id'] = $context['employee_id'];
            $params['viewer_department_id'] = $context['department_id'];
            $params['viewer_branch_id'] = $context['branch_id'];
        }

        if ($search !== '') {
            $sql .= ' AND (';
            $sql .= 'a.title LIKE :search_title OR a.content LIKE :search_content';
            $sql .= ')';
            $params['search_title'] = '%' . $search . '%';
            $params['search_content'] = '%' . $search . '%';
        }

        if ($conditions !== []) {
            $sql .= ' AND ' . implode(' AND ', $conditions);
        }

        $sql .= $this->groupingAndOrdering();

        return $this->database->fetchAll($sql, $params);
    }

    public function findAnnouncement(int $id, array $user, bool $canManage = false): ?array
    {
        $params = [
            'announcement_id' => $id,
            'read_user_id' => (int) ($user['id'] ?? 0),
        ];
        $conditions = ['a.id = :announcement_id'];
        $sql = $this->baseQuery();

        if (!$canManage) {
            $context = $this->viewerContext($user);
            $conditions[] = "a.status = 'published'";
            $conditions[] = '(a.starts_at IS NULL OR a.starts_at <= NOW())';
            $conditions[] = '(a.ends_at IS NULL OR a.ends_at >= NOW())';
            $conditions[] = $this->viewerTargetCondition();
            $params['viewer_role_id'] = $context['role_id'];
            $params['viewer_employee_id'] = $context['employee_id'];
            $params['viewer_department_id'] = $context['department_id'];
            $params['viewer_branch_id'] = $context['branch_id'];
        }

        $sql .= ' AND ' . implode(' AND ', $conditions);
        $sql .= $this->groupingAndOrdering();
        $sql .= ' LIMIT 1';

        return $this->database->fetch($sql, $params);
    }

    public function markRead(int $announcementId, int $userId): void
    {
        $this->database->execute(
            'INSERT INTO announcement_reads (announcement_id, user_id, read_at)
             VALUES (:announcement_id, :user_id, NOW())
             ON DUPLICATE KEY UPDATE read_at = VALUES(read_at)',
            [
                'announcement_id' => $announcementId,
                'user_id' => $userId,
            ]
        );
    }

    public function createAnnouncement(array $data, ?int $actorId): int
    {
        return $this->database->transaction(function (Database $database) use ($data, $actorId): int {
            $database->execute(
                'INSERT INTO announcements (
                    title, content, priority, starts_at, ends_at, status, created_by, updated_by
                 ) VALUES (
                    :title, :content, :priority, :starts_at, :ends_at, :status, :created_by, :updated_by
                 )',
                [
                    'title' => trim((string) ($data['title'] ?? '')),
                    'content' => trim((string) ($data['content'] ?? '')),
                    'priority' => (string) ($data['priority'] ?? 'normal'),
                    'starts_at' => $this->nullableString($data['starts_at'] ?? null),
                    'ends_at' => $this->nullableString($data['ends_at'] ?? null),
                    'status' => (string) ($data['status'] ?? 'draft'),
                    'created_by' => $actorId,
                    'updated_by' => $actorId,
                ]
            );

            $announcementId = (int) $database->lastInsertId();
            $database->execute(
                'INSERT INTO announcement_targets (announcement_id, target_type, target_id)
                 VALUES (:announcement_id, :target_type, :target_id)',
                [
                    'announcement_id' => $announcementId,
                    'target_type' => (string) ($data['target_type'] ?? 'all'),
                    'target_id' => $this->nullableInt($data['target_id'] ?? null),
                ]
            );

            return $announcementId;
        });
    }

    public function targetOptions(): array
    {
        return [
            'roles' => $this->database->fetchAll('SELECT id, name, code FROM roles ORDER BY name ASC'),
            'branches' => $this->database->fetchAll(
                "SELECT id, name, code FROM branches WHERE status = 'active' ORDER BY name ASC"
            ),
            'departments' => $this->database->fetchAll(
                "SELECT id, name, code FROM departments WHERE status = 'active' ORDER BY name ASC"
            ),
            'employees' => $this->database->fetchAll(
                "SELECT id, employee_code, CONCAT_WS(' ', first_name, middle_name, last_name) AS full_name
                 FROM employees
                 WHERE archived_at IS NULL
                 ORDER BY full_name ASC"
            ),
        ];
    }

    private function baseQuery(): string
    {
        return "SELECT a.id, a.title, a.content, a.priority, a.starts_at, a.ends_at, a.status, a.created_at, a.updated_at,
                       COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u.first_name, u.last_name)), ''), 'System') AS created_by_name,
                       CASE WHEN ar.id IS NULL THEN 0 ELSE 1 END AS is_read,
                       COALESCE(
                           NULLIF(
                               GROUP_CONCAT(DISTINCT CASE
                                   WHEN at.target_type = 'all' THEN 'All Employees'
                                   WHEN at.target_type = 'role' THEN CONCAT('Role: ', r.name)
                                   WHEN at.target_type = 'department' THEN CONCAT('Department: ', d.name)
                                   WHEN at.target_type = 'branch' THEN CONCAT('Branch: ', b.name)
                                   WHEN at.target_type = 'employee' THEN CONCAT('Employee: ', CONCAT_WS(' ', te.first_name, te.middle_name, te.last_name))
                                   ELSE NULL
                               END ORDER BY at.id SEPARATOR ', '),
                               ''
                           ),
                           'All Employees'
                       ) AS target_summary
                FROM announcements a
                LEFT JOIN users u ON u.id = a.created_by
                LEFT JOIN announcement_targets at ON at.announcement_id = a.id
                LEFT JOIN roles r ON r.id = at.target_id AND at.target_type = 'role'
                LEFT JOIN departments d ON d.id = at.target_id AND at.target_type = 'department'
                LEFT JOIN branches b ON b.id = at.target_id AND at.target_type = 'branch'
                LEFT JOIN employees te ON te.id = at.target_id AND at.target_type = 'employee'
                LEFT JOIN announcement_reads ar ON ar.announcement_id = a.id AND ar.user_id = :read_user_id
                WHERE 1 = 1";
    }

    private function groupingAndOrdering(): string
    {
        return " GROUP BY a.id, a.title, a.content, a.priority, a.starts_at, a.ends_at, a.status, a.created_at, a.updated_at,
                          u.first_name, u.last_name, ar.id
                 ORDER BY CASE a.status WHEN 'published' THEN 0 WHEN 'draft' THEN 1 ELSE 2 END ASC,
                          COALESCE(a.starts_at, a.created_at) DESC,
                          a.created_at DESC";
    }

    private function viewerTargetCondition(): string
    {
        return "EXISTS (
                    SELECT 1
                    FROM announcement_targets atv
                    WHERE atv.announcement_id = a.id
                      AND (
                          atv.target_type = 'all'
                          OR (atv.target_type = 'role' AND atv.target_id = :viewer_role_id)
                          OR (atv.target_type = 'employee' AND atv.target_id = :viewer_employee_id)
                          OR (atv.target_type = 'department' AND atv.target_id = :viewer_department_id)
                          OR (atv.target_type = 'branch' AND atv.target_id = :viewer_branch_id)
                      )
                )";
    }

    private function viewerContext(array $user): array
    {
        $context = [
            'role_id' => (int) ($user['role_id'] ?? 0),
            'employee_id' => isset($user['employee_id']) ? (int) $user['employee_id'] : null,
            'department_id' => null,
            'branch_id' => null,
        ];

        if (($context['employee_id'] ?? 0) > 0) {
            $employee = $this->database->fetch(
                'SELECT department_id, branch_id FROM employees WHERE id = :employee_id LIMIT 1',
                ['employee_id' => $context['employee_id']]
            );

            if ($employee !== null) {
                $context['department_id'] = isset($employee['department_id']) ? (int) $employee['department_id'] : null;
                $context['branch_id'] = isset($employee['branch_id']) ? (int) $employee['branch_id'] : null;
            }
        }

        return $context;
    }

    /**
     * Get all active user emails that should receive an announcement notification.
     * Returns array of ['user_id' => int, 'email' => string].
     */
    public function targetedRecipients(int $announcementId): array
    {
        $targets = $this->database->fetchAll(
            'SELECT target_type, target_id FROM announcement_targets WHERE announcement_id = :id',
            ['id' => $announcementId]
        );

        if ($targets === []) {
            return [];
        }

        // If any target is 'all', return all active users with email
        $isAll = false;
        $roleIds = [];
        $departmentIds = [];
        $branchIds = [];
        $employeeIds = [];

        foreach ($targets as $t) {
            $type = (string) $t['target_type'];
            $targetId = $t['target_id'] !== null ? (int) $t['target_id'] : null;

            if ($type === 'all') {
                $isAll = true;
                break;
            }

            if ($type === 'role' && $targetId !== null) {
                $roleIds[] = $targetId;
            } elseif ($type === 'department' && $targetId !== null) {
                $departmentIds[] = $targetId;
            } elseif ($type === 'branch' && $targetId !== null) {
                $branchIds[] = $targetId;
            } elseif ($type === 'employee' && $targetId !== null) {
                $employeeIds[] = $targetId;
            }
        }

        if ($isAll) {
            return $this->database->fetchAll(
                "SELECT id AS user_id, email FROM users WHERE status = 'active' AND email IS NOT NULL AND email <> ''"
            );
        }

        // Build a UNION query for targeted users
        $unions = [];
        $params = [];

        if ($roleIds !== []) {
            $placeholders = [];
            foreach ($roleIds as $i => $rid) {
                $key = 'role_id_' . $i;
                $placeholders[] = ':' . $key;
                $params[$key] = $rid;
            }
            $unions[] = "SELECT id AS user_id, email FROM users WHERE status = 'active' AND email IS NOT NULL AND email <> '' AND role_id IN (" . implode(',', $placeholders) . ")";
        }

        if ($departmentIds !== []) {
            $placeholders = [];
            foreach ($departmentIds as $i => $did) {
                $key = 'dept_id_' . $i;
                $placeholders[] = ':' . $key;
                $params[$key] = $did;
            }
            $unions[] = "SELECT u.id AS user_id, u.email FROM users u INNER JOIN employees e ON e.user_id = u.id WHERE u.status = 'active' AND u.email IS NOT NULL AND u.email <> '' AND e.department_id IN (" . implode(',', $placeholders) . ")";
        }

        if ($branchIds !== []) {
            $placeholders = [];
            foreach ($branchIds as $i => $bid) {
                $key = 'branch_id_' . $i;
                $placeholders[] = ':' . $key;
                $params[$key] = $bid;
            }
            $unions[] = "SELECT u.id AS user_id, u.email FROM users u INNER JOIN employees e ON e.user_id = u.id WHERE u.status = 'active' AND u.email IS NOT NULL AND u.email <> '' AND e.branch_id IN (" . implode(',', $placeholders) . ")";
        }

        if ($employeeIds !== []) {
            $placeholders = [];
            foreach ($employeeIds as $i => $eid) {
                $key = 'emp_id_' . $i;
                $placeholders[] = ':' . $key;
                $params[$key] = $eid;
            }
            $unions[] = "SELECT u.id AS user_id, u.email FROM users u INNER JOIN employees e ON e.user_id = u.id WHERE u.status = 'active' AND u.email IS NOT NULL AND u.email <> '' AND e.id IN (" . implode(',', $placeholders) . ")";
        }

        if ($unions === []) {
            return [];
        }

        $sql = implode(' UNION ', $unions);

        return $this->database->fetchAll($sql, $params);
    }

    public function addLink(int $announcementId, string $label, string $url, int $sortOrder = 0): void
    {
        $this->database->execute(
            'INSERT INTO announcement_links (announcement_id, label, url, sort_order) VALUES (:announcement_id, :label, :url, :sort_order)',
            ['announcement_id' => $announcementId, 'label' => $label, 'url' => $url, 'sort_order' => $sortOrder]
        );
    }

    public function addAttachment(int $announcementId, string $originalName, string $storedName, string $mimeType, int $fileSize): void
    {
        $this->database->execute(
            'INSERT INTO announcement_attachments (announcement_id, original_name, stored_name, mime_type, file_size) VALUES (:announcement_id, :original_name, :stored_name, :mime_type, :file_size)',
            ['announcement_id' => $announcementId, 'original_name' => $originalName, 'stored_name' => $storedName, 'mime_type' => $mimeType, 'file_size' => $fileSize]
        );
    }

    public function announcementLinks(int $announcementId): array
    {
        return $this->database->fetchAll(
            'SELECT id, label, url, sort_order FROM announcement_links WHERE announcement_id = :id ORDER BY sort_order ASC, id ASC',
            ['id' => $announcementId]
        );
    }

    public function announcementAttachments(int $announcementId): array
    {
        return $this->database->fetchAll(
            'SELECT id, original_name, stored_name, mime_type, file_size FROM announcement_attachments WHERE announcement_id = :id ORDER BY id ASC',
            ['id' => $announcementId]
        );
    }

    public function findAttachment(int $attachmentId): ?array
    {
        return $this->database->fetch(
            'SELECT id, announcement_id, original_name, stored_name, mime_type, file_size FROM announcement_attachments WHERE id = :id LIMIT 1',
            ['id' => $attachmentId]
        );
    }

    private function nullableString(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}