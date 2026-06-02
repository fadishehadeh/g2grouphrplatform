<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Application;
use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;

final class RoleMiddleware implements MiddlewareInterface
{
    private Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function handle(Request $request, callable $next, array $params = []): mixed
    {
        if (!$this->app->auth()->hasRole($params)) {
            Response::abort(403, 'You do not have permission to access this page.');
        }

        return $next($request);
    }
}