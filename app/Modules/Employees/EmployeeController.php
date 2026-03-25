<?php

declare(strict_types=1);

namespace App\Modules\Employees;

use App\Core\Application;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use Throwable;

final class EmployeeController extends Controller
{
    private EmployeeRepository $repository;

    public function __construct(Application $app)
    {
        parent::__construct($app);
        $this->repository = new EmployeeRepository($this->app->database());
    }

    public function index(Request $request): void
    {
        $search = trim((string) $request->input('q', ''));
        $employees = [];

        try {
            $employees = $this->repository->listEmployees($search);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load employees: ' . $throwable->getMessage());
        }

        $this->render('employees.index', [
            'title' => 'Employees',
            'pageTitle' => 'Employees',
            'employees' => $employees,
            'search' => $search,
        ]);
    }

    public function create(Request $request): void
    {
        try {
            $options = $this->repository->formOptions();
            $defaults = ['employee_code' => $this->repository->nextEmployeeCode(), 'employment_type' => 'full_time', 'employee_status' => 'draft'];
        } catch (Throwable $throwable) {
            $options = $this->emptyOptions();
            $defaults = ['employee_code' => 'EMP-0001', 'employment_type' => 'full_time', 'employee_status' => 'draft'];
            $this->app->session()->flash('error', 'Unable to load employee setup lists: ' . $throwable->getMessage());
        }

        $this->render('employees.form', [
            'title' => 'Add Employee',
            'pageTitle' => 'Add Employee',
            'employee' => $defaults,
            'options' => $options,
            'formAction' => '/employees/create',
            'submitLabel' => 'Create Employee',
            'isEdit' => false,
        ]);
    }

    public function store(Request $request): void
    {
        $this->validateCsrf($request, '/employees/create');
        $data = $this->sanitized($request);

        if (!$this->validate($data, '/employees/create')) {
            return;
        }

        try {
            $employeeId = $this->repository->createEmployee($data, $this->app->auth()->id());
            $this->app->session()->flash('success', 'Employee created successfully.');
            $this->redirect('/employees/' . $employeeId);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to save employee: ' . $throwable->getMessage());
            $this->app->session()->flash('old_input', $data);
            $this->redirect('/employees/create');
        }
    }

    public function show(Request $request, string $id): void
    {
        $employeeId = (int) $id;

        if (!$this->canView($employeeId)) {
            Response::abort(403, 'You do not have access to this employee profile.');
        }

        try {
            $employee = $this->repository->findEmployee($employeeId);

            if ($employee === null) {
                Response::abort(404, 'Employee not found.');
            }

            $contacts = $this->repository->emergencyContacts($employeeId);
            $stats = $this->repository->profileStats($employeeId);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load employee profile: ' . $throwable->getMessage());
            $this->redirect('/dashboard');
        }

        $this->render('employees.show', [
            'title' => 'Employee Profile',
            'pageTitle' => 'Employee Profile',
            'employee' => $employee,
            'contacts' => $contacts,
            'stats' => $stats,
        ]);
    }

    public function edit(Request $request, string $id): void
    {
        $employeeId = (int) $id;

        try {
            $employee = $this->repository->findEmployee($employeeId);

            if ($employee === null) {
                Response::abort(404, 'Employee not found.');
            }

            $options = $this->repository->formOptions($employeeId);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load employee details: ' . $throwable->getMessage());
            $this->redirect('/employees');
        }

        $this->render('employees.form', [
            'title' => 'Edit Employee',
            'pageTitle' => 'Edit Employee',
            'employee' => $employee,
            'options' => $options,
            'formAction' => '/employees/' . $employeeId . '/edit',
            'submitLabel' => 'Save Changes',
            'isEdit' => true,
        ]);
    }

    public function archive(Request $request, string $id): void
    {
        $employeeId = (int) $id;

        try {
            $employee = $this->repository->findEmployee($employeeId);

            if ($employee === null) {
                Response::abort(404, 'Employee not found.');
            }
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load employee archive details: ' . $throwable->getMessage());
            $this->redirect('/employees');
        }

        $this->render('employees.archive', [
            'title' => 'Archive Employee',
            'pageTitle' => 'Archive Employee',
            'employee' => $employee,
        ]);
    }

