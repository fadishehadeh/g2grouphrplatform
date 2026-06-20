<?php

declare(strict_types=1);

namespace App\Modules\Leave;

use App\Core\Application;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Support\Branding;
use DateTimeImmutable;
use Throwable;

final class LeaveController extends Controller
{
    private LeaveRepository $repository;

    public function __construct(Application $app)
    {
        parent::__construct($app);
        $this->repository = new LeaveRepository($this->app->database());
    }

    public function index(Request $request): void
    {
        $employeeId = $this->requireEmployeeProfile();
        $balances = [];
        $requests = [];

        try {
            $balances = $this->repository->balances($employeeId, (int) date('Y'));
            $requests = $this->repository->myRequests($employeeId);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load leave data: ' . $throwable->getMessage());
        }

        $this->render('leaves.index', [
            'title' => 'My Leave',
            'pageTitle' => 'My Leave',
            'balances' => $balances,
            'requests' => $requests,
        ]);
    }

    private function optionMap(array $rows): array
    {
        $options = [];
        foreach ($rows as $row) {
            $options[(string) $row['id']] = (string) $row['name'];
        }
        return $options;
    }

    public function create(Request $request): void
    {
        $employeeId = $this->requireEmployeeProfile();
        $leaveTypes = [];
        $balances = [];
        $replacementEmployeeOptions = [];

        try {
            $leaveTypes = $this->repository->activeLeaveTypes();
            $balances = $this->repository->balances($employeeId, (int) date('Y'));
            $replacementEmployeeOptions = $this->optionMap($this->repository->employeeOptions());
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load leave request options: ' . $throwable->getMessage());
        }

        $this->render('leaves.request', [
            'title' => 'Request Leave',
            'pageTitle' => 'Request Leave',
            'leaveTypes' => $leaveTypes,
            'balances' => $balances,
            'replacementEmployeeOptions' => $replacementEmployeeOptions,
        ]);
    }

    public function store(Request $request): void
    {
        $this->validateCsrf($request, '/leave/request');
        $employeeId = $this->requireEmployeeProfile();
        $data = $this->sanitized($request);
        $storedAttachmentPath = null;

        try {
            $leaveType = $this->repository->findLeaveType((int) ($data['leave_type_id'] ?? 0));

            if ($leaveType === null || ($leaveType['status'] ?? 'inactive') !== 'active') {
                $this->invalid('/leave/request', $data, 'Please select a valid active leave type.');
            }

            // Validate replacement employee
            $replacementEmployeeId = (int) ($data['replacement_employee_id'] ?? 0);
            if ($replacementEmployeeId === 0) {
                $this->invalid('/leave/request', $data, 'Please select a replacement employee.');
            }

            if ($replacementEmployeeId === $employeeId) {
                $this->invalid('/leave/request', $data, 'You cannot select yourself as a replacement.');
            }

            // Verify replacement employee exists
            $replacementEmployee = $this->repository->findEmployee($replacementEmployeeId);
            if ($replacementEmployee === null) {
                $this->invalid('/leave/request', $data, 'Selected replacement employee does not exist.');
            }

            $attachmentMeta = $this->prepareLeaveAttachment($request, $leaveType, $employeeId, '/leave/request', $data);
            $storedAttachmentPath = $attachmentMeta['absolute_path'] ?? null;

            $daysRequested = $this->validateLeaveRequest($employeeId, $data, $leaveType, '/leave/request');
            $data['days_requested'] = $daysRequested;
            $data['attachment_meta'] = $attachmentMeta;
            $this->repository->createLeaveRequest($data, $employeeId, $this->app->auth()->id());

            $this->app->session()->flash('success', 'Leave request submitted successfully.');
            $this->redirect('/leave/my');
        } catch (Throwable $throwable) {
            if ($storedAttachmentPath !== null && is_file($storedAttachmentPath)) {
                @unlink($storedAttachmentPath);
            }
            $this->app->session()->flash('error', 'Unable to submit leave request: ' . $throwable->getMessage());
            $this->app->session()->flash('old_input', $data);
            $this->redirect('/leave/request');
        }
    }

    public function approvals(Request $request): void
    {
        $user = $this->app->auth()->user() ?? [];
        $managerQueue = [];
        $hrQueue = [];

        try {
            if ($this->app->auth()->hasPermission('leave.approve_team') && !empty($user['employee_id'])) {
                $managerQueue = $this->repository->managerPendingRequests((int) $user['employee_id']);
            }

            if ($this->app->auth()->hasPermission('leave.manage_types')) {
                $hrQueue = $this->repository->hrPendingRequests();
            }
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load leave approval queues: ' . $throwable->getMessage());
        }

        $this->render('leaves.approvals', [
            'title' => 'Leave Approvals',
            'pageTitle' => 'Leave Approvals',
            'managerQueue' => $managerQueue,
            'hrQueue' => $hrQueue,
        ]);
    }

    public function approve(Request $request, string $id): void
    {
        $this->validateCsrf($request, '/leave/approvals');
        $requestId = (int) $id;
        $queue = (string) $request->input('queue', '');
        $comments = trim((string) $request->input('comments', ''));
        $user = $this->app->auth()->user() ?? [];

        try {
            if ($queue === 'manager') {
                if (empty($user['employee_id']) || !$this->app->auth()->hasPermission('leave.approve_team')) {
                    Response::abort(403, 'You do not have permission to approve this request.');
                }

                $this->repository->approveForManager($requestId, (int) $user['employee_id'], $this->app->auth()->id(), $comments);
            } elseif ($queue === 'hr') {
                if (!$this->app->auth()->hasPermission('leave.manage_types')) {
                    Response::abort(403, 'You do not have permission to approve this request.');
                }

                $this->repository->approveForHr($requestId, $this->app->auth()->id(), $comments);
            } else {
                Response::abort(400, 'Unknown approval queue.');
            }

            $this->app->session()->flash('success', 'Leave request approved successfully.');
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to approve leave request: ' . $throwable->getMessage());
        }

        $returnTo = trim((string) $request->input('return_to', ''));
        $this->redirect($returnTo !== '' ? $returnTo : '/leave/approvals');
    }

    public function reject(Request $request, string $id): void
    {
        $this->validateCsrf($request, '/leave/approvals');
        $requestId = (int) $id;
        $queue = (string) $request->input('queue', '');
        $reason = trim((string) $request->input('reason', '')) ?: trim((string) $request->input('comments', ''));
        $user = $this->app->auth()->user() ?? [];
        $returnTo = trim((string) $request->input('return_to', ''));

        if ($reason === '') {
            $this->app->session()->flash('error', 'A rejection reason is required.');
            $this->redirect($returnTo !== '' ? $returnTo : '/leave/approvals');
        }

        try {
            if ($queue === 'manager') {
                if (empty($user['employee_id']) || !$this->app->auth()->hasPermission('leave.approve_team')) {
                    Response::abort(403, 'You do not have permission to reject this request.');
                }

                $this->repository->rejectForManager($requestId, (int) $user['employee_id'], $this->app->auth()->id(), $reason);
            } elseif ($queue === 'hr') {
                if (!$this->app->auth()->hasPermission('leave.manage_types')) {
                    Response::abort(403, 'You do not have permission to reject this request.');
                }

                $this->repository->rejectForHr($requestId, $this->app->auth()->id(), $reason);
            } else {
                Response::abort(400, 'Unknown approval queue.');
            }

            $this->app->session()->flash('success', 'Leave request rejected successfully.');
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to reject leave request: ' . $throwable->getMessage());
        }

        $this->redirect($returnTo !== '' ? $returnTo : '/leave/approvals');
    }

    public function balances(Request $request): void
    {
        $scope = $this->leaveScope();
        $search = trim((string) $request->input('q', ''));
        $year = $this->normalizeYear((string) $request->input('year', (string) date('Y')));
        $status = 'active';
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 25;
        $balances = [];
        $stats = [
            'employees' => 0,
            'leave_types' => 0,
            'available_total' => 0.0,
            'used_total' => 0.0,
        ];
        $total = 0;
        $totalPages = 1;
        $employeeOptions = [];
        $leaveTypes = [];

        try {
            $total = $this->repository->countBalanceOverview($scope, $year, $search, $status);
            $totalPages = (int) ceil($total / $perPage);
            $page = min($page, max(1, $totalPages));
            $balances = $this->repository->balanceOverview($scope, $year, $search, $status, $page, $perPage);
            $stats = $this->repository->balanceOverviewSummary($scope, $year, $search, $status);
            $leaveTypes = $this->repository->activeLeaveTypes();
            $stats['employees'] = $total;
            $stats['leave_types'] = count($leaveTypes);
            if ($this->app->auth()->hasPermission('leave.manage_types')) {
                $employeeOptions = $this->optionMap($this->repository->employeeOptions());
            }
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load leave balances: ' . $throwable->getMessage());
        }

        $this->render('leaves.balances', [
            'title' => 'Leave Balances',
            'pageTitle' => 'Leave Balances',
            'balances' => $balances,
            'stats' => $stats,
            'scope' => $scope,
            'search' => $search,
            'year' => $year,
            'status' => $status,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'totalPages' => $totalPages,
            'employeeOptions' => $employeeOptions,
            'leaveTypes' => $leaveTypes,
        ]);
    }

