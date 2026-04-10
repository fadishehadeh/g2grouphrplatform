<?php

declare(strict_types=1);

namespace App\Modules\Leave;

use App\Core\Database;
use App\Modules\Notifications\NotificationRepository;

final class LeaveRepository
{
    private Database $database;
    private NotificationRepository $notifications;

    public function __construct(Database $database)
    {
        $this->database = $database;
        $this->notifications = new NotificationRepository($database);
    }

    public function balances(int $employeeId, int $year): array
    {
        return $this->database->fetchAll(
            'SELECT lt.name AS leave_type_name, lt.code AS leave_type_code,
                    lb.opening_balance, lb.accrued, lb.used_amount, lb.adjusted_amount, lb.closing_balance, lb.balance_year
             FROM leave_balances lb
             INNER JOIN leave_types lt ON lt.id = lb.leave_type_id
             WHERE lb.employee_id = :employee_id AND lb.balance_year = :balance_year
             ORDER BY lt.name ASC',
            ['employee_id' => $employeeId, 'balance_year' => $year]
        );
    }

    public function myRequests(int $employeeId): array
    {
        return $this->database->fetchAll(
            'SELECT lr.id, lr.start_date, lr.end_date, lr.start_session, lr.end_session, lr.days_requested, lr.reason,
                    lr.status, lr.rejection_reason, lr.submitted_at, lr.decided_at,
                    lt.name AS leave_type_name, lt.requires_attachment
             FROM leave_requests lr
             INNER JOIN leave_types lt ON lt.id = lr.leave_type_id
             WHERE lr.employee_id = :employee_id
             ORDER BY lr.created_at DESC',
            ['employee_id' => $employeeId]
        );
    }

    public function activeLeaveTypes(): array
    {
        return $this->database->fetchAll(
            'SELECT id, name, code, description, is_paid, requires_balance, requires_attachment,
                    requires_hr_approval, allow_half_day, default_days, carry_forward_allowed,
                    carry_forward_limit, notice_days_required, max_days_per_request
             FROM leave_types
             WHERE status = :status
             ORDER BY name ASC',
            ['status' => 'active']
        );
    }

    public function findLeaveType(int $leaveTypeId): ?array
    {
        return $this->database->fetch(
            'SELECT * FROM leave_types WHERE id = :id LIMIT 1',
            ['id' => $leaveTypeId]
        );
    }

    public function employeeContext(int $employeeId): ?array
    {
        return $this->database->fetch(
            "SELECT e.id, e.user_id, e.company_id, e.department_id, e.manager_employee_id,
                    CONCAT_WS(' ', e.first_name, e.middle_name, e.last_name) AS employee_name,
                    m.user_id AS manager_user_id
             FROM employees e
             LEFT JOIN employees m ON m.id = e.manager_employee_id
             WHERE e.id = :id
             LIMIT 1",
            ['id' => $employeeId]
        );
    }

    public function currentBalance(int $employeeId, int $leaveTypeId, int $year): float
    {
        return (float) ($this->database->fetchValue(
            'SELECT COALESCE(closing_balance, 0) FROM leave_balances
             WHERE employee_id = :employee_id AND leave_type_id = :leave_type_id AND balance_year = :balance_year
             LIMIT 1',
            [
                'employee_id' => $employeeId,
                'leave_type_id' => $leaveTypeId,
                'balance_year' => $year,
            ]
        ) ?? 0);
    }

