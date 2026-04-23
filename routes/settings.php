<?php

declare(strict_types=1);

use App\Middleware\AccountStatusMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\PermissionMiddleware;
use App\Modules\Settings\SettingsController;

$router = $app->router();
$settingsBaseMiddleware = [
    AuthMiddleware::class,
    AccountStatusMiddleware::class,
    [PermissionMiddleware::class, ['settings.manage']],
];

$router->get('/settings', [SettingsController::class, 'index'], $settingsBaseMiddleware);
$router->post('/settings/{id}/update', [SettingsController::class, 'update'], $settingsBaseMiddleware);
$router->get('/settings/attendance', [SettingsController::class, 'attendance'], $settingsBaseMiddleware);
$router->get('/settings/attendance/records', [SettingsController::class, 'attendanceRecords'], $settingsBaseMiddleware);
$router->post('/settings/attendance/records', [SettingsController::class, 'storeAttendanceRecord'], $settingsBaseMiddleware);
$router->get('/settings/attendance/assignments', [SettingsController::class, 'attendanceAssignments'], $settingsBaseMiddleware);
$router->post('/settings/attendance/assignments', [SettingsController::class, 'storeAttendanceAssignment'], $settingsBaseMiddleware);
$router->get('/settings/shifts', [SettingsController::class, 'shifts'], $settingsBaseMiddleware);
$router->post('/settings/shifts', [SettingsController::class, 'storeShift'], $settingsBaseMiddleware);
$router->post('/settings/shifts/{id}/update', [SettingsController::class, 'updateShift'], $settingsBaseMiddleware);
$router->get('/settings/schedules', [SettingsController::class, 'schedules'], $settingsBaseMiddleware);
$router->post('/settings/schedules', [SettingsController::class, 'storeSchedule'], $settingsBaseMiddleware);
$router->post('/settings/schedules/{id}/update', [SettingsController::class, 'updateSchedule'], $settingsBaseMiddleware);
$router->get('/settings/attendance-statuses', [SettingsController::class, 'attendanceStatuses'], $settingsBaseMiddleware);
$router->post('/settings/attendance-statuses', [SettingsController::class, 'storeAttendanceStatus'], $settingsBaseMiddleware);