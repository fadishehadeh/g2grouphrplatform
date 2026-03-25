<?php

declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    private Session $session;

    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    public function token(): string
    {
        if (!$this->session->has(self::SESSION_KEY)) {
            $this->session->put(self::SESSION_KEY, bin2hex(random_bytes(32)));
        }

        return (string) $this->session->get(self::SESSION_KEY);
    }

    public function validate(?string $token): bool
    {
        return is_string($token)
            && hash_equals((string) $this->session->get(self::SESSION_KEY, ''), $token);
    }
}