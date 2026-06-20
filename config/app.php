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
    'marketing' => [
        'product_name' => env('MARKETING_PRODUCT_NAME', 'Peoplova'),
        'demo_email' => trim((string) env('MARKETING_DEMO_EMAIL', env('LEAVE_ADMIN_EMAIL', env('MAIL_FROM_ADDRESS', '')))),
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
    'backups' => [
        'storage_dir' => env('BACKUP_STORAGE_DIR', 'storage/backups'),
        'retention_days' => (int) env('BACKUP_RETENTION_DAYS', '30'),
        'link_ttl_days' => (int) env('BACKUP_LINK_TTL_DAYS', '7'),
    ],
    'b2' => [
        'enabled'         => filter_var(env('B2_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN),
        'key_id'          => env('B2_KEY_ID', ''),
        'application_key' => env('B2_APPLICATION_KEY', ''),
        'bucket_name'     => env('B2_BUCKET_NAME', ''),
        'endpoint'        => env('B2_ENDPOINT', ''),   // S3-compatible endpoint (informational; native API auto-discovers)
    ],
    'recaptcha' => [
        'enabled'   => filter_var(env('RECAPTCHA_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN),
        'site_key'  => env('RECAPTCHA_SITE_KEY', ''),
        'secret_key'=> env('RECAPTCHA_SECRET_KEY', ''),
        'min_score' => (float) env('RECAPTCHA_MIN_SCORE', '0.5'),
    ],
    'jwt' => [
        'secret'             => env('JWT_SECRET', ''),
        'access_ttl_seconds' => (int) env('JWT_ACCESS_TTL', '3600'),
        'refresh_ttl_days'   => (int) env('JWT_REFRESH_TTL_DAYS', '30'),
    ],
    'security' => [
        'login_lockout_attempts' => (int) env('LOGIN_LOCKOUT_ATTEMPTS', '5'),
        'login_lockout_minutes'  => (int) env('LOGIN_LOCKOUT_MINUTES', '15'),
        'session_idle_timeout' => (int) env('SESSION_IDLE_TIMEOUT', '3600'),
        'session_cookie_lifetime' => (int) env('SESSION_COOKIE_LIFETIME', '3600'),
        'password_reset_expiry_minutes' => (int) env('PASSWORD_RESET_EXPIRY_MINUTES', '60'),
        'referrer_policy' => env('REFERRER_POLICY', 'strict-origin-when-cross-origin'),
        'permissions_policy' => env('PERMISSIONS_POLICY', 'camera=(), microphone=(), geolocation=()'),
        'content_security_policy' => env(
            'CONTENT_SECURITY_POLICY',
            "default-src 'self'; connect-src 'self' https://cdn.jsdelivr.net https://static.cloudflareinsights.com; img-src 'self' data:; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://static.cloudflareinsights.com; font-src 'self' data: https://cdn.jsdelivr.net; object-src 'none'; base-uri 'self'; frame-ancestors 'self'; form-action 'self'"
        ),
    ],
];
