<?php

declare(strict_types=1);

namespace App\Modules\Structure;

use App\Core\Database;

final class MasterDataRepository
{
    private Database $database;

    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    public function summaryCounts(): array
    {
        return [
            'companies' => (int) $this->database->fetchValue('SELECT COUNT(*) FROM companies'),
            'branches' => (int) $this->database->fetchValue('SELECT COUNT(*) FROM branches'),
            'departments' => (int) $this->database->fetchValue('SELECT COUNT(*) FROM departments'),
            'teams' => (int) $this->database->fetchValue('SELECT COUNT(*) FROM teams'),
            'job_titles' => (int) $this->database->fetchValue('SELECT COUNT(*) FROM job_titles'),
            'designations' => (int) $this->database->fetchValue('SELECT COUNT(*) FROM designations'),
            'reporting_lines' => (int) $this->database->fetchValue('SELECT COUNT(*) FROM employee_reporting_lines'),
        ];
    }

    public function companies(): array
    {
        return $this->database->fetchAll('SELECT id, name FROM companies WHERE status = :status ORDER BY name ASC', ['status' => 'active']);
    }

    public function listCompanies(string $search = ''): array
    {
        $sql = 'SELECT id, name, code, email, phone, city, country, status, logo_path FROM companies';
        $params = [];

        if ($search !== '') {
            $searchValue = '%' . $search . '%';
            $sql .= ' WHERE name LIKE :search_name
                      OR code LIKE :search_code
                      OR email LIKE :search_email
                      OR city LIKE :search_city
                      OR country LIKE :search_country';
            $params = [
                'search_name' => $searchValue,
                'search_code' => $searchValue,
                'search_email' => $searchValue,
                'search_city' => $searchValue,
                'search_country' => $searchValue,
            ];
        }

        $sql .= ' ORDER BY created_at DESC';

        return $this->database->fetchAll($sql, $params);
    }

    public function findCompany(int $id): ?array
    {
        return $this->database->fetch(
            'SELECT id, name, legal_name, registration_number, tax_number, code, email, phone, address_line_1, address_line_2, city, state, country, postal_code, timezone, status, logo_path
             FROM companies WHERE id = :id LIMIT 1',
            ['id' => $id]
        );
    }

    public function updateCompany(int $id, array $data): void
    {
        $logoSql = array_key_exists('logo_path', $data) ? ', logo_path = :logo_path' : '';
        $params = array_merge([
            'id'                  => $id,
            'name'                => $data['name'],
            'legal_name'          => $data['legal_name'],
            'registration_number' => $data['registration_number'],
            'tax_number'          => $data['tax_number'],
            'code'                => $data['code'],
            'email'               => $data['email'],
            'phone'               => $data['phone'],
            'address_line_1'      => $data['address_line_1'],
            'address_line_2'      => $data['address_line_2'],
            'city'                => $data['city'],
            'state'               => $data['state'],
            'country'             => $data['country'],
            'postal_code'         => $data['postal_code'],
            'timezone'            => $data['timezone'],
            'status'              => $data['status'],
        ], array_key_exists('logo_path', $data) ? ['logo_path' => $data['logo_path']] : []);

        $this->database->execute(
            'UPDATE companies SET
                name = :name, legal_name = :legal_name, registration_number = :registration_number,
                tax_number = :tax_number, code = :code, email = :email, phone = :phone,
                address_line_1 = :address_line_1, address_line_2 = :address_line_2,
                city = :city, state = :state, country = :country,
                postal_code = :postal_code, timezone = :timezone, status = :status' . $logoSql . '
             WHERE id = :id',
            $params
        );
    }

    public function companyBranches(int $companyId): array
    {
        return $this->database->fetchAll(
            'SELECT id, name, code, city, country, status FROM branches WHERE company_id = :company_id ORDER BY name ASC',
            ['company_id' => $companyId]
        );
    }

