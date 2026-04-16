<?php

declare(strict_types=1);

use App\Middleware\AccountStatusMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\PermissionMiddleware;
use App\Middleware\RoleMiddleware;
use App\Modules\Employees\EmployeeController;

$router = $app->router();
$employeeBaseMiddleware = [
    AuthMiddleware::class,
    AccountStatusMiddleware::class,
];

$router->get('/employees', [EmployeeController::class, 'index'], [
    ...$employeeBaseMiddleware,
    [PermissionMiddleware::class, ['employee.view_all']],
]);

$router->get('/employees/create', [EmployeeController::class, 'create'], [
    ...$employeeBaseMiddleware,
    [PermissionMiddleware::class, ['employee.create']],
]);

$router->post('/employees/create', [EmployeeController::class, 'store'], [
    ...$employeeBaseMiddleware,
    [PermissionMiddleware::class, ['employee.create']],
]);

$router->get('/employees/export-excel', [EmployeeController::class, 'exportExcel'], [
    ...$employeeBaseMiddleware,
    [PermissionMiddleware::class, ['employee.view_all']],
]);

$router->get('/employees/export-pdf', [EmployeeController::class, 'exportPdf'], [
    ...$employeeBaseMiddleware,
    [PermissionMiddleware::class, ['employee.view_all']],
]);

$router->get('/employees/import', [EmployeeController::class, 'importForm'], [
    ...$employeeBaseMiddleware,
    [PermissionMiddleware::class, ['employee.create']],
]);

$router->post('/employees/import', [EmployeeController::class, 'import'], [
    ...$employeeBaseMiddleware,
    [PermissionMiddleware::class, ['employee.create']],
]);

$router->get('/employees/import-template', [EmployeeController::class, 'downloadTemplate'], [
    ...$employeeBaseMiddleware,
    [PermissionMiddleware::class, ['employee.create']],
]);

$router->get('/employees/{id}', [EmployeeController::class, 'show'], [
    ...$employeeBaseMiddleware,
    [PermissionMiddleware::class, ['employee.view_all', 'employee.view_self']],
]);

$router->get('/employees/{id}/history', [EmployeeController::class, 'history'], [
    ...$employeeBaseMiddleware,
    [PermissionMiddleware::class, ['employee.view_all', 'employee.view_self']],
]);

$router->get('/employees/{id}/archive', [EmployeeController::class, 'archive'], [
    ...$employeeBaseMiddleware,
    [PermissionMiddleware::class, ['employee.archive']],
]);

$router->post('/employees/{id}/archive', [EmployeeController::class, 'storeArchive'], [
    ...$employeeBaseMiddleware,
    [PermissionMiddleware::class, ['employee.archive']],
]);

$router->get('/employees/{id}/edit', [EmployeeController::class, 'edit'], [
    ...$employeeBaseMiddleware,
    [PermissionMiddleware::class, ['employee.edit']],
]);

$router->post('/employees/{id}/edit', [EmployeeController::class, 'update'], [
    ...$employeeBaseMiddleware,
    [PermissionMiddleware::class, ['employee.edit']],
]);

$router->post('/employees/{id}/insurance', [EmployeeController::class, 'saveInsurance'], [
    ...$employeeBaseMiddleware,
    [PermissionMiddleware::class, ['employee.edit']],
]);

$router->post('/employees/{id}/delete', [EmployeeController::class, 'destroy'], [
    ...$employeeBaseMiddleware,
    [PermissionMiddleware::class, ['employee.delete']],
]);

$router->post('/employees/{id}/send-access', [EmployeeController::class, 'sendAccess'], [
    AuthMiddleware::class,
    AccountStatusMiddleware::class,
    [RoleMiddleware::class, ['super_admin', 'hr_only']],
]);