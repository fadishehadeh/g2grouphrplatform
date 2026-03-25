<?php

declare(strict_types=1);

namespace App\Modules\Auth;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Support\PasswordPolicy;
use Throwable;

final class AuthController extends Controller
{
    public function showLogin(Request $request): void
    {
        $this->render('auth.login', [
            'title' => 'Login',
            'pageTitle' => 'Sign in to your account',
        ], 'auth');
    }

    public function login(Request $request): void
    {
        if (!$this->app->csrf()->validate((string) $request->input('_token'))) {
            $this->app->session()->flash('error', 'Your session has expired. Please try again.');
            $this->redirect('/login');
        }

        $login = trim((string) $request->input('login'));
        $password = (string) $request->input('password');

        if ($login === '' || $password === '') {
            $this->app->session()->flash('error', 'Username/email and password are required.');
            $this->app->session()->flash('old_input', ['login' => $login]);
            $this->redirect('/login');
        }

        try {
            if (!$this->app->auth()->attempt($login, $password)) {
                $this->app->session()->flash('error', 'Invalid credentials or inactive account.');
                $this->app->session()->flash('old_input', ['login' => $login]);
                $this->redirect('/login');
            }
        } catch (Throwable $exception) {
            $this->app->session()->flash('error', 'Unable to complete login: ' . $exception->getMessage());
            $this->app->session()->flash('old_input', ['login' => $login]);
            $this->redirect('/login');
        }

        $this->app->session()->flash('success', 'Welcome back.');
        $this->redirect('/dashboard');
    }

    public function showForgotPassword(Request $request): void
    {
        $this->render('auth.forgot-password', [
            'title' => 'Forgot Password',
            'pageTitle' => 'Reset your password',
        ], 'auth');
    }

    public function sendResetLink(Request $request): void
    {
        $this->validateGuestCsrf($request, '/forgot-password');

        $email = strtolower(trim((string) $request->input('email')));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->app->session()->flash('error', 'Please enter a valid email address.');
            $this->app->session()->flash('old_input', ['email' => $email]);
            $this->redirect('/forgot-password');
        }

