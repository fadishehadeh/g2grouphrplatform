<?php

declare(strict_types=1);

use App\Middleware\AccountStatusMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\PermissionMiddleware;
use App\Modules\Reports\ReportController;

$router = $app->router();
$reportsBaseMiddleware = [
    AuthMiddleware::class,
    AccountStatusMiddleware::class,
];

$router->get('/reports', [ReportController::class, 'index'], [
    ...$reportsBaseMiddleware,
    [PermissionMiddleware::class, ['reports.view_hr', 'reports.view_team']],
]);

$router->get('/reports/headcount', [ReportController::class, 'headcount'], [
    ...$reportsBaseMiddleware,
    [PermissionMiddleware::class, ['reports.view_hr', 'reports.view_team']],
]);

$router->get('/reports/headcount/export-excel', [ReportController::class, 'exportHeadcountExcel'], [
    ...$reportsBaseMiddleware,
    [PermissionMiddleware::class, ['reports.view_hr', 'reports.view_team']],
]);

$router->get('/reports/headcount/export-pdf', [ReportController::class, 'exportHeadcountPdf'], [
    ...$reportsBaseMiddleware,
    [PermissionMiddleware::class, ['reports.view_hr', 'reports.view_team']],
]);

$router->get('/reports/department', [ReportController::class, 'department'], [
    ...$reportsBaseMiddleware,
    [PermissionMiddleware::class, ['reports.view_hr', 'reports.view_team']],
]);

$router->get('/reports/leave-usage', [ReportController::class, 'leaveUsage'], [
    ...$reportsBaseMiddleware,
    [PermissionMiddleware::class, ['reports.view_hr', 'reports.view_team']],
]);

$router->get('/reports/new-joiners', [ReportController::class, 'newJoiners'], [
    ...$reportsBaseMiddleware,
    [PermissionMiddleware::class, ['reports.view_hr', 'reports.view_team']],
]);

$router->get('/reports/exits', [ReportController::class, 'exits'], [
    ...$reportsBaseMiddleware,
    [PermissionMiddleware::class, ['reports.view_hr', 'reports.view_team']],
]);

$router->get('/reports/documents', [ReportController::class, 'documents'], [
    ...$reportsBaseMiddleware,
    [PermissionMiddleware::class, ['reports.view_hr', 'reports.view_team']],
]);

$router->get('/reports/audit', [ReportController::class, 'audit'], [
    ...$reportsBaseMiddleware,
    [PermissionMiddleware::class, ['audit.view']],
]);