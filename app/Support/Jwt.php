<?php

declare(strict_types=1);

namespace App\Support;

use UnexpectedValueException;

/**
 * Minimal HMAC-SHA256 JWT implementation.
 * No external dependency — only uses built-in PHP functions.
 * Supports HS256 only.
 */
final class Jwt
{
    private static function b64Encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function b64Decode(string $data): string
    {
        $padded = str_pad(strtr($data, '-_', '+/'), strlen($data) + (4 - strlen($data) % 4) % 4, '=');
        $decoded = base64_decode($padded, strict: true);
        if ($decoded === false) {
            throw new UnexpectedValueException('Invalid base64url segment.');
        }
        return $decoded;
    }

    public static function encode(array $payload, string $secret): string
    {
        if ($secret === '') {
            throw new UnexpectedValueException('JWT secret must not be empty.');
        }

        $header  = self::b64Encode((string) json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $body    = self::b64Encode((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $sig     = self::b64Encode(hash_hmac('sha256', "{$header}.{$body}", $secret, true));

        return "{$header}.{$body}.{$sig}";
    }

    /**
     * Decode and verify a JWT. Throws UnexpectedValueException on any failure.
     * Checks: signature, expiry (exp claim). Does NOT check nbf or iss.
     */
    public static function decode(string $token, string $secret): array
    {
        if ($secret === '') {
            throw new UnexpectedValueException('JWT secret must not be empty.');
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new UnexpectedValueException('Invalid JWT structure.');
        }

        [$headerB64, $bodyB64, $sigB64] = $parts;

        $expectedSig = self::b64Encode(hash_hmac('sha256', "{$headerB64}.{$bodyB64}", $secret, true));
        if (!hash_equals($expectedSig, $sigB64)) {
            throw new UnexpectedValueException('JWT signature verification failed.');
        }

        $payload = json_decode(self::b64Decode($bodyB64), true);
        if (!is_array($payload)) {
            throw new UnexpectedValueException('JWT payload is not a valid JSON object.');
        }

        if (isset($payload['exp']) && (int) $payload['exp'] < time()) {
            throw new UnexpectedValueException('JWT has expired.');
        }

        return $payload;
    }
}
