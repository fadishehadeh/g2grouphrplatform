<?php

declare(strict_types=1);

use App\Middleware\AuthMiddleware;
use App\Middleware\GuestMiddleware;
use App\Modules\Auth\AuthController;

$router = $app->router();

$router->get('/login',                [AuthController::class, 'showLogin'],          [GuestMiddleware::class]);
$router->post('/login',               [AuthController::class, 'login'],              [GuestMiddleware::class]);
$router->get('/otp',                  [AuthController::class, 'showOtp']);
$router->post('/otp',                 [AuthController::class, 'verifyOtp']);
$router->post('/otp/resend',          [AuthController::class, 'resendOtp']);
$router->get('/forgot-password',      [AuthController::class, 'showForgotPassword'], [GuestMiddleware::class]);
$router->post('/forgot-password',     [AuthController::class, 'sendResetLink'],      [GuestMiddleware::class]);
$router->get('/reset-password/{token}', [AuthController::class, 'showResetPassword'], [GuestMiddleware::class]);
$router->post('/reset-password',      [AuthController::class, 'resetPassword'],      [GuestMiddleware::class]);
$router->post('/logout',              [AuthController::class, 'logout'],             [AuthMiddleware::class]);
