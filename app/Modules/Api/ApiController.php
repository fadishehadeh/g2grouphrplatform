<?php

declare(strict_types=1);

namespace App\Modules\Api;

use App\Core\Application;
use App\Core\Request;

/**
 * Base class for all REST API controllers.
 * Sends JSON responses and never uses the HTML view system.
 */
abstract class ApiController
{
    protected Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    protected function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    protected function success(mixed $data, int $status = 200): never
    {
        $this->json(['data' => $data], $status);
    }

    protected function paginated(array $items, int $total, int $page, int $perPage): never
    {
        $this->json([
            'data' => $items,
            'meta' => [
                'total'      => $total,
                'page'       => $page,
                'per_page'   => $perPage,
                'last_page'  => (int) ceil($total / max(1, $perPage)),
            ],
        ]);
    }

    protected function error(string $message, int $status = 400): never
    {
        $this->json(['error' => $message], $status);
    }

    protected function notFound(string $message = 'Resource not found.'): never
    {
        $this->error($message, 404);
    }

    protected function forbidden(string $message = 'Forbidden.'): never
    {
        $this->error($message, 403);
    }

    protected function page(Request $request): int
    {
        return max(1, (int) $request->input('page', 1));
    }

    protected function perPage(Request $request, int $default = 25, int $max = 100): int
    {
        return max(1, min($max, (int) $request->input('per_page', $default)));
    }
}
