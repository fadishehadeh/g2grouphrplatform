<?php

declare(strict_types=1);

use App\Middleware\AccountStatusMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\PermissionMiddleware;
use App\Modules\Letters\LetterController;

$router = $app->router();
$lettersBaseMiddleware = [
    AuthMiddleware::class,
    AccountStatusMiddleware::class,
];

// Employee: view own letter requests
$router->get('/letters/my', [LetterController::class, 'myLetters'], [
    ...$lettersBaseMiddleware,
    [PermissionMiddleware::class, ['letters.request', 'letters.manage']],
]);

// Employee: request form
$router->get('/letters/request', [LetterController::class, 'requestForm'], [
    ...$lettersBaseMiddleware,
    [PermissionMiddleware::class, ['letters.request']],
]);

// Employee: submit letter request
$router->post('/letters/request', [LetterController::class, 'submitRequest'], [
    ...$lettersBaseMiddleware,
    [PermissionMiddleware::class, ['letters.request']],
]);

// HR Admin: all letter requests
$router->get('/letters/admin', [LetterController::class, 'adminLetters'], [
    ...$lettersBaseMiddleware,
    [PermissionMiddleware::class, ['letters.manage']],
]);

// HR Admin: letter templates (must be before {id} routes)
$router->get('/letters/templates', [LetterController::class, 'templates'], [
    ...$lettersBaseMiddleware,
    [PermissionMiddleware::class, ['letters.manage']],
]);

$router->get('/letters/templates/{type}/edit', [LetterController::class, 'editTemplate'], [
    ...$lettersBaseMiddleware,
    [PermissionMiddleware::class, ['letters.manage']],
]);

$router->post('/letters/templates/{type}/save', [LetterController::class, 'saveTemplate'], [
    ...$lettersBaseMiddleware,
    [PermissionMiddleware::class, ['letters.manage']],
]);

$router->post('/letters/templates/{type}/reset', [LetterController::class, 'resetTemplate'], [
    ...$lettersBaseMiddleware,
    [PermissionMiddleware::class, ['letters.manage']],
]);

// View generated letter (employee + admin)
$router->get('/letters/{id}/view', [LetterController::class, 'viewLetter'], [
    ...$lettersBaseMiddleware,
    [PermissionMiddleware::class, ['letters.request', 'letters.manage']],
]);

// Download letter as PDF (admin + employee)
$router->get('/letters/{id}/download', [LetterController::class, 'downloadPdf'], [
    ...$lettersBaseMiddleware,
    [PermissionMiddleware::class, ['letters.request', 'letters.manage']],
]);

// HR Admin: show generate/review form
$router->get('/letters/{id}', [LetterController::class, 'showRequest'], [
    ...$lettersBaseMiddleware,
    [PermissionMiddleware::class, ['letters.manage']],
]);

// HR Admin: generate letter
$router->post('/letters/{id}/generate', [LetterController::class, 'generate'], [
    ...$lettersBaseMiddleware,
    [PermissionMiddleware::class, ['letters.manage']],
]);

// HR Admin: reject request
$router->post('/letters/{id}/reject', [LetterController::class, 'reject'], [
    ...$lettersBaseMiddleware,
    [PermissionMiddleware::class, ['letters.manage']],
]);

// HR Admin: update letter status
$router->post('/letters/{id}/status', [LetterController::class, 'updateStatus'], [
    ...$lettersBaseMiddleware,
    [PermissionMiddleware::class, ['letters.manage']],
]);
