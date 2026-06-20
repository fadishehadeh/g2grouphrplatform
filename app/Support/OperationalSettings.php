<?php

declare(strict_types=1);

namespace App\Support;

final class OperationalSettings
{
    public static function sections(): array
    {
        return [
            'general' => [
                'title' => 'General Application',
                'description' => 'Core runtime values that affect app URL and timezone.',
            ],
            'mail' => [
                'title' => 'Mail',
                'description' => 'Outgoing email delivery settings used for notifications and reset flows.',
            ],
            'backups' => [
                'title' => 'Backups / Resilience',
                'description' => 'Local backup storage and retention settings.',
            ],
            'b2' => [
                'title' => 'Backblaze B2',
                'description' => 'Off-server backup sync settings for resilience.',
            ],
            'security' => [
                'title' => 'Session / Security',
                'description' => 'Safe runtime security tuning values.',
            ],
        ];
    }

    public static function definitions(): array
    {
        static $definitions = null;

        if ($definitions !== null) {
            return $definitions;
        }

        $definitions = [
            'app_url' => [
                'section' => 'general',
                'category_name' => 'system_app',
                'setting_key' => 'app_url',
                'config_path' => 'app.url',
                'value_type' => 'string',
                'label' => 'Application URL',
                'help' => 'Full base URL for the HR app, e.g. https://hr.greydoha.com.',
                'input' => 'url',
                'placeholder' => 'https://hr.greydoha.com',
                'validator' => 'url',
            ],
            'app_timezone' => [
                'section' => 'general',
                'category_name' => 'system_app',
                'setting_key' => 'app_timezone',
                'config_path' => 'app.timezone',
                'value_type' => 'string',
                'label' => 'Application Timezone',
                'help' => 'PHP timezone identifier used across the app.',
                'input' => 'text',
                'placeholder' => 'Asia/Qatar',
                'validator' => 'timezone',
            ],
            'mail_enabled' => [
                'section' => 'mail',
                'category_name' => 'system_mail',
                'setting_key' => 'enabled',
                'config_path' => 'app.mail.enabled',
                'value_type' => 'boolean',
                'label' => 'Mail Enabled',
                'help' => 'Disable only if you want to stop all app email delivery.',
                'input' => 'boolean',
            ],
            'mail_transport' => [
                'section' => 'mail',
                'category_name' => 'system_mail',
                'setting_key' => 'transport',
                'config_path' => 'app.mail.transport',
                'value_type' => 'string',
                'label' => 'Transport',
                'help' => 'Supported transports are SMTP, Mailjet API, or PHP mail().',
                'input' => 'select',
                'options' => [
                    'smtp' => 'SMTP',
                    'mailjet' => 'Mailjet API',
                    'mail' => 'PHP mail()',
                ],
            ],
            'mail_host' => [
                'section' => 'mail',
                'category_name' => 'system_mail',
                'setting_key' => 'host',
                'config_path' => 'app.mail.host',
                'value_type' => 'string',
                'label' => 'Mail Host',
                'help' => 'Used for SMTP transport only.',
                'input' => 'text',
                'placeholder' => 'in-v3.mailjet.com',
            ],
            'mail_port' => [
                'section' => 'mail',
                'category_name' => 'system_mail',
                'setting_key' => 'port',
                'config_path' => 'app.mail.port',
                'value_type' => 'integer',
                'label' => 'Mail Port',
                'help' => 'Network port for SMTP.',
                'input' => 'number',
                'validator' => 'port',
            ],
            'mail_encryption' => [
                'section' => 'mail',
                'category_name' => 'system_mail',
                'setting_key' => 'encryption',
                'config_path' => 'app.mail.encryption',
                'value_type' => 'string',
                'label' => 'Encryption',
                'help' => 'Use None to save an explicit empty encryption value.',
                'input' => 'select',
                'options' => [
                    'tls' => 'TLS',
                    'ssl' => 'SSL',
                    '__EMPTY__' => 'None',
                ],
            ],
            'mail_username' => [
                'section' => 'mail',
                'category_name' => 'system_mail',
                'setting_key' => 'username',
                'config_path' => 'app.mail.username',
                'value_type' => 'string',
                'label' => 'Mail Username / API Key',
                'help' => 'SMTP username or Mailjet API key.',
                'input' => 'text',
            ],
            'mail_password' => [
                'section' => 'mail',
                'category_name' => 'system_mail',
                'setting_key' => 'password',
                'config_path' => 'app.mail.password',
                'value_type' => 'string',
                'label' => 'Mail Password / API Secret',
                'help' => 'Stored securely in the database override layer and never rendered back in plaintext.',
                'input' => 'password',
                'secret' => true,
            ],
            'mail_from_address' => [
                'section' => 'mail',
                'category_name' => 'system_mail',
                'setting_key' => 'from_address',
                'config_path' => 'app.mail.from_address',
                'value_type' => 'string',
                'label' => 'From Address',
                'help' => 'Sender email address used for outgoing app messages.',
                'input' => 'email',
                'validator' => 'email',
            ],
            'mail_from_name' => [
                'section' => 'mail',
                'category_name' => 'system_mail',
                'setting_key' => 'from_name',
                'config_path' => 'app.mail.from_name',
                'value_type' => 'string',
                'label' => 'From Name',
                'help' => 'Sender display name used in outgoing app messages.',
                'input' => 'text',
            ],
            'leave_admin_email' => [
                'section' => 'mail',
                'category_name' => 'system_leave',
                'setting_key' => 'admin_email',
                'config_path' => 'app.leave.admin_email',
                'value_type' => 'string',
                'label' => 'Leave Admin Email',
                'help' => 'Fallback HR/admin recipient used by leave-related notifications.',
                'input' => 'email',
                'validator' => 'email',
            ],
            'backup_storage_dir' => [
                'section' => 'backups',
                'category_name' => 'system_backups',
                'setting_key' => 'storage_dir',
                'config_path' => 'app.backups.storage_dir',
                'value_type' => 'string',
                'label' => 'Backup Storage Directory',
                'help' => 'Relative path inside the project where local backup files are stored.',
                'input' => 'text',
                'placeholder' => 'storage/backups',
            ],
            'backup_retention_days' => [
                'section' => 'backups',
                'category_name' => 'system_backups',
                'setting_key' => 'retention_days',
                'config_path' => 'app.backups.retention_days',
                'value_type' => 'integer',
                'label' => 'Backup Retention Days',
                'help' => 'Number of days local and B2 backups are retained.',
                'input' => 'number',
                'validator' => 'positive_integer',
            ],
            'backup_link_ttl_days' => [
                'section' => 'backups',
                'category_name' => 'system_backups',
                'setting_key' => 'link_ttl_days',
                'config_path' => 'app.backups.link_ttl_days',
                'value_type' => 'integer',
                'label' => 'Download Link TTL Days',
                'help' => 'Number of days generated backup download links remain valid.',
                'input' => 'number',
                'validator' => 'positive_integer',
            ],
            'b2_enabled' => [
                'section' => 'b2',
                'category_name' => 'system_b2',
                'setting_key' => 'enabled',
                'config_path' => 'app.b2.enabled',
                'value_type' => 'boolean',
                'label' => 'B2 Enabled',
                'help' => 'Enable or disable off-server backup sync.',
                'input' => 'boolean',
            ],
            'b2_key_id' => [
                'section' => 'b2',
                'category_name' => 'system_b2',
                'setting_key' => 'key_id',
                'config_path' => 'app.b2.key_id',
                'value_type' => 'string',
                'label' => 'B2 Key ID',
                'help' => 'Backblaze application key ID.',
                'input' => 'text',
            ],
            'b2_application_key' => [
                'section' => 'b2',
                'category_name' => 'system_b2',
                'setting_key' => 'application_key',
                'config_path' => 'app.b2.application_key',
                'value_type' => 'string',
                'label' => 'B2 Application Key',
                'help' => 'Secret key used for Backblaze B2 uploads.',
                'input' => 'password',
                'secret' => true,
            ],
            'b2_bucket_name' => [
                'section' => 'b2',
                'category_name' => 'system_b2',
                'setting_key' => 'bucket_name',
                'config_path' => 'app.b2.bucket_name',
                'value_type' => 'string',
                'label' => 'B2 Bucket Name',
                'help' => 'Target bucket for off-server backups.',
                'input' => 'text',
            ],
            'b2_endpoint' => [
                'section' => 'b2',
                'category_name' => 'system_b2',
                'setting_key' => 'endpoint',
                'config_path' => 'app.b2.endpoint',
                'value_type' => 'string',
                'label' => 'B2 Endpoint',
                'help' => 'Saved for operational visibility even though native B2 upload auto-discovers the API URL.',
                'input' => 'text',
                'placeholder' => 's3.us-east-005.backblazeb2.com',
            ],
            'login_lockout_attempts' => [
                'section' => 'security',
                'category_name' => 'system_security',
                'setting_key' => 'login_lockout_attempts',
                'config_path' => 'app.security.login_lockout_attempts',
                'value_type' => 'integer',
                'label' => 'Login Lockout Attempts',
                'help' => 'Number of failed login attempts before lockout.',
                'input' => 'number',
                'validator' => 'positive_integer',
            ],
            'login_lockout_minutes' => [
                'section' => 'security',
                'category_name' => 'system_security',
                'setting_key' => 'login_lockout_minutes',
                'config_path' => 'app.security.login_lockout_minutes',
                'value_type' => 'integer',
                'label' => 'Login Lockout Minutes',
                'help' => 'Minutes a locked user must wait before retrying.',
                'input' => 'number',
                'validator' => 'positive_integer',
            ],
            'session_idle_timeout' => [
                'section' => 'security',
                'category_name' => 'system_security',
                'setting_key' => 'session_idle_timeout',
                'config_path' => 'app.security.session_idle_timeout',
                'value_type' => 'integer',
                'label' => 'Session Idle Timeout (seconds)',
                'help' => 'Maximum idle time before a signed-in user is logged out.',
                'input' => 'number',
                'validator' => 'security_timeout',
            ],
            'session_cookie_lifetime' => [
                'section' => 'security',
                'category_name' => 'system_security',
                'setting_key' => 'session_cookie_lifetime',
                'config_path' => 'app.security.session_cookie_lifetime',
                'value_type' => 'integer',
                'label' => 'Session Cookie Lifetime (seconds)',
                'help' => 'Lifetime for the session cookie issued by the app.',
                'input' => 'number',
                'validator' => 'security_timeout',
            ],
            'password_reset_expiry_minutes' => [
                'section' => 'security',
                'category_name' => 'system_security',
                'setting_key' => 'password_reset_expiry_minutes',
                'config_path' => 'app.security.password_reset_expiry_minutes',
                'value_type' => 'integer',
                'label' => 'Password Reset Expiry (minutes)',
                'help' => 'How long password reset links remain valid.',
                'input' => 'number',
                'validator' => 'positive_integer',
            ],
            'referrer_policy' => [
                'section' => 'security',
                'category_name' => 'system_security',
                'setting_key' => 'referrer_policy',
                'config_path' => 'app.security.referrer_policy',
                'value_type' => 'string',
                'label' => 'Referrer Policy',
                'help' => 'HTTP Referrer-Policy header value.',
                'input' => 'text',
            ],
            'permissions_policy' => [
                'section' => 'security',
                'category_name' => 'system_security',
                'setting_key' => 'permissions_policy',
                'config_path' => 'app.security.permissions_policy',
                'value_type' => 'text',
                'label' => 'Permissions Policy',
                'help' => 'HTTP Permissions-Policy header value.',
                'input' => 'textarea',
            ],
            'content_security_policy' => [
                'section' => 'security',
                'category_name' => 'system_security',
                'setting_key' => 'content_security_policy',
                'config_path' => 'app.security.content_security_policy',
                'value_type' => 'text',
                'label' => 'Content Security Policy',
                'help' => 'HTTP Content-Security-Policy header value.',
                'input' => 'textarea',
            ],
        ];

        return $definitions;
    }

