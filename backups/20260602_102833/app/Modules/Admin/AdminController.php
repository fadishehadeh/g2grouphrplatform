<?php

declare(strict_types=1);

namespace App\Modules\Admin;

use App\Core\Application;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Notifications\NotificationRepository;
use App\Support\PasswordPolicy;
use Throwable;

final class AdminController extends Controller
{
    private AdminRepository $repository;

    public function __construct(Application $app)
    {
        parent::__construct($app);
        $this->repository = new AdminRepository($this->app->database());
    }

    public function users(Request $request): void
    {
        $search = trim((string) $request->input('q', ''));
        $roleId = trim((string) $request->input('role_id', 'all'));
        $status = trim((string) $request->input('status', 'all'));

        try {
            $users = $this->repository->listUsers($search, $roleId, $status);
            $roles = $this->repository->roleOptions();
        } catch (Throwable $throwable) {
            $users = [];
            $roles = [];
            $this->app->session()->flash('error', 'Unable to load user accounts: ' . $throwable->getMessage());
        }

        $this->render('admin.users.index', [
            'title' => 'User Access',
            'pageTitle' => 'User Access',
            'activeSection' => 'users',
            'users' => $users,
            'roles' => $roles,
            'search' => $search,
            'roleId' => $roleId,
            'status' => $status,
        ]);
    }

    public function createUser(Request $request): void
    {
        try {
            $roles = $this->repository->roleOptions();
            $employees = $this->repository->availableEmployees();
        } catch (Throwable $throwable) {
            $roles = [];
            $employees = [];
            $this->app->session()->flash('error', 'Unable to load user setup lists: ' . $throwable->getMessage());
        }

        $this->render('admin.users.form', [
            'title' => 'Create User',
            'pageTitle' => 'Create User',
            'activeSection' => 'users',
            'userRecord' => ['status' => 'active', 'must_change_password' => 1],
            'roles' => $roles,
            'employees' => $employees,
            'formAction' => '/admin/users/create',
            'submitLabel' => 'Create User',
            'isEdit' => false,
        ]);
    }

    public function storeUser(Request $request): void
    {
        $this->validateCsrf($request, '/admin/users/create');
        $data = $this->userPayload($request);

        if (!$this->validateUserPayload($data, '/admin/users/create', true)) {
            return;
        }

        try {
            $userId = $this->repository->createUser($data, $this->app->auth()->id(), $request->ip(), $request->userAgent());
            $this->app->session()->flash('success', 'User account created successfully.');
            $this->redirect('/admin/users/' . $userId . '/edit');
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to create user: ' . $throwable->getMessage());
            $this->app->session()->flash('old_input', $this->userOldInput($data));
            $this->redirect('/admin/users/create');
        }
    }

    public function editUser(Request $request, string $id): void
    {
        $userId = (int) $id;

        try {
            $userRecord = $this->repository->findUser($userId);
            if ($userRecord === null) {
                Response::abort(404, 'User not found.');
            }

            $roles = $this->repository->roleOptions();
            $employees = $this->repository->availableEmployees($userId);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load the selected user: ' . $throwable->getMessage());
            $this->redirect('/admin/users');
        }

        $this->render('admin.users.form', [
            'title' => 'Edit User',
            'pageTitle' => 'Edit User',
            'activeSection' => 'users',
            'userRecord' => $userRecord,
            'roles' => $roles,
            'employees' => $employees,
            'formAction' => '/admin/users/' . $userId . '/edit',
            'submitLabel' => 'Update User',
            'isEdit' => true,
        ]);
    }

    public function updateUser(Request $request, string $id): void
    {
        $userId = (int) $id;
        $this->validateCsrf($request, '/admin/users/' . $userId . '/edit');
        $data = $this->userPayload($request);

        if (!$this->validateUserPayload($data, '/admin/users/' . $userId . '/edit', false)) {
            return;
        }

        try {
            $existing = $this->repository->findUser($userId);

            if ($existing === null) {
                Response::abort(404, 'User not found.');
            }

            if ($this->app->auth()->id() === $userId) {
                if ((int) $existing['role_id'] !== (int) $data['role_id'] || (string) $existing['status'] !== (string) $data['status']) {
                    $this->app->session()->flash('error', 'You cannot change your own role or account status from this screen.');
                    $this->app->session()->flash('old_input', $this->userOldInput($data));
                    $this->redirect('/admin/users/' . $userId . '/edit');
                }
            }

            $this->repository->updateUser($userId, $data, $this->app->auth()->id(), $request->ip(), $request->userAgent());
            $message = $this->app->auth()->id() === $userId
                ? 'Your account was updated. Sign out and back in if you need the sidebar/profile session to refresh.'
                : 'User account updated successfully.';
            $this->app->session()->flash('success', $message);
            $this->redirect('/admin/users/' . $userId . '/edit');
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to update user: ' . $throwable->getMessage());
            $this->app->session()->flash('old_input', $this->userOldInput($data));
            $this->redirect('/admin/users/' . $userId . '/edit');
        }
    }

