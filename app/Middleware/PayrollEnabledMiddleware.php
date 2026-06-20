<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Application;
use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;

final class PayrollEnabledMiddleware implements MiddlewareInterface
{
    private Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function handle(Request $request, callable $next, array $params = []): mixed
    {
        if (!is_payroll_enabled()) {
            Response::abort(403, 'The Payroll module is not enabled.');
        }

        return $next($request);
    }
}
