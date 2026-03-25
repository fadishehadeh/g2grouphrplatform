<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\PasswordPolicy;
use PHPUnit\Framework\TestCase;

final class PasswordPolicyTest extends TestCase
{
    public function testStrongPasswordPassesPolicy(): void
    {
        $password = 'GreyDoha#2026';

        self::assertTrue(PasswordPolicy::passes($password));
        self::assertSame([], PasswordPolicy::errors($password));
    }

    public function testWeakPasswordReturnsExpectedErrors(): void
    {
        $password = 'weak';

        self::assertFalse(PasswordPolicy::passes($password));
        self::assertSame(
            ['minimum_length', 'uppercase', 'number', 'symbol'],
            PasswordPolicy::errors($password)
        );
    }

    public function testDescriptionSummarizesRequirements(): void
    {
        self::assertStringContainsString('10 characters', PasswordPolicy::description());
        self::assertStringContainsString('uppercase', PasswordPolicy::description());
        self::assertStringContainsString('symbol', PasswordPolicy::description());
    }
}