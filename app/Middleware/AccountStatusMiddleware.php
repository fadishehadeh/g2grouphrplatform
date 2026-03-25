<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Application;
use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;

final class AccountStatusMiddleware implements MiddlewareInterface
{
    private Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function handle(Request $request, callable $next, array $params = []): mixed
    {
        $user = $this->app->auth()->user();

        if (($user['status'] ?? null) !== 'active') {
            $this->app->auth()->logout();
            $this->app->session()->flash('error', 'Your account is not active. Please contact HR.');
            Response::redirect('/login');
        }

        return $next($request);
    }
}