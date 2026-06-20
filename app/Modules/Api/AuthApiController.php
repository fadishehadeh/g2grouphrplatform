<?php

declare(strict_types=1);

namespace App\Modules\Api;

use App\Core\Request;
use App\Support\Branding;
use App\Support\EmailTemplate;
use App\Support\Jwt;
use App\Support\Mailer;
use DateTimeImmutable;
use Throwable;
use UnexpectedValueException;

/**
 * Two-step JWT login flow:
 *   POST /api/v1/auth/login  → { data: { pending_token, otp_sent } }
 *   POST /api/v1/auth/otp    → { data: { access_token, refresh_token, expires_in, token_type } }
 *   POST /api/v1/auth/refresh→ { data: { access_token, expires_in, token_type } }
 *   POST /api/v1/auth/logout → { data: { message } }
 */
final class AuthApiController extends ApiController
{
    private const OTP_TTL_MINUTES  = 10;
    private const OTP_MAX_ATTEMPTS = 5;
    private const OTP_MAX_SENDS    = 5;
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOCKOUT_MINUTES    = 15;

    // ------------------------------------------------------------------
    //  Step 1 — POST /api/v1/auth/login
    // ------------------------------------------------------------------

    public function login(Request $request): never
    {
        $body     = $this->jsonBody($request);
        $login    = trim((string) ($body['login'] ?? ''));
        $password = (string) ($body['password'] ?? '');

        if ($login === '' || $password === '') {
            $this->error('login and password are required.', 422);
        }

        $user = $this->app->auth()->findByLogin($login);

        if ($user === null || ($user['status'] ?? 'inactive') !== 'active') {
            $this->error('Invalid credentials or inactive account.', 401);
        }

        // Lockout check
        if (!empty($user['locked_until'])) {
            try {
                $lockedUntil = new DateTimeImmutable((string) $user['locked_until']);
                if (new DateTimeImmutable() < $lockedUntil) {
                    $remaining = (int) ceil(($lockedUntil->getTimestamp() - time()) / 60);
                    $this->error("Account locked. Try again in {$remaining} minute(s).", 429);
                }
            } catch (Throwable) {
            }
        }

        if (!password_verify($password, (string) ($user['password_hash'] ?? ''))) {
            $newAttempts = (int) ($user['login_attempts'] ?? 0) + 1;
            $lockedUntil = null;

            if ($newAttempts >= self::MAX_LOGIN_ATTEMPTS) {
                $lockedUntil = date('Y-m-d H:i:s', strtotime('+' . self::LOCKOUT_MINUTES . ' minutes'));
            }

            $this->app->database()->execute(
                'UPDATE users SET login_attempts = :a, locked_until = :l WHERE id = :id',
                ['a' => $newAttempts, 'l' => $lockedUntil, 'id' => (int) $user['id']]
            );

            if ($lockedUntil !== null) {
                $this->error('Too many failed attempts. Account locked for ' . self::LOCKOUT_MINUTES . ' minutes.', 429);
            }

            $remaining = self::MAX_LOGIN_ATTEMPTS - $newAttempts;
            $this->error("Invalid credentials. {$remaining} attempt(s) remaining before lockout.", 401);
        }

        // Credentials correct — reset counter
        $this->app->database()->execute(
            'UPDATE users SET login_attempts = 0, locked_until = NULL WHERE id = :id',
            ['id' => (int) $user['id']]
        );

        // Send OTP
        $this->dispatchOtp((int) $user['id'], (string) ($user['email'] ?? ''), (string) ($user['first_name'] ?? 'User'));

        // Issue a short-lived pending token (10 min)
        $secret       = $this->jwtSecret();
        $pendingToken = Jwt::encode([
            'type' => 'pending',
            'sub'  => (int) $user['id'],
            'iat'  => time(),
            'exp'  => time() + (self::OTP_TTL_MINUTES * 60),
        ], $secret);

        $this->success(['pending_token' => $pendingToken, 'otp_sent' => true]);
    }

    // ------------------------------------------------------------------
    //  Step 2 — POST /api/v1/auth/otp
    // ------------------------------------------------------------------

