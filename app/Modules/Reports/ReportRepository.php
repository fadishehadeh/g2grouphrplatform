<?php

declare(strict_types=1);

namespace App\Modules\Reports;

use App\Core\Database;

final class ReportRepository
{
    public function __construct(private Database $database)
    {
    }

    public function overview(?int $managerEmployeeId = null): array
    {
        $employeeScope = $this->scopeCondition($managerEmployeeId, 'e');
        $employeeParams = $this->scopeParams($managerEmployeeId);
        $leaveScope = $this->scopeCondition($managerEmployeeId, 'e');
        $documentScope = $this->scopeCondition($managerEmployeeId, 'e');
        $movementScope = $this->scopeCondition($managerEmployeeId, 'e');

        return [
            'total_employees' => (int) ($this->database->fetchValue(
                "SELECT COUNT(*) FROM employees e WHERE e.archived_at IS NULL{$employeeScope}",
                $employeeParams
            ) ?? 0),
            'active_employees' => (int) ($this->database->fetchValue(
                "SELECT COUNT(*) FROM employees e WHERE e.archived_at IS NULL{$employeeScope} AND e.employee_status = 'active'",
                $employeeParams
            ) ?? 0),
            'new_joiners_30' => (int) ($this->database->fetchValue(
                "SELECT COUNT(*) FROM employees e
                 WHERE e.archived_at IS NULL{$movementScope}
                   AND e.joining_date IS NOT NULL
                   AND e.joining_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
                $employeeParams
            ) ?? 0),
            'pending_leave' => (int) ($this->database->fetchValue(
                "SELECT COUNT(*)
                 FROM leave_requests lr
                 INNER JOIN employees e ON e.id = lr.employee_id
                 WHERE e.archived_at IS NULL{$leaveScope}
                   AND lr.status IN ('submitted','pending_manager','pending_hr')",
                $employeeParams
            ) ?? 0),
            'documents_expiring_30' => (int) ($this->database->fetchValue(
                "SELECT COUNT(*)
                 FROM employee_documents ed
                 INNER JOIN employees e ON e.id = ed.employee_id
                 WHERE e.archived_at IS NULL{$documentScope}
                   AND ed.is_current = 1
                   AND ed.expiry_date IS NOT NULL
                   AND ed.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)",
                $employeeParams
            ) ?? 0),
            'upcoming_exits_30' => (int) ($this->database->fetchValue(
                "SELECT COUNT(*)
                 FROM offboarding_records obr
                 INNER JOIN employees e ON e.id = obr.employee_id
                 WHERE e.archived_at IS NULL{$movementScope}
                   AND obr.exit_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)",
                $employeeParams
            ) ?? 0),
        ];
    }

    public function departmentDistribution(?int $managerEmployeeId = null): array
    {
        $scope = $this->scopeCondition($managerEmployeeId, 'e');

        return $this->database->fetchAll(
            "SELECT COALESCE(d.name, 'Unassigned') AS department_name, COUNT(*) AS total_employees
             FROM employees e
             LEFT JOIN departments d ON d.id = e.department_id
             WHERE e.archived_at IS NULL{$scope}
             GROUP BY d.id, d.name
             ORDER BY total_employees DESC, department_name ASC",
            $this->scopeParams($managerEmployeeId)
        );
    }

    public function employeeStatusDistribution(?int $managerEmployeeId = null): array
    {
        $scope = $this->scopeCondition($managerEmployeeId, 'e');

        return $this->database->fetchAll(
            "SELECT e.employee_status, COUNT(*) AS total_employees
             FROM employees e
             WHERE e.archived_at IS NULL{$scope}
             GROUP BY e.employee_status
             ORDER BY FIELD(e.employee_status, 'active', 'on_leave', 'inactive', 'draft', 'resigned', 'terminated', 'archived') ASC",
            $this->scopeParams($managerEmployeeId)
        );
    }

    public function leaveSummary(?int $managerEmployeeId = null): array
    {
        $scope = $this->scopeCondition($managerEmployeeId, 'e');

        return $this->database->fetchAll(
            "SELECT lr.status, COUNT(*) AS total_requests, COALESCE(SUM(lr.days_requested), 0) AS total_days
             FROM leave_requests lr
             INNER JOIN employees e ON e.id = lr.employee_id
             WHERE e.archived_at IS NULL{$scope}
               AND lr.created_at >= DATE_SUB(CURDATE(), INTERVAL 180 DAY)
             GROUP BY lr.status
             ORDER BY FIELD(lr.status, 'submitted', 'pending_manager', 'pending_hr', 'approved', 'rejected', 'cancelled', 'withdrawn') ASC",
            $this->scopeParams($managerEmployeeId)
        );
    }

    public function documentExpirySummary(?int $managerEmployeeId = null, int $days = 30): array
    {
        $scope = $this->scopeCondition($managerEmployeeId, 'e');
        $params = $this->scopeParams($managerEmployeeId);
        $params['days_until'] = $days;

        return $this->database->fetchAll(
            "SELECT ed.id, ed.title, ed.expiry_date, dc.name AS category_name,
                    e.employee_code, CONCAT_WS(' ', e.first_name, e.middle_name, e.last_name) AS employee_name,
                    DATEDIFF(ed.expiry_date, CURDATE()) AS days_until_expiry
             FROM employee_documents ed
             INNER JOIN employees e ON e.id = ed.employee_id
             INNER JOIN document_categories dc ON dc.id = ed.category_id
             WHERE e.archived_at IS NULL{$scope}
               AND ed.is_current = 1
               AND ed.expiry_date IS NOT NULL
               AND DATEDIFF(ed.expiry_date, CURDATE()) BETWEEN 0 AND :days_until
             ORDER BY ed.expiry_date ASC, employee_name ASC
             LIMIT 10",
            $params
        );
    }

    public function recentJoiners(?int $managerEmployeeId = null): array
    {
        $scope = $this->scopeCondition($managerEmployeeId, 'e');

        return $this->database->fetchAll(
            "SELECT e.id, e.employee_code, CONCAT_WS(' ', e.first_name, e.middle_name, e.last_name) AS employee_name,
                    COALESCE(d.name, 'Unassigned') AS department_name, e.joining_date, e.employee_status
             FROM employees e
             LEFT JOIN departments d ON d.id = e.department_id
             WHERE e.archived_at IS NULL{$scope}
               AND e.joining_date IS NOT NULL
             ORDER BY e.joining_date DESC, employee_name ASC
             LIMIT 8",
            $this->scopeParams($managerEmployeeId)
        );
    }

    public function recentExits(?int $managerEmployeeId = null): array
    {
        $scope = $this->scopeCondition($managerEmployeeId, 'e');

        return $this->database->fetchAll(
            "SELECT obr.id, obr.record_type, obr.exit_date, obr.status, obr.clearance_status,
                    e.employee_code, CONCAT_WS(' ', e.first_name, e.middle_name, e.last_name) AS employee_name
             FROM offboarding_records obr
             INNER JOIN employees e ON e.id = obr.employee_id
             WHERE e.archived_at IS NULL{$scope}
             ORDER BY obr.exit_date DESC, employee_name ASC
             LIMIT 8",
            $this->scopeParams($managerEmployeeId)
        );
    }

    public function headcountEmployees(string $search = '', string $status = 'all', ?int $managerEmployeeId = null): array
    {
        $sql = "SELECT e.id, e.employee_code,
                       CONCAT_WS(' ', e.first_name, e.middle_name, e.last_name) AS employee_name,
                       e.work_email, e.joining_date, e.employee_status,
                       COALESCE(d.name, 'Unassigned') AS department_name,
                       COALESCE(jt.name, 'Unassigned') AS job_title_name
                FROM employees e
                LEFT JOIN departments d ON d.id = e.department_id
                LEFT JOIN job_titles jt ON jt.id = e.job_title_id
                WHERE e.archived_at IS NULL";
        $params = [];

        if ($managerEmployeeId !== null) {
            $sql .= ' AND e.manager_employee_id = :scope_manager_employee_id';
            $params['scope_manager_employee_id'] = $managerEmployeeId;
        }

        if ($status !== 'all') {
            $sql .= ' AND e.employee_status = :headcount_status';
            $params['headcount_status'] = $status;
        }

        if ($search !== '') {
            $searchValue = '%' . $search . '%';
            $sql .= ' AND ('
                . 'e.employee_code LIKE :headcount_search_code '
                . 'OR e.first_name LIKE :headcount_search_first_name '
                . 'OR e.last_name LIKE :headcount_search_last_name '
                . 'OR e.work_email LIKE :headcount_search_email '
                . 'OR COALESCE(d.name, \'\') LIKE :headcount_search_department '
                . 'OR COALESCE(jt.name, \'\') LIKE :headcount_search_job_title'
                . ')';
            $params['headcount_search_code'] = $searchValue;
            $params['headcount_search_first_name'] = $searchValue;
            $params['headcount_search_last_name'] = $searchValue;
            $params['headcount_search_email'] = $searchValue;
            $params['headcount_search_department'] = $searchValue;
            $params['headcount_search_job_title'] = $searchValue;
        }

        $sql .= " ORDER BY FIELD(e.employee_status, 'active', 'on_leave', 'inactive', 'draft', 'resigned', 'terminated', 'archived') ASC,
                         employee_name ASC";

        return $this->database->fetchAll($sql, $params);
    }

    public function departmentReport(?int $managerEmployeeId = null): array
    {
        $scope = $this->scopeCondition($managerEmployeeId, 'e');

        return $this->database->fetchAll(
            "SELECT COALESCE(d.name, 'Unassigned') AS department_name,
                    COUNT(*) AS total_employees,
                    COALESCE(SUM(CASE WHEN e.employee_status = 'active' THEN 1 ELSE 0 END), 0) AS active_employees,
                    COALESCE(SUM(CASE WHEN e.employee_status = 'on_leave' THEN 1 ELSE 0 END), 0) AS on_leave_employees,
                    COALESCE(SUM(CASE WHEN e.employee_status NOT IN ('active', 'on_leave') THEN 1 ELSE 0 END), 0) AS other_employees
             FROM employees e
             LEFT JOIN departments d ON d.id = e.department_id
             WHERE e.archived_at IS NULL{$scope}
             GROUP BY d.id, d.name
             ORDER BY total_employees DESC, department_name ASC",
            $this->scopeParams($managerEmployeeId)
        );
    }

    public function leaveUsageSummary(?int $managerEmployeeId = null, ?string $startDate = null, ?string $endDate = null): array
    {
        $sql = "SELECT lr.status, COUNT(*) AS total_requests, COALESCE(SUM(lr.days_requested), 0) AS total_days
                FROM leave_requests lr
                INNER JOIN employees e ON e.id = lr.employee_id
                WHERE e.archived_at IS NULL";
        $params = [];

        if ($managerEmployeeId !== null) {
            $sql .= ' AND e.manager_employee_id = :scope_manager_employee_id';
            $params['scope_manager_employee_id'] = $managerEmployeeId;
        }

        if ($startDate !== null && $startDate !== '') {
            $sql .= ' AND lr.start_date >= :leave_summary_start_date';
            $params['leave_summary_start_date'] = $startDate;
        }

        if ($endDate !== null && $endDate !== '') {
            $sql .= ' AND lr.end_date <= :leave_summary_end_date';
            $params['leave_summary_end_date'] = $endDate;
        }

        $sql .= " GROUP BY lr.status
                  ORDER BY FIELD(lr.status, 'submitted', 'pending_manager', 'pending_hr', 'approved', 'rejected', 'cancelled', 'withdrawn', 'draft') ASC";

        return $this->database->fetchAll($sql, $params);
    }

    public function leaveUsageReport(
        string $search = '',
        string $status = 'all',
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $managerEmployeeId = null
    ): array {
        $sql = "SELECT lr.id, lr.start_date, lr.end_date, lr.days_requested, lr.status, lr.submitted_at, lr.decided_at,
                       lt.name AS leave_type_name,
                       COALESCE(d.name, 'Unassigned') AS department_name,
                       e.employee_code,
                       CONCAT_WS(' ', e.first_name, e.middle_name, e.last_name) AS employee_name
                FROM leave_requests lr
                INNER JOIN employees e ON e.id = lr.employee_id
                INNER JOIN leave_types lt ON lt.id = lr.leave_type_id
                LEFT JOIN departments d ON d.id = e.department_id
                WHERE e.archived_at IS NULL";
        $params = [];

        if ($managerEmployeeId !== null) {
            $sql .= ' AND e.manager_employee_id = :scope_manager_employee_id';
            $params['scope_manager_employee_id'] = $managerEmployeeId;
        }

        if ($status !== 'all') {
            $sql .= ' AND lr.status = :leave_usage_status';
            $params['leave_usage_status'] = $status;
        }

        if ($startDate !== null && $startDate !== '') {
            $sql .= ' AND lr.start_date >= :leave_usage_start_date';
            $params['leave_usage_start_date'] = $startDate;
        }

        if ($endDate !== null && $endDate !== '') {
            $sql .= ' AND lr.end_date <= :leave_usage_end_date';
            $params['leave_usage_end_date'] = $endDate;
        }

        if ($search !== '') {
            $searchValue = '%' . $search . '%';
            $sql .= ' AND ('
                . 'e.employee_code LIKE :leave_usage_search_code '
                . 'OR CONCAT_WS(\' \' , e.first_name, e.middle_name, e.last_name) LIKE :leave_usage_search_name '
                . 'OR lt.name LIKE :leave_usage_search_type '
                . 'OR COALESCE(d.name, \'\') LIKE :leave_usage_search_department'
                . ')';
            $params['leave_usage_search_code'] = $searchValue;
            $params['leave_usage_search_name'] = $searchValue;
            $params['leave_usage_search_type'] = $searchValue;
            $params['leave_usage_search_department'] = $searchValue;
        }

        $sql .= ' ORDER BY COALESCE(lr.submitted_at, lr.created_at) DESC, lr.start_date DESC';

        return $this->database->fetchAll($sql, $params);
    }

    public function joinerReport(string $search = '', ?string $startDate = null, ?string $endDate = null, ?int $managerEmployeeId = null): array
    {
        $sql = "SELECT e.id, e.employee_code,
                       CONCAT_WS(' ', e.first_name, e.middle_name, e.last_name) AS employee_name,
                       e.joining_date, e.employee_status,
                       COALESCE(d.name, 'Unassigned') AS department_name,
                       COALESCE(jt.name, 'Unassigned') AS job_title_name,
                       COALESCE(CONCAT_WS(' ', m.first_name, m.middle_name, m.last_name), '—') AS manager_name
                FROM employees e
                LEFT JOIN departments d ON d.id = e.department_id
                LEFT JOIN job_titles jt ON jt.id = e.job_title_id
                LEFT JOIN employees m ON m.id = e.manager_employee_id
                WHERE e.archived_at IS NULL
                  AND e.joining_date IS NOT NULL";
        $params = [];

        if ($managerEmployeeId !== null) {
            $sql .= ' AND e.manager_employee_id = :scope_manager_employee_id';
            $params['scope_manager_employee_id'] = $managerEmployeeId;
        }

        if ($startDate !== null && $startDate !== '') {
            $sql .= ' AND e.joining_date >= :joiner_start_date';
            $params['joiner_start_date'] = $startDate;
        }

        if ($endDate !== null && $endDate !== '') {
            $sql .= ' AND e.joining_date <= :joiner_end_date';
            $params['joiner_end_date'] = $endDate;
        }

        if ($search !== '') {
            $searchValue = '%' . $search . '%';
            $sql .= ' AND ('
                . 'e.employee_code LIKE :joiner_search_code '
                . 'OR e.first_name LIKE :joiner_search_first_name '
                . 'OR e.last_name LIKE :joiner_search_last_name '
                . 'OR e.work_email LIKE :joiner_search_email '
                . 'OR COALESCE(d.name, \'\') LIKE :joiner_search_department '
                . 'OR COALESCE(jt.name, \'\') LIKE :joiner_search_job_title'
                . ')';
            $params['joiner_search_code'] = $searchValue;
            $params['joiner_search_first_name'] = $searchValue;
            $params['joiner_search_last_name'] = $searchValue;
            $params['joiner_search_email'] = $searchValue;
            $params['joiner_search_department'] = $searchValue;
            $params['joiner_search_job_title'] = $searchValue;
        }

        $sql .= ' ORDER BY e.joining_date DESC, employee_name ASC';

        return $this->database->fetchAll($sql, $params);
    }

    public function exitReport(
        string $search = '',
        string $status = 'all',
        string $recordType = 'all',
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $managerEmployeeId = null
    ): array {
        $sql = "SELECT obr.id, obr.record_type, obr.exit_date, obr.last_working_date, obr.status, obr.clearance_status, obr.reason,
                       e.employee_code,
                       CONCAT_WS(' ', e.first_name, e.middle_name, e.last_name) AS employee_name,
                       COALESCE(d.name, 'Unassigned') AS department_name
                FROM offboarding_records obr
                INNER JOIN employees e ON e.id = obr.employee_id
                LEFT JOIN departments d ON d.id = e.department_id
                WHERE e.archived_at IS NULL";
        $params = [];

        if ($managerEmployeeId !== null) {
            $sql .= ' AND e.manager_employee_id = :scope_manager_employee_id';
            $params['scope_manager_employee_id'] = $managerEmployeeId;
        }

        if ($status !== 'all') {
            $sql .= ' AND obr.status = :exit_status';
            $params['exit_status'] = $status;
        }

        if ($recordType !== 'all') {
            $sql .= ' AND obr.record_type = :exit_record_type';
            $params['exit_record_type'] = $recordType;
        }

        if ($startDate !== null && $startDate !== '') {
            $sql .= ' AND obr.exit_date >= :exit_start_date';
            $params['exit_start_date'] = $startDate;
        }

        if ($endDate !== null && $endDate !== '') {
            $sql .= ' AND obr.exit_date <= :exit_end_date';
            $params['exit_end_date'] = $endDate;
        }

        if ($search !== '') {
            $searchValue = '%' . $search . '%';
            $sql .= ' AND ('
                . 'e.employee_code LIKE :exit_search_code '
                . 'OR e.first_name LIKE :exit_search_first_name '
                . 'OR e.last_name LIKE :exit_search_last_name '
                . 'OR COALESCE(d.name, \'\') LIKE :exit_search_department '
                . 'OR COALESCE(obr.reason, \'\') LIKE :exit_search_reason'
                . ')';
            $params['exit_search_code'] = $searchValue;
            $params['exit_search_first_name'] = $searchValue;
            $params['exit_search_last_name'] = $searchValue;
            $params['exit_search_department'] = $searchValue;
            $params['exit_search_reason'] = $searchValue;
        }

        $sql .= ' ORDER BY obr.exit_date DESC, employee_name ASC';

        return $this->database->fetchAll($sql, $params);
    }

    public function documentReport(
        string $search = '',
        string $expiryFilter = 'all',
        int $days = 30,
        ?int $managerEmployeeId = null
    ): array {
        $sql = "SELECT ed.id, ed.title, ed.document_number, ed.issue_date, ed.expiry_date, ed.status,
                       dc.name AS category_name, dc.requires_expiry,
                       e.employee_code,
                       CONCAT_WS(' ', e.first_name, e.middle_name, e.last_name) AS employee_name,
                       CASE WHEN ed.expiry_date IS NULL THEN NULL ELSE DATEDIFF(ed.expiry_date, CURDATE()) END AS days_until_expiry
                FROM employee_documents ed
                INNER JOIN employees e ON e.id = ed.employee_id
                INNER JOIN document_categories dc ON dc.id = ed.category_id
                WHERE e.archived_at IS NULL
                  AND ed.is_current = 1";
        $params = [];

        if ($managerEmployeeId !== null) {
            $sql .= ' AND e.manager_employee_id = :scope_manager_employee_id';
            $params['scope_manager_employee_id'] = $managerEmployeeId;
        }

        if ($search !== '') {
            $searchValue = '%' . $search . '%';
            $sql .= ' AND ('
                . 'ed.title LIKE :document_search_title '
                . 'OR COALESCE(ed.document_number, \'\') LIKE :document_search_number '
                . 'OR dc.name LIKE :document_search_category '
                . 'OR e.employee_code LIKE :document_search_code '
                . 'OR CONCAT_WS(\' \' , e.first_name, e.middle_name, e.last_name) LIKE :document_search_name'
                . ')';
            $params['document_search_title'] = $searchValue;
            $params['document_search_number'] = $searchValue;
            $params['document_search_category'] = $searchValue;
            $params['document_search_code'] = $searchValue;
            $params['document_search_name'] = $searchValue;
        }

        if ($expiryFilter === 'expiring') {
            $sql .= ' AND ed.expiry_date IS NOT NULL AND DATEDIFF(ed.expiry_date, CURDATE()) BETWEEN 0 AND :document_days';
            $params['document_days'] = $days;
        } elseif ($expiryFilter === 'expired') {
            $sql .= ' AND ed.expiry_date IS NOT NULL AND ed.expiry_date < CURDATE()';
        } elseif ($expiryFilter === 'missing_expiry') {
            $sql .= ' AND dc.requires_expiry = 1 AND ed.expiry_date IS NULL';
        }

        $sql .= ' ORDER BY CASE WHEN ed.expiry_date IS NULL THEN 1 ELSE 0 END ASC, ed.expiry_date ASC, employee_name ASC';

        return $this->database->fetchAll($sql, $params);
    }

    public function listAuditLogs(string $search = '', string $module = 'all'): array
    {
        $sql = "SELECT al.id, al.module_name, al.entity_type, al.entity_id, al.action_name, al.old_values, al.new_values,
                       al.ip_address, al.user_agent, al.created_at,
                       COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u.first_name, u.last_name)), ''), 'System') AS actor_name
                FROM audit_logs al
                LEFT JOIN users u ON u.id = al.user_id
                WHERE 1 = 1";
        $params = [];

        if ($module !== 'all') {
            $sql .= ' AND al.module_name = :module_name';
            $params['module_name'] = $module;
        }

        if ($search !== '') {
            $searchValue = '%' . $search . '%';
            $sql .= ' AND ('
                . 'al.module_name LIKE :search_module '
                . 'OR al.entity_type LIKE :search_entity '
                . 'OR al.action_name LIKE :search_action '
                . 'OR al.ip_address LIKE :search_ip '
                . "OR CONCAT_WS(' ', u.first_name, u.last_name) LIKE :search_actor"
                . ')';
            $params['search_module'] = $searchValue;
            $params['search_entity'] = $searchValue;
            $params['search_action'] = $searchValue;
            $params['search_ip'] = $searchValue;
            $params['search_actor'] = $searchValue;
        }

        $sql .= ' ORDER BY al.created_at DESC LIMIT 100';

        return $this->database->fetchAll($sql, $params);
    }

    public function auditModules(): array
    {
        return $this->database->fetchAll(
            'SELECT DISTINCT module_name FROM audit_logs ORDER BY module_name ASC'
        );
    }

    private function scopeCondition(?int $managerEmployeeId, string $employeeAlias): string
    {
        return $managerEmployeeId !== null ? " AND {$employeeAlias}.manager_employee_id = :scope_manager_employee_id" : '';
    }

    private function scopeParams(?int $managerEmployeeId): array
    {
        return $managerEmployeeId !== null ? ['scope_manager_employee_id' => $managerEmployeeId] : [];
    }
}