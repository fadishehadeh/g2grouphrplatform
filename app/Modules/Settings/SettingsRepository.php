<?php

declare(strict_types=1);

namespace App\Modules\Settings;

use App\Core\Database;

final class SettingsRepository
{
    public function __construct(private Database $database)
    {
    }

    public function listSettings(): array
    {
        return $this->database->fetchAll(
            'SELECT id, category_name, setting_key, setting_value, value_type, updated_by, updated_at
             FROM settings
             ORDER BY category_name ASC, setting_key ASC'
        );
    }

    public function findSetting(int $id): ?array
    {
        return $this->database->fetch(
            'SELECT id, category_name, setting_key, setting_value, value_type FROM settings WHERE id = :id LIMIT 1',
            ['id' => $id]
        );
    }

    public function updateSetting(int $id, ?string $value, ?int $actorId): void
    {
        $this->database->execute(
            'UPDATE settings SET setting_value = :setting_value, updated_by = :updated_by WHERE id = :id',
            [
                'setting_value' => $value,
                'updated_by' => $actorId,
                'id' => $id,
            ]
        );
    }

    public function shifts(): array
    {
        return $this->database->fetchAll(
            "SELECT s.id, s.company_id, c.name AS company_name, s.name, s.code, s.start_time, s.end_time,
                    s.late_grace_minutes, s.half_day_minutes, s.is_night_shift, s.is_active,
                    (SELECT COUNT(DISTINCT wsd.work_schedule_id)
                     FROM work_schedule_days wsd
                     WHERE wsd.shift_id = s.id AND wsd.is_working_day = 1) AS schedule_count,
                    (SELECT COUNT(*)
                     FROM employee_schedule_assignments esa
                     WHERE esa.shift_id = s.id AND esa.status = 'active') AS assignment_count
             FROM shifts s
             INNER JOIN companies c ON c.id = s.company_id
             ORDER BY s.is_active DESC, c.name ASC, s.name ASC"
        );
    }

    public function schedules(): array
    {
        return $this->database->fetchAll(
            "SELECT ws.id, ws.company_id, c.name AS company_name, ws.name, ws.code, ws.weekly_hours, ws.is_active,
                    (SELECT COUNT(*)
                     FROM work_schedule_days wsd
                     WHERE wsd.work_schedule_id = ws.id AND wsd.is_working_day = 1) AS working_day_count,
                    (SELECT COUNT(*)
                     FROM employee_schedule_assignments esa
                     WHERE esa.work_schedule_id = ws.id AND esa.status = 'active') AS assignment_count,
                    COALESCE((
                        SELECT GROUP_CONCAT(
                            CONCAT(
                                CASE wsd2.day_of_week
                                    WHEN 1 THEN 'Mon'
                                    WHEN 2 THEN 'Tue'
                                    WHEN 3 THEN 'Wed'
                                    WHEN 4 THEN 'Thu'
                                    WHEN 5 THEN 'Fri'
                                    WHEN 6 THEN 'Sat'
                                    WHEN 7 THEN 'Sun'
                                    ELSE 'Day'
                                END,
                                ': ',
                                CASE
                                    WHEN wsd2.is_working_day = 1 THEN COALESCE(s2.code, 'No shift')
                                    ELSE 'Off'
                                END
                            )
                            ORDER BY wsd2.day_of_week SEPARATOR ', '
                        )
                        FROM work_schedule_days wsd2
                        LEFT JOIN shifts s2 ON s2.id = wsd2.shift_id
                        WHERE wsd2.work_schedule_id = ws.id
                    ), '') AS day_summary
             FROM work_schedules ws
             INNER JOIN companies c ON c.id = ws.company_id
             ORDER BY ws.is_active DESC, c.name ASC, ws.name ASC"
        );
    }

    public function attendanceStatuses(): array
    {
        return $this->database->fetchAll(
            "SELECT ast.id, ast.name, ast.code, ast.color_class, ast.counts_as_present, ast.counts_as_absent, ast.is_active,
                    COUNT(DISTINCT ar.id) AS usage_count
             FROM attendance_statuses ast
             LEFT JOIN attendance_records ar ON ar.status_id = ast.id
             GROUP BY ast.id, ast.name, ast.code, ast.color_class, ast.counts_as_present, ast.counts_as_absent, ast.is_active
             ORDER BY ast.is_active DESC, ast.name ASC"
        );
    }

