<?php

declare(strict_types=1);

use App\Middleware\AccountStatusMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\PermissionMiddleware;
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