<?php

declare(strict_types=1);

use App\Middleware\AccountStatusMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Modules\Intake\IntakeController;

$router = $app->router();

// ── Public (no auth) ─────────────────────────────────────────────────────────
$router->get('/employee-registration',         [IntakeController::class, 'form']);
$router->post('/employee-registration',        [IntakeController::class, 'submit']);
$router->get('/employee-registration/success', [IntakeController::class, 'success']);

// ── HR-protected ─────────────────────────────────────────────────────────────
$hrMid = [
    AuthMiddleware::class,
    AccountStatusMiddleware::class,
    [RoleMiddleware::class, ['super_admin', 'hr_only']],
];

$router->get('/employee-registration/review',                          [IntakeController::class, 'reviewList'], $hrMid);
$router->get('/employee-registration/review/{token}',                  [IntakeController::class, 'reviewShow'], $hrMid);
$router->post('/employee-registration/review/{token}/approve',         [IntakeController::class, 'approve'],    $hrMid);
$router->post('/employee-registration/review/{token}/reject',          [IntakeController::class, 'reject'],     $hrMid);

// Document download/preview (protected by token)
$router->get('/employee-registration/review/{token}/document/{docId}/download', [IntakeController::class, 'downloadDocument'], $hrMid);
$router->get('/employee-registration/review/{token}/document/{docId}/preview',  [IntakeController::class, 'previewDocument'],  $hrMid);
