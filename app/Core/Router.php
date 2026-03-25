<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    private Application $app;
    private array $routes = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function get(string $path, mixed $handler, array $middlewares = []): void
    {
        $this->add(['GET'], $path, $handler, $middlewares);
    }

    public function post(string $path, mixed $handler, array $middlewares = []): void
    {
        $this->add(['POST'], $path, $handler, $middlewares);
    }

    public function add(array $methods, string $path, mixed $handler, array $middlewares = []): void
    {
        $this->routes[] = [
            'methods' => $methods,
            'path' => $this->normalizePath($path),
            'handler' => $handler,
            'middlewares' => $middlewares,
        ];
    }

    public function dispatch(Request $request): void
    {
        foreach ($this->routes as $route) {
            if (!in_array($request->method(), $route['methods'], true)) {
                continue;
            }

            $params = $this->match($route['path'], $request->path());

            if ($params === null) {
                continue;
            }

            $handler = fn (Request $req) => $this->invokeHandler($route['handler'], $req, $params);
            $pipeline = array_reduce(
                array_reverse($route['middlewares']),
                fn (callable $next, mixed $middleware) => fn (Request $req) => $this->runMiddleware($middleware, $req, $next),
                $handler
            );

            $pipeline($request);

            return;
        }

        Response::abort(404, 'Page not found.');
    }

    private function invokeHandler(mixed $handler, Request $request, array $params): mixed
    {
        if (is_array($handler) && is_string($handler[0])) {
            $controller = new $handler[0]($this->app);

            return $controller->{$handler[1]}($request, ...array_values($params));
        }

        if (is_callable($handler)) {
            return $handler($request, ...array_values($params));
        }

        throw new \RuntimeException('Invalid route handler provided.');
    }

    private function runMiddleware(mixed $definition, Request $request, callable $next): mixed
    {
        $class = $definition;
        $params = [];

        if (is_array($definition)) {
            $class = $definition[0] ?? null;
            $params = $definition[1] ?? [];
            $params = is_array($params) ? $params : [$params];
        }

        if (!is_string($class) || !class_exists($class)) {
            throw new \RuntimeException('Invalid middleware definition.');
        }

        $middleware = new $class($this->app);

        if (!$middleware instanceof MiddlewareInterface) {
            throw new \RuntimeException(sprintf('%s must implement MiddlewareInterface.', $class));
        }

        return $middleware->handle($request, $next, $params);
    }

    private function match(string $routePath, string $requestPath): ?array
    {
        $pattern = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '(?P<$1>[^/]+)', $routePath);
        $pattern = '#^' . $pattern . '$#';

        if (!preg_match($pattern, $requestPath, $matches)) {
            return null;
        }

        return array_filter($matches, static fn ($key) => is_string($key), ARRAY_FILTER_USE_KEY);
    }

    private function normalizePath(string $path): string
    {
        if ($path === '/') {
            return '/';
        }

        return '/' . trim($path, '/');
    }
}