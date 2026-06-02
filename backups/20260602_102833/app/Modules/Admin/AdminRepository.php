<?php

declare(strict_types=1);

namespace App\Modules\Admin;

use App\Core\Database;
use RuntimeException;

final class AdminRepository
{
    private Database $database;

    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    public function listUsers(string $search = '', string $roleId = 'all', string $status = 'all'): array
    {
        $sql = "SELECT u.id, u.role_id, u.username, u.email, u.first_name, u.last_name, u.status, u.must_change_password,
                       r.name AS role_name, r.code AS role_code,
                       e.id AS employee_id, e.employee_code,
                       CONCAT_WS(' ', e.first_name, e.middle_name, e.last_name) AS employee_name
                FROM users u
                INNER JOIN roles r ON r.id = u.role_id
                LEFT JOIN employees e ON e.user_id = u.id
                WHERE 1 = 1";
        $params = [];

        if ($roleId !== 'all' && ctype_digit($roleId)) {
            $sql .= ' AND u.role_id = :role_filter';
            $params['role_filter'] = (int) $roleId;
        }

        if (in_array($status, ['active', 'inactive', 'suspended'], true)) {
            $sql .= ' AND u.status = :status_filter';
            $params['status_filter'] = $status;
        }

        if ($search !== '') {
            $sql .= " AND CONCAT_WS(' ', u.username, u.email, u.first_name, u.last_name, r.name,
                                     COALESCE(e.employee_code, ''), COALESCE(e.first_name, ''), COALESCE(e.last_name, '')) LIKE :search_text";
            $params['search_text'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY u.created_at DESC';

        return $this->database->fetchAll($sql, $params);
    }

    public function findUser(int $id): ?array
    {
        return $this->fetchUserRecord($this->database, $id);
    }

    public function roleOptions(): array
    {
        return $this->database->fetchAll('SELECT id, name, code FROM roles ORDER BY is_system DESC, name ASC');
    }

    public function availableEmployees(?int $currentUserId = null): array
    {
        $sql = "SELECT id, employee_code, first_name, middle_name, last_name, work_email,
                       CONCAT(employee_code, ' - ', CONCAT_WS(' ', first_name, middle_name, last_name)) AS name
                FROM employees
                WHERE archived_at IS NULL AND (user_id IS NULL";
        $params = [];

        if ($currentUserId !== null) {
            $sql .= ' OR user_id = :current_user_id';
            $params['current_user_id'] = $currentUserId;
        }

        $sql .= ') ORDER BY first_name ASC, last_name ASC';

        return $this->database->fetchAll($sql, $params);
    }

    public function listRoles(string $search = ''): array
    {
        $sql = 'SELECT r.id, r.name, r.code, r.description, r.is_system,
                       COUNT(DISTINCT u.id) AS user_count,
                       COUNT(DISTINCT rp.permission_id) AS permission_count
                FROM roles r
                LEFT JOIN users u ON u.role_id = r.id
                LEFT JOIN role_permissions rp ON rp.role_id = r.id
                WHERE 1 = 1';
        $params = [];

        if ($search !== '') {
            $sql .= " AND CONCAT_WS(' ', r.name, r.code, COALESCE(r.description, '')) LIKE :role_search";
            $params['role_search'] = '%' . $search . '%';
        }

        $sql .= ' GROUP BY r.id, r.name, r.code, r.description, r.is_system ORDER BY r.is_system DESC, r.name ASC';

        return $this->database->fetchAll($sql, $params);
    }

    public function findRole(int $id): ?array
    {
        return $this->database->fetch('SELECT id, name, code, description, is_system FROM roles WHERE id = :id LIMIT 1', ['id' => $id]);
    }

    public function permissionsGrouped(): array
    {
        $rows = $this->database->fetchAll(
            'SELECT id, module_name, action_name, code, description FROM permissions ORDER BY module_name ASC, action_name ASC'
        );
        $grouped = [];

        foreach ($rows as $row) {
            $grouped[$row['module_name']][] = $row;
        }

        return $grouped;
    }

    public function rolePermissionIds(int $roleId): array
    {
        $rows = $this->database->fetchAll(
            'SELECT permission_id FROM role_permissions WHERE role_id = :role_id ORDER BY permission_id ASC',
            ['role_id' => $roleId]
        );

        return array_map(static fn (array $row): int => (int) $row['permission_id'], $rows);
    }

    public function createUser(array $data, ?int $actorId, string $ipAddress, string $userAgent): int
    {
        return $this->database->transaction(function (Database $database) use ($data, $actorId, $ipAddress, $userAgent): int {
            $roleId = (int) $data['role_id'];
            $employeeId = $data['employee_id'] !== '' ? (int) $data['employee_id'] : null;

            if (!$this->roleExists($database, $roleId)) {
                throw new RuntimeException('Invalid role selected.');
            }

            if ($employeeId !== null && !$this->employeeCanBeLinked($database, $employeeId, null)) {
                throw new RuntimeException('Employee is already linked to another user.');
            }

            $database->execute(
                'INSERT INTO users (
                    role_id, username, email, password_hash, first_name, last_name, status, must_change_password, last_password_change_at
                 ) VALUES (
                    :role_id, :username, :email, :password_hash, :first_name, :last_name, :status, :must_change_password, :last_password_change_at
                 )',
                [
                    'role_id' => $roleId,
                    'username' => $data['username'],
                    'email' => $data['email'],
                    'password_hash' => password_hash((string) $data['password'], PASSWORD_DEFAULT),
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'status' => $data['status'],
                    'must_change_password' => (int) $data['must_change_password'],
                    'last_password_change_at' => date('Y-m-d H:i:s'),
                ]
            );

            $userId = (int) $database->lastInsertId();

            if ($employeeId !== null) {
                $database->execute(
                    'UPDATE employees SET user_id = :user_id, updated_by = :updated_by WHERE id = :employee_id',
                    ['user_id' => $userId, 'updated_by' => $actorId, 'employee_id' => $employeeId]
                );
            }

            $current = $this->fetchUserRecord($database, $userId);

            $this->insertAudit($database, $actorId, 'admin', 'user', $userId, 'created', null, $current, $ipAddress, $userAgent);

            return $userId;
        });
    }

    public function updateUser(int $id, array $data, ?int $actorId, string $ipAddress, string $userAgent): void
    {
        $this->database->transaction(function (Database $database) use ($id, $data, $actorId, $ipAddress, $userAgent): void {
            $existing = $this->fetchUserRecord($database, $id);

            if ($existing === null) {
                throw new RuntimeException('User not found.');
            }

            $roleId = (int) $data['role_id'];
            $newEmployeeId = $data['employee_id'] !== '' ? (int) $data['employee_id'] : null;
            $oldEmployeeId = !empty($existing['employee_id']) ? (int) $existing['employee_id'] : null;

            if (!$this->roleExists($database, $roleId)) {
                throw new RuntimeException('Invalid role selected.');
            }

            if ($newEmployeeId !== null && !$this->employeeCanBeLinked($database, $newEmployeeId, $id)) {
                throw new RuntimeException('Employee is already linked to another user.');
            }

            $sql = 'UPDATE users SET
                        role_id = :role_id,
                        username = :username,
                        email = :email,
                        first_name = :first_name,
                        last_name = :last_name,
                        status = :status,
                        must_change_password = :must_change_password';
            $params = [
                'role_id' => $roleId,
                'username' => $data['username'],
                'email' => $data['email'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'status' => $data['status'],
                'must_change_password' => (int) $data['must_change_password'],
                'id' => $id,
            ];

            if ($data['password'] !== '') {
                $sql .= ', password_hash = :password_hash, last_password_change_at = :last_password_change_at';
                $params['password_hash'] = password_hash((string) $data['password'], PASSWORD_DEFAULT);
                $params['last_password_change_at'] = date('Y-m-d H:i:s');
            }

            $sql .= ' WHERE id = :id';
            $database->execute($sql, $params);

            if ($oldEmployeeId !== null && $oldEmployeeId !== $newEmployeeId) {
                $database->execute(
                    'UPDATE employees SET user_id = NULL, updated_by = :updated_by WHERE id = :employee_id',
                    ['updated_by' => $actorId, 'employee_id' => $oldEmployeeId]
                );
            }

            if ($newEmployeeId !== null && $newEmployeeId !== $oldEmployeeId) {
                $database->execute(
                    'UPDATE employees SET user_id = :user_id, updated_by = :updated_by WHERE id = :employee_id',
                    ['user_id' => $id, 'updated_by' => $actorId, 'employee_id' => $newEmployeeId]
                );
            }

            $current = $this->fetchUserRecord($database, $id);
            $this->insertAudit($database, $actorId, 'admin', 'user', $id, 'updated', $existing, $current, $ipAddress, $userAgent);
        });
    }

    public function createRole(array $data, ?int $actorId, string $ipAddress, string $userAgent): int
    {
        return $this->database->transaction(function (Database $database) use ($data, $actorId, $ipAddress, $userAgent): int {
            $database->execute(
                'INSERT INTO roles (name, code, description, is_system) VALUES (:name, :code, :description, 0)',
                ['name' => $data['name'], 'code' => $data['code'], 'description' => $data['description'] ?: null]
            );

            $roleId = (int) $database->lastInsertId();
            $role = $this->findRole($roleId);
            $this->insertAudit($database, $actorId, 'admin', 'role', $roleId, 'created', null, $role, $ipAddress, $userAgent);

            return $roleId;
        });
    }

    public function updateRolePermissions(int $roleId, array $permissionIds, ?int $actorId, string $ipAddress, string $userAgent): void
    {
        $this->database->transaction(function (Database $database) use ($roleId, $permissionIds, $actorId, $ipAddress, $userAgent): void {
            $role = $this->findRole($roleId);

            if ($role === null) {
                throw new RuntimeException('Role not found.');
            }

            $currentRows = $database->fetchAll(
                'SELECT p.id, p.code
                 FROM role_permissions rp
                 INNER JOIN permissions p ON p.id = rp.permission_id
                 WHERE rp.role_id = :role_id',
                ['role_id' => $roleId]
            );
            $available = $database->fetchAll('SELECT id, code FROM permissions');
            $validPermissions = [];

            foreach ($available as $permission) {
                $validPermissions[(int) $permission['id']] = $permission['code'];
            }

            $selectedIds = [];
            foreach ($permissionIds as $permissionId) {
                $id = (int) $permissionId;
                if ($id > 0 && isset($validPermissions[$id])) {
                    $selectedIds[$id] = $id;
                }
            }

            $database->execute('DELETE FROM role_permissions WHERE role_id = :role_id_delete', ['role_id_delete' => $roleId]);

            foreach (array_values($selectedIds) as $index => $permissionId) {
                $database->execute(
                    'INSERT INTO role_permissions (role_id, permission_id) VALUES (:role_id_insert, :permission_id_' . $index . ')',
                    ['role_id_insert' => $roleId, 'permission_id_' . $index => $permissionId]
                );
            }

            $oldCodes = array_map(static fn (array $row): string => (string) $row['code'], $currentRows);
            $newCodes = array_values(array_map(static fn (int $id): string => $validPermissions[$id], array_values($selectedIds)));

            $this->insertAudit(
                $database,
                $actorId,
                'admin',
                'role',
                $roleId,
                'permissions_updated',
                ['role' => $role['code'], 'permissions' => $oldCodes],
                ['role' => $role['code'], 'permissions' => $newCodes],
                $ipAddress,
                $userAgent
            );
        });
    }

    private function fetchUserRecord(Database $database, int $id): ?array
    {
        return $database->fetch(
            "SELECT u.id, u.role_id, u.username, u.email, u.first_name, u.last_name, u.status, u.must_change_password,
                    r.name AS role_name, r.code AS role_code,
                    e.id AS employee_id, e.employee_code,
                    CONCAT_WS(' ', e.first_name, e.middle_name, e.last_name) AS employee_name
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             LEFT JOIN employees e ON e.user_id = u.id
             WHERE u.id = :id
             LIMIT 1",
            ['id' => $id]
        );
    }

    private function roleExists(Database $database, int $roleId): bool
    {
        return (int) ($database->fetchValue('SELECT COUNT(*) FROM roles WHERE id = :role_id', ['role_id' => $roleId]) ?? 0) > 0;
    }

    private function employeeCanBeLinked(Database $database, int $employeeId, ?int $userId): bool
    {
        $sql = 'SELECT COUNT(*) FROM employees WHERE id = :employee_id AND archived_at IS NULL AND (user_id IS NULL';
        $params = ['employee_id' => $employeeId];

        if ($userId !== null) {
            $sql .= ' OR user_id = :linked_user_id';
            $params['linked_user_id'] = $userId;
        }

        $sql .= ')';

        return (int) ($database->fetchValue($sql, $params) ?? 0) === 1;
    }

    private function insertAudit(
        Database $database,
        ?int $actorId,
        string $module,
        string $entityType,
        ?int $entityId,
        string $action,
        ?array $oldValues,
        ?array $newValues,
        string $ipAddress,
        string $userAgent
    ): void {
        $database->execute(
            'INSERT INTO audit_logs (user_id, module_name, entity_type, entity_id, action_name, old_values, new_values, ip_address, user_agent)
             VALUES (:user_id, :module_name, :entity_type, :entity_id, :action_name, :old_values, :new_values, :ip_address, :user_agent)',
            [
                'user_id' => $actorId,
                'module_name' => $module,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'action_name' => $action,
                'old_values' => $oldValues !== null ? json_encode($oldValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                'new_values' => $newValues !== null ? json_encode($newValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                'ip_address' => $ipAddress,
                'user_agent' => substr($userAgent, 0, 255),
            ]
        );
    }
}
