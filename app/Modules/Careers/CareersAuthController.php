<?php

declare(strict_types=1);

namespace App\Modules\Careers;

use App\Core\Application;
use App\Core\Controller;
use App\Core\Request;
use App\Support\CareersAuth;
use App\Support\CareersDatabase;
use App\Support\Mailer;
use Throwable;

final class CareersAuthController extends Controller
{
    private CareersRepository $repo;
    private CareersAuth $careersAuth;

    public function __construct(Application $app)
    {
        parent::__construct($app);
        $this->repo        = new CareersRepository(CareersDatabase::get());
        $this->careersAuth = new CareersAuth($app->session());
    }

    // ------------------------------------------------------------------ //
    //  Register
    // ------------------------------------------------------------------ //

    public function showRegister(Request $request): void
    {
        $this->render('careers.auth.register', ['title' => 'Create Account — Careers Portal'], 'careers');
    }

    public function register(Request $request): void
    {
        if (!$this->app->csrf()->validate((string) $request->input('_token'))) {
            $this->app->session()->flash('error', 'Invalid form token.');
            $this->redirect('/careers/register');
        }

        $username  = trim((string) $request->input('username', ''));
        $email     = trim((string) $request->input('email', ''));
        $password  = (string) $request->input('password', '');
        $confirm   = (string) $request->input('password_confirmation', '');

        $old = ['username' => $username, 'email' => $email];

        if ($username === '' || $email === '' || $password === '') {
            $this->app->session()->flash('error', 'All fields are required.');
            $this->app->session()->flash('old_input', $old);
            $this->redirect('/careers/register');
        }

        if (!preg_match('/^[a-zA-Z0-9_]{3,40}$/', $username)) {
            $this->app->session()->flash('error', 'Username must be 3–40 characters and contain only letters, numbers, and underscores.');
            $this->app->session()->flash('old_input', $old);
            $this->redirect('/careers/register');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->app->session()->flash('error', 'Please enter a valid email address.');
            $this->app->session()->flash('old_input', $old);
            $this->redirect('/careers/register');
        }

        if (strlen($password) < 8) {
            $this->app->session()->flash('error', 'Password must be at least 8 characters.');
            $this->app->session()->flash('old_input', $old);
            $this->redirect('/careers/register');
        }

        if ($password !== $confirm) {
            $this->app->session()->flash('error', 'Passwords do not match.');
            $this->app->session()->flash('old_input', $old);
            $this->redirect('/careers/register');
        }

        if ($this->repo->findSeekerByEmail($email) !== null) {
            $this->app->session()->flash('error', 'An account with that email already exists.');
            $this->app->session()->flash('old_input', $old);
            $this->redirect('/careers/register');
        }

        if ($this->repo->findSeekerByUsername($username) !== null) {
            $this->app->session()->flash('error', 'That username is already taken.');
            $this->app->session()->flash('old_input', $old);
            $this->redirect('/careers/register');
        }

        try {
            $this->repo->createSeeker($username, $email, password_hash($password, PASSWORD_BCRYPT));
            $this->app->session()->flash('success', 'Account created! Please log in.');
            $this->redirect('/careers/login');
        } catch (Throwable $e) {
            $this->app->session()->flash('error', 'Registration failed. Please try again.');
            $this->app->session()->flash('old_input', $old);
            $this->redirect('/careers/register');
        }
    }

    // ------------------------------------------------------------------ //
    //  Login
    // ------------------------------------------------------------------ //

    public function showLogin(Request $request): void
    {
        $this->render('careers.auth.login', ['title' => 'Sign In — Careers Portal'], 'careers');
    }

    public function login(Request $request): void
    {
        if (!$this->app->csrf()->validate((string) $request->input('_token'))) {
            $this->app->session()->flash('error', 'Invalid form token.');
            $this->redirect('/careers/login');
        }

        $email    = trim((string) $request->input('email', ''));
        $password = (string) $request->input('password', '');

        $seeker = $this->repo->findSeekerByEmail($email);

        if ($seeker === null || !password_verify($password, (string) $seeker['password_hash'])) {
            $this->app->session()->flash('error', 'Invalid email or password.');
            $this->app->session()->flash('old_input', ['email' => $email]);
            $this->redirect('/careers/login');
        }

        if (!(bool) $seeker['is_active']) {
            $this->app->session()->flash('error', 'Your account has been deactivated.');
            $this->redirect('/careers/login');
        }

        // Generate and send OTP
        $this->sendOtp($seeker);
    }

    // ------------------------------------------------------------------ //
    //  OTP
    // ------------------------------------------------------------------ //

    public function showOtp(Request $request): void
    {
        if ($this->careersAuth->pendingOtpSeekerId() === null) {
            $this->redirect('/careers/login');
        }
        $this->render('careers.auth.otp', ['title' => 'Verify OTP — Careers Portal'], 'careers');
    }