    public function otp(Request $request): never
    {
        $body         = $this->jsonBody($request);
        $pendingToken = trim((string) ($body['pending_token'] ?? ''));
        $submittedOtp = trim((string) ($body['otp'] ?? ''));
        $deviceLabel  = trim((string) ($body['device_label'] ?? ''));

        if ($pendingToken === '' || $submittedOtp === '') {
            $this->error('pending_token and otp are required.', 422);
        }

        $secret = $this->jwtSecret();

        try {
            $payload = Jwt::decode($pendingToken, $secret);
        } catch (UnexpectedValueException $e) {
            $this->error('Invalid or expired pending token: ' . $e->getMessage(), 401);
        }

        if (($payload['type'] ?? '') !== 'pending') {
            $this->error('Token type mismatch.', 401);
        }

        $userId = (int) ($payload['sub'] ?? 0);
        if ($userId <= 0) {
            $this->error('Invalid pending token.', 401);
        }

        $user = $this->app->database()->fetch(
            'SELECT id, email, first_name, otp_code, otp_expires_at, otp_attempts FROM users WHERE id = :id LIMIT 1',
            ['id' => $userId]
        );

        if ($user === null) {
            $this->error('User not found.', 401);
        }

        if ((int) ($user['otp_attempts'] ?? 0) >= self::OTP_MAX_ATTEMPTS) {
            $this->error('Too many OTP attempts. Please log in again.', 429);
        }

        if (empty($user['otp_code']) || empty($user['otp_expires_at'])) {
            $this->error('No OTP was issued. Please start the login flow again.', 401);
        }

        try {
            if (new DateTimeImmutable() > new DateTimeImmutable((string) $user['otp_expires_at'])) {
                $this->error('OTP has expired. Please log in again.', 401);
            }
        } catch (Throwable) {
            $this->error('OTP expiry is invalid.', 401);
        }

        if (!hash_equals((string) $user['otp_code'], $submittedOtp)) {
            $this->app->database()->execute(
                'UPDATE users SET otp_attempts = otp_attempts + 1 WHERE id = :id',
                ['id' => $userId]
            );
            $this->error('Incorrect OTP code.', 401);
        }

        // OTP correct — clear it
        $this->app->database()->execute(
            'UPDATE users SET last_login_at = NOW(), login_attempts = 0, locked_until = NULL,
                otp_code = NULL, otp_expires_at = NULL, otp_attempts = 0
             WHERE id = :id',
            ['id' => $userId]
        );

        [$accessToken, $refreshToken, $expiresIn] = $this->issuePair($userId, $deviceLabel !== '' ? $deviceLabel : null);

        $this->success([
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in'    => $expiresIn,
            'token_type'    => 'Bearer',
        ]);
    }

    // ------------------------------------------------------------------
    //  POST /api/v1/auth/refresh
    // ------------------------------------------------------------------

    public function refresh(Request $request): never
    {
        $body         = $this->jsonBody($request);
        $refreshToken = trim((string) ($body['refresh_token'] ?? ''));

        if ($refreshToken === '') {
            $this->error('refresh_token is required.', 422);
        }

        $tokenHash = hash('sha256', $refreshToken);

        $row = $this->app->database()->fetch(
            'SELECT id, user_id, expires_at, revoked_at, device_label
             FROM refresh_tokens
             WHERE token_hash = :hash LIMIT 1',
            ['hash' => $tokenHash]
        );

        if ($row === null) {
            $this->error('Invalid refresh token.', 401);
        }

        if ($row['revoked_at'] !== null) {
            $this->error('Refresh token has been revoked.', 401);
        }

        try {
            if (new DateTimeImmutable() > new DateTimeImmutable((string) $row['expires_at'])) {
                $this->error('Refresh token has expired. Please log in again.', 401);
            }
        } catch (Throwable) {
            $this->error('Refresh token expiry is invalid.', 401);
        }

        $userId = (int) $row['user_id'];

        // Rotate: revoke old token, issue new pair
        $this->app->database()->execute(
            'UPDATE refresh_tokens SET revoked_at = NOW() WHERE id = :id',
            ['id' => (int) $row['id']]
        );

        [$accessToken, $newRefreshToken, $expiresIn] = $this->issuePair($userId, $row['device_label'] ?? null);

        $this->success([
            'access_token'  => $accessToken,
            'refresh_token' => $newRefreshToken,
            'expires_in'    => $expiresIn,
            'token_type'    => 'Bearer',
        ]);
    }

    // ------------------------------------------------------------------
    //  POST /api/v1/auth/logout
    // ------------------------------------------------------------------

    public function logout(Request $request): never
    {
        $body         = $this->jsonBody($request);
        $refreshToken = trim((string) ($body['refresh_token'] ?? ''));

        if ($refreshToken !== '') {
            $tokenHash = hash('sha256', $refreshToken);
            try {
                $this->app->database()->execute(
                    'UPDATE refresh_tokens SET revoked_at = NOW()
                     WHERE token_hash = :hash AND revoked_at IS NULL',
                    ['hash' => $tokenHash]
                );
            } catch (Throwable) {
                // Best-effort
            }
        }

        $this->success(['message' => 'Logged out.']);
    }

    // ------------------------------------------------------------------
    //  Internals
    // ------------------------------------------------------------------

