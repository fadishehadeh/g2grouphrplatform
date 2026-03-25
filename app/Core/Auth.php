<?php

declare(strict_types=1);

namespace App\Core;

final class Auth
{
    private const SESSION_KEY = 'auth_user';

    private Database $database;
    private Session $session;

    public function __construct(
        Database $database,
        Session $session,
    ) {
        $this->database = $database;
        $this->session = $session;
    }

    public function attempt(string $login, string $password): bool
    {
        $user = $this->database->fetch(
            'SELECT u.id, u.role_id, u.username, u.email, u.password_hash, u.first_name, u.last_name, u.status,
                    e.id AS employee_id, e.employee_code,
                    r.code AS role_code, r.name AS role_name
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             LEFT JOIN employees e ON e.user_id = u.id
             WHERE u.username = :username_login OR u.email = :email_login
             LIMIT 1',
            [
                'username_login' => $login,
                'email_login' => $login,
            ]
        );

        if ($user === null || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        if (($user['status'] ?? 'inactive') !== 'active') {
            return false;
        }

        $permissions = $this->database->fetchAll(
            'SELECT p.code
             FROM role_permissions rp
             INNER JOIN permissions p ON p.id = rp.permission_id
             WHERE rp.role_id = :role_id',
            ['role_id' => $user['role_id']]
        );

        $authUser = [
            'id' => (int) $user['id'],
            'role_id' => (int) $user['role_id'],
            'employee_id' => isset($user['employee_id']) ? (int) $user['employee_id'] : null,
            'employee_code' => $user['employee_code'] ?? null,
            'username' => $user['username'],
            'email' => $user['email'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'status' => $user['status'],
            'role_code' => $user['role_code'],
            'role_name' => $user['role_name'],
            'permissions' => array_column($permissions, 'code'),
        ];

        $this->session->regenerate();
        $this->session->put(self::SESSION_KEY, $authUser);

        return true;
    }

    public function user(): ?array
    {
        $user = $this->session->get(self::SESSION_KEY);

        return is_array($user) ? $user : null;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function id(): ?int
    {
        return $this->user()['id'] ?? null;
    }

    public function logout(): void
    {
        $this->session->remove(self::SESSION_KEY);
        $this->session->regenerate();
    }

    public function hasRole(string|array $roles): bool
    {
        $roles = is_array($roles) ? $roles : [$roles];
        $currentRole = $this->user()['role_code'] ?? null;

        return $currentRole !== null && in_array($currentRole, $roles, true);
    }

    public function hasPermission(string $permission): bool
    {
        $permissions = $this->user()['permissions'] ?? [];

        return in_array($permission, $permissions, true);
    }
}