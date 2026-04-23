<?php

declare(strict_types=1);

namespace App\Support;

use Throwable;

final class Branding
{
    public static function appName(): string
    {
        return (string) config('app.brand.display_name', config('app.name', 'HR Management System'));
    }

    public static function brandColor(): string
    {
        return '#ff3d33';
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