    public function companyDepartments(int $companyId): array
    {
        return $this->database->fetchAll(
            'SELECT d.id, d.name, d.code, b.name AS branch_name, d.status
             FROM departments d LEFT JOIN branches b ON b.id = d.branch_id
             WHERE d.company_id = :company_id ORDER BY d.name ASC',
            ['company_id' => $companyId]
        );
    }

    public function companyJobTitles(): array
    {
        return $this->database->fetchAll(
            'SELECT id, name, code, level_rank, status FROM job_titles ORDER BY name ASC'
        );
    }

    public function companyDesignations(): array
    {
        return $this->database->fetchAll(
            'SELECT id, name, code, status FROM designations ORDER BY name ASC'
        );
    }

    public function updateRecord(string $table, int $id, string $name, string $code, string $status, ?string $description = null, ?int $branchId = null): void
    {
        $sql    = "UPDATE {$table} SET name = :name, status = :status";
        $params = ['name' => $name, 'status' => $status, 'id' => $id];

        if ($code !== '') {
            $sql .= ', code = :code';
            $params['code'] = strtoupper($code);
        }

        if ($description !== null) {
            $sql .= ', description = :description';
            $params['description'] = $description !== '' ? $description : null;
        }

        if ($branchId !== null) {
            $sql .= ', branch_id = :branch_id';
            $params['branch_id'] = $branchId > 0 ? $branchId : null;
        }

        $sql .= ' WHERE id = :id';
        $this->database->execute($sql, $params);
    }

    public function nextCompanyCode(): string     { return $this->nextCode('companies', 'CO-'); }
    public function nextBranchCode(): string      { return $this->nextCode('branches', 'BR-'); }
    public function nextDepartmentCode(): string  { return $this->nextCode('departments', 'DEPT-'); }
    public function nextTeamCode(): string        { return $this->nextCode('teams', 'TEAM-'); }
    public function nextJobTitleCode(): string    { return $this->nextCode('job_titles', 'JT-'); }
    public function nextDesignationCode(): string { return $this->nextCode('designations', 'DESG-'); }

    private function nextCode(string $table, string $prefix): string
    {
        $rows = $this->database->fetchAll("SELECT code FROM {$table} WHERE code LIKE :prefix", ['prefix' => $prefix . '%']);
        $max  = 0;
        foreach ($rows as $row) {
            if (preg_match('/([0-9]+)$/', (string) $row['code'], $m)) {
                $max = max($max, (int) $m[1]);
            }
        }
        return $prefix . str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
    }

    public function createCompany(array $data): int
    {
        $this->database->execute(
            'INSERT INTO companies (
                name, legal_name, registration_number, tax_number,
                code, email, phone, address_line_1, address_line_2, city, state, country, postal_code, timezone, status, logo_path
             ) VALUES (
                :name, :legal_name, :registration_number, :tax_number,
                :code, :email, :phone, :address_line_1, :address_line_2, :city, :state, :country, :postal_code, :timezone, :status, :logo_path
             )',
            $data
        );

