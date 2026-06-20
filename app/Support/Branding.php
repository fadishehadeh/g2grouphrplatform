<?php

declare(strict_types=1);

namespace App\Support;

use Throwable;

final class Branding
{
    /** @var array<string,mixed>|null */
    private static ?array $tenantCache = null;

    /**
     * Fetch (and cache per-request) the is_main_tenant=1 row.
     * Returns an empty array if the row doesn't exist yet.
     *
     * @return array<string,mixed>
     */
    private static function tenant(): array
    {
        if (self::$tenantCache === null) {
            try {
                $row = app()->database()->fetch(
                    'SELECT name, tagline, logo_path, logo_path_white, brand_color
                     FROM companies
                     WHERE is_main_tenant = 1
                     LIMIT 1'
                );
                self::$tenantCache = is_array($row) ? $row : [];
            } catch (Throwable) {
                self::$tenantCache = [];
            }
        }

        return self::$tenantCache;
    }

    /** Invalidate the per-request tenant cache after saving branding. */
    public static function clearCache(): void
    {
        self::$tenantCache = null;
    }

    // ------------------------------------------------------------------
    // Main-tenant branding (DB-backed, fall back to config/env)
    // ------------------------------------------------------------------

    /** Display name of the main tenant, e.g. "G2 Group". */
    public static function name(): string
    {
        $v = self::tenant()['name'] ?? null;

        return ($v !== null && $v !== '')
            ? (string) $v
            : self::appName();
    }

    /** Tagline of the main tenant. */
    public static function tagline(): string
    {
        $v = self::tenant()['tagline'] ?? null;

        return ($v !== null && $v !== '')
            ? (string) $v
            : (string) config('app.brand.tagline', 'People operations platform');
    }

    /** Web URL of the main tenant's primary (coloured) logo. */
    public static function logoUrl(): string
    {
        $path = self::tenant()['logo_path'] ?? null;

        if ($path !== null && $path !== '') {
            $clean = ltrim((string) $path, '/');
            if (is_file(base_path('public-hr/' . $clean))) {
                return url('/' . $clean);
            }
        }

        return self::defaultLogoUrl();
    }

    /** Web URL of the main tenant's white/reversed logo. */
    public static function logoWhiteUrl(): string
    {
        $path = self::tenant()['logo_path_white'] ?? null;

        if ($path !== null && $path !== '') {
            $clean = ltrim((string) $path, '/');
            if (is_file(base_path('public-hr/' . $clean))) {
                return url('/' . $clean);
            }
        }

        return url('/assets/images/g2group-white.svg');
    }

    /** Hex brand colour, e.g. "#FF3D33", or the default if not configured. */
    public static function brandColor(): string
    {
        $v = self::tenant()['brand_color'] ?? null;

        return ($v !== null && $v !== '') ? (string) $v : '#ff3d33';
    }

    // ------------------------------------------------------------------
    // Legacy / config-only accessors (kept for backward compat)
    // ------------------------------------------------------------------

    public static function appName(): string
    {
        return (string) config('app.brand.display_name', config('app.name', 'HR Management System'));
    }

    public static function defaultLogoUrl(): string
    {
        return self::assetUrl((string) config('app.brand.logo_asset', 'images/g2group.svg'));
    }

    public static function defaultLogoPath(): ?string
    {
        $path = base_path('public/assets/' . ltrim((string) config('app.brand.logo_asset', 'images/g2group.svg'), '/'));

        return is_file($path) ? $path : null;
    }

    public static function companyLogoUrlForUser(?int $userId, ?string $email = null): string
    {
        $logoPath = self::companyLogoPathForUser($userId, $email);

        return self::publicHrUrl($logoPath) ?? self::defaultLogoUrl();
    }

    public static function companyLogoUrlForEmployee(array $employee): string
    {
        $logoPath = (string) ($employee['company_logo_path'] ?? '');

        if ($logoPath === '') {
            $employeeId = isset($employee['id']) ? (int) $employee['id'] : null;
            $email = (string) ($employee['work_email'] ?? '');
            $logoPath = self::companyLogoPathForEmployee($employeeId, $email) ?? '';
        }

        return self::publicHrUrl($logoPath) ?? self::defaultLogoUrl();
    }

    public static function companyLogoFileForEmployee(array $employee): ?string
    {
        $logoPath = (string) ($employee['company_logo_path'] ?? '');

        if ($logoPath === '') {
            $employeeId = isset($employee['id']) ? (int) $employee['id'] : null;
            $email = (string) ($employee['work_email'] ?? '');
            $logoPath = self::companyLogoPathForEmployee($employeeId, $email) ?? '';
        }

        return self::publicHrFile($logoPath) ?? self::defaultLogoPath();
    }

    private static function companyLogoPathForUser(?int $userId, ?string $email): ?string
    {
        try {
            if ($userId !== null && $userId > 0) {
                $path = app()->database()->fetchValue(
                    'SELECT c.logo_path
                     FROM users u
                     LEFT JOIN employees e ON e.user_id = u.id
                     LEFT JOIN companies c ON c.id = e.company_id
                     WHERE u.id = :user_id
                     LIMIT 1',
                    ['user_id' => $userId]
                );

                if (is_string($path) && $path !== '') {
                    return $path;
                }
            }

            if ($email !== null && $email !== '') {
                $path = app()->database()->fetchValue(
                    'SELECT c.logo_path
                     FROM employees e
                     INNER JOIN companies c ON c.id = e.company_id
                     WHERE e.work_email = :email
                     LIMIT 1',
                    ['email' => $email]
                );

                return is_string($path) && $path !== '' ? $path : null;
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    private static function companyLogoPathForEmployee(?int $employeeId, ?string $email): ?string
    {
        try {
            if ($employeeId !== null && $employeeId > 0) {
                $path = app()->database()->fetchValue(
                    'SELECT c.logo_path
                     FROM employees e
                     INNER JOIN companies c ON c.id = e.company_id
                     WHERE e.id = :employee_id
                     LIMIT 1',
                    ['employee_id' => $employeeId]
                );

                if (is_string($path) && $path !== '') {
                    return $path;
                }
            }

            if ($email !== null && $email !== '') {
                $path = app()->database()->fetchValue(
                    'SELECT c.logo_path
                     FROM employees e
                     INNER JOIN companies c ON c.id = e.company_id
                     WHERE e.work_email = :email
                     LIMIT 1',
                    ['email' => $email]
                );

                return is_string($path) && $path !== '' ? $path : null;
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    private static function publicHrUrl(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        $path = ltrim($path, '/');
        if (!is_file(base_path('public-hr/' . $path))) {
            return null;
        }

        return url('/' . $path);
    }

    private static function publicHrFile(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        $file = base_path('public-hr/' . ltrim($path, '/'));

        return is_file($file) ? $file : null;
    }

    private static function assetUrl(string $path): string
    {
        $relativePath = 'assets/' . ltrim($path, '/');

        return url('/' . $relativePath);
    }
}
