<?php

declare(strict_types=1);

namespace App\Modules\Settings;

use App\Core\Application;
use App\Core\Controller;
use App\Core\Request;
use App\Modules\Resilience\B2BackupUploader;
use App\Modules\Resilience\BackupService;
use App\Support\Mailer;
use App\Support\OperationalSettings;
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

            if (OperationalSettings::isSystemManagedSetting((string) $setting['category_name'], (string) $setting['setting_key'])) {
                $this->app->session()->flash('error', 'This setting is managed from System Settings.');
                $this->redirect('/settings/system');
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

    // ------------------------------------------------------------------
    // System Settings — branding + module toggles (super_admin only)
    // ------------------------------------------------------------------

    public function systemSettings(Request $request): void
    {
        try {
            $this->repository->ensureOperationalSettingsSeeded();
            $tenant = $this->repository->getMainTenant();
            $payrollEnabled = $this->repository->getPayrollEnabled();
            $operationalSettings = $this->buildOperationalSettingsViewModel($this->repository->operationalSettings());
            $recentOperationalChanges = array_map(function (array $row): array {
                $definition = OperationalSettings::definitionByStorageKey(
                    (string) ($row['category_name'] ?? ''),
                    (string) ($row['setting_key'] ?? '')
                );
                $row['label'] = $definition['label'] ?? ucwords(str_replace('_', ' ', (string) ($row['setting_key'] ?? '')));
                return $row;
            }, $this->repository->recentOperationalSettingChanges(8));
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load system settings: ' . $throwable->getMessage());
            $tenant = null;
            $payrollEnabled = false;
            $operationalSettings = [];
            $recentOperationalChanges = [];
        }

        $this->render('settings.system', [
            'title'          => 'System Settings',
            'pageTitle'      => 'System Settings',
            'tenant'         => $tenant,
            'payrollEnabled' => $payrollEnabled,
            'operationalSettings' => $operationalSettings,
            'recentOperationalChanges' => $recentOperationalChanges,
        ]);
    }

    public function saveSystemSettings(Request $request): void
    {
        $redirectPath = '/settings/system';
        $this->validateCsrf($request, $redirectPath);

        $tenant = $this->repository->getMainTenant();

        if ($tenant === null) {
            $this->app->session()->flash('error', 'Main tenant record not found. Run the branding migration first.');
            $this->redirect($redirectPath);
        }

        $tenantId  = (int) $tenant['id'];
        $name      = trim((string) $request->input('name', ''));
        $tagline   = trim((string) $request->input('tagline', ''));
        $color     = trim((string) $request->input('brand_color', ''));

        if ($name === '') {
            $this->app->session()->flash('error', 'Organisation name is required.');
            $this->redirect($redirectPath);
        }

        if (strlen($name) > 150) {
            $this->app->session()->flash('error', 'Organisation name must be 150 characters or fewer.');
            $this->redirect($redirectPath);
        }

        if ($color !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $this->app->session()->flash('error', 'Brand colour must be a valid 6-digit hex code (e.g. #FF3D33).');
            $this->redirect($redirectPath);
        }

        $this->repository->ensureOperationalSettingsSeeded();
        $existingOperationalRows = $this->repository->operationalSettings();
        $operationalPayload = $request->input('settings', []);
        if (!is_array($operationalPayload)) {
            $operationalPayload = [];
        }

        try {
            $normalizedOperational = $this->normalizeOperationalSettingsPayload($operationalPayload, $existingOperationalRows);
            $this->validateOperationalSettingsPayload($normalizedOperational);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to save settings: ' . $throwable->getMessage());
            $this->redirect($redirectPath);
        }

        $allowedExt  = ['png', 'jpg', 'jpeg', 'svg', 'gif'];
        $maxBytes    = 2 * 1024 * 1024;
        $logoDir     = base_path('public-hr/assets/uploads/logos');

        $logoPath      = $tenant['logo_path']       !== '' ? $tenant['logo_path']       : null;
        $logoPathWhite = $tenant['logo_path_white'] !== '' ? $tenant['logo_path_white'] : null;

        foreach ([
            ['field' => 'logo_file',       'key' => 'main_tenant',       'outVar' => &$logoPath],
            ['field' => 'logo_file_white', 'key' => 'main_tenant_white', 'outVar' => &$logoPathWhite],
        ] as $upload) {
            $file = $request->file($upload['field']);
            if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }

            $ext = strtolower(pathinfo(basename((string) ($file['name'] ?? '')), PATHINFO_EXTENSION));

            if (!in_array($ext, $allowedExt, true)) {
                $this->app->session()->flash('error', 'Logo must be PNG, JPG, SVG, or GIF.');
                $this->redirect($redirectPath);
            }

            if ((int) ($file['size'] ?? 0) > $maxBytes) {
                $this->app->session()->flash('error', 'Logo file must be 2 MB or smaller.');
                $this->redirect($redirectPath);
            }

            if (!is_dir($logoDir) && !mkdir($logoDir, 0775, true) && !is_dir($logoDir)) {
                throw new \RuntimeException('Unable to create logo upload directory.');
            }

            $storedName = $upload['key'] . '_' . date('YmdHis') . '.' . $ext;
            $storedPath = 'assets/uploads/logos/' . $storedName;

            if (!move_uploaded_file((string) ($file['tmp_name'] ?? ''), base_path('public-hr/' . $storedPath))) {
                $this->app->session()->flash('error', 'Unable to save uploaded logo file.');
                $this->redirect($redirectPath);
            }

            $upload['outVar'] = $storedPath;
        }

        try {
            $actorId = (int) $this->app->auth()->id();
            $requestIp = (string) ($request->ip() ?? '');
            $userAgent = (string) ($request->userAgent() ?? '');

            $payrollEnabled = $request->input('payroll_enabled') === '1';
            $this->app->database()->transaction(function () use (
                $tenantId,
                $name,
                $tagline,
                $logoPath,
                $logoPathWhite,
                $color,
                $payrollEnabled,
                $actorId,
                $normalizedOperational
            ): void {
                $this->repository->saveMainTenant(
                    $tenantId,
                    $name,
                    $tagline === '' ? null : $tagline,
                    $logoPath,
                    $logoPathWhite,
                    $color === '' ? null : $color,
                    $actorId
                );

                $this->repository->setPayrollEnabled($payrollEnabled, $actorId);

                foreach ($normalizedOperational as $field) {
                    if (!$field['changed']) {
                        continue;
                    }

                    $this->repository->saveOperationalSetting(
                        $field['definition']['category_name'],
                        $field['definition']['setting_key'],
                        $field['stored_value'],
                        $field['definition']['value_type'],
                        $actorId
                    );
                }
            });

            \App\Support\Branding::clearCache();
            $this->auditLog(
                'settings',
                'system_settings',
                null,
                'updated',
                $requestIp,
                $userAgent,
                null,
                [
                    'branding' => [
                        'name' => $name,
                        'tagline' => $tagline === '' ? null : $tagline,
                        'brand_color' => $color === '' ? null : $color,
                    ],
                    'payroll_enabled' => $payrollEnabled,
                    'operational_changed_fields' => array_values(array_map(
                        static fn (array $field): string => (string) $field['definition']['label'],
                        array_filter($normalizedOperational, static fn (array $field): bool => $field['changed'])
                    )),
                ]
            );

            $this->app->session()->flash('success', 'System settings saved successfully.');
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to save settings: ' . $throwable->getMessage());
        }

        $this->redirect($redirectPath);
    }

    public function testSystemEmail(Request $request): void
    {
        $redirectPath = '/settings/system';
        $this->validateCsrf($request, $redirectPath);

        $recipient = trim((string) ($this->app->auth()->user()['email'] ?? ''));
        if ($recipient === '') {
            $this->app->session()->flash('error', 'The signed-in super admin does not have an email address.');
            $this->redirect($redirectPath);
        }

        try {
            (new Mailer((array) config('app.mail', [])))->send(
                $recipient,
                'System Settings Test Email',
                '<p>This is a test email from the HR Management System system-settings console.</p>'
                . '<p>If you received this message, the current effective mail configuration is working.</p>'
            );

            $this->app->session()->flash('success', 'Test email sent successfully to ' . $recipient . '.');
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Test email failed: ' . $throwable->getMessage());
        }

        $this->redirect($redirectPath);
    }

    public function testBackupConfiguration(Request $request): void
    {
        $redirectPath = '/settings/system';
        $this->validateCsrf($request, $redirectPath);

        try {
            $result = (new BackupService($this->app))->validateConfiguration();
            $this->app->session()->flash(
                'success',
                'Backup configuration looks valid. Storage: ' . $result['storage_dir']
                . ' | Source directories found: ' . count((array) $result['source_directories'])
            );
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Backup configuration check failed: ' . $throwable->getMessage());
        }

        $this->redirect($redirectPath);
    }

    public function testB2Configuration(Request $request): void
    {
        $redirectPath = '/settings/system';
        $this->validateCsrf($request, $redirectPath);

        if (!filter_var(config('app.b2.enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->app->session()->flash('error', 'B2 is currently disabled in the effective runtime configuration.');
            $this->redirect($redirectPath);
        }

        try {
            $result = (new B2BackupUploader(
                (string) config('app.b2.key_id', ''),
                (string) config('app.b2.application_key', ''),
                (string) config('app.b2.bucket_name', ''),
                60
            ))->validateConnection();

            $this->app->session()->flash(
                'success',
                'B2 connectivity verified for bucket ' . $result['bucket_name'] . ' (' . $result['bucket_id'] . ').'
            );
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'B2 validation failed: ' . $throwable->getMessage());
        }

        $this->redirect($redirectPath);
    }

    private function buildOperationalSettingsViewModel(array $rowsByStorageKey): array
    {
        $sections = [];

        foreach (OperationalSettings::sections() as $sectionKey => $sectionMeta) {
            $sections[$sectionKey] = $sectionMeta + ['fields' => []];
        }

        foreach (OperationalSettings::definitions() as $alias => $definition) {
            $storageKey = $definition['category_name'] . ':' . $definition['setting_key'];
            $row = $rowsByStorageKey[$storageKey] ?? null;
            $effective = $row !== null && $row['setting_value'] !== null
                ? OperationalSettings::castStoredValue((string) $row['setting_value'], $definition)
                : config((string) $definition['config_path'], null);
            $hasFallbackValue = !in_array($effective, [null, ''], true);

            $sections[(string) $definition['section']]['fields'][] = [
                'alias' => $alias,
                'definition' => $definition,
                'row' => $row,
                'effective_value' => $effective,
                'display_value' => is_bool($effective) ? ($effective ? 'Enabled' : 'Disabled') : (string) ($effective ?? ''),
                'has_secret_value' => !empty($definition['secret']) && $hasFallbackValue,
                'uses_override' => $row !== null && $row['setting_value'] !== null,
                'updated_at' => $row['updated_at'] ?? null,
                'updated_by_name' => $row['updated_by_name'] ?? null,
            ];
        }

        return $sections;
    }

    private function normalizeOperationalSettingsPayload(array $payload, array $existingRows): array
    {
        $normalized = [];

        foreach (OperationalSettings::definitions() as $alias => $definition) {
            $storageKey = $definition['category_name'] . ':' . $definition['setting_key'];
            $existingStoredValue = isset($existingRows[$storageKey]) ? $existingRows[$storageKey]['setting_value'] : null;
            $rawValue = $payload[$alias] ?? null;

            if (!empty($definition['secret'])) {
                $newStoredValue = is_string($rawValue) && trim($rawValue) !== ''
                    ? $this->normalizeOperationalFieldValue($definition, $rawValue)
                    : $existingStoredValue;
            } else {
                $newStoredValue = $this->normalizeOperationalFieldValue($definition, $rawValue);
            }

            $normalized[$alias] = [
                'definition' => $definition,
                'stored_value' => $newStoredValue,
                'existing_stored_value' => $existingStoredValue,
                'changed' => $newStoredValue !== $existingStoredValue,
                'effective_value' => $newStoredValue !== null
                    ? OperationalSettings::castStoredValue($newStoredValue, $definition)
                    : config((string) $definition['config_path'], null),
            ];
        }

        return $normalized;
    }

    private function normalizeOperationalFieldValue(array $definition, mixed $rawValue): ?string
    {
        $type = (string) ($definition['value_type'] ?? 'string');

        if ($type === 'boolean') {
            return in_array((string) $rawValue, ['1', 'true', 'on'], true) ? 'true' : 'false';
        }

        if ($rawValue === null) {
            return null;
        }

        if ($type === 'integer') {
            $trimmed = trim((string) $rawValue);
            if ($trimmed === '') {
                return null;
            }

            if (filter_var($trimmed, FILTER_VALIDATE_INT) === false) {
                throw new \RuntimeException(((string) ($definition['label'] ?? 'Field')) . ' must be an integer.');
            }

            return $trimmed;
        }

        $trimmed = trim((string) $rawValue);
        if ($trimmed === '') {
            return null;
        }

        if ($trimmed === '__EMPTY__') {
            return '';
        }

        return $trimmed;
    }

    private function validateOperationalSettingsPayload(array $normalized): void
    {
        foreach ($normalized as $field) {
            $definition = $field['definition'];
            $label = (string) ($definition['label'] ?? 'Field');
            $effective = $field['effective_value'];

            switch ((string) ($definition['validator'] ?? '')) {
                case 'url':
                    if (!is_string($effective) || filter_var($effective, FILTER_VALIDATE_URL) === false) {
                        throw new \RuntimeException($label . ' must be a valid URL.');
                    }
                    break;
                case 'timezone':
                    if (!is_string($effective) || !in_array($effective, \DateTimeZone::listIdentifiers(), true)) {
                        throw new \RuntimeException($label . ' must be a valid PHP timezone identifier.');
                    }
                    break;
                case 'email':
                    if ($effective !== null && $effective !== '' && (!is_string($effective) || filter_var($effective, FILTER_VALIDATE_EMAIL) === false)) {
                        throw new \RuntimeException($label . ' must be a valid email address.');
                    }
                    break;
                case 'port':
                    if ($effective !== null && ((int) $effective < 1 || (int) $effective > 65535)) {
                        throw new \RuntimeException($label . ' must be between 1 and 65535.');
                    }
                    break;
                case 'positive_integer':
                    if ($effective === null || (int) $effective <= 0) {
                        throw new \RuntimeException($label . ' must be greater than zero.');
                    }
                    break;
                case 'security_timeout':
                    if ($effective === null || (int) $effective < 60) {
                        throw new \RuntimeException($label . ' must be at least 60 seconds.');
                    }
                    break;
            }
        }

        $mailEnabled = (bool) ($normalized['mail_enabled']['effective_value'] ?? false);
        $mailTransport = (string) ($normalized['mail_transport']['effective_value'] ?? 'smtp');
        $mailFromAddress = trim((string) ($normalized['mail_from_address']['effective_value'] ?? ''));
        $mailFromName = trim((string) ($normalized['mail_from_name']['effective_value'] ?? ''));
        $mailHost = trim((string) ($normalized['mail_host']['effective_value'] ?? ''));
        $mailPort = (int) ($normalized['mail_port']['effective_value'] ?? 0);
        $mailUsername = trim((string) ($normalized['mail_username']['effective_value'] ?? ''));
        $mailPassword = trim((string) ($normalized['mail_password']['effective_value'] ?? ''));
        $mailEncryption = (string) ($normalized['mail_encryption']['effective_value'] ?? 'tls');

        if ($mailEnabled) {
            if (!in_array($mailTransport, ['smtp', 'mailjet', 'mail'], true)) {
                throw new \RuntimeException('Mail transport must be SMTP, Mailjet API, or PHP mail().');
            }

            if ($mailFromAddress === '' || filter_var($mailFromAddress, FILTER_VALIDATE_EMAIL) === false) {
                throw new \RuntimeException('From Address is required when mail is enabled.');
            }

            if ($mailFromName === '') {
                throw new \RuntimeException('From Name is required when mail is enabled.');
            }

            if (!in_array($mailEncryption, ['tls', 'ssl', ''], true)) {
                throw new \RuntimeException('Mail encryption must be TLS, SSL, or None.');
            }

            if ($mailTransport === 'smtp') {
                if ($mailHost === '') {
                    throw new \RuntimeException('Mail Host is required for SMTP transport.');
                }
                if ($mailPort < 1 || $mailPort > 65535) {
                    throw new \RuntimeException('Mail Port must be between 1 and 65535 for SMTP transport.');
                }
            }

            if ($mailTransport === 'mailjet') {
                if ($mailUsername === '' || $mailPassword === '') {
                    throw new \RuntimeException('Mailjet API key and secret are required for Mailjet transport.');
                }
            }
        }

        $b2Enabled = (bool) ($normalized['b2_enabled']['effective_value'] ?? false);
        if ($b2Enabled) {
            if (trim((string) ($normalized['b2_key_id']['effective_value'] ?? '')) === '') {
                throw new \RuntimeException('B2 Key ID is required when B2 is enabled.');
            }
            if (trim((string) ($normalized['b2_application_key']['effective_value'] ?? '')) === '') {
                throw new \RuntimeException('B2 Application Key is required when B2 is enabled.');
            }
            if (trim((string) ($normalized['b2_bucket_name']['effective_value'] ?? '')) === '') {
                throw new \RuntimeException('B2 Bucket Name is required when B2 is enabled.');
            }
        }
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
