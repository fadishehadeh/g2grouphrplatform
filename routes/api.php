<?php

declare(strict_types=1);

use App\Middleware\ApiAuthMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\AccountStatusMiddleware;
use App\Modules\Api\EmployeeApiController;
use App\Modules\Api\LeaveApiController;
use App\Modules\Api\ApiTokenController;

$router = $app->router();

// ── REST API v1 ───────────────────────────────────────────────────────────────
// All routes under /api/v1/* require a valid Bearer token.

$apiMiddleware = [ApiAuthMiddleware::class];

// Employees
$router->get('/api/v1/employees',      [EmployeeApiController::class, 'index'], $apiMiddleware);
$router->get('/api/v1/employees/{id}', [EmployeeApiController::class, 'show'],  $apiMiddleware);

// Leave
$router->get('/api/v1/leave/requests', [LeaveApiController::class, 'myRequests'], $apiMiddleware);
$router->get('/api/v1/leave/balances', [LeaveApiController::class, 'myBalances'], $apiMiddleware);
$router->get('/api/v1/leave/pending',  [LeaveApiController::class, 'pending'],    $apiMiddleware);

// ── API Token Management (web UI, session auth) ───────────────────────────────

$webMiddleware = [AuthMiddleware::class, AccountStatusMiddleware::class];

$router->get('/profile/api-tokens',            [ApiTokenController::class, 'index'],  $webMiddleware);
$router->post('/profile/api-tokens',           [ApiTokenController::class, 'store'],  $webMiddleware);
$router->post('/profile/api-tokens/{id}/revoke', [ApiTokenController::class, 'revoke'], $webMiddleware);
