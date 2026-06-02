<?php

declare(strict_types=1);

namespace App\Modules\Onboarding;

use App\Core\Database;
use RuntimeException;

final class OnboardingRepository
{
    private Database $database;

    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    public function listTemplates(string $search = ''): array
    {
        $sql = 'SELECT oct.id, oct.name, oct.description, oct.is_active, oct.created_at,
                       COUNT(ott.id) AS task_count
                FROM onboarding_checklist_templates oct
                LEFT JOIN onboarding_template_tasks ott ON ott.template_id = oct.id
                WHERE 1 = 1';
        $params = [];

        if ($search !== '') {
            $sql .= ' AND (oct.name LIKE :template_name_search OR oct.description LIKE :template_description_search)';
            $searchValue = '%' . $search . '%';
            $params['template_name_search'] = $searchValue;
            $params['template_description_search'] = $searchValue;
        }

        $sql .= ' GROUP BY oct.id, oct.name, oct.description, oct.is_active, oct.created_at
                  ORDER BY oct.is_active DESC, oct.created_at DESC';

        return $this->database->fetchAll($sql, $params);
    }

    public function listRecords(string $search = '', string $status = 'all'): array
    {
        $sql = "SELECT eo.id, eo.start_date, eo.due_date, eo.status, eo.progress_percent, eo.created_at, eo.completed_at,
                       CONCAT_WS(' ', e.first_name, e.middle_name, e.last_name) AS employee_name,
                       e.employee_code, oct.name AS template_name,
                       COUNT(eot.id) AS total_tasks,
                       COALESCE(SUM(CASE WHEN eot.status IN ('completed','waived') THEN 1 ELSE 0 END), 0) AS done_tasks
                FROM employee_onboarding eo
                INNER JOIN employees e ON e.id = eo.employee_id
                LEFT JOIN onboarding_checklist_templates oct ON oct.id = eo.template_id
                LEFT JOIN employee_onboarding_tasks eot ON eot.employee_onboarding_id = eo.id
                WHERE e.archived_at IS NULL";
        $params = [];

        if ($search !== '') {
            $sql .= ' AND (
                e.employee_code LIKE :record_employee_code_search
                OR e.first_name LIKE :record_first_name_search
                OR e.last_name LIKE :record_last_name_search
                OR oct.name LIKE :record_template_search
            )';
            $searchValue = '%' . $search . '%';
            $params['record_employee_code_search'] = $searchValue;
            $params['record_first_name_search'] = $searchValue;
            $params['record_last_name_search'] = $searchValue;
            $params['record_template_search'] = $searchValue;
        }

        if ($status !== 'all') {
            $sql .= ' AND eo.status = :record_status';
            $params['record_status'] = $status;
        }

        $sql .= ' GROUP BY eo.id, eo.start_date, eo.due_date, eo.status, eo.progress_percent, eo.created_at, eo.completed_at,
                           e.first_name, e.middle_name, e.last_name, e.employee_code, oct.name
                  ORDER BY eo.created_at DESC';

