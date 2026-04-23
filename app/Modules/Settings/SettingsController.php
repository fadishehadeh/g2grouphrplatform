<?php

declare(strict_types=1);

namespace App\Modules\Settings;

use App\Core\Application;
use App\Core\Controller;
use App\Core\Request;
use Throwable;

final class SettingsController extends Controller
{
    private SettingsRepository $repository;

    public function __construct(Application $app)
    {
        parent::__construct($app);
        $this->repository = new SettingsRepository($this->app->database());
    }

    public function index(Request $request): void
    {
        try {
            $settings = $this->groupedSettings();
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load settings: ' . $throwable->getMessage());
            $settings = [];
        }

        $this->render('settings.index', [
            'title' => 'Settings',
            'pageTitle' => 'Settings',
            'settings' => $settings,
        ]);
    }

    public function update(Request $request, string $id): void
    {
        $settingId = (int) $id;
        $redirectPath = '/settings';
        $this->validateCsrf($request, $redirectPath);

        try {
            $setting = $this->repository->findSetting($settingId);

            if ($setting === null) {
                $this->app->session()->flash('error', 'Setting not found.');
                $this->redirect($redirectPath);
            }

            $normalizedValue = $this->normalizeValue($setting, $request->input('setting_value'));
            $this->repository->updateSetting($settingId, $normalizedValue, $this->app->auth()->id());
            $this->app->session()->flash('success', 'Setting updated successfully.');
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to update setting: ' . $throwable->getMessage());
        }

        $this->redirect($redirectPath);
    }

    public function attendance(Request $request): void
    {
        try {
            $attendance = $this->repository->attendanceOverview();
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load attendance data: ' . $throwable->getMessage());
            $attendance = ['today_total' => 0, 'today_present' => 0, 'today_absent' => 0, 'today_late' => 0, 'active_assignments' => 0, 'recent_records' => []];
        }

        $this->render('settings.attendance', [
            'title' => 'Attendance Foundation',
            'pageTitle' => 'Attendance Foundation',
            'attendance' => $attendance,
        ]);
    }

    public function attendanceRecords(Request $request): void
    {
        $search = trim((string) $request->input('q', ''));
        $dateFrom = trim((string) $request->input('date_from', ''));
        $dateTo = trim((string) $request->input('date_to', ''));
        $statusId = trim((string) $request->input('status_id', 'all'));

        try {
            $statusOptions = $this->optionMap($this->repository->attendanceStatusOptions());
            if ($statusId !== 'all' && !array_key_exists($statusId, $statusOptions)) {
                $statusId = 'all';
            }

            $records = $this->repository->listAttendanceRecords($search, $dateFrom, $dateTo, $statusId);
            $employeeOptions = $this->optionMap($this->repository->employeeOptions());
            $shiftOptions = $this->optionMap($this->repository->shiftOptions());
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load attendance records: ' . $throwable->getMessage());
            $records = [];
            $employeeOptions = [];
            $shiftOptions = [];
            $statusOptions = [];
            $statusId = 'all';
        }

        $this->render('settings.attendance-records', [
            'title' => 'Attendance Register',
            'pageTitle' => 'Attendance Register',
            'records' => $records,
            'search' => $search,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'statusId' => $statusId,
            'employeeOptions' => $employeeOptions,
            'shiftOptions' => $shiftOptions,
            'statusOptions' => $statusOptions,
        ]);
    }

    public function storeAttendanceRecord(Request $request): void
    {
        $redirectPath = '/settings/attendance/records';
        $this->validateCsrf($request, $redirectPath);
        $data = $this->attendanceRecordPayload($request);

        if (!$this->validateAttendanceRecordPayload($data, $redirectPath)) {
            return;
        }

        try {
            $this->repository->upsertAttendanceRecord($this->normalizeAttendanceRecordPayload($data), $this->app->auth()->id());
            $this->app->session()->flash('success', 'Attendance record saved successfully.');
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to save attendance record: ' . $throwable->getMessage());
            $this->app->session()->flash('old_input', $data);
        }

        $this->redirect($redirectPath);
    }

