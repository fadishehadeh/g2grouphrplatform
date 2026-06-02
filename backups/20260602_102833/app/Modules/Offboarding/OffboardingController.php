<?php

declare(strict_types=1);

namespace App\Modules\Offboarding;

use App\Core\Application;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Employees\EmployeeRepository;
use DateTimeImmutable;
use Throwable;

final class OffboardingController extends Controller
{
    private OffboardingRepository $repository;
    private EmployeeRepository $employees;

    public function __construct(Application $app)
    {
        parent::__construct($app);
        $this->repository = new OffboardingRepository($this->app->database());
        $this->employees = new EmployeeRepository($this->app->database());
    }

    public function index(Request $request): void
    {
        $search = trim((string) $request->input('q', ''));
        $status = (string) $request->input('status', 'all');

        if (!in_array($status, ['all', 'draft', 'pending', 'in_progress', 'completed', 'cancelled'], true)) {
            $status = 'all';
        }

        $records = [];

        try {
            $records = $this->repository->listRecords($search, $status);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load offboarding records: ' . $throwable->getMessage());
        }

        $this->render('offboarding.index', [
            'title' => 'Offboarding',
            'pageTitle' => 'Offboarding',
            'records' => $records,
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function create(Request $request, string $employeeId): void
    {
        $employeeId = (int) $employeeId;

        try {
            $employee = $this->employees->findEmployee($employeeId);

            if ($employee === null) {
                Response::abort(404, 'Employee not found.');
            }
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load offboarding setup: ' . $throwable->getMessage());
            $this->redirect('/employees');
        }

        $this->render('offboarding.create', [
            'title' => 'Start Offboarding',
            'pageTitle' => 'Start Offboarding',
            'employee' => $employee,
        ]);
    }

    public function store(Request $request, string $employeeId): void
    {
        $employeeId = (int) $employeeId;
        $redirectPath = '/offboarding/create/' . $employeeId;
        $this->validateCsrf($request, $redirectPath);
        $data = $this->sanitized($request);

        try {
            $employee = $this->employees->findEmployee($employeeId);

            if ($employee === null) {
                Response::abort(404, 'Employee not found.');
            }

            if (!in_array((string) ($data['record_type'] ?? ''), ['resignation', 'termination', 'retirement', 'contract_end', 'absconding', 'other'], true)) {
                $this->invalid($redirectPath, $data, 'Please select a valid offboarding type.');
            }

            if (!in_array((string) ($data['status'] ?? ''), ['draft', 'pending', 'in_progress'], true)) {
                $this->invalid($redirectPath, $data, 'Please select a valid initial offboarding status.');
            }

            $noticeDate = $this->validatedDate($data['notice_date'] ?? null, 'notice date', $redirectPath, $data, false);
            $exitDate = $this->validatedDate($data['exit_date'] ?? null, 'exit date', $redirectPath, $data, true);
            $lastWorkingDate = $this->validatedDate($data['last_working_date'] ?? null, 'last working date', $redirectPath, $data, false);

            if ($noticeDate !== null && $exitDate !== null && $noticeDate > $exitDate) {
                $this->invalid($redirectPath, $data, 'Notice date cannot be later than the exit date.');
            }

            if ($lastWorkingDate !== null && $exitDate !== null && $lastWorkingDate > $exitDate) {
                $this->invalid($redirectPath, $data, 'Last working date cannot be later than the exit date.');
            }

            $data['notice_date'] = $noticeDate?->format('Y-m-d');
            $data['exit_date'] = $exitDate?->format('Y-m-d');
            $data['last_working_date'] = $lastWorkingDate?->format('Y-m-d');

            $recordId = $this->repository->createRecord($employeeId, $data, $this->app->auth()->id());
            $this->app->session()->flash('success', 'Offboarding record created successfully.');
            $this->redirect('/offboarding/' . $recordId);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to create offboarding record: ' . $throwable->getMessage());
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
                Response::abort(404, 'Offboarding record not found.');
            }

            $tasks = $this->repository->recordTasks($recordId);
            $assets = $this->repository->assetItems($recordId);
            $options = $this->repository->managementOptions();
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load offboarding record: ' . $throwable->getMessage());
            $this->redirect('/offboarding');
        }

        $this->render('offboarding.show', [
            'title' => 'Offboarding Details',
            'pageTitle' => 'Offboarding Details',
            'record' => $record,
            'tasks' => $tasks,
            'assets' => $assets,
            'options' => $options,
        ]);
    }

    public function storeTask(Request $request, string $id): void
    {
        $recordId = (int) $id;
        $redirectPath = '/offboarding/' . $recordId;
        $this->validateCsrf($request, $redirectPath);
        $data = $this->sanitized($request);

        try {
            if ($this->repository->findRecord($recordId) === null) {
                Response::abort(404, 'Offboarding record not found.');
            }

            $taskData = $this->validatedTaskData([
                'task_name' => $data['new_task_name'] ?? '',
                'department_id' => $data['new_department_id'] ?? '',
                'assigned_to_user_id' => $data['new_assigned_to_user_id'] ?? '',
                'due_date' => $data['new_due_date'] ?? '',
                'status' => $data['new_status'] ?? 'pending',
                'remarks' => $data['new_remarks'] ?? '',
            ], $redirectPath, true);

            $this->repository->createTask($recordId, $taskData);
            $this->app->session()->flash('success', 'Offboarding task added successfully.');
            $this->redirect($redirectPath);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to add offboarding task: ' . $throwable->getMessage());
            $this->app->session()->flash('old_input', $data);
            $this->redirect($redirectPath);
        }
    }

    public function storeAsset(Request $request, string $id): void
    {
        $recordId = (int) $id;
        $redirectPath = '/offboarding/' . $recordId;
        $this->validateCsrf($request, $redirectPath);
        $data = $this->sanitized($request);

        try {
            if ($this->repository->findRecord($recordId) === null) {
                Response::abort(404, 'Offboarding record not found.');
            }

            $assetData = $this->validatedAssetData([
                'asset_name' => $data['new_asset_name'] ?? '',
                'asset_code' => $data['new_asset_code'] ?? '',
                'quantity' => $data['new_quantity'] ?? '1',
                'return_status' => $data['new_return_status'] ?? 'pending',
                'remarks' => $data['new_remarks'] ?? '',
            ], $redirectPath, true);

            $this->repository->createAsset($recordId, $assetData, $this->app->auth()->id());
            $this->app->session()->flash('success', 'Asset return item added successfully.');
            $this->redirect($redirectPath);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to add asset return item: ' . $throwable->getMessage());
            $this->app->session()->flash('old_input', $data);
            $this->redirect($redirectPath);
        }
    }

    public function updateTask(Request $request, string $id): void
    {
        $taskId = (int) $id;
        $recordId = (int) $request->input('offboarding_id', 0);
        $redirectPath = $recordId > 0 ? '/offboarding/' . $recordId : '/offboarding';
        $this->validateCsrf($request, $redirectPath);
        $data = $this->sanitized($request);
        $status = (string) ($data['status'] ?? 'pending');

        if (!in_array($status, ['pending', 'in_progress', 'completed', 'waived'], true)) {
            $this->invalid($redirectPath, $data, 'Please choose a valid offboarding task status.');
        }

        try {
            $taskData = $this->validatedTaskData($data, $redirectPath, false);
            $updatedRecordId = $this->repository->updateTask($taskId, $taskData);

            if ($updatedRecordId === null) {
                Response::abort(404, 'Offboarding task not found.');
            }

            $this->app->session()->flash('success', 'Offboarding task updated successfully.');
            $this->redirect('/offboarding/' . $updatedRecordId);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to update offboarding task: ' . $throwable->getMessage());
            $this->redirect($redirectPath);
        }
    }

    public function updateAsset(Request $request, string $id): void
    {
        $assetId = (int) $id;
        $recordId = (int) $request->input('offboarding_id', 0);
        $redirectPath = $recordId > 0 ? '/offboarding/' . $recordId : '/offboarding';
        $this->validateCsrf($request, $redirectPath);
        $data = $this->sanitized($request);
        $returnStatus = (string) ($data['return_status'] ?? 'pending');

        if (!in_array($returnStatus, ['pending', 'returned', 'missing', 'waived'], true)) {
            $this->invalid($redirectPath, $data, 'Please choose a valid asset return status.');
        }

        try {
            $assetData = $this->validatedAssetData($data, $redirectPath, false);
            $updatedRecordId = $this->repository->updateAsset(
                $assetId,
                $assetData,
                $this->app->auth()->id()
            );

            if ($updatedRecordId === null) {
                Response::abort(404, 'Asset return item not found.');
            }

            $this->app->session()->flash('success', 'Asset return item updated successfully.');
            $this->redirect('/offboarding/' . $updatedRecordId);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to update asset return item: ' . $throwable->getMessage());
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

        return $data;
    }

    private function validatedDate(
        mixed $value,
        string $label,
        string $redirectPath,
        array $data,
        bool $required,
        bool $flashOldInput = true,
    ): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            if ($required) {
                $this->invalid($redirectPath, $data, 'Please provide a valid ' . $label . '.', $flashOldInput);
            }

            return null;
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d', (string) $value);

        if (!$date instanceof DateTimeImmutable || $date->format('Y-m-d') !== (string) $value) {
            $this->invalid($redirectPath, $data, 'Please provide a valid ' . $label . '.', $flashOldInput);
        }

        return $date;
    }

    private function validatedTaskData(array $data, string $redirectPath, bool $flashOldInput): array
    {
        $taskName = trim((string) ($data['task_name'] ?? ''));

        if ($taskName === '') {
            $this->invalid($redirectPath, $data, 'Please provide a task name.', $flashOldInput);
        }

        if (strlen($taskName) > 150) {
            $this->invalid($redirectPath, $data, 'Task name must be 150 characters or fewer.', $flashOldInput);
        }

        $status = (string) ($data['status'] ?? 'pending');

        if (!in_array($status, ['pending', 'in_progress', 'completed', 'waived'], true)) {
            $this->invalid($redirectPath, $data, 'Please choose a valid offboarding task status.', $flashOldInput);
        }

        $options = $this->repository->managementOptions();
        $departmentId = $this->validatedOptionId(
            $data['department_id'] ?? '',
            'department',
            array_column($options['departments'] ?? [], 'id'),
            $redirectPath,
            $data,
            $flashOldInput
        );
        $assignedToUserId = $this->validatedOptionId(
            $data['assigned_to_user_id'] ?? '',
            'task owner',
            array_column($options['users'] ?? [], 'id'),
            $redirectPath,
            $data,
            $flashOldInput
        );
        $dueDate = $this->validatedDate($data['due_date'] ?? null, 'due date', $redirectPath, $data, false, $flashOldInput);
        $remarks = trim((string) ($data['remarks'] ?? ''));

        if (strlen($remarks) > 255) {
            $this->invalid($redirectPath, $data, 'Task remarks must be 255 characters or fewer.', $flashOldInput);
        }

        return [
            'task_name' => $taskName,
            'department_id' => $departmentId,
            'assigned_to_user_id' => $assignedToUserId,
            'due_date' => $dueDate?->format('Y-m-d'),
            'status' => $status,
            'remarks' => $remarks,
        ];
    }

    private function validatedAssetData(array $data, string $redirectPath, bool $flashOldInput): array
    {
        $assetName = trim((string) ($data['asset_name'] ?? ''));

        if ($assetName === '') {
            $this->invalid($redirectPath, $data, 'Please provide an asset name.', $flashOldInput);
        }

        if (strlen($assetName) > 150) {
            $this->invalid($redirectPath, $data, 'Asset name must be 150 characters or fewer.', $flashOldInput);
        }

        $assetCode = trim((string) ($data['asset_code'] ?? ''));

        if (strlen($assetCode) > 50) {
            $this->invalid($redirectPath, $data, 'Asset code must be 50 characters or fewer.', $flashOldInput);
        }

        $quantity = $this->validatedPositiveInteger($data['quantity'] ?? '1', 'quantity', $redirectPath, $data, $flashOldInput);
        $returnStatus = (string) ($data['return_status'] ?? 'pending');

        if (!in_array($returnStatus, ['pending', 'returned', 'missing', 'waived'], true)) {
            $this->invalid($redirectPath, $data, 'Please choose a valid asset return status.', $flashOldInput);
        }

        $remarks = trim((string) ($data['remarks'] ?? ''));

        if (strlen($remarks) > 255) {
            $this->invalid($redirectPath, $data, 'Asset remarks must be 255 characters or fewer.', $flashOldInput);
        }

        return [
            'asset_name' => $assetName,
            'asset_code' => $assetCode,
            'quantity' => $quantity,
            'return_status' => $returnStatus,
            'remarks' => $remarks,
        ];
    }

    private function validatedOptionId(
        mixed $value,
        string $label,
        array $allowedIds,
        string $redirectPath,
        array $data,
        bool $flashOldInput,
    ): ?int {
        if ($value === null || $value === '') {
            return null;
        }

        if (!preg_match('/^[0-9]+$/', (string) $value) || (int) $value <= 0) {
            $this->invalid($redirectPath, $data, 'Please select a valid ' . $label . '.', $flashOldInput);
        }

        $id = (int) $value;
        $normalizedIds = array_map(static fn (mixed $item): int => (int) $item, $allowedIds);

        if (!in_array($id, $normalizedIds, true)) {
            $this->invalid($redirectPath, $data, 'Please select a valid ' . $label . '.', $flashOldInput);
        }

        return $id;
    }

    private function validatedPositiveInteger(
        mixed $value,
        string $label,
        string $redirectPath,
        array $data,
        bool $flashOldInput,
    ): int {
        if (!preg_match('/^[0-9]+$/', (string) $value) || (int) $value <= 0) {
            $this->invalid($redirectPath, $data, 'Please provide a valid positive ' . $label . '.', $flashOldInput);
        }

        return (int) $value;
    }

    private function invalid(string $redirectPath, array $data, string $message, bool $flashOldInput = true): never
    {
        $this->app->session()->flash('error', $message);

        if ($flashOldInput) {
            $this->app->session()->flash('old_input', $data);
        }

        $this->redirect($redirectPath);
    }
}