    public function update(Request $request, string $id): void
    {
        $employeeId = (int) $id;
        $this->validateCsrf($request, '/employees/' . $employeeId . '/edit');
        $data = $this->sanitized($request);

        if (!$this->validate($data, '/employees/' . $employeeId . '/edit')) {
            return;
        }

        try {
            $existing = $this->repository->findEmployee($employeeId);

            if ($existing === null) {
                Response::abort(404, 'Employee not found.');
            }

            $this->repository->updateEmployee($employeeId, $data, $this->app->auth()->id());
            $this->app->session()->flash('success', 'Employee updated successfully.');
            $this->redirect('/employees/' . $employeeId);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to update employee: ' . $throwable->getMessage());
            $this->app->session()->flash('old_input', $data);
            $this->redirect('/employees/' . $employeeId . '/edit');
        }
    }

    public function storeArchive(Request $request, string $id): void
    {
        $employeeId = (int) $id;
        $redirectPath = '/employees/' . $employeeId . '/archive';
        $this->validateCsrf($request, $redirectPath);
        $data = $this->sanitized($request);
        $remarks = trim((string) ($data['remarks'] ?? ''));

        if (strlen($remarks) > 255) {
            $this->app->session()->flash('error', 'Archive remarks must be 255 characters or fewer.');
            $this->app->session()->flash('old_input', $data);
            $this->redirect($redirectPath);
        }

        try {
            $employee = $this->repository->findEmployee($employeeId);

            if ($employee === null) {
                Response::abort(404, 'Employee not found.');
            }

            $archived = $this->repository->archiveEmployee($employeeId, $remarks, $this->app->auth()->id());

            if (!$archived) {
                $this->app->session()->flash('success', 'Employee is already archived.');
                $this->redirect('/employees/' . $employeeId . '/history');
            }

            $this->app->session()->flash('success', 'Employee archived successfully.');
            $this->redirect('/employees/' . $employeeId . '/history');
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to archive employee: ' . $throwable->getMessage());
            $this->app->session()->flash('old_input', $data);
            $this->redirect($redirectPath);
        }
    }

    public function history(Request $request, string $id): void
    {
        $employeeId = (int) $id;

        if (!$this->canView($employeeId)) {
            Response::abort(403, 'You do not have access to this employee history.');
        }

        try {
            $employee = $this->repository->findEmployee($employeeId);

            if ($employee === null) {
                Response::abort(404, 'Employee not found.');
            }

            $statusHistory = $this->repository->statusHistory($employeeId);
            $historyLogs = $this->repository->historyLogs($employeeId);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load employee history: ' . $throwable->getMessage());
            $this->redirect('/employees/' . $employeeId);
        }

        $this->render('employees.history', [
            'title' => 'Employee History',
            'pageTitle' => 'Employee History',
            'employee' => $employee,
            'statusHistory' => $statusHistory,
            'historyLogs' => $historyLogs,
        ]);
    }

    private function validateCsrf(Request $request, string $redirectPath): void
    {
        if (!$this->app->csrf()->validate((string) $request->input('_token'))) {
            $this->app->session()->flash('error', 'Invalid form submission token.');
            $this->redirect($redirectPath);
        }
    }

    private function sanitized(Request $request): array
    {
        $data = [];

        foreach ($request->all() as $key => $value) {
            if ($key === '_token') {
                continue;
            }

            $data[$key] = is_string($value) ? trim($value) : $value;
        }

        return $data;
    }

    private function validate(array $data, string $redirectPath): bool
    {
        foreach (['employee_code', 'company_id', 'first_name', 'last_name', 'work_email', 'employment_type'] as $field) {
            if (($data[$field] ?? '') === '') {
                $this->app->session()->flash('error', 'Please complete all required employee fields.');
                $this->app->session()->flash('old_input', $data);
                $this->redirect($redirectPath);
            }
        }

        if (!filter_var((string) $data['work_email'], FILTER_VALIDATE_EMAIL)) {
            $this->app->session()->flash('error', 'Please provide a valid work email address.');
            $this->app->session()->flash('old_input', $data);
            $this->redirect($redirectPath);
        }

        if (($data['personal_email'] ?? '') !== '' && !filter_var((string) $data['personal_email'], FILTER_VALIDATE_EMAIL)) {
            $this->app->session()->flash('error', 'Please provide a valid personal email address.');
            $this->app->session()->flash('old_input', $data);
            $this->redirect($redirectPath);
        }

        return true;
    }

    private function canView(int $employeeId): bool
    {
        if ($this->app->auth()->hasPermission('employee.view_all')) {
            return true;
        }

        $user = $this->app->auth()->user() ?? [];

        return $this->app->auth()->hasPermission('employee.view_self')
            && (int) ($user['employee_id'] ?? 0) === $employeeId;
    }

    private function emptyOptions(): array
    {
        return [
            'companies' => [],
            'branches' => [],
            'departments' => [],
            'teams' => [],
            'job_titles' => [],
            'designations' => [],
            'managers' => [],
        ];
    }
}