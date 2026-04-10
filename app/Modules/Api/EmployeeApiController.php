<?php

declare(strict_types=1);

namespace App\Modules\Api;

use App\Core\Request;
use App\Modules\Employees\EmployeeRepository;
use Throwable;

final class EmployeeApiController extends ApiController
{
    private EmployeeRepository $repository;

    public function __construct(\App\Core\Application $app)
    {
        parent::__construct($app);
        $this->repository = new EmployeeRepository($this->app->database());
    }

    /**
     * GET /api/v1/employees
     * Query params: q (search), page, per_page
     */
    public function index(Request $request): never
    {
        if (!$this->app->auth()->hasPermission('employee.view_all')) {
            $this->forbidden('You do not have permission to list employees.');
        }

        $search  = trim((string) $request->input('q', ''));
        $page    = $this->page($request);
        $perPage = $this->perPage($request);

        try {
            $total     = $this->repository->countEmployees($search);
            $employees = $this->repository->listEmployees($search, $page, $perPage);
        } catch (Throwable $e) {
            $this->error('Failed to retrieve employees: ' . $e->getMessage(), 500);
        }

        $this->paginated($employees, $total, $page, $perPage);
    }

    /**
     * GET /api/v1/employees/{id}
     */
    public function show(Request $request, string $id): never
    {
        $employeeId = (int) $id;

        if ($employeeId <= 0) {
            $this->notFound();
        }

        $user    = $this->app->auth()->user() ?? [];
        $ownEmpId = (int) ($user['employee_id'] ?? 0);

        $canViewAll  = $this->app->auth()->hasPermission('employee.view_all');
        $canViewSelf = $this->app->auth()->hasPermission('employee.view_self') && $ownEmpId === $employeeId;

        if (!$canViewAll && !$canViewSelf) {
            $this->forbidden();
        }

        try {
            $employee = $this->repository->findEmployee($employeeId);
        } catch (Throwable $e) {
            $this->error('Failed to retrieve employee: ' . $e->getMessage(), 500);
        }

        if ($employee === null) {
            $this->notFound('Employee not found.');
        }

        // Redact sensitive encrypted fields from API response
        unset($employee['id_number'], $employee['passport_number']);

        $this->success($employee);
    }
}
