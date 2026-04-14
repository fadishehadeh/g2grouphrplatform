<?php

declare(strict_types=1);

namespace App\Modules\Auth;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Support\Mailer;
use App\Support\PasswordPolicy;
use Throwable;

final class AuthController extends Controller
{
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOCKOUT_MINUTES    = 15;
    private const OTP_TTL_MINUTES    = 10;
    private const OTP_MAX_ATTEMPTS   = 5;
    private const OTP_MAX_SENDS      = 5;
    private const OTP_RESEND_COOLDOWN = 60; // seconds

    // ------------------------------------------------------------------ //
    //  Login
    // ------------------------------------------------------------------ //

    public function showLogin(Request $request): void
    {
        $this->render('auth.login', [
            'title'          => 'Login',
            'pageTitle'      => 'Sign in to your account',
            'recaptchaSiteKey' => $this->recaptchaSiteKey(),
        ], 'auth');
    }

    public function login(Request $request): void
    {
        if (!$this->app->csrf()->validate((string) $request->input('_token'))) {
            $this->app->session()->flash('error', 'Your session has expired. Please try again.');
            $this->redirect('/login');
        }

        // CAPTCHA verification
        if (!$this->verifyRecaptcha((string) $request->input('g-recaptcha-response', ''))) {
            $this->app->session()->flash('error', 'CAPTCHA verification failed. Please try again.');
            $this->redirect('/login');
        }

        $login    = trim((string) $request->input('login'));
        $password = (string) $request->input('password');

        if ($login === '' || $password === '') {
            $this->app->session()->flash('error', 'Email and password are required.');
            $this->app->session()->flash('old_input', ['login' => $login]);
            $this->redirect('/login');
        }

        // Find user
        $user = $this->app->auth()->findByLogin($login);

        if ($user === null) {
            // Generic error — don't reveal if account exists
            $this->app->session()->flash('error', 'Invalid credentials or inactive account.');
            $this->app->session()->flash('old_input', ['login' => $login]);
            $this->redirect('/login');
        }

        // Check active status
        if (($user['status'] ?? 'inactive') !== 'active') {
            $this->app->session()->flash('error', 'Invalid credentials or inactive account.');
            $this->redirect('/login');
        }

        // Check lockout
        if (!empty($user['locked_until'])) {
            $lockedUntil = new \DateTimeImmutable((string) $user['locked_until']);
            $now = new \DateTimeImmutable();
            if ($now < $lockedUntil) {
                $remaining = (int) ceil(($lockedUntil->getTimestamp() - $now->getTimestamp()) / 60);
                $this->app->session()->flash('error', "Account temporarily locked due to too many failed attempts. Try again in {$remaining} minute(s).");
                $this->redirect('/login');
            }
        }

        // Verify password
        if (!password_verify($password, (string) $user['password_hash'])) {
            $newAttempts = (int) $user['login_attempts'] + 1;
            $lockedUntil = null;

            if ($newAttempts >= self::MAX_LOGIN_ATTEMPTS) {
                $lockedUntil = date('Y-m-d H:i:s', strtotime('+' . self::LOCKOUT_MINUTES . ' minutes'));
            }

            $this->app->database()->execute(
                'UPDATE users SET login_attempts = :a, locked_until = :l WHERE id = :id',
                ['a' => $newAttempts, 'l' => $lockedUntil, 'id' => $user['id']]
            );

            if ($lockedUntil !== null) {
                $this->app->session()->flash('error', 'Too many failed attempts. Your account has been locked for ' . self::LOCKOUT_MINUTES . ' minutes.');
            } else {
                $remaining = self::MAX_LOGIN_ATTEMPTS - $newAttempts;
                $this->app->session()->flash('error', "Invalid credentials. {$remaining} attempt(s) remaining before lockout.");
            }
            $this->app->session()->flash('old_input', ['login' => $login]);
            $this->redirect('/login');
        }

        // Credentials correct — reset failed attempts and send OTP
        $this->app->database()->execute(
            'UPDATE users SET login_attempts = 0, locked_until = NULL WHERE id = :id',
            ['id' => $user['id']]
        );

        $this->sendOtp((int) $user['id'], (string) $user['email'], (string) ($user['first_name'] ?? 'User'));
    }

    // ------------------------------------------------------------------ //
    //  OTP
    // ------------------------------------------------------------------ //

    public function showOtp(Request $request): void
    {
        if ($this->app->auth()->pendingUserId() === null) {
            $this->redirect('/login');
        }

        $this->render('auth.otp', [
            'title'     => 'Verify OTP',
            'pageTitle' => 'Two-Factor Verification',
        ], 'auth');
    }

