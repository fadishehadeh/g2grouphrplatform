<?php

declare(strict_types=1);

namespace App\Core;

final class Session
{
    private bool $timedOut = false;

    public function __construct()
    {
        $this->start();
        $this->sweepFlashData();
    }

    public function timedOut(): bool
    {
        return $this->timedOut;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $_SESSION);
    }

    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public function invalidate(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            setcookie(session_name(), '', $this->expiredCookieOptions());
        }

        session_destroy();
    }

    public function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash'][$key] = [
            'value' => $value,
            'remove' => false,
        ];
    }

    public function getFlash(string $key, mixed $default = null): mixed
    {
        return $_SESSION['_flash'][$key]['value'] ?? $default;
    }

    private function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', $secure ? '1' : '0');

        session_name((string) config('app.session_name', 'hr_system_session'));
        session_set_cookie_params($this->cookieOptions($secure));

        session_start();
        $this->enforceIdleTimeout();
        $_SESSION['_flash'] ??= [];
        $_SESSION['_last_activity_at'] = time();
    }

    private function sweepFlashData(): void
    {
        foreach ($_SESSION['_flash'] as $key => $payload) {
            if (($payload['remove'] ?? false) === true) {
                unset($_SESSION['_flash'][$key]);
                continue;
            }

            $_SESSION['_flash'][$key]['remove'] = true;
        }
    }

    private function enforceIdleTimeout(): void
    {
        $timeout = (int) config('app.security.session_idle_timeout', 7200);
        $lastActivity = $_SESSION['_last_activity_at'] ?? null;

        if ($timeout <= 0 || !is_int($lastActivity)) {
            return;
        }

        if ((time() - $lastActivity) <= $timeout) {
            return;
        }

        $_SESSION = [];
        session_regenerate_id(true);
        $this->timedOut = true;
    }

    private function cookieOptions(bool $secure): array
    {
        return [
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }

    private function expiredCookieOptions(): array
    {
        $params = session_get_cookie_params();

        return [
            'expires' => time() - 42000,
            'path' => $params['path'] ?? '/',
            'domain' => $params['domain'] ?? '',
            'secure' => (bool) ($params['secure'] ?? false),
            'httponly' => (bool) ($params['httponly'] ?? true),
            'samesite' => $params['samesite'] ?? 'Lax',
        ];
    }
}