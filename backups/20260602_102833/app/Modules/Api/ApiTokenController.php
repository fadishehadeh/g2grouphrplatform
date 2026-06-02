<?php

declare(strict_types=1);

namespace App\Modules\Api;

use App\Core\Application;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use Throwable;

/**
 * Web controller (not API) — lets authenticated users manage their own API tokens
 * at /profile/api-tokens.
 */
final class ApiTokenController extends Controller
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function index(Request $request): void
    {
        $userId = $this->app->auth()->id();
        $tokens = [];

        try {
            $tokens = $this->app->database()->fetchAll(
                'SELECT id, name, last_used_at, expires_at, is_active, created_at
                 FROM api_tokens WHERE user_id = :user_id ORDER BY created_at DESC',
                ['user_id' => $userId]
            );
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load API tokens: ' . $throwable->getMessage());
        }

        $this->render('api.tokens', [
            'title'      => 'API Tokens',
            'pageTitle'  => 'API Tokens',
            'tokens'     => $tokens,
        ]);
    }

    public function store(Request $request): void
    {
        if (!$this->app->csrf()->validate((string) $request->input('_token'))) {
            $this->app->session()->flash('error', 'Invalid form token.');
            $this->redirect('/profile/api-tokens');
        }

        $name = trim((string) $request->input('name', ''));

        if ($name === '') {
            $this->app->session()->flash('error', 'Please provide a name for the token.');
            $this->redirect('/profile/api-tokens');
        }

        $expiresAt = null;
        $expiryDays = (int) $request->input('expires_days', 0);
        if ($expiryDays > 0) {
            $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiryDays} days"));
        }

        // Generate a 40-byte (80 hex char) cryptographically secure raw token
        $rawToken  = bin2hex(random_bytes(40));
        $tokenHash = hash('sha256', $rawToken);

        try {
            $this->app->database()->execute(
                'INSERT INTO api_tokens (user_id, token_hash, name, expires_at)
                 VALUES (:user_id, :token_hash, :name, :expires_at)',
                [
                    'user_id'    => $this->app->auth()->id(),
                    'token_hash' => $tokenHash,
                    'name'       => substr($name, 0, 100),
                    'expires_at' => $expiresAt,
                ]
            );

            // Show the raw token ONCE — it is never stored in plaintext
            $this->app->session()->flash('new_api_token', $rawToken);
            $this->app->session()->flash('success', 'API token created. Copy it now — it will not be shown again.');
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to create token: ' . $throwable->getMessage());
        }

        $this->redirect('/profile/api-tokens');
    }

    public function revoke(Request $request, string $id): void
    {
        if (!$this->app->csrf()->validate((string) $request->input('_token'))) {
            $this->app->session()->flash('error', 'Invalid form token.');
            $this->redirect('/profile/api-tokens');
        }

        try {
            $this->app->database()->execute(
                'UPDATE api_tokens SET is_active = 0 WHERE id = :id AND user_id = :user_id',
                ['id' => (int) $id, 'user_id' => $this->app->auth()->id()]
            );
            $this->app->session()->flash('success', 'Token revoked.');
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to revoke token: ' . $throwable->getMessage());
        }

        $this->redirect('/profile/api-tokens');
    }
}