    public function createAdminLeave(Request $request): void
    {
        if (!$this->app->auth()->hasPermission('leave.manage_types')) {
            Response::abort(403);
        }

        $leaveTypes = [];
        $employeeOptions = [];

        try {
            $leaveTypes = $this->repository->activeLeaveTypes();
            $employeeOptions = $this->optionMap($this->repository->employeeOptions());
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load admin leave entry options: ' . $throwable->getMessage());
        }

        $this->render('leaves.admin-create', [
            'title' => 'Add Leave for Employee',
            'pageTitle' => 'Add Leave for Employee',
            'leaveTypes' => $leaveTypes,
            'employeeOptions' => $employeeOptions,
        ]);
    }

    public function storeAdminLeave(Request $request): void
    {
        if (!$this->app->auth()->hasPermission('leave.manage_types')) {
            Response::abort(403);
        }

        $this->validateCsrf($request, '/admin/leave/create');
        $data = $this->sanitized($request);
        $employeeId = (int) ($data['employee_id'] ?? 0);
        $storedAttachmentPath = null;
        $returnYear = $this->normalizeYear((string) ($data['return_year'] ?? date('Y')));
        $returnToEmployee = (int) ($data['return_to_employee'] ?? 0) === 1;
        $employeeRedirect = $employeeId > 0 ? '/admin/leave/employees/' . $employeeId . '?year=' . $returnYear : '/leave/balances?year=' . $returnYear;
        $redirectPath = $returnToEmployee && $employeeId > 0 ? $employeeRedirect : '/admin/leave/create';

        try {
            if ($employeeId <= 0) {
                $this->invalid($redirectPath, $data, 'Please select a valid employee.');
            }

            $employee = $this->repository->findEmployee($employeeId);
            if ($employee === null) {
                $this->invalid($redirectPath, $data, 'Selected employee does not exist.');
            }

            $leaveType = $this->repository->findLeaveType((int) ($data['leave_type_id'] ?? 0));
            if ($leaveType === null || ($leaveType['status'] ?? 'inactive') !== 'active') {
                $this->invalid($redirectPath, $data, 'Please select a valid active leave type.');
            }

            $attachmentMeta = $this->prepareLeaveAttachment($request, $leaveType, $employeeId, $redirectPath, $data);
            $storedAttachmentPath = $attachmentMeta['absolute_path'] ?? null;

            $daysRequested = $this->validateLeaveRequest($employeeId, $data, $leaveType, $redirectPath, true);
            $data['days_requested'] = $daysRequested;
            $data['attachment_meta'] = $attachmentMeta;

            $requestId = $this->repository->createApprovedLeaveRequest(
                $data,
                $employeeId,
                $this->app->auth()->id(),
                trim((string) ($data['admin_note'] ?? ''))
            );

            $this->app->session()->flash('success', 'Leave record created and approved successfully.');
            $this->redirect($returnToEmployee ? $employeeRedirect : '/leave/requests/' . $requestId);
        } catch (Throwable $throwable) {
            if ($storedAttachmentPath !== null && is_file($storedAttachmentPath)) {
                @unlink($storedAttachmentPath);
            }
            $this->app->session()->flash('error', 'Unable to create admin leave record: ' . $throwable->getMessage());
            $this->app->session()->flash('old_input', $data);
            $this->redirect($redirectPath);
        }
    }

    public function employeeDetail(Request $request, string $id): void
    {
        if (!$this->app->auth()->hasPermission('leave.manage_types')) {
            Response::abort(403);
        }

        $employeeId = (int) $id;
        if ($employeeId <= 0) {
            Response::abort(404, 'Employee not found.');
        }

        $year = $this->normalizeYear((string) $request->input('year', (string) date('Y')));
        $this->renderEmployeeLeaveDetail($employeeId, $year);
    }

    public function editAdminLeave(Request $request, string $id): void
    {
        if (!$this->app->auth()->hasPermission('leave.manage_types')) {
            Response::abort(403);
        }

        $requestId = (int) $id;
        if ($requestId <= 0) {
            Response::abort(404, 'Leave request not found.');
        }

        try {
            $editLeaveRequest = $this->repository->findApprovedRequestForAdminEdit($requestId);
            if ($editLeaveRequest === null) {
                Response::abort(404, 'Approved leave request not found.');
            }

            $year = $this->normalizeYear((string) $request->input('year', (string) date('Y', strtotime((string) $editLeaveRequest['start_date']))));
            $editAttachments = $this->repository->attachments($requestId);
            $this->renderEmployeeLeaveDetail((int) $editLeaveRequest['employee_id'], $year, $editLeaveRequest, $editAttachments);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load approved leave record: ' . $throwable->getMessage());
            $this->redirect('/leave/balances');
        }
    }

    public function updateAdminLeave(Request $request, string $id): void
    {
        if (!$this->app->auth()->hasPermission('leave.manage_types')) {
            Response::abort(403);
        }

        $requestId = (int) $id;
        if ($requestId <= 0) {
            Response::abort(404, 'Leave request not found.');
        }

        $redirectPath = '/admin/leave/requests/' . $requestId . '/edit';
        $this->validateCsrf($request, $redirectPath);
        $data = $this->sanitized($request);
        $storedAttachmentPath = null;

        try {
            $existingRequest = $this->repository->findApprovedRequestForAdminEdit($requestId);
            if ($existingRequest === null) {
                Response::abort(404, 'Approved leave request not found.');
            }

            $employeeId = (int) $existingRequest['employee_id'];
            $leaveType = $this->repository->findLeaveType((int) ($data['leave_type_id'] ?? 0));
            if ($leaveType === null || ($leaveType['status'] ?? 'inactive') !== 'active') {
                $this->invalid($redirectPath, $data, 'Please select a valid active leave type.');
            }

            $existingAttachments = $this->repository->attachments($requestId);
            $attachmentMeta = $this->prepareLeaveAttachment(
                $request,
                $leaveType,
                $employeeId,
                $redirectPath,
                $data,
                $existingAttachments !== []
            );
            $storedAttachmentPath = $attachmentMeta['absolute_path'] ?? null;

            $daysRequested = $this->validateLeaveRequest($employeeId, $data, $leaveType, $redirectPath, true, $existingRequest);
            $data['days_requested'] = $daysRequested;

            $this->repository->updateApprovedLeaveRequest(
                $requestId,
                $data,
                $this->app->auth()->id(),
                trim((string) ($data['admin_note'] ?? '')),
                $attachmentMeta
            );

            $year = $this->normalizeYear((string) ($data['return_year'] ?? date('Y', strtotime((string) $data['start_date']))));
            $this->app->session()->flash('success', 'Approved leave record updated successfully.');
            $this->redirect('/admin/leave/employees/' . $employeeId . '?year=' . $year);
        } catch (Throwable $throwable) {
            if ($storedAttachmentPath !== null && is_file($storedAttachmentPath)) {
                @unlink($storedAttachmentPath);
            }
            $this->app->session()->flash('error', 'Unable to update approved leave record: ' . $throwable->getMessage());
            $this->app->session()->flash('old_input', $data);
            $this->redirect($redirectPath . '?year=' . $this->normalizeYear((string) ($data['return_year'] ?? date('Y'))));
        }
    }

    public function assignBalances(Request $request): void
    {
        if (!$this->app->auth()->hasPermission('leave.manage_types')) {
            Response::abort(403);
        }

        $this->validateCsrf($request, '/leave/balances');
        $year = (int) $request->input('year', date('Y'));

        try {
            $count = $this->repository->bulkAssignBalances($year);
            $this->app->session()->flash('success', "Assigned non-annual balances for {$year}: {$count} record(s) created or refreshed for active employees.");
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to assign balances: ' . $throwable->getMessage());
        }

        $this->redirect('/leave/balances?year=' . $year);
    }

    public function recalculateAnnualBalances(Request $request): void
    {
        if (!$this->app->auth()->hasPermission('leave.manage_types')) {
            Response::abort(403);
        }

        $this->validateCsrf($request, '/leave/balances');
        $year = (int) $request->input('year', date('Y'));

        try {
            $result = $this->repository->recalculateAnnualLeaveBalances($year, $this->app->auth()->id());
            $this->app->session()->flash(
                'success',
                "Annual leave recalculated for {$year}: {$result['created']} created, {$result['updated']} updated."
            );
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to recalculate annual leave: ' . $throwable->getMessage());
        }

        $this->redirect('/leave/balances?year=' . $year);
    }

    public function adjustBalance(Request $request): void
    {
        if (!$this->app->auth()->hasPermission('leave.manage_types')) {
            Response::abort(403);
        }

        $this->validateCsrf($request, '/leave/balances');
        $employeeId = (int) $request->input('employee_id');
        $leaveTypeId = (int) $request->input('leave_type_id');
        $year = (int) $request->input('year', date('Y'));
        $opening = (float) $request->input('opening_balance', 0);
        $accrued = (float) $request->input('accrued', 0);
        $usedAmount = (float) $request->input('used_amount', 0);
        $adjustment = (float) $request->input('adjusted_amount', 0);

        try {
            if ($employeeId <= 0 || $leaveTypeId <= 0) {
                throw new \InvalidArgumentException('Invalid employee or leave type.');
            }

            $this->repository->saveBalance($employeeId, $leaveTypeId, $year, $opening, $accrued, $usedAmount, $adjustment);
            $this->app->session()->flash('success', 'Leave balance updated successfully.');
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to update balance: ' . $throwable->getMessage());
        }

        $this->redirect('/leave/balances?year=' . $year);
    }

