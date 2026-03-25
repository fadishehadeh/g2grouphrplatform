<?php

declare(strict_types=1);

namespace App\Modules\Reports;

use App\Core\Application;
use App\Core\Controller;
use App\Core\Request;
use Throwable;

final class ReportController extends Controller
{
    private ReportRepository $repository;
    private const EMPLOYEE_STATUSES = ['all', 'draft', 'active', 'on_leave', 'inactive', 'resigned', 'terminated', 'archived'];
    private const LEAVE_STATUSES = ['all', 'draft', 'submitted', 'pending_manager', 'pending_hr', 'approved', 'rejected', 'cancelled', 'withdrawn'];
    private const EXIT_STATUSES = ['all', 'draft', 'pending', 'in_progress', 'completed', 'cancelled'];
    private const EXIT_TYPES = ['all', 'resignation', 'termination', 'retirement', 'contract_end', 'absconding', 'other'];
    private const DOCUMENT_FILTERS = ['all', 'expiring', 'expired', 'missing_expiry'];

    public function __construct(Application $app)
    {
        parent::__construct($app);
        $this->repository = new ReportRepository($this->app->database());
    }

    public function index(Request $request): void
    {
        ['canHr' => $canHr, 'scopeEmployeeId' => $scopeEmployeeId, 'scopeLabel' => $scopeLabel] = $this->reportScope();

        try {
            $overview = $this->repository->overview($scopeEmployeeId);
            $departments = $this->repository->departmentDistribution($scopeEmployeeId);
            $leaveSummary = $this->repository->leaveSummary($scopeEmployeeId);
            $documentExpiry = $this->repository->documentExpirySummary($scopeEmployeeId, 30);
            $recentJoiners = $this->repository->recentJoiners($scopeEmployeeId);
            $recentExits = $this->repository->recentExits($scopeEmployeeId);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load reports: ' . $throwable->getMessage());
            $overview = ['total_employees' => 0, 'active_employees' => 0, 'new_joiners_30' => 0, 'pending_leave' => 0, 'documents_expiring_30' => 0, 'upcoming_exits_30' => 0];
            $departments = [];
            $leaveSummary = [];
            $documentExpiry = [];
            $recentJoiners = [];
            $recentExits = [];
        }

        $this->render('reports.index', [
            'title' => 'Reports Overview',
            'pageTitle' => $canHr ? 'HR Reports' : 'Team Reports',
            'canHr' => $canHr,
            'scopeLabel' => $scopeLabel,
            'overview' => $overview,
            'departments' => $departments,
            'leaveSummary' => $leaveSummary,
            'documentExpiry' => $documentExpiry,
            'recentJoiners' => $recentJoiners,
            'recentExits' => $recentExits,
        ]);
    }

