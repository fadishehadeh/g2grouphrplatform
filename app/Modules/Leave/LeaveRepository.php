<?php
// v2.1 - Fixed parameter binding HY093 errors (2026-06-02)
declare(strict_types=1);

namespace App\Modules\Leave;

use App\Core\Database;
use App\Modules\Notifications\NotificationRepository;

final class LeaveRepository
{
    private const ANNUAL_LEAVE_CODE = 'annual_leave';
    private const ANNUAL_FULL_ENTITLEMENT = 22.0;
    private const ANNUAL_MONTHLY_ACCRUAL = 1.8333;
    private const ANNUAL_CARRY_FORWARD_CAP = 5.0;

    private Database $database;
    private NotificationRepository $notifications;

    public function __construct(Database $database)
    {
        $this->database = $database;
        $this->notifications = new NotificationRepository($database);
    }

    public function balances(int $employeeId, int $year): array
    {
        $rows = $this->database->fetchAll(
            'SELECT lt.name AS leave_type_name, lt.code AS leave_type_code,
                    lb.opening_balance, lb.accrued, lb.used_amount, lb.adjusted_amount, lb.carry_forward_amount, lb.closing_balance, lb.balance_year
             FROM leave_balances lb
             INNER JOIN leave_types lt ON lt.id = lb.leave_type_id
             WHERE lb.employee_id = :employee_id AND lb.balance_year = :balance_year
             ORDER BY lt.name ASC',
            ['employee_id' => $employeeId, 'balance_year' => $year]
        );

        return array_map(fn (array $row): array => $this->hydrateBalanceRow($row), $rows);
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

    public function employeeLeaveHistory(int $employeeId): array
    {
        return $this->database->fetchAll(
            'SELECT lr.id, lr.employee_id, lr.leave_type_id, lr.start_date, lr.end_date, lr.start_session, lr.end_session,
                    lr.days_requested, lr.reason, lr.status, lr.submitted_at, lr.decided_at, lr.created_at, lr.updated_at,
                    lt.name AS leave_type_name, lt.code AS leave_type_code, lt.requires_attachment, lt.requires_balance,
                    (
                        SELECT COUNT(*)
                        FROM leave_request_attachments lra
                        WHERE lra.leave_request_id = lr.id
                    ) AS attachment_count
             FROM leave_requests lr
             INNER JOIN leave_types lt ON lt.id = lr.leave_type_id
             WHERE lr.employee_id = :employee_id
             ORDER BY lr.start_date DESC, lr.created_at DESC, lr.id DESC',
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

    public function employeeLeaveProfile(int $employeeId): ?array
    {
        return $this->database->fetch(
            "SELECT e.id, e.employee_code, e.work_email, e.personal_email, e.employee_status, e.joining_date,
                    CONCAT_WS(' ', e.first_name, e.middle_name, e.last_name) AS employee_name,
                    c.name AS company_name, b.name AS branch_name, d.name AS department_name,
                    t.name AS team_name, jt.name AS job_title_name,
                    CONCAT_WS(' ', m.first_name, m.middle_name, m.last_name) AS manager_name
             FROM employees e
             INNER JOIN companies c ON c.id = e.company_id
             LEFT JOIN branches b ON b.id = e.branch_id
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN teams t ON t.id = e.team_id
             LEFT JOIN job_titles jt ON jt.id = e.job_title_id
             LEFT JOIN employees m ON m.id = e.manager_employee_id
             WHERE e.id = :id
             LIMIT 1",
            ['id' => $employeeId]
        );
    }

    public function currentBalance(int $employeeId, int $leaveTypeId, int $year): float
    {
        $leaveType = $this->findLeaveType($leaveTypeId);
        if ($leaveType !== null && (string) ($leaveType['code'] ?? '') === self::ANNUAL_LEAVE_CODE) {
            $this->ensureAnnualBalanceExists($employeeId, $year);
        }

        $balance = $this->database->fetch(
            'SELECT lb.opening_balance, lb.accrued, lb.used_amount, lb.adjusted_amount, lb.carry_forward_amount,
                    lb.closing_balance, lt.code AS leave_type_code
             FROM leave_balances lb
             INNER JOIN leave_types lt ON lt.id = lb.leave_type_id
             WHERE employee_id = :employee_id AND leave_type_id = :leave_type_id AND balance_year = :balance_year
             LIMIT 1',
            [
                'employee_id' => $employeeId,
                'leave_type_id' => $leaveTypeId,
                'balance_year' => $year,
            ]
        );

        if ($balance === null) {
            return 0.0;
        }

        return $this->calculateEffectiveClosingBalance($balance, $year);
    }

    public function createLeaveRequest(array $data, int $employeeId, ?int $actorUserId): int
    {
        return $this->database->transaction(function (Database $database) use ($data, $employeeId, $actorUserId): int {
            $employee = $this->employeeContext($employeeId);
            $leaveType = $this->findLeaveType((int) $data['leave_type_id']);

            if ($employee === null || $leaveType === null) {
                throw new \RuntimeException('Invalid leave request context.');
            }

            $managerEmployeeId = !empty($employee['manager_employee_id']) ? (int) $employee['manager_employee_id'] : null;
            $managerUserId = $managerEmployeeId !== null && !empty($employee['manager_user_id'])
                ? (int) $employee['manager_user_id']
                : null;
            $hasDirectManager = $managerEmployeeId !== null && $managerUserId !== null;
            $submittedAt = date('Y-m-d H:i:s');
            $initialStatus = $hasDirectManager ? 'pending_manager' : 'pending_hr';

            $database->execute(
                'INSERT INTO leave_requests (
                    employee_id, leave_type_id, workflow_id, start_date, end_date, start_session, end_session,
                    days_requested, reason, status, current_step_order, submitted_at, replacement_employee_id
                 ) VALUES (
                    :employee_id, :leave_type_id, :workflow_id, :start_date, :end_date, :start_session, :end_session,
                    :days_requested, :reason, :status, :current_step_order, :submitted_at, :replacement_employee_id
                 )',
                [
                    'employee_id'            => $employeeId,
                    'leave_type_id'          => (int) $data['leave_type_id'],
                    'workflow_id'            => null,
                    'start_date'             => (string) $data['start_date'],
                    'end_date'               => (string) $data['end_date'],
                    'start_session'          => (string) $data['start_session'],
                    'end_session'            => (string) $data['end_session'],
                    'days_requested'         => (float) $data['days_requested'],
                    'reason'                 => (string) $data['reason'],
                    'status'                 => $initialStatus,
                    'current_step_order'     => 1,
                    'submitted_at'           => $submittedAt,
                    'replacement_employee_id'=> isset($data['replacement_employee_id']) ? (int) $data['replacement_employee_id'] : null,
                ]
            );

            $requestId = (int) $database->lastInsertId();

            if ($hasDirectManager) {
                $database->execute(
                    'INSERT INTO leave_approvals (leave_request_id, step_order, approver_user_id, approver_role_id, decision)
                     VALUES (:leave_request_id, :step_order, :approver_user_id, :approver_role_id, :decision)',
                    [
                        'leave_request_id' => $requestId,
                        'step_order' => 1,
                        'approver_user_id' => $managerUserId,
                        'approver_role_id' => null,
                        'decision' => 'pending',
                    ]
                );
            } else {
                $database->execute(
                    'INSERT INTO leave_approvals (leave_request_id, step_order, approver_user_id, approver_role_id, decision)
                     VALUES (:leave_request_id, :step_order, :approver_user_id, :approver_role_id, :decision)',
                    [
                        'leave_request_id' => $requestId,
                        'step_order' => 1,
                        'approver_user_id' => null,
                        'approver_role_id' => $this->hrAdminRoleId(),
                        'decision' => 'pending',
                    ]
                );
            }

            if (!empty($data['attachment_meta']) && is_array($data['attachment_meta'])) {
                $this->storeLeaveAttachment($database, $requestId, $data['attachment_meta'], $actorUserId);
            }

            // --- Notifications ---
            $this->notifyLeaveRequestStakeholders($employee, $requestId, (float) $data['days_requested'], (string) $data['start_date'], (string) $data['end_date']);

            // Notify replacement employee if assigned
            if (isset($data['replacement_employee_id']) && (int) $data['replacement_employee_id'] !== 0) {
                $this->notifyReplacementEmployee(
                    $requestId,
                    (int) $data['replacement_employee_id'],
                    $employee,
                    (float) $data['days_requested'],
                    (string) $data['start_date'],
                    (string) $data['end_date']
                );
            }

            return $requestId;
        });
    }

    public function createApprovedLeaveRequest(
        array $data,
        int $employeeId,
        ?int $actorUserId,
        ?string $approvalComments = null
    ): int {
        return $this->database->transaction(function (Database $database) use ($data, $employeeId, $actorUserId, $approvalComments): int {
            $employee = $this->employeeContext($employeeId);
            $leaveType = $this->findLeaveType((int) $data['leave_type_id']);

            if ($employee === null || $leaveType === null) {
                throw new \RuntimeException('Invalid leave request context.');
            }

            $submittedAt = date('Y-m-d H:i:s');

            $database->execute(
                'INSERT INTO leave_requests (
                    employee_id, leave_type_id, workflow_id, start_date, end_date, start_session, end_session,
                    days_requested, reason, status, current_step_order, submitted_at, replacement_employee_id
                 ) VALUES (
                    :employee_id, :leave_type_id, :workflow_id, :start_date, :end_date, :start_session, :end_session,
                    :days_requested, :reason, :status, :current_step_order, :submitted_at, :replacement_employee_id
                 )',
                [
                    'employee_id' => $employeeId,
                    'leave_type_id' => (int) $data['leave_type_id'],
                    'workflow_id' => null,
                    'start_date' => (string) $data['start_date'],
                    'end_date' => (string) $data['end_date'],
                    'start_session' => (string) ($data['start_session'] ?? 'full'),
                    'end_session' => (string) ($data['end_session'] ?? 'full'),
                    'days_requested' => (float) $data['days_requested'],
                    'reason' => (string) $data['reason'],
                    'status' => 'approved',
                    'current_step_order' => 1,
                    'submitted_at' => $submittedAt,
                    'replacement_employee_id' => isset($data['replacement_employee_id']) && (int) $data['replacement_employee_id'] > 0
                        ? (int) $data['replacement_employee_id']
                        : null,
                ]
            );

            $requestId = (int) $database->lastInsertId();

            $database->execute(
                'INSERT INTO leave_approvals (leave_request_id, step_order, approver_user_id, approver_role_id, decision, comments, acted_at)
                 VALUES (:leave_request_id, :step_order, :approver_user_id, :approver_role_id, :decision, :comments, :acted_at)',
                [
                    'leave_request_id' => $requestId,
                    'step_order' => 1,
                    'approver_user_id' => $actorUserId,
                    'approver_role_id' => null,
                    'decision' => 'approved',
                    'comments' => $this->nullableString($approvalComments),
                    'acted_at' => $submittedAt,
                ]
            );

            if (!empty($data['attachment_meta']) && is_array($data['attachment_meta'])) {
                $this->storeLeaveAttachment($database, $requestId, $data['attachment_meta'], $actorUserId);
            }

            $this->finalizeApproval(
                $database,
                [
                    'id' => $requestId,
                    'employee_id' => $employeeId,
                    'leave_type_id' => (int) $data['leave_type_id'],
                    'days_requested' => (float) $data['days_requested'],
                    'start_date' => (string) $data['start_date'],
                    'requires_balance' => (int) ($leaveType['requires_balance'] ?? 0),
                ]
            );

            $this->notifyLeaveDecision($employeeId, $requestId, 'approved');

            return $requestId;
        });
    }

    public function updateApprovedLeaveRequest(
        int $requestId,
        array $data,
        ?int $actorUserId,
        ?string $approvalComments = null,
        ?array $attachmentMeta = null
    ): void {
        $this->database->transaction(function (Database $database) use ($requestId, $data, $actorUserId, $approvalComments, $attachmentMeta): void {
            $existing = $this->findApprovedRequestForAdminEdit($requestId);
            if ($existing === null) {
                throw new \RuntimeException('Approved leave request not found.');
            }

            $leaveType = $this->findLeaveType((int) $data['leave_type_id']);
            if ($leaveType === null) {
                throw new \RuntimeException('Selected leave type could not be found.');
            }

            $this->applyApprovedLeaveBalanceDelta(
                $database,
                (int) $existing['employee_id'],
                (int) $existing['leave_type_id'],
                (int) date('Y', strtotime((string) $existing['start_date'])),
                -1 * (float) $existing['days_requested'],
                (int) ($existing['requires_balance'] ?? 0) === 1
            );

            $database->execute(
                'UPDATE leave_requests
                 SET leave_type_id = :leave_type_id,
                     start_date = :start_date,
                     end_date = :end_date,
                     start_session = :start_session,
                     end_session = :end_session,
                     days_requested = :days_requested,
                     reason = :reason,
                     updated_at = :updated_at
                 WHERE id = :id',
                [
                    'id' => $requestId,
                    'leave_type_id' => (int) $data['leave_type_id'],
                    'start_date' => (string) $data['start_date'],
                    'end_date' => (string) $data['end_date'],
                    'start_session' => (string) ($data['start_session'] ?? 'full'),
                    'end_session' => (string) ($data['end_session'] ?? 'full'),
                    'days_requested' => (float) $data['days_requested'],
                    'reason' => (string) $data['reason'],
                    'updated_at' => date('Y-m-d H:i:s'),
                ]
            );

            if ($attachmentMeta !== null) {
                $this->storeLeaveAttachment($database, $requestId, $attachmentMeta, $actorUserId);
            }

            $this->applyApprovedLeaveBalanceDelta(
                $database,
                (int) $existing['employee_id'],
                (int) $data['leave_type_id'],
                (int) date('Y', strtotime((string) $data['start_date'])),
                (float) $data['days_requested'],
                (int) ($leaveType['requires_balance'] ?? 0) === 1
            );

            if ($approvalComments !== null && trim($approvalComments) !== '') {
                $approvalId = $database->fetchValue(
                    "SELECT id
                     FROM leave_approvals
                     WHERE leave_request_id = :leave_request_id AND decision = 'approved'
                     ORDER BY step_order ASC, id ASC
                     LIMIT 1",
                    ['leave_request_id' => $requestId]
                );

                if ($approvalId !== null && $approvalId !== false) {
                    $database->execute(
                        'UPDATE leave_approvals
                         SET approver_user_id = :approver_user_id,
                             comments = :comments,
                             acted_at = :acted_at
                         WHERE id = :id',
                        [
                            'id' => (int) $approvalId,
                            'approver_user_id' => $actorUserId,
                            'comments' => $this->nullableString($approvalComments),
                            'acted_at' => date('Y-m-d H:i:s'),
                        ]
                    );
                }
            }
        });
    }

    public function managerPendingRequests(int $managerEmployeeId): array
    {
        $requests = $this->database->fetchAll(
            "SELECT lr.id, lr.current_step_order, e.employee_code, CONCAT_WS(' ', e.first_name, e.middle_name, e.last_name) AS employee_name,
                    lt.name AS leave_type_name, lr.start_date, lr.end_date, lr.days_requested, lr.reason,
                    lr.submitted_at, lr.status
             FROM leave_requests lr
             INNER JOIN employees e ON e.id = lr.employee_id
             INNER JOIN leave_types lt ON lt.id = lr.leave_type_id
             WHERE lr.status = 'pending_manager'
             ORDER BY COALESCE(lr.submitted_at, lr.created_at) ASC, lr.start_date ASC"
        );

        // Filter to only requests where this manager has a pending approval
        $filtered = [];
        foreach ($requests as $req) {
            $approval = $this->database->fetch(
                'SELECT step_order FROM leave_approvals
                 WHERE leave_request_id = :leave_request_id
                   AND approver_user_id = (SELECT user_id FROM employees WHERE id = :manager_employee_id)
                   AND decision = :decision
                 LIMIT 1',
                [
                    'leave_request_id' => (int) $req['id'],
                    'manager_employee_id' => $managerEmployeeId,
                    'decision' => 'pending',
                ]
            );

            if ($approval !== null) {
                $req['manager_level'] = 'Review';
                $req['step_order'] = 1;
                $filtered[] = $req;
            }
        }

        return $filtered;
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
        $employee = $this->database->fetch(
            "SELECT lr.id, lr.employee_id, lr.leave_type_id, lr.days_requested, lr.start_date,
                    lt.requires_balance,
                    e.manager_employee_id,
                    CONCAT_WS(' ', e.first_name, e.middle_name, e.last_name) AS employee_name
             FROM leave_requests lr
             INNER JOIN employees e ON e.id = lr.employee_id
             INNER JOIN leave_types lt ON lt.id = lr.leave_type_id
             WHERE lr.id = :id AND lr.status = 'pending_manager'
             LIMIT 1",
            ['id' => $requestId]
        );

        if ($employee === null) {
            throw new \RuntimeException('Leave request not available for manager approval.');
        }

        if ($managerEmployeeId !== (int) ($employee['manager_employee_id'] ?? 0)) {
            throw new \RuntimeException('This manager is not authorized to approve this request.');
        }

        $this->database->transaction(function (Database $database) use ($employee, $actorUserId, $comments): void {
            $requestId = (int) $employee['id'];
            $approval = $this->pendingApproval($requestId);

            if ($approval !== null) {
                $this->markApproval($database, (int) $approval['id'], 'approved', $actorUserId, $comments);
            }

            $this->finalizeApproval($database, $employee);
            $this->notifyLeaveDecision((int) $employee['employee_id'], $requestId, 'approved');
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

    public function balanceOverview(array $scope, int $year, string $search = '', string $status = 'active', int $page = 1, int $perPage = 25): array
    {
        $page = max(1, $page);
        $perPage = max(10, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        ['sql' => $sql, 'params' => $params] = $this->balanceOverviewBaseQuery($scope, $year, $search, $status);
        $sql .= ' GROUP BY e.id, e.employee_code, e.work_email, e.employee_status, e.joining_date, employee_name, c.name, d.name
                  ORDER BY employee_name ASC
                  LIMIT ' . $perPage . ' OFFSET ' . $offset;

        return $this->database->fetchAll($sql, $params);
    }

    public function countBalanceOverview(array $scope, int $year, string $search = '', string $status = 'active'): int
    {
        ['sql' => $sql, 'params' => $params] = $this->balanceOverviewBaseQuery($scope, $year, $search, $status);

        return (int) $this->database->fetchValue(
            'SELECT COUNT(*) FROM (' . $sql . ' GROUP BY e.id) balance_count',
            $params
        );
    }

    public function balanceOverviewSummary(array $scope, int $year, string $search = '', string $status = 'active'): array
    {
        ['sql' => $sql, 'params' => $params] = $this->balanceOverviewBaseQuery($scope, $year, $search, $status);
        $summary = $this->database->fetch(
            'SELECT COALESCE(SUM(balance_rows.total_balance), 0) AS available_total,
                    COALESCE(SUM(balance_rows.used_amount), 0) AS used_total
             FROM (' . $sql . '
                GROUP BY e.id, e.employee_code, e.work_email, e.employee_status, e.joining_date, employee_name, c.name, d.name
             ) balance_rows',
            $params
        );

        return [
            'employees' => 0,
            'leave_types' => 0,
            'available_total' => (float) ($summary['available_total'] ?? 0),
            'used_total' => (float) ($summary['used_total'] ?? 0),
        ];
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

    public function findApprovedRequestForAdminEdit(int $requestId): ?array
    {
        return $this->database->fetch(
            'SELECT lr.id, lr.employee_id, lr.leave_type_id, lr.start_date, lr.end_date, lr.start_session, lr.end_session,
                    lr.days_requested, lr.reason, lr.status, lr.submitted_at, lr.decided_at, lr.created_at, lr.updated_at,
                    lt.name AS leave_type_name, lt.code AS leave_type_code, lt.requires_attachment, lt.requires_balance
             FROM leave_requests lr
             INNER JOIN leave_types lt ON lt.id = lr.leave_type_id
             WHERE lr.id = :id AND lr.status = :status
             LIMIT 1',
            ['id' => $requestId, 'status' => 'approved']
        );
    }

    public function approvalTrail(int $requestId): array
    {
        $trail = $this->database->fetchAll(
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

        // Add manager level labels for manager approvals
        foreach ($trail as &$step) {
            if ($step['approver_role_name'] === null || $step['approver_role_name'] === '') {
                // No role = manager approval
                $stepOrder = (int) $step['step_order'];
                $step['manager_level'] = $stepOrder === 1 ? 'Level 1 Manager' : ($stepOrder === 2 ? 'Level 2 Manager' : null);
            }
        }

        return $trail;
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

    private function storeLeaveAttachment(Database $database, int $requestId, array $attachmentMeta, ?int $actorUserId): void
    {
        $database->execute(
            'INSERT INTO leave_request_attachments (
                leave_request_id, original_file_name, stored_file_name, file_path, mime_type, file_size, uploaded_by
             ) VALUES (
                :leave_request_id, :original_file_name, :stored_file_name, :file_path, :mime_type, :file_size, :uploaded_by
             )',
            [
                'leave_request_id' => $requestId,
                'original_file_name' => (string) ($attachmentMeta['original_file_name'] ?? ''),
                'stored_file_name' => (string) ($attachmentMeta['stored_file_name'] ?? ''),
                'file_path' => (string) ($attachmentMeta['file_path'] ?? ''),
                'mime_type' => $this->nullableString($attachmentMeta['mime_type'] ?? null),
                'file_size' => (int) ($attachmentMeta['file_size'] ?? 0),
                'uploaded_by' => $actorUserId,
            ]
        );
    }

    private function balanceOverviewBaseQuery(array $scope, int $year, string $search, string $status): array
    {
        $scopeCondition = $this->scopeCondition($scope, 'e.id', 'e.manager_employee_id');
        $sql = 'SELECT e.id AS employee_id,
                       e.employee_code,
                       e.work_email,
                       e.employee_status,
                       e.joining_date,
                       CONCAT_WS(" ", e.first_name, e.middle_name, e.last_name) AS employee_name,
                       c.name AS company_name,
                       d.name AS department_name,
                       COALESCE(SUM(lb.opening_balance + lb.accrued + lb.carry_forward_amount + lb.adjusted_amount), 0) AS total_balance,
                       COALESCE(SUM(lb.used_amount), 0) AS used_amount,
                       COALESCE(SUM(lb.closing_balance), 0) AS closing_balance
                FROM employees e
                INNER JOIN companies c ON c.id = e.company_id
                LEFT JOIN departments d ON d.id = e.department_id
                LEFT JOIN leave_balances lb
                    ON lb.employee_id = e.id
                   AND lb.balance_year = :balance_year
                WHERE e.archived_at IS NULL'
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
            )';
            $params['search_employee_code'] = $searchValue;
            $params['search_employee_name'] = $searchValue;
            $params['search_email'] = $searchValue;
            $params['search_company'] = $searchValue;
            $params['search_department'] = $searchValue;
        }

        return ['sql' => $sql, 'params' => $params];
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

    public function updateLeaveType(int $id, string $name, string $code, string $status): void
    {
        $this->database->execute(
            'UPDATE leave_types SET name = :name, code = :code, status = :status WHERE id = :id',
            ['name' => $name, 'code' => $code, 'status' => $status, 'id' => $id]
        );
    }

    public function nextLeaveTypeCode(): string
    {
        $rows = $this->database->fetchAll("SELECT code FROM leave_types WHERE code LIKE 'LV-%'");
        $max  = 0;
        foreach ($rows as $row) {
            if (preg_match('/([0-9]+)$/', (string) $row['code'], $m)) {
                $max = max($max, (int) $m[1]);
            }
        }
        return 'LV-' . str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
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

    public function seedDefaultLeaveTypes(): array
    {
        $defaults = [
            [
                'name' => 'Annual Leave',
                'code' => self::ANNUAL_LEAVE_CODE,
                'description' => 'Standard annual leave entitlement.',
                'is_paid' => 1,
                'requires_balance' => 1,
                'requires_attachment' => 0,
                'requires_hr_approval' => 0,
                'allow_half_day' => 1,
                'default_days' => self::ANNUAL_FULL_ENTITLEMENT,
                'carry_forward_allowed' => 1,
                'carry_forward_limit' => self::ANNUAL_CARRY_FORWARD_CAP,
                'notice_days_required' => 0,
                'max_days_per_request' => null,
                'status' => 'active',
            ],
            [
                'name' => 'Sick Leave',
                'code' => 'sick_leave',
                'description' => 'Short-term illness and recovery leave.',
                'is_paid' => 1,
                'requires_balance' => 1,
                'requires_attachment' => 1,
                'requires_hr_approval' => 0,
                'allow_half_day' => 0,
                'default_days' => 10,
                'carry_forward_allowed' => 0,
                'carry_forward_limit' => 0,
                'notice_days_required' => 0,
                'max_days_per_request' => null,
                'status' => 'active',
            ],
            [
                'name' => 'Emergency Leave',
                'code' => 'emergency_leave',
                'description' => 'Urgent personal emergency leave.',
                'is_paid' => 1,
                'requires_balance' => 1,
                'requires_attachment' => 0,
                'requires_hr_approval' => 0,
                'allow_half_day' => 1,
                'default_days' => 3,
                'carry_forward_allowed' => 0,
                'carry_forward_limit' => 0,
                'notice_days_required' => 0,
                'max_days_per_request' => null,
                'status' => 'active',
            ],
            [
                'name' => 'Unpaid Leave',
                'code' => 'unpaid_leave',
                'description' => 'Approved unpaid leave time.',
                'is_paid' => 0,
                'requires_balance' => 1,
                'requires_attachment' => 0,
                'requires_hr_approval' => 0,
                'allow_half_day' => 1,
                'default_days' => 30,
                'carry_forward_allowed' => 0,
                'carry_forward_limit' => 0,
                'notice_days_required' => 0,
                'max_days_per_request' => null,
                'status' => 'active',
            ],
            [
                'name' => 'Maternity Leave',
                'code' => 'maternity_leave',
                'description' => 'Maternity leave entitlement.',
                'is_paid' => 1,
                'requires_balance' => 1,
                'requires_attachment' => 0,
                'requires_hr_approval' => 0,
                'allow_half_day' => 0,
                'default_days' => 60,
                'carry_forward_allowed' => 0,
                'carry_forward_limit' => 0,
                'notice_days_required' => 0,
                'max_days_per_request' => null,
                'status' => 'active',
            ],
        ];

        $created = 0;
        $skipped = 0;

        foreach ($defaults as $definition) {
            $existingId = $this->database->fetchValue(
                'SELECT id FROM leave_types WHERE LOWER(code) = :code OR LOWER(name) = :name LIMIT 1',
                [
                    'code' => strtolower((string) $definition['code']),
                    'name' => strtolower((string) $definition['name']),
                ]
            );

            if ($existingId !== null && $existingId !== false) {
                $skipped++;
                continue;
            }

            $this->createLeaveType($definition);
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
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
            "SELECT id, joining_date, CONCAT_WS(' ', first_name, middle_name, last_name) AS employee_name, employee_code
             FROM employees
             WHERE employee_status = 'active' AND archived_at IS NULL
             ORDER BY first_name ASC, last_name ASC"
        );
    }

    public function saveBalance(
        int $employeeId,
        int $leaveTypeId,
        int $year,
        float $openingBalance,
        float $accrued,
        float $usedAmount,
        float $adjustedAmount
    ): void
    {
        if ($openingBalance < 0 || $accrued < 0 || $usedAmount < 0) {
            throw new \InvalidArgumentException('Opening, accrued, and used values cannot be negative.');
        }

        $existing = $this->database->fetch(
            'SELECT lb.id, lb.carry_forward_amount, lt.code AS leave_type_code
             FROM leave_balances lb
             INNER JOIN leave_types lt ON lt.id = lb.leave_type_id
             WHERE employee_id = :eid AND leave_type_id = :ltid AND balance_year = :year LIMIT 1',
            ['eid' => $employeeId, 'ltid' => $leaveTypeId, 'year' => $year]
        );

        $carryForwardAmount = (float) ($existing['carry_forward_amount'] ?? 0);
        $leaveTypeCode = (string) ($existing['leave_type_code'] ?? '');
        if ($existing === null) {
            $leaveType = $this->findLeaveType($leaveTypeId);
            $leaveTypeCode = (string) ($leaveType['code'] ?? '');
        }

        $closing = $this->computeClosingBalance(
            $openingBalance,
            $accrued,
            $usedAmount,
            $adjustedAmount,
            $carryForwardAmount,
            $year,
            $leaveTypeCode
        );

        if ($existing === null) {
            $this->database->execute(
                'INSERT INTO leave_balances (employee_id, leave_type_id, balance_year, opening_balance, accrued, used_amount, adjusted_amount, carry_forward_amount, closing_balance)
                 VALUES (:eid, :ltid, :year, :opening, :accrued, :used, :adjusted, :carry_forward, :closing)',
                ['eid' => $employeeId, 'ltid' => $leaveTypeId, 'year' => $year,
                 'opening' => $openingBalance, 'accrued' => $accrued, 'used' => $usedAmount,
                 'adjusted' => $adjustedAmount, 'carry_forward' => $carryForwardAmount, 'closing' => $closing]
            );
        } else {
            $this->database->execute(
                'UPDATE leave_balances
                 SET opening_balance = :opening,
                     accrued = :accrued,
                     used_amount = :used,
                     adjusted_amount = :adjusted,
                     closing_balance = :closing
                 WHERE employee_id = :eid AND leave_type_id = :ltid AND balance_year = :year',
                ['opening' => $openingBalance, 'accrued' => $accrued, 'used' => $usedAmount, 'adjusted' => $adjustedAmount, 'closing' => $closing,
                 'eid' => $employeeId, 'ltid' => $leaveTypeId, 'year' => $year]
            );
        }
    }

    public function bulkAssignBalances(int $year): int
    {
        $employees = $this->activeEmployeesForBalance();
        $leaveTypes = $this->database->fetchAll(
            "SELECT id, default_days, code FROM leave_types WHERE status = 'active' AND code <> 'annual_leave'"
        );
        $count = 0;

        foreach ($employees as $employee) {
            foreach ($leaveTypes as $lt) {
                $days = (float) ($lt['default_days'] ?? 0);
                $existing = $this->database->fetch(
                    'SELECT id, opening_balance FROM leave_balances WHERE employee_id = :eid AND leave_type_id = :ltid AND balance_year = :year LIMIT 1',
                    ['eid' => $employee['id'], 'ltid' => $lt['id'], 'year' => $year]
                );

                if ($existing === null) {
                    // Create new balance record
                    $this->database->execute(
                        'INSERT INTO leave_balances (employee_id, leave_type_id, balance_year, opening_balance, accrued, used_amount, adjusted_amount, carry_forward_amount, closing_balance)
                         VALUES (:eid, :ltid, :year, :opening, :zero1, :zero2, :zero3, :carry_forward, :closing)',
                        ['eid' => $employee['id'], 'ltid' => $lt['id'], 'year' => $year, 'opening' => $days, 'zero1' => 0, 'zero2' => 0, 'zero3' => 0, 'carry_forward' => 0, 'closing' => $days]
                    );
                    $count++;
                } else {
                    // Update existing balance if opening_balance differs (leave type default_days might have changed)
                    $currentOpening = (float) $existing['opening_balance'];
                    if ($currentOpening !== $days) {
                        $difference = $days - $currentOpening;
                        $this->database->execute(
                            'UPDATE leave_balances
                             SET opening_balance = :days,
                                 closing_balance = closing_balance + :difference
                             WHERE employee_id = :eid AND leave_type_id = :ltid AND balance_year = :year',
                            ['eid' => $employee['id'], 'ltid' => $lt['id'], 'year' => $year, 'days' => $days, 'difference' => $difference]
                        );
                        $count++;
                    }
                }
            }
        }

        return $count;
    }

    public function recalculateAnnualLeaveBalances(int $year, ?int $actorUserId = null): array
    {
        $annualLeaveType = $this->annualLeaveType();
        if ($annualLeaveType === null) {
            throw new \RuntimeException('Annual leave type with code "annual_leave" was not found.');
        }

        $employees = $this->activeEmployeesForBalance();
        $created = 0;
        $updated = 0;
        $carryForwardLimit = (float) ($annualLeaveType['carry_forward_limit'] ?? self::ANNUAL_CARRY_FORWARD_CAP);
        if ($carryForwardLimit <= 0) {
            $carryForwardLimit = self::ANNUAL_CARRY_FORWARD_CAP;
        }

        foreach ($employees as $employee) {
            $result = $this->upsertAnnualBalanceForEmployee(
                $employee,
                $year,
                $annualLeaveType,
                min(self::ANNUAL_CARRY_FORWARD_CAP, $carryForwardLimit)
            );
            if ($result === 'created') {
                $created++;
            } else {
                $updated++;
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
        ];
    }

    public function importAnnualUsedDays(int $year, array $rows, ?int $actorUserId = null): array
    {
        $annualLeaveType = $this->annualLeaveType();
        if ($annualLeaveType === null) {
            throw new \RuntimeException('Annual leave type with code "annual_leave" was not found.');
        }

        $updated = 0;
        $errors = [];

        foreach ($rows as $row) {
            $rowNumber = (int) ($row['row_number'] ?? 0);
            $employeeCode = strtoupper(trim((string) ($row['employee_code'] ?? '')));
            $employeeName = trim((string) ($row['employee_name'] ?? ''));
            $usedDays = (float) ($row['used_days'] ?? 0);

            try {
                $employee = null;

                if ($employeeCode !== '') {
                    $employee = $this->database->fetch(
                        'SELECT id, employee_code, joining_date
                         FROM employees
                         WHERE UPPER(employee_code) = :employee_code
                         LIMIT 1',
                        ['employee_code' => $employeeCode]
                    );
                }

                if ($employee === null && $employeeName !== '') {
                    $matches = $this->database->fetchAll(
                        'SELECT id, employee_code, joining_date
                         FROM employees
                         WHERE archived_at IS NULL
                           AND TRIM(CONCAT_WS(" ", first_name, middle_name, last_name)) = :employee_name
                         ORDER BY id ASC',
                        ['employee_name' => $employeeName]
                    );

                    if (count($matches) > 1) {
                        throw new \RuntimeException("Employee name \"{$employeeName}\" matched multiple employees. Please import by employee_code for this row.");
                    }

                    $employee = $matches[0] ?? null;
                }

                if ($employee === null) {
                    if ($employeeCode !== '') {
                        throw new \RuntimeException("Employee code \"{$employeeCode}\" was not found.");
                    }

                    throw new \RuntimeException("Employee name \"{$employeeName}\" was not found.");
                }

                $employeeRecord = $this->employeeForAnnualBalance((int) $employee['id']);
                if ($employeeRecord === null) {
                    throw new \RuntimeException('Employee was not found or is archived.');
                }

                $carryForwardLimit = (float) ($annualLeaveType['carry_forward_limit'] ?? self::ANNUAL_CARRY_FORWARD_CAP);
                if ($carryForwardLimit <= 0) {
                    $carryForwardLimit = self::ANNUAL_CARRY_FORWARD_CAP;
                }

                $this->upsertAnnualBalanceForEmployee(
                    $employeeRecord,
                    $year,
                    $annualLeaveType,
                    min(self::ANNUAL_CARRY_FORWARD_CAP, $carryForwardLimit)
                );

                $existing = $this->database->fetch(
                    'SELECT opening_balance, accrued, adjusted_amount, carry_forward_amount
                     FROM leave_balances
                     WHERE employee_id = :employee_id AND leave_type_id = :leave_type_id AND balance_year = :balance_year
                     LIMIT 1',
                    [
                        'employee_id' => (int) $employee['id'],
                        'leave_type_id' => (int) $annualLeaveType['id'],
                        'balance_year' => $year,
                    ]
                );

                if ($existing === null) {
                    throw new \RuntimeException('Annual leave balance could not be prepared for this employee.');
                }

                $closingBalance = $this->computeClosingBalance(
                    (float) $existing['opening_balance'],
                    (float) $existing['accrued'],
                    $usedDays,
                    (float) $existing['adjusted_amount'],
                    (float) $existing['carry_forward_amount'],
                    $year,
                    self::ANNUAL_LEAVE_CODE
                );

                $this->database->execute(
                    'UPDATE leave_balances
                     SET used_amount = :used_amount,
                         closing_balance = :closing_balance
                     WHERE employee_id = :employee_id AND leave_type_id = :leave_type_id AND balance_year = :balance_year',
                    [
                        'used_amount' => $usedDays,
                        'closing_balance' => $closingBalance,
                        'employee_id' => (int) $employee['id'],
                        'leave_type_id' => (int) $annualLeaveType['id'],
                        'balance_year' => $year,
                    ]
                );
                $updated++;
            } catch (\Throwable $throwable) {
                $errors[] = "Row {$rowNumber}: " . $throwable->getMessage();
            }
        }

        return [
            'updated' => $updated,
            'errors' => $errors,
        ];
    }

    public function importAnnualUsedDaysByEmployeeId(int $year, array $rows, ?int $actorUserId = null): array
    {
        $annualLeaveType = $this->annualLeaveType();
        if ($annualLeaveType === null) {
            throw new \RuntimeException('Annual leave type with code "annual_leave" was not found.');
        }

        $updated = 0;
        $errors = [];

        foreach ($rows as $row) {
            $rowNumber = (int) ($row['row_number'] ?? 0);
            $employeeId = (int) ($row['employee_id'] ?? 0);
            $usedDays = (float) ($row['used_days'] ?? 0);

            try {
                if ($employeeId <= 0) {
                    throw new \RuntimeException('No employee was selected for this row.');
                }

                $employee = $this->employeeForAnnualBalance($employeeId);
                if ($employee === null) {
                    throw new \RuntimeException('Employee was not found or is archived.');
                }

                $carryForwardLimit = (float) ($annualLeaveType['carry_forward_limit'] ?? self::ANNUAL_CARRY_FORWARD_CAP);
                if ($carryForwardLimit <= 0) {
                    $carryForwardLimit = self::ANNUAL_CARRY_FORWARD_CAP;
                }

                $this->upsertAnnualBalanceForEmployee(
                    $employee,
                    $year,
                    $annualLeaveType,
                    min(self::ANNUAL_CARRY_FORWARD_CAP, $carryForwardLimit)
                );

                $existing = $this->database->fetch(
                    'SELECT opening_balance, accrued, adjusted_amount, carry_forward_amount
                     FROM leave_balances
                     WHERE employee_id = :employee_id AND leave_type_id = :leave_type_id AND balance_year = :balance_year
                     LIMIT 1',
                    [
                        'employee_id' => $employeeId,
                        'leave_type_id' => (int) $annualLeaveType['id'],
                        'balance_year' => $year,
                    ]
                );

                if ($existing === null) {
                    throw new \RuntimeException('Annual leave balance could not be prepared for this employee.');
                }

                $closingBalance = $this->computeClosingBalance(
                    (float) $existing['opening_balance'],
                    (float) $existing['accrued'],
                    $usedDays,
                    (float) $existing['adjusted_amount'],
                    (float) $existing['carry_forward_amount'],
                    $year,
                    self::ANNUAL_LEAVE_CODE
                );

                $this->database->execute(
                    'UPDATE leave_balances
                     SET used_amount = :used_amount,
                         closing_balance = :closing_balance
                     WHERE employee_id = :employee_id AND leave_type_id = :leave_type_id AND balance_year = :balance_year',
                    [
                        'used_amount' => $usedDays,
                        'closing_balance' => $closingBalance,
                        'employee_id' => $employeeId,
                        'leave_type_id' => (int) $annualLeaveType['id'],
                        'balance_year' => $year,
                    ]
                );
                $updated++;
            } catch (\Throwable $throwable) {
                $errors[] = "Row {$rowNumber}: " . $throwable->getMessage();
            }
        }

        return [
            'updated' => $updated,
            'errors' => $errors,
        ];
    }

    public function leaveImportEmployeeSummariesByName(string $employeeName): array
    {
        $employeeName = trim($employeeName);
        if ($employeeName === '') {
            return [];
        }

        return $this->database->fetchAll(
            'SELECT id, employee_code,
                    TRIM(CONCAT_WS(" ", first_name, middle_name, last_name)) AS employee_name,
                    joining_date
             FROM employees
             WHERE archived_at IS NULL
               AND TRIM(CONCAT_WS(" ", first_name, middle_name, last_name)) = :employee_name
             ORDER BY employee_code ASC, id ASC',
            ['employee_name' => $employeeName]
        );
    }

    private function annualLeaveType(): ?array
    {
        return $this->database->fetch(
            'SELECT id, code, carry_forward_allowed, carry_forward_limit
             FROM leave_types
             WHERE code = :code
             LIMIT 1',
            ['code' => self::ANNUAL_LEAVE_CODE]
        );
    }

    private function annualEntitlementForEmployee(array $employee, int $year): array
    {
        $joiningDateRaw = trim((string) ($employee['joining_date'] ?? ''));
        $yearStart = new \DateTimeImmutable(sprintf('%04d-01-01', $year));
        $yearEnd = new \DateTimeImmutable(sprintf('%04d-12-31', $year));

        if ($joiningDateRaw === '') {
            return [
                'opening_balance' => round(self::ANNUAL_FULL_ENTITLEMENT, 2),
                'accrued' => 0.0,
            ];
        }

        $joiningDate = \DateTimeImmutable::createFromFormat('Y-m-d', $joiningDateRaw);
        if (!$joiningDate instanceof \DateTimeImmutable || $joiningDate < $yearStart) {
            return [
                'opening_balance' => round(self::ANNUAL_FULL_ENTITLEMENT, 2),
                'accrued' => 0.0,
            ];
        }

        if ($joiningDate > $yearEnd) {
            return [
                'opening_balance' => 0.0,
                'accrued' => 0.0,
            ];
        }

        $eligibleStart = $joiningDate->modify('first day of next month');
        $eligibleMonths = 0;

        if ((int) $eligibleStart->format('Y') === $year) {
            $startMonth = (int) $eligibleStart->format('n');
            $eligibleMonths = max(0, 12 - $startMonth + 1);
        }

        $accrued = min(self::ANNUAL_FULL_ENTITLEMENT, $eligibleMonths * self::ANNUAL_MONTHLY_ACCRUAL);

        return [
            'opening_balance' => 0.0,
            'accrued' => round($accrued, 2),
        ];
    }

    private function annualCarryForwardForEmployee(int $employeeId, int $leaveTypeId, int $year, float $limit): float
    {
        if ($year <= 0) {
            return 0.0;
        }

        $previous = $this->database->fetch(
            'SELECT closing_balance
             FROM leave_balances
             WHERE employee_id = :employee_id AND leave_type_id = :leave_type_id AND balance_year = :balance_year
             LIMIT 1',
            [
                'employee_id' => $employeeId,
                'leave_type_id' => $leaveTypeId,
                'balance_year' => $year - 1,
            ]
        );

        if ($previous === null) {
            return 0.0;
        }

        return round(min($limit, max(0.0, (float) ($previous['closing_balance'] ?? 0))), 2);
    }

    private function ensureAnnualBalanceExists(int $employeeId, int $year, ?int $actorUserId = null): void
    {
        $annualLeaveType = $this->annualLeaveType();
        if ($annualLeaveType === null) {
            throw new \RuntimeException('Annual leave type with code "annual_leave" was not found.');
        }

        $existing = $this->database->fetchValue(
            'SELECT id
             FROM leave_balances
             WHERE employee_id = :employee_id AND leave_type_id = :leave_type_id AND balance_year = :balance_year
             LIMIT 1',
            [
                'employee_id' => $employeeId,
                'leave_type_id' => (int) $annualLeaveType['id'],
                'balance_year' => $year,
            ]
        );

        if ($existing !== null) {
            return;
        }

        $employee = $this->employeeForAnnualBalance($employeeId);

        if ($employee === null || ($employee['archived_at'] ?? null) !== null) {
            throw new \RuntimeException('Employee was not found or is archived.');
        }

        $carryForwardLimit = (float) ($annualLeaveType['carry_forward_limit'] ?? self::ANNUAL_CARRY_FORWARD_CAP);
        if ($carryForwardLimit <= 0) {
            $carryForwardLimit = self::ANNUAL_CARRY_FORWARD_CAP;
        }

        $this->upsertAnnualBalanceForEmployee(
            $employee,
            $year,
            $annualLeaveType,
            min(self::ANNUAL_CARRY_FORWARD_CAP, $carryForwardLimit)
        );
    }

    private function employeeForAnnualBalance(int $employeeId): ?array
    {
        return $this->database->fetch(
            'SELECT id, joining_date, employee_status, archived_at,
                    CONCAT_WS(" ", first_name, middle_name, last_name) AS employee_name,
                    employee_code
             FROM employees
             WHERE id = :employee_id
             LIMIT 1',
            ['employee_id' => $employeeId]
        );
    }

    private function upsertAnnualBalanceForEmployee(array $employee, int $year, array $annualLeaveType, float $carryForwardLimit): string
    {
        $employeeId = (int) ($employee['id'] ?? 0);
        if ($employeeId <= 0) {
            throw new \RuntimeException('Invalid employee context for annual leave balance.');
        }

        $entitlement = $this->annualEntitlementForEmployee($employee, $year);
        $carryForwardAmount = $this->annualCarryForwardForEmployee(
            $employeeId,
            (int) $annualLeaveType['id'],
            $year,
            $carryForwardLimit
        );

        $existing = $this->database->fetch(
            'SELECT id, used_amount, adjusted_amount
             FROM leave_balances
             WHERE employee_id = :employee_id AND leave_type_id = :leave_type_id AND balance_year = :balance_year
             LIMIT 1',
            [
                'employee_id' => $employeeId,
                'leave_type_id' => (int) $annualLeaveType['id'],
                'balance_year' => $year,
            ]
        );

        $usedAmount = (float) ($existing['used_amount'] ?? 0);
        $adjustedAmount = (float) ($existing['adjusted_amount'] ?? 0);
        $closingBalance = $this->computeClosingBalance(
            $entitlement['opening_balance'],
            $entitlement['accrued'],
            $usedAmount,
            $adjustedAmount,
            $carryForwardAmount,
            $year,
            self::ANNUAL_LEAVE_CODE
        );

        if ($existing === null) {
            $this->database->execute(
                'INSERT INTO leave_balances (
                    employee_id, leave_type_id, balance_year, opening_balance, accrued, used_amount,
                    adjusted_amount, carry_forward_amount, closing_balance
                 ) VALUES (
                    :employee_id, :leave_type_id, :balance_year, :opening_balance, :accrued, :used_amount,
                    :adjusted_amount, :carry_forward_amount, :closing_balance
                 )',
                [
                    'employee_id' => $employeeId,
                    'leave_type_id' => (int) $annualLeaveType['id'],
                    'balance_year' => $year,
                    'opening_balance' => $entitlement['opening_balance'],
                    'accrued' => $entitlement['accrued'],
                    'used_amount' => $usedAmount,
                    'adjusted_amount' => $adjustedAmount,
                    'carry_forward_amount' => $carryForwardAmount,
                    'closing_balance' => $closingBalance,
                ]
            );

            return 'created';
        }

        $this->database->execute(
            'UPDATE leave_balances
             SET opening_balance = :opening_balance,
                 accrued = :accrued,
                 carry_forward_amount = :carry_forward_amount,
                 closing_balance = :closing_balance
             WHERE id = :id',
            [
                'id' => (int) $existing['id'],
                'opening_balance' => $entitlement['opening_balance'],
                'accrued' => $entitlement['accrued'],
                'carry_forward_amount' => $carryForwardAmount,
                'closing_balance' => $closingBalance,
            ]
        );

        return 'updated';
    }

    private function hydrateBalanceRow(array $row): array
    {
        $carryForwardAmount = round((float) ($row['carry_forward_amount'] ?? 0), 2);
        $leaveTypeCode = (string) ($row['leave_type_code'] ?? '');
        $year = (int) ($row['balance_year'] ?? date('Y'));
        $usedAmount = round((float) ($row['used_amount'] ?? 0), 2);
        $carryForwardConsumed = $leaveTypeCode === self::ANNUAL_LEAVE_CODE
            ? min($carryForwardAmount, $usedAmount)
            : 0.0;
        $carryForwardExpired = $leaveTypeCode === self::ANNUAL_LEAVE_CODE
            ? $this->expiredCarryForwardAmount($carryForwardAmount, $usedAmount, $year)
            : 0.0;
        $carryForwardAvailable = $leaveTypeCode === self::ANNUAL_LEAVE_CODE
            ? max(0.0, $carryForwardAmount - $carryForwardConsumed - $carryForwardExpired)
            : 0.0;

        $row['opening_balance'] = round((float) ($row['opening_balance'] ?? 0), 2);
        $row['accrued'] = round((float) ($row['accrued'] ?? 0), 2);
        $row['used_amount'] = $usedAmount;
        $row['adjusted_amount'] = round((float) ($row['adjusted_amount'] ?? 0), 2);
        $row['carry_forward_amount'] = $carryForwardAmount;
        $row['carry_forward_consumed'] = round($carryForwardConsumed, 2);
        $row['carry_forward_expired'] = round($carryForwardExpired, 2);
        $row['carry_forward_available'] = round($carryForwardAvailable, 2);
        $row['current_year_entitlement'] = round($row['opening_balance'] + $row['accrued'], 2);
        $row['closing_balance'] = $this->calculateEffectiveClosingBalance($row, $year);

        return $row;
    }

    private function calculateEffectiveClosingBalance(array $row, int $year): float
    {
        return $this->computeClosingBalance(
            (float) ($row['opening_balance'] ?? 0),
            (float) ($row['accrued'] ?? 0),
            (float) ($row['used_amount'] ?? 0),
            (float) ($row['adjusted_amount'] ?? 0),
            (float) ($row['carry_forward_amount'] ?? 0),
            $year,
            (string) ($row['leave_type_code'] ?? '')
        );
    }

    private function computeClosingBalance(
        float $openingBalance,
        float $accrued,
        float $usedAmount,
        float $adjustedAmount,
        float $carryForwardAmount,
        int $year,
        string $leaveTypeCode
    ): float {
        $expiredCarryForward = $leaveTypeCode === self::ANNUAL_LEAVE_CODE
            ? $this->expiredCarryForwardAmount($carryForwardAmount, $usedAmount, $year)
            : 0.0;

        return round($openingBalance + $accrued + $carryForwardAmount - $expiredCarryForward - $usedAmount + $adjustedAmount, 2);
    }

    private function expiredCarryForwardAmount(float $carryForwardAmount, float $usedAmount, int $year): float
    {
        $expiryDate = new \DateTimeImmutable(sprintf('%04d-03-31', $year));
        $today = new \DateTimeImmutable(date('Y-m-d'));

        if ($today <= $expiryDate) {
            return 0.0;
        }

        return round(max(0.0, $carryForwardAmount - min($carryForwardAmount, $usedAmount)), 2);
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
     * Get up to 2 managers for an employee from employee_reporting_lines.
     * Returns: ['level_1' => managerId, 'level_2' => managerId] (level_2 optional)
     */
    private function getEmployeeManagers(int $employeeId): array
    {
        $managers = $this->database->fetchAll(
            'SELECT manager_employee_id, priority_order
             FROM employee_reporting_lines
             WHERE employee_id = :employee_id
               AND relationship_type = :relationship_type
               AND is_active = 1
             ORDER BY priority_order ASC
             LIMIT 2',
            ['employee_id' => $employeeId, 'relationship_type' => 'line_manager']
        );

        $result = [];
        foreach ($managers as $manager) {
            $order = (int) $manager['priority_order'];
            if ($order === 1) {
                $result['level_1'] = (int) $manager['manager_employee_id'];
            } elseif ($order === 2) {
                $result['level_2'] = (int) $manager['manager_employee_id'];
            }
        }

        return $result;
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
        $roleId = $this->database->fetchValue('SELECT id FROM roles WHERE code = :code LIMIT 1', ['code' => 'hr_only']);

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
        $balance = $database->fetch(
            'SELECT lb.opening_balance, lb.accrued, lb.used_amount, lb.adjusted_amount, lb.carry_forward_amount,
                    lt.code AS leave_type_code
             FROM leave_balances lb
             INNER JOIN leave_types lt ON lt.id = lb.leave_type_id
             WHERE lb.employee_id = :employee_id AND lb.leave_type_id = :leave_type_id AND lb.balance_year = :balance_year
             LIMIT 1',
            [
                'employee_id' => (int) $request['employee_id'],
                'leave_type_id' => (int) $request['leave_type_id'],
                'balance_year' => (int) date('Y', strtotime((string) $request['start_date'])),
            ]
        );

        if ($balance === null) {
            return;
        }

        $newUsedAmount = (float) $balance['used_amount'] + $days;
        $closingBalance = $this->computeClosingBalance(
            (float) $balance['opening_balance'],
            (float) $balance['accrued'],
            $newUsedAmount,
            (float) $balance['adjusted_amount'],
            (float) ($balance['carry_forward_amount'] ?? 0),
            (int) date('Y', strtotime((string) $request['start_date'])),
            (string) ($balance['leave_type_code'] ?? '')
        );

        $database->execute(
            'UPDATE leave_balances
             SET used_amount = :used_amount,
                 closing_balance = :closing_balance
             WHERE employee_id = :employee_id AND leave_type_id = :leave_type_id AND balance_year = :balance_year',
            [
                'used_amount' => $newUsedAmount,
                'closing_balance' => $closingBalance,
                'employee_id' => (int) $request['employee_id'],
                'leave_type_id' => (int) $request['leave_type_id'],
                'balance_year' => (int) date('Y', strtotime((string) $request['start_date'])),
            ]
        );
    }

    private function applyApprovedLeaveBalanceDelta(
        Database $database,
        int $employeeId,
        int $leaveTypeId,
        int $year,
        float $daysDelta,
        bool $requiresBalance
    ): void {
        if (!$requiresBalance || abs($daysDelta) < 0.0001) {
            return;
        }

        $leaveType = $this->findLeaveType($leaveTypeId);
        if ($leaveType !== null && (string) ($leaveType['code'] ?? '') === self::ANNUAL_LEAVE_CODE) {
            $this->ensureAnnualBalanceExists($employeeId, $year);
        }

        $balance = $database->fetch(
            'SELECT lb.id, lb.opening_balance, lb.accrued, lb.used_amount, lb.adjusted_amount, lb.carry_forward_amount,
                    lt.code AS leave_type_code
             FROM leave_balances lb
             INNER JOIN leave_types lt ON lt.id = lb.leave_type_id
             WHERE lb.employee_id = :employee_id AND lb.leave_type_id = :leave_type_id AND lb.balance_year = :balance_year
             LIMIT 1',
            [
                'employee_id' => $employeeId,
                'leave_type_id' => $leaveTypeId,
                'balance_year' => $year,
            ]
        );

        if ($balance === null) {
            throw new \RuntimeException('Leave balance record was not found for the selected leave type and year.');
        }

        $newUsedAmount = round(max(0.0, (float) $balance['used_amount'] + $daysDelta), 2);
        $closingBalance = $this->computeClosingBalance(
            (float) $balance['opening_balance'],
            (float) $balance['accrued'],
            $newUsedAmount,
            (float) $balance['adjusted_amount'],
            (float) ($balance['carry_forward_amount'] ?? 0),
            $year,
            (string) ($balance['leave_type_code'] ?? '')
        );

        $database->execute(
            'UPDATE leave_balances
             SET used_amount = :used_amount,
                 closing_balance = :closing_balance
             WHERE id = :id',
            [
                'id' => (int) $balance['id'],
                'used_amount' => $newUsedAmount,
                'closing_balance' => $closingBalance,
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
        $html .= '<div style="background:#ff3d33;color:#fff;padding:18px 24px;border-radius:6px 6px 0 0;">';
        $html .= '<h2 style="margin:0;font-size:20px;">' . e($heading) . '</h2></div>';
        $html .= '<div style="padding:20px 24px;border:1px solid #e0e0e0;border-top:none;">';
        $html .= '<p style="font-size:15px;">' . $bodyIntro . '</p>';

        if ($highlightRequestId !== null) {
            $requestUrl = $appUrl . '/leave/requests/' . $highlightRequestId;
            $html .= '<p style="margin:20px 0;"><a href="' . e($requestUrl) . '" style="display:inline-block;background:#ff3d33;color:#fff;padding:11px 28px;border-radius:5px;text-decoration:none;font-weight:bold;font-size:14px;">&#128196; View &amp; Action This Request</a></p>';
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
            $html .= '<h3 style="font-size:15px;margin:20px 0 8px;border-bottom:2px solid #ff3d33;padding-bottom:4px;">Leave Balances (' . $year . ')</h3>';
            $html .= '<table style="width:100%;border-collapse:collapse;font-size:13px;border:1px solid #dee2e6;">';
            $html .= '<thead><tr style="background:#ff3d33;color:#fff;">';
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
            $html .= '<h3 style="font-size:15px;margin:20px 0 8px;border-bottom:2px solid #ff3d33;padding-bottom:4px;">Recent Leave Requests</h3>';
            $html .= '<table style="width:100%;border-collapse:collapse;font-size:13px;border:1px solid #dee2e6;">';
            $html .= '<thead><tr style="background:#ff3d33;color:#fff;">';
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
        $actionUrl = '/leave/approvals';
        $mailEnabled = (bool) config('app.mail.enabled', false);
        $recipientUserIds = $this->leaveStakeholderUserIds($employee);
        $managerRouted = !empty($employee['manager_employee_id']) && !empty($employee['manager_user_id']);
        $title = $managerRouted ? 'New Leave Request' : 'Leave Request Awaiting HR Approval';
        $message = $managerRouted
            ? "{$employeeName} submitted a leave request for {$days} day(s) ({$startDate} - {$endDate})."
            : "{$employeeName} submitted a leave request for {$days} day(s) ({$startDate} - {$endDate}) and it is waiting for HR approval.";
        $bodyText = $managerRouted
            ? '<strong>' . e($employeeName) . '</strong> submitted a new leave request for <strong>' . $days . ' day(s)</strong> from <strong>' . e($startDate) . '</strong> to <strong>' . e($endDate) . '</strong>. Please review and take action.'
            : '<strong>' . e($employeeName) . '</strong> submitted a new leave request for <strong>' . $days . ' day(s)</strong> from <strong>' . e($startDate) . '</strong> to <strong>' . e($endDate) . '</strong>. No direct manager is available, so this request was routed to HR for approval.';
        $bodyHtml = $mailEnabled
            ? $this->buildLeaveEmailHtml((int) $employee['id'], $title, $bodyText, $requestId)
            : '';

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

    /**
     * Notify HR users that a leave request has been fully approved by all required managers.
     */
    private function notifyHrOfApprovedLeave(int $employeeId, int $requestId): void
    {
        $empRow = $this->employeeContext($employeeId);
        $employeeName = $empRow !== null ? (string) ($empRow['employee_name'] ?? 'An employee') : 'An employee';
        $title = 'Leave Request Approved';
        $message = "{$employeeName}'s leave request #{$requestId} has been fully approved.";
        $actionUrl = '/leave/approvals';
        $mailEnabled = (bool) config('app.mail.enabled', false);

        $bodyHtml = $mailEnabled
            ? $this->buildLeaveEmailHtml($employeeId, $title, '<strong>' . e($employeeName) . '</strong>\'s leave request <strong>#' . $requestId . '</strong> has been <strong>fully approved</strong> by all required managers and is now registered in the system.')
            : '';

        $recipientUserIds = $this->leaveHrEscalationUserIds();

        foreach ($recipientUserIds as $userId) {
            $this->notifications->create($userId, 'leave_approved', $title, $message, 'leave_request', $requestId, $actionUrl);

            if ($mailEnabled) {
                $email = $this->notifications->userEmail($userId);
                if ($email !== null) {
                    $this->notifications->queueEmail($email, $title, $bodyHtml, $message, $userId, 'leave_request', $requestId);
                }
            }
        }

        $adminEmail = $this->leaveAdminEmail();
        if ($mailEnabled && $adminEmail !== null && !$this->recipientListContainsEmail($recipientUserIds, $adminEmail)) {
            $this->notifications->queueEmail($adminEmail, $title, $bodyHtml, $message, null, 'leave_request', $requestId);
        }
    }

    private function leaveStakeholderUserIds(array $employee): array
    {
        $managerEmployeeId = !empty($employee['manager_employee_id']) ? (int) $employee['manager_employee_id'] : null;
        $managerUserId = !empty($employee['manager_user_id']) ? (int) $employee['manager_user_id'] : null;

        if ($managerEmployeeId !== null && $managerUserId !== null) {
            return [$managerUserId];
        }

        return $this->leaveHrEscalationUserIds();
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

    public function employeeOptions(): array
    {
        return $this->database->fetchAll(
            "SELECT id, company_id, CONCAT(employee_code, ' - ', CONCAT_WS(' ', first_name, middle_name, last_name)) AS name
             FROM employees
             WHERE archived_at IS NULL AND employee_status NOT IN ('archived', 'resigned', 'terminated')
             ORDER BY first_name ASC, last_name ASC"
        );
    }

    public function findEmployee(int $employeeId): ?array
    {
        return $this->database->fetch(
            'SELECT * FROM employees WHERE id = :id LIMIT 1',
            ['id' => $employeeId]
        );
    }

    private function notifyReplacementEmployee(
        int $leaveRequestId,
        int $replacementEmployeeId,
        array $employee,
        float $days,
        string $startDate,
        string $endDate
    ): void {
        try {
            $replacementEmployee = $this->findEmployee($replacementEmployeeId);
            if ($replacementEmployee === null) {
                return;
            }

            $replacementUserId = !empty($replacementEmployee['user_id']) ? (int) $replacementEmployee['user_id'] : null;
            if ($replacementUserId === null) {
                return;
            }

            $employeeName = trim(sprintf('%s %s', (string) $employee['first_name'], (string) $employee['last_name']));
            $dateRange = sprintf('%s to %s', $startDate, $endDate);

            // Create in-app notification
            $this->notifications->create(
                $replacementUserId,
                'leave_replacement',
                'Replacement Assignment',
                sprintf(
                    'You have been assigned as replacement for %s\'s leave from %s (%s days)',
                    $employeeName,
                    $dateRange,
                    number_format($days, 1)
                ),
                'leave_request',
                $leaveRequestId,
                url('/leave/my')
            );

            // Queue email notification
            $replacementEmail = $this->notifications->userEmail($replacementUserId);
            if ($replacementEmail !== null) {
                $this->notifications->queueEmail(
                    $replacementEmail,
                    'Leave Replacement Assignment',
                    sprintf(
                        '<p>You have been assigned as replacement for <strong>%s</strong>\'s leave.</p>
                        <p><strong>Leave Details:</strong></p>
                        <ul>
                            <li>Employee: %s (%s)</li>
                            <li>Dates: %s</li>
                            <li>Duration: %s days</li>
                        </ul>',
                        e($employeeName),
                        e($employeeName),
                        e((string) $employee['employee_code']),
                        e($dateRange),
                        number_format($days, 1)
                    ),
                    null,
                    $replacementUserId,
                    'leave_request',
                    $leaveRequestId
                );
            }
        } catch (\Throwable $e) {
            // Log error but don't fail the leave request creation
            error_log('Failed to notify replacement employee: ' . $e->getMessage());
        }
    }
}