    public function importAnnualUsedDays(Request $request): void
    {
        if (!$this->app->auth()->hasPermission('leave.manage_types')) {
            Response::abort(403);
        }

        $this->validateCsrf($request, '/leave/balances');
        $year = (int) $request->input('year', date('Y'));
        $file = $request->file('import_file');

        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->app->session()->flash('error', 'Please select a valid Excel file to upload.');
            $this->redirect('/leave/balances?year=' . $year);
        }

        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xls'], true)) {
            $this->app->session()->flash('error', 'Only .xlsx and .xls files are supported.');
            $this->redirect('/leave/balances?year=' . $year);
        }

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file['tmp_name']);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);

            if (count($rows) < 2) {
                throw new \RuntimeException('The file appears to be empty. Please add used-days data below the header row.');
            }

            $headerRow = array_map(fn($value) => strtolower(trim((string) $value)), $rows[1] ?? []);
            unset($rows[1]);

            $preparedRows = [];
            $reviewRows = [];
            $errors = [];
            $rowNumber = 1;

            $hasLegacyHeaders = in_array('employee_code', $headerRow, true) || in_array('used_days', $headerRow, true);
            $hasBalanceHeaders = in_array('employee name', $headerRow, true) || in_array('used balance', $headerRow, true);
            $employeeOptions = $this->optionRowsById($this->repository->employeeOptions());

            foreach ($rows as $row) {
                $rowNumber++;
                $mapped = [];

                foreach ($headerRow as $column => $field) {
                    $mapped[$field] = trim((string) ($row[$column] ?? ''));
                }

                $employeeCode = strtoupper(trim((string) ($mapped['employee_code'] ?? '')));
                $employeeName = trim((string) ($mapped['employee name'] ?? ''));
                $usedDaysRaw = trim((string) ($mapped['used_days'] ?? ($mapped['used balance'] ?? '')));

                if ($employeeCode === '' && $employeeName === '' && $usedDaysRaw === '') {
                    continue;
                }

                if (($employeeCode === '' && $employeeName === '') || $usedDaysRaw === '') {
                    $errors[] = "Row {$rowNumber}: employee_code or Employee Name is required, and Used Days/Used Balance must be present.";
                    continue;
                }

                if (!is_numeric($usedDaysRaw) || (float) $usedDaysRaw < 0) {
                    $errors[] = "Row {$rowNumber}: Used Days/Used Balance must be a valid non-negative number.";
                    continue;
                }

                $preparedRows[] = [
                    'row_number' => $rowNumber,
                    'employee_code' => $employeeCode,
                    'employee_name' => $employeeName,
                    'used_days' => (float) $usedDaysRaw,
                ];
            }

            if ($preparedRows === []) {
                $this->app->session()->flash('import_errors', $errors);
                throw new \RuntimeException('No annual leave used-days rows were found to import.');
            }

            if (!$hasLegacyHeaders && !$hasBalanceHeaders) {
                $this->app->session()->flash('import_errors', [
                    'Header row not recognized. Use either employee_code + used_days, or Employee Name + Used Balance.',
                ]);
                throw new \RuntimeException('The import file headers are not in a supported format.');
            }

            $directRows = [];

            foreach ($preparedRows as $preparedRow) {
                $employeeCode = trim((string) ($preparedRow['employee_code'] ?? ''));
                $employeeName = trim((string) ($preparedRow['employee_name'] ?? ''));

                if ($employeeCode !== '') {
                    $directRows[] = $preparedRow;
                    continue;
                }

                $candidates = $this->repository->leaveImportEmployeeSummariesByName($employeeName);

                if (count($candidates) === 1) {
                    $preparedRow['employee_code'] = (string) ($candidates[0]['employee_code'] ?? '');
                    $preparedRow['employee_id'] = (int) ($candidates[0]['id'] ?? 0);
                    $directRows[] = $preparedRow;
                    continue;
                }

                $reviewRows[] = [
                    'row_number' => (int) $preparedRow['row_number'],
                    'employee_name' => $employeeName,
                    'used_days' => (float) $preparedRow['used_days'],
                    'candidates' => $candidates,
                ];
            }

            $result = $this->repository->importAnnualUsedDays($year, $directRows, $this->app->auth()->id());
            $allErrors = [...$errors, ...$result['errors']];

            if ($allErrors !== []) {
                $this->app->session()->flash('import_errors', $allErrors);
            }

            if ($reviewRows !== []) {
                $this->app->session()->put('annual_used_days_import_review', [
                    'year' => $year,
                    'rows' => $reviewRows,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);

                $this->render('leaves.import-annual-used-review', [
                    'title' => 'Review Annual Used Days Import',
                    'pageTitle' => 'Review Annual Used Days Import',
                    'reviewRows' => $reviewRows,
                    'employeeOptions' => $employeeOptions,
                    'year' => $year,
                    'importErrors' => $allErrors,
                    'updatedCount' => (int) ($result['updated'] ?? 0),
                ]);

                return;
            }

            if ($result['updated'] > 0) {
                $this->app->session()->flash(
                    'success',
                    "Annual leave used days imported for {$year}: {$result['updated']} employee(s) updated."
                );
            } elseif ($allErrors !== []) {
                $this->app->session()->flash('error', 'No annual leave used-days rows were imported. Please review the errors below.');
            } else {
                $this->app->session()->flash('error', 'No annual leave used-days rows were imported.');
            }
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to import annual leave used days: ' . $throwable->getMessage());
        }

        $this->redirect('/leave/balances?year=' . $year);
    }

    public function confirmAnnualUsedDaysImport(Request $request): void
    {
        if (!$this->app->auth()->hasPermission('leave.manage_types')) {
            Response::abort(403);
        }

        $this->validateCsrf($request, '/leave/balances');
        $reviewPayload = $this->app->session()->get('annual_used_days_import_review');

        if (!is_array($reviewPayload) || !isset($reviewPayload['rows']) || !is_array($reviewPayload['rows'])) {
            $this->app->session()->flash('error', 'Import review session expired. Please upload the file again.');
            $this->redirect('/leave/balances');
        }

        $year = (int) ($reviewPayload['year'] ?? date('Y'));
        $actions = $request->input('row_action', []);
        $selectedEmployees = $request->input('employee_id', []);
        $rowsToImport = [];
        $errors = [];
        $skipped = 0;

        foreach ($reviewPayload['rows'] as $index => $row) {
            $action = is_array($actions) ? (string) ($actions[$index] ?? 'import') : 'import';
            $rowNumber = (int) ($row['row_number'] ?? 0);

            if ($action === 'skip') {
                $skipped++;
                continue;
            }

            $employeeId = is_array($selectedEmployees) ? (int) ($selectedEmployees[$index] ?? 0) : 0;
            if ($employeeId <= 0) {
                $errors[] = "Row {$rowNumber}: Please select an employee or skip this row.";
                continue;
            }

            $rowsToImport[] = [
                'row_number' => $rowNumber,
                'employee_id' => $employeeId,
                'used_days' => (float) ($row['used_days'] ?? 0),
            ];
        }

        $result = ['updated' => 0, 'errors' => []];
        if ($rowsToImport !== []) {
            try {
                $result = $this->repository->importAnnualUsedDaysByEmployeeId($year, $rowsToImport, $this->app->auth()->id());
            } catch (Throwable $throwable) {
                $errors[] = $throwable->getMessage();
            }
        }

        $allErrors = [...$errors, ...$result['errors']];
        $this->app->session()->remove('annual_used_days_import_review');

        if ($allErrors !== []) {
            $this->app->session()->flash('import_errors', $allErrors);
        }

        if (($result['updated'] ?? 0) > 0 || $skipped > 0) {
            $parts = [];
            if (($result['updated'] ?? 0) > 0) {
                $parts[] = "{$result['updated']} imported";
            }
            if ($skipped > 0) {
                $parts[] = "{$skipped} skipped";
            }
            $this->app->session()->flash('success', 'Annual leave used-days review completed: ' . implode(', ', $parts) . '.');
        } elseif ($allErrors !== []) {
            $this->app->session()->flash('error', 'No annual leave used-days rows were imported. Please review the errors below.');
        }

        $this->redirect('/leave/balances?year=' . $year);
    }

    public function requests(Request $request): void
    {
        $scope = $this->leaveScope();
        $search = trim((string) $request->input('q', ''));
        $status = $this->normalizeRequestStatus((string) $request->input('status', 'all'), 'all');
        $leaveTypes = [];
        $requests = [];
        $summary = [
            'total' => 0,
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0,
            'closed' => 0,
        ];
        $leaveTypeId = (int) $request->input('leave_type_id', 0);

        try {
            $leaveTypes = $this->repository->activeLeaveTypes();
            $leaveTypeOptions = $this->optionRowsById($leaveTypes);

            if ($leaveTypeId > 0 && !isset($leaveTypeOptions[$leaveTypeId])) {
                $leaveTypeId = 0;
            }

            $requests = $this->repository->listRequests($scope, $search, $status, $leaveTypeId);
            $summary = $this->requestSummary($requests);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load leave requests: ' . $throwable->getMessage());
        }

        $this->render('leaves.requests', [
            'title' => 'Leave Requests',
            'pageTitle' => 'Leave Requests',
            'leaveTypes' => $leaveTypes,
            'requests' => $requests,
            'summary' => $summary,
            'scope' => $scope,
            'search' => $search,
            'status' => $status,
            'leaveTypeId' => $leaveTypeId,
        ]);
    }

    public function showRequest(Request $request, string $id): void
    {
        $requestId = (int) $id;

        if ($requestId <= 0) {
            Response::abort(404, 'Leave request not found.');
        }

        $scope = $this->leaveScope();
        $user = $this->app->auth()->user() ?? [];

        try {
            $leaveRequest = $this->repository->findRequestForScope($requestId, $scope);

            if ($leaveRequest === null) {
                Response::abort(404, 'Leave request not found.');
            }

            $approvalTrail = $this->repository->approvalTrail($requestId);
            $attachments = $this->repository->attachments($requestId);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load leave request: ' . $throwable->getMessage());
            $this->redirect('/leave/requests');
        }

        $status = (string) ($leaveRequest['status'] ?? '');
        $canApproveAsManager = $status === 'pending_manager'
            && $this->app->auth()->hasPermission('leave.approve_team')
            && (int) ($user['employee_id'] ?? 0) > 0;
        $canApproveAsHr = $status === 'pending_hr'
            && $this->app->auth()->hasPermission('leave.manage_types');

        $this->render('leaves.show', [
            'title' => 'Leave Request Detail',
            'pageTitle' => 'Leave Request Detail',
            'leaveRequest' => $leaveRequest,
            'approvalTrail' => $approvalTrail,
            'attachments' => $attachments,
            'scope' => $scope,
            'canApprove' => $canApproveAsManager || $canApproveAsHr,
            'approveQueue' => $canApproveAsHr ? 'hr' : 'manager',
        ]);
    }

    public function calendar(Request $request): void
    {
        $scope = $this->leaveScope();
        $search = trim((string) $request->input('q', ''));
        $month = $this->normalizeMonth((string) $request->input('month', date('Y-m')));
        $status = $this->normalizeRequestStatus((string) $request->input('status', 'approved'), 'approved');
        $leaveTypes = [];
        $calendarRequests = [];
        $summary = [
            'total' => 0,
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0,
            'closed' => 0,
        ];
        $leaveTypeId = (int) $request->input('leave_type_id', 0);
        $monthStart = new DateTimeImmutable($month . '-01');
        $monthEnd = $monthStart->modify('last day of this month');
        $weeks = [];

        try {
            $leaveTypes = $this->repository->activeLeaveTypes();
            $leaveTypeOptions = $this->optionRowsById($leaveTypes);

            if ($leaveTypeId > 0 && !isset($leaveTypeOptions[$leaveTypeId])) {
                $leaveTypeId = 0;
            }

            $calendarRequests = $this->repository->calendarRequests(
                $scope,
                $monthStart->format('Y-m-d'),
                $monthEnd->format('Y-m-d'),
                $status,
                $leaveTypeId,
                $search
            );
            $weeks = $this->buildCalendarWeeks($monthStart, $monthEnd, $calendarRequests);
            $summary = $this->requestSummary($calendarRequests);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load leave calendar: ' . $throwable->getMessage());
        }

        $this->render('leaves.calendar', [
            'title' => 'Leave Calendar',
            'pageTitle' => 'Leave Calendar',
            'leaveTypes' => $leaveTypes,
            'calendarRequests' => $calendarRequests,
            'summary' => $summary,
            'scope' => $scope,
            'search' => $search,
            'status' => $status,
            'leaveTypeId' => $leaveTypeId,
            'month' => $month,
            'monthLabel' => $monthStart->format('F Y'),
            'previousMonth' => $monthStart->modify('-1 month')->format('Y-m'),
            'nextMonth' => $monthStart->modify('+1 month')->format('Y-m'),
            'weeks' => $weeks,
        ]);
    }

    public function types(Request $request): void
    {
        $search = trim((string) $request->input('q', ''));
        $leaveTypes = [];

        try {
            $leaveTypes = $this->repository->listLeaveTypes($search);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load leave types: ' . $throwable->getMessage());
        }

        $this->render('leaves.types', [
            'title' => 'Leave Types',
            'pageTitle' => 'Leave Types',
            'leaveTypes' => $leaveTypes,
            'search' => $search,
        ]);
    }

    public function updateType(Request $request, string $id): void
    {
        $this->validateCsrf($request, '/admin/leave/types');
        $name   = trim((string) $request->input('name', ''));
        $code   = trim((string) $request->input('code', ''));
        $status = trim((string) $request->input('status', 'active'));

        if ($name === '' || $code === '') {
            $this->invalid('/admin/leave/types', [], 'Name and code are required.');
        }

        try {
            $this->repository->updateLeaveType((int) $id, $name, $code, $status);
            $this->app->session()->flash('success', 'Leave type updated successfully.');
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to update leave type: ' . $throwable->getMessage());
        }

        $this->redirect('/admin/leave/types');
    }

    public function storeType(Request $request): void
    {
        $this->validateCsrf($request, '/admin/leave/types');
        $data = $this->sanitized($request);

        if (($data['name'] ?? '') === '') {
            $this->invalid('/admin/leave/types', $data, 'Please provide the required leave type fields.');
        }

        if (($data['code'] ?? '') === '') {
            $data['code'] = $this->repository->nextLeaveTypeCode();
        }

        try {
            $this->repository->createLeaveType($data);
            $this->app->session()->flash('success', 'Leave type created successfully.');
            $this->redirect('/admin/leave/types');
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to save leave type: ' . $throwable->getMessage());
            $this->app->session()->flash('old_input', $data);
            $this->redirect('/admin/leave/types');
        }
    }

    public function seedDefaultTypes(Request $request): void
    {
        $this->validateCsrf($request, '/admin/leave/types');

        try {
            $result = $this->repository->seedDefaultLeaveTypes();
            $this->app->session()->flash(
                'success',
                sprintf(
                    'Default leave types processed: %d created, %d already present.',
                    (int) ($result['created'] ?? 0),
                    (int) ($result['skipped'] ?? 0)
                )
            );
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to seed default leave types: ' . $throwable->getMessage());
        }

        $this->redirect('/admin/leave/types');
    }

    public function policies(Request $request): void
    {
        $search = trim((string) $request->input('q', ''));
        $policies = [];
        $policyRules = [];
        $policyOptions = [];
        $leaveTypeOptions = [];
        $departmentOptions = [];
        $jobTitleOptions = [];
        $companies = [];
        $accrualFrequencies = $this->policyAccrualOptions();
        $employmentTypes = $this->policyEmploymentTypeOptions();

        try {
            $policies = $this->repository->listLeavePolicies($search);
            $policyOptions = $this->repository->policyOptions();
            $leaveTypeOptions = $this->repository->leaveTypeOptions();
            $departmentOptions = $this->repository->departmentOptions();
            $jobTitleOptions = $this->repository->jobTitleOptions();
            $companies = $this->repository->companyOptions();
            $policyIds = array_map('intval', array_column($policies, 'id'));

            foreach ($this->repository->listLeavePolicyRules($policyIds) as $rule) {
                $policyRules[(int) $rule['leave_policy_id']][] = $rule;
            }
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load leave policies: ' . $throwable->getMessage());
        }

        $this->render('leaves.policies', [
            'title' => 'Leave Policies',
            'pageTitle' => 'Leave Policies',
            'policies' => $policies,
            'policyRules' => $policyRules,
            'policyOptions' => $policyOptions,
            'leaveTypeOptions' => $leaveTypeOptions,
            'departmentOptions' => $departmentOptions,
            'jobTitleOptions' => $jobTitleOptions,
            'companies' => $companies,
            'accrualFrequencies' => $accrualFrequencies,
            'employmentTypes' => $employmentTypes,
            'search' => $search,
        ]);
    }

    public function storePolicy(Request $request): void
    {
        $redirectPath = '/admin/leave/policies';
        $this->validateCsrf($request, $redirectPath);
        $data = $this->sanitized($request);

        try {
            $companies = $this->optionRowsById($this->repository->companyOptions());
            $validated = $this->validatePolicyPayload($data, $companies);

            $this->repository->createLeavePolicy($validated, $this->app->auth()->id());
            $this->app->session()->flash('success', 'Leave policy created successfully.');
            $this->redirect($redirectPath);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to save leave policy: ' . $throwable->getMessage());
            $this->app->session()->flash('old_input', $data);
            $this->redirect($redirectPath);
        }
    }

    public function storePolicyRule(Request $request): void
    {
        $redirectPath = '/admin/leave/policies';
        $this->validateCsrf($request, $redirectPath);
        $data = $this->sanitized($request);

        try {
            $policies = $this->optionRowsById($this->repository->policyOptions());
            $leaveTypes = $this->optionRowsById($this->repository->leaveTypeOptions());
            $departments = $this->optionRowsById($this->repository->departmentOptions());
            $jobTitles = $this->optionRowsById($this->repository->jobTitleOptions());
            $validated = $this->validatePolicyRulePayload($data, $policies, $leaveTypes, $departments, $jobTitles);

            if ($this->repository->policyRuleExists($validated)) {
                $this->invalid($redirectPath, $data, 'A leave policy rule already exists for the selected policy scope.');
            }

            $this->repository->createLeavePolicyRule($validated);
            $this->app->session()->flash('success', 'Leave policy rule created successfully.');
            $this->redirect($redirectPath);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to save leave policy rule: ' . $throwable->getMessage());
            $this->app->session()->flash('old_input', $data);
            $this->redirect($redirectPath);
        }
    }

    public function updatePolicyRule(Request $request, string $id): void
    {
        $redirectPath = '/admin/leave/policies';
        $ruleId = (int) $id;
        $this->validateCsrf($request, $redirectPath);
        $data = $this->sanitized($request);

        try {
            if ($ruleId <= 0 || $this->repository->findLeavePolicyRule($ruleId) === null) {
                $this->app->session()->flash('error', 'Leave policy rule not found.');
                $this->redirect($redirectPath);
            }

            $policies = $this->optionRowsById($this->repository->policyOptions());
            $leaveTypes = $this->optionRowsById($this->repository->leaveTypeOptions());
            $departments = $this->optionRowsById($this->repository->departmentOptions());
            $jobTitles = $this->optionRowsById($this->repository->jobTitleOptions());
            $validated = $this->validatePolicyRulePayload($data, $policies, $leaveTypes, $departments, $jobTitles);

            if ($this->repository->policyRuleExists($validated, $ruleId)) {
                $this->invalid($redirectPath, $data, 'A leave policy rule already exists for the selected policy scope.');
            }

            $this->repository->updateLeavePolicyRule($ruleId, $validated);
            $this->app->session()->flash('success', 'Leave policy rule updated successfully.');
            $this->redirect($redirectPath);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to update leave policy rule: ' . $throwable->getMessage());
            $this->app->session()->flash('old_input', $data);
            $this->redirect($redirectPath);
        }
    }

    public function deletePolicyRule(Request $request, string $id): void
    {
        $redirectPath = '/admin/leave/policies';
        $ruleId = (int) $id;
        $this->validateCsrf($request, $redirectPath);

        try {
            if ($ruleId <= 0 || $this->repository->findLeavePolicyRule($ruleId) === null) {
                $this->app->session()->flash('error', 'Leave policy rule not found.');
                $this->redirect($redirectPath);
            }

            $this->repository->deleteLeavePolicyRule($ruleId);
            $this->app->session()->flash('success', 'Leave policy rule deleted successfully.');
            $this->redirect($redirectPath);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to delete leave policy rule: ' . $throwable->getMessage());
            $this->redirect($redirectPath);
        }
    }

    public function holidays(Request $request): void
    {
        $search = trim((string) $request->input('q', ''));
        $holidays = [];
        $companies = [];
        $branches = [];

        try {
            $holidays = $this->repository->listHolidays($search);
            $companies = $this->repository->companyOptions();
            $branches = $this->repository->branchOptions();
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load holiday data: ' . $throwable->getMessage());
        }

        $this->render('leaves.holidays', [
            'title' => 'Holiday Calendar',
            'pageTitle' => 'Holiday Calendar',
            'holidays' => $holidays,
            'companies' => $companies,
            'branches' => $branches,
            'search' => $search,
        ]);
    }

    public function storeHoliday(Request $request): void
    {
        $this->validateCsrf($request, '/admin/leave/holidays');
        $data = $this->sanitized($request);

        try {
            $companies = $this->optionRowsById($this->repository->companyOptions());
            $branches = $this->optionRowsById($this->repository->branchOptions());

            $this->validateHolidayPayload($data, $companies, $branches);
            $this->repository->createHoliday($data);

            $this->app->session()->flash('success', 'Holiday saved successfully.');
            $this->redirect('/admin/leave/holidays');
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to save holiday: ' . $throwable->getMessage());
            $this->app->session()->flash('old_input', $data);
            $this->redirect('/admin/leave/holidays');
        }
    }

    public function weekends(Request $request): void
    {
        $search = trim((string) $request->input('q', ''));
        $weekendSettings = [];
        $companies = [];
        $branches = [];
        $weekendDays = $this->weekendDayOptions();

        try {
            $weekendSettings = $this->repository->listWeekendSettings($search);
            $companies = $this->repository->companyOptions();
            $branches = $this->repository->branchOptions();
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load weekend settings: ' . $throwable->getMessage());
        }

        $this->render('leaves.weekends', [
            'title' => 'Weekend Settings',
            'pageTitle' => 'Weekend Settings',
            'weekendSettings' => $weekendSettings,
            'companies' => $companies,
            'branches' => $branches,
            'weekendDays' => $weekendDays,
            'search' => $search,
        ]);
    }

    public function storeWeekend(Request $request): void
    {
        $redirectPath = '/admin/leave/weekends';
        $this->validateCsrf($request, $redirectPath);
        $data = $this->sanitized($request);

        try {
            $companies = $this->optionRowsById($this->repository->companyOptions());
            $branches = $this->optionRowsById($this->repository->branchOptions());
            $validated = $this->validateWeekendPayload($data, $companies, $branches);

            if ($this->repository->weekendSettingExists(
                (int) $validated['company_id'],
                $validated['branch_id'] !== null ? (int) $validated['branch_id'] : null,
                (int) $validated['day_of_week']
            )) {
                $this->invalid($redirectPath, $data, 'A weekend setting already exists for that company, branch, and day.');
            }

            $this->repository->createWeekendSetting($validated);

            $this->app->session()->flash('success', 'Weekend setting saved successfully.');
            $this->redirect($redirectPath);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to save weekend setting: ' . $throwable->getMessage());
            $this->app->session()->flash('old_input', $data);
            $this->redirect($redirectPath);
        }
    }

    private function weekendDayOptions(): array
    {
        return [
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday',
        ];
    }

    private function leaveScope(): array
    {
        $user = $this->app->auth()->user() ?? [];

        if ($this->app->auth()->hasPermission('leave.manage_types')) {
            return [
                'type' => 'all',
                'employee_id' => (int) ($user['employee_id'] ?? 0),
                'label' => 'All leave records',
            ];
        }

        if ($this->app->auth()->hasPermission('leave.approve_team')) {
            $employeeId = (int) ($user['employee_id'] ?? 0);

            if ($employeeId <= 0) {
                $this->app->session()->flash('error', 'Your account is not linked to an employee profile yet.');
                $this->redirect('/dashboard');
            }

            return [
                'type' => 'team',
                'employee_id' => $employeeId,
                'label' => 'My leave and direct reports',
            ];
        }

        if ($this->app->auth()->hasPermission('leave.view_self')) {
            return [
                'type' => 'self',
                'employee_id' => $this->requireEmployeeProfile(),
                'label' => 'My leave records',
            ];
        }

        Response::abort(403, 'You do not have access to leave records.');
    }

    private function normalizeYear(string $value): int
    {
        $year = (int) $value;

        return $year >= 2000 && $year <= 2100 ? $year : (int) date('Y');
    }

    private function normalizeMonth(string $value): string
    {
        if (!preg_match('/^[0-9]{4}-[0-9]{2}$/', $value)) {
            return date('Y-m');
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value . '-01');

        if (!$date instanceof DateTimeImmutable || $date->format('Y-m') !== $value) {
            return date('Y-m');
        }

        return $value;
    }

    private function normalizeEmployeeStatus(string $value): string
    {
        $allowed = ['all', 'draft', 'active', 'on_leave', 'inactive', 'resigned', 'terminated', 'archived'];

        return in_array($value, $allowed, true) ? $value : 'all';
    }

    private function normalizeRequestStatus(string $value, string $default = 'all'): string
    {
        $allowed = ['all', 'draft', 'submitted', 'pending_manager', 'pending_hr', 'approved', 'rejected', 'cancelled', 'withdrawn'];

        return in_array($value, $allowed, true) ? $value : $default;
    }

    private function balanceStats(array $balances): array
    {
        $employees = [];
        $availableTotal = 0.0;
        $usedTotal = 0.0;

        foreach ($balances as $balance) {
            $employees[(int) ($balance['employee_id'] ?? 0)] = true;
            $availableTotal += (float) ($balance['closing_balance'] ?? 0);
            $usedTotal += (float) ($balance['used_amount'] ?? 0);
        }

        return [
            'employees' => count(array_filter(array_keys($employees))),
            'leave_types' => 0,
            'available_total' => $availableTotal,
            'used_total' => $usedTotal,
        ];
    }

    private function requestSummary(array $requests): array
    {
        $summary = [
            'total' => count($requests),
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0,
            'closed' => 0,
        ];

        foreach ($requests as $leaveRequest) {
            $status = (string) ($leaveRequest['status'] ?? '');

            if (in_array($status, ['draft', 'submitted', 'pending_manager', 'pending_hr'], true)) {
                $summary['pending']++;
                continue;
            }

            if ($status === 'approved') {
                $summary['approved']++;
                continue;
            }

            if ($status === 'rejected') {
                $summary['rejected']++;
                continue;
            }

            if (in_array($status, ['cancelled', 'withdrawn'], true)) {
                $summary['closed']++;
            }
        }

        return $summary;
    }

    private function buildCalendarWeeks(DateTimeImmutable $monthStart, DateTimeImmutable $monthEnd, array $requests): array
    {
        $eventsByDate = [];

        foreach ($requests as $leaveRequest) {
            $requestStart = DateTimeImmutable::createFromFormat('Y-m-d', (string) ($leaveRequest['start_date'] ?? ''));
            $requestEnd = DateTimeImmutable::createFromFormat('Y-m-d', (string) ($leaveRequest['end_date'] ?? ''));

            if (!$requestStart instanceof DateTimeImmutable || !$requestEnd instanceof DateTimeImmutable) {
                continue;
            }

            $cursor = $requestStart < $monthStart ? $monthStart : $requestStart;
            $lastDay = $requestEnd > $monthEnd ? $monthEnd : $requestEnd;

            while ($cursor <= $lastDay) {
                $dateKey = $cursor->format('Y-m-d');
                $eventsByDate[$dateKey][] = $leaveRequest;
                $cursor = $cursor->modify('+1 day');
            }
        }

        $weeks = [];
        $week = [];
        $gridStart = $monthStart->modify('monday this week');
        $gridEnd = $monthEnd->modify('sunday this week');

        for ($day = $gridStart; $day <= $gridEnd; $day = $day->modify('+1 day')) {
            $dateKey = $day->format('Y-m-d');
            $week[] = [
                'date' => $dateKey,
                'day_number' => $day->format('j'),
                'in_month' => $day->format('m') === $monthStart->format('m'),
                'is_today' => $dateKey === date('Y-m-d'),
                'events' => $eventsByDate[$dateKey] ?? [],
            ];

            if (count($week) === 7) {
                $weeks[] = $week;
                $week = [];
            }
        }

        return $weeks;
    }

    private function renderEmployeeLeaveDetail(
        int $employeeId,
        int $year,
        ?array $editLeaveRequest = null,
        array $editAttachments = []
    ): void {
        $employee = null;
        $balances = [];
        $leaveHistory = [];
        $leaveTypes = [];

        try {
            $employee = $this->repository->employeeLeaveProfile($employeeId);
            if ($employee === null) {
                Response::abort(404, 'Employee not found.');
            }

            $balances = $this->repository->balances($employeeId, $year);
            $leaveHistory = $this->repository->employeeLeaveHistory($employeeId);
            $leaveTypes = $this->repository->activeLeaveTypes();
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load employee leave detail: ' . $throwable->getMessage());
            $this->redirect('/leave/balances?year=' . $year);
        }

        $this->render('leaves.employee-detail', [
            'title' => 'Employee Leave Detail',
            'pageTitle' => 'Employee Leave Detail',
            'employee' => $employee,
            'balances' => $balances,
            'leaveHistory' => $leaveHistory,
            'leaveTypes' => $leaveTypes,
            'year' => $year,
            'editLeaveRequest' => $editLeaveRequest,
            'editAttachments' => $editAttachments,
        ]);
    }

    private function prepareLeaveAttachment(
        Request $request,
        array $leaveType,
        int $employeeId,
        string $redirectPath,
        array $data,
        bool $hasExistingAttachment = false
    ): ?array {
        $file = $this->normalizedUploadedFile($request->file('attachment'));
        $requiresAttachment = (int) ($leaveType['requires_attachment'] ?? 0) === 1;

        if ($file === null) {
            if ($requiresAttachment && !$hasExistingAttachment) {
                $this->invalid($redirectPath, $data, 'Please upload a supporting document for this leave type.');
            }

            return null;
        }

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->invalid($redirectPath, $data, 'Unable to read the uploaded attachment.');
        }

        if (($file['size'] ?? 0) > 10 * 1024 * 1024) {
            $this->invalid($redirectPath, $data, 'File size exceeds 10 MB limit.');
        }

        $detectedMimeType = $this->detectedMimeType((string) ($file['tmp_name'] ?? ''), (string) ($file['type'] ?? ''));
        $allowedMimes = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];

        if (!in_array($detectedMimeType, $allowedMimes, true)) {
            $this->invalid($redirectPath, $data, 'File type not allowed. Please upload PDF, JPG, PNG, DOC, or DOCX.');
        }

        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        $safeBaseName = preg_replace('/[^A-Za-z0-9._-]+/', '_', pathinfo((string) ($file['name'] ?? 'attachment'), PATHINFO_FILENAME)) ?: 'attachment';
        $storedFileName = uniqid('leave_', true) . ($extension !== '' ? '.' . $extension : '');
        $relativeDirectory = 'storage/uploads/leaves/employee_' . $employeeId;
        $absoluteDirectory = base_path($relativeDirectory);

        if (!is_dir($absoluteDirectory) && !mkdir($absoluteDirectory, 0775, true) && !is_dir($absoluteDirectory)) {
            throw new \RuntimeException('Unable to create the leave attachment directory.');
        }

        $absolutePath = $absoluteDirectory . DIRECTORY_SEPARATOR . $storedFileName;
        if (!$this->moveUploadedAttachment((string) $file['tmp_name'], $absolutePath)) {
            throw new \RuntimeException('Unable to store the uploaded leave attachment.');
        }

        return [
            'original_file_name' => ($safeBaseName . ($extension !== '' ? '.' . $extension : '')),
            'stored_file_name' => $storedFileName,
            'file_path' => $relativeDirectory . '/' . $storedFileName,
            'mime_type' => $detectedMimeType,
            'file_size' => (int) ($file['size'] ?? 0),
            'absolute_path' => $absolutePath,
        ];
    }

    private function normalizedUploadedFile(mixed $file): ?array
    {
        if (!is_array($file)) {
            return null;
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        $name = (string) ($file['name'] ?? '');
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($tmpName === '' && $name === '' && $error === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        return [
            'name' => $name,
            'tmp_name' => $tmpName,
            'type' => (string) ($file['type'] ?? ''),
            'size' => (int) ($file['size'] ?? 0),
            'error' => $error,
        ];
    }

    private function detectedMimeType(string $tmpName, string $fallback): string
    {
        if ($tmpName !== '' && is_file($tmpName)) {
            $mimeType = mime_content_type($tmpName);
            if (is_string($mimeType) && $mimeType !== '') {
                return $mimeType;
            }
        }

        return $fallback;
    }

    private function moveUploadedAttachment(string $tmpName, string $destination): bool
    {
        if ($tmpName === '' || !is_file($tmpName)) {
            return false;
        }

        if (is_uploaded_file($tmpName)) {
            return move_uploaded_file($tmpName, $destination);
        }

        return rename($tmpName, $destination);
    }

    private function validateCsrf(Request $request, string $redirectPath): void
    {
        if (!$this->app->csrf()->validate((string) $request->input('_token'))) {
            $this->app->session()->flash('error', 'Invalid form submission token.');
            $this->redirect($redirectPath);
        }
    }

    private function requireEmployeeProfile(): int
    {
        $user = $this->app->auth()->user() ?? [];
        $employeeId = (int) ($user['employee_id'] ?? 0);

        if ($employeeId <= 0) {
            $this->app->session()->flash('error', 'Your account is not linked to an employee profile yet.');
            $this->redirect('/dashboard');
        }

        return $employeeId;
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

        foreach (['is_paid', 'requires_balance', 'requires_attachment', 'requires_hr_approval', 'allow_half_day', 'carry_forward_allowed', 'is_recurring'] as $toggle) {
            if (array_key_exists($toggle, $data)) {
                $data[$toggle] = (int) $data[$toggle];
            }
        }

        return $data;
    }

    private function validateHolidayPayload(array $data, array $companies, array $branches): void
    {
        foreach (['company_id', 'name', 'holiday_date', 'holiday_type'] as $field) {
            if (($data[$field] ?? '') === '') {
                $this->invalid('/admin/leave/holidays', $data, 'Please complete all required holiday fields.');
            }
        }

        $companyId = (int) ($data['company_id'] ?? 0);
        $branchId = ($data['branch_id'] ?? '') === '' ? 0 : (int) $data['branch_id'];
        $holidayType = (string) ($data['holiday_type'] ?? '');
        $isRecurring = (int) ($data['is_recurring'] ?? 0);
        $name = trim((string) ($data['name'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));

        if ($companyId <= 0 || !isset($companies[$companyId])) {
            $this->invalid('/admin/leave/holidays', $data, 'Please select a valid company.');
        }

        if ($branchId < 0) {
            $this->invalid('/admin/leave/holidays', $data, 'Please select a valid branch.');
        }

        if ($branchId > 0 && !isset($branches[$branchId])) {
            $this->invalid('/admin/leave/holidays', $data, 'Please select a valid branch.');
        }

        if ($branchId > 0 && (int) ($branches[$branchId]['company_id'] ?? 0) !== $companyId) {
            $this->invalid('/admin/leave/holidays', $data, 'The selected branch does not belong to the selected company.');
        }

        if (!in_array($holidayType, ['public', 'company', 'branch'], true)) {
            $this->invalid('/admin/leave/holidays', $data, 'Please select a valid holiday type.');
        }

        if ($holidayType === 'branch' && $branchId <= 0) {
            $this->invalid('/admin/leave/holidays', $data, 'Branch holidays require a branch selection.');
        }

        if (!$this->isValidDate((string) ($data['holiday_date'] ?? ''))) {
            $this->invalid('/admin/leave/holidays', $data, 'Please provide a valid holiday date.');
        }

        if ($name === '' || strlen($name) > 150) {
            $this->invalid('/admin/leave/holidays', $data, 'Holiday name is required and must be 150 characters or fewer.');
        }

        if ($description !== '' && strlen($description) > 255) {
            $this->invalid('/admin/leave/holidays', $data, 'Holiday description must be 255 characters or fewer.');
        }

        if (!in_array($isRecurring, [0, 1], true)) {
            $this->invalid('/admin/leave/holidays', $data, 'Please select a valid recurring option.');
        }
    }

    private function validatePolicyPayload(array $data, array $companies): array
    {
        foreach (['name', 'accrual_frequency', 'is_active'] as $field) {
            if (($data[$field] ?? '') === '') {
                $this->invalid('/admin/leave/policies', $data, 'Please complete all required leave policy fields.');
            }
        }

        $name = trim((string) ($data['name'] ?? ''));
        $companyId = ($data['company_id'] ?? '') === '' ? null : (int) $data['company_id'];
        $description = trim((string) ($data['description'] ?? ''));
        $accrualFrequency = (string) ($data['accrual_frequency'] ?? '');
        $isActive = (int) ($data['is_active'] ?? -1);
        $accrualOptions = $this->policyAccrualOptions();

        if ($companyId !== null && ($companyId <= 0 || !isset($companies[$companyId]))) {
            $this->invalid('/admin/leave/policies', $data, 'Please select a valid company.');
        }

        if (!isset($accrualOptions[$accrualFrequency])) {
            $this->invalid('/admin/leave/policies', $data, 'Please select a valid accrual frequency.');
        }

        if (!in_array($isActive, [0, 1], true)) {
            $this->invalid('/admin/leave/policies', $data, 'Please select a valid policy status.');
        }

        if ($name === '' || strlen($name) > 150) {
            $this->invalid('/admin/leave/policies', $data, 'Policy name must be between 1 and 150 characters.');
        }

        if ($description !== '' && strlen($description) > 255) {
            $this->invalid('/admin/leave/policies', $data, 'Policy description must be 255 characters or fewer.');
        }

        return [
            'name' => $name,
            'company_id' => $companyId,
            'description' => $description,
            'accrual_frequency' => $accrualFrequency,
            'is_active' => $isActive,
        ];
    }

    private function validatePolicyRulePayload(
        array $data,
        array $policies,
        array $leaveTypes,
        array $departments,
        array $jobTitles
    ): array {
        foreach (['leave_policy_id', 'leave_type_id', 'annual_allocation', 'accrual_rate_monthly', 'carry_forward_limit', 'min_service_months'] as $field) {
            if (($data[$field] ?? '') === '') {
                $this->invalid('/admin/leave/policies', $data, 'Please complete all required leave policy rule fields.');
            }
        }

        $policyId = (int) ($data['leave_policy_id'] ?? 0);
        $leaveTypeId = (int) ($data['leave_type_id'] ?? 0);
        $departmentId = ($data['department_id'] ?? '') === '' ? null : (int) $data['department_id'];
        $jobTitleId = ($data['job_title_id'] ?? '') === '' ? null : (int) $data['job_title_id'];
        $employmentType = ($data['employment_type'] ?? '') === '' ? null : (string) $data['employment_type'];
        $minServiceMonthsRaw = (string) ($data['min_service_months'] ?? '');

        if ($policyId <= 0 || !isset($policies[$policyId])) {
            $this->invalid('/admin/leave/policies', $data, 'Please select a valid leave policy.');
        }

        if ($leaveTypeId <= 0 || !isset($leaveTypes[$leaveTypeId])) {
            $this->invalid('/admin/leave/policies', $data, 'Please select a valid active leave type.');
        }

        if ($departmentId !== null && ($departmentId <= 0 || !isset($departments[$departmentId]))) {
            $this->invalid('/admin/leave/policies', $data, 'Please select a valid department.');
        }

        if ($jobTitleId !== null && ($jobTitleId <= 0 || !isset($jobTitles[$jobTitleId]))) {
            $this->invalid('/admin/leave/policies', $data, 'Please select a valid job title.');
        }

        if ($employmentType !== null && !isset($this->policyEmploymentTypeOptions()[$employmentType])) {
            $this->invalid('/admin/leave/policies', $data, 'Please select a valid employment type.');
        }

        $policyCompanyId = (int) ($policies[$policyId]['company_id'] ?? 0);
        if ($departmentId !== null && $policyCompanyId > 0 && (int) ($departments[$departmentId]['company_id'] ?? 0) !== $policyCompanyId) {
            $this->invalid('/admin/leave/policies', $data, 'The selected department does not belong to the leave policy company scope.');
        }

        $annualAllocation = $this->validatedPolicyRuleDecimal($data['annual_allocation'] ?? null, $data, 'Annual allocation');
        $accrualRateMonthly = $this->validatedPolicyRuleDecimal($data['accrual_rate_monthly'] ?? null, $data, 'Monthly accrual rate');
        $carryForwardLimit = $this->validatedPolicyRuleDecimal($data['carry_forward_limit'] ?? null, $data, 'Carry forward limit');
        $maxConsecutiveDays = $this->validatedPolicyRuleDecimal($data['max_consecutive_days'] ?? null, $data, 'Max consecutive days', true);

        if ($maxConsecutiveDays !== null && $maxConsecutiveDays <= 0) {
            $this->invalid('/admin/leave/policies', $data, 'Max consecutive days must be greater than zero when provided.');
        }

        if (!preg_match('/^[0-9]+$/', $minServiceMonthsRaw)) {
            $this->invalid('/admin/leave/policies', $data, 'Minimum service months must be a whole number zero or greater.');
        }

        return [
            'leave_policy_id' => $policyId,
            'leave_type_id' => $leaveTypeId,
            'department_id' => $departmentId,
            'job_title_id' => $jobTitleId,
            'employment_type' => $employmentType,
            'annual_allocation' => $annualAllocation,
            'accrual_rate_monthly' => $accrualRateMonthly,
            'carry_forward_limit' => $carryForwardLimit,
            'max_consecutive_days' => $maxConsecutiveDays,
            'min_service_months' => (int) $minServiceMonthsRaw,
        ];
    }

    private function validateWeekendPayload(array $data, array $companies, array $branches): array
    {
        foreach (['company_id', 'day_of_week', 'is_weekend'] as $field) {
            if (($data[$field] ?? '') === '') {
                $this->invalid('/admin/leave/weekends', $data, 'Please complete all required weekend setting fields.');
            }
        }

        $companyId = (int) ($data['company_id'] ?? 0);
        $branchId = ($data['branch_id'] ?? '') === '' ? null : (int) $data['branch_id'];
        $dayOfWeek = (int) ($data['day_of_week'] ?? 0);
        $isWeekend = (int) ($data['is_weekend'] ?? -1);

        if ($companyId <= 0 || !isset($companies[$companyId])) {
            $this->invalid('/admin/leave/weekends', $data, 'Please select a valid company.');
        }

        if ($branchId !== null && ($branchId <= 0 || !isset($branches[$branchId]))) {
            $this->invalid('/admin/leave/weekends', $data, 'Please select a valid branch.');
        }

        if ($branchId !== null && (int) ($branches[$branchId]['company_id'] ?? 0) !== $companyId) {
            $this->invalid('/admin/leave/weekends', $data, 'The selected branch does not belong to the selected company.');
        }

        if (!isset($this->weekendDayOptions()[$dayOfWeek])) {
            $this->invalid('/admin/leave/weekends', $data, 'Please select a valid weekday.');
        }

        if (!in_array($isWeekend, [0, 1], true)) {
            $this->invalid('/admin/leave/weekends', $data, 'Please select a valid weekend flag.');
        }

        return [
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'day_of_week' => $dayOfWeek,
            'is_weekend' => $isWeekend,
        ];
    }

    private function policyAccrualOptions(): array
    {
        return [
            'none' => 'None',
            'monthly' => 'Monthly',
            'quarterly' => 'Quarterly',
            'yearly' => 'Yearly',
        ];
    }

    private function policyEmploymentTypeOptions(): array
    {
        return [
            'full_time' => 'Full Time',
            'part_time' => 'Part Time',
            'contract' => 'Contract',
            'intern' => 'Intern',
            'temporary' => 'Temporary',
        ];
    }

    private function validateLeaveRequest(
        int $employeeId,
        array $data,
        array $leaveType,
        string $redirectPath = '/leave/request',
        bool $skipNoticeValidation = false,
        ?array $existingApprovedRequest = null
    ): float
    {
        foreach (['leave_type_id', 'start_date', 'end_date', 'reason'] as $field) {
            if (($data[$field] ?? '') === '') {
                $this->invalid($redirectPath, $data, 'Please complete all required leave request fields.');
            }
        }

        // Attachment validation is now handled in the store() method
        // No need to block here anymore

        $startDate = DateTimeImmutable::createFromFormat('Y-m-d', (string) $data['start_date']);
        $endDate = DateTimeImmutable::createFromFormat('Y-m-d', (string) $data['end_date']);

        if (!$startDate instanceof DateTimeImmutable || !$endDate instanceof DateTimeImmutable) {
            $this->invalid($redirectPath, $data, 'Please provide valid leave dates.');
        }

        if ($endDate < $startDate) {
            $this->invalid($redirectPath, $data, 'End date cannot be earlier than start date.');
        }

        $startSession = (string) ($data['start_session'] ?? 'full');
        $endSession = (string) ($data['end_session'] ?? 'full');
        $validSessions = ['full', 'first_half', 'second_half'];

        if (!in_array($startSession, $validSessions, true) || !in_array($endSession, $validSessions, true)) {
            $this->invalid($redirectPath, $data, 'Please select valid leave day sessions.');
        }

        if ((int) ($leaveType['allow_half_day'] ?? 0) !== 1 && ($startSession !== 'full' || $endSession !== 'full')) {
            $this->invalid($redirectPath, $data, 'This leave type does not allow half-day requests.');
        }

        if ($startDate->format('Y-m-d') === $endDate->format('Y-m-d')
            && $startSession === 'second_half'
            && $endSession === 'first_half') {
            $this->invalid($redirectPath, $data, 'For a same-day request, the end session cannot be earlier than the start session.');
        }

        $daysRequested = $this->calculateDaysRequested($startDate, $endDate, $startSession, $endSession);

        if (($leaveType['max_days_per_request'] ?? null) !== null && $daysRequested > (float) $leaveType['max_days_per_request']) {
            $this->invalid($redirectPath, $data, 'This leave request exceeds the allowed maximum per request.');
        }

        $noticeDaysRequired = (int) ($leaveType['notice_days_required'] ?? 0);
        $today = new DateTimeImmutable(date('Y-m-d'));

        if (!$skipNoticeValidation) {
            if ($noticeDaysRequired > 0 && $startDate->diff($today)->invert === 0) {
                $this->invalid($redirectPath, $data, 'This leave type requires advance notice before the start date.');
            }

            if ($noticeDaysRequired > 0 && (int) $today->diff($startDate)->days < $noticeDaysRequired) {
                $this->invalid($redirectPath, $data, 'This leave type requires more advance notice before submission.');
            }
        }

        if ((string) ($leaveType['code'] ?? '') === 'annual_leave') {
            $employee = $this->repository->findEmployee($employeeId);
            $joiningDateValue = trim((string) ($employee['joining_date'] ?? ''));

            if ($joiningDateValue !== '') {
                $joiningDate = DateTimeImmutable::createFromFormat('Y-m-d', $joiningDateValue);

                if ($joiningDate instanceof DateTimeImmutable) {
                    $annualEligibilityDate = $joiningDate->modify('+3 months');

                    if ($startDate < $annualEligibilityDate) {
                        $this->invalid(
                            $redirectPath,
                            $data,
                            'Annual leave is available only after 3 months from the joining date.'
                        );
                    }
                }
            }
        }

        if ((int) ($leaveType['requires_balance'] ?? 0) === 1) {
            $targetYear = (int) $startDate->format('Y');
            $balance = $this->repository->currentBalance($employeeId, (int) $leaveType['id'], $targetYear);
            $balance += $this->reusableBalanceDaysForEdit($existingApprovedRequest, (int) $leaveType['id'], $targetYear);

            if ($daysRequested > $balance) {
                $this->invalid($redirectPath, $data, 'Insufficient leave balance for this request.');
            }
        }

        return $daysRequested;
    }

    private function calculateDaysRequested(
        DateTimeImmutable $startDate,
        DateTimeImmutable $endDate,
        string $startSession,
        string $endSession
    ): float {
        $days = (float) $startDate->diff($endDate)->days + 1;

        if ($startDate->format('Y-m-d') === $endDate->format('Y-m-d')) {
            if ($startSession === $endSession && in_array($startSession, ['first_half', 'second_half'], true)) {
                return 0.5;
            }

            return 1.0;
        }

        if ($startSession !== 'full') {
            $days -= 0.5;
        }

        if ($endSession !== 'full') {
            $days -= 0.5;
        }

        return max(0.5, $days);
    }

    private function reusableBalanceDaysForEdit(?array $existingApprovedRequest, int $leaveTypeId, int $targetYear): float
    {
        if ($existingApprovedRequest === null || (string) ($existingApprovedRequest['status'] ?? '') !== 'approved') {
            return 0.0;
        }

        $existingYear = (int) date('Y', strtotime((string) ($existingApprovedRequest['start_date'] ?? date('Y-m-d'))));
        $existingLeaveTypeId = (int) ($existingApprovedRequest['leave_type_id'] ?? 0);
        $existingRequiresBalance = (int) ($existingApprovedRequest['requires_balance'] ?? 0) === 1;

        if (!$existingRequiresBalance) {
            return 0.0;
        }

        if ($existingLeaveTypeId !== $leaveTypeId || $existingYear !== $targetYear) {
            return 0.0;
        }

        return (float) ($existingApprovedRequest['days_requested'] ?? 0);
    }

    private function optionRowsById(array $rows): array
    {
        $mapped = [];

        foreach ($rows as $row) {
            $mapped[(int) ($row['id'] ?? 0)] = $row;
        }

        return $mapped;
    }

    private function validatedPolicyRuleDecimal(mixed $value, array $data, string $label, bool $nullable = false): ?float
    {
        if ($value === null || $value === '') {
            if ($nullable) {
                return null;
            }

            $this->invalid('/admin/leave/policies', $data, $label . ' is required.');
        }

        if (!is_numeric($value)) {
            $this->invalid('/admin/leave/policies', $data, $label . ' must be a valid number.');
        }

        $number = (float) $value;

        if ($number < 0) {
            $this->invalid('/admin/leave/policies', $data, $label . ' cannot be negative.');
        }

        return $number;
    }

    public function exportRequestsExcel(Request $request): void
    {
        $scope = $this->leaveScope();
        $search = trim((string) $request->input('q', ''));
        $status = $this->normalizeRequestStatus((string) $request->input('status', 'all'), 'all');
        $leaveTypeId = (int) $request->input('leave_type_id', 0);

        try {
            $requests = $this->repository->listRequests($scope, $search, $status, $leaveTypeId);
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Leave Requests');

            $sheet->mergeCells('A1:J1');
            $sheet->mergeCells('A2:J2');
            $sheet->mergeCells('A3:J3');
            $sheet->setCellValue('A1', 'Leave Requests');
            $sheet->setCellValue('A2', Branding::appName() . ' | Generated ' . date('d M Y, H:i'));
            $sheet->setCellValue('A3', 'Total requests: ' . count($requests));
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(18)->getColor()->setRGB('111827');
            $sheet->getStyle('A2:A3')->getFont()->setSize(10)->getColor()->setRGB('64748B');

            $headers = ['Employee', 'Code', 'Department', 'Leave Type', 'Start Date', 'End Date',
                'Days', 'Status', 'Submitted', 'Reason'];
            $sheet->fromArray($headers, null, 'A5');
            $headerStyle = $sheet->getStyle('A5:J5');
            $headerStyle->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $headerStyle->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('FF3D33');

            $row = 6;
            foreach ($requests as $r) {
                $sheet->fromArray([
                    $r['employee_name'] ?? '', $r['employee_code'] ?? '', $r['department_name'] ?? '',
                    $r['leave_type_name'] ?? '', $r['start_date'] ?? '', $r['end_date'] ?? '',
                    $r['days_requested'] ?? '', $r['status'] ?? '',
                    $r['submitted_at'] ?? $r['created_at'] ?? '', $r['reason'] ?? '',
                ], null, 'A' . $row);
                if ($row % 2 === 0) {
                    $sheet->getStyle('A' . $row . ':J' . $row)->getFill()
                        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('F8FAFC');
                }
                $row++;
            }

            $lastRow = max(6, $row - 1);
            $sheet->freezePane('A6');
            $sheet->setAutoFilter('A5:J' . $lastRow);
            $sheet->getStyle('A5:J' . $lastRow)->getBorders()->getAllBorders()
                ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)
                ->getColor()->setRGB('E2E8F0');

            foreach (range('A', 'J') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="leave_requests_' . date('Y-m-d') . '.xlsx"');
            header('Cache-Control: max-age=0');

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
            exit;
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Export failed: ' . $throwable->getMessage());
            $this->redirect('/leave/requests');
        }
    }

    public function exportRequestsPdf(Request $request): void
    {
        $scope = $this->leaveScope();
        $search = trim((string) $request->input('q', ''));
        $status = $this->normalizeRequestStatus((string) $request->input('status', 'all'), 'all');
        $leaveTypeId = (int) $request->input('leave_type_id', 0);

        try {
            $requests = $this->repository->listRequests($scope, $search, $status, $leaveTypeId);
            $pdf = new \TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
            $pdf->SetCreator('HR System');
            $pdf->SetTitle('Leave Requests');
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(true);
            $pdf->SetMargins(12, 14, 12);
            $pdf->SetAutoPageBreak(true, 15);
            $pdf->AddPage();
            $pdf->SetFont('helvetica', 'B', 15);
            $pdf->SetTextColor(17, 24, 39);
            $pdf->Cell(0, 7, 'Leave Requests', 0, 1, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->SetTextColor(100, 116, 139);
            $pdf->Cell(0, 6, Branding::appName() . ' | Generated ' . date('d M Y, H:i') . ' | Total requests: ' . count($requests), 0, 1, 'L');
            $pdf->SetDrawColor(255, 61, 51);
            $pdf->SetLineWidth(0.7);
            $pdf->Line(12, 30, 285, 30);
            $pdf->Ln(8);

            $html = '<style>
                table.export { border-collapse: collapse; font-size: 8px; color: #111827; }
                table.export th { background-color:#ff3d33;color:#ffffff;font-weight:bold;border:1px solid #ff3d33; }
                table.export td { border:1px solid #e2e8f0; }
                tr.alt td { background-color:#f8fafc; }
            </style><table class="export" cellpadding="5" cellspacing="0">
            <thead><tr>
                <th>Employee</th><th>Leave Type</th><th>Start</th><th>End</th>
                <th>Days</th><th>Status</th><th>Submitted</th>
            </tr></thead><tbody>';

            $i = 0;
            foreach ($requests as $r) {
                $sBg = ($r['status'] ?? '') === 'approved' ? '#D4EDDA' : (in_array($r['status'] ?? '', ['rejected', 'cancelled'], true) ? '#F8D7DA' : '#FFF3CD');
                $html .= '<tr' . ($i % 2 === 1 ? ' class="alt"' : '') . '>
                    <td>' . htmlspecialchars($r['employee_name'] ?? '') . '</td>
                    <td>' . htmlspecialchars($r['leave_type_name'] ?? '') . '</td>
                    <td>' . htmlspecialchars($r['start_date'] ?? '') . '</td>
                    <td>' . htmlspecialchars($r['end_date'] ?? '') . '</td>
                    <td>' . htmlspecialchars((string) ($r['days_requested'] ?? '')) . '</td>
                    <td style="background-color:' . $sBg . ';">' . htmlspecialchars($r['status'] ?? '') . '</td>
                    <td>' . htmlspecialchars($r['submitted_at'] ?? $r['created_at'] ?? '') . '</td>
                </tr>';
                $i++;
            }
            $html .= '</tbody></table>';

            $pdf->SetFont('helvetica', '', 8);
            $pdf->writeHTML($html, true, false, true, false, '');

            $pdf->Output('leave_requests_' . date('Y-m-d') . '.pdf', 'D');
            exit;
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'PDF export failed: ' . $throwable->getMessage());
            $this->redirect('/leave/requests');
        }
    }

    private function isValidDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $value;
    }

    private function invalid(string $redirectPath, array $data, string $message): void
    {
        $this->app->session()->flash('error', $message);
        $this->app->session()->flash('old_input', $data);
        $this->redirect($redirectPath);
    }
}
