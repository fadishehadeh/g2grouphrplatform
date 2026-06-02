<?php

declare(strict_types=1);

use App\Middleware\AccountStatusMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\PermissionMiddleware;
use App\Modules\Leave\LeaveController;

$router = $app->router();
$leaveBaseMiddleware = [
    AuthMiddleware::class,
    AccountStatusMiddleware::class,
];
$leaveVisibilityPermissions = ['leave.view_self', 'leave.approve_team', 'leave.manage_types'];

$router->get('/leave/my', [LeaveController::class, 'index'], [
    ...$leaveBaseMiddleware,
    [PermissionMiddleware::class, ['leave.view_self']],
]);

$router->get('/leave/balances', [LeaveController::class, 'balances'], [
    ...$leaveBaseMiddleware,
    [PermissionMiddleware::class, $leaveVisibilityPermissions],
]);

$router->post('/admin/leave/balances/assign', [LeaveController::class, 'assignBalances'], [
    ...$leaveBaseMiddleware,
    [PermissionMiddleware::class, ['leave.manage_types']],
]);

$router->post('/admin/leave/balances/adjust', [LeaveController::class, 'adjustBalance'], [
    ...$leaveBaseMiddleware,
    [PermissionMiddleware::class, ['leave.manage_types']],
]);

$router->get('/leave/requests', [LeaveController::class, 'requests'], [
    ...$leaveBaseMiddleware,
    [PermissionMiddleware::class, $leaveVisibilityPermissions],
]);

$router->get('/leave/requests/export-excel', [LeaveController::class, 'exportRequestsExcel'], [
    ...$leaveBaseMiddleware,
    [PermissionMiddleware::class, $leaveVisibilityPermissions],
]);

$router->get('/leave/requests/export-pdf', [LeaveController::class, 'exportRequestsPdf'], [
    ...$leaveBaseMiddleware,
    [PermissionMiddleware::class, $leaveVisibilityPermissions],
]);

$router->get('/leave/requests/{id}', [LeaveController::class, 'showRequest'], [
    ...$leaveBaseMiddleware,
    [PermissionMiddleware::class, $leaveVisibilityPermissions],
]);

$router->get('/leave/calendar', [LeaveController::class, 'calendar'], [
    ...$leaveBaseMiddleware,
    [PermissionMiddleware::class, $leaveVisibilityPermissions],
]);

$router->get('/leave/request', [LeaveController::class, 'create'], [
    ...$leaveBaseMiddleware,
    [PermissionMiddleware::class, ['leave.submit']],
]);

$router->post('/leave/request', [LeaveController::class, 'store'], [
    ...$leaveBaseMiddleware,
    [PermissionMiddleware::class, ['leave.submit']],
]);

$router->get('/leave/approvals', [LeaveController::class, 'approvals'], [
    ...$leaveBaseMiddleware,
    [PermissionMiddleware::class, ['leave.approve_team', 'leave.manage_types']],
]);

$router->post('/leave/{id}/approve', [LeaveController::class, 'approve'], [
    ...$leaveBaseMiddleware,
    [PermissionMiddleware::class, ['leave.approve_team', 'leave.manage_types']],
]);

$router->post('/leave/{id}/reject', [LeaveController::class, 'reject'], [
    ...$leaveBaseMiddleware,
    [PermissionMiddleware::class, ['leave.approve_team', 'leave.manage_types']],
]);

$router->get('/admin/leave/types', [LeaveController::class, 'types'], [
    ...$leaveBaseMiddleware,
    [PermissionMiddleware::class, ['leave.manage_types']],
]);

$router->post('/admin/leave/types/{id}/update', [LeaveController::class, 'updateType'], [
    ...$leaveBaseMiddleware,
    [PermissionMiddleware::class, ['leave.manage_types']],
]);

$router->post('/admin/leave/types', [LeaveController::class, 'storeType'], [
    ...$leaveBaseMiddleware,
    [PermissionMiddleware::class, ['leave.manage_types']],
]);

$router->get('/admin/leave/policies', [LeaveController::class, 'policies'], [
    ...$leaveBaseMiddleware,
    [PermissionMiddleware::class, ['leave.manage_types']],
]);

$router->post('/admin/leave/policies', [LeaveController::class, 'storePolicy'], [
    ...$leaveBaseMiddleware,
    [PermissionMiddleware::class, ['leave.manage_types']],
]);

$router->post('/admin/leave/policies/rules', [LeaveController::class, 'storePolicyRule'], [
    ...$leaveBaseMiddleware,
    [PermissionMiddleware::class, ['leave.manage_types']],
]);

$router->post('/admin/leave/policies/rules/{id}/update', [LeaveController::class, 'updatePolicyRule'], [
    ...$leaveBaseMiddleware,
    [PermissionMiddleware::class, ['leave.manage_types']],
]);

$router->post('/admin/leave/policies/rules/{id}/delete', [LeaveController::class, 'deletePolicyRule'], [
    ...$leaveBaseMiddleware,
    [PermissionMiddleware::class, ['leave.manage_types']],
]);

$router->get('/admin/leave/holidays', [LeaveController::class, 'holidays'], [
    ...$leaveBaseMiddleware,
    [PermissionMiddleware::class, ['leave.manage_types']],
]);

$router->post('/admin/leave/holidays', [LeaveController::class, 'storeHoliday'], [
    ...$leaveBaseMiddleware,
    [PermissionMiddleware::class, ['leave.manage_types']],
]);

$router->get('/admin/leave/weekends', [LeaveController::class, 'weekends'], [
    ...$leaveBaseMiddleware,
    [PermissionMiddleware::class, ['leave.manage_types']],
]);

$router->post('/admin/leave/weekends', [LeaveController::class, 'storeWeekend'], [
    ...$leaveBaseMiddleware,
    [PermissionMiddleware::class, ['leave.manage_types']],
]);