<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Application;
use App\Core\MiddlewareInterface;
use App\Core\Request;

/**
 * Bearer token authentication for the REST API.
 *
 * Reads:  Authorization: Bearer <raw_token>
 * Hashes the token with SHA-256 and looks it up in api_tokens.
 * On success, loads the full user + permissions into auth() session,
 * exactly the same as web login does.
 */
final class ApiAuthMiddleware implements MiddlewareInterface
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

        $tokenHash = hash('sha256', $rawToken);

        try {
            $row = $this->app->database()->fetch(
                'SELECT t.id AS token_id, t.user_id, t.expires_at, t.is_active,
                        u.username, u.email, u.first_name, u.last_name, u.status AS user_status,
                        r.code AS role_code, r.name AS role_name
                 FROM api_tokens t
                 INNER JOIN users u ON u.id = t.user_id
                 INNER JOIN roles r ON r.id = u.role_id
                 WHERE t.token_hash = :token_hash LIMIT 1',
                ['token_hash' => $tokenHash]
            );
        } catch (\Throwable) {
            return $this->unauthorized('Token lookup failed.');
        }

        if ($row === null || (int) ($row['is_active'] ?? 0) !== 1) {
            return $this->unauthorized('Invalid or revoked token.');
        }

        if ((string) ($row['user_status'] ?? '') !== 'active') {
            return $this->unauthorized('User account is inactive.');
        }

        if ($row['expires_at'] !== null && strtotime((string) $row['expires_at']) < time()) {
            return $this->unauthorized('Token has expired.');
        }

        // Load permissions
        try {
            $permissions = $this->app->database()->fetchAll(
                'SELECT p.code FROM role_permissions rp INNER JOIN permissions p ON p.id = rp.permission_id
                 WHERE rp.role_id = (SELECT role_id FROM users WHERE id = :user_id LIMIT 1)',
                ['user_id' => (int) $row['user_id']]
            );
        } catch (\Throwable) {
            $permissions = [];
        }

        $permissionCodes = array_column($permissions, 'code');

        // Stamp the user into auth (session-free, in-memory for this request)
        $this->app->auth()->setApiUser([
            'id'          => (int) $row['user_id'],
            'username'    => (string) $row['username'],
            'email'       => (string) $row['email'],
            'first_name'  => (string) $row['first_name'],
            'last_name'   => (string) $row['last_name'],
            'role_code'   => (string) $row['role_code'],
            'role_name'   => (string) $row['role_name'],
            'permissions' => $permissionCodes,
        ]);

        // Update last_used_at (best-effort, non-blocking)
        try {
            $this->app->database()->execute(
                'UPDATE api_tokens SET last_used_at = NOW() WHERE id = :id',
                ['id' => (int) $row['token_id']]
            );
        } catch (\Throwable) {
        }

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