    public static function categories(): array
    {
        return array_values(array_unique(array_map(
            static fn (array $definition): string => (string) $definition['category_name'],
            self::definitions()
        )));
    }

    public static function systemManagedPairs(): array
    {
        $pairs = [];

        foreach (self::definitions() as $definition) {
            $pairs[] = [
                'category_name' => (string) $definition['category_name'],
                'setting_key' => (string) $definition['setting_key'],
            ];
        }

        $pairs[] = ['category_name' => 'modules', 'setting_key' => 'payroll_enabled'];

        return $pairs;
    }

    public static function isSystemManagedSetting(string $categoryName, string $settingKey): bool
    {
        foreach (self::systemManagedPairs() as $pair) {
            if ($pair['category_name'] === $categoryName && $pair['setting_key'] === $settingKey) {
                return true;
            }
        }

        return false;
    }

    public static function definitionByStorageKey(string $categoryName, string $settingKey): ?array
    {
        foreach (self::definitions() as $alias => $definition) {
            if ($definition['category_name'] === $categoryName && $definition['setting_key'] === $settingKey) {
                return ['alias' => $alias] + $definition;
            }
        }

        return null;
    }

    public static function definition(string $alias): ?array
    {
        $definitions = self::definitions();

        return isset($definitions[$alias]) ? ['alias' => $alias] + $definitions[$alias] : null;
    }

    public static function castStoredValue(?string $value, array $definition): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ((string) ($definition['value_type'] ?? 'string')) {
            'boolean' => $value === 'true',
            'integer' => (int) $value,
            default => $value,
        };
    }
}