    public function verifyOtp(Request $request): void
    {
        if (!$this->app->csrf()->validate((string) $request->input('_token'))) {
            $this->app->session()->flash('error', 'Your session has expired. Please try again.');
            $this->redirect('/otp');
        }

        $userId = $this->app->auth()->pendingUserId();
        if ($userId === null) {
            $this->redirect('/login');
        }

        $user = $this->app->database()->fetch(
            'SELECT id, email, first_name, otp_code, otp_expires_at, otp_attempts FROM users WHERE id = :id LIMIT 1',
            ['id' => $userId]
        );

        if ($user === null) {
            $this->app->auth()->clearPendingUser();
            $this->redirect('/login');
        }

        // Max OTP attempts
        if ((int) $user['otp_attempts'] >= self::OTP_MAX_ATTEMPTS) {
            $this->app->auth()->clearPendingUser();
            $this->app->session()->flash('error', 'Too many incorrect attempts. Please log in again.');
            $this->redirect('/login');
        }

        // Check expiry
        if (empty($user['otp_code']) || empty($user['otp_expires_at']) ||
            new \DateTimeImmutable() > new \DateTimeImmutable((string) $user['otp_expires_at'])) {
            $this->app->auth()->clearPendingUser();
            $this->app->session()->flash('error', 'Your OTP has expired. Please log in again.');
            $this->redirect('/login');
        }

        $submitted = trim((string) $request->input('otp', ''));

        if (!hash_equals((string) $user['otp_code'], $submitted)) {
            $this->app->database()->execute(
                'UPDATE users SET otp_attempts = otp_attempts + 1 WHERE id = :id',
                ['id' => $userId]
            );
            $this->app->session()->flash('error', 'Incorrect code. Please try again.');
            $this->redirect('/otp');
        }

        // OTP correct — complete login
        $this->app->auth()->clearPendingUser();

        if (!$this->app->auth()->loginById($userId)) {
            $this->app->session()->flash('error', 'Login failed. Please try again.');
            $this->redirect('/login');
        }

        $this->app->session()->flash('success', 'Welcome back.');
        $this->redirect('/dashboard');
    }

    public function resendOtp(Request $request): void
    {
        if (!$this->app->csrf()->validate((string) $request->input('_token'))) {
            $this->redirect('/otp');
        }

        $userId = $this->app->auth()->pendingUserId();
        if ($userId === null) {
            $this->redirect('/login');
        }

        $user = $this->app->database()->fetch(
            'SELECT id, email, first_name, otp_sent_count, otp_sent_window_start, otp_expires_at FROM users WHERE id = :id LIMIT 1',
            ['id' => $userId]
        );

        if ($user === null) {
            $this->redirect('/login');
        }

        // Resend cooldown
        if (!empty($user['otp_expires_at'])) {
            $expiresAt = new \DateTimeImmutable((string) $user['otp_expires_at']);
            $issuedAt  = $expiresAt->modify('-' . self::OTP_TTL_MINUTES . ' minutes');
            if ((time() - $issuedAt->getTimestamp()) < self::OTP_RESEND_COOLDOWN) {
                $this->app->session()->flash('error', 'Please wait 60 seconds before requesting a new code.');
                $this->redirect('/otp');
            }
        }

        $this->sendOtp((int) $user['id'], (string) $user['email'], (string) ($user['first_name'] ?? 'User'), isResend: true);
    }

    // ------------------------------------------------------------------ //
    //  Forgot / Reset Password
    // ------------------------------------------------------------------ //

    public function showForgotPassword(Request $request): void
    {
        $this->render('auth.forgot-password', [
            'title'     => 'Forgot Password',
            'pageTitle' => 'Reset your password',
        ], 'auth');
    }

    public function sendResetLink(Request $request): void
    {
        $this->validateCsrf($request, '/forgot-password');

        $email = strtolower(trim((string) $request->input('email')));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->app->session()->flash('error', 'Please enter a valid email address.');
            $this->app->session()->flash('old_input', ['email' => $email]);
            $this->redirect('/forgot-password');
        }

        $token       = bin2hex(random_bytes(32));
        $resetLink   = url('/reset-password/' . $token);
        $mailEnabled = $this->mailEnabled();
        $timestamp   = date('Y-m-d H:i:s');
        $expiresAt   = date('Y-m-d H:i:s', strtotime('+' . $this->passwordResetExpiryMinutes() . ' minutes'));

