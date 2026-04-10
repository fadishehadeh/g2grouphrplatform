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
}
