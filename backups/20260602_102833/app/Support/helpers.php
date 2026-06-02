<?php

declare(strict_types=1);

use App\Core\Application;
use App\Core\Auth;

// Load .env file once (if it exists)
(static function (): void {
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;
    $envFile = dirname(__DIR__, 2) . '/.env';
    if (!is_file($envFile)) {
        return;
    }
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (str_contains($line, '=')) {
            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            // Strip surrounding quotes
            if (strlen($value) >= 2 && (($value[0] === '"' && $value[-1] === '"') || ($value[0] === "'" && $value[-1] === "'"))) {
                $value = substr($value, 1, -1);
            }
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
            putenv("{$name}={$value}");
        }
    }
})();

function env(string $key, mixed $default = null): mixed
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

    return $value === false || $value === null ? $default : $value;
}

function app(): Application
{
    return Application::getInstance();
}

function config(string $key, mixed $default = null): mixed
{
    return app()->config($key, $default);
}

function auth(): Auth
{
    return app()->auth();
}

function base_path(string $path = ''): string
{
    return BASE_PATH . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

function url(string $path = ''): string
{
    $baseUrl = rtrim((string) config('app.url', ''), '/');
    $path = '/' . ltrim($path, '/');

    return $baseUrl !== '' ? $baseUrl . $path : $path;
}

function asset(string $path = ''): string
{
    $relativePath = 'assets/' . ltrim($path, '/');
    $assetUrl = url($relativePath);

    $version = trim((string) env('ASSET_VERSION', ''));
    if ($version !== '') {
        return $assetUrl . '?v=' . rawurlencode($version);
    }

    $fullPath = base_path('public/' . $relativePath);
    if (is_file($fullPath)) {
        return $assetUrl . '?v=' . filemtime($fullPath);
    }

    return $assetUrl;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function old(string $key, mixed $default = ''): mixed
{
    $oldInput = app()->session()->getFlash('old_input', []);

    return $oldInput[$key] ?? $default;
}

function flash(string $key, mixed $default = null): mixed
{
    return app()->session()->getFlash($key, $default);
}

function csrf_token(): string
{
    return app()->csrf()->token();
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

function has_role(string|array $roles): bool
{
    return auth()->hasRole($roles);
}

function can(string $permission): bool
{
    return auth()->hasPermission($permission);
}

/**
 * Encrypt a sensitive field value for database storage.
 * Returns null for null / empty input.
 */
function encrypt_field(?string $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    static $enc = null;
    if ($enc === null) {
        $enc = new \App\Support\Encryption();
    }

    return $enc->encrypt($value);
}

/**
 * Decrypt a sensitive field value retrieved from the database.
 * Returns null for null / empty stored value.
 */
function decrypt_field(?string $stored): ?string
{
    if ($stored === null || $stored === '') {
        return null;
    }

    static $enc = null;
    if ($enc === null) {
        $enc = new \App\Support\Encryption();
    }

    return $enc->decrypt($stored);
}

function notification_unread_count(): int
{
    if (!auth()->check() || !can('notifications.view_self')) {
        return 0;
    }

    $userId = auth()->id();

    if ($userId === null) {
        return 0;
    }

    try {
        return (int) (app()->database()->fetchValue(
            'SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0',
            ['user_id' => $userId]
        ) ?? 0);
    } catch (Throwable $throwable) {
        return 0;
    }
}