    public function roles(Request $request): void
    {
        $search = trim((string) $request->input('q', ''));

        try {
            $roles = $this->repository->listRoles($search);
        } catch (Throwable $throwable) {
            $roles = [];
            $this->app->session()->flash('error', 'Unable to load roles: ' . $throwable->getMessage());
        }

        $this->render('admin.roles.index', [
            'title' => 'Roles & Permissions',
            'pageTitle' => 'Roles & Permissions',
            'activeSection' => 'roles',
            'roles' => $roles,
            'search' => $search,
        ]);
    }

    public function storeRole(Request $request): void
    {
        $this->validateCsrf($request, '/admin/roles');
        $data = $this->rolePayload($request);

        if (!$this->validateRolePayload($data, '/admin/roles')) {
            return;
        }

        try {
            $roleId = $this->repository->createRole($data, $this->app->auth()->id(), $request->ip(), $request->userAgent());
            $this->app->session()->flash('success', 'Role created successfully.');
            $this->redirect('/admin/roles/' . $roleId . '/permissions');
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to create role: ' . $throwable->getMessage());
            $this->app->session()->flash('old_input', $data);
            $this->redirect('/admin/roles');
        }
    }

    public function rolePermissions(Request $request, string $id): void
    {
        $roleId = (int) $id;

        try {
            $role = $this->repository->findRole($roleId);
            if ($role === null) {
                Response::abort(404, 'Role not found.');
            }

            $permissionGroups = $this->repository->permissionsGrouped();
            $selectedPermissionIds = $this->repository->rolePermissionIds($roleId);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load role permissions: ' . $throwable->getMessage());
            $this->redirect('/admin/roles');
        }

        $this->render('admin.roles.permissions', [
            'title' => 'Role Permissions',
            'pageTitle' => 'Role Permissions',
            'activeSection' => 'roles',
            'role' => $role,
            'permissionGroups' => $permissionGroups,
            'selectedPermissionIds' => $selectedPermissionIds,
        ]);
    }

    public function updateRolePermissions(Request $request, string $id): void
    {
        $roleId = (int) $id;
        $this->validateCsrf($request, '/admin/roles/' . $roleId . '/permissions');
        $permissionIds = $request->input('permission_ids', []);
        $permissionIds = is_array($permissionIds) ? $permissionIds : [];

        try {
            $role = $this->repository->findRole($roleId);
            if ($role === null) {
                Response::abort(404, 'Role not found.');
            }

            $this->repository->updateRolePermissions($roleId, $permissionIds, $this->app->auth()->id(), $request->ip(), $request->userAgent());
            $this->app->session()->flash('success', 'Role permissions updated successfully.');
            $this->redirect('/admin/roles/' . $roleId . '/permissions');
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to update role permissions: ' . $throwable->getMessage());
            $this->app->session()->flash('old_input', ['permission_ids' => $permissionIds]);
            $this->redirect('/admin/roles/' . $roleId . '/permissions');
        }
    }

    public function sendWelcomeEmail(Request $request, string $id): void
    {
        $userId = (int) $id;
        $this->validateCsrf($request, '/admin/users/' . $userId . '/edit');

        try {
            $userRecord = $this->repository->findUser($userId);

            if ($userRecord === null) {
                Response::abort(404, 'User not found.');
            }

            if (empty($userRecord['email'])) {
                $this->app->session()->flash('error', 'This user has no email address configured.');
                $this->redirect('/admin/users/' . $userId . '/edit');
            }

            // Generate a strong random password
            $plainPassword = PasswordPolicy::generateSecurePassword(16);
            $hash = password_hash($plainPassword, PASSWORD_DEFAULT);

            // Update the user's password and force change on next login
            $this->app->database()->execute(
                'UPDATE users SET password_hash = :hash, must_change_password = 1 WHERE id = :id',
                ['hash' => $hash, 'id' => $userId]
            );

            // Build the welcome email
            $appName = (string) config('app.brand.display_name', config('app.name', 'HR Management System'));
            $loginUrl = url('/login');
            $username = (string) $userRecord['username'];
            $firstName = (string) $userRecord['first_name'];

            $bodyHtml = '<div style="font-family:Arial,sans-serif;max-width:600px">'
                . '<h2>Welcome to ' . e($appName) . '</h2>'
                . '<p>Hello ' . e($firstName) . ',</p>'
                . '<p>Your account has been created. Please use the credentials below to log in:</p>'
                . '<table style="margin:16px 0;border-collapse:collapse">'
                . '<tr><td style="padding:4px 12px 4px 0;font-weight:bold">Login URL</td><td style="padding:4px 0"><a href="' . e($loginUrl) . '">' . e($loginUrl) . '</a></td></tr>'
                . '<tr><td style="padding:4px 12px 4px 0;font-weight:bold">Username</td><td style="padding:4px 0">' . e($username) . '</td></tr>'
                . '<tr><td style="padding:4px 12px 4px 0;font-weight:bold">Password</td><td style="padding:4px 0"><code style="background:#f4f4f4;padding:2px 6px;border-radius:3px">' . e($plainPassword) . '</code></td></tr>'
                . '</table>'
                . '<p><strong>You will be required to change your password on first login.</strong></p>'
                . '<p>Best regards,<br>' . e($appName) . '</p>'
                . '</div>';

            $bodyText = "Welcome to {$appName}\n\n"
                . "Hello {$firstName},\n\n"
                . "Your account has been created. Please log in with:\n"
                . "URL: {$loginUrl}\n"
                . "Username: {$username}\n"
                . "Password: {$plainPassword}\n\n"
                . "You will be required to change your password on first login.\n\n"
                . "Best regards,\n{$appName}";

            // Queue the email
            $notifications = new NotificationRepository($this->app->database());
            $notifications->queueEmail(
                (string) $userRecord['email'],
                "Welcome to {$appName} – Your Login Credentials",
                $bodyHtml,
                $bodyText,
                $userId,
                'welcome_email',
                $userId
            );

            $this->app->session()->flash('success', 'Welcome email queued for ' . e((string) $userRecord['email']) . '. Password has been reset.');
            $this->redirect('/admin/users/' . $userId . '/edit');
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to send welcome email: ' . $throwable->getMessage());
            $this->redirect('/admin/users/' . $userId . '/edit');
        }
    }

