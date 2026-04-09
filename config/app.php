<?php

declare(strict_types=1);

return [
    'name' => env('APP_NAME', 'HR Management System'),
    'env' => env('APP_ENV', 'production'),
    'debug' => filter_var(env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN),
    'url' => env('APP_URL', 'http://localhost/HR%20System/public'),
    'timezone' => env('APP_TIMEZONE', 'UTC'),
    'session_name' => env('SESSION_NAME', 'hr_system_session'),
    'brand' => [
        'display_name' => env('APP_BRAND_NAME', env('APP_NAME', 'HR Management System')),
        'tagline' => env('APP_BRAND_TAGLINE', 'People operations platform'),
        'logo_asset' => env('APP_LOGO_ASSET', 'images/g2group.svg'),
    ],
    'mail' => [
        'enabled' => filter_var(env('MAIL_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN),
        'transport' => env('MAIL_TRANSPORT', 'smtp'),       // smtp | mail
        'host' => env('MAIL_HOST', '127.0.0.1'),
        'port' => (int) env('MAIL_PORT', '587'),
        'encryption' => env('MAIL_ENCRYPTION', 'tls'),      // tls | ssl | '' (none)
        'username' => env('MAIL_USERNAME', ''),
        'password' => env('MAIL_PASSWORD', ''),
        'from_address' => env('MAIL_FROM_ADDRESS', ''),
        'from_name' => env('MAIL_FROM_NAME', env('APP_NAME', 'HR Management System')),
        'max_attempts' => (int) env('MAIL_MAX_ATTEMPTS', '3'),
    ],
    'leave' => [
        'admin_email' => trim((string) env('LEAVE_ADMIN_EMAIL', '')),
    ],
    'security' => [
        'session_idle_timeout' => (int) env('SESSION_IDLE_TIMEOUT', '7200'),
        'password_reset_expiry_minutes' => (int) env('PASSWORD_RESET_EXPIRY_MINUTES', '60'),
        'referrer_policy' => env('REFERRER_POLICY', 'strict-origin-when-cross-origin'),
        'permissions_policy' => env('PERMISSIONS_POLICY', 'camera=(), microphone=(), geolocation=()'),
        'content_security_policy' => env(
            'CONTENT_SECURITY_POLICY',
            "default-src 'self'; connect-src 'self' https://cdn.jsdelivr.net; img-src 'self' data:; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; font-src 'self' data: https://cdn.jsdelivr.net; object-src 'none'; base-uri 'self'; frame-ancestors 'self'; form-action 'self'"
        ),
    ],
];
