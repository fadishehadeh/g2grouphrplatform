<?php

declare(strict_types=1);

namespace App\Modules\Employees;

use App\Core\Database;

final class EmployeeRepository
{
    private Database $database;

    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    public function listEmployees(string $search = ''): array
    {
        $sql = "SELECT e.id, e.employee_code, CONCAT_WS(' ', e.first_name, e.middle_name, e.last_name) AS full_name,
                       e.work_email, d.name AS department_name, jt.name AS job_title_name, e.employee_status, e.joining_date,
                       CONCAT_WS(' ', m.first_name, m.middle_name, m.last_name) AS manager_name
                FROM employees e
                LEFT JOIN departments d ON d.id = e.department_id
                LEFT JOIN job_titles jt ON jt.id = e.job_title_id
                LEFT JOIN employees m ON m.id = e.manager_employee_id
                WHERE e.archived_at IS NULL";
        $params = [];

        if ($search !== '') {
            $sql .= " AND (
                e.employee_code LIKE :search_code OR e.first_name LIKE :search_first_name OR e.last_name LIKE :search_last_name
                OR e.work_email LIKE :search_email OR d.name LIKE :search_department OR jt.name LIKE :search_job_title
            )";
            $searchValue = '%' . $search . '%';
            $params['search_code'] = $searchValue;
            $params['search_first_name'] = $searchValue;
            $params['search_last_name'] = $searchValue;
            $params['search_email'] = $searchValue;
            $params['search_department'] = $searchValue;
            $params['search_job_title'] = $searchValue;
        }

        $sql .= ' ORDER BY e.created_at DESC';

