<?php

declare(strict_types=1);

namespace App\Modules\Profile;

use App\Core\Controller;
use App\Core\Request;
use App\Support\PasswordPolicy;
use Throwable;

final class ProfileController extends Controller
{
    public function show(Request $request): void
    {
        $user = auth()->user();

        $this->render('profile.index', [
            'title' => 'My Profile',
            'pageTitle' => 'My Profile',
            'user' => $user,
            'passwordPolicy' => PasswordPolicy::description(),
        ]);
    }

    public function changePassword(Request $request): void
    {
        if (!$this->app->csrf()->validate((string) $request->input('_token'))) {
            $this->app->session()->flash('error', 'Your session has expired. Please try again.');
            $this->redirect('/profile');
        }

        $currentPassword     = (string) $request->input('current_password', '');
        $newPassword         = (string) $request->input('new_password', '');
        $passwordConfirm     = (string) $request->input('new_password_confirmation', '');
        $userId              = (int) auth()->id();

        if ($currentPassword === '' || $newPassword === '' || $passwordConfirm === '') {
            $this->app->session()->flash('error', 'All password fields are required.');
            $this->redirect('/profile');
        }

        if ($newPassword !== $passwordConfirm) {
            $this->app->session()->flash('error', 'New password confirmation does not match.');
            $this->redirect('/profile');
        }

        if (!PasswordPolicy::passes($newPassword)) {
            $this->app->session()->flash('error', PasswordPolicy::description());
            $this->redirect('/profile');
        }

        try {
            $user = $this->app->database()->fetch(
                'SELECT id, password_hash FROM users WHERE id = :id LIMIT 1',
                ['id' => $userId]
            );

            if ($user === null || !password_verify($currentPassword, (string) $user['password_hash'])) {
                $this->app->session()->flash('error', 'Current password is incorrect.');
                $this->redirect('/profile');
            }

            $this->app->database()->execute(
                'UPDATE users
                 SET password_hash = :password_hash,
                     must_change_password = 0,
                     last_password_change_at = :changed_at
                 WHERE id = :id',
                [
                    'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                    'changed_at'    => date('Y-m-d H:i:s'),
                    'id'            => $userId,
                ]
            );
        } catch (Throwable $e) {
            $this->app->session()->flash('error', 'Failed to update password: ' . $e->getMessage());
            $this->redirect('/profile');
        }

        $this->app->session()->flash('success', 'Password updated successfully.');
        $this->redirect('/profile');
    }
}
