<?php

declare(strict_types=1);

namespace App\Modules\Api;

use App\Core\Request;
use App\Modules\Leave\LeaveRepository;
use Throwable;

final class LeaveApiController extends ApiController
{
    private LeaveRepository $repository;

    public function __construct(\App\Core\Application $app)
    {
        parent::__construct($app);
        $this->repository = new LeaveRepository($this->app->database());
    }

    /**
     * GET /api/v1/leave/requests
     * Returns the authenticated employee's own leave requests.
     */
    public function myRequests(Request $request): never
    {
        if (!$this->app->auth()->hasPermission('leave.view_self')) {
            $this->forbidden();
        }

        $user       = $this->app->auth()->user() ?? [];
        $employeeId = (int) ($user['employee_id'] ?? 0);

        if ($employeeId <= 0) {
            $this->error('No employee record linked to this user account.', 422);
        }

        try {
            $requests = $this->repository->myRequests($employeeId);
        } catch (Throwable $e) {
            $this->error('Failed to retrieve leave requests: ' . $e->getMessage(), 500);
        }

        $this->success($requests);
    }

    /**
     * GET /api/v1/leave/balances
     * Returns the authenticated employee's leave balances for the current year.
     */
    public function myBalances(Request $request): never
    {
        if (!$this->app->auth()->hasPermission('leave.view_self')) {
            $this->forbidden();
        }

        $user       = $this->app->auth()->user() ?? [];
        $employeeId = (int) ($user['employee_id'] ?? 0);

        if ($employeeId <= 0) {
            $this->error('No employee record linked to this user account.', 422);
        }

        $year = (int) $request->input('year', date('Y'));

        try {
            $balances = $this->repository->balances($employeeId, $year);
        } catch (Throwable $e) {
            $this->error('Failed to retrieve leave balances: ' . $e->getMessage(), 500);
        }

        $this->success($balances);
    }

    /**
     * GET /api/v1/leave/pending
     * Returns requests pending the current user's approval.
     */
    public function pending(Request $request): never
    {
        if (!$this->app->auth()->hasPermission('leave.approve_team')) {
            $this->forbidden();
        }

        $user       = $this->app->auth()->user() ?? [];
        $employeeId = (int) ($user['employee_id'] ?? 0);

        try {
            $pending = $this->repository->managerPendingRequests($employeeId);
        } catch (Throwable $e) {
            $this->error('Failed to retrieve pending requests: ' . $e->getMessage(), 500);
        }

        $this->success($pending);
    }

    /**
     * GET /api/v1/leave/my
     * Combined: balances + recent requests for the mobile dashboard.
     */
    public function my(Request $request): never
    {
        if (!$this->app->auth()->hasPermission('leave.view_self')) {
            $this->forbidden();
        }

        $user       = $this->app->auth()->user() ?? [];
        $employeeId = (int) ($user['employee_id'] ?? 0);

        if ($employeeId <= 0) {
            $this->error('No employee record linked to this user account.', 422);
        }

        $year = (int) $request->input('year', date('Y'));

        try {
            $balances = $this->repository->balances($employeeId, $year);
            $requests = $this->repository->myRequests($employeeId);
        } catch (Throwable $e) {
            $this->error('Failed to retrieve leave data: ' . $e->getMessage(), 500);
        }

        $this->success(['balances' => $balances, 'requests' => $requests]);
    }

    /**
     * POST /api/v1/leave/request
     * Submit a new leave request (no attachment upload in API flow).
     *
     * Required JSON fields:
     *   leave_type_id, start_date, end_date, start_session, end_session, reason
     * Optional:
     *   replacement_employee_id, days_requested (auto-calculated if omitted)
     */
    public function submitRequest(Request $request): never
    {
        if (!$this->app->auth()->hasPermission('leave.view_self')) {
            $this->forbidden();
        }

        $user       = $this->app->auth()->user() ?? [];
        $employeeId = (int) ($user['employee_id'] ?? 0);
        $userId     = (int) ($user['id'] ?? 0);

        if ($employeeId <= 0) {
            $this->error('No employee record linked to this user account.', 422);
        }

        $body = $this->jsonBody($request);

        $leaveTypeId   = (int) ($body['leave_type_id'] ?? 0);
        $startDate     = trim((string) ($body['start_date'] ?? ''));
        $endDate       = trim((string) ($body['end_date'] ?? ''));
        $startSession  = trim((string) ($body['start_session'] ?? 'morning'));
        $endSession    = trim((string) ($body['end_session'] ?? 'afternoon'));
        $reason        = trim((string) ($body['reason'] ?? ''));

        if ($leaveTypeId <= 0) {
            $this->error('leave_type_id is required.', 422);
        }
        if ($startDate === '' || $endDate === '') {
            $this->error('start_date and end_date are required (YYYY-MM-DD).', 422);
        }
        if (!in_array($startSession, ['morning', 'afternoon'], true)) {
            $this->error('start_session must be morning or afternoon.', 422);
        }
        if (!in_array($endSession, ['morning', 'afternoon'], true)) {
            $this->error('end_session must be morning or afternoon.', 422);
        }

        try {
            $leaveType = $this->repository->findLeaveType($leaveTypeId);
        } catch (Throwable $e) {
            $this->error('Failed to load leave type: ' . $e->getMessage(), 500);
        }

        if ($leaveType === null || ($leaveType['status'] ?? '') !== 'active') {
            $this->error('Invalid or inactive leave type.', 422);
        }

        // Auto-calculate days_requested if not provided
        $daysRequested = isset($body['days_requested']) ? (float) $body['days_requested'] : null;
        if ($daysRequested === null) {
            try {
                $start = new \DateTimeImmutable($startDate);
                $end   = new \DateTimeImmutable($endDate);
                $diff  = (int) $start->diff($end)->days;
                $daysRequested = (float) ($diff + 1);
                // Half-day adjustments
                if ($startSession === 'afternoon') {
                    $daysRequested -= 0.5;
                }
                if ($endSession === 'morning') {
                    $daysRequested -= 0.5;
                }
                $daysRequested = max(0.5, $daysRequested);
            } catch (Throwable) {
                $this->error('Invalid date format. Use YYYY-MM-DD.', 422);
            }
        }

        $data = [
            'leave_type_id'          => $leaveTypeId,
            'start_date'             => $startDate,
            'end_date'               => $endDate,
            'start_session'          => $startSession,
            'end_session'            => $endSession,
            'reason'                 => $reason,
            'days_requested'         => $daysRequested,
            'replacement_employee_id'=> isset($body['replacement_employee_id']) ? (int) $body['replacement_employee_id'] : null,
        ];

        try {
            $requestId = $this->repository->createLeaveRequest($data, $employeeId, $userId);
        } catch (Throwable $e) {
            $this->error('Failed to submit leave request: ' . $e->getMessage(), 500);
        }

        $this->success(['id' => $requestId, 'message' => 'Leave request submitted successfully.'], 201);
    }