    public function createLeaveRequest(array $data, int $employeeId, ?int $actorUserId): int
    {
        return $this->database->transaction(function (Database $database) use ($data, $employeeId, $actorUserId): int {
            $employee = $this->employeeContext($employeeId);
            $leaveType = $this->findLeaveType((int) $data['leave_type_id']);

            if ($employee === null || $leaveType === null) {
                throw new \RuntimeException('Invalid leave request context.');
            }

            $hasManager     = !empty($employee['manager_employee_id']);
            $workflowId     = $this->activeWorkflowId(
                (int) $employee['company_id'],
                isset($employee['department_id']) && $employee['department_id'] !== null ? (int) $employee['department_id'] : null
            );
            $workflowSteps  = $workflowId !== null ? $this->workflowSteps($workflowId) : [];
            $submittedAt    = date('Y-m-d H:i:s');

            // Determine initial status from the first step (or fall back to legacy logic)
            if ($workflowSteps !== []) {
                $firstStep     = $workflowSteps[0];
                $initialStatus = ($firstStep['approver_type'] === 'manager') ? 'pending_manager' : 'pending_hr';
            } else {
                $requiresHrApproval = ((int) ($leaveType['requires_hr_approval'] ?? 0) === 1) || !$hasManager;
                $initialStatus      = $hasManager ? 'pending_manager' : 'pending_hr';
            }

            $database->execute(
                'INSERT INTO leave_requests (
                    employee_id, leave_type_id, workflow_id, start_date, end_date, start_session, end_session,
                    days_requested, reason, status, current_step_order, submitted_at
                 ) VALUES (
                    :employee_id, :leave_type_id, :workflow_id, :start_date, :end_date, :start_session, :end_session,
                    :days_requested, :reason, :status, :current_step_order, :submitted_at
                 )',
                [
                    'employee_id'       => $employeeId,
                    'leave_type_id'     => (int) $data['leave_type_id'],
                    'workflow_id'       => $workflowId,
                    'start_date'        => (string) $data['start_date'],
                    'end_date'          => (string) $data['end_date'],
                    'start_session'     => (string) $data['start_session'],
                    'end_session'       => (string) $data['end_session'],
                    'days_requested'    => (float) $data['days_requested'],
                    'reason'            => (string) $data['reason'],
                    'status'            => $initialStatus,
                    'current_step_order'=> 1,
                    'submitted_at'      => $submittedAt,
                ]
            );

            $requestId = (int) $database->lastInsertId();

            if ($workflowSteps !== []) {
                // Dynamic: generate approval rows from workflow step definitions
                foreach ($workflowSteps as $step) {
                    $approverUserId = null;
                    $approverRoleId = null;

                    switch ((string) $step['approver_type']) {
                        case 'manager':
                            // Resolve to the employee's actual manager user_id
                            $approverUserId = ($hasManager && $employee['manager_user_id'] !== null)
                                ? (int) $employee['manager_user_id']
                                : null;
                            break;
                        case 'hr_admin':
                            $approverRoleId = $this->hrAdminRoleId();
                            break;
                        case 'specific_role':
                            $approverRoleId = $step['role_id'] !== null ? (int) $step['role_id'] : null;
                            break;
                        case 'specific_user':
                            $approverUserId = $step['user_id'] !== null ? (int) $step['user_id'] : null;
                            break;
                    }

                    $database->execute(
                        'INSERT INTO leave_approvals (leave_request_id, step_order, approver_user_id, approver_role_id, decision)
                         VALUES (:leave_request_id, :step_order, :approver_user_id, :approver_role_id, :decision)',
                        [
                            'leave_request_id' => $requestId,
                            'step_order'       => (int) $step['step_order'],
                            'approver_user_id' => $approverUserId,
                            'approver_role_id' => $approverRoleId,
                            'decision'         => 'pending',
                        ]
                    );
                }
            } else {
                // Legacy fallback: hardcoded manager → HR chain
                $nextStepOrder = 1;

                if ($hasManager) {
                    $database->execute(
                        'INSERT INTO leave_approvals (leave_request_id, step_order, approver_user_id, approver_role_id, decision)
                         VALUES (:leave_request_id, :step_order, :approver_user_id, :approver_role_id, :decision)',
                        [
                            'leave_request_id' => $requestId,
                            'step_order'       => 1,
                            'approver_user_id' => $employee['manager_user_id'] !== null ? (int) $employee['manager_user_id'] : null,
                            'approver_role_id' => null,
                            'decision'         => 'pending',
                        ]
                    );
                    $nextStepOrder = 2;
                }

                if ((int) ($leaveType['requires_hr_approval'] ?? 0) === 1 || !$hasManager) {
                    $database->execute(
                        'INSERT INTO leave_approvals (leave_request_id, step_order, approver_user_id, approver_role_id, decision)
                         VALUES (:leave_request_id, :step_order, :approver_user_id, :approver_role_id, :decision)',
                        [
                            'leave_request_id' => $requestId,
                            'step_order'       => $nextStepOrder,
                            'approver_user_id' => null,
                            'approver_role_id' => $this->hrAdminRoleId(),
                            'decision'         => 'pending',
                        ]
                    );
                }
            }

            // --- Notifications ---
            $this->notifyLeaveRequestStakeholders($employee, $requestId, (float) $data['days_requested'], (string) $data['start_date'], (string) $data['end_date']);

            return $requestId;
        });
    }

    public function managerPendingRequests(int $managerEmployeeId): array
    {
        return $this->database->fetchAll(
            "SELECT lr.id, e.employee_code, CONCAT_WS(' ', e.first_name, e.middle_name, e.last_name) AS employee_name,
                    lt.name AS leave_type_name, lr.start_date, lr.end_date, lr.days_requested, lr.reason,
                    lr.submitted_at, lr.status
             FROM leave_requests lr
             INNER JOIN employees e ON e.id = lr.employee_id
             INNER JOIN leave_types lt ON lt.id = lr.leave_type_id
             WHERE e.manager_employee_id = :manager_employee_id AND lr.status = 'pending_manager'
             ORDER BY COALESCE(lr.submitted_at, lr.created_at) ASC, lr.start_date ASC",
            ['manager_employee_id' => $managerEmployeeId]
        );
    }

    public function hrPendingRequests(): array
    {
        return $this->database->fetchAll(
            "SELECT lr.id, e.employee_code, CONCAT_WS(' ', e.first_name, e.middle_name, e.last_name) AS employee_name,
                    lt.name AS leave_type_name, lr.start_date, lr.end_date, lr.days_requested, lr.reason,
                    lr.submitted_at, lr.status
             FROM leave_requests lr
             INNER JOIN employees e ON e.id = lr.employee_id
             INNER JOIN leave_types lt ON lt.id = lr.leave_type_id
             WHERE lr.status = 'pending_hr'
             ORDER BY COALESCE(lr.submitted_at, lr.created_at) ASC, lr.start_date ASC"
        );
    }

    public function approveForManager(int $requestId, int $managerEmployeeId, ?int $actorUserId, ?string $comments = null): void
    {
        $request = $this->database->fetch(
            "SELECT lr.id, lr.employee_id, lr.leave_type_id, lr.days_requested, lr.start_date,
                    lt.requires_balance, lt.requires_hr_approval
             FROM leave_requests lr
             INNER JOIN employees e ON e.id = lr.employee_id
             INNER JOIN leave_types lt ON lt.id = lr.leave_type_id
             WHERE lr.id = :id AND lr.status = 'pending_manager' AND e.manager_employee_id = :manager_employee_id
             LIMIT 1",
            ['id' => $requestId, 'manager_employee_id' => $managerEmployeeId]
        );

        if ($request === null) {
            throw new \RuntimeException('Leave request not available for manager approval.');
        }

        $this->database->transaction(function (Database $database) use ($request, $actorUserId, $comments): void {
            $approval = $this->pendingApproval($request['id']);

            if ($approval !== null) {
                $this->markApproval($database, (int) $approval['id'], 'approved', $actorUserId, $comments);
            }

            if ((int) ($request['requires_hr_approval'] ?? 0) === 1) {
                $nextApproval = $this->pendingApproval($request['id']);
                $database->execute(
                    'UPDATE leave_requests SET status = :status, current_step_order = :current_step_order WHERE id = :id',
                    [
                        'status' => 'pending_hr',
                        'current_step_order' => (int) ($nextApproval['step_order'] ?? 2),
                        'id' => (int) $request['id'],
                    ]
                );

                // Notify HR users that request is forwarded
                $this->notifyHrPendingStakeholders((int) $request['id'], (int) $request['employee_id']);

                return;
            }

            $this->finalizeApproval($database, $request);

            // Notify employee of final approval
            $this->notifyLeaveDecision((int) $request['employee_id'], (int) $request['id'], 'approved');
        });
    }

    public function approveForHr(int $requestId, ?int $actorUserId, ?string $comments = null): void
    {
        $request = $this->database->fetch(
            "SELECT lr.id, lr.employee_id, lr.leave_type_id, lr.days_requested, lr.start_date,
                    lt.requires_balance
             FROM leave_requests lr
             INNER JOIN leave_types lt ON lt.id = lr.leave_type_id
             WHERE lr.id = :id AND lr.status = 'pending_hr'
             LIMIT 1",
            ['id' => $requestId]
        );

        if ($request === null) {
            throw new \RuntimeException('Leave request not available for HR approval.');
        }

        $this->database->transaction(function (Database $database) use ($request, $actorUserId, $comments): void {
            $approval = $this->pendingApproval($request['id']);

            if ($approval !== null) {
                $this->markApproval($database, (int) $approval['id'], 'approved', $actorUserId, $comments);
            }

            $this->finalizeApproval($database, $request);

            // Notify employee of final approval
            $this->notifyLeaveDecision((int) $request['employee_id'], (int) $request['id'], 'approved');
        });
    }

    public function rejectForManager(int $requestId, int $managerEmployeeId, ?int $actorUserId, string $reason): void
    {
        $request = $this->database->fetch(
            "SELECT lr.id
             FROM leave_requests lr
             INNER JOIN employees e ON e.id = lr.employee_id
             WHERE lr.id = :id AND lr.status = 'pending_manager' AND e.manager_employee_id = :manager_employee_id
             LIMIT 1",
            ['id' => $requestId, 'manager_employee_id' => $managerEmployeeId]
        );

        if ($request === null) {
            throw new \RuntimeException('Leave request not available for manager rejection.');
        }

        $this->rejectRequest((int) $request['id'], $actorUserId, $reason);
    }

    public function rejectForHr(int $requestId, ?int $actorUserId, string $reason): void
    {
        $request = $this->database->fetch(
            'SELECT id FROM leave_requests WHERE id = :id AND status = :status LIMIT 1',
            ['id' => $requestId, 'status' => 'pending_hr']
        );

        if ($request === null) {
            throw new \RuntimeException('Leave request not available for HR rejection.');
        }

        $this->rejectRequest((int) $request['id'], $actorUserId, $reason);
    }

    public function balanceOverview(array $scope, int $year, string $search = '', string $status = 'all'): array
    {
        $scopeCondition = $this->scopeCondition($scope, 'lb.employee_id', 'e.manager_employee_id');
        $sql = 'SELECT lb.employee_id, lb.leave_type_id, lb.balance_year,
                       lb.opening_balance, lb.accrued, lb.used_amount, lb.adjusted_amount, lb.closing_balance,
                       e.employee_code, e.work_email, e.employee_status,
                       CONCAT_WS(" ", e.first_name, e.middle_name, e.last_name) AS employee_name,
                       c.name AS company_name, d.name AS department_name,
                       lt.name AS leave_type_name, lt.code AS leave_type_code
                FROM leave_balances lb
                INNER JOIN employees e ON e.id = lb.employee_id
                INNER JOIN companies c ON c.id = e.company_id
                LEFT JOIN departments d ON d.id = e.department_id
                INNER JOIN leave_types lt ON lt.id = lb.leave_type_id
                WHERE lb.balance_year = :balance_year'
            . $scopeCondition['sql'];
        $params = array_merge(['balance_year' => $year], $scopeCondition['params']);

        if ($status !== 'all') {
            $sql .= ' AND e.employee_status = :employee_status';
            $params['employee_status'] = $status;
        }

        if ($search !== '') {
            $searchValue = '%' . $search . '%';
            $sql .= ' AND (
                e.employee_code LIKE :search_employee_code
                OR CONCAT_WS(" ", e.first_name, e.middle_name, e.last_name) LIKE :search_employee_name
                OR e.work_email LIKE :search_email
                OR COALESCE(c.name, "") LIKE :search_company
                OR COALESCE(d.name, "") LIKE :search_department
                OR lt.name LIKE :search_leave_type
                OR lt.code LIKE :search_leave_code
            )';
            $params['search_employee_code'] = $searchValue;
            $params['search_employee_name'] = $searchValue;
            $params['search_email'] = $searchValue;
            $params['search_company'] = $searchValue;
            $params['search_department'] = $searchValue;
            $params['search_leave_type'] = $searchValue;
            $params['search_leave_code'] = $searchValue;
        }

        $sql .= ' ORDER BY employee_name ASC, lt.name ASC';

        return $this->database->fetchAll($sql, $params);
    }

    public function listRequests(array $scope, string $search = '', string $status = 'all', int $leaveTypeId = 0): array
    {
        $scopeCondition = $this->scopeCondition($scope, 'lr.employee_id', 'e.manager_employee_id');
        $sql = 'SELECT lr.id, lr.employee_id, lr.leave_type_id, lr.start_date, lr.end_date, lr.start_session, lr.end_session,
                       lr.days_requested, lr.reason, lr.status, lr.rejection_reason, lr.submitted_at, lr.decided_at,
                       lr.created_at, e.employee_code, e.work_email, e.employee_status,
                       CONCAT_WS(" ", e.first_name, e.middle_name, e.last_name) AS employee_name,
                       c.name AS company_name, d.name AS department_name,
                       lt.name AS leave_type_name, lt.code AS leave_type_code
                FROM leave_requests lr
                INNER JOIN employees e ON e.id = lr.employee_id
                INNER JOIN companies c ON c.id = e.company_id
                LEFT JOIN departments d ON d.id = e.department_id
                INNER JOIN leave_types lt ON lt.id = lr.leave_type_id
                WHERE 1 = 1'
            . $scopeCondition['sql'];
        $params = $scopeCondition['params'];

        if ($status !== 'all') {
            $sql .= ' AND lr.status = :status';
            $params['status'] = $status;
        }

        if ($leaveTypeId > 0) {
            $sql .= ' AND lr.leave_type_id = :leave_type_id';
            $params['leave_type_id'] = $leaveTypeId;
        }

        if ($search !== '') {
            $searchValue = '%' . $search . '%';
            $sql .= ' AND (
                e.employee_code LIKE :search_employee_code
                OR CONCAT_WS(" ", e.first_name, e.middle_name, e.last_name) LIKE :search_employee_name
                OR e.work_email LIKE :search_email
                OR COALESCE(c.name, "") LIKE :search_company
                OR COALESCE(d.name, "") LIKE :search_department
                OR lt.name LIKE :search_leave_type
                OR lt.code LIKE :search_leave_code
                OR lr.reason LIKE :search_reason
            )';
            $params['search_employee_code'] = $searchValue;
            $params['search_employee_name'] = $searchValue;
            $params['search_email'] = $searchValue;
            $params['search_company'] = $searchValue;
            $params['search_department'] = $searchValue;
            $params['search_leave_type'] = $searchValue;
            $params['search_leave_code'] = $searchValue;
            $params['search_reason'] = $searchValue;
        }

        $sql .= ' ORDER BY COALESCE(lr.submitted_at, lr.created_at) DESC, lr.start_date DESC';

        return $this->database->fetchAll($sql, $params);
    }

    public function findRequestForScope(int $requestId, array $scope): ?array
    {
        $scopeCondition = $this->scopeCondition($scope, 'lr.employee_id', 'e.manager_employee_id');

        return $this->database->fetch(
            'SELECT lr.id, lr.employee_id, lr.leave_type_id, lr.workflow_id, lr.start_date, lr.end_date,
                    lr.start_session, lr.end_session, lr.days_requested, lr.reason, lr.status,
                    lr.current_step_order, lr.rejection_reason, lr.submitted_at, lr.decided_at,
                    lr.cancelled_at, lr.withdrawn_at, lr.created_at, lr.updated_at,
                    e.employee_code, e.work_email, e.employee_status,
                    CONCAT_WS(" ", e.first_name, e.middle_name, e.last_name) AS employee_name,
                    c.name AS company_name, b.name AS branch_name, d.name AS department_name,
                    t.name AS team_name, jt.name AS job_title_name,
                    lt.name AS leave_type_name, lt.code AS leave_type_code,
                    lt.requires_attachment, lt.requires_balance, lt.requires_hr_approval,
                    CONCAT_WS(" ", m.first_name, m.middle_name, m.last_name) AS manager_name
             FROM leave_requests lr
             INNER JOIN employees e ON e.id = lr.employee_id
             INNER JOIN companies c ON c.id = e.company_id
             LEFT JOIN branches b ON b.id = e.branch_id
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN teams t ON t.id = e.team_id
             LEFT JOIN job_titles jt ON jt.id = e.job_title_id
             LEFT JOIN employees m ON m.id = e.manager_employee_id
             INNER JOIN leave_types lt ON lt.id = lr.leave_type_id
             WHERE lr.id = :id'
                . $scopeCondition['sql']
                . ' LIMIT 1',
            array_merge(['id' => $requestId], $scopeCondition['params'])
        );
    }

    public function approvalTrail(int $requestId): array
    {
        return $this->database->fetchAll(
            'SELECT la.id, la.step_order, la.decision, la.comments, la.acted_at, la.created_at,
                    CONCAT_WS(" ", u.first_name, u.last_name) AS approver_name,
                    u.username AS approver_username, r.name AS approver_role_name
             FROM leave_approvals la
             LEFT JOIN users u ON u.id = la.approver_user_id
             LEFT JOIN roles r ON r.id = la.approver_role_id
             WHERE la.leave_request_id = :leave_request_id
             ORDER BY la.step_order ASC, la.id ASC',
            ['leave_request_id' => $requestId]
        );
    }

    public function attachments(int $requestId): array
    {
        return $this->database->fetchAll(
            'SELECT id, original_file_name, stored_file_name, file_path, mime_type, file_size, created_at
             FROM leave_request_attachments
             WHERE leave_request_id = :leave_request_id
             ORDER BY created_at ASC, id ASC',
            ['leave_request_id' => $requestId]
        );
    }

    public function calendarRequests(
        array $scope,
        string $startDate,
        string $endDate,
        string $status = 'approved',
        int $leaveTypeId = 0,
        string $search = ''
    ): array {
        $scopeCondition = $this->scopeCondition($scope, 'lr.employee_id', 'e.manager_employee_id');
        $sql = 'SELECT lr.id, lr.employee_id, lr.leave_type_id, lr.start_date, lr.end_date,
                       lr.start_session, lr.end_session, lr.days_requested, lr.status,
                       e.employee_code, e.employee_status,
                       CONCAT_WS(" ", e.first_name, e.middle_name, e.last_name) AS employee_name,
                       c.name AS company_name, d.name AS department_name,
                       lt.name AS leave_type_name, lt.code AS leave_type_code
                FROM leave_requests lr
                INNER JOIN employees e ON e.id = lr.employee_id
                INNER JOIN companies c ON c.id = e.company_id
                LEFT JOIN departments d ON d.id = e.department_id
                INNER JOIN leave_types lt ON lt.id = lr.leave_type_id
                WHERE lr.end_date >= :range_start AND lr.start_date <= :range_end'
            . $scopeCondition['sql'];
        $params = array_merge([
            'range_start' => $startDate,
            'range_end' => $endDate,
        ], $scopeCondition['params']);

        if ($status !== 'all') {
            $sql .= ' AND lr.status = :status';
            $params['status'] = $status;
        }

        if ($leaveTypeId > 0) {
            $sql .= ' AND lr.leave_type_id = :leave_type_id';
            $params['leave_type_id'] = $leaveTypeId;
        }

        if ($search !== '') {
            $searchValue = '%' . $search . '%';
            $sql .= ' AND (
                e.employee_code LIKE :search_employee_code
                OR CONCAT_WS(" ", e.first_name, e.middle_name, e.last_name) LIKE :search_employee_name
                OR COALESCE(c.name, "") LIKE :search_company
                OR COALESCE(d.name, "") LIKE :search_department
                OR lt.name LIKE :search_leave_type
                OR lt.code LIKE :search_leave_code
            )';
            $params['search_employee_code'] = $searchValue;
            $params['search_employee_name'] = $searchValue;
            $params['search_company'] = $searchValue;
            $params['search_department'] = $searchValue;
            $params['search_leave_type'] = $searchValue;
            $params['search_leave_code'] = $searchValue;
        }

        $sql .= ' ORDER BY lr.start_date ASC, employee_name ASC, lt.name ASC';

        return $this->database->fetchAll($sql, $params);
    }

    public function listLeaveTypes(string $search = ''): array
    {
        $sql = 'SELECT id, name, code, is_paid, requires_balance, requires_attachment,
                       requires_hr_approval, allow_half_day, default_days, status
                FROM leave_types
                WHERE 1 = 1';
        $params = [];

        if ($search !== '') {
            $sql .= ' AND (name LIKE :search OR code LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY status ASC, name ASC';

        return $this->database->fetchAll($sql, $params);
    }

    public function createLeaveType(array $data): void
    {
        $this->database->execute(
            'INSERT INTO leave_types (
                name, code, description, is_paid, requires_balance, requires_attachment,
                requires_hr_approval, allow_half_day, default_days, carry_forward_allowed,
                carry_forward_limit, notice_days_required, max_days_per_request, status
             ) VALUES (
                :name, :code, :description, :is_paid, :requires_balance, :requires_attachment,
                :requires_hr_approval, :allow_half_day, :default_days, :carry_forward_allowed,
                :carry_forward_limit, :notice_days_required, :max_days_per_request, :status
             )',
            [
                'name' => (string) $data['name'],
                'code' => strtoupper((string) $data['code']),
                'description' => $this->nullableString($data['description'] ?? null),
                'is_paid' => (int) ($data['is_paid'] ?? 1),
                'requires_balance' => (int) ($data['requires_balance'] ?? 1),
                'requires_attachment' => (int) ($data['requires_attachment'] ?? 0),
                'requires_hr_approval' => (int) ($data['requires_hr_approval'] ?? 0),
                'allow_half_day' => (int) ($data['allow_half_day'] ?? 0),
                'default_days' => (float) ($data['default_days'] ?? 0),
                'carry_forward_allowed' => (int) ($data['carry_forward_allowed'] ?? 0),
                'carry_forward_limit' => (float) ($data['carry_forward_limit'] ?? 0),
                'notice_days_required' => (int) ($data['notice_days_required'] ?? 0),
                'max_days_per_request' => $this->nullableFloat($data['max_days_per_request'] ?? null),
                'status' => (string) ($data['status'] ?? 'active'),
            ]
        );
    }

    public function companyOptions(): array
    {
        return $this->database->fetchAll(
            'SELECT id, name FROM companies WHERE status = :status ORDER BY name ASC',
            ['status' => 'active']
        );
    }

    public function branchOptions(): array
    {
        return $this->database->fetchAll(
            'SELECT b.id, b.company_id, b.name, c.name AS company_name
             FROM branches b
             INNER JOIN companies c ON c.id = b.company_id
             WHERE b.status = :branch_status AND c.status = :company_status
             ORDER BY c.name ASC, b.name ASC',
            [
                'branch_status' => 'active',
                'company_status' => 'active',
            ]
        );
    }

    public function policyOptions(): array
    {
        return $this->database->fetchAll(
            'SELECT lp.id, lp.company_id, lp.name, lp.is_active, c.name AS company_name
             FROM leave_policies lp
             LEFT JOIN companies c ON c.id = lp.company_id
             ORDER BY lp.is_active DESC, lp.name ASC'
        );
    }

    public function leaveTypeOptions(): array
    {
        return $this->database->fetchAll(
            'SELECT id, name, code FROM leave_types WHERE status = :status ORDER BY name ASC',
            ['status' => 'active']
        );
    }

    public function departmentOptions(): array
    {
        return $this->database->fetchAll(
            'SELECT id, company_id, name FROM departments WHERE status = :status ORDER BY name ASC',
            ['status' => 'active']
        );
    }

    public function jobTitleOptions(): array
    {
        return $this->database->fetchAll(
            'SELECT id, name FROM job_titles WHERE status = :status ORDER BY name ASC',
            ['status' => 'active']
        );
    }

    public function listLeavePolicies(string $search = ''): array
    {
        $sql = 'SELECT lp.id, lp.name, lp.description, lp.accrual_frequency, lp.is_active, lp.created_at,
                       c.name AS company_name, COUNT(lpr.id) AS rules_count
                FROM leave_policies lp
                LEFT JOIN companies c ON c.id = lp.company_id
                LEFT JOIN leave_policy_rules lpr ON lpr.leave_policy_id = lp.id
                WHERE 1 = 1';
        $params = [];

        if ($search !== '') {
            $searchValue = '%' . $search . '%';
            $sql .= ' AND (
                lp.name LIKE :search_name
                OR COALESCE(lp.description, \'\') LIKE :search_description
                OR COALESCE(c.name, \'\') LIKE :search_company
                OR lp.accrual_frequency LIKE :search_frequency
            )';
            $params = [
                'search_name' => $searchValue,
                'search_description' => $searchValue,
                'search_company' => $searchValue,
                'search_frequency' => $searchValue,
            ];
        }

        $sql .= ' GROUP BY lp.id, lp.name, lp.description, lp.accrual_frequency, lp.is_active, lp.created_at, c.name
                  ORDER BY lp.is_active DESC, lp.name ASC';

        return $this->database->fetchAll($sql, $params);
    }

    public function listLeavePolicyRules(array $policyIds): array
    {
        $policyIds = array_values(array_unique(array_map('intval', $policyIds)));

        if ($policyIds === []) {
            return [];
        }

        $placeholders = [];
        $params = [];

        foreach ($policyIds as $index => $policyId) {
            $parameter = 'policy_id_' . $index;
            $placeholders[] = ':' . $parameter;
            $params[$parameter] = $policyId;
        }

        return $this->database->fetchAll(
            'SELECT lpr.id, lpr.leave_policy_id, lpr.leave_type_id, lpr.department_id, lpr.job_title_id,
                    lt.name AS leave_type_name, d.name AS department_name,
                    jt.name AS job_title_name, lpr.employment_type, lpr.annual_allocation,
                    lpr.accrual_rate_monthly, lpr.carry_forward_limit, lpr.max_consecutive_days,
                    lpr.min_service_months
             FROM leave_policy_rules lpr
             INNER JOIN leave_types lt ON lt.id = lpr.leave_type_id
             LEFT JOIN departments d ON d.id = lpr.department_id
             LEFT JOIN job_titles jt ON jt.id = lpr.job_title_id
             WHERE lpr.leave_policy_id IN (' . implode(', ', $placeholders) . ')
             ORDER BY lpr.leave_policy_id ASC, lt.name ASC, d.name ASC, jt.name ASC',
            $params
        );
    }

    public function findLeavePolicyRule(int $id): ?array
    {
        return $this->database->fetch(
            'SELECT id, leave_policy_id, leave_type_id, department_id, job_title_id, employment_type,
                    annual_allocation, accrual_rate_monthly, carry_forward_limit, max_consecutive_days, min_service_months
             FROM leave_policy_rules
             WHERE id = :id
             LIMIT 1',
            ['id' => $id]
        );
    }

    public function createLeavePolicy(array $data, ?int $actorId): void
    {
        $this->database->execute(
            'INSERT INTO leave_policies (name, company_id, description, accrual_frequency, is_active, created_by)
             VALUES (:name, :company_id, :description, :accrual_frequency, :is_active, :created_by)',
            [
                'name' => trim((string) $data['name']),
                'company_id' => $this->nullableInt($data['company_id'] ?? null),
                'description' => $this->nullableString($data['description'] ?? null),
                'accrual_frequency' => (string) $data['accrual_frequency'],
                'is_active' => (int) ($data['is_active'] ?? 1),
                'created_by' => $actorId,
            ]
        );
    }

    public function policyRuleExists(array $data, ?int $excludeId = null): bool
    {
        $sql = 'SELECT id
                FROM leave_policy_rules
                WHERE leave_policy_id = :leave_policy_id
                  AND leave_type_id = :leave_type_id';
        $params = [
            'leave_policy_id' => (int) $data['leave_policy_id'],
            'leave_type_id' => (int) $data['leave_type_id'],
        ];

        $departmentId = $this->nullableInt($data['department_id'] ?? null);
        $jobTitleId = $this->nullableInt($data['job_title_id'] ?? null);
        $employmentType = $this->nullableString($data['employment_type'] ?? null);

        if ($departmentId === null) {
            $sql .= ' AND department_id IS NULL';
        } else {
            $sql .= ' AND department_id = :department_id';
            $params['department_id'] = $departmentId;
        }

        if ($jobTitleId === null) {
            $sql .= ' AND job_title_id IS NULL';
        } else {
            $sql .= ' AND job_title_id = :job_title_id';
            $params['job_title_id'] = $jobTitleId;
        }

        if ($employmentType === null) {
            $sql .= ' AND employment_type IS NULL';
        } else {
            $sql .= ' AND employment_type = :employment_type';
            $params['employment_type'] = $employmentType;
        }

        if ($excludeId !== null && $excludeId > 0) {
            $sql .= ' AND id <> :exclude_id';
            $params['exclude_id'] = $excludeId;
        }

        return $this->database->fetch($sql . ' LIMIT 1', $params) !== null;
    }

    public function createLeavePolicyRule(array $data): void
    {
        $this->database->execute(
            'INSERT INTO leave_policy_rules (
                leave_policy_id, leave_type_id, department_id, job_title_id, employment_type,
                annual_allocation, accrual_rate_monthly, carry_forward_limit, max_consecutive_days, min_service_months
             ) VALUES (
                :leave_policy_id, :leave_type_id, :department_id, :job_title_id, :employment_type,
                :annual_allocation, :accrual_rate_monthly, :carry_forward_limit, :max_consecutive_days, :min_service_months
             )',
            [
                'leave_policy_id' => (int) $data['leave_policy_id'],
                'leave_type_id' => (int) $data['leave_type_id'],
                'department_id' => $this->nullableInt($data['department_id'] ?? null),
                'job_title_id' => $this->nullableInt($data['job_title_id'] ?? null),
                'employment_type' => $this->nullableString($data['employment_type'] ?? null),
                'annual_allocation' => (float) $data['annual_allocation'],
                'accrual_rate_monthly' => (float) $data['accrual_rate_monthly'],
                'carry_forward_limit' => (float) $data['carry_forward_limit'],
                'max_consecutive_days' => $this->nullableFloat($data['max_consecutive_days'] ?? null),
                'min_service_months' => (int) $data['min_service_months'],
            ]
        );
    }

    public function updateLeavePolicyRule(int $id, array $data): void
    {
        $this->database->execute(
            'UPDATE leave_policy_rules
             SET leave_policy_id = :leave_policy_id,
                 leave_type_id = :leave_type_id,
                 department_id = :department_id,
                 job_title_id = :job_title_id,
                 employment_type = :employment_type,
                 annual_allocation = :annual_allocation,
                 accrual_rate_monthly = :accrual_rate_monthly,
                 carry_forward_limit = :carry_forward_limit,
                 max_consecutive_days = :max_consecutive_days,
                 min_service_months = :min_service_months
             WHERE id = :id',
            [
                'id' => $id,
                'leave_policy_id' => (int) $data['leave_policy_id'],
                'leave_type_id' => (int) $data['leave_type_id'],
                'department_id' => $this->nullableInt($data['department_id'] ?? null),
                'job_title_id' => $this->nullableInt($data['job_title_id'] ?? null),
                'employment_type' => $this->nullableString($data['employment_type'] ?? null),
                'annual_allocation' => (float) $data['annual_allocation'],
                'accrual_rate_monthly' => (float) $data['accrual_rate_monthly'],
                'carry_forward_limit' => (float) $data['carry_forward_limit'],
                'max_consecutive_days' => $this->nullableFloat($data['max_consecutive_days'] ?? null),
                'min_service_months' => (int) $data['min_service_months'],
            ]
        );
    }

    public function deleteLeavePolicyRule(int $id): void
    {
        $this->database->execute(
            'DELETE FROM leave_policy_rules WHERE id = :id',
            ['id' => $id]
        );
    }

    public function listHolidays(string $search = ''): array
    {
        $sql = 'SELECT h.id, h.company_id, h.branch_id, h.name, h.holiday_date, h.holiday_type,
                       h.is_recurring, h.description, c.name AS company_name, b.name AS branch_name
                FROM holidays h
                INNER JOIN companies c ON c.id = h.company_id
                LEFT JOIN branches b ON b.id = h.branch_id
                WHERE 1 = 1';
        $params = [];

        if ($search !== '') {
            $searchValue = '%' . $search . '%';
            $sql .= ' AND (
                h.name LIKE :search_name
                OR c.name LIKE :search_company
                OR COALESCE(b.name, \'\') LIKE :search_branch
                OR h.holiday_type LIKE :search_type
                OR h.holiday_date LIKE :search_date
            )';
            $params = [
                'search_name' => $searchValue,
                'search_company' => $searchValue,
                'search_branch' => $searchValue,
                'search_type' => $searchValue,
                'search_date' => $searchValue,
            ];
        }

        $sql .= ' ORDER BY h.holiday_date ASC, h.name ASC';

        return $this->database->fetchAll($sql, $params);
    }

    public function createHoliday(array $data): void
    {
        $this->database->execute(
            'INSERT INTO holidays (company_id, branch_id, name, holiday_date, holiday_type, is_recurring, description)
             VALUES (:company_id, :branch_id, :name, :holiday_date, :holiday_type, :is_recurring, :description)',
            [
                'company_id' => (int) $data['company_id'],
                'branch_id' => $this->nullableInt($data['branch_id'] ?? null),
                'name' => (string) $data['name'],
                'holiday_date' => (string) $data['holiday_date'],
                'holiday_type' => (string) $data['holiday_type'],
                'is_recurring' => (int) ($data['is_recurring'] ?? 0),
                'description' => $this->nullableString($data['description'] ?? null),
            ]
        );
    }

    public function listWeekendSettings(string $search = ''): array
    {
        $dayNameSql = "CASE ws.day_of_week
            WHEN 1 THEN 'Monday'
            WHEN 2 THEN 'Tuesday'
            WHEN 3 THEN 'Wednesday'
            WHEN 4 THEN 'Thursday'
            WHEN 5 THEN 'Friday'
            WHEN 6 THEN 'Saturday'
            WHEN 7 THEN 'Sunday'
            ELSE 'Unknown'
        END";

        $sql = 'SELECT ws.id, ws.company_id, ws.branch_id, ws.day_of_week, ws.is_weekend,
                       c.name AS company_name, b.name AS branch_name,
                       ' . $dayNameSql . ' AS day_name
                FROM weekend_settings ws
                INNER JOIN companies c ON c.id = ws.company_id
                LEFT JOIN branches b ON b.id = ws.branch_id
                WHERE 1 = 1';
        $params = [];

        if ($search !== '') {
            $searchValue = '%' . $search . '%';
            $sql .= ' AND (
                c.name LIKE :search_company
                OR COALESCE(b.name, \'\') LIKE :search_branch
                OR ' . $dayNameSql . ' LIKE :search_day_name
            )';
            $params = [
                'search_company' => $searchValue,
                'search_branch' => $searchValue,
                'search_day_name' => $searchValue,
            ];
        }

        $sql .= ' ORDER BY c.name ASC, b.name ASC, ws.day_of_week ASC';

        return $this->database->fetchAll($sql, $params);
    }

    public function weekendSettingExists(int $companyId, ?int $branchId, int $dayOfWeek): bool
    {
        $existingId = $this->database->fetchValue(
            'SELECT id
             FROM weekend_settings
             WHERE company_id = :company_id
               AND branch_id <=> :branch_id
               AND day_of_week = :day_of_week
             LIMIT 1',
            [
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'day_of_week' => $dayOfWeek,
            ]
        );

        return $existingId !== null;
    }

    public function activeEmployeesForBalance(): array
    {
        return $this->database->fetchAll(
            "SELECT id, CONCAT_WS(' ', first_name, middle_name, last_name) AS employee_name, employee_code
             FROM employees
             WHERE employee_status = 'active' AND archived_at IS NULL
             ORDER BY first_name ASC, last_name ASC"
        );
    }

    public function upsertBalance(int $employeeId, int $leaveTypeId, int $year, float $openingBalance, float $adjustment = 0): void
    {
        $existing = $this->database->fetch(
            'SELECT id, opening_balance, accrued, used_amount, adjusted_amount FROM leave_balances
             WHERE employee_id = :eid AND leave_type_id = :ltid AND balance_year = :year LIMIT 1',
            ['eid' => $employeeId, 'ltid' => $leaveTypeId, 'year' => $year]
        );

        if ($existing === null) {
            $closing = $openingBalance + $adjustment;
            $this->database->execute(
                'INSERT INTO leave_balances (employee_id, leave_type_id, balance_year, opening_balance, accrued, used_amount, adjusted_amount, closing_balance)
                 VALUES (:eid, :ltid, :year, :opening, 0, 0, :adjusted, :closing)',
                ['eid' => $employeeId, 'ltid' => $leaveTypeId, 'year' => $year,
                 'opening' => $openingBalance, 'adjusted' => $adjustment, 'closing' => $closing]
            );
        } else {
            $newOpening = $openingBalance;
            $newAdjusted = (float) $existing['adjusted_amount'] + $adjustment;
            $closing = $newOpening + (float) $existing['accrued'] - (float) $existing['used_amount'] + $newAdjusted;
            $this->database->execute(
                'UPDATE leave_balances SET opening_balance = :opening, adjusted_amount = :adjusted, closing_balance = :closing
                 WHERE employee_id = :eid AND leave_type_id = :ltid AND balance_year = :year',
                ['opening' => $newOpening, 'adjusted' => $newAdjusted, 'closing' => $closing,
                 'eid' => $employeeId, 'ltid' => $leaveTypeId, 'year' => $year]
            );
        }
    }

    public function bulkAssignBalances(int $year): int
    {
        $employees = $this->activeEmployeesForBalance();
        $leaveTypes = $this->database->fetchAll(
            "SELECT id, default_days FROM leave_types WHERE status = 'active'"
        );
        $count = 0;

        foreach ($employees as $employee) {
            foreach ($leaveTypes as $lt) {
                $exists = $this->database->fetchValue(
                    'SELECT id FROM leave_balances WHERE employee_id = :eid AND leave_type_id = :ltid AND balance_year = :year LIMIT 1',
                    ['eid' => $employee['id'], 'ltid' => $lt['id'], 'year' => $year]
                );

                if ($exists === null || $exists === false) {
                    $days = (float) ($lt['default_days'] ?? 0);
                    $this->database->execute(
                        'INSERT INTO leave_balances (employee_id, leave_type_id, balance_year, opening_balance, accrued, used_amount, adjusted_amount, closing_balance)
                         VALUES (:eid, :ltid, :year, :days, 0, 0, 0, :days)',
                        ['eid' => $employee['id'], 'ltid' => $lt['id'], 'year' => $year, 'days' => $days]
                    );
                    $count++;
                }
            }
        }

        return $count;
    }

    public function createWeekendSetting(array $data): void
    {
        $this->database->execute(
            'INSERT INTO weekend_settings (company_id, branch_id, day_of_week, is_weekend)
             VALUES (:company_id, :branch_id, :day_of_week, :is_weekend)',
            [
                'company_id' => (int) $data['company_id'],
                'branch_id' => $this->nullableInt($data['branch_id'] ?? null),
                'day_of_week' => (int) $data['day_of_week'],
                'is_weekend' => (int) $data['is_weekend'],
            ]
        );
    }

    /**
     * Resolve the best-matching active leave workflow for an employee.
     * Priority: department-specific > company-wide > global (no company or department).
     */
    private function activeWorkflowId(int $companyId, ?int $departmentId = null): ?int
    {
        // 1. Department-specific workflow (highest priority)
        if ($departmentId !== null) {
            $workflowId = $this->database->fetchValue(
                'SELECT id FROM approval_workflows
                 WHERE module_code = :module_code AND is_active = 1
                   AND company_id = :company_id AND department_id = :department_id
                 ORDER BY id ASC LIMIT 1',
                ['module_code' => 'leave', 'company_id' => $companyId, 'department_id' => $departmentId]
            );

            if ($workflowId !== null && $workflowId !== false) {
                return (int) $workflowId;
            }
        }

        // 2. Company-wide workflow
        $workflowId = $this->database->fetchValue(
            'SELECT id FROM approval_workflows
             WHERE module_code = :module_code AND is_active = 1
               AND company_id = :company_id AND department_id IS NULL
             ORDER BY id ASC LIMIT 1',
            ['module_code' => 'leave', 'company_id' => $companyId]
        );

        if ($workflowId !== null && $workflowId !== false) {
            return (int) $workflowId;
        }

        // 3. Global fallback (no company or department scoping)
        $workflowId = $this->database->fetchValue(
            'SELECT id FROM approval_workflows
             WHERE module_code = :module_code AND is_active = 1
               AND company_id IS NULL AND department_id IS NULL
             ORDER BY id ASC LIMIT 1',
            ['module_code' => 'leave']
        );

        return ($workflowId !== null && $workflowId !== false) ? (int) $workflowId : null;
    }

    /**
     * Return the ordered steps for a workflow.
     * Each step has: step_order, approver_type, role_id, user_id, is_required
     */
    private function workflowSteps(int $workflowId): array
    {
        return $this->database->fetchAll(
            'SELECT step_order, approver_type, role_id, user_id, is_required
             FROM approval_workflow_steps
             WHERE workflow_id = :workflow_id
             ORDER BY step_order ASC',
            ['workflow_id' => $workflowId]
        );
    }

    private function hrAdminRoleId(): ?int
    {
        $roleId = $this->database->fetchValue('SELECT id FROM roles WHERE code = :code LIMIT 1', ['code' => 'hr_admin']);

        return ($roleId !== null && $roleId !== false) ? (int) $roleId : null;
    }

    private function pendingApproval(int $requestId): ?array
    {
        return $this->database->fetch(
            'SELECT id, step_order FROM leave_approvals
             WHERE leave_request_id = :leave_request_id AND decision = :decision
             ORDER BY step_order ASC
             LIMIT 1',
            ['leave_request_id' => $requestId, 'decision' => 'pending']
        );
    }

    private function markApproval(Database $database, int $approvalId, string $decision, ?int $actorUserId, ?string $comments): void
    {
        $database->execute(
            'UPDATE leave_approvals
             SET approver_user_id = :approver_user_id, decision = :decision, comments = :comments, acted_at = :acted_at
             WHERE id = :id',
            [
                'approver_user_id' => $actorUserId,
                'decision' => $decision,
                'comments' => $this->nullableString($comments),
                'acted_at' => date('Y-m-d H:i:s'),
                'id' => $approvalId,
            ]
        );
    }

    private function finalizeApproval(Database $database, array $request): void
    {
        $database->execute(
            'UPDATE leave_requests
             SET status = :status, decided_at = :decided_at, rejection_reason = NULL
             WHERE id = :id',
            [
                'status' => 'approved',
                'decided_at' => date('Y-m-d H:i:s'),
                'id' => (int) $request['id'],
            ]
        );

        if ((int) ($request['requires_balance'] ?? 0) !== 1) {
            return;
        }

        $days = (float) $request['days_requested'];
        $database->execute(
            'UPDATE leave_balances
             SET used_amount = used_amount + :days_add,
                 closing_balance = closing_balance - :days_sub
             WHERE employee_id = :employee_id AND leave_type_id = :leave_type_id AND balance_year = :balance_year',
            [
                'days_add' => $days,
                'days_sub' => $days,
                'employee_id' => (int) $request['employee_id'],
                'leave_type_id' => (int) $request['leave_type_id'],
                'balance_year' => (int) date('Y', strtotime((string) $request['start_date'])),
            ]
        );
    }

    private function rejectRequest(int $requestId, ?int $actorUserId, string $reason): void
    {
        $this->database->transaction(function (Database $database) use ($requestId, $actorUserId, $reason): void {
            $approval = $this->pendingApproval($requestId);

            if ($approval !== null) {
                $this->markApproval($database, (int) $approval['id'], 'rejected', $actorUserId, $reason);
            }

            $database->execute(
                'UPDATE leave_requests
                 SET status = :status, rejection_reason = :rejection_reason, decided_at = :decided_at
                 WHERE id = :id',
                [
                    'status' => 'rejected',
                    'rejection_reason' => $reason,
                    'decided_at' => date('Y-m-d H:i:s'),
                    'id' => $requestId,
                ]
            );

            // Find employee_id for the notification
            $employeeId = $database->fetchValue(
                'SELECT employee_id FROM leave_requests WHERE id = :id LIMIT 1',
                ['id' => $requestId]
            );

            if ($employeeId !== null && $employeeId !== false) {
                $this->notifyLeaveDecision((int) $employeeId, $requestId, 'rejected', $reason);
            }
        });
    }

    private function scopeCondition(array $scope, string $employeeColumn, string $managerColumn): array
    {
        $scopeType = (string) ($scope['type'] ?? 'self');
        $employeeId = (int) ($scope['employee_id'] ?? 0);

        if ($scopeType === 'all') {
            return ['sql' => '', 'params' => []];
        }

        if ($employeeId <= 0) {
            throw new \InvalidArgumentException('Leave scope requires an employee id.');
        }

        if ($scopeType === 'team') {
            return [
                'sql' => sprintf(
                    ' AND (%s = :scope_self_employee_id OR %s = :scope_manager_employee_id)',
                    $employeeColumn,
                    $managerColumn
                ),
                'params' => [
                    'scope_self_employee_id' => $employeeId,
                    'scope_manager_employee_id' => $employeeId,
                ],
            ];
        }

        return [
            'sql' => sprintf(' AND %s = :scope_employee_id', $employeeColumn),
            'params' => ['scope_employee_id' => $employeeId],
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return $value === null || $value === '' ? null : (string) $value;
    }

    private function nullableFloat(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    // ── Notification helpers ────────────────────────────────────────────

    /**
     * Fetch employee profile data for rich emails.
     */
    private function employeeProfile(int $employeeId): ?array
    {
        return $this->database->fetch(
            "SELECT e.id, e.employee_code, e.work_email,
                    CONCAT_WS(' ', e.first_name, e.middle_name, e.last_name) AS employee_name,
                    e.joining_date, e.employment_type, e.employee_status,
                    c.name AS company_name, d.name AS department_name,
                    jt.name AS job_title_name, b.name AS branch_name,
                    e.user_id
             FROM employees e
             LEFT JOIN companies c ON c.id = e.company_id
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN job_titles jt ON jt.id = e.job_title_id
             LEFT JOIN branches b ON b.id = e.branch_id
             WHERE e.id = :employee_id LIMIT 1",
            ['employee_id' => $employeeId]
        );
    }

    /**
     * Build a rich HTML email body with employee leave overview.
     */
    private function buildLeaveEmailHtml(int $employeeId, string $heading, string $bodyIntro, ?int $highlightRequestId = null): string
    {
        $profile = $this->employeeProfile($employeeId);
        $year = (int) date('Y');
        $balances = $this->balances($employeeId, $year);
        $requests = $this->database->fetchAll(
            "SELECT lr.id, lt.name AS leave_type_name, lr.start_date, lr.end_date,
                    lr.days_requested, lr.status, lr.reason, lr.submitted_at, lr.decided_at
             FROM leave_requests lr
             INNER JOIN leave_types lt ON lt.id = lr.leave_type_id
             WHERE lr.employee_id = :employee_id
             ORDER BY lr.start_date DESC
             LIMIT 20",
            ['employee_id' => $employeeId]
        );

        $appName = (string) config('app.name', 'HR Management System');
        $appUrl = rtrim((string) config('app.url', ''), '/');
        $employeeName = e((string) ($profile['employee_name'] ?? 'Employee'));
        $empCode = e((string) ($profile['employee_code'] ?? '—'));
        $company = e((string) ($profile['company_name'] ?? '—'));
        $department = e((string) ($profile['department_name'] ?? '—'));
        $jobTitle = e((string) ($profile['job_title_name'] ?? '—'));
        $branch = e((string) ($profile['branch_name'] ?? '—'));
        $joiningDate = e((string) ($profile['joining_date'] ?? '—'));

        $html = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:700px;margin:0 auto;color:#333;">';
        $html .= '<div style="background:#1a3a5c;color:#fff;padding:18px 24px;border-radius:6px 6px 0 0;">';
        $html .= '<h2 style="margin:0;font-size:20px;">' . e($heading) . '</h2></div>';
        $html .= '<div style="padding:20px 24px;border:1px solid #e0e0e0;border-top:none;">';
        $html .= '<p style="font-size:15px;">' . $bodyIntro . '</p>';

        if ($highlightRequestId !== null) {
            $requestUrl = $appUrl . '/leave/requests/' . $highlightRequestId;
            $html .= '<p style="margin:20px 0;"><a href="' . e($requestUrl) . '" style="display:inline-block;background:#1a3a5c;color:#fff;padding:11px 28px;border-radius:5px;text-decoration:none;font-weight:bold;font-size:14px;">&#128196; View &amp; Action This Request</a></p>';
        }

        // Employee info card
        $html .= '<table style="width:100%;border-collapse:collapse;margin:16px 0;font-size:13px;">';
        $html .= '<tr><td style="padding:6px 12px;background:#f5f7fa;font-weight:600;width:35%;">Employee</td><td style="padding:6px 12px;">' . $employeeName . ' (' . $empCode . ')</td></tr>';
        $html .= '<tr><td style="padding:6px 12px;background:#f5f7fa;font-weight:600;">Company</td><td style="padding:6px 12px;">' . $company . '</td></tr>';
        $html .= '<tr><td style="padding:6px 12px;background:#f5f7fa;font-weight:600;">Department</td><td style="padding:6px 12px;">' . $department . '</td></tr>';
        $html .= '<tr><td style="padding:6px 12px;background:#f5f7fa;font-weight:600;">Job Title</td><td style="padding:6px 12px;">' . $jobTitle . '</td></tr>';
        $html .= '<tr><td style="padding:6px 12px;background:#f5f7fa;font-weight:600;">Branch</td><td style="padding:6px 12px;">' . $branch . '</td></tr>';
        $html .= '<tr><td style="padding:6px 12px;background:#f5f7fa;font-weight:600;">Joining Date</td><td style="padding:6px 12px;">' . $joiningDate . '</td></tr>';
        $html .= '</table>';

        // Leave balances
        if ($balances !== []) {
            $html .= '<h3 style="font-size:15px;margin:20px 0 8px;border-bottom:2px solid #1a3a5c;padding-bottom:4px;">Leave Balances (' . $year . ')</h3>';
            $html .= '<table style="width:100%;border-collapse:collapse;font-size:13px;border:1px solid #dee2e6;">';
            $html .= '<thead><tr style="background:#1a3a5c;color:#fff;">';
            $html .= '<th style="padding:8px;text-align:left;">Leave Type</th>';
            $html .= '<th style="padding:8px;text-align:center;">Opening</th>';
            $html .= '<th style="padding:8px;text-align:center;">Accrued</th>';
            $html .= '<th style="padding:8px;text-align:center;">Used</th>';
            $html .= '<th style="padding:8px;text-align:center;">Adjusted</th>';
            $html .= '<th style="padding:8px;text-align:center;font-weight:700;">Remaining</th>';
            $html .= '</tr></thead><tbody>';
            foreach ($balances as $i => $bal) {
                $bg = $i % 2 === 0 ? '#fff' : '#f9fafb';
                $remaining = (float) $bal['closing_balance'];
                $remainColor = $remaining <= 2 ? '#dc3545' : ($remaining <= 5 ? '#fd7e14' : '#198754');
                $html .= '<tr style="background:' . $bg . ';">';
                $html .= '<td style="padding:6px 8px;">' . e((string) $bal['leave_type_name']) . '</td>';
                $html .= '<td style="padding:6px 8px;text-align:center;">' . number_format((float) $bal['opening_balance'], 1) . '</td>';
                $html .= '<td style="padding:6px 8px;text-align:center;">' . number_format((float) $bal['accrued'], 1) . '</td>';
                $html .= '<td style="padding:6px 8px;text-align:center;">' . number_format((float) $bal['used_amount'], 1) . '</td>';
                $html .= '<td style="padding:6px 8px;text-align:center;">' . number_format((float) $bal['adjusted_amount'], 1) . '</td>';
                $html .= '<td style="padding:6px 8px;text-align:center;font-weight:700;color:' . $remainColor . ';">' . number_format($remaining, 1) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
        }

        // Recent leave requests
        if ($requests !== []) {
            $html .= '<h3 style="font-size:15px;margin:20px 0 8px;border-bottom:2px solid #1a3a5c;padding-bottom:4px;">Recent Leave Requests</h3>';
            $html .= '<table style="width:100%;border-collapse:collapse;font-size:13px;border:1px solid #dee2e6;">';
            $html .= '<thead><tr style="background:#1a3a5c;color:#fff;">';
            $html .= '<th style="padding:8px;text-align:left;">Type</th>';
            $html .= '<th style="padding:8px;text-align:center;">From</th>';
            $html .= '<th style="padding:8px;text-align:center;">To</th>';
            $html .= '<th style="padding:8px;text-align:center;">Days</th>';
            $html .= '<th style="padding:8px;text-align:center;">Status</th>';
            $html .= '<th style="padding:8px;text-align:left;">Reason</th>';
            $html .= '</tr></thead><tbody>';
            $statusColors = [
                'approved' => '#198754', 'rejected' => '#dc3545', 'pending_manager' => '#fd7e14',
                'pending_hr' => '#fd7e14', 'cancelled' => '#6c757d', 'withdrawn' => '#6c757d',
                'draft' => '#adb5bd', 'submitted' => '#0d6efd',
            ];
            foreach ($requests as $i => $req) {
                $isHighlight = $highlightRequestId !== null && (int) $req['id'] === $highlightRequestId;
                $bg = $isHighlight ? '#fff3cd' : ($i % 2 === 0 ? '#fff' : '#f9fafb');
                $sColor = $statusColors[$req['status']] ?? '#333';
                $reasonShort = mb_strlen((string) $req['reason']) > 50 ? mb_substr((string) $req['reason'], 0, 50) . '…' : (string) $req['reason'];
                $html .= '<tr style="background:' . $bg . ';">';
                $html .= '<td style="padding:6px 8px;">' . e((string) $req['leave_type_name']) . '</td>';
                $html .= '<td style="padding:6px 8px;text-align:center;">' . e((string) $req['start_date']) . '</td>';
                $html .= '<td style="padding:6px 8px;text-align:center;">' . e((string) $req['end_date']) . '</td>';
                $html .= '<td style="padding:6px 8px;text-align:center;">' . number_format((float) $req['days_requested'], 1) . '</td>';
                $html .= '<td style="padding:6px 8px;text-align:center;"><span style="color:' . $sColor . ';font-weight:600;">' . e(ucwords(str_replace('_', ' ', (string) $req['status']))) . '</span></td>';
                $html .= '<td style="padding:6px 8px;">' . e($reasonShort) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
        }

        $html .= '<p style="margin-top:20px;font-size:13px;color:#666;">This is an automated message from <strong>' . e($appName) . '</strong>.</p>';
        $html .= '</div></div>';

        return $html;
    }

    /**
     * Notify the relevant approver(s) that a new leave request was submitted.
     */
    private function notifyLeaveSubmitted(array $employee, int $requestId, float $days, string $startDate, string $endDate): void
    {
        $employeeName = (string) ($employee['employee_name'] ?? 'An employee');
        $title = 'New Leave Request';
        $message = "{$employeeName} submitted a leave request for {$days} day(s) ({$startDate} – {$endDate}).";
        $actionUrl = '/leave/approvals';
        $mailEnabled = (bool) config('app.mail.enabled', false);

        $bodyHtml = $mailEnabled
            ? $this->buildLeaveEmailHtml((int) $employee['id'], $title, '<strong>' . e($employeeName) . '</strong> submitted a new leave request for <strong>' . $days . ' day(s)</strong> from <strong>' . e($startDate) . '</strong> to <strong>' . e($endDate) . '</strong>. Please review and take action.', $requestId)
            : '';

        // Notify the direct manager (if any)
        $managerUserId = !empty($employee['manager_user_id']) ? (int) $employee['manager_user_id'] : null;

        if ($managerUserId !== null) {
            $this->notifications->create($managerUserId, 'leave_request', $title, $message, 'leave_request', $requestId, $actionUrl);

            if ($mailEnabled) {
                $email = $this->notifications->userEmail($managerUserId);
                if ($email !== null) {
                    $this->notifications->queueEmail($email, $title, $bodyHtml, $message, $managerUserId, 'leave_request', $requestId);
                }
            }

            return;
        }

        // No manager → goes straight to HR
        $this->notifyHrPending($requestId, (int) $employee['id']);
    }

    /**
     * Notify HR role holders that a leave request needs their approval.
     */
    private function notifyHrPending(int $requestId, int $employeeId): void
    {
        $empRow = $this->employeeContext($employeeId);
        $employeeName = $empRow !== null ? (string) ($empRow['employee_name'] ?? 'An employee') : 'An employee';
        $title = 'Leave Request Awaiting HR Approval';
        $message = "{$employeeName}'s leave request #{$requestId} requires HR approval.";
        $actionUrl = '/leave/approvals';
        $mailEnabled = (bool) config('app.mail.enabled', false);

        $hrRoleId = $this->hrAdminRoleId();

        if ($hrRoleId === null) {
            return;
        }

        $bodyHtml = $mailEnabled
            ? $this->buildLeaveEmailHtml($employeeId, $title, '<strong>' . e($employeeName) . '</strong>\'s leave request <strong>#' . $requestId . '</strong> has been forwarded for HR approval. Below is the full leave overview for this employee.')
            : '';

        $hrUserIds = $this->notifications->userIdsByRole($hrRoleId);

        foreach ($hrUserIds as $hrUserId) {
            $this->notifications->create((int) $hrUserId, 'leave_request', $title, $message, 'leave_request', $requestId, $actionUrl);

            if ($mailEnabled) {
                $email = $this->notifications->userEmail((int) $hrUserId);
                if ($email !== null) {
                    $this->notifications->queueEmail($email, $title, $bodyHtml, $message, (int) $hrUserId, 'leave_request', $requestId);
                }
            }
        }
    }

    /**
     * Notify the requesting employee about the final decision on their leave.
     */
    private function notifyLeaveDecision(int $employeeId, int $requestId, string $decision, ?string $reason = null): void
    {
        $empRow = $this->employeeContext($employeeId);
        $employeeUserId = $empRow !== null && !empty($empRow['user_id']) ? (int) $empRow['user_id'] : null;
        $employeeName = $empRow !== null ? (string) ($empRow['employee_name'] ?? 'Employee') : 'Employee';

        $status = ucfirst($decision);
        $employeeTitle = "Leave Request {$status}";
        $employeeMessage = "Your leave request #{$requestId} has been {$decision}.";

        if ($reason !== null && $reason !== '') {
            $employeeMessage .= " Reason: {$reason}";
        }

        $actionUrl = '/leave/requests/' . $requestId;
        $mailEnabled = (bool) config('app.mail.enabled', false);

        // --- Notify the employee ---
        if ($employeeUserId !== null) {
            $this->notifications->create($employeeUserId, 'leave_decision', $employeeTitle, $employeeMessage, 'leave_request', $requestId, $actionUrl);

            if ($mailEnabled) {
                $intro = 'Your leave request <strong>#' . $requestId . '</strong> has been <strong>' . e($decision) . '</strong>.';
                if ($reason !== null && $reason !== '') {
                    $intro .= ' <em>Reason: ' . e($reason) . '</em>';
                }
                $bodyHtml = $this->buildLeaveEmailHtml($employeeId, $employeeTitle, $intro, $requestId);
                $email = $this->notifications->userEmail($employeeUserId);
                if ($email !== null) {
                    $this->notifications->deliverEmail($email, $employeeTitle, $bodyHtml, $employeeMessage, $employeeUserId, 'leave_request', $requestId);
                }
            }
        }

        // --- Notify HR, super admin, manager, and leave admin email ---
        $stakeholderTitle = "Leave Request {$status}: {$employeeName}";
        $stakeholderMessage = "{$employeeName}'s leave request #{$requestId} has been {$decision}.";
        if ($reason !== null && $reason !== '') {
            $stakeholderMessage .= " Reason: {$reason}";
        }

        $stakeholderUserIds = $this->leaveHrEscalationUserIds();

        // Also include the employee's direct manager
        if ($empRow !== null && !empty($empRow['manager_user_id'])) {
            $stakeholderUserIds[] = (int) $empRow['manager_user_id'];
            $stakeholderUserIds = array_values(array_unique($stakeholderUserIds));
        }

        // Exclude the employee themselves from stakeholder list
        if ($employeeUserId !== null) {
            $stakeholderUserIds = array_values(array_filter($stakeholderUserIds, fn($uid) => $uid !== $employeeUserId));
        }

        if ($mailEnabled) {
            $stakeholderIntro = '<strong>' . e($employeeName) . '</strong>\'s leave request <strong>#' . $requestId . '</strong> has been <strong>' . e($decision) . '</strong>.';
            if ($reason !== null && $reason !== '') {
                $stakeholderIntro .= ' <em>Reason: ' . e($reason) . '</em>';
            }
            $stakeholderBodyHtml = $this->buildLeaveEmailHtml($employeeId, $stakeholderTitle, $stakeholderIntro, $requestId);
        } else {
            $stakeholderBodyHtml = '';
        }

        foreach ($stakeholderUserIds as $userId) {
            $this->notifications->create($userId, 'leave_decision', $stakeholderTitle, $stakeholderMessage, 'leave_request', $requestId, '/leave/approvals');

            if ($mailEnabled) {
                $email = $this->notifications->userEmail($userId);
                if ($email !== null) {
                    $this->notifications->deliverEmail($email, $stakeholderTitle, $stakeholderBodyHtml, $stakeholderMessage, $userId, 'leave_request', $requestId);
                }
            }
        }

        // Leave admin email (if configured and not already covered)
        if ($mailEnabled) {
            $adminEmail = $this->leaveAdminEmail();
            if ($adminEmail !== null && !$this->recipientListContainsEmail($stakeholderUserIds, $adminEmail)) {
                $this->notifications->deliverEmail($adminEmail, $stakeholderTitle, $stakeholderBodyHtml, $stakeholderMessage, null, 'leave_request', $requestId);
            }
        }
    }

    private function notifyLeaveRequestStakeholders(array $employee, int $requestId, float $days, string $startDate, string $endDate): void
    {
        $employeeName = (string) ($employee['employee_name'] ?? 'An employee');
        $title = 'New Leave Request';
        $message = "{$employeeName} submitted a leave request for {$days} day(s) ({$startDate} - {$endDate}).";
        $actionUrl = '/leave/approvals';
        $mailEnabled = (bool) config('app.mail.enabled', false);
        $bodyHtml = $mailEnabled
            ? $this->buildLeaveEmailHtml((int) $employee['id'], $title, '<strong>' . e($employeeName) . '</strong> submitted a new leave request for <strong>' . $days . ' day(s)</strong> from <strong>' . e($startDate) . '</strong> to <strong>' . e($endDate) . '</strong>. Please review and take action.', $requestId)
            : '';

        $recipientUserIds = $this->leaveStakeholderUserIds($employee);

        foreach ($recipientUserIds as $userId) {
            $this->notifications->create($userId, 'leave_request', $title, $message, 'leave_request', $requestId, $actionUrl);

            if ($mailEnabled) {
                $email = $this->notifications->userEmail($userId);
                if ($email !== null) {
                    $this->notifications->deliverEmail($email, $title, $bodyHtml, $message, $userId, 'leave_request', $requestId);
                }
            }
        }

        $adminEmail = $this->leaveAdminEmail();
        if ($mailEnabled && $adminEmail !== null && !$this->recipientListContainsEmail($recipientUserIds, $adminEmail)) {
            $this->notifications->deliverEmail($adminEmail, $title, $bodyHtml, $message, null, 'leave_request', $requestId);
        }
    }

    private function notifyHrPendingStakeholders(int $requestId, int $employeeId): void
    {
        $empRow = $this->employeeContext($employeeId);
        $employeeName = $empRow !== null ? (string) ($empRow['employee_name'] ?? 'An employee') : 'An employee';
        $title = 'Leave Request Awaiting HR Approval';
        $message = "{$employeeName}'s leave request #{$requestId} requires HR approval.";
        $actionUrl = '/leave/approvals';
        $mailEnabled = (bool) config('app.mail.enabled', false);
        $bodyHtml = $mailEnabled
            ? $this->buildLeaveEmailHtml($employeeId, $title, '<strong>' . e($employeeName) . '</strong>\'s leave request <strong>#' . $requestId . '</strong> has been forwarded for HR approval. Below is the full leave overview for this employee.')
            : '';

        $recipientUserIds = $this->leaveHrEscalationUserIds();

        foreach ($recipientUserIds as $userId) {
            $this->notifications->create($userId, 'leave_request', $title, $message, 'leave_request', $requestId, $actionUrl);

            if ($mailEnabled) {
                $email = $this->notifications->userEmail($userId);
                if ($email !== null) {
                    $this->notifications->deliverEmail($email, $title, $bodyHtml, $message, $userId, 'leave_request', $requestId);
                }
            }
        }

        $adminEmail = $this->leaveAdminEmail();
        if ($mailEnabled && $adminEmail !== null && !$this->recipientListContainsEmail($recipientUserIds, $adminEmail)) {
            $this->notifications->deliverEmail($adminEmail, $title, $bodyHtml, $message, null, 'leave_request', $requestId);
        }
    }

    private function leaveStakeholderUserIds(array $employee): array
    {
        $userIds = $this->leaveHrEscalationUserIds();
        $managerUserId = !empty($employee['manager_user_id']) ? (int) $employee['manager_user_id'] : null;

        if ($managerUserId !== null) {
            $userIds[] = $managerUserId;
        }

        return array_values(array_unique(array_map('intval', $userIds)));
    }

    private function leaveHrEscalationUserIds(): array
    {
        $userIds = [];
        $hrRoleId = $this->hrAdminRoleId();
        $superAdminRoleId = $this->superAdminRoleId();

        if ($hrRoleId !== null) {
            $userIds = [...$userIds, ...$this->notifications->userIdsByRole($hrRoleId)];
        }

        if ($superAdminRoleId !== null) {
            $userIds = [...$userIds, ...$this->notifications->userIdsByRole($superAdminRoleId)];
        }

        return array_values(array_unique(array_map('intval', $userIds)));
    }

    private function superAdminRoleId(): ?int
    {
        $roleId = $this->database->fetchValue('SELECT id FROM roles WHERE code = :code LIMIT 1', ['code' => 'super_admin']);

        return ($roleId !== null && $roleId !== false) ? (int) $roleId : null;
    }

    private function leaveAdminEmail(): ?string
    {
        $email = trim((string) config('app.leave.admin_email', ''));

        return $email !== '' ? $email : null;
    }

    private function recipientListContainsEmail(array $userIds, string $email): bool
    {
        foreach ($userIds as $userId) {
            $userEmail = $this->notifications->userEmail((int) $userId);
            if ($userEmail !== null && strcasecmp($userEmail, $email) === 0) {
                return true;
            }
        }

        return false;
    }
}