    public function attendanceAssignments(Request $request): void
    {
        $search = trim((string) $request->input('q', ''));
        $status = trim((string) $request->input('status', 'all'));
        $statusOptions = [
            'all' => 'All statuses',
            'active' => 'Active',
            'inactive' => 'Inactive',
        ];

        if (!array_key_exists($status, $statusOptions)) {
            $status = 'all';
        }

        try {
            $assignments = $this->repository->listScheduleAssignments($search, $status);
            $employeeOptions = $this->repository->employeeOptions();
            $scheduleOptions = $this->repository->scheduleOptions();
            $shiftOptions = $this->repository->shiftOptions();
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load attendance assignments: ' . $throwable->getMessage());
            $assignments = [];
            $employeeOptions = [];
            $scheduleOptions = [];
            $shiftOptions = [];
        }

        $this->render('settings.attendance-assignments', [
            'title' => 'Schedule Assignments',
            'pageTitle' => 'Schedule Assignments',
            'assignments' => $assignments,
            'employeeOptions' => $employeeOptions,
            'scheduleOptions' => $scheduleOptions,
            'shiftOptions' => $shiftOptions,
            'assignmentStates' => [
                'active' => 'Active',
                'inactive' => 'Inactive',
            ],
            'search' => $search,
            'status' => $status,
            'statusOptions' => $statusOptions,
        ]);
    }

    public function storeAttendanceAssignment(Request $request): void
    {
        $redirectPath = '/settings/attendance/assignments';
        $this->validateCsrf($request, $redirectPath);
        $data = $this->attendanceAssignmentPayload($request);

        try {
            $employees = $this->rowsById($this->repository->employeeOptions());
            $schedules = $this->rowsById($this->repository->scheduleOptions());
            $shifts = $this->rowsById($this->repository->shiftOptions());
            $this->validateAttendanceAssignmentPayload($data, $employees, $schedules, $shifts);
            $this->repository->createScheduleAssignment($this->normalizeAttendanceAssignmentPayload($data));
            $this->app->session()->flash('success', 'Schedule assignment saved successfully.');
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to save schedule assignment: ' . $throwable->getMessage());
            $this->app->session()->flash('old_input', $data);
        }

        $this->redirect($redirectPath);
    }

    public function shifts(Request $request): void
    {
        try {
            $shifts = $this->repository->shifts();
            $companies = $this->repository->companyOptions();
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load shifts: ' . $throwable->getMessage());
            $shifts = [];
            $companies = [];
        }

        $this->render('settings.shifts', [
            'title' => 'Shift Library',
            'pageTitle' => 'Shift Library',
            'shifts' => $shifts,
            'companies' => $companies,
        ]);
    }

    public function updateShift(Request $request, string $id): void
    {
        $redirectPath = '/settings/shifts';
        $this->validateCsrf($request, $redirectPath);
        $name = trim((string) $request->input('name', ''));
        $code = strtoupper(trim((string) $request->input('code', '')));

        if ($name === '' || $code === '') {
            $this->app->session()->flash('error', 'Name and code are required.');
            $this->redirect($redirectPath);
        }

        if (strlen($code) > 50 || !preg_match('/^[A-Z0-9_]+$/', $code)) {
            $this->app->session()->flash('error', 'Code may only contain uppercase letters, numbers, and underscores.');
            $this->redirect($redirectPath);
        }

        try {
            $this->repository->updateShift((int) $id, $name, $code);
            $this->app->session()->flash('success', 'Shift updated successfully.');
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to update shift: ' . $throwable->getMessage());
        }

        $this->redirect($redirectPath);
    }

    public function updateSchedule(Request $request, string $id): void
    {
        $redirectPath = '/settings/schedules';
        $this->validateCsrf($request, $redirectPath);
        $name = trim((string) $request->input('name', ''));
        $code = strtoupper(trim((string) $request->input('code', '')));

        if ($name === '' || $code === '') {
            $this->app->session()->flash('error', 'Name and code are required.');
            $this->redirect($redirectPath);
        }

        if (strlen($code) > 50 || !preg_match('/^[A-Z0-9_]+$/', $code)) {
            $this->app->session()->flash('error', 'Code may only contain uppercase letters, numbers, and underscores.');
            $this->redirect($redirectPath);
        }

        try {
            $this->repository->updateSchedule((int) $id, $name, $code);
            $this->app->session()->flash('success', 'Schedule updated successfully.');
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to update schedule: ' . $throwable->getMessage());
        }

        $this->redirect($redirectPath);
    }