    private function validateCsrf(Request $request, string $redirect): void
    {
        if (!$this->app->csrf()->validate((string) $request->input('_token'))) {
            $this->app->session()->flash('error', 'Invalid form submission token.');
            $this->redirect($redirect);
        }
    }

    private function userPayload(Request $request): array
    {
        $email = strtolower(trim((string) $request->input('email', '')));

        return [
            'role_id' => trim((string) $request->input('role_id', '')),
            'employee_id' => trim((string) $request->input('employee_id', '')),
            'username' => $email,
            'email' => $email,
            'first_name' => trim((string) $request->input('first_name', '')),
            'last_name' => trim((string) $request->input('last_name', '')),
            'status' => trim((string) $request->input('status', 'active')),
            'must_change_password' => $request->input('must_change_password') ? 1 : 0,
            'password' => (string) $request->input('password', ''),
            'password_confirmation' => (string) $request->input('password_confirmation', ''),
        ];
    }

    private function validateUserPayload(array $data, string $redirect, bool $requirePassword): bool
    {
        $required = ['role_id', 'email', 'first_name', 'last_name'];

        foreach ($required as $field) {
            if ($data[$field] === '') {
                $this->app->session()->flash('error', 'Please complete all required user fields.');
                $this->app->session()->flash('old_input', $this->userOldInput($data));
                $this->redirect($redirect);
            }
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $this->app->session()->flash('error', 'Please provide a valid email address.');
            $this->app->session()->flash('old_input', $this->userOldInput($data));
            $this->redirect($redirect);
        }

        if (!ctype_digit($data['role_id'])) {
            $this->app->session()->flash('error', 'Please select a valid role.');
            $this->app->session()->flash('old_input', $this->userOldInput($data));
            $this->redirect($redirect);
        }

        if ($data['employee_id'] !== '' && !ctype_digit($data['employee_id'])) {
            $this->app->session()->flash('error', 'Please select a valid employee record.');
            $this->app->session()->flash('old_input', $this->userOldInput($data));
            $this->redirect($redirect);
        }

        if (!in_array($data['status'], ['active', 'inactive', 'suspended'], true)) {
            $this->app->session()->flash('error', 'Please select a valid account status.');
            $this->app->session()->flash('old_input', $this->userOldInput($data));
            $this->redirect($redirect);
        }

        if ($requirePassword && $data['password'] === '') {
            $this->app->session()->flash('error', 'A password is required when creating a user account.');
            $this->app->session()->flash('old_input', $this->userOldInput($data));
            $this->redirect($redirect);
        }

        if ($data['password'] !== '' && PasswordPolicy::errors($data['password']) !== []) {
            $this->app->session()->flash('error', PasswordPolicy::description());
            $this->app->session()->flash('old_input', $this->userOldInput($data));
            $this->redirect($redirect);
        }

        if ($data['password'] !== '' && $data['password'] !== $data['password_confirmation']) {
            $this->app->session()->flash('error', 'Password confirmation does not match.');
            $this->app->session()->flash('old_input', $this->userOldInput($data));
            $this->redirect($redirect);
        }

        return true;
    }

    private function userOldInput(array $data): array
    {
        unset($data['password'], $data['password_confirmation']);

        return $data;
    }

    private function rolePayload(Request $request): array
    {
        $codeInput = trim((string) $request->input('code', ''));
        $name = trim((string) $request->input('name', ''));
        $normalized = strtolower(trim((string) preg_replace('/[^a-z0-9]+/', '_', $codeInput !== '' ? $codeInput : $name), '_'));

        return [
            'name' => $name,
            'code' => $normalized,
            'description' => trim((string) $request->input('description', '')),
        ];
    }

    private function validateRolePayload(array $data, string $redirect): bool
    {
        if ($data['name'] === '' || $data['code'] === '') {
            $this->app->session()->flash('error', 'Role name and code are required.');
            $this->app->session()->flash('old_input', $data);
            $this->redirect($redirect);
        }

        return true;
    }
}