<?php

declare(strict_types=1);

use App\Middleware\AccountStatusMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\PermissionMiddleware;
use App\Modules\Onboarding\OnboardingController;

$router = $app->router();
$onboardingBaseMiddleware = [
    AuthMiddleware::class,
    AccountStatusMiddleware::class,
];

$router->get('/onboarding', [OnboardingController::class, 'index'], [
    ...$onboardingBaseMiddleware,
    [PermissionMiddleware::class, ['onboarding.manage']],
]);

$router->get('/onboarding/templates', [OnboardingController::class, 'templates'], [
    ...$onboardingBaseMiddleware,
    [PermissionMiddleware::class, ['onboarding.manage']],
]);

$router->post('/onboarding/templates', [OnboardingController::class, 'storeTemplate'], [
    ...$onboardingBaseMiddleware,
    [PermissionMiddleware::class, ['onboarding.manage']],
]);

$router->get('/onboarding/templates/{id}', [OnboardingController::class, 'templateShow'], [
    ...$onboardingBaseMiddleware,
    [PermissionMiddleware::class, ['onboarding.manage']],
]);

$router->post('/onboarding/templates/{id}/tasks', [OnboardingController::class, 'storeTemplateTask'], [
    ...$onboardingBaseMiddleware,
    [PermissionMiddleware::class, ['onboarding.manage']],
]);

$router->post('/onboarding/templates/tasks/{id}/update', [OnboardingController::class, 'updateTemplateTask'], [
    ...$onboardingBaseMiddleware,
    [PermissionMiddleware::class, ['onboarding.manage']],
]);

$router->get('/onboarding/create/{employeeId}', [OnboardingController::class, 'create'], [
    ...$onboardingBaseMiddleware,
    [PermissionMiddleware::class, ['onboarding.manage']],
]);

$router->post('/onboarding/create/{employeeId}', [OnboardingController::class, 'store'], [
    ...$onboardingBaseMiddleware,
    [PermissionMiddleware::class, ['onboarding.manage']],
]);

$router->get('/onboarding/{id}', [OnboardingController::class, 'show'], [
    ...$onboardingBaseMiddleware,
    [PermissionMiddleware::class, ['onboarding.manage']],
]);

$router->post('/onboarding/tasks/{id}/update', [OnboardingController::class, 'updateTask'], [
    ...$onboardingBaseMiddleware,
    [PermissionMiddleware::class, ['onboarding.manage']],
]);