    public function storeShift(Request $request): void
    {
        $redirectPath = '/settings/shifts';
        $this->validateCsrf($request, $redirectPath);
        $data = $this->shiftPayload($request);

        try {
            if (($data['code'] ?? '') === '') {
                $data['code'] = $this->repository->nextShiftCode();
            }
            $companies = $this->rowsById($this->repository->companyOptions());
            $this->validateShiftPayload($data, $companies);
            $this->repository->createShift($this->normalizeShiftPayload($data));
            $this->app->session()->flash('success', 'Shift saved successfully.');
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to save shift: ' . $throwable->getMessage());
            $this->app->session()->flash('old_input', $data);
        }

        $this->redirect($redirectPath);
    }

    public function schedules(Request $request): void
    {
        try {
            $schedules = $this->repository->schedules();
            $companies = $this->repository->companyOptions();
            $shiftOptions = $this->repository->shiftOptions();
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load work schedules: ' . $throwable->getMessage());
            $schedules = [];
            $companies = [];
            $shiftOptions = [];
        }

        $this->render('settings.schedules', [
            'title' => 'Work Schedules',
            'pageTitle' => 'Work Schedules',
            'schedules' => $schedules,
            'companies' => $companies,
            'shiftOptions' => $shiftOptions,
            'dayNames' => $this->dayNames(),
        ]);
    }

    public function storeSchedule(Request $request): void
    {
        $redirectPath = '/settings/schedules';
        $this->validateCsrf($request, $redirectPath);
        $data = $this->schedulePayload($request);

        try {
            if (($data['code'] ?? '') === '') {
                $data['code'] = $this->repository->nextScheduleCode();
            }
            $companies = $this->rowsById($this->repository->companyOptions());
            $shifts = $this->rowsById($this->repository->shiftOptions());
            $this->validateSchedulePayload($data, $companies, $shifts);
            $this->repository->createSchedule($this->normalizeSchedulePayload($data));
            $this->app->session()->flash('success', 'Work schedule saved successfully.');
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to save work schedule: ' . $throwable->getMessage());
            $this->app->session()->flash('old_input', $data);
        }

        $this->redirect($redirectPath);
    }

    public function attendanceStatuses(Request $request): void
    {
        try {
            $statuses = $this->repository->attendanceStatuses();
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load attendance statuses: ' . $throwable->getMessage());
            $statuses = [];
        }

        $this->render('settings.attendance-statuses', [
            'title' => 'Attendance Statuses',
            'pageTitle' => 'Attendance Statuses',
            'statuses' => $statuses,
            'colorOptions' => $this->colorOptions(),
        ]);
    }

    public function storeAttendanceStatus(Request $request): void
    {
        $redirectPath = '/settings/attendance-statuses';
        $this->validateCsrf($request, $redirectPath);
        $data = $this->attendanceStatusPayload($request);

        try {
            $this->validateAttendanceStatusPayload($data);
            $this->repository->createAttendanceStatus($this->normalizeAttendanceStatusPayload($data));
            $this->app->session()->flash('success', 'Attendance status saved successfully.');
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to save attendance status: ' . $throwable->getMessage());
            $this->app->session()->flash('old_input', $data);
        }

        $this->redirect($redirectPath);
    }

    private function groupedSettings(): array
    {
        $grouped = [];

        foreach ($this->repository->listSettings() as $setting) {
            $grouped[(string) $setting['category_name']][] = $setting;
        }

        return $grouped;
    }

    private function normalizeValue(array $setting, mixed $value): ?string
    {
        $type = (string) ($setting['value_type'] ?? 'string');
        $raw = is_string($value) ? trim($value) : $value;

        return match ($type) {
            'integer' => $this->normalizeInteger($raw),
            'boolean' => $this->normalizeBoolean($raw),
            'json' => $this->normalizeJson($raw),
            'text', 'string' => $raw === '' ? null : (string) $raw,
            default => $raw === '' ? null : (string) $raw,
        };
    }

    private function normalizeInteger(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new \RuntimeException('Invalid integer value.');
        }