        return (int) $this->database->lastInsertId();
    }

    public function branchesOptions(): array
    {
        return $this->database->fetchAll('SELECT id, name FROM branches WHERE status = :status ORDER BY name ASC', ['status' => 'active']);
    }

    public function departmentsOptions(): array
    {
        return $this->database->fetchAll('SELECT id, name FROM departments WHERE status = :status ORDER BY name ASC', ['status' => 'active']);
    }

    public function employeeOptions(): array
    {
        return $this->database->fetchAll(
            "SELECT id, CONCAT(employee_code, ' - ', CONCAT_WS(' ', first_name, middle_name, last_name)) AS name
             FROM employees
             WHERE archived_at IS NULL AND employee_status <> :employee_status
             ORDER BY first_name ASC, last_name ASC",
            ['employee_status' => 'archived']
        );
    }

    public function listBranches(string $search = ''): array
    {
        $sql = 'SELECT b.id, b.name, b.code, c.name AS company_name, b.city, b.status
                FROM branches b
                INNER JOIN companies c ON c.id = b.company_id';
        $params = [];

        if ($search !== '') {
            $searchValue = '%' . $search . '%';
            $sql .= ' WHERE b.name LIKE :search_name OR b.code LIKE :search_code OR c.name LIKE :search_company';
            $params = [
                'search_name' => $searchValue,
                'search_code' => $searchValue,
                'search_company' => $searchValue,
            ];
        }

        $sql .= ' ORDER BY b.created_at DESC';

        return $this->database->fetchAll($sql, $params);
    }

    public function createBranch(array $data): void
    {
        $this->database->execute(
            'INSERT INTO branches (company_id, name, code, email, phone, city, country, status)
             VALUES (:company_id, :name, :code, :email, :phone, :city, :country, :status)',
            $data
        );
    }

    public function listDepartments(string $search = ''): array
    {
        $sql = 'SELECT d.id, d.name, d.code, d.description, d.branch_id, c.name AS company_name, b.name AS branch_name, d.status
                FROM departments d
                INNER JOIN companies c ON c.id = d.company_id
                LEFT JOIN branches b ON b.id = d.branch_id';
        $params = [];

        if ($search !== '') {
            $searchValue = '%' . $search . '%';
            $sql .= ' WHERE d.name LIKE :search_name OR d.code LIKE :search_code OR c.name LIKE :search_company';
            $params = [
                'search_name' => $searchValue,
                'search_code' => $searchValue,
                'search_company' => $searchValue,
            ];
        }

        $sql .= ' ORDER BY d.created_at DESC';

        return $this->database->fetchAll($sql, $params);
    }

    public function createDepartment(array $data): void
    {
        $this->database->execute(
            'INSERT INTO departments (company_id, branch_id, parent_department_id, name, code, description, status)
             VALUES (:company_id, :branch_id, :parent_department_id, :name, :code, :description, :status)',
            $data
        );
    }

    public function listTeams(string $search = ''): array
    {
        $sql = 'SELECT t.id, t.name, t.code, t.description, d.name AS department_name, t.status
                FROM teams t
                INNER JOIN departments d ON d.id = t.department_id';
        $params = [];

        if ($search !== '') {
            $searchValue = '%' . $search . '%';
            $sql .= ' WHERE t.name LIKE :search_name OR t.code LIKE :search_code OR d.name LIKE :search_department';
            $params = [
                'search_name' => $searchValue,
                'search_code' => $searchValue,
                'search_department' => $searchValue,
            ];
        }

        $sql .= ' ORDER BY t.created_at DESC';

        return $this->database->fetchAll($sql, $params);
    }

    public function createTeam(array $data): void
    {
        $this->database->execute(
            'INSERT INTO teams (department_id, name, code, description, status)
             VALUES (:department_id, :name, :code, :description, :status)',
            $data
        );
    }

    public function listJobTitles(string $search = ''): array
    {
        $sql = 'SELECT id, name, code, level_rank, description, status FROM job_titles';
        $params = [];

        if ($search !== '') {
            $sql .= ' WHERE name LIKE :search_name OR code LIKE :search_code';
            $searchValue = '%' . $search . '%';
            $params['search_name'] = $searchValue;
            $params['search_code'] = $searchValue;
        }

        $sql .= ' ORDER BY created_at DESC';

        return $this->database->fetchAll($sql, $params);
    }

    public function createJobTitle(array $data): void
    {
        $this->database->execute(
            'INSERT INTO job_titles (name, code, level_rank, description, status)
             VALUES (:name, :code, :level_rank, :description, :status)',
            $data
        );
    }

    public function listDesignations(string $search = ''): array
    {
        $sql = 'SELECT id, name, code, description, status FROM designations';
        $params = [];

        if ($search !== '') {
            $searchValue = '%' . $search . '%';
            $sql .= ' WHERE name LIKE :search_name OR code LIKE :search_code';
            $params = [
                'search_name' => $searchValue,
                'search_code' => $searchValue,
            ];
        }

        $sql .= ' ORDER BY created_at DESC';

        return $this->database->fetchAll($sql, $params);
    }

    public function createDesignation(array $data): void
    {
        $this->database->execute(
            'INSERT INTO designations (name, code, description, status)
             VALUES (:name, :code, :description, :status)',
            $data
        );
    }

    public function listReportingLines(string $search = '', string $relationshipType = 'all'): array
    {
        $sql = "SELECT erl.id,
                       CONCAT(e.employee_code, ' - ', CONCAT_WS(' ', e.first_name, e.middle_name, e.last_name)) AS employee_name,
                       CONCAT(m.employee_code, ' - ', CONCAT_WS(' ', m.first_name, m.middle_name, m.last_name)) AS manager_name,
                       REPLACE(erl.relationship_type, '_', ' ') AS relationship_type,
                       erl.priority_order,
                       erl.effective_from,
                       COALESCE(erl.effective_to, 'Open-ended') AS effective_to,
                       CASE WHEN erl.is_active = 1 THEN 'active' ELSE 'inactive' END AS status
                FROM employee_reporting_lines erl
                INNER JOIN employees e ON e.id = erl.employee_id
                INNER JOIN employees m ON m.id = erl.manager_employee_id
                WHERE 1 = 1";
        $params = [];

        if ($relationshipType !== 'all') {
            $sql .= ' AND erl.relationship_type = :relationship_type';
            $params['relationship_type'] = $relationshipType;
        }

        if ($search !== '') {
            $searchValue = '%' . $search . '%';
            $sql .= ' AND (
                e.employee_code LIKE :search_employee_code
                OR CONCAT_WS(\' \' , e.first_name, e.middle_name, e.last_name) LIKE :search_employee_name
                OR m.employee_code LIKE :search_manager_code
                OR CONCAT_WS(\' \' , m.first_name, m.middle_name, m.last_name) LIKE :search_manager_name
                OR erl.relationship_type LIKE :search_relationship
            )';
            $params['search_employee_code'] = $searchValue;
            $params['search_employee_name'] = $searchValue;
            $params['search_manager_code'] = $searchValue;
            $params['search_manager_name'] = $searchValue;
            $params['search_relationship'] = $searchValue;
        }

        $sql .= ' ORDER BY erl.created_at DESC, erl.priority_order ASC';

        return $this->database->fetchAll($sql, $params);
    }

    public function createReportingLine(array $data, ?int $actorId): void
    {
        $payload = [
            'employee_id' => (int) $data['employee_id'],
            'manager_employee_id' => (int) $data['manager_employee_id'],
            'relationship_type' => (string) $data['relationship_type'],
            'priority_order' => (int) $data['priority_order'],
            'is_active' => (int) $data['is_active'],
            'effective_from' => (string) $data['effective_from'],
            'effective_to' => $this->nullableString($data['effective_to'] ?? null),
            'created_by' => $actorId,
        ];

        $this->database->transaction(function () use ($payload): void {
            $this->database->execute(
                'INSERT INTO employee_reporting_lines (
                    employee_id, manager_employee_id, relationship_type, priority_order, is_active, effective_from, effective_to, created_by
                 ) VALUES (
                    :employee_id, :manager_employee_id, :relationship_type, :priority_order, :is_active, :effective_from, :effective_to, :created_by
                 )',
                $payload
            );

            $this->syncPrimaryManager($payload['employee_id']);
        });
    }

    private function syncPrimaryManager(int $employeeId): void
    {
        $managerEmployeeId = $this->database->fetchValue(
            "SELECT manager_employee_id
             FROM employee_reporting_lines
             WHERE employee_id = :employee_id
               AND relationship_type = :relationship_type
               AND is_active = 1
               AND effective_from <= CURDATE()
               AND (effective_to IS NULL OR effective_to >= CURDATE())
             ORDER BY priority_order ASC, id ASC
             LIMIT 1",
            [
                'employee_id' => $employeeId,
                'relationship_type' => 'line_manager',
            ]
        );

        $this->database->execute(
            'UPDATE employees SET manager_employee_id = :manager_employee_id WHERE id = :employee_id',
            [
                'manager_employee_id' => $managerEmployeeId !== null ? (int) $managerEmployeeId : null,
                'employee_id' => $employeeId,
            ]
        );
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