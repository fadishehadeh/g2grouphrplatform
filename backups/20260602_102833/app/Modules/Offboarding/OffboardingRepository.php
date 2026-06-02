<?php

declare(strict_types=1);

namespace App\Modules\Offboarding;

use App\Core\Database;
use RuntimeException;

final class OffboardingRepository
{
    private Database $database;

    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    public function listRecords(string $search = '', string $status = 'all'): array
    {
        $sql = "SELECT obr.id, obr.record_type, obr.exit_date, obr.last_working_date, obr.status, obr.clearance_status,
                       CONCAT_WS(' ', e.first_name, e.middle_name, e.last_name) AS employee_name,
                       e.employee_code,
                       COUNT(DISTINCT obt.id) AS total_tasks,
                       COUNT(DISTINCT ari.id) AS total_assets
                FROM offboarding_records obr
                INNER JOIN employees e ON e.id = obr.employee_id
                LEFT JOIN offboarding_tasks obt ON obt.offboarding_record_id = obr.id
                LEFT JOIN asset_return_items ari ON ari.offboarding_record_id = obr.id
                WHERE e.archived_at IS NULL";
        $params = [];

        if ($search !== '') {
            $sql .= ' AND (
                e.employee_code LIKE :offboarding_employee_code_search
                OR e.first_name LIKE :offboarding_first_name_search
                OR e.last_name LIKE :offboarding_last_name_search
                OR obr.reason LIKE :offboarding_reason_search
            )';
            $searchValue = '%' . $search . '%';
            $params['offboarding_employee_code_search'] = $searchValue;
            $params['offboarding_first_name_search'] = $searchValue;
            $params['offboarding_last_name_search'] = $searchValue;
            $params['offboarding_reason_search'] = $searchValue;
        }

        if ($status !== 'all') {
            $sql .= ' AND obr.status = :offboarding_status';
            $params['offboarding_status'] = $status;
        }

        $sql .= ' GROUP BY obr.id, obr.record_type, obr.exit_date, obr.last_working_date, obr.status, obr.clearance_status,
                           e.first_name, e.middle_name, e.last_name, e.employee_code
                  ORDER BY obr.exit_date DESC, obr.id DESC';