        try {
            $user = $this->app->database()->fetch(
                'SELECT id, email FROM users WHERE email = :email AND status = :status LIMIT 1',
                ['email' => $email, 'status' => 'active']
            );

            if ($user !== null) {
                $this->app->database()->transaction(function (Database $database) use ($user, $token, $timestamp, $expiresAt): void {
                    $database->execute(
                        'UPDATE password_resets SET used_at = :used_at WHERE user_id = :user_id AND used_at IS NULL',
                        ['used_at' => $timestamp, 'user_id' => $user['id']]
                    );
                    $database->execute(
                        'INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (:user_id, :token_hash, :expires_at)',
                        ['user_id' => $user['id'], 'token_hash' => password_hash($token, PASSWORD_DEFAULT), 'expires_at' => $expiresAt]
                    );
                });

                if ($mailEnabled) {
                    $this->sendPasswordResetEmail((string) $user['email'], $resetLink, $expiresAt);
                }
            }
        } catch (Throwable $exception) {
            $this->app->session()->flash('error', 'Unable to process password reset: ' . $exception->getMessage());
            $this->redirect('/forgot-password');
        }

        $this->app->session()->flash('success', 'If the email exists, a password reset link has been sent.');
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
            'title'          => 'Set Password',
            'pageTitle'      => 'Set your password',
            'resetToken'     => $token,
            'passwordPolicy' => PasswordPolicy::description(),
        ], 'auth');
    }

    public function resetPassword(Request $request): void
    {
        $token        = trim((string) $request->input('token'));
        $redirectPath = $token !== '' ? $this->resetTokenPath($token) : '/forgot-password';

        $this->validateCsrf($request, $redirectPath);

        $password             = (string) $request->input('password', '');
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
                    'UPDATE users SET password_hash = :password_hash, must_change_password = 0,
                         last_password_change_at = :last_password_change_at WHERE id = :user_id',
                    ['password_hash' => password_hash($password, PASSWORD_DEFAULT), 'last_password_change_at' => $timestamp, 'user_id' => $reset['user_id']]
                );
                $database->execute(
                    'UPDATE password_resets SET used_at = :used_at WHERE user_id = :user_id AND used_at IS NULL',
                    ['used_at' => $timestamp, 'user_id' => $reset['user_id']]
                );
            });
        } catch (Throwable $exception) {
            $this->app->session()->flash('error', 'Unable to reset password: ' . $exception->getMessage());
            $this->redirect($redirectPath);
        }

        $this->app->session()->flash('success', 'Your password has been reset. Please sign in with the new password.');
        $this->redirect('/login');
    }

    // ------------------------------------------------------------------ //
    //  Logout
    // ------------------------------------------------------------------ //

    public function logout(Request $request): void
    {
        $this->validateCsrf($request, '/dashboard', 'Invalid logout request.');
        $this->app->auth()->logout();
        $this->app->session()->flash('success', 'You have been logged out.');
        $this->redirect('/login');
    }

    // ------------------------------------------------------------------ //
    //  OTP helper
    // ------------------------------------------------------------------ //

    private function sendOtp(int $userId, string $email, string $firstName, bool $isResend = false): never
    {
        // Re-fetch OTP rate-limit columns
        $user = $this->app->database()->fetch(
            'SELECT otp_sent_count, otp_sent_window_start FROM users WHERE id = :id LIMIT 1',
            ['id' => $userId]
        );

        $now        = new \DateTimeImmutable();
        $sentCount  = (int) ($user['otp_sent_count'] ?? 0);
        $windowStart = !empty($user['otp_sent_window_start'])
            ? new \DateTimeImmutable((string) $user['otp_sent_window_start'])
            : null;

        if ($windowStart !== null && ($now->getTimestamp() - $windowStart->getTimestamp()) < 3600) {
            if ($sentCount >= self::OTP_MAX_SENDS) {
                $this->app->session()->flash('error', 'Too many verification requests. Please wait an hour and try again.');
                $this->redirect('/login');
            }
            $newSentCount   = $sentCount + 1;
            $newWindowStart = $windowStart;
        } else {
            $newSentCount   = 1;
            $newWindowStart = $now;
        }

        $code    = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = $now->modify('+' . self::OTP_TTL_MINUTES . ' minutes');

        $this->app->database()->execute(
            'UPDATE users SET otp_code = :code, otp_expires_at = :exp, otp_attempts = 0,
                otp_sent_count = :cnt, otp_sent_window_start = :win
             WHERE id = :id',
            [
                'code' => $code,
                'exp'  => $expires->format('Y-m-d H:i:s'),
                'cnt'  => $newSentCount,
                'win'  => $newWindowStart->format('Y-m-d H:i:s'),
                'id'   => $userId,
            ]
        );

        $this->app->auth()->setPendingUserId($userId);

        // Send email
        $mailConfig = (array) $this->app->config('app.mail', []);
        $mailer     = new Mailer($mailConfig);

        if ($mailer->isEnabled()) {
            $html = $this->otpEmailHtml($firstName, $code);
            try {
                $mailer->send($email, 'Your HR System Login Code', $html);
            } catch (Throwable) {
                // silently fail — OTP is still saved
            }
        }

        if ($isResend) {
            $this->app->session()->flash('success', 'A new verification code has been sent to your email.');
        }

        $this->redirect('/otp');
    }

    private function otpEmailHtml(string $name, string $code): string
    {
        $appName = htmlspecialchars((string) config('app.name', 'HR System'), ENT_QUOTES);
        $name    = htmlspecialchars($name, ENT_QUOTES);
        $code    = htmlspecialchars($code, ENT_QUOTES);

        return '<!DOCTYPE html><html><body style="font-family:sans-serif;background:#f8f9fa;padding:40px">
<div style="max-width:480px;margin:auto;background:#fff;border-radius:8px;padding:40px;border:1px solid #dee2e6">
  <h2 style="color:#212529;margin-top:0">' . $appName . ' — Login Verification</h2>
  <p>Hi <strong>' . $name . '</strong>,</p>
  <p>Use the code below to complete your sign-in. It expires in <strong>10 minutes</strong>.</p>
  <div style="font-size:36px;font-weight:700;letter-spacing:12px;text-align:center;padding:24px;background:#f1f3f5;border-radius:6px;margin:24px 0">' . $code . '</div>
  <p style="color:#6c757d;font-size:13px">If you did not attempt to log in, please contact your system administrator immediately.</p>
</div></body></html>';
    }

    // ------------------------------------------------------------------ //
    //  CAPTCHA
    // ------------------------------------------------------------------ //

    private function recaptchaSiteKey(): string
    {
        if (!config('app.recaptcha.enabled', false)) {
            return '';
        }
        return (string) config('app.recaptcha.site_key', '');
    }

    private function verifyRecaptcha(string $token): bool
    {
        if (!config('app.recaptcha.enabled', false)) {
            return true; // CAPTCHA disabled — pass through
        }

        $secretKey = (string) config('app.recaptcha.secret_key', '');
        if ($secretKey === '' || $token === '') {
            return false;
        }

        $response = @file_get_contents(
            'https://www.google.com/recaptcha/api/siteverify?secret=' . urlencode($secretKey) . '&response=' . urlencode($token)
        );

        if ($response === false) {
            return true; // Can't reach Google — fail open to avoid lockout
        }

        $data = json_decode($response, true);
        $minScore = (float) config('app.recaptcha.min_score', 0.5);

        return isset($data['success']) && $data['success'] === true
            && isset($data['score']) && (float) $data['score'] >= $minScore;
    }

    // ------------------------------------------------------------------ //
    //  Private helpers
    // ------------------------------------------------------------------ //

    private function validateCsrf(Request $request, string $redirectPath, string $message = 'Your session has expired. Please try again.'): void
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
            ['now' => date('Y-m-d H:i:s'), 'status' => 'active']
        );

        foreach ($candidates as $candidate) {
            if (password_verify($token, (string) $candidate['token_hash'])) {
                return $candidate;
            }
        }

        return null;
    }

    private function sendPasswordResetEmail(string $email, string $resetLink, string $expiresAt): void
    {
        $appName = htmlspecialchars((string) config('app.name', 'HR System'), ENT_QUOTES);
        $link    = htmlspecialchars($resetLink, ENT_QUOTES);
        $expires = htmlspecialchars($expiresAt, ENT_QUOTES);

        $bodyHtml = '<!DOCTYPE html><html><body style="font-family:sans-serif;background:#f8f9fa;padding:40px">'
            . '<div style="max-width:520px;margin:auto;background:#fff;border-radius:8px;padding:40px;border:1px solid #dee2e6">'
            . '<h2 style="color:#212529;margin-top:0">' . $appName . ' — Password Reset</h2>'
            . '<p>A password reset was requested for your account.</p>'
            . '<p><a href="' . $link . '" style="display:inline-block;padding:12px 24px;background:#0d6efd;color:#fff;border-radius:6px;text-decoration:none;font-weight:600">Reset your password</a></p>'
            . '<p style="color:#6c757d;font-size:13px">This link expires at <strong>' . $expires . '</strong>.</p>'
            . '<p style="color:#6c757d;font-size:13px">If you did not request this, you can safely ignore this message.</p>'
            . '</div></body></html>';

        $mailConfig = (array) $this->app->config('app.mail', []);
        $mailer     = new Mailer($mailConfig);

        try {
            $mailer->send($email, 'Reset your password', $bodyHtml);
        } catch (Throwable) {
            // Email send failed — token is already saved, so the user can retry
        }
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