    /**
     * Issue an access JWT + refresh token pair for $userId.
     * Returns [$accessToken, $refreshToken, $expiresIn].
     */
    private function issuePair(int $userId, ?string $deviceLabel): array
    {
        $secret     = $this->jwtSecret();
        $accessTtl  = (int) config('app.jwt.access_ttl_seconds', 3600);
        $refreshTtl = (int) config('app.jwt.refresh_ttl_days', 30);

        $userRow = $this->app->database()->fetch(
            'SELECT u.id, u.username, u.email, u.first_name, u.last_name,
                    r.id AS role_id, r.code AS role_code, r.name AS role_name,
                    e.id AS employee_id, e.company_id
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             LEFT JOIN employees e ON e.user_id = u.id
             WHERE u.id = :id AND u.status = :status LIMIT 1',
            ['id' => $userId, 'status' => 'active']
        );

        if ($userRow === null) {
            $this->error('User account is inactive or not found.', 401);
        }

        $permissions = $this->app->database()->fetchAll(
            'SELECT p.code FROM role_permissions rp
             INNER JOIN permissions p ON p.id = rp.permission_id
             WHERE rp.role_id = :role_id',
            ['role_id' => (int) $userRow['role_id']]
        );

        $now       = time();
        $accessExp = $now + $accessTtl;

        $accessToken = Jwt::encode([
            'type'        => 'access',
            'sub'         => $userId,
            'username'    => (string) ($userRow['username'] ?? ''),
            'email'       => (string) ($userRow['email'] ?? ''),
            'first_name'  => (string) ($userRow['first_name'] ?? ''),
            'last_name'   => (string) ($userRow['last_name'] ?? ''),
            'role_code'   => (string) ($userRow['role_code'] ?? ''),
            'role_name'   => (string) ($userRow['role_name'] ?? ''),
            'employee_id' => isset($userRow['employee_id']) ? (int) $userRow['employee_id'] : null,
            'company_id'  => isset($userRow['company_id'])  ? (int) $userRow['company_id']  : null,
            'permissions' => array_column($permissions, 'code'),
            'iat'         => $now,
            'exp'         => $accessExp,
        ], $secret);

        $rawRefresh = bin2hex(random_bytes(40));
        $refreshHash = hash('sha256', $rawRefresh);
        $refreshExpiresAt = date('Y-m-d H:i:s', strtotime("+{$refreshTtl} days"));

        $this->app->database()->execute(
            'INSERT INTO refresh_tokens (user_id, token_hash, device_label, expires_at)
             VALUES (:user_id, :token_hash, :device_label, :expires_at)',
            [
                'user_id'      => $userId,
                'token_hash'   => $refreshHash,
                'device_label' => $deviceLabel,
                'expires_at'   => $refreshExpiresAt,
            ]
        );

        return [$accessToken, $rawRefresh, $accessTtl];
    }

    /**
     * Write an OTP to the DB and send it by email. Pure side-effect — no session writes.
     * Returns on success. Calls $this->error() on rate-limit hit (never returns).
     */
    private function dispatchOtp(int $userId, string $email, string $firstName): void
    {
        $user = $this->app->database()->fetch(
            'SELECT otp_sent_count, otp_sent_window_start FROM users WHERE id = :id LIMIT 1',
            ['id' => $userId]
        );

        $now        = new DateTimeImmutable();
        $sentCount  = (int) ($user['otp_sent_count'] ?? 0);
        $windowStart = !empty($user['otp_sent_window_start'])
            ? new DateTimeImmutable((string) $user['otp_sent_window_start'])
            : null;

        if ($windowStart !== null && ($now->getTimestamp() - $windowStart->getTimestamp()) < 3600) {
            if ($sentCount >= self::OTP_MAX_SENDS) {
                $this->error('Too many OTP requests. Please wait an hour and try again.', 429);
            }
            $newSentCount   = $sentCount + 1;
            $newWindowStart = $windowStart;
        } else {
            $newSentCount   = 1;
            $newWindowStart = $now;
        }

        $code    = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = $now->modify('+' . self::OTP_TTL_MINUTES . ' minutes');

        $this->app->database()->execute(
            'UPDATE users SET otp_code = :code, otp_expires_at = :exp, otp_attempts = 0,
                otp_sent_count = :cnt, otp_sent_window_start = :win
             WHERE id = :id',
            [
                'code' => $code,
                'exp'  => $expires->format('Y-m-d H:i:s'),
                'cnt'  => $newSentCount,
                'win'  => $newWindowStart->format('Y-m-d H:i:s'),
                'id'   => $userId,
            ]
        );

        $mailConfig = (array) $this->app->config('app.mail', []);
        $mailer     = new Mailer($mailConfig);

        if ($mailer->isEnabled()) {
            $html = EmailTemplate::otp(
                $firstName,
                $code,
                Branding::appName(),
                Branding::companyLogoUrlForUser($userId, $email)
            );
            try {
                $mailer->send($email, 'Your Login Code', $html);
            } catch (Throwable $e) {
                error_log('[API OTP Mail Error] ' . $e->getMessage());
            }
        }
    }

    private function jwtSecret(): string
    {
        $secret = (string) config('app.jwt.secret', '');
        if ($secret === '') {
            $this->error('JWT authentication is not configured on this server.', 500);
        }
        return $secret;
    }

    /** Parse raw request body as JSON or fall back to form POST fields. */
    private function jsonBody(Request $request): array
    {
        $contentType = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
        if (str_contains($contentType, 'application/json')) {
            $raw = (string) file_get_contents('php://input');
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }
        // Fall back to standard POST (useful for testing with curl -d)
        $result = [];
        foreach (['login', 'password', 'pending_token', 'otp', 'device_label', 'refresh_token'] as $key) {
            $val = $request->input($key);
            if ($val !== null) {
                $result[$key] = $val;
            }
        }
        return $result;
    }
}
