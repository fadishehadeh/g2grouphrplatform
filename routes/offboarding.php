<?php

declare(strict_types=1);

use App\Middleware\AccountStatusMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\PermissionMiddleware;
use App\Modules\Offboarding\OffboardingController;

$router = $app->router();
$offboardingBaseMiddleware = [
    AuthMiddleware::class,
    AccountStatusMiddleware::class,
];

$router->get('/offboarding', [OffboardingController::class, 'index'], [
    ...$offboardingBaseMiddleware,
    [PermissionMiddleware::class, ['offboarding.manage']],
]);

$router->get('/offboarding/create/{employeeId}', [OffboardingController::class, 'create'], [
    ...$offboardingBaseMiddleware,
    [PermissionMiddleware::class, ['offboarding.manage']],
]);

$router->post('/offboarding/create/{employeeId}', [OffboardingController::class, 'store'], [
    ...$offboardingBaseMiddleware,
    [PermissionMiddleware::class, ['offboarding.manage']],
]);

$router->post('/offboarding/{id}/tasks', [OffboardingController::class, 'storeTask'], [
    ...$offboardingBaseMiddleware,
    [PermissionMiddleware::class, ['offboarding.manage']],
]);

$router->post('/offboarding/{id}/assets', [OffboardingController::class, 'storeAsset'], [
    ...$offboardingBaseMiddleware,
    [PermissionMiddleware::class, ['offboarding.manage']],
]);

$router->get('/offboarding/{id}', [OffboardingController::class, 'show'], [
    ...$offboardingBaseMiddleware,
    [PermissionMiddleware::class, ['offboarding.manage']],
]);

$router->post('/offboarding/tasks/{id}/update', [OffboardingController::class, 'updateTask'], [
    ...$offboardingBaseMiddleware,
    [PermissionMiddleware::class, ['offboarding.manage']],
]);

$router->post('/offboarding/assets/{id}/update', [OffboardingController::class, 'updateAsset'], [
    ...$offboardingBaseMiddleware,
    [PermissionMiddleware::class, ['offboarding.manage']],
]);