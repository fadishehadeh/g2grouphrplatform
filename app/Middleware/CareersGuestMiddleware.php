<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Application;
use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;
use App\Support\CareersAuth;

final class CareersGuestMiddleware implements MiddlewareInterface
{
    public function __construct(private Application $app) {}

    public function handle(Request $request, callable $next, array $params = []): mixed
    {
        $auth = new CareersAuth($this->app->session());

        if ($auth->check()) {
            Response::redirect('/careers/dashboard');
        }

        return $next($request);
    }
}
