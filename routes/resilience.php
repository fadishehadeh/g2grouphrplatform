<?php

declare(strict_types=1);

use App\Middleware\AccountStatusMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Modules\Resilience\ResilienceController;

$router = $app->router();
$resilienceMiddleware = [
    AuthMiddleware::class,
    AccountStatusMiddleware::class,
    [RoleMiddleware::class, ['super_admin']],
];

$router->get('/admin/resilience', [ResilienceController::class, 'index'], $resilienceMiddleware);
$router->post('/admin/resilience/run-now', [ResilienceController::class, 'runNow'], $resilienceMiddleware);
$router->post('/admin/resilience/backups/{runId}/links', [ResilienceController::class, 'generateLinks'], $resilienceMiddleware);
$router->get('/admin/resilience/backups/download/{token}', [ResilienceController::class, 'download'], $resilienceMiddleware);
