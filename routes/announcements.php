<?php

declare(strict_types=1);

use App\Middleware\AccountStatusMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\PermissionMiddleware;
use App\Modules\Announcements\AnnouncementController;
use App\Modules\Notifications\NotificationController;

$router = $app->router();
$communicationsBaseMiddleware = [
    AuthMiddleware::class,
    AccountStatusMiddleware::class,
];

$router->get('/announcements', [AnnouncementController::class, 'index'], [
    ...$communicationsBaseMiddleware,
    [PermissionMiddleware::class, ['announcements.view', 'announcements.manage']],
]);

$router->post('/announcements', [AnnouncementController::class, 'store'], [
    ...$communicationsBaseMiddleware,
    [PermissionMiddleware::class, ['announcements.manage']],
]);

$router->get('/announcements/attachments/{id}', [AnnouncementController::class, 'downloadAttachment'], [
    ...$communicationsBaseMiddleware,
    [PermissionMiddleware::class, ['announcements.view', 'announcements.manage']],
]);

$router->get('/announcements/{id}', [AnnouncementController::class, 'show'], [
    ...$communicationsBaseMiddleware,
    [PermissionMiddleware::class, ['announcements.view', 'announcements.manage']],
]);

$router->get('/notifications', [NotificationController::class, 'index'], [
    ...$communicationsBaseMiddleware,
    [PermissionMiddleware::class, ['notifications.view_self']],
]);

$router->post('/notifications/read-all', [NotificationController::class, 'markAll'], [
    ...$communicationsBaseMiddleware,
    [PermissionMiddleware::class, ['notifications.view_self']],
]);

$router->post('/notifications/{id}/read', [NotificationController::class, 'markRead'], [
    ...$communicationsBaseMiddleware,
    [PermissionMiddleware::class, ['notifications.view_self']],
]);