    public function verifyOtp(Request $request): void
    {
        if (!$this->app->csrf()->validate((string) $request->input('_token'))) {
            $this->app->session()->flash('error', 'Invalid form token.');
            $this->redirect('/careers/otp');
        }

        $seekerId = $this->careersAuth->pendingOtpSeekerId();
        if ($seekerId === null) {
            $this->redirect('/careers/login');
        }

        $seeker = $this->repo->findSeekerById($seekerId);
        if ($seeker === null) {
            $this->careersAuth->clearPendingOtp();
            $this->redirect('/careers/login');
        }

        $submitted = trim((string) $request->input('otp', ''));

        if ((int) $seeker['otp_attempts'] >= 5) {
            $this->app->session()->flash('error', 'Too many incorrect attempts. Please log in again.');
            $this->careersAuth->clearPendingOtp();
            $this->redirect('/careers/login');
        }

        if ($seeker['otp_code'] === null || new \DateTimeImmutable() > new \DateTimeImmutable((string) $seeker['otp_expires_at'])) {
            $this->app->session()->flash('error', 'Your OTP has expired. Please log in again to get a new one.');
            $this->careersAuth->clearPendingOtp();
            $this->redirect('/careers/login');
        }

        if (!hash_equals((string) $seeker['otp_code'], $submitted)) {
            $this->repo->incrementOtpAttempts($seekerId);
            $this->app->session()->flash('error', 'Incorrect code. Please try again.');
            $this->redirect('/careers/otp');
        }

        $this->repo->clearOtp($seekerId);
        $this->careersAuth->clearPendingOtp();
        $this->careersAuth->login($seeker);
        $this->redirect('/careers/dashboard');
    }

    public function resendOtp(Request $request): void
    {
        if (!$this->app->csrf()->validate((string) $request->input('_token'))) {
            $this->redirect('/careers/otp');
        }

        $seekerId = $this->careersAuth->pendingOtpSeekerId();
        if ($seekerId === null) {
            $this->redirect('/careers/login');
        }

        $seeker = $this->repo->findSeekerById($seekerId);
        if ($seeker === null) {
            $this->redirect('/careers/login');
        }

        $this->sendOtp($seeker, isResend: true);
    }

    // ------------------------------------------------------------------ //
    //  Logout
    // ------------------------------------------------------------------ //

    public function logout(Request $request): void
    {
        $this->careersAuth->logout();
        $this->app->session()->flash('success', 'You have been signed out.');
        $this->redirect('/careers/login');
    }

    // ------------------------------------------------------------------ //
    //  OTP helper
    // ------------------------------------------------------------------ //

    private function sendOtp(array $seeker, bool $isResend = false): never
    {
        $seekerId = (int) $seeker['id'];

        // Rate-limit: max 5 OTP sends per hour
        $windowStart = $seeker['otp_sent_window_start']
            ? new \DateTimeImmutable((string) $seeker['otp_sent_window_start'])
            : null;
        $sentCount = (int) $seeker['otp_sent_count'];

        $now = new \DateTimeImmutable();
        if ($windowStart !== null && ($now->getTimestamp() - $windowStart->getTimestamp()) < 3600) {
            if ($sentCount >= 5) {
                $this->app->session()->flash('error', 'Too many OTP requests. Please wait an hour and try again.');
                $this->redirect('/careers/login');
            }
            $newSentCount  = $sentCount + 1;
            $newWindowStart = $windowStart;
        } else {
            $newSentCount  = 1;
            $newWindowStart = $now;
        }

        // Resend cooldown: 60 seconds
        if ($isResend && $seeker['otp_expires_at'] !== null) {
            $expiresAt = new \DateTimeImmutable((string) $seeker['otp_expires_at']);
            $issuedAt  = $expiresAt->modify('-10 minutes');
            if (($now->getTimestamp() - $issuedAt->getTimestamp()) < 60) {
                $this->app->session()->flash('error', 'Please wait 60 seconds before requesting a new code.');
                $this->redirect('/careers/otp');
            }
        }

        $code    = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = $now->modify('+10 minutes');

        $this->repo->saveOtp($seekerId, $code, $expires, $newSentCount, $newWindowStart);
        $this->careersAuth->setPendingOtp($seekerId);

        // Send email
        $mailConfig = (array) $this->app->config('app.mail', []);
        $mailer     = new Mailer($mailConfig);

        if ($mailer->isEnabled()) {
            $html = $this->otpEmailHtml((string) $seeker['username'], $code);
            try {
                $mailer->send((string) $seeker['email'], 'Your Careers Portal OTP Code', $html);
            } catch (Throwable) {
                // silently fail — OTP is still saved, user will see the code-entry page
            }
        }

        if ($isResend) {
            $this->app->session()->flash('success', 'A new code has been sent to your email.');
        }

        $this->redirect('/careers/otp');
    }

    private function otpEmailHtml(string $username, string $code): string
    {
        return '<!DOCTYPE html><html><body style="font-family:sans-serif;background:#f8f9fa;padding:40px">
<div style="max-width:480px;margin:auto;background:#fff;border-radius:8px;padding:40px;border:1px solid #dee2e6">
  <h2 style="color:#212529;margin-top:0">Verify Your Login</h2>
  <p>Hi <strong>' . htmlspecialchars($username, ENT_QUOTES) . '</strong>,</p>
  <p>Use the code below to complete your sign-in to the Careers Portal. It expires in <strong>10 minutes</strong>.</p>
  <div style="font-size:36px;font-weight:700;letter-spacing:12px;text-align:center;padding:24px;background:#f1f3f5;border-radius:6px;margin:24px 0">
    ' . htmlspecialchars($code, ENT_QUOTES) . '
  </div>
  <p style="color:#6c757d;font-size:13px">If you did not attempt to log in, you can safely ignore this email.</p>
</div>
</body></html>';
    }
}
