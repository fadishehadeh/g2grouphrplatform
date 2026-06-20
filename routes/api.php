<?php

declare(strict_types=1);

use App\Middleware\ApiAuthMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\AccountStatusMiddleware;
use App\Middleware\JwtAuthMiddleware;
use App\Modules\Api\AuthApiController;
use App\Modules\Api\DocumentApiController;
use App\Modules\Api\EmployeeApiController;
use App\Modules\Api\LeaveApiController;
use App\Modules\Api\LetterApiController;
use App\Modules\Api\ProfileApiController;
use App\Modules\Api\ApiTokenController;

$router = $app->router();

// ── JWT Auth (no middleware — these issue tokens) ─────────────────────────────
$router->post('/api/v1/auth/login',   [AuthApiController::class, 'login']);
$router->post('/api/v1/auth/otp',     [AuthApiController::class, 'otp']);
$router->post('/api/v1/auth/refresh', [AuthApiController::class, 'refresh']);
$router->post('/api/v1/auth/logout',  [AuthApiController::class, 'logout']);

// ── JWT-protected REST API v1 ─────────────────────────────────────────────────
$jwtMiddleware = [JwtAuthMiddleware::class];

// Profile
$router->get('/api/v1/profile/me',   [ProfileApiController::class, 'me'],     $jwtMiddleware);
$router->post('/api/v1/profile/me',  [ProfileApiController::class, 'update'], $jwtMiddleware); // PATCH via POST

// Leave — mobile combined endpoint
$router->get('/api/v1/leave/my',          [LeaveApiController::class, 'my'],           $jwtMiddleware);
$router->post('/api/v1/leave/request',    [LeaveApiController::class, 'submitRequest'], $jwtMiddleware);
$router->post('/api/v1/leave/{id}/approve', [LeaveApiController::class, 'approve'],    $jwtMiddleware);
$router->post('/api/v1/leave/{id}/reject',  [LeaveApiController::class, 'reject'],     $jwtMiddleware);

// Letters
$router->get('/api/v1/letters/my',           [LetterApiController::class, 'my'],       $jwtMiddleware);
$router->post('/api/v1/letters/request',     [LetterApiController::class, 'request'],  $jwtMiddleware);
$router->get('/api/v1/letters/{id}/download',[LetterApiController::class, 'download'], $jwtMiddleware);

// Documents
$router->get('/api/v1/documents/my', [DocumentApiController::class, 'my'], $jwtMiddleware);

// ── Personal API token REST API v1 (existing — Bearer personal token) ─────────
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

$router->get('/profile/api-tokens',              [ApiTokenController::class, 'index'],  $webMiddleware);
$router->post('/profile/api-tokens',             [ApiTokenController::class, 'store'],  $webMiddleware);
$router->post('/profile/api-tokens/{id}/revoke', [ApiTokenController::class, 'revoke'], $webMiddleware);
