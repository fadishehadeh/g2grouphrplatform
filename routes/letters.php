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

// View generated letter (employee + admin)
$router->get('/letters/{id}/view', [LetterController::class, 'viewLetter'], [
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
