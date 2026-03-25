<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Application;
use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;

final class PermissionMiddleware implements MiddlewareInterface
{
    private Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function handle(Request $request, callable $next, array $params = []): mixed
    {
        foreach ($params as $permission) {
            if ($this->app->auth()->hasPermission((string) $permission)) {
                return $next($request);
            }
        }

        Response::abort(403, 'You do not have the required permission for this action.');
    }
}