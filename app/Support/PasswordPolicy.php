<?php

declare(strict_types=1);

namespace App\Support;

final class PasswordPolicy
{
    private const MIN_LENGTH = 10;

    public static function passes(string $password): bool
    {
        return self::errors($password) === [];
    }

    public static function errors(string $password): array
    {
        $errors = [];

        if (strlen($password) < self::MIN_LENGTH) {
            $errors[] = 'minimum_length';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'uppercase';
        }

        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'lowercase';
        }

        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'number';
        }

        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'symbol';
        }

        return $errors;
    }

    public static function description(): string
    {
        return 'Passwords must be at least 10 characters long and include uppercase, lowercase, number, and symbol.';
    }

    /**
     * Generate a cryptographically secure random password that satisfies all policy rules.
     */
    public static function generateSecurePassword(int $length = 16): string
    {
        $length = max($length, self::MIN_LENGTH);
        $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lower = 'abcdefghjkmnpqrstuvwxyz';
        $digits = '23456789';
        $symbols = '!@#$%^*()-_=+[]{}|;:,.?';

        // Guarantee at least one of each required class
        $password = $upper[random_int(0, strlen($upper) - 1)]
            . $lower[random_int(0, strlen($lower) - 1)]
            . $digits[random_int(0, strlen($digits) - 1)]
            . $symbols[random_int(0, strlen($symbols) - 1)];

        $pool = $upper . $lower . $digits . $symbols;

        for ($i = strlen($password); $i < $length; $i++) {
            $password .= $pool[random_int(0, strlen($pool) - 1)];
        }

        // Shuffle to avoid predictable positions
        $chars = str_split($password);
        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        return implode('', $chars);
    }
}