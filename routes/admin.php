<?php

declare(strict_types=1);

use App\Middleware\AccountStatusMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Modules\Admin\AdminController;

$router = $app->router();
$adminBaseMiddleware = [
    AuthMiddleware::class,
    AccountStatusMiddleware::class,
    [RoleMiddleware::class, ['super_admin', 'hr_admin']],
];

$router->get('/admin/users', [AdminController::class, 'users'], $adminBaseMiddleware);
$router->get('/admin/users/create', [AdminController::class, 'createUser'], $adminBaseMiddleware);
$router->post('/admin/users/create', [AdminController::class, 'storeUser'], $adminBaseMiddleware);
$router->get('/admin/users/{id}/edit', [AdminController::class, 'editUser'], $adminBaseMiddleware);
$router->post('/admin/users/{id}/edit', [AdminController::class, 'updateUser'], $adminBaseMiddleware);
$router->post('/admin/users/{id}/welcome-email', [AdminController::class, 'sendWelcomeEmail'], $adminBaseMiddleware);

$router->get('/admin/roles', [AdminController::class, 'roles'], $adminBaseMiddleware);
$router->post('/admin/roles', [AdminController::class, 'storeRole'], $adminBaseMiddleware);
$router->get('/admin/roles/{id}/permissions', [AdminController::class, 'rolePermissions'], $adminBaseMiddleware);
$router->post('/admin/roles/{id}/permissions', [AdminController::class, 'updateRolePermissions'], $adminBaseMiddleware);