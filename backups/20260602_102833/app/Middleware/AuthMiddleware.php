<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Application;
use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;

final class AuthMiddleware implements MiddlewareInterface
{
    private Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function handle(Request $request, callable $next, array $params = []): mixed
    {
        if ($this->app->session()->timedOut()) {
            $this->app->session()->flash('error', 'Your session expired due to inactivity. Please sign in again.');
            Response::redirect('/login');
        }

        if (!$this->app->auth()->check()) {
            $this->app->session()->flash('error', 'Please log in to continue.');
            Response::redirect('/login');
        }

        return $next($request);
    }
}