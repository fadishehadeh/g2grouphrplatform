<?php

declare(strict_types=1);

namespace App\Modules\Onboarding;

use App\Core\Application;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Employees\EmployeeRepository;
use DateTimeImmutable;
use Throwable;

final class OnboardingController extends Controller
{
    private OnboardingRepository $repository;
    private EmployeeRepository $employees;

    public function __construct(Application $app)
    {
        parent::__construct($app);
        $this->repository = new OnboardingRepository($this->app->database());
        $this->employees = new EmployeeRepository($this->app->database());
    }

    public function index(Request $request): void
    {
        $search = trim((string) $request->input('q', ''));
        $status = (string) $request->input('status', 'all');

        if (!in_array($status, ['all', 'pending', 'in_progress', 'completed', 'cancelled'], true)) {
            $status = 'all';
        }

        $records = [];

        try {
            $records = $this->repository->listRecords($search, $status);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load onboarding records: ' . $throwable->getMessage());
        }

        $this->render('onboarding.index', [
            'title' => 'Onboarding',
            'pageTitle' => 'Onboarding',
            'records' => $records,
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function templates(Request $request): void
    {
        $search = trim((string) $request->input('q', ''));
        $templates = [];

        try {
            $templates = $this->repository->listTemplates($search);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load onboarding templates: ' . $throwable->getMessage());
        }

        $this->render('onboarding.templates', [
            'title' => 'Onboarding Templates',
            'pageTitle' => 'Onboarding Templates',
            'templates' => $templates,
            'search' => $search,
        ]);
    }

    public function templateShow(Request $request, string $id): void
    {
        $templateId = (int) $id;

        try {
            $template = $this->repository->findTemplateDetail($templateId);

            if ($template === null) {
                Response::abort(404, 'Onboarding template not found.');
            }

            $tasks = $this->repository->templateTasks($templateId);
            $roles = $this->repository->roleOptions();
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load onboarding template: ' . $throwable->getMessage());
            $this->redirect('/onboarding/templates');
        }

        $nextSortOrder = 1;

        foreach ($tasks as $task) {
            $nextSortOrder = max($nextSortOrder, ((int) ($task['sort_order'] ?? 0)) + 1);
        }

        $this->render('onboarding.template-show', [
            'title' => 'Onboarding Template Details',
            'pageTitle' => 'Onboarding Template Details',
            'templateDetail' => $template,
            'tasks' => $tasks,
            'roles' => $roles,
            'nextSortOrder' => $nextSortOrder,
        ]);
    }

    public function storeTemplate(Request $request): void
    {
        $redirectPath = '/onboarding/templates';
        $this->validateCsrf($request, $redirectPath);
        $data = $this->sanitized($request);

        if (($data['name'] ?? '') === '' || ($data['task_lines'] ?? '') === '') {
            $this->invalid($redirectPath, $data, 'Please provide a template name and at least one task.');
        }

        try {
            $this->repository->createTemplate($data, $this->app->auth()->id());
            $this->app->session()->flash('success', 'Onboarding template created successfully.');
            $this->redirect($redirectPath);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to save onboarding template: ' . $throwable->getMessage());
            $this->app->session()->flash('old_input', $data);
            $this->redirect($redirectPath);
        }
    }

    public function storeTemplateTask(Request $request, string $templateId): void
    {
        $templateId = (int) $templateId;
        $redirectPath = '/onboarding/templates/' . $templateId;
        $this->validateCsrf($request, $redirectPath);
        $data = $this->sanitized($request);

        try {
            if ($this->repository->findTemplate($templateId) === null) {
                Response::abort(404, 'Onboarding template not found.');
            }

            $taskData = $this->validatedTemplateTaskData([
                'task_name' => $data['new_task_name'] ?? '',
                'description' => $data['new_description'] ?? '',
                'sort_order' => $data['new_sort_order'] ?? '',
                'assignee_role_id' => $data['new_assignee_role_id'] ?? '',
                'is_required' => $data['new_is_required'] ?? '1',
            ], $redirectPath, true);

            $this->repository->createTemplateTask($templateId, $taskData);
            $this->app->session()->flash('success', 'Onboarding template task added successfully.');
            $this->redirect($redirectPath);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to save onboarding template task: ' . $throwable->getMessage());
            $this->app->session()->flash('old_input', $data);
            $this->redirect($redirectPath);
        }
    }

    public function create(Request $request, string $employeeId): void
    {
        $employeeId = (int) $employeeId;

        try {
            $employee = $this->employees->findEmployee($employeeId);

            if ($employee === null) {
                Response::abort(404, 'Employee not found.');
            }

            $existingRecord = $this->repository->findExistingForEmployee($employeeId);
            $templates = $this->repository->templateOptions();
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load onboarding setup: ' . $throwable->getMessage());
            $this->redirect('/employees');
        }

        $this->render('onboarding.create', [
            'title' => 'Start Onboarding',
            'pageTitle' => 'Start Onboarding',
            'employee' => $employee,
            'templates' => $templates,
            'existingRecord' => $existingRecord,
        ]);
    }

    public function store(Request $request, string $employeeId): void
    {
        $employeeId = (int) $employeeId;
        $redirectPath = '/onboarding/create/' . $employeeId;
        $this->validateCsrf($request, $redirectPath);
        $data = $this->sanitized($request);

        try {
            $employee = $this->employees->findEmployee($employeeId);

            if ($employee === null) {
                Response::abort(404, 'Employee not found.');
            }

            $existing = $this->repository->findExistingForEmployee($employeeId);

            if ($existing !== null) {
                $this->app->session()->flash('error', 'This employee already has an onboarding record.');
                $this->redirect('/onboarding/' . $existing['id']);
            }

            if ((int) ($data['template_id'] ?? 0) <= 0) {
                $this->invalid($redirectPath, $data, 'Please select an onboarding template.');
            }

            $startDate = $this->validatedDate($data['start_date'] ?? null, 'start date', $redirectPath, $data, false);
            $dueDate = $this->validatedDate($data['due_date'] ?? null, 'due date', $redirectPath, $data, false);

            if ($startDate !== null && $dueDate !== null && $dueDate < $startDate) {
                $this->invalid($redirectPath, $data, 'Due date cannot be earlier than the start date.');
            }

            $data['start_date'] = $startDate?->format('Y-m-d');
            $data['due_date'] = $dueDate?->format('Y-m-d');

            $recordId = $this->repository->createRecord($employeeId, $data, $this->app->auth()->id());
            $this->app->session()->flash('success', 'Onboarding record created successfully.');
            $this->redirect('/onboarding/' . $recordId);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to create onboarding record: ' . $throwable->getMessage());
            $this->app->session()->flash('old_input', $data);
            $this->redirect($redirectPath);
        }
    }

    public function show(Request $request, string $id): void
    {
        $recordId = (int) $id;

        try {
            $record = $this->repository->findRecord($recordId);

            if ($record === null) {
                Response::abort(404, 'Onboarding record not found.');
            }

            $tasks = $this->repository->recordTasks($recordId);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load onboarding record: ' . $throwable->getMessage());
            $this->redirect('/onboarding');
        }

        $this->render('onboarding.show', [
            'title' => 'Onboarding Details',
            'pageTitle' => 'Onboarding Details',
            'record' => $record,
            'tasks' => $tasks,
        ]);
    }

    public function updateTask(Request $request, string $id): void
    {
        $taskId = (int) $id;
        $recordId = (int) $request->input('onboarding_id', 0);
        $redirectPath = $recordId > 0 ? '/onboarding/' . $recordId : '/onboarding';
        $this->validateCsrf($request, $redirectPath);
        $data = $this->sanitized($request);
        $status = (string) ($data['status'] ?? 'pending');

        if (!in_array($status, ['pending', 'in_progress', 'completed', 'waived'], true)) {
            $this->invalid($redirectPath, $data, 'Please choose a valid onboarding task status.');
        }

        try {
            $updatedRecordId = $this->repository->updateTask(
                $taskId,
                $status,
                (string) ($data['remarks'] ?? ''),
                $this->app->auth()->id()
            );

            if ($updatedRecordId === null) {
                Response::abort(404, 'Onboarding task not found.');
            }

            $this->app->session()->flash('success', 'Onboarding task updated successfully.');
            $this->redirect('/onboarding/' . $updatedRecordId);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to update onboarding task: ' . $throwable->getMessage());
            $this->redirect($redirectPath);
        }
    }

    public function updateTemplateTask(Request $request, string $id): void
    {
        $taskId = (int) $id;
        $templateId = (int) $request->input('template_id', 0);
        $redirectPath = $templateId > 0 ? '/onboarding/templates/' . $templateId : '/onboarding/templates';
        $this->validateCsrf($request, $redirectPath);
        $data = $this->sanitized($request);

        try {
            $taskData = $this->validatedTemplateTaskData($data, $redirectPath, false);
            $updatedTemplateId = $this->repository->updateTemplateTask($taskId, $taskData);

            if ($updatedTemplateId === null) {
                Response::abort(404, 'Onboarding template task not found.');
            }

            $this->app->session()->flash('success', 'Onboarding template task updated successfully.');
            $this->redirect('/onboarding/templates/' . $updatedTemplateId);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to update onboarding template task: ' . $throwable->getMessage());
            $this->redirect($redirectPath);
        }
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

        if (array_key_exists('is_active', $data)) {
            $data['is_active'] = (int) $data['is_active'];
        }

        return $data;
    }

    private function validatedDate(mixed $value, string $label, string $redirectPath, array $data, bool $required): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            if ($required) {
                $this->invalid($redirectPath, $data, 'Please provide a valid ' . $label . '.');
            }

            return null;
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d', (string) $value);

        if (!$date instanceof DateTimeImmutable || $date->format('Y-m-d') !== (string) $value) {
            $this->invalid($redirectPath, $data, 'Please provide a valid ' . $label . '.');
        }

        return $date;
    }

    private function validatedTemplateTaskData(array $data, string $redirectPath, bool $flashOldInput): array
    {
        $taskName = trim((string) ($data['task_name'] ?? ''));

        if ($taskName === '') {
            $this->templateTaskInvalid($redirectPath, $data, 'Please provide a task name.', $flashOldInput);
        }

        if (strlen($taskName) > 150) {
            $this->templateTaskInvalid($redirectPath, $data, 'Task name must be 150 characters or fewer.', $flashOldInput);
        }

        $description = trim((string) ($data['description'] ?? ''));

        if (strlen($description) > 255) {
            $this->templateTaskInvalid($redirectPath, $data, 'Task description must be 255 characters or fewer.', $flashOldInput);
        }

        $sortOrderValue = $data['sort_order'] ?? '1';

        if ($sortOrderValue === '' || $sortOrderValue === null) {
            $sortOrderValue = '1';
        }

        if (!preg_match('/^[0-9]+$/', (string) $sortOrderValue) || (int) $sortOrderValue <= 0) {
            $this->templateTaskInvalid($redirectPath, $data, 'Please provide a valid positive sort order.', $flashOldInput);
        }

        $assigneeRoleId = null;
        $roleValue = (string) ($data['assignee_role_id'] ?? '');

        if ($roleValue !== '') {
            if (!preg_match('/^[0-9]+$/', $roleValue) || (int) $roleValue <= 0) {
                $this->templateTaskInvalid($redirectPath, $data, 'Please select a valid assignee role.', $flashOldInput);
            }

            $assigneeRoleId = (int) $roleValue;
            $validRoleIds = array_map(static fn (array $role): int => (int) $role['id'], $this->repository->roleOptions());

            if (!in_array($assigneeRoleId, $validRoleIds, true)) {
                $this->templateTaskInvalid($redirectPath, $data, 'Please select a valid assignee role.', $flashOldInput);
            }
        }

        $isRequired = (string) ($data['is_required'] ?? '1');

        if (!in_array($isRequired, ['0', '1'], true)) {
            $this->templateTaskInvalid($redirectPath, $data, 'Please choose whether this task is required.', $flashOldInput);
        }

        return [
            'task_name' => $taskName,
            'description' => $description,
            'sort_order' => (int) $sortOrderValue,
            'assignee_role_id' => $assigneeRoleId,
            'is_required' => (int) $isRequired,
        ];
    }

    private function templateTaskInvalid(string $redirectPath, array $data, string $message, bool $flashOldInput): never
    {
        $this->app->session()->flash('error', $message);

        if ($flashOldInput) {
            $this->app->session()->flash('old_input', $data);
        }

        $this->redirect($redirectPath);
    }

    private function invalid(string $redirectPath, array $data, string $message): never
    {
        $this->app->session()->flash('error', $message);
        $this->app->session()->flash('old_input', $data);
        $this->redirect($redirectPath);
    }
}