        return (string) $value;
    }

    private function normalizeBoolean(mixed $value): ?string
    {
        $normalized = strtolower((string) $value);

        return match ($normalized) {
            '1', 'true', 'yes', 'on' => '1',
            '0', 'false', 'no', 'off' => '0',
            default => throw new \RuntimeException('Invalid boolean value.'),
        };
    }

    private function normalizeJson(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        json_decode((string) $value, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON value.');
        }

        return (string) $value;
    }

    private function attendanceRecordPayload(Request $request): array
    {
        return [
            'employee_id' => trim((string) $request->input('employee_id', '')),
            'attendance_date' => trim((string) $request->input('attendance_date', '')),
            'status_id' => trim((string) $request->input('status_id', '')),
            'shift_id' => trim((string) $request->input('shift_id', '')),
            'clock_in_time' => trim((string) $request->input('clock_in_time', '')),
            'clock_out_time' => trim((string) $request->input('clock_out_time', '')),
            'source' => trim((string) $request->input('source', 'manual')),
            'remarks' => trim((string) $request->input('remarks', '')),
        ];
    }

    private function normalizeAttendanceRecordPayload(array $data): array
    {
        return [
            'employee_id' => $data['employee_id'],
            'attendance_date' => $data['attendance_date'],
            'status_id' => $data['status_id'],
            'shift_id' => $data['shift_id'] === '' ? null : $data['shift_id'],
            'clock_in_time' => $this->normalizeDateTimeInput($data['clock_in_time']),
            'clock_out_time' => $this->normalizeDateTimeInput($data['clock_out_time']),
            'source' => $data['source'],
            'remarks' => $data['remarks'],
        ];
    }

    private function validateAttendanceRecordPayload(array $data, string $redirectPath): bool
    {
        if ($data['employee_id'] === '' || $data['attendance_date'] === '' || $data['status_id'] === '') {
            $this->app->session()->flash('error', 'Employee, attendance date, and status are required.');
            $this->app->session()->flash('old_input', $data);
            $this->redirect($redirectPath);
        }

        if (!ctype_digit($data['employee_id'])) {
            $this->app->session()->flash('error', 'Please select a valid employee.');
            $this->app->session()->flash('old_input', $data);
            $this->redirect($redirectPath);
        }

        if (!ctype_digit($data['status_id'])) {
            $this->app->session()->flash('error', 'Please select a valid attendance status.');
            $this->app->session()->flash('old_input', $data);
            $this->redirect($redirectPath);
        }

        if ($data['shift_id'] !== '' && !ctype_digit($data['shift_id'])) {
            $this->app->session()->flash('error', 'Please select a valid shift.');
            $this->app->session()->flash('old_input', $data);
            $this->redirect($redirectPath);
        }

        if (!$this->isValidDate($data['attendance_date'])) {
            $this->app->session()->flash('error', 'Please provide a valid attendance date.');
            $this->app->session()->flash('old_input', $data);
            $this->redirect($redirectPath);
        }

        if ($data['clock_in_time'] !== '' && !$this->isValidDateTimeInput($data['clock_in_time'])) {
            $this->app->session()->flash('error', 'Please provide a valid clock-in date and time.');
            $this->app->session()->flash('old_input', $data);
            $this->redirect($redirectPath);
        }

        if ($data['clock_out_time'] !== '' && !$this->isValidDateTimeInput($data['clock_out_time'])) {
            $this->app->session()->flash('error', 'Please provide a valid clock-out date and time.');
            $this->app->session()->flash('old_input', $data);
            $this->redirect($redirectPath);
        }

        if ($data['clock_in_time'] !== '' && $data['clock_out_time'] !== '') {
            $clockIn = $this->normalizeDateTimeInput($data['clock_in_time']);
            $clockOut = $this->normalizeDateTimeInput($data['clock_out_time']);

            if ($clockIn !== null && $clockOut !== null && strtotime($clockOut) < strtotime($clockIn)) {
                $this->app->session()->flash('error', 'Clock-out time cannot be earlier than clock-in time.');
                $this->app->session()->flash('old_input', $data);
                $this->redirect($redirectPath);
            }
        }

        if (!in_array($data['source'], ['manual', 'device', 'import', 'system'], true)) {
            $this->app->session()->flash('error', 'Please select a valid attendance source.');
            $this->app->session()->flash('old_input', $data);
            $this->redirect($redirectPath);
        }

        if (strlen($data['remarks']) > 255) {
            $this->app->session()->flash('error', 'Remarks must not exceed 255 characters.');
            $this->app->session()->flash('old_input', $data);
            $this->redirect($redirectPath);
        }

        return true;
    }

    private function attendanceAssignmentPayload(Request $request): array
    {
        return [
            'employee_id' => trim((string) $request->input('employee_id', '')),
            'work_schedule_id' => trim((string) $request->input('work_schedule_id', '')),
            'shift_id' => trim((string) $request->input('shift_id', '')),
            'effective_from' => trim((string) $request->input('effective_from', date('Y-m-d'))),
            'effective_to' => trim((string) $request->input('effective_to', '')),
            'status' => trim((string) $request->input('status', 'active')),
        ];
    }

    private function normalizeAttendanceAssignmentPayload(array $data): array
    {
        return [
            'employee_id' => (int) $data['employee_id'],
            'work_schedule_id' => (int) $data['work_schedule_id'],
            'shift_id' => $data['shift_id'] === '' ? null : (int) $data['shift_id'],
            'effective_from' => $data['effective_from'],
            'effective_to' => $data['effective_to'] === '' ? null : $data['effective_to'],
            'status' => $data['status'],
        ];
    }

    private function validateAttendanceAssignmentPayload(array $data, array $employees, array $schedules, array $shifts): void
    {
        foreach (['employee_id', 'work_schedule_id', 'effective_from'] as $field) {
            if (($data[$field] ?? '') === '') {
                $this->invalid('/settings/attendance/assignments', $data, 'Please complete all required assignment fields.');
            }
        }

        if (!ctype_digit((string) $data['employee_id']) || !isset($employees[(int) $data['employee_id']])) {
            $this->invalid('/settings/attendance/assignments', $data, 'Please select a valid employee.');
        }

        if (!ctype_digit((string) $data['work_schedule_id']) || !isset($schedules[(int) $data['work_schedule_id']])) {
            $this->invalid('/settings/attendance/assignments', $data, 'Please select a valid active work schedule.');
        }

        if ($data['shift_id'] !== '' && (!ctype_digit((string) $data['shift_id']) || !isset($shifts[(int) $data['shift_id']]))) {
            $this->invalid('/settings/attendance/assignments', $data, 'Please select a valid shift override.');
        }

        if (!$this->isValidDate((string) $data['effective_from'])) {
            $this->invalid('/settings/attendance/assignments', $data, 'Please provide a valid effective-from date.');
        }

        if ($data['effective_to'] !== '' && !$this->isValidDate((string) $data['effective_to'])) {
            $this->invalid('/settings/attendance/assignments', $data, 'Please provide a valid effective-to date.');
        }

        if (!in_array((string) $data['status'], ['active', 'inactive'], true)) {
            $this->invalid('/settings/attendance/assignments', $data, 'Please choose a valid assignment status.');
        }

        if ($data['effective_to'] !== '' && strtotime((string) $data['effective_to']) < strtotime((string) $data['effective_from'])) {
            $this->invalid('/settings/attendance/assignments', $data, 'Effective-to date cannot be earlier than effective-from date.');
        }

        $employeeId = (int) $data['employee_id'];
        $scheduleId = (int) $data['work_schedule_id'];
        $employeeCompanyId = (int) ($employees[$employeeId]['company_id'] ?? 0);
        $scheduleCompanyId = (int) ($schedules[$scheduleId]['company_id'] ?? 0);

        if ($employeeCompanyId <= 0 || $scheduleCompanyId <= 0 || $employeeCompanyId !== $scheduleCompanyId) {
            $this->invalid('/settings/attendance/assignments', $data, 'Employee and work schedule must belong to the same company.');
        }

        if ($data['shift_id'] !== '') {
            $shiftCompanyId = (int) ($shifts[(int) $data['shift_id']]['company_id'] ?? 0);

            if ($shiftCompanyId <= 0 || $shiftCompanyId !== $scheduleCompanyId) {
                $this->invalid('/settings/attendance/assignments', $data, 'Shift override must belong to the same company as the selected schedule.');
            }
        }

        if ($this->repository->assignmentOverlapExists(
            $employeeId,
            (string) $data['effective_from'],
            $data['effective_to'] === '' ? null : (string) $data['effective_to']
        )) {
            $this->invalid('/settings/attendance/assignments', $data, 'Selected employee already has a schedule assignment overlapping the chosen dates.');
        }
    }

    private function optionMap(array $rows): array
    {
        $options = [];

        foreach ($rows as $row) {
            $options[(string) $row['id']] = (string) $row['name'];
        }

        return $options;
    }

    private function rowsById(array $rows): array
    {
        $indexed = [];

        foreach ($rows as $row) {
            $indexed[(int) $row['id']] = $row;
        }

        return $indexed;
    }

    private function shiftPayload(Request $request): array
    {
        return [
            'company_id' => trim((string) $request->input('company_id', '')),
            'name' => trim((string) $request->input('name', '')),
            'code' => strtoupper(trim((string) $request->input('code', ''))),
            'start_time' => trim((string) $request->input('start_time', '')),
            'end_time' => trim((string) $request->input('end_time', '')),
            'late_grace_minutes' => trim((string) $request->input('late_grace_minutes', '0')),
            'half_day_minutes' => trim((string) $request->input('half_day_minutes', '')),
            'is_night_shift' => trim((string) $request->input('is_night_shift', '0')),
            'is_active' => trim((string) $request->input('is_active', '1')),
        ];
    }

    private function normalizeShiftPayload(array $data): array
    {
        return [
            'company_id' => (int) $data['company_id'],
            'name' => trim((string) $data['name']),
            'code' => strtoupper(trim((string) $data['code'])),
            'start_time' => $this->normalizeTimeInput((string) $data['start_time']),
            'end_time' => $this->normalizeTimeInput((string) $data['end_time']),
            'late_grace_minutes' => (int) $data['late_grace_minutes'],
            'half_day_minutes' => $data['half_day_minutes'] === '' ? null : (int) $data['half_day_minutes'],
            'is_night_shift' => (int) $data['is_night_shift'],
            'is_active' => (int) $data['is_active'],
        ];
    }

    private function validateShiftPayload(array $data, array $companies): void
    {
        foreach (['company_id', 'name', 'start_time', 'end_time'] as $field) {
            if (($data[$field] ?? '') === '') {
                $this->invalid('/settings/shifts', $data, 'Please complete all required shift fields.');
            }
        }

        $companyId = (int) ($data['company_id'] ?? 0);
        $name = trim((string) ($data['name'] ?? ''));
        $code = strtoupper(trim((string) ($data['code'] ?? '')));
        $lateGraceMinutes = $data['late_grace_minutes'] ?? '';
        $halfDayMinutes = $data['half_day_minutes'] ?? '';
        $isNightShift = $data['is_night_shift'] ?? '';
        $isActive = $data['is_active'] ?? '';
        $startTime = $this->normalizeTimeInput((string) ($data['start_time'] ?? ''));
        $endTime = $this->normalizeTimeInput((string) ($data['end_time'] ?? ''));

        if ($companyId <= 0 || !isset($companies[$companyId])) {
            $this->invalid('/settings/shifts', $data, 'Please select a valid company.');
        }

        if ($name === '' || strlen($name) > 150) {
            $this->invalid('/settings/shifts', $data, 'Shift name must be between 1 and 150 characters.');
        }

        if ($code === '' || strlen($code) > 50 || !preg_match('/^[A-Z0-9_]+$/', $code)) {
            $this->invalid('/settings/shifts', $data, 'Shift code may contain only uppercase letters, numbers, and underscores.');
        }

        if (filter_var($lateGraceMinutes, FILTER_VALIDATE_INT) === false || (int) $lateGraceMinutes < 0) {
            $this->invalid('/settings/shifts', $data, 'Late grace minutes must be a whole number zero or greater.');
        }

        if ($halfDayMinutes !== '' && (filter_var($halfDayMinutes, FILTER_VALIDATE_INT) === false || (int) $halfDayMinutes < 0)) {
            $this->invalid('/settings/shifts', $data, 'Half day minutes must be a whole number zero or greater.');
        }

        if (!in_array((string) $isNightShift, ['0', '1'], true) || !in_array((string) $isActive, ['0', '1'], true)) {
            $this->invalid('/settings/shifts', $data, 'Please choose valid shift status values.');
        }

        if ($startTime === null || $endTime === null) {
            $this->invalid('/settings/shifts', $data, 'Please provide valid start and end times.');
        }

        if ($startTime === $endTime) {
            $this->invalid('/settings/shifts', $data, 'Shift start and end time cannot be the same.');
        }

        if ((int) $isNightShift === 0 && strcmp($endTime, $startTime) <= 0) {
            $this->invalid('/settings/shifts', $data, 'Day shifts must end later than they start. Use night shift for overnight timings.');
        }
    }

    private function schedulePayload(Request $request): array
    {
        $data = [
            'company_id' => trim((string) $request->input('company_id', '')),
            'name' => trim((string) $request->input('name', '')),
            'code' => strtoupper(trim((string) $request->input('code', ''))),
            'weekly_hours' => trim((string) $request->input('weekly_hours', '40')),
            'is_active' => trim((string) $request->input('is_active', '1')),
        ];

        foreach (array_keys($this->dayNames()) as $day) {
            $data['day_' . $day . '_is_working'] = trim((string) $request->input('day_' . $day . '_is_working', in_array($day, [1, 2, 3, 4, 5], true) ? '1' : '0'));
            $data['day_' . $day . '_shift_id'] = trim((string) $request->input('day_' . $day . '_shift_id', ''));
        }

        return $data;
    }

    private function normalizeSchedulePayload(array $data): array
    {
        $normalized = [
            'company_id' => (int) $data['company_id'],
            'name' => trim((string) $data['name']),
            'code' => strtoupper(trim((string) $data['code'])),
            'weekly_hours' => round((float) $data['weekly_hours'], 2),
            'is_active' => (int) $data['is_active'],
            'days' => [],
        ];

        foreach (array_keys($this->dayNames()) as $day) {
            $isWorkingDay = (int) ($data['day_' . $day . '_is_working'] ?? 0) === 1;
            $shiftId = trim((string) ($data['day_' . $day . '_shift_id'] ?? ''));

            $normalized['days'][] = [
                'day_of_week' => $day,
                'is_working_day' => $isWorkingDay ? 1 : 0,
                'shift_id' => $isWorkingDay && $shiftId !== '' ? (int) $shiftId : null,
            ];
        }

        return $normalized;
    }

    private function validateSchedulePayload(array $data, array $companies, array $shifts): void
    {
        foreach (['company_id', 'name', 'weekly_hours'] as $field) {
            if (($data[$field] ?? '') === '') {
                $this->invalid('/settings/schedules', $data, 'Please complete all required schedule fields.');
            }
        }

        $companyId = (int) ($data['company_id'] ?? 0);
        $name = trim((string) ($data['name'] ?? ''));
        $code = strtoupper(trim((string) ($data['code'] ?? '')));
        $weeklyHours = $data['weekly_hours'] ?? '';

        if ($companyId <= 0 || !isset($companies[$companyId])) {
            $this->invalid('/settings/schedules', $data, 'Please select a valid company.');
        }

        if ($name === '' || strlen($name) > 150) {
            $this->invalid('/settings/schedules', $data, 'Schedule name must be between 1 and 150 characters.');
        }

        if ($code === '' || strlen($code) > 50 || !preg_match('/^[A-Z0-9_]+$/', $code)) {
            $this->invalid('/settings/schedules', $data, 'Schedule code may contain only uppercase letters, numbers, and underscores.');
        }

        if (!is_numeric($weeklyHours) || (float) $weeklyHours <= 0 || (float) $weeklyHours > 168) {
            $this->invalid('/settings/schedules', $data, 'Weekly hours must be a number greater than 0 and not more than 168.');
        }

        if (!in_array((string) ($data['is_active'] ?? ''), ['0', '1'], true)) {
            $this->invalid('/settings/schedules', $data, 'Please choose a valid schedule state.');
        }

        $workingDays = 0;

        foreach ($this->dayNames() as $day => $label) {
            $isWorkingKey = 'day_' . $day . '_is_working';
            $shiftKey = 'day_' . $day . '_shift_id';
            $isWorkingDay = (string) ($data[$isWorkingKey] ?? '');
            $shiftId = trim((string) ($data[$shiftKey] ?? ''));

            if (!in_array($isWorkingDay, ['0', '1'], true)) {
                $this->invalid('/settings/schedules', $data, 'Please choose valid day settings for the schedule.');
            }

            if ($shiftId !== '' && (!ctype_digit($shiftId) || !isset($shifts[(int) $shiftId]))) {
                $this->invalid('/settings/schedules', $data, 'Please select valid shifts for each configured day.');
            }

            if ($isWorkingDay === '1') {
                $workingDays++;

                if ($shiftId === '') {
                    $this->invalid('/settings/schedules', $data, 'Please choose a shift for ' . $label . '.');
                }

                if ((int) ($shifts[(int) $shiftId]['company_id'] ?? 0) !== $companyId) {
                    $this->invalid('/settings/schedules', $data, 'Each working-day shift must belong to the selected company.');
                }
            }
        }

        if ($workingDays === 0) {
            $this->invalid('/settings/schedules', $data, 'Please mark at least one day as a working day.');
        }
    }

    private function attendanceStatusPayload(Request $request): array
    {
        return [
            'name' => trim((string) $request->input('name', '')),
            'code' => strtoupper(trim((string) $request->input('code', ''))),
            'color_class' => trim((string) $request->input('color_class', 'secondary')),
            'counts_as_present' => trim((string) $request->input('counts_as_present', '0')),
            'counts_as_absent' => trim((string) $request->input('counts_as_absent', '0')),
            'is_active' => trim((string) $request->input('is_active', '1')),
        ];
    }

    private function normalizeAttendanceStatusPayload(array $data): array
    {
        return [
            'name' => trim((string) $data['name']),
            'code' => strtoupper(trim((string) $data['code'])),
            'color_class' => trim((string) $data['color_class']),
            'counts_as_present' => (int) $data['counts_as_present'],
            'counts_as_absent' => (int) $data['counts_as_absent'],
            'is_active' => (int) $data['is_active'],
        ];
    }

    private function validateAttendanceStatusPayload(array $data): void
    {
        foreach (['name', 'code', 'color_class'] as $field) {
            if (($data[$field] ?? '') === '') {
                $this->invalid('/settings/attendance-statuses', $data, 'Please complete all required attendance status fields.');
            }
        }

        $name = trim((string) ($data['name'] ?? ''));
        $code = strtoupper(trim((string) ($data['code'] ?? '')));
        $colorClass = trim((string) ($data['color_class'] ?? ''));
        $countsAsPresent = (string) ($data['counts_as_present'] ?? '');
        $countsAsAbsent = (string) ($data['counts_as_absent'] ?? '');
        $isActive = (string) ($data['is_active'] ?? '');

        if ($name === '' || strlen($name) > 100) {
            $this->invalid('/settings/attendance-statuses', $data, 'Attendance status name must be between 1 and 100 characters.');
        }

        if ($code === '' || strlen($code) > 50 || !preg_match('/^[A-Z0-9_]+$/', $code)) {
            $this->invalid('/settings/attendance-statuses', $data, 'Attendance status code may contain only uppercase letters, numbers, and underscores.');
        }

        if (!array_key_exists($colorClass, $this->colorOptions())) {
            $this->invalid('/settings/attendance-statuses', $data, 'Please select a valid badge color.');
        }

        if (!in_array($countsAsPresent, ['0', '1'], true) || !in_array($countsAsAbsent, ['0', '1'], true) || !in_array($isActive, ['0', '1'], true)) {
            $this->invalid('/settings/attendance-statuses', $data, 'Please choose valid attendance status options.');
        }

        if ($countsAsPresent === '1' && $countsAsAbsent === '1') {
            $this->invalid('/settings/attendance-statuses', $data, 'An attendance status cannot count as both present and absent.');
        }
    }

    private function isValidDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function isValidDateTimeInput(string $value): bool
    {
        return $this->normalizeDateTimeInput($value) !== null;
    }

    private function normalizeTimeInput(string $value): ?string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        foreach (['H:i', 'H:i:s'] as $format) {
            $time = \DateTimeImmutable::createFromFormat($format, $trimmed);

            if ($time !== false && $time->format($format) === $trimmed) {
                return $time->format('H:i:s');
            }
        }

        return null;
    }

    private function normalizeDateTimeInput(string $value): ?string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        foreach (['Y-m-d\\TH:i', 'Y-m-d\\TH:i:s', 'Y-m-d H:i', 'Y-m-d H:i:s'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $trimmed);

            if ($date !== false && $date->format($format) === $trimmed) {
                return $date->format('Y-m-d H:i:s');
            }
        }

        return null;
    }

    private function validateCsrf(Request $request, string $redirectPath): void
    {
        if (!$this->app->csrf()->validate((string) $request->input('_token'))) {
            $this->app->session()->flash('error', 'Invalid form submission token.');
            $this->redirect($redirectPath);
        }
    }

    private function colorOptions(): array
    {
        return [
            'primary' => 'Primary',
            'secondary' => 'Secondary',
            'success' => 'Success',
            'danger' => 'Danger',
            'warning' => 'Warning',
            'info' => 'Info',
            'dark' => 'Dark',
        ];
    }

    private function dayNames(): array
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

    private function invalid(string $redirectPath, array $data, string $message): void
    {
        $this->app->session()->flash('error', $message);
        $this->app->session()->flash('old_input', $data);
        $this->redirect($redirectPath);
    }
}