    /**
     * POST /api/v1/leave/{id}/approve
     * Approve a leave request. Supports manager and HR queues.
     *
     * Required JSON: { "queue": "manager"|"hr", "comments": "..." (optional) }
     */
    public function approve(Request $request, string $id): never
    {
        $requestId = (int) $id;
        $body      = $this->jsonBody($request);
        $queue     = trim((string) ($body['queue'] ?? ''));
        $comments  = trim((string) ($body['comments'] ?? ''));

        if (!in_array($queue, ['manager', 'hr'], true)) {
            $this->error('queue must be manager or hr.', 422);
        }

        $user       = $this->app->auth()->user() ?? [];
        $userId     = (int) ($user['id'] ?? 0);
        $employeeId = (int) ($user['employee_id'] ?? 0);

        try {
            if ($queue === 'manager') {
                if (!$this->app->auth()->hasPermission('leave.approve_team') || $employeeId <= 0) {
                    $this->forbidden('You do not have permission to approve as manager.');
                }
                $this->repository->approveForManager($requestId, $employeeId, $userId, $comments !== '' ? $comments : null);
            } else {
                if (!$this->app->auth()->hasPermission('leave.manage_types')) {
                    $this->forbidden('You do not have permission to approve as HR.');
                }
                $this->repository->approveForHr($requestId, $userId, $comments !== '' ? $comments : null);
            }
        } catch (Throwable $e) {
            $this->error('Failed to approve leave request: ' . $e->getMessage(), 500);
        }

        $this->success(['message' => 'Leave request approved.']);
    }

    /**
     * POST /api/v1/leave/{id}/reject
     * Reject a leave request.
     *
     * Required JSON: { "queue": "manager"|"hr", "reason": "..." }
     */
    public function reject(Request $request, string $id): never
    {
        $requestId = (int) $id;
        $body      = $this->jsonBody($request);
        $queue     = trim((string) ($body['queue'] ?? ''));
        $reason    = trim((string) ($body['reason'] ?? ''));

        if (!in_array($queue, ['manager', 'hr'], true)) {
            $this->error('queue must be manager or hr.', 422);
        }
        if ($reason === '') {
            $this->error('reason is required for rejection.', 422);
        }

        $user       = $this->app->auth()->user() ?? [];
        $userId     = (int) ($user['id'] ?? 0);
        $employeeId = (int) ($user['employee_id'] ?? 0);

        try {
            if ($queue === 'manager') {
                if (!$this->app->auth()->hasPermission('leave.approve_team') || $employeeId <= 0) {
                    $this->forbidden('You do not have permission to reject as manager.');
                }
                $this->repository->rejectForManager($requestId, $employeeId, $userId, $reason);
            } else {
                if (!$this->app->auth()->hasPermission('leave.manage_types')) {
                    $this->forbidden('You do not have permission to reject as HR.');
                }
                $this->repository->rejectForHr($requestId, $userId, $reason);
            }
        } catch (Throwable $e) {
            $this->error('Failed to reject leave request: ' . $e->getMessage(), 500);
        }

        $this->success(['message' => 'Leave request rejected.']);
    }

    /** Parse raw request body as JSON or fall back to form POST. */
    private function jsonBody(Request $request): array
    {
        $contentType = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
        if (str_contains($contentType, 'application/json')) {
            $raw     = (string) file_get_contents('php://input');
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }
        $result = [];
        $keys   = ['leave_type_id', 'start_date', 'end_date', 'start_session', 'end_session',
                   'reason', 'days_requested', 'replacement_employee_id', 'queue', 'comments', 'reason'];
        foreach ($keys as $key) {
            $val = $request->input($key);
            if ($val !== null) {
                $result[$key] = $val;
            }
        }
        return $result;
    }
}