        $token = bin2hex(random_bytes(32));
        $resetLink = url('/reset-password/' . $token);
        $mailEnabled = $this->mailEnabled();
        $timestamp = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . $this->passwordResetExpiryMinutes() . ' minutes'));

        try {
            $user = $this->app->database()->fetch(
                'SELECT id, email FROM users WHERE email = :email AND status = :status LIMIT 1',
                [
                    'email' => $email,
                    'status' => 'active',
                ]
            );

            if ($user !== null) {
                $this->app->database()->transaction(function (Database $database) use ($user, $token, $timestamp, $expiresAt, $mailEnabled, $resetLink): void {
                    $database->execute(
                        'UPDATE password_resets SET used_at = :used_at WHERE user_id = :user_id AND used_at IS NULL',
                        [
                            'used_at' => $timestamp,
                            'user_id' => $user['id'],
                        ]
                    );

                    $database->execute(
                        'INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (:user_id, :token_hash, :expires_at)',
                        [
                            'user_id' => $user['id'],
                            'token_hash' => password_hash($token, PASSWORD_DEFAULT),
                            'expires_at' => $expiresAt,
                        ]
                    );

                    if ($mailEnabled) {
                        $this->queuePasswordResetEmail($database, (int) $user['id'], (string) $user['email'], $resetLink, $expiresAt, $timestamp);
                    }
                });

                if (!$mailEnabled) {
                    $this->app->session()->flash('reset_preview_link', $resetLink);
                }
            }
        } catch (Throwable $exception) {
            $this->app->session()->flash('error', 'Unable to process password reset: ' . $exception->getMessage());
            $this->redirect('/forgot-password');
        }

        $this->app->session()->flash(
            'success',
            $mailEnabled
                ? 'If the email exists, a password reset link has been queued for delivery.'
                : 'If the email exists, a password reset link is ready below for local testing.'
        );
        $this->redirect('/forgot-password');
    }

    public function showResetPassword(Request $request, string $token): void
    {
        try {
            $reset = $this->findActiveReset($token);
        } catch (Throwable $exception) {
            $this->app->session()->flash('error', 'Unable to validate reset link: ' . $exception->getMessage());
            $this->redirect('/forgot-password');
        }

        if ($reset === null) {
            $this->app->session()->flash('error', 'That password reset link is invalid or has expired.');
            $this->redirect('/forgot-password');
        }

        $this->render('auth.reset-password', [
            'title' => 'Reset Password',
            'pageTitle' => 'Choose a new password',
            'resetToken' => $token,
            'passwordPolicy' => PasswordPolicy::description(),
        ], 'auth');
    }

    public function resetPassword(Request $request): void
    {
        $token = trim((string) $request->input('token'));
        $redirectPath = $token !== '' ? $this->resetTokenPath($token) : '/forgot-password';

        $this->validateGuestCsrf($request, $redirectPath);

        $password = (string) $request->input('password', '');
        $passwordConfirmation = (string) $request->input('password_confirmation', '');

        if ($token === '' || !ctype_xdigit($token)) {
            $this->app->session()->flash('error', 'That password reset link is invalid or has expired.');
            $this->redirect('/forgot-password');
        }

        if ($password === '') {
            $this->app->session()->flash('error', 'Please enter a new password.');
            $this->redirect($redirectPath);
        }

        if ($password !== $passwordConfirmation) {
            $this->app->session()->flash('error', 'Password confirmation does not match.');
            $this->redirect($redirectPath);
        }

        if (!PasswordPolicy::passes($password)) {
            $this->app->session()->flash('error', PasswordPolicy::description());
            $this->redirect($redirectPath);
        }

        try {
            $reset = $this->findActiveReset($token);
            if ($reset === null) {
                $this->app->session()->flash('error', 'That password reset link is invalid or has expired.');
                $this->redirect('/forgot-password');
            }

            $timestamp = date('Y-m-d H:i:s');

            $this->app->database()->transaction(function (Database $database) use ($reset, $password, $timestamp): void {
                $database->execute(
                    'UPDATE users
                     SET password_hash = :password_hash,
                         must_change_password = 0,
                         last_password_change_at = :last_password_change_at
                     WHERE id = :user_id',
                    [
                        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                        'last_password_change_at' => $timestamp,
                        'user_id' => $reset['user_id'],
                    ]
                );

                $database->execute(
                    'UPDATE password_resets SET used_at = :used_at WHERE user_id = :user_id AND used_at IS NULL',
                    [
                        'used_at' => $timestamp,
                        'user_id' => $reset['user_id'],
                    ]
                );
            });
        } catch (Throwable $exception) {
            $this->app->session()->flash('error', 'Unable to reset password: ' . $exception->getMessage());
            $this->redirect($redirectPath);
        }

        $this->app->session()->flash('success', 'Your password has been reset. Please sign in with the new password.');
        $this->redirect('/login');
    }

    public function logout(Request $request): void
    {
        $this->validateGuestCsrf($request, '/dashboard', 'Invalid logout request.');

        $this->app->auth()->logout();
        $this->app->session()->flash('success', 'You have been logged out.');
        $this->redirect('/login');
    }

    private function validateGuestCsrf(Request $request, string $redirectPath, string $message = 'Your session has expired. Please try again.'): void
    {
        if (!$this->app->csrf()->validate((string) $request->input('_token'))) {
            $this->app->session()->flash('error', $message);
            $this->redirect($redirectPath);
        }
    }

    private function findActiveReset(string $token): ?array
    {
        if ($token === '' || !ctype_xdigit($token)) {
            return null;
        }

        $candidates = $this->app->database()->fetchAll(
            'SELECT pr.id, pr.user_id, pr.token_hash, pr.expires_at, u.email
             FROM password_resets pr
             INNER JOIN users u ON u.id = pr.user_id
             WHERE pr.used_at IS NULL AND pr.expires_at >= :now AND u.status = :status
             ORDER BY pr.id DESC',
            [
                'now' => date('Y-m-d H:i:s'),
                'status' => 'active',
            ]
        );

        foreach ($candidates as $candidate) {
            if (password_verify($token, (string) $candidate['token_hash'])) {
                return $candidate;
            }
        }

        return null;
    }

    private function queuePasswordResetEmail(Database $database, int $userId, string $email, string $resetLink, string $expiresAt, string $scheduledAt): void
    {
        $bodyText = "A password reset was requested for your account.\n\n"
            . "Reset link: {$resetLink}\n"
            . "This link expires at {$expiresAt}. If you did not request this change, you can ignore this message.";

        $bodyHtml = '<p>A password reset was requested for your account.</p>'
            . '<p><a href="' . e($resetLink) . '">Reset your password</a></p>'
            . '<p>This link expires at ' . e($expiresAt) . '. If you did not request this change, you can ignore this message.</p>';

        $database->execute(
            'INSERT INTO email_queue (user_id, to_email, subject, body_html, body_text, related_type, scheduled_at)
             VALUES (:user_id, :to_email, :subject, :body_html, :body_text, :related_type, :scheduled_at)',
            [
                'user_id' => $userId,
                'to_email' => $email,
                'subject' => 'Reset your password',
                'body_html' => $bodyHtml,
                'body_text' => $bodyText,
                'related_type' => 'password_reset',
                'scheduled_at' => $scheduledAt,
            ]
        );
    }

    private function mailEnabled(): bool
    {
        return (bool) config('app.mail.enabled', false);
    }

    private function passwordResetExpiryMinutes(): int
    {
        return max(15, (int) config('app.security.password_reset_expiry_minutes', 60));
    }

    private function resetTokenPath(string $token): string
    {
        return '/reset-password/' . rawurlencode($token);
    }
}