        return $this->database->fetchAll($sql, $params);
    }

    public function findEmployee(int $id): ?array
    {
        return $this->database->fetch(
            "SELECT e.*, c.name AS company_name, b.name AS branch_name, d.name AS department_name, t.name AS team_name,
                    jt.name AS job_title_name, ds.name AS designation_name,
                    CONCAT_WS(' ', m.first_name, m.middle_name, m.last_name) AS manager_name
             FROM employees e
             INNER JOIN companies c ON c.id = e.company_id
             LEFT JOIN branches b ON b.id = e.branch_id
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN teams t ON t.id = e.team_id
             LEFT JOIN job_titles jt ON jt.id = e.job_title_id
             LEFT JOIN designations ds ON ds.id = e.designation_id
             LEFT JOIN employees m ON m.id = e.manager_employee_id
             WHERE e.id = :id
             LIMIT 1",
            ['id' => $id]
        );
    }

    public function emergencyContacts(int $employeeId): array
    {
        return $this->database->fetchAll(
            'SELECT full_name, relationship, phone, alternate_phone, email, is_primary
             FROM employee_emergency_contacts
             WHERE employee_id = :employee_id
             ORDER BY is_primary DESC, created_at ASC',
            ['employee_id' => $employeeId]
        );
    }

    public function profileStats(int $employeeId): array
    {
        return [
            'documents' => (int) $this->database->fetchValue(
                'SELECT COUNT(*) FROM employee_documents WHERE employee_id = :employee_id AND is_current = 1',
                ['employee_id' => $employeeId]
            ),
            'pending_leave' => (int) $this->database->fetchValue(
                "SELECT COUNT(*) FROM leave_requests WHERE employee_id = :employee_id AND status IN ('submitted','pending_manager','pending_hr')",
                ['employee_id' => $employeeId]
            ),
            'leave_balance' => (float) $this->database->fetchValue(
                'SELECT COALESCE(SUM(closing_balance), 0) FROM leave_balances WHERE employee_id = :employee_id AND balance_year = :balance_year',
                ['employee_id' => $employeeId, 'balance_year' => date('Y')]
            ),
        ];
    }

    public function nextEmployeeCode(): string
    {
        $latest = (string) ($this->database->fetchValue('SELECT employee_code FROM employees ORDER BY id DESC LIMIT 1') ?? 'EMP-0000');
        preg_match('/([0-9]+)$/', $latest, $matches);
        $number = isset($matches[1]) ? (int) $matches[1] : 0;

        return 'EMP-' . str_pad((string) ($number + 1), 4, '0', STR_PAD_LEFT);
    }

    public function formOptions(?int $excludeEmployeeId = null): array
    {
        return [
            'companies' => $this->optionList('SELECT id, name FROM companies WHERE status = :status ORDER BY name ASC', ['status' => 'active']),
            'branches' => $this->optionList('SELECT id, name FROM branches WHERE status = :status ORDER BY name ASC', ['status' => 'active']),
            'departments' => $this->optionList('SELECT id, name FROM departments WHERE status = :status ORDER BY name ASC', ['status' => 'active']),
            'teams' => $this->optionList('SELECT id, name FROM teams WHERE status = :status ORDER BY name ASC', ['status' => 'active']),
            'job_titles' => $this->optionList('SELECT id, name FROM job_titles WHERE status = :status ORDER BY name ASC', ['status' => 'active']),
            'designations' => $this->optionList('SELECT id, name FROM designations WHERE status = :status ORDER BY name ASC', ['status' => 'active']),
            'managers' => $this->managerOptions($excludeEmployeeId),
        ];
    }

    public function createEmployee(array $data, ?int $actorId): int
    {
        return $this->database->transaction(function (Database $database) use ($data, $actorId): int {
            $payload = $this->persistableData($data, $actorId);
            $payload['created_by'] = $actorId;

            $database->execute(
                'INSERT INTO employees (
                    employee_code, company_id, branch_id, department_id, team_id, job_title_id, designation_id, manager_employee_id,
                    first_name, middle_name, last_name, work_email, personal_email, phone, alternate_phone, date_of_birth,
                    gender, marital_status, nationality, second_nationality, employment_type, contract_type, joining_date, probation_start_date,
                    probation_end_date, employee_status, notes, created_by, updated_by
                 ) VALUES (
                    :employee_code, :company_id, :branch_id, :department_id, :team_id, :job_title_id, :designation_id, :manager_employee_id,
                    :first_name, :middle_name, :last_name, :work_email, :personal_email, :phone, :alternate_phone, :date_of_birth,
                    :gender, :marital_status, :nationality, :second_nationality, :employment_type, :contract_type, :joining_date, :probation_start_date,
                    :probation_end_date, :employee_status, :notes, :created_by, :updated_by
                 )',
                $payload
            );

            $employeeId = (int) $database->lastInsertId();
            $this->insertHistoryLog($database, $employeeId, $actorId, 'created', null, null, 'Employee record created.');
            $this->insertStatusHistory(
                $database,
                $employeeId,
                null,
                (string) $payload['employee_status'],
                'Initial employee status recorded during employee creation.',
                $actorId
            );

            return $employeeId;
        });
    }

    public function updateEmployee(int $id, array $data, ?int $actorId): void
    {
        $this->database->transaction(function (Database $database) use ($id, $data, $actorId): void {
            $existing = $this->coreEmployee($id, $database);

            if ($existing === null) {
                throw new \RuntimeException('Employee not found.');
            }

            $payload = $this->persistableData($data, $actorId);
            $payload['id'] = $id;

            $database->execute(
                'UPDATE employees SET
                    employee_code = :employee_code,
                    company_id = :company_id,
                    branch_id = :branch_id,
                    department_id = :department_id,
                    team_id = :team_id,
                    job_title_id = :job_title_id,
                    designation_id = :designation_id,
                    manager_employee_id = :manager_employee_id,
                    first_name = :first_name,
                    middle_name = :middle_name,
                    last_name = :last_name,
                    work_email = :work_email,
                    personal_email = :personal_email,
                    phone = :phone,
                    alternate_phone = :alternate_phone,
                    date_of_birth = :date_of_birth,
                    gender = :gender,
                    marital_status = :marital_status,
                    nationality = :nationality,
                    second_nationality = :second_nationality,
                    employment_type = :employment_type,
                    contract_type = :contract_type,
                    joining_date = :joining_date,
                    probation_start_date = :probation_start_date,
                    probation_end_date = :probation_end_date,
                    employee_status = :employee_status,
                    notes = :notes,
                    updated_by = :updated_by
                 WHERE id = :id',
                $payload
            );

            foreach ($this->detectChanges($existing, $payload) as $change) {
                $this->insertHistoryLog(
                    $database,
                    $id,
                    $actorId,
                    'updated',
                    $change['field'],
                    $change['old'],
                    $change['new']
                );
            }

            if ($this->normalizeComparableValue($existing['employee_status'] ?? null) !== $this->normalizeComparableValue($payload['employee_status'])) {
                $this->insertStatusHistory(
                    $database,
                    $id,
                    $this->nullableString($existing['employee_status'] ?? null),
                    (string) $payload['employee_status'],
                    'Employee status updated from the profile maintenance form.',
                    $actorId
                );
            }
        });
    }

    public function archiveEmployee(int $id, string $remarks, ?int $actorId): bool
    {
        return $this->database->transaction(function (Database $database) use ($id, $remarks, $actorId): bool {
            $employee = $this->coreEmployee($id, $database);

            if ($employee === null) {
                throw new \RuntimeException('Employee not found.');
            }

            $isArchived = ($employee['archived_at'] ?? null) !== null || (string) ($employee['employee_status'] ?? '') === 'archived';

            if ($isArchived) {
                return false;
            }

            $archivedAt = date('Y-m-d H:i:s');

            $database->execute(
                'UPDATE employees SET employee_status = :employee_status, archived_at = :archived_at, updated_by = :updated_by WHERE id = :id',
                [
                    'employee_status' => 'archived',
                    'archived_at' => $archivedAt,
                    'updated_by' => $actorId,
                    'id' => $id,
                ]
            );

            $this->insertStatusHistory(
                $database,
                $id,
                $this->nullableString($employee['employee_status'] ?? null),
                'archived',
                $remarks !== '' ? $remarks : 'Employee archived from the employee profile.',
                $actorId
            );
            $this->insertHistoryLog(
                $database,
                $id,
                $actorId,
                'archived',
                'employee_status',
                $this->nullableString($employee['employee_status'] ?? null),
                'archived'
            );
            $this->insertHistoryLog(
                $database,
                $id,
                $actorId,
                'archived',
                'archived_at',
                null,
                $archivedAt
            );

            if ($remarks !== '') {
                $this->insertHistoryLog($database, $id, $actorId, 'archived', 'archive_reason', null, $remarks);
            }

            return true;
        });
    }

    public function statusHistory(int $employeeId): array
    {
        return $this->database->fetchAll(
            'SELECT esh.id, esh.previous_status, esh.new_status, esh.effective_date, esh.remarks, esh.created_at,
                    CONCAT_WS(" ", u.first_name, u.last_name) AS changed_by_name
             FROM employee_status_history esh
             LEFT JOIN users u ON u.id = esh.changed_by
             WHERE esh.employee_id = :employee_id
             ORDER BY esh.effective_date DESC, esh.id DESC',
            ['employee_id' => $employeeId]
        );
    }

    public function historyLogs(int $employeeId): array
    {
        return $this->database->fetchAll(
            'SELECT ehl.id, ehl.action_name, ehl.field_name, ehl.old_value, ehl.new_value, ehl.created_at,
                    CONCAT_WS(" ", u.first_name, u.last_name) AS actor_name
             FROM employee_history_logs ehl
             LEFT JOIN users u ON u.id = ehl.actor_user_id
             WHERE ehl.employee_id = :employee_id
             ORDER BY ehl.created_at DESC, ehl.id DESC',
            ['employee_id' => $employeeId]
        );
    }

    private function optionList(string $sql, array $params = []): array
    {
        $options = [];

        foreach ($this->database->fetchAll($sql, $params) as $row) {
            $options[(string) $row['id']] = $row['name'];
        }

        return $options;
    }

    private function managerOptions(?int $excludeEmployeeId = null): array
    {
        $sql = "SELECT id, CONCAT_WS(' ', first_name, middle_name, last_name) AS name
                FROM employees
                WHERE archived_at IS NULL AND employee_status IN ('active','on_leave')";
        $params = [];

        if ($excludeEmployeeId !== null) {
            $sql .= ' AND id <> :exclude_id';
            $params['exclude_id'] = $excludeEmployeeId;
        }

        $sql .= ' ORDER BY first_name ASC, last_name ASC';

        return $this->optionList($sql, $params);
    }

    private function coreEmployee(int $id, ?Database $database = null): ?array
    {
        $database ??= $this->database;

        return $database->fetch('SELECT * FROM employees WHERE id = :id LIMIT 1', ['id' => $id]);
    }

    private function detectChanges(array $existing, array $payload): array
    {
        $changes = [];

        foreach ($this->historyTrackedFields() as $field) {
            $oldValue = $this->normalizeComparableValue($existing[$field] ?? null);
            $newValue = $this->normalizeComparableValue($payload[$field] ?? null);

            if ($oldValue === $newValue) {
                continue;
            }

            $changes[] = [
                'field' => $field,
                'old' => $oldValue,
                'new' => $newValue,
            ];
        }

        return $changes;
    }

    private function historyTrackedFields(): array
    {
        return [
            'employee_code',
            'company_id',
            'branch_id',
            'department_id',
            'team_id',
            'job_title_id',
            'designation_id',
            'manager_employee_id',
            'first_name',
            'middle_name',
            'last_name',
            'work_email',
            'personal_email',
            'phone',
            'alternate_phone',
            'date_of_birth',
            'gender',
            'marital_status',
            'nationality',
            'second_nationality',
            'employment_type',
            'contract_type',
            'joining_date',
            'probation_start_date',
            'probation_end_date',
            'employee_status',
            'notes',
        ];
    }

    private function normalizeComparableValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_string($value) ? $value : (string) $value;
    }

    private function insertStatusHistory(
        Database $database,
        int $employeeId,
        ?string $previousStatus,
        string $newStatus,
        string $remarks,
        ?int $actorId
    ): void {
        $database->execute(
            'INSERT INTO employee_status_history (employee_id, previous_status, new_status, effective_date, remarks, changed_by)
             VALUES (:employee_id, :previous_status, :new_status, :effective_date, :remarks, :changed_by)',
            [
                'employee_id' => $employeeId,
                'previous_status' => $previousStatus,
                'new_status' => $newStatus,
                'effective_date' => date('Y-m-d'),
                'remarks' => $this->nullableString($remarks),
                'changed_by' => $actorId,
            ]
        );
    }

    private function insertHistoryLog(
        Database $database,
        int $employeeId,
        ?int $actorId,
        string $actionName,
        ?string $fieldName,
        ?string $oldValue,
        ?string $newValue
    ): void {
        $database->execute(
            'INSERT INTO employee_history_logs (employee_id, actor_user_id, action_name, field_name, old_value, new_value)
             VALUES (:employee_id, :actor_user_id, :action_name, :field_name, :old_value, :new_value)',
            [
                'employee_id' => $employeeId,
                'actor_user_id' => $actorId,
                'action_name' => $actionName,
                'field_name' => $this->nullableString($fieldName),
                'old_value' => $oldValue,
                'new_value' => $newValue,
            ]
        );
    }

    public function exportEmployees(): array
    {
        return $this->database->fetchAll(
            "SELECT e.employee_code, e.first_name, e.middle_name, e.last_name, e.work_email, e.personal_email,
                    e.phone, e.employment_type, e.employee_status, e.nationality, e.second_nationality,
                    e.gender, e.date_of_birth, e.joining_date, e.marital_status, e.notes,
                    c.name AS company_name, b.name AS branch_name, d.name AS department_name,
                    t.name AS team_name, jt.name AS job_title_name, ds.name AS designation_name,
                    CONCAT_WS(' ', m.first_name, m.middle_name, m.last_name) AS manager_name
             FROM employees e
             LEFT JOIN companies c ON c.id = e.company_id
             LEFT JOIN branches b ON b.id = e.branch_id
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN teams t ON t.id = e.team_id
             LEFT JOIN job_titles jt ON jt.id = e.job_title_id
             LEFT JOIN designations ds ON ds.id = e.designation_id
             LEFT JOIN employees m ON m.id = e.manager_employee_id
             WHERE e.archived_at IS NULL
             ORDER BY e.employee_code ASC"
        );
    }

    public function importLookups(): array
    {
        $build = function (string $sql): array {
            $map = [];
            foreach ($this->database->fetchAll($sql) as $row) {
                $map[strtolower((string) $row['name'])] = (int) $row['id'];
            }
            return $map;
        };

        $employees = [];
        foreach ($this->database->fetchAll("SELECT id, employee_code FROM employees WHERE archived_at IS NULL") as $row) {
            $employees[strtoupper((string) $row['employee_code'])] = (int) $row['id'];
        }

        return [
            'companies' => $build('SELECT id, name FROM companies ORDER BY name'),
            'branches' => $build('SELECT id, name FROM branches ORDER BY name'),
            'departments' => $build("SELECT id, name FROM departments WHERE status = 'active' ORDER BY name"),
            'teams' => $build("SELECT id, name FROM teams WHERE status = 'active' ORDER BY name"),
            'job_titles' => $build("SELECT id, name FROM job_titles WHERE status = 'active' ORDER BY name"),
            'designations' => $build("SELECT id, name FROM designations WHERE status = 'active' ORDER BY name"),
            'employees' => $employees,
        ];
    }

    private function persistableData(array $data, ?int $actorId): array
    {
        return [
            'employee_code' => strtoupper((string) $data['employee_code']),
            'company_id' => (int) $data['company_id'],
            'branch_id' => $this->nullableInt($data['branch_id'] ?? null),
            'department_id' => $this->nullableInt($data['department_id'] ?? null),
            'team_id' => $this->nullableInt($data['team_id'] ?? null),
            'job_title_id' => $this->nullableInt($data['job_title_id'] ?? null),
            'designation_id' => $this->nullableInt($data['designation_id'] ?? null),
            'manager_employee_id' => $this->nullableInt($data['manager_employee_id'] ?? null),
            'first_name' => (string) $data['first_name'],
            'middle_name' => $this->nullableString($data['middle_name'] ?? null),
            'last_name' => (string) $data['last_name'],
            'work_email' => strtolower((string) $data['work_email']),
            'personal_email' => $this->nullableString(isset($data['personal_email']) ? strtolower((string) $data['personal_email']) : null),
            'phone' => $this->nullableString($data['phone'] ?? null),
            'alternate_phone' => $this->nullableString($data['alternate_phone'] ?? null),
            'date_of_birth' => $this->nullableString($data['date_of_birth'] ?? null),
            'gender' => $this->nullableString($data['gender'] ?? null),
            'marital_status' => $this->nullableString($data['marital_status'] ?? null),
            'nationality' => $this->nullableString($data['nationality'] ?? null),
            'second_nationality' => $this->nullableString($data['second_nationality'] ?? null),
            'employment_type' => (string) $data['employment_type'],
            'contract_type' => $this->nullableString($data['contract_type'] ?? null),
            'joining_date' => $this->nullableString($data['joining_date'] ?? null),
            'probation_start_date' => $this->nullableString($data['probation_start_date'] ?? null),
            'probation_end_date' => $this->nullableString($data['probation_end_date'] ?? null),
            'employee_status' => (string) ($data['employee_status'] ?? 'draft'),
            'notes' => $this->nullableString($data['notes'] ?? null),
            'updated_by' => $actorId,
        ];
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return $value === null || $value === '' ? null : (string) $value;
    }
}