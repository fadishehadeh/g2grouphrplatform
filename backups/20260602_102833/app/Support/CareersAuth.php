<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Session;

/**
 * Manages job-seeker portal authentication.
 * Uses a separate session key from the HR system auth.
 */
final class CareersAuth
{
    private const SESSION_KEY = 'careers_seeker';

    private Session $session;

    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    public function login(array $seeker): void
    {
        $this->session->regenerate();
        $this->session->put(self::SESSION_KEY, [
            'id'       => (int) $seeker['id'],
            'username' => $seeker['username'],
            'email'    => $seeker['email'],
        ]);
    }

    public function logout(): void
    {
        $this->session->remove(self::SESSION_KEY);
        $this->session->remove('careers_otp_pending');
    }

    public function check(): bool
    {
        return $this->session->get(self::SESSION_KEY) !== null;
    }

    public function user(): ?array
    {
        $u = $this->session->get(self::SESSION_KEY);
        return is_array($u) ? $u : null;
    }

    public function id(): ?int
    {
        return $this->user()['id'] ?? null;
    }

    /** Store pending seeker id while OTP is being verified */
    public function setPendingOtp(int $seekerId): void
    {
        $this->session->put('careers_otp_pending', $seekerId);
    }

    public function pendingOtpSeekerId(): ?int
    {
        $v = $this->session->get('careers_otp_pending');
        return is_int($v) ? $v : (is_numeric($v) ? (int) $v : null);
    }

    public function clearPendingOtp(): void
    {
        $this->session->remove('careers_otp_pending');
    }
}