        return $this->database->fetchAll($sql, $params);
    }

    public function findRecord(int $recordId): ?array
    {
        return $this->database->fetch(
            "SELECT obr.*, CONCAT_WS(' ', e.first_name, e.middle_name, e.last_name) AS employee_name,
                    e.employee_code,
                    CONCAT_WS(' ', iu.first_name, iu.last_name) AS initiated_by_name,
                    CONCAT_WS(' ', au.first_name, au.last_name) AS approved_by_name
             FROM offboarding_records obr
             INNER JOIN employees e ON e.id = obr.employee_id
             LEFT JOIN users iu ON iu.id = obr.initiated_by
             LEFT JOIN users au ON au.id = obr.approved_by
             WHERE obr.id = :id
             LIMIT 1",
            ['id' => $recordId]
        );
    }

    public function managementOptions(): array
    {
        return [
            'departments' => $this->departmentOptions(),
            'users' => $this->userOptions(),
        ];
    }

    public function recordTasks(int $recordId): array
    {
        return $this->database->fetchAll(
            "SELECT obt.*, d.name AS department_name,
                    CONCAT_WS(' ', u.first_name, u.last_name) AS assigned_to_name
             FROM offboarding_tasks obt
             LEFT JOIN departments d ON d.id = obt.department_id
             LEFT JOIN users u ON u.id = obt.assigned_to_user_id
             WHERE obt.offboarding_record_id = :record_id
             ORDER BY obt.id ASC",
            ['record_id' => $recordId]
        );
    }

    public function assetItems(int $recordId): array
    {
        return $this->database->fetchAll(
            "SELECT ari.*, CONCAT_WS(' ', u.first_name, u.last_name) AS checked_by_name
             FROM asset_return_items ari
             LEFT JOIN users u ON u.id = ari.checked_by
             WHERE ari.offboarding_record_id = :record_id
             ORDER BY ari.id ASC",
            ['record_id' => $recordId]
        );
    }

    public function createRecord(int $employeeId, array $data, ?int $actorId): int
    {
        return $this->database->transaction(function (Database $database) use ($employeeId, $data, $actorId): int {
            $database->execute(
                'INSERT INTO offboarding_records (
                    employee_id, record_type, notice_date, exit_date, last_working_date, reason, remarks,
                    status, clearance_status, initiated_by
                 ) VALUES (
                    :employee_id, :record_type, :notice_date, :exit_date, :last_working_date, :reason, :remarks,
                    :status, :clearance_status, :initiated_by
                 )',
                [
                    'employee_id' => $employeeId,
                    'record_type' => (string) $data['record_type'],
                    'notice_date' => $this->nullableString($data['notice_date'] ?? null),
                    'exit_date' => (string) $data['exit_date'],
                    'last_working_date' => $this->nullableString($data['last_working_date'] ?? null),
                    'reason' => $this->nullableString($data['reason'] ?? null),
                    'remarks' => $this->nullableString($data['remarks'] ?? null),
                    'status' => (string) ($data['status'] ?? 'pending'),
                    'clearance_status' => 'pending',
                    'initiated_by' => $actorId,
                ]
            );

            $recordId = (int) $database->lastInsertId();
            $taskLines = $this->taskLines((string) ($data['task_lines'] ?? ''));
            $assetLines = $this->taskLines((string) ($data['asset_lines'] ?? ''));

            if ($taskLines === []) {
                $taskLines = ['Collect ID card', 'Revoke application access', 'Confirm department handover', 'Close finance clearance'];
            }

            foreach ($taskLines as $taskName) {
                $database->execute(
                    'INSERT INTO offboarding_tasks (
                        offboarding_record_id, task_name, department_id, assigned_to_user_id, status, due_date, remarks
                     ) VALUES (
                        :offboarding_record_id, :task_name, :department_id, :assigned_to_user_id, :status, :due_date, :remarks
                     )',
                    [
                        'offboarding_record_id' => $recordId,
                        'task_name' => $taskName,
                        'department_id' => null,
                        'assigned_to_user_id' => null,
                        'status' => 'pending',
                        'due_date' => (string) $data['exit_date'],
                        'remarks' => null,
                    ]
                );
            }

            foreach ($assetLines as $assetName) {
                $database->execute(
                    'INSERT INTO asset_return_items (
                        offboarding_record_id, asset_name, asset_code, quantity, return_status, remarks, checked_by, checked_at
                     ) VALUES (
                        :offboarding_record_id, :asset_name, :asset_code, :quantity, :return_status, :remarks, :checked_by, :checked_at
                     )',
                    [
                        'offboarding_record_id' => $recordId,
                        'asset_name' => $assetName,
                        'asset_code' => null,
                        'quantity' => 1,
                        'return_status' => 'pending',
                        'remarks' => null,
                        'checked_by' => null,
                        'checked_at' => null,
                    ]
                );
            }

            $this->refreshStatus($recordId);

            return $recordId;
        });
    }

    public function createTask(int $recordId, array $data): int
    {
        return $this->database->transaction(function (Database $database) use ($recordId, $data): int {
            if (!$this->recordExists($database, $recordId)) {
                throw new RuntimeException('Offboarding record not found.');
            }

            $departmentId = $data['department_id'] ?? null;
            $assignedUserId = $data['assigned_to_user_id'] ?? null;

            if ($departmentId !== null && !$this->departmentExists($database, (int) $departmentId)) {
                throw new RuntimeException('Selected department is invalid.');
            }

            if ($assignedUserId !== null && !$this->userExists($database, (int) $assignedUserId)) {
                throw new RuntimeException('Selected task owner is invalid.');
            }

            $database->execute(
                'INSERT INTO offboarding_tasks (
                    offboarding_record_id, task_name, department_id, assigned_to_user_id, status, due_date, remarks
                 ) VALUES (
                    :offboarding_record_id, :task_name, :department_id, :assigned_to_user_id, :status, :due_date, :remarks
                 )',
                [
                    'offboarding_record_id' => $recordId,
                    'task_name' => (string) $data['task_name'],
                    'department_id' => $departmentId,
                    'assigned_to_user_id' => $assignedUserId,
                    'status' => (string) ($data['status'] ?? 'pending'),
                    'due_date' => $this->nullableString($data['due_date'] ?? null),
                    'remarks' => $this->nullableString($data['remarks'] ?? null),
                ]
            );

            $this->refreshStatus($recordId);

            return (int) $database->lastInsertId();
        });
    }

    public function updateTask(int $taskId, array $data): ?int
    {
        return $this->database->transaction(function (Database $database) use ($taskId, $data): ?int {
            $task = $database->fetch(
                'SELECT id, offboarding_record_id FROM offboarding_tasks WHERE id = :id LIMIT 1',
                ['id' => $taskId]
            );

            if ($task === null) {
                return null;
            }

            $departmentId = $data['department_id'] ?? null;
            $assignedUserId = $data['assigned_to_user_id'] ?? null;

            if ($departmentId !== null && !$this->departmentExists($database, (int) $departmentId)) {
                throw new RuntimeException('Selected department is invalid.');
            }

            if ($assignedUserId !== null && !$this->userExists($database, (int) $assignedUserId)) {
                throw new RuntimeException('Selected task owner is invalid.');
            }

            $completedAt = (string) ($data['status'] ?? 'pending') === 'completed' ? date('Y-m-d H:i:s') : null;

            $database->execute(
                'UPDATE offboarding_tasks
                 SET task_name = :task_name,
                     department_id = :department_id,
                     assigned_to_user_id = :assigned_to_user_id,
                     status = :status,
                     due_date = :due_date,
                     remarks = :remarks,
                     completed_at = :completed_at
                 WHERE id = :id',
                [
                    'task_name' => (string) $data['task_name'],
                    'department_id' => $departmentId,
                    'assigned_to_user_id' => $assignedUserId,
                    'status' => (string) ($data['status'] ?? 'pending'),
                    'due_date' => $this->nullableString($data['due_date'] ?? null),
                    'remarks' => $this->nullableString($data['remarks'] ?? null),
                    'completed_at' => $completedAt,
                    'id' => $taskId,
                ]
            );

            $recordId = (int) $task['offboarding_record_id'];
            $this->refreshStatus($recordId);

            return $recordId;
        });
    }

    public function createAsset(int $recordId, array $data, ?int $actorId): int
    {
        return $this->database->transaction(function (Database $database) use ($recordId, $data, $actorId): int {
            if (!$this->recordExists($database, $recordId)) {
                throw new RuntimeException('Offboarding record not found.');
            }

            $returnStatus = (string) ($data['return_status'] ?? 'pending');
            $checkedAt = in_array($returnStatus, ['returned', 'missing', 'waived'], true) ? date('Y-m-d H:i:s') : null;
            $checkedBy = $checkedAt !== null ? $actorId : null;

            $database->execute(
                'INSERT INTO asset_return_items (
                    offboarding_record_id, asset_name, asset_code, quantity, return_status, remarks, checked_by, checked_at
                 ) VALUES (
                    :offboarding_record_id, :asset_name, :asset_code, :quantity, :return_status, :remarks, :checked_by, :checked_at
                 )',
                [
                    'offboarding_record_id' => $recordId,
                    'asset_name' => (string) $data['asset_name'],
                    'asset_code' => $this->nullableString($data['asset_code'] ?? null),
                    'quantity' => (int) ($data['quantity'] ?? 1),
                    'return_status' => $returnStatus,
                    'remarks' => $this->nullableString($data['remarks'] ?? null),
                    'checked_by' => $checkedBy,
                    'checked_at' => $checkedAt,
                ]
            );

            $this->refreshStatus($recordId);

            return (int) $database->lastInsertId();
        });
    }

    public function updateAsset(int $assetId, array $data, ?int $actorId): ?int
    {
        return $this->database->transaction(function (Database $database) use ($assetId, $data, $actorId): ?int {
            $asset = $database->fetch(
                'SELECT id, offboarding_record_id FROM asset_return_items WHERE id = :id LIMIT 1',
                ['id' => $assetId]
            );

            if ($asset === null) {
                return null;
            }

            $returnStatus = (string) ($data['return_status'] ?? 'pending');
            $checkedAt = in_array($returnStatus, ['returned', 'missing', 'waived'], true) ? date('Y-m-d H:i:s') : null;
            $checkedBy = $checkedAt !== null ? $actorId : null;

            $database->execute(
                'UPDATE asset_return_items
                 SET asset_name = :asset_name,
                     asset_code = :asset_code,
                     quantity = :quantity,
                     return_status = :return_status,
                     remarks = :remarks,
                     checked_by = :checked_by,
                     checked_at = :checked_at
                 WHERE id = :id',
                [
                    'asset_name' => (string) $data['asset_name'],
                    'asset_code' => $this->nullableString($data['asset_code'] ?? null),
                    'quantity' => (int) ($data['quantity'] ?? 1),
                    'return_status' => $returnStatus,
                    'remarks' => $this->nullableString($data['remarks'] ?? null),
                    'checked_by' => $checkedBy,
                    'checked_at' => $checkedAt,
                    'id' => $assetId,
                ]
            );

            $recordId = (int) $asset['offboarding_record_id'];
            $this->refreshStatus($recordId);

            return $recordId;
        });
    }

    private function refreshStatus(int $recordId): void
    {
        $taskSummary = $this->database->fetch(
            "SELECT COUNT(*) AS total_tasks,
                    COALESCE(SUM(CASE WHEN status IN ('completed','waived') THEN 1 ELSE 0 END), 0) AS completed_tasks,
                    COALESCE(SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END), 0) AS in_progress_tasks
             FROM offboarding_tasks
             WHERE offboarding_record_id = :record_id",
            ['record_id' => $recordId]
        ) ?? ['total_tasks' => 0, 'completed_tasks' => 0, 'in_progress_tasks' => 0];

        $assetSummary = $this->database->fetch(
            "SELECT COUNT(*) AS total_assets,
                    COALESCE(SUM(CASE WHEN return_status IN ('returned','waived') THEN 1 ELSE 0 END), 0) AS cleared_assets,
                    COALESCE(SUM(CASE WHEN return_status <> 'pending' THEN 1 ELSE 0 END), 0) AS started_assets
             FROM asset_return_items
             WHERE offboarding_record_id = :record_id",
            ['record_id' => $recordId]
        ) ?? ['total_assets' => 0, 'cleared_assets' => 0, 'started_assets' => 0];

        $totalItems = (int) $taskSummary['total_tasks'] + (int) $assetSummary['total_assets'];
        $clearedItems = (int) $taskSummary['completed_tasks'] + (int) $assetSummary['cleared_assets'];
        $startedItems = (int) $taskSummary['completed_tasks'] + (int) $taskSummary['in_progress_tasks'] + (int) $assetSummary['started_assets'];

        if ($totalItems > 0 && $clearedItems >= $totalItems) {
            $status = 'completed';
            $clearanceStatus = 'cleared';
            $completedAt = date('Y-m-d H:i:s');
        } elseif ($startedItems > 0) {
            $status = 'in_progress';
            $clearanceStatus = 'partial';
            $completedAt = null;
        } else {
            $status = 'pending';
            $clearanceStatus = 'pending';
            $completedAt = null;
        }

        $this->database->execute(
            'UPDATE offboarding_records
             SET status = :status, clearance_status = :clearance_status, completed_at = :completed_at
             WHERE id = :id',
            [
                'status' => $status,
                'clearance_status' => $clearanceStatus,
                'completed_at' => $completedAt,
                'id' => $recordId,
            ]
        );
    }

    private function taskLines(string $lines): array
    {
        $items = [];

        foreach (preg_split('/\r\n|\r|\n/', $lines) ?: [] as $line) {
            $item = trim($line);

            if ($item !== '') {
                $items[] = $item;
            }
        }

        return array_values(array_unique($items));
    }

    private function departmentOptions(): array
    {
        return $this->database->fetchAll(
            'SELECT id, name FROM departments WHERE status = :status ORDER BY name ASC',
            ['status' => 'active']
        );
    }

    private function userOptions(): array
    {
        return $this->database->fetchAll(
            "SELECT id, username, CONCAT_WS(' ', first_name, last_name) AS name
             FROM users
             WHERE status = :status
             ORDER BY first_name ASC, last_name ASC",
            ['status' => 'active']
        );
    }

    private function recordExists(Database $database, int $recordId): bool
    {
        return (int) ($database->fetchValue(
            'SELECT COUNT(*) FROM offboarding_records WHERE id = :record_id',
            ['record_id' => $recordId]
        ) ?? 0) > 0;
    }

    private function departmentExists(Database $database, int $departmentId): bool
    {
        return (int) ($database->fetchValue(
            'SELECT COUNT(*) FROM departments WHERE id = :department_id AND status = :status',
            ['department_id' => $departmentId, 'status' => 'active']
        ) ?? 0) > 0;
    }

    private function userExists(Database $database, int $userId): bool
    {
        return (int) ($database->fetchValue(
            'SELECT COUNT(*) FROM users WHERE id = :user_id AND status = :status',
            ['user_id' => $userId, 'status' => 'active']
        ) ?? 0) > 0;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}