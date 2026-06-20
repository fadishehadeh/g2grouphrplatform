<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Application;
use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Support\Jwt;
use UnexpectedValueException;

/**
 * Bearer JWT authentication middleware for mobile/PWA API endpoints.
 *
 * Accepts: Authorization: Bearer <access_jwt>
 * The JWT must have type=access (not type=pending or type=refresh).
 * On success, hydrates auth()->user() for the request — no session touched.
 *
 * Coexists with ApiAuthMiddleware (personal tokens) on separate route groups.
 */
final class JwtAuthMiddleware implements MiddlewareInterface
{
    private Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function handle(Request $request, callable $next, array $params = []): mixed
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (!str_starts_with($header, 'Bearer ')) {
            return $this->unauthorized('Missing or malformed Authorization header.');
        }

        $rawToken = substr($header, 7);
        if ($rawToken === '') {
            return $this->unauthorized('Empty bearer token.');
        }

        $secret = (string) config('app.jwt.secret', '');
        if ($secret === '') {
            return $this->unauthorized('JWT authentication is not configured.');
        }

        try {
            $payload = Jwt::decode($rawToken, $secret);
        } catch (UnexpectedValueException $e) {
            return $this->unauthorized($e->getMessage());
        }

        if (($payload['type'] ?? '') !== 'access') {
            return $this->unauthorized('Invalid token type.');
        }

        $userId = (int) ($payload['sub'] ?? 0);
        if ($userId <= 0) {
            return $this->unauthorized('Invalid token subject.');
        }

        $this->app->auth()->setApiUser([
            'id'          => $userId,
            'username'    => (string) ($payload['username'] ?? ''),
            'email'       => (string) ($payload['email'] ?? ''),
            'first_name'  => (string) ($payload['first_name'] ?? ''),
            'last_name'   => (string) ($payload['last_name'] ?? ''),
            'role_code'   => (string) ($payload['role_code'] ?? ''),
            'role_name'   => (string) ($payload['role_name'] ?? ''),
            'employee_id' => isset($payload['employee_id']) ? (int) $payload['employee_id'] : null,
            'company_id'  => isset($payload['company_id'])  ? (int) $payload['company_id']  : null,
            'permissions' => (array) ($payload['permissions'] ?? []),
        ]);

        return $next($request);
    }

    private function unauthorized(string $message): void
    {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        header('WWW-Authenticate: Bearer realm="HR API"');
        echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
