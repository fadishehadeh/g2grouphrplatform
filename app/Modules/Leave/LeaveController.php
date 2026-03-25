<?php

declare(strict_types=1);

namespace App\Modules\Leave;

use App\Core\Application;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
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

    public function create(Request $request): void
    {
        $employeeId = $this->requireEmployeeProfile();
        $leaveTypes = [];
        $balances = [];

        try {
            $leaveTypes = $this->repository->activeLeaveTypes();
            $balances = $this->repository->balances($employeeId, (int) date('Y'));
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load leave request options: ' . $throwable->getMessage());
        }

        $this->render('leaves.request', [
            'title' => 'Request Leave',
            'pageTitle' => 'Request Leave',
            'leaveTypes' => $leaveTypes,
            'balances' => $balances,
        ]);
    }

    public function store(Request $request): void
    {
        $this->validateCsrf($request, '/leave/request');
        $employeeId = $this->requireEmployeeProfile();
        $data = $this->sanitized($request);

        try {
            $leaveType = $this->repository->findLeaveType((int) ($data['leave_type_id'] ?? 0));

            if ($leaveType === null || ($leaveType['status'] ?? 'inactive') !== 'active') {
                $this->invalid('/leave/request', $data, 'Please select a valid active leave type.');
            }

            $daysRequested = $this->validateLeaveRequest($employeeId, $data, $leaveType);
            $data['days_requested'] = $daysRequested;
            $this->repository->createLeaveRequest($data, $employeeId, $this->app->auth()->id());

            $this->app->session()->flash('success', 'Leave request submitted successfully.');
            $this->redirect('/leave/my');
        } catch (Throwable $throwable) {
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

        $this->redirect('/leave/approvals');
    }

    public function reject(Request $request, string $id): void
    {
        $this->validateCsrf($request, '/leave/approvals');
        $requestId = (int) $id;
        $queue = (string) $request->input('queue', '');
        $reason = trim((string) $request->input('comments', ''));
        $user = $this->app->auth()->user() ?? [];

        if ($reason === '') {
            $this->app->session()->flash('error', 'A rejection reason is required.');
            $this->redirect('/leave/approvals');
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

        $this->redirect('/leave/approvals');
    }

    public function balances(Request $request): void
    {
        $scope = $this->leaveScope();
        $search = trim((string) $request->input('q', ''));
        $year = $this->normalizeYear((string) $request->input('year', (string) date('Y')));
        $status = $this->normalizeEmployeeStatus((string) $request->input('status', 'all'));
        $balances = [];
        $stats = [
            'employees' => 0,
            'leave_types' => 0,
            'available_total' => 0.0,
            'used_total' => 0.0,
        ];

        try {
            $balances = $this->repository->balanceOverview($scope, $year, $search, $status);
            $stats = $this->balanceStats($balances);
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
        ]);
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

        $this->render('leaves.show', [
            'title' => 'Leave Request Detail',
            'pageTitle' => 'Leave Request Detail',
            'leaveRequest' => $leaveRequest,
            'approvalTrail' => $approvalTrail,
            'attachments' => $attachments,
            'scope' => $scope,
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

    public function storeType(Request $request): void
    {
        $this->validateCsrf($request, '/admin/leave/types');
        $data = $this->sanitized($request);

        foreach (['name', 'code'] as $field) {
            if (($data[$field] ?? '') === '') {
                $this->invalid('/admin/leave/types', $data, 'Please provide the required leave type fields.');
            }
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
        $leaveTypes = [];
        $availableTotal = 0.0;
        $usedTotal = 0.0;

        foreach ($balances as $balance) {
            $employees[(int) ($balance['employee_id'] ?? 0)] = true;
            $leaveTypes[(int) ($balance['leave_type_id'] ?? 0)] = true;
            $availableTotal += (float) ($balance['closing_balance'] ?? 0);
            $usedTotal += (float) ($balance['used_amount'] ?? 0);
        }

        return [
            'employees' => count(array_filter(array_keys($employees))),
            'leave_types' => count(array_filter(array_keys($leaveTypes))),
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

    private function validateLeaveRequest(int $employeeId, array $data, array $leaveType): float
    {
        foreach (['leave_type_id', 'start_date', 'end_date', 'reason'] as $field) {
            if (($data[$field] ?? '') === '') {
                $this->invalid('/leave/request', $data, 'Please complete all required leave request fields.');
            }
        }

        if ((int) ($leaveType['requires_attachment'] ?? 0) === 1) {
            $this->invalid('/leave/request', $data, 'This leave type requires a supporting attachment and is not yet available in self-service.');
        }

        $startDate = DateTimeImmutable::createFromFormat('Y-m-d', (string) $data['start_date']);
        $endDate = DateTimeImmutable::createFromFormat('Y-m-d', (string) $data['end_date']);

        if (!$startDate instanceof DateTimeImmutable || !$endDate instanceof DateTimeImmutable) {
            $this->invalid('/leave/request', $data, 'Please provide valid leave dates.');
        }

        if ($endDate < $startDate) {
            $this->invalid('/leave/request', $data, 'End date cannot be earlier than start date.');
        }

        $startSession = (string) ($data['start_session'] ?? 'full');
        $endSession = (string) ($data['end_session'] ?? 'full');
        $validSessions = ['full', 'first_half', 'second_half'];

        if (!in_array($startSession, $validSessions, true) || !in_array($endSession, $validSessions, true)) {
            $this->invalid('/leave/request', $data, 'Please select valid leave day sessions.');
        }

        if ((int) ($leaveType['allow_half_day'] ?? 0) !== 1 && ($startSession !== 'full' || $endSession !== 'full')) {
            $this->invalid('/leave/request', $data, 'This leave type does not allow half-day requests.');
        }

        if ($startDate->format('Y-m-d') === $endDate->format('Y-m-d')
            && $startSession === 'second_half'
            && $endSession === 'first_half') {
            $this->invalid('/leave/request', $data, 'For a same-day request, the end session cannot be earlier than the start session.');
        }

        $daysRequested = $this->calculateDaysRequested($startDate, $endDate, $startSession, $endSession);

        if (($leaveType['max_days_per_request'] ?? null) !== null && $daysRequested > (float) $leaveType['max_days_per_request']) {
            $this->invalid('/leave/request', $data, 'This leave request exceeds the allowed maximum per request.');
        }

        $noticeDaysRequired = (int) ($leaveType['notice_days_required'] ?? 0);
        $today = new DateTimeImmutable(date('Y-m-d'));

        if ($noticeDaysRequired > 0 && $startDate->diff($today)->invert === 0) {
            $this->invalid('/leave/request', $data, 'This leave type requires advance notice before the start date.');
        }

        if ($noticeDaysRequired > 0 && (int) $today->diff($startDate)->days < $noticeDaysRequired) {
            $this->invalid('/leave/request', $data, 'This leave type requires more advance notice before submission.');
        }

        if ((int) ($leaveType['requires_balance'] ?? 0) === 1) {
            $balance = $this->repository->currentBalance($employeeId, (int) $leaveType['id'], (int) $startDate->format('Y'));

            if ($daysRequested > $balance) {
                $this->invalid('/leave/request', $data, 'Insufficient leave balance for this request.');
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