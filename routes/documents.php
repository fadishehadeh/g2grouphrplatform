<?php

declare(strict_types=1);

use App\Middleware\AccountStatusMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\PermissionMiddleware;
use App\Modules\Documents\DocumentController;

$router = $app->router();
$documentBaseMiddleware = [
    AuthMiddleware::class,
    AccountStatusMiddleware::class,
];

$router->get('/documents', [DocumentController::class, 'index'], [
    ...$documentBaseMiddleware,
    [PermissionMiddleware::class, ['documents.manage_all']],
]);

$router->get('/documents/categories', [DocumentController::class, 'categories'], [
    ...$documentBaseMiddleware,
    [PermissionMiddleware::class, ['documents.manage_all']],
]);

$router->post('/documents/categories', [DocumentController::class, 'storeCategory'], [
    ...$documentBaseMiddleware,
    [PermissionMiddleware::class, ['documents.manage_all']],
]);

$router->get('/documents/expiring', [DocumentController::class, 'expiring'], [
    ...$documentBaseMiddleware,
    [PermissionMiddleware::class, ['documents.manage_all']],
]);

$router->post('/documents/send-expiry-alerts', [DocumentController::class, 'sendExpiryAlerts'], [
    ...$documentBaseMiddleware,
    [PermissionMiddleware::class, ['documents.manage_all']],
]);

// Token-based download (POST issues a 15-min signed token, GET /dl/{token} serves the file)
$router->post('/documents/{id}/token', [DocumentController::class, 'issueToken'], [
    ...$documentBaseMiddleware,
    [PermissionMiddleware::class, ['documents.manage_all', 'documents.view_self', 'documents.upload_self']],
]);

// Public token route — no permission middleware, the token IS the auth
$router->get('/documents/dl/{token}', [DocumentController::class, 'downloadViaToken'], [
    AuthMiddleware::class,
    AccountStatusMiddleware::class,
]);

// Legacy direct download kept for backward compatibility (still auth-gated)
$router->get('/documents/{id}/download', [DocumentController::class, 'download'], [
    ...$documentBaseMiddleware,
    [PermissionMiddleware::class, ['documents.manage_all', 'documents.view_self', 'documents.upload_self']],
]);

$router->get('/employees/{id}/documents/upload', [DocumentController::class, 'upload'], [
    ...$documentBaseMiddleware,
    [PermissionMiddleware::class, ['documents.upload_self', 'documents.view_self', 'documents.manage_all']],
]);

$router->post('/employees/{id}/documents/upload', [DocumentController::class, 'storeUpload'], [
    ...$documentBaseMiddleware,
    [PermissionMiddleware::class, ['documents.upload_self', 'documents.manage_all']],
]);