<?php

declare(strict_types=1);

namespace App\Modules\Api;

use App\Core\Request;
use App\Modules\Employees\EmployeeRepository;
use Throwable;

final class ProfileApiController extends ApiController
{
    private EmployeeRepository $repository;

    public function __construct(\App\Core\Application $app)
    {
        parent::__construct($app);
        $this->repository = new EmployeeRepository($this->app->database());
    }

    /**
     * GET /api/v1/profile/me
     * Returns the authenticated employee's own profile data.
     */
    public function me(Request $request): never
    {
        $user       = $this->app->auth()->user() ?? [];
        $employeeId = (int) ($user['employee_id'] ?? 0);

        if ($employeeId <= 0) {
            $this->error('No employee record linked to this user account.', 422);
        }

        try {
            $employee = $this->repository->findEmployee($employeeId);
        } catch (Throwable $e) {
            $this->error('Failed to load profile: ' . $e->getMessage(), 500);
        }

        if ($employee === null) {
            $this->notFound('Employee profile not found.');
        }

        // Strip sensitive / internal fields before returning
        $safe = $this->safeProfile($employee);

        $this->success($safe);
    }

    /**
     * PATCH /api/v1/profile/me
     * Self-service update of personal contact fields only.
     *
     * Updatable fields: phone, alternate_phone, personal_email
     */
    public function update(Request $request): never
    {
        $user       = $this->app->auth()->user() ?? [];
        $employeeId = (int) ($user['employee_id'] ?? 0);

        if ($employeeId <= 0) {
            $this->error('No employee record linked to this user account.', 422);
        }

        $body         = $this->jsonBody($request);
        $phone        = isset($body['phone'])          ? trim((string) $body['phone'])          : null;
        $altPhone     = isset($body['alternate_phone']) ? trim((string) $body['alternate_phone']) : null;
        $personalEmail= isset($body['personal_email']) ? trim((string) $body['personal_email']) : null;

        // Validate email format if provided
        if ($personalEmail !== null && $personalEmail !== '' && !filter_var($personalEmail, FILTER_VALIDATE_EMAIL)) {
            $this->error('personal_email must be a valid email address.', 422);
        }

        $updates = [];
        if ($phone !== null) {
            $updates['phone'] = $phone;
        }
        if ($altPhone !== null) {
            $updates['alternate_phone'] = $altPhone;
        }
        if ($personalEmail !== null) {
            $updates['personal_email'] = $personalEmail;
        }

        if ($updates === []) {
            $this->error('No updatable fields provided. Accepted: phone, alternate_phone, personal_email.', 422);
        }

        try {
            $setClauses = implode(', ', array_map(static fn (string $k): string => "{$k} = :{$k}", array_keys($updates)));
            $updates['id'] = $employeeId;

            $this->app->database()->execute(
                "UPDATE employees SET {$setClauses}, updated_at = NOW() WHERE id = :id",
                $updates
            );

            $employee = $this->repository->findEmployee($employeeId);
        } catch (Throwable $e) {
            $this->error('Failed to update profile: ' . $e->getMessage(), 500);
        }

        $this->success($this->safeProfile($employee ?? []));
    }

    /**
     * Strip internal/sensitive columns before returning to mobile clients.
     */
    private function safeProfile(array $employee): array
    {
        $allowed = [
            'id', 'employee_code', 'first_name', 'middle_name', 'last_name',
            'work_email', 'personal_email', 'phone', 'alternate_phone',
            'date_of_birth', 'gender', 'marital_status', 'nationality',
            'employment_type', 'contract_type', 'employee_status', 'joining_date',
            'company_name', 'branch_name', 'department_name', 'team_name',
            'job_title_name', 'designation_name', 'manager_name',
        ];

        return array_intersect_key($employee, array_flip($allowed));
    }

    private function jsonBody(Request $request): array
    {
        $contentType = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
        if (str_contains($contentType, 'application/json')) {
            $raw     = (string) file_get_contents('php://input');
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }
        $result = [];
        foreach (['phone', 'alternate_phone', 'personal_email'] as $key) {
            $val = $request->input($key);
            if ($val !== null) {
                $result[$key] = $val;
            }
        }
        return $result;
    }
}
