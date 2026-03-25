<?php

declare(strict_types=1);

namespace App\Core;

final class Application
{
    private static ?self $instance = null;

    private string $basePath;
    private array $config = [];
    private Router $router;
    private Session $session;
    private Database $database;
    private Auth $auth;
    private Csrf $csrf;

    public function __construct(string $basePath)
    {
        self::$instance = $this;
        $this->basePath = $basePath;

        $this->loadConfig();
        date_default_timezone_set((string) $this->config('app.timezone', 'UTC'));

        $this->session = new Session();
        $this->database = new Database((array) $this->config('database', []));
        $this->auth = new Auth($this->database, $this->session);
        $this->csrf = new Csrf($this->session);
        $this->router = new Router($this);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            throw new \RuntimeException('Application has not been booted.');
        }

        return self::$instance;
    }

    public function basePath(string $path = ''): string
    {
        return $this->basePath . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }

    public function config(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = $this->config;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function session(): Session
    {
        return $this->session;
    }

    public function database(): Database
    {
        return $this->database;
    }

    public function auth(): Auth
    {
        return $this->auth;
    }

    public function csrf(): Csrf
    {
        return $this->csrf;
    }

    public function run(): void
    {
        $this->applySecurityHeaders();
        $this->router->dispatch(Request::capture());
    }

    private function loadConfig(): void
    {
        $this->config = [
            'app' => require $this->basePath('config/app.php'),
            'database' => require $this->basePath('config/database.php'),
        ];
    }

    private function applySecurityHeaders(): void
    {
        if (headers_sent()) {
            return;
        }

        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');

        $referrerPolicy = trim((string) $this->config('app.security.referrer_policy', 'strict-origin-when-cross-origin'));
        if ($referrerPolicy !== '') {
            header('Referrer-Policy: ' . $referrerPolicy);
        }

        $permissionsPolicy = trim((string) $this->config('app.security.permissions_policy', 'camera=(), microphone=(), geolocation=()'));
        if ($permissionsPolicy !== '') {
            header('Permissions-Policy: ' . $permissionsPolicy);
        }

        $contentSecurityPolicy = trim((string) $this->config('app.security.content_security_policy', ''));
        if ($contentSecurityPolicy !== '') {
            header('Content-Security-Policy: ' . $contentSecurityPolicy);
        }

        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
}