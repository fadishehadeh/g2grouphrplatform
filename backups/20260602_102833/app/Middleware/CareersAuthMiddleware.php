<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Application;
use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;
use App\Support\CareersAuth;

final class CareersAuthMiddleware implements MiddlewareInterface
{
    public function __construct(private Application $app) {}

    public function handle(Request $request, callable $next, array $params = []): mixed
    {
        $auth = new CareersAuth($this->app->session());

        if (!$auth->check()) {
            $this->app->session()->flash('error', 'Please log in to continue.');
            Response::redirect('/careers/login');
        }

        return $next($request);
    }
}