    public function attendanceOverview(): array
    {
        return [
            'today_total' => (int) ($this->database->fetchValue(
                'SELECT COUNT(*) FROM attendance_records WHERE attendance_date = CURDATE()'
            ) ?? 0),
            'today_present' => (int) ($this->database->fetchValue(
                'SELECT COUNT(*) FROM attendance_records ar INNER JOIN attendance_statuses ast ON ast.id = ar.status_id WHERE ar.attendance_date = CURDATE() AND ast.counts_as_present = 1'
            ) ?? 0),
            'today_absent' => (int) ($this->database->fetchValue(
                'SELECT COUNT(*) FROM attendance_records ar INNER JOIN attendance_statuses ast ON ast.id = ar.status_id WHERE ar.attendance_date = CURDATE() AND ast.counts_as_absent = 1'
            ) ?? 0),
            'today_late' => (int) ($this->database->fetchValue(
                'SELECT COUNT(*) FROM attendance_records WHERE attendance_date = CURDATE() AND minutes_late > 0'
            ) ?? 0),
            'active_assignments' => (int) ($this->database->fetchValue(
                "SELECT COUNT(*) FROM employee_schedule_assignments
                 WHERE status = 'active'
                   AND effective_from <= CURDATE()
                   AND (effective_to IS NULL OR effective_to >= CURDATE())"
            ) ?? 0),
            'recent_records' => $this->database->fetchAll(
                "SELECT ar.id, ar.attendance_date, ar.clock_in_time, ar.clock_out_time, ar.minutes_late, ar.source, ar.remarks,
                        CONCAT_WS(' ', e.first_name, e.middle_name, e.last_name) AS employee_name,
                        e.employee_code, COALESCE(ast.name, 'Unassigned') AS status_name, COALESCE(ast.color_class, 'secondary') AS color_class,
                        COALESCE(s.name, 'No Shift') AS shift_name
                 FROM attendance_records ar
                 INNER JOIN employees e ON e.id = ar.employee_id
                 LEFT JOIN attendance_statuses ast ON ast.id = ar.status_id
                 LEFT JOIN shifts s ON s.id = ar.shift_id
                 ORDER BY ar.attendance_date DESC, ar.created_at DESC
                 LIMIT 12"
            ),
        ];
    }

    public function employeeOptions(): array
    {
        return $this->database->fetchAll(
            "SELECT id, company_id, CONCAT(employee_code, ' - ', CONCAT_WS(' ', first_name, middle_name, last_name)) AS name
             FROM employees
             WHERE archived_at IS NULL AND employee_status <> :employee_status
             ORDER BY first_name ASC, last_name ASC",
            ['employee_status' => 'archived']
        );
    }

    public function companyOptions(): array
    {
        return $this->database->fetchAll(
            'SELECT id, name FROM companies WHERE status = :status ORDER BY name ASC',
            ['status' => 'active']
        );
    }

    public function shiftOptions(): array
    {
        return $this->database->fetchAll(
            "SELECT id, company_id, CONCAT(name, ' (', code, ')') AS name
             FROM shifts
             WHERE is_active = :is_active
             ORDER BY name ASC",
            ['is_active' => 1]
        );
    }

    public function scheduleOptions(): array
    {
        return $this->database->fetchAll(
            "SELECT ws.id, ws.company_id, CONCAT(c.name, ' - ', ws.name, ' (', ws.code, ')') AS name
             FROM work_schedules ws
             INNER JOIN companies c ON c.id = ws.company_id
             WHERE ws.is_active = :is_active
             ORDER BY c.name ASC, ws.name ASC",
            ['is_active' => 1]
        );
    }

    public function attendanceStatusOptions(): array
    {
        return $this->database->fetchAll(
            'SELECT id, name FROM attendance_statuses WHERE is_active = :is_active ORDER BY name ASC',
            ['is_active' => 1]
        );
    }