    public function headcount(Request $request): void
    {
        ['canHr' => $canHr, 'scopeEmployeeId' => $scopeEmployeeId, 'scopeLabel' => $scopeLabel] = $this->reportScope();
        $search = trim((string) $request->input('q', ''));
        $status = $this->normalizeEnum($request->input('status', 'all'), self::EMPLOYEE_STATUSES, 'all');

        try {
            $overview = $this->repository->overview($scopeEmployeeId);
            $statusDistribution = $this->repository->employeeStatusDistribution($scopeEmployeeId);
            $employees = $this->repository->headcountEmployees($search, $status, $scopeEmployeeId);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load headcount report: ' . $throwable->getMessage());
            $overview = ['total_employees' => 0, 'active_employees' => 0, 'new_joiners_30' => 0, 'pending_leave' => 0, 'documents_expiring_30' => 0, 'upcoming_exits_30' => 0];
            $statusDistribution = [];
            $employees = [];
        }

        $this->render('reports.headcount', [
            'title' => 'Headcount Report',
            'pageTitle' => $canHr ? 'Headcount Report' : 'Team Headcount Report',
            'canHr' => $canHr,
            'scopeLabel' => $scopeLabel,
            'overview' => $overview,
            'statusDistribution' => $statusDistribution,
            'employees' => $employees,
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function department(Request $request): void
    {
        ['canHr' => $canHr, 'scopeEmployeeId' => $scopeEmployeeId, 'scopeLabel' => $scopeLabel] = $this->reportScope();

        try {
            $overview = $this->repository->overview($scopeEmployeeId);
            $departments = $this->repository->departmentReport($scopeEmployeeId);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load department report: ' . $throwable->getMessage());
            $overview = ['total_employees' => 0, 'active_employees' => 0, 'new_joiners_30' => 0, 'pending_leave' => 0, 'documents_expiring_30' => 0, 'upcoming_exits_30' => 0];
            $departments = [];
        }

        $this->render('reports.department', [
            'title' => 'Department Report',
            'pageTitle' => $canHr ? 'Department Distribution' : 'Team Department Distribution',
            'canHr' => $canHr,
            'scopeLabel' => $scopeLabel,
            'overview' => $overview,
            'departments' => $departments,
        ]);
    }

    public function leaveUsage(Request $request): void
    {
        ['canHr' => $canHr, 'scopeEmployeeId' => $scopeEmployeeId, 'scopeLabel' => $scopeLabel] = $this->reportScope();
        $search = trim((string) $request->input('q', ''));
        $status = $this->normalizeEnum($request->input('status', 'all'), self::LEAVE_STATUSES, 'all');
        $fromDate = $this->normalizeDate($request->input('from_date', ''));
        $toDate = $this->normalizeDate($request->input('to_date', ''));

        try {
            $summary = $this->repository->leaveUsageSummary($scopeEmployeeId, $fromDate, $toDate);
            $requests = $this->repository->leaveUsageReport($search, $status, $fromDate, $toDate, $scopeEmployeeId);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load leave usage report: ' . $throwable->getMessage());
            $summary = [];
            $requests = [];
        }

        $this->render('reports.leave-usage', [
            'title' => 'Leave Usage Report',
            'pageTitle' => $canHr ? 'Leave Usage Report' : 'Team Leave Usage Report',
            'canHr' => $canHr,
            'scopeLabel' => $scopeLabel,
            'summary' => $summary,
            'requests' => $requests,
            'search' => $search,
            'status' => $status,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
        ]);
    }

    public function newJoiners(Request $request): void
    {
        ['canHr' => $canHr, 'scopeEmployeeId' => $scopeEmployeeId, 'scopeLabel' => $scopeLabel] = $this->reportScope();
        $search = trim((string) $request->input('q', ''));
        $fromDate = $this->normalizeDate($request->input('from_date', ''));
        $toDate = $this->normalizeDate($request->input('to_date', ''));

        try {
            $joiners = $this->repository->joinerReport($search, $fromDate, $toDate, $scopeEmployeeId);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load new joiners report: ' . $throwable->getMessage());
            $joiners = [];
        }

        $this->render('reports.new-joiners', [
            'title' => 'New Joiners Report',
            'pageTitle' => $canHr ? 'New Joiners Report' : 'Team New Joiners Report',
            'canHr' => $canHr,
            'scopeLabel' => $scopeLabel,
            'joiners' => $joiners,
            'search' => $search,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
        ]);
    }

    public function exits(Request $request): void
    {
        ['canHr' => $canHr, 'scopeEmployeeId' => $scopeEmployeeId, 'scopeLabel' => $scopeLabel] = $this->reportScope();
        $search = trim((string) $request->input('q', ''));
        $status = $this->normalizeEnum($request->input('status', 'all'), self::EXIT_STATUSES, 'all');
        $recordType = $this->normalizeEnum($request->input('record_type', 'all'), self::EXIT_TYPES, 'all');
        $fromDate = $this->normalizeDate($request->input('from_date', ''));
        $toDate = $this->normalizeDate($request->input('to_date', ''));

        try {
            $records = $this->repository->exitReport($search, $status, $recordType, $fromDate, $toDate, $scopeEmployeeId);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load exits report: ' . $throwable->getMessage());
            $records = [];
        }

        $this->render('reports.exits', [
            'title' => 'Exits Report',
            'pageTitle' => $canHr ? 'Exits Report' : 'Team Exits Report',
            'canHr' => $canHr,
            'scopeLabel' => $scopeLabel,
            'records' => $records,
            'search' => $search,
            'status' => $status,
            'recordType' => $recordType,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
        ]);
    }

    public function documents(Request $request): void
    {
        ['canHr' => $canHr, 'scopeEmployeeId' => $scopeEmployeeId, 'scopeLabel' => $scopeLabel] = $this->reportScope();
        $search = trim((string) $request->input('q', ''));
        $expiryFilter = $this->normalizeEnum($request->input('expiry', 'expiring'), self::DOCUMENT_FILTERS, 'expiring');
        $days = $this->normalizeDays($request->input('days', 30), 30);

        try {
            $documents = $this->repository->documentReport($search, $expiryFilter, $days, $scopeEmployeeId);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load documents report: ' . $throwable->getMessage());
            $documents = [];
        }

        $this->render('reports.documents', [
            'title' => 'Document Expiry Report',
            'pageTitle' => $canHr ? 'Document Expiry Report' : 'Team Document Expiry Report',
            'canHr' => $canHr,
            'scopeLabel' => $scopeLabel,
            'documents' => $documents,
            'search' => $search,
            'expiryFilter' => $expiryFilter,
            'days' => $days,
        ]);
    }

    public function audit(Request $request): void
    {
        $search = trim((string) $request->input('q', ''));
        $module = trim((string) $request->input('module', 'all'));

        try {
            $auditLogs = $this->repository->listAuditLogs($search, $module);
            $modules = $this->repository->auditModules();
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load audit logs: ' . $throwable->getMessage());
            $auditLogs = [];
            $modules = [];
        }

        $this->render('reports.audit', [
            'title' => 'Audit Logs',
            'pageTitle' => 'Audit Logs',
            'auditLogs' => $auditLogs,
            'modules' => $modules,
            'search' => $search,
            'module' => $module,
        ]);
    }

    private function reportScope(): array
    {
        $canHr = $this->app->auth()->hasPermission('reports.view_hr');

        return [
            'canHr' => $canHr,
            'scopeEmployeeId' => $this->scopeEmployeeId($canHr),
            'scopeLabel' => $canHr ? 'Company-wide' : 'Direct reports only',
        ];
    }

    private function normalizeEnum(mixed $value, array $allowed, string $default): string
    {
        $value = trim((string) $value);

        return in_array($value, $allowed, true) ? $value : $default;
    }

    private function normalizeDate(mixed $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        $date = \DateTime::createFromFormat('Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $value : '';
    }

    private function normalizeDays(mixed $value, int $default): int
    {
        if (!is_numeric($value)) {
            return $default;
        }

        $days = (int) $value;

        if ($days < 1) {
            return $default;
        }

        return min($days, 365);
    }

    private function scopeEmployeeId(bool $canHr): ?int
    {
        if ($canHr) {
            return null;
        }

        $employeeId = $this->app->auth()->user()['employee_id'] ?? null;

        return is_numeric($employeeId) ? (int) $employeeId : -1;
    }
}