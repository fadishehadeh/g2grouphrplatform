<?php

declare(strict_types=1);

use App\Core\Response;
use App\Middleware\AccountStatusMiddleware;
use App\Middleware\AuthMiddleware;
use App\Modules\Dashboard\DashboardController;

$router = $app->router();

$router->get('/', static function (): void {
    Response::redirect(auth()->check() ? '/dashboard' : '/login');
});

$router->get('/dashboard', [DashboardController::class, 'index'], [
    AuthMiddleware::class,
    AccountStatusMiddleware::class,
]);