        return $this->database->fetchAll($sql, $params);
    }

    public function templateOptions(): array
    {
        return $this->database->fetchAll(
            'SELECT id, name FROM onboarding_checklist_templates WHERE is_active = 1 ORDER BY name ASC'
        );
    }

    public function roleOptions(): array
    {
        return $this->database->fetchAll('SELECT id, name, code FROM roles ORDER BY is_system DESC, name ASC');
    }

    public function findTemplate(int $templateId): ?array
    {
        return $this->database->fetch(
            'SELECT * FROM onboarding_checklist_templates WHERE id = :id LIMIT 1',
            ['id' => $templateId]
        );
    }

    public function findTemplateDetail(int $templateId): ?array
    {
        return $this->database->fetch(
            "SELECT oct.*, CONCAT_WS(' ', u.first_name, u.last_name) AS created_by_name
             FROM onboarding_checklist_templates oct
             LEFT JOIN users u ON u.id = oct.created_by
             WHERE oct.id = :id
             LIMIT 1",
            ['id' => $templateId]
        );
    }

    public function templateTasks(int $templateId): array
    {
        return $this->database->fetchAll(
            'SELECT ott.*, r.name AS role_name
             FROM onboarding_template_tasks ott
             LEFT JOIN roles r ON r.id = ott.assignee_role_id
             WHERE ott.template_id = :template_id
             ORDER BY ott.sort_order ASC, ott.id ASC',
            ['template_id' => $templateId]
        );
    }

    public function createTemplateTask(int $templateId, array $data): int
    {
        if ($this->findTemplate($templateId) === null) {
            throw new RuntimeException('Onboarding template not found.');
        }

        return $this->database->transaction(function (Database $database) use ($templateId, $data): int {
            $roleId = $data['assignee_role_id'] ?? null;

            if ($roleId !== null && !$this->roleExists($database, (int) $roleId)) {
                throw new RuntimeException('Selected onboarding role is invalid.');
            }

            $sortOrder = (int) ($data['sort_order'] ?? 0);

            if ($sortOrder <= 0) {
                $sortOrder = $this->nextSortOrder($database, $templateId);
            }

            $taskCode = $this->uniqueTaskCode(
                (string) $data['task_name'],
                $this->templateTaskCodes($database, $templateId)
            );

            $metaFields = $this->buildMetaFields($data['meta_fields'] ?? null);

            $database->execute(
                'INSERT INTO onboarding_template_tasks (
                    template_id, task_name, task_code, description, sort_order, assignee_role_id, is_required, meta_fields
                 ) VALUES (
                    :template_id, :task_name, :task_code, :description, :sort_order, :assignee_role_id, :is_required, :meta_fields
                 )',
                [
                    'template_id' => $templateId,
                    'task_name' => (string) $data['task_name'],
                    'task_code' => $taskCode,
                    'description' => $this->nullableString($data['description'] ?? null),
                    'sort_order' => $sortOrder,
                    'assignee_role_id' => $roleId,
                    'is_required' => (int) ($data['is_required'] ?? 1),
                    'meta_fields' => $metaFields,
                ]
            );

            return (int) $database->lastInsertId();
        });
    }

    public function updateTemplateTask(int $taskId, array $data): ?int
    {
        return $this->database->transaction(function (Database $database) use ($taskId, $data): ?int {
            $task = $database->fetch(
                'SELECT id, template_id FROM onboarding_template_tasks WHERE id = :id LIMIT 1',
                ['id' => $taskId]
            );

            if ($task === null) {
                return null;
            }

            $roleId = $data['assignee_role_id'] ?? null;

            if ($roleId !== null && !$this->roleExists($database, (int) $roleId)) {
                throw new RuntimeException('Selected onboarding role is invalid.');
            }

            $metaFields = $this->buildMetaFields($data['meta_fields'] ?? null);

            $database->execute(
                'UPDATE onboarding_template_tasks
                 SET task_name = :task_name,
                     description = :description,
                     sort_order = :sort_order,
                     assignee_role_id = :assignee_role_id,
                     is_required = :is_required,
                     meta_fields = :meta_fields
                 WHERE id = :id',
                [
                    'task_name' => (string) $data['task_name'],
                    'description' => $this->nullableString($data['description'] ?? null),
                    'sort_order' => (int) ($data['sort_order'] ?? 1),
                    'assignee_role_id' => $roleId,
                    'is_required' => (int) ($data['is_required'] ?? 1),
                    'meta_fields' => $metaFields,
                    'id' => $taskId,
                ]
            );

            return (int) $task['template_id'];
        });
    }

    public function createTemplate(array $data, ?int $actorId): int
    {
        $taskLines = $this->taskLines((string) ($data['task_lines'] ?? ''));

        if ($taskLines === []) {
            throw new RuntimeException('At least one onboarding task is required.');
        }

        return $this->database->transaction(function (Database $database) use ($data, $actorId, $taskLines): int {
            $companyId = (int) ($database->fetchValue('SELECT id FROM companies ORDER BY id ASC LIMIT 1') ?? 1);

            $database->execute(
                'INSERT INTO onboarding_checklist_templates (company_id, name, description, is_active, created_by)
                 VALUES (:company_id, :name, :description, :is_active, :created_by)',
                [
                    'company_id' => $companyId,
                    'name' => trim((string) ($data['name'] ?? '')),
                    'description' => $this->nullableString($data['description'] ?? null),
                    'is_active' => (int) ($data['is_active'] ?? 1),
                    'created_by' => $actorId,
                ]
            );

            $templateId = (int) $database->lastInsertId();
            $usedCodes = [];

            foreach ($taskLines as $index => $taskName) {
                $taskCode = $this->uniqueTaskCode($taskName, $usedCodes);
                $usedCodes[] = $taskCode;

                $database->execute(
                    'INSERT INTO onboarding_template_tasks (
                        template_id, task_name, task_code, description, sort_order, assignee_role_id, is_required
                     ) VALUES (
                        :template_id, :task_name, :task_code, :description, :sort_order, :assignee_role_id, :is_required
                     )',
                    [
                        'template_id' => $templateId,
                        'task_name' => $taskName,
                        'task_code' => $taskCode,
                        'description' => null,
                        'sort_order' => $index + 1,
                        'assignee_role_id' => null,
                        'is_required' => 1,
                    ]
                );
            }

            return $templateId;
        });
    }

    public function findExistingForEmployee(int $employeeId): ?array
    {
        return $this->database->fetch(
            'SELECT id, status FROM employee_onboarding WHERE employee_id = :employee_id LIMIT 1',
            ['employee_id' => $employeeId]
        );
    }

    public function createRecord(int $employeeId, array $data, ?int $actorId): int
    {
        $templateId = (int) ($data['template_id'] ?? 0);
        $template = $this->findTemplate($templateId);

        if ($template === null || (int) ($template['is_active'] ?? 0) !== 1) {
            throw new RuntimeException('Selected onboarding template is invalid.');
        }

        $templateTasks = $this->templateTasks($templateId);

        return $this->database->transaction(function (Database $database) use ($employeeId, $data, $actorId, $templateId, $templateTasks): int {
            $database->execute(
                'INSERT INTO employee_onboarding (
                    employee_id, template_id, start_date, due_date, status, progress_percent, created_by
                 ) VALUES (
                    :employee_id, :template_id, :start_date, :due_date, :status, :progress_percent, :created_by
                 )',
                [
                    'employee_id' => $employeeId,
                    'template_id' => $templateId,
                    'start_date' => $this->nullableString($data['start_date'] ?? null),
                    'due_date' => $this->nullableString($data['due_date'] ?? null),
                    'status' => $templateTasks === [] ? 'pending' : 'in_progress',
                    'progress_percent' => 0,
                    'created_by' => $actorId,
                ]
            );

            $recordId = (int) $database->lastInsertId();

            foreach ($templateTasks as $task) {
                $database->execute(
                    'INSERT INTO employee_onboarding_tasks (
                        employee_onboarding_id, template_task_id, task_name, description, assigned_to_user_id, status, due_date
                     ) VALUES (
                        :employee_onboarding_id, :template_task_id, :task_name, :description, :assigned_to_user_id, :status, :due_date
                     )',
                    [
                        'employee_onboarding_id' => $recordId,
                        'template_task_id' => (int) $task['id'],
                        'task_name' => (string) $task['task_name'],
                        'description' => $this->nullableString($task['description'] ?? null),
                        'assigned_to_user_id' => $this->activeUserIdForRole($task['assignee_role_id'] ?? null),
                        'status' => 'pending',
                        'due_date' => $this->nullableString($data['due_date'] ?? null),
                    ]
                );
            }

            $this->refreshProgress($recordId);

            return $recordId;
        });
    }

    public function findRecord(int $recordId): ?array
    {
        return $this->database->fetch(
            "SELECT eo.*, oct.name AS template_name,
                    CONCAT_WS(' ', e.first_name, e.middle_name, e.last_name) AS employee_name,
                    e.employee_code,
                    CONCAT_WS(' ', u.first_name, u.last_name) AS created_by_name
             FROM employee_onboarding eo
             INNER JOIN employees e ON e.id = eo.employee_id
             LEFT JOIN onboarding_checklist_templates oct ON oct.id = eo.template_id
             LEFT JOIN users u ON u.id = eo.created_by
             WHERE eo.id = :id
             LIMIT 1",
            ['id' => $recordId]
        );
    }

    public function recordTasks(int $recordId): array
    {
        return $this->database->fetchAll(
            "SELECT eot.*, CONCAT_WS(' ', au.first_name, au.last_name) AS assigned_to_name,
                    CONCAT_WS(' ', cu.first_name, cu.last_name) AS completed_by_name,
                    ott.meta_fields
             FROM employee_onboarding_tasks eot
             LEFT JOIN users au ON au.id = eot.assigned_to_user_id
             LEFT JOIN users cu ON cu.id = eot.completed_by
             LEFT JOIN onboarding_template_tasks ott ON ott.id = eot.template_task_id
             WHERE eot.employee_onboarding_id = :record_id
             ORDER BY eot.id ASC",
            ['record_id' => $recordId]
        );
    }

    public function updateTask(int $taskId, string $status, string $remarks, ?int $actorId, array $metaValues = []): ?int
    {
        $task = $this->database->fetch(
            'SELECT id, employee_onboarding_id FROM employee_onboarding_tasks WHERE id = :id LIMIT 1',
            ['id' => $taskId]
        );

        if ($task === null) {
            return null;
        }

        $completedAt = $status === 'completed' ? date('Y-m-d H:i:s') : null;
        $completedBy = $status === 'completed' ? $actorId : null;
        $metaValuesJson = !empty($metaValues) ? json_encode($metaValues, JSON_UNESCAPED_UNICODE) : null;

        $this->database->execute(
            'UPDATE employee_onboarding_tasks
             SET status = :status, remarks = :remarks, completed_at = :completed_at, completed_by = :completed_by, meta_values = :meta_values
             WHERE id = :id',
            [
                'status' => $status,
                'remarks' => $this->nullableString($remarks),
                'completed_at' => $completedAt,
                'completed_by' => $completedBy,
                'meta_values' => $metaValuesJson,
                'id' => $taskId,
            ]
        );

        $recordId = (int) $task['employee_onboarding_id'];
        $this->refreshProgress($recordId);

        return $recordId;
    }

    private function refreshProgress(int $recordId): void
    {
        $summary = $this->database->fetch(
            "SELECT COUNT(*) AS total_tasks,
                    COALESCE(SUM(CASE WHEN status IN ('completed','waived') THEN 1 ELSE 0 END), 0) AS done_tasks,
                    COALESCE(SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END), 0) AS active_tasks
             FROM employee_onboarding_tasks
             WHERE employee_onboarding_id = :record_id",
            ['record_id' => $recordId]
        ) ?? ['total_tasks' => 0, 'done_tasks' => 0, 'active_tasks' => 0];

        $totalTasks = (int) ($summary['total_tasks'] ?? 0);
        $doneTasks = (int) ($summary['done_tasks'] ?? 0);
        $activeTasks = (int) ($summary['active_tasks'] ?? 0);
        $progressPercent = $totalTasks > 0 ? round(($doneTasks / $totalTasks) * 100, 2) : 0;

        if ($totalTasks > 0 && $doneTasks >= $totalTasks) {
            $status = 'completed';
            $completedAt = date('Y-m-d H:i:s');
            $progressPercent = 100;
        } elseif ($doneTasks > 0 || $activeTasks > 0) {
            $status = 'in_progress';
            $completedAt = null;
        } else {
            $status = 'pending';
            $completedAt = null;
        }

        $this->database->execute(
            'UPDATE employee_onboarding
             SET status = :status, progress_percent = :progress_percent, completed_at = :completed_at
             WHERE id = :id',
            [
                'status' => $status,
                'progress_percent' => $progressPercent,
                'completed_at' => $completedAt,
                'id' => $recordId,
            ]
        );
    }

    private function activeUserIdForRole(mixed $roleId): ?int
    {
        if ($roleId === null || (int) $roleId <= 0) {
            return null;
        }

        $userId = $this->database->fetchValue(
            'SELECT id FROM users WHERE role_id = :role_id AND status = :status ORDER BY id ASC LIMIT 1',
            ['role_id' => (int) $roleId, 'status' => 'active']
        );

        return $userId === false || $userId === null ? null : (int) $userId;
    }

    private function roleExists(Database $database, int $roleId): bool
    {
        return (int) ($database->fetchValue('SELECT COUNT(*) FROM roles WHERE id = :role_id', ['role_id' => $roleId]) ?? 0) > 0;
    }

    private function templateTaskCodes(Database $database, int $templateId): array
    {
        $rows = $database->fetchAll(
            'SELECT task_code FROM onboarding_template_tasks WHERE template_id = :template_id ORDER BY id ASC',
            ['template_id' => $templateId]
        );

        return array_values(array_map(static fn (array $row): string => (string) $row['task_code'], $rows));
    }

    private function nextSortOrder(Database $database, int $templateId): int
    {
        $maxSortOrder = $database->fetchValue(
            'SELECT MAX(sort_order) FROM onboarding_template_tasks WHERE template_id = :template_id',
            ['template_id' => $templateId]
        );

        return max(1, ((int) ($maxSortOrder ?? 0)) + 1);
    }

    private function taskLines(string $taskLines): array
    {
        $tasks = [];

        foreach (preg_split('/\r\n|\r|\n/', $taskLines) ?: [] as $line) {
            $taskName = trim($line);

            if ($taskName !== '') {
                $tasks[] = $taskName;
            }
        }

        return array_values(array_unique($tasks));
    }

    private function uniqueTaskCode(string $taskName, array $usedCodes): string
    {
        $baseCode = strtoupper((string) preg_replace('/[^A-Z0-9]+/', '_', strtoupper($taskName)));
        $baseCode = trim($baseCode, '_');
        $baseCode = $baseCode !== '' ? substr($baseCode, 0, 40) : 'TASK';
        $candidate = $baseCode;
        $suffix = 1;

        while (in_array($candidate, $usedCodes, true)) {
            $candidate = substr($baseCode, 0, 35) . '_' . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    /**
     * Build a JSON string for meta_fields from submitted form data.
     * Input: array of {label, key, type, required} rows, or a JSON string already.
     */
    private function buildMetaFields(mixed $input): ?string
    {
        if ($input === null || $input === '') {
            return null;
        }

        // If already a JSON string passed directly
        if (is_string($input)) {
            $decoded = json_decode($input, true);
            if (is_array($decoded)) {
                $input = $decoded;
            } else {
                return null;
            }
        }

        if (!is_array($input)) {
            return null;
        }

        $fields = [];

        foreach ($input as $field) {
            $label = trim((string) ($field['label'] ?? ''));
            $key = trim((string) ($field['key'] ?? ''));

            if ($label === '') {
                continue;
            }

            if ($key === '') {
                // Auto-generate key from label
                $key = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $label));
            }

            $fields[] = [
                'label'    => $label,
                'key'      => $key,
                'type'     => in_array((string) ($field['type'] ?? 'text'), ['text', 'number', 'date', 'textarea'], true)
                              ? (string) $field['type']
                              : 'text',
                'required' => (bool) ($field['required'] ?? false),
            ];
        }

        return empty($fields) ? null : json_encode($fields, JSON_UNESCAPED_UNICODE);
    }
}