    public function listAttendanceRecords(string $search = '', string $dateFrom = '', string $dateTo = '', string $statusId = 'all'): array
    {
        $sql = "SELECT ar.id, ar.attendance_date, ar.minutes_late, ar.source, COALESCE(ar.remarks, '') AS remarks,
                       CONCAT(e.employee_code, ' - ', CONCAT_WS(' ', e.first_name, e.middle_name, e.last_name)) AS employee_name,
                       COALESCE(ast.name, 'Unassigned') AS status_name, COALESCE(ast.color_class, 'secondary') AS color_class,
                       COALESCE(s.name, 'No Shift') AS shift_name,
                       COALESCE(DATE_FORMAT(ar.clock_in_time, '%Y-%m-%d %H:%i'), '—') AS clock_in_time,
                       COALESCE(DATE_FORMAT(ar.clock_out_time, '%Y-%m-%d %H:%i'), '—') AS clock_out_time
                FROM attendance_records ar
                INNER JOIN employees e ON e.id = ar.employee_id
                LEFT JOIN attendance_statuses ast ON ast.id = ar.status_id
                LEFT JOIN shifts s ON s.id = ar.shift_id
                WHERE e.archived_at IS NULL";
        $params = [];

        if ($search !== '') {
            $like = '%' . $search . '%';
            $sql .= " AND (
                e.employee_code LIKE :search_code OR e.first_name LIKE :search_first_name OR e.last_name LIKE :search_last_name
                OR CONCAT_WS(' ', e.first_name, e.middle_name, e.last_name) LIKE :search_full_name
                OR ar.remarks LIKE :search_remarks
            )";
            $params['search_code'] = $like;
            $params['search_first_name'] = $like;
            $params['search_last_name'] = $like;
            $params['search_full_name'] = $like;
            $params['search_remarks'] = $like;
        }

        if ($dateFrom !== '') {
            $sql .= ' AND ar.attendance_date >= :date_from';
            $params['date_from'] = $dateFrom;
        }

        if ($dateTo !== '') {
            $sql .= ' AND ar.attendance_date <= :date_to';
            $params['date_to'] = $dateTo;
        }

        if ($statusId !== 'all' && ctype_digit($statusId)) {
            $sql .= ' AND ar.status_id = :status_id';
            $params['status_id'] = (int) $statusId;
        }

        $sql .= ' ORDER BY ar.attendance_date DESC, e.first_name ASC, e.last_name ASC';

        return $this->database->fetchAll($sql, $params);
    }

    public function listScheduleAssignments(string $search = '', string $status = 'all'): array
    {
        $sql = "SELECT esa.id,
                       CONCAT(e.employee_code, ' - ', CONCAT_WS(' ', e.first_name, e.middle_name, e.last_name)) AS employee_name,
                       CONCAT(ws.name, ' (', ws.code, ')') AS schedule_name,
                       COALESCE(s.name, 'Schedule default') AS shift_name,
                       esa.effective_from,
                       COALESCE(esa.effective_to, 'Open-ended') AS effective_to,
                       esa.status
                FROM employee_schedule_assignments esa
                INNER JOIN employees e ON e.id = esa.employee_id
                INNER JOIN work_schedules ws ON ws.id = esa.work_schedule_id
                LEFT JOIN shifts s ON s.id = esa.shift_id
                WHERE e.archived_at IS NULL";
        $params = [];

        if ($search !== '') {
            $like = '%' . $search . '%';
            $sql .= " AND (
                e.employee_code LIKE :search_code OR e.first_name LIKE :search_first_name OR e.last_name LIKE :search_last_name
                OR CONCAT_WS(' ', e.first_name, e.middle_name, e.last_name) LIKE :search_full_name
                OR ws.name LIKE :search_schedule_name OR ws.code LIKE :search_schedule_code
            )";
            $params['search_code'] = $like;
            $params['search_first_name'] = $like;
            $params['search_last_name'] = $like;
            $params['search_full_name'] = $like;
            $params['search_schedule_name'] = $like;
            $params['search_schedule_code'] = $like;
        }

        if ($status !== 'all') {
            $sql .= ' AND esa.status = :status';
            $params['status'] = $status;
        }

        $sql .= ' ORDER BY esa.status ASC, esa.effective_from DESC, e.first_name ASC, e.last_name ASC';

        return $this->database->fetchAll($sql, $params);
    }

    public function upsertAttendanceRecord(array $data, ?int $actorId): void
    {
        $employeeId = (int) $data['employee_id'];
        $attendanceDate = (string) $data['attendance_date'];
        $shiftId = isset($data['shift_id']) && $data['shift_id'] !== null
            ? (int) $data['shift_id']
            : $this->activeAssignedShiftId($employeeId, $attendanceDate);
        $clockInTime = $data['clock_in_time'] ?? null;

        $payload = [
            'employee_id' => $employeeId,
            'attendance_date' => $attendanceDate,
            'shift_id' => $shiftId,
            'status_id' => isset($data['status_id']) ? (int) $data['status_id'] : null,
            'clock_in_time' => $clockInTime,
            'clock_out_time' => $data['clock_out_time'] ?? null,
            'minutes_late' => $this->calculateMinutesLate($shiftId, is_string($clockInTime) ? $clockInTime : null),
            'source' => (string) ($data['source'] ?? 'manual'),
            'remarks' => $this->nullableString($data['remarks'] ?? null),
            'created_by' => $actorId,
        ];

        $this->database->transaction(function () use ($payload): void {
            $existing = $this->database->fetch(
                'SELECT id FROM attendance_records WHERE employee_id = :employee_id AND attendance_date = :attendance_date LIMIT 1',
                [
                    'employee_id' => $payload['employee_id'],
                    'attendance_date' => $payload['attendance_date'],
                ]
            );

            if ($existing !== null) {
                $this->database->execute(
                    'UPDATE attendance_records
                     SET shift_id = :shift_id,
                         status_id = :status_id,
                         clock_in_time = :clock_in_time,
                         clock_out_time = :clock_out_time,
                         minutes_late = :minutes_late,
                         source = :source,
                         remarks = :remarks
                     WHERE id = :id',
                    [
                        'shift_id' => $payload['shift_id'],
                        'status_id' => $payload['status_id'],
                        'clock_in_time' => $payload['clock_in_time'],
                        'clock_out_time' => $payload['clock_out_time'],
                        'minutes_late' => $payload['minutes_late'],
                        'source' => $payload['source'],
                        'remarks' => $payload['remarks'],
                        'id' => $existing['id'],
                    ]
                );

                return;
            }

            $this->database->execute(
                'INSERT INTO attendance_records (
                    employee_id, attendance_date, shift_id, status_id, clock_in_time, clock_out_time, minutes_late, source, remarks, created_by
                 ) VALUES (
                    :employee_id, :attendance_date, :shift_id, :status_id, :clock_in_time, :clock_out_time, :minutes_late, :source, :remarks, :created_by
                 )',
                $payload
            );
        });
    }

    public function createShift(array $data): void
    {
        $this->database->execute(
            'INSERT INTO shifts (
                company_id, name, code, start_time, end_time, late_grace_minutes, half_day_minutes, is_night_shift, is_active
             ) VALUES (
                :company_id, :name, :code, :start_time, :end_time, :late_grace_minutes, :half_day_minutes, :is_night_shift, :is_active
             )',
            [
                'company_id' => (int) $data['company_id'],
                'name' => (string) $data['name'],
                'code' => (string) $data['code'],
                'start_time' => (string) $data['start_time'],
                'end_time' => (string) $data['end_time'],
                'late_grace_minutes' => (int) $data['late_grace_minutes'],
                'half_day_minutes' => $data['half_day_minutes'] === null ? null : (int) $data['half_day_minutes'],
                'is_night_shift' => (int) $data['is_night_shift'],
                'is_active' => (int) $data['is_active'],
            ]
        );
    }

    public function createSchedule(array $data): void
    {
        $this->database->transaction(function () use ($data): void {
            $this->database->execute(
                'INSERT INTO work_schedules (company_id, name, code, weekly_hours, is_active)
                 VALUES (:company_id, :name, :code, :weekly_hours, :is_active)',
                [
                    'company_id' => (int) $data['company_id'],
                    'name' => (string) $data['name'],
                    'code' => (string) $data['code'],
                    'weekly_hours' => (float) $data['weekly_hours'],
                    'is_active' => (int) $data['is_active'],
                ]
            );

            $scheduleId = (int) $this->database->lastInsertId();

            foreach ($data['days'] as $day) {
                $this->database->execute(
                    'INSERT INTO work_schedule_days (work_schedule_id, day_of_week, shift_id, is_working_day)
                     VALUES (:work_schedule_id, :day_of_week, :shift_id, :is_working_day)',
                    [
                        'work_schedule_id' => $scheduleId,
                        'day_of_week' => (int) $day['day_of_week'],
                        'shift_id' => $day['shift_id'] === null ? null : (int) $day['shift_id'],
                        'is_working_day' => (int) $day['is_working_day'],
                    ]
                );
            }
        });
    }

    public function createScheduleAssignment(array $data): void
    {
        $employeeId = (int) $data['employee_id'];
        $effectiveFrom = (string) $data['effective_from'];
        $effectiveTo = $data['effective_to'] === null ? null : (string) $data['effective_to'];

        $this->database->transaction(function () use ($data, $employeeId, $effectiveFrom, $effectiveTo): void {
            if ($this->assignmentOverlapExists($employeeId, $effectiveFrom, $effectiveTo)) {
                throw new \RuntimeException('Selected employee already has an overlapping schedule assignment.');
            }

            $this->database->execute(
                'INSERT INTO employee_schedule_assignments (
                    employee_id, work_schedule_id, shift_id, effective_from, effective_to, status
                 ) VALUES (
                    :employee_id, :work_schedule_id, :shift_id, :effective_from, :effective_to, :status
                 )',
                [
                    'employee_id' => $employeeId,
                    'work_schedule_id' => (int) $data['work_schedule_id'],
                    'shift_id' => $data['shift_id'] === null ? null : (int) $data['shift_id'],
                    'effective_from' => $effectiveFrom,
                    'effective_to' => $effectiveTo,
                    'status' => (string) $data['status'],
                ]
            );
        });
    }

    public function assignmentOverlapExists(int $employeeId, string $effectiveFrom, ?string $effectiveTo): bool
    {
        if ($effectiveTo === null) {
            $count = $this->database->fetchValue(
                'SELECT COUNT(*)
                 FROM employee_schedule_assignments
                 WHERE employee_id = :employee_id
                   AND (effective_to IS NULL OR effective_to >= :effective_from)',
                [
                    'employee_id' => $employeeId,
                    'effective_from' => $effectiveFrom,
                ]
            );

            return (int) $count > 0;
        }

        $count = $this->database->fetchValue(
            'SELECT COUNT(*)
             FROM employee_schedule_assignments
             WHERE employee_id = :employee_id
               AND effective_from <= :effective_to
               AND (effective_to IS NULL OR effective_to >= :effective_from)',
            [
                'employee_id' => $employeeId,
                'effective_to' => $effectiveTo,
                'effective_from' => $effectiveFrom,
            ]
        );

        return (int) $count > 0;
    }

    public function createAttendanceStatus(array $data): void
    {
        $this->database->execute(
            'INSERT INTO attendance_statuses (
                name, code, color_class, counts_as_present, counts_as_absent, is_active
             ) VALUES (
                :name, :code, :color_class, :counts_as_present, :counts_as_absent, :is_active
             )',
            [
                'name' => (string) $data['name'],
                'code' => (string) $data['code'],
                'color_class' => (string) $data['color_class'],
                'counts_as_present' => (int) $data['counts_as_present'],
                'counts_as_absent' => (int) $data['counts_as_absent'],
                'is_active' => (int) $data['is_active'],
            ]
        );
    }

    private function activeAssignedShiftId(int $employeeId, string $attendanceDate): ?int
    {
        $shiftId = $this->database->fetchValue(
            "SELECT shift_id
             FROM employee_schedule_assignments
             WHERE employee_id = :employee_id
               AND status = 'active'
               AND effective_from <= :effective_from
               AND (effective_to IS NULL OR effective_to >= :effective_to)
               AND shift_id IS NOT NULL
             ORDER BY effective_from DESC
             LIMIT 1",
            [
                'employee_id' => $employeeId,
                'effective_from' => $attendanceDate,
                'effective_to' => $attendanceDate,
            ]
        );

        return $shiftId === null ? null : (int) $shiftId;
    }

    private function calculateMinutesLate(?int $shiftId, ?string $clockInTime): int
    {
        if ($shiftId === null || $clockInTime === null) {
            return 0;
        }

        $shift = $this->database->fetch(
            'SELECT start_time, late_grace_minutes FROM shifts WHERE id = :id LIMIT 1',
            ['id' => $shiftId]
        );

        if ($shift === null) {
            return 0;
        }

        $clockIn = new \DateTimeImmutable($clockInTime);
        $scheduledStart = new \DateTimeImmutable($clockIn->format('Y-m-d') . ' ' . (string) $shift['start_time']);
        $threshold = $scheduledStart->modify('+' . (int) $shift['late_grace_minutes'] . ' minutes');

        if ($clockIn <= $threshold) {
            return 0;
        }

        return (int) ceil(($clockIn->getTimestamp() - $threshold->getTimestamp()) / 60);
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}