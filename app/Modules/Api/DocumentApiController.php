<?php

declare(strict_types=1);

namespace App\Modules\Api;

use App\Core\Request;
use App\Modules\Documents\DocumentRepository;
use Throwable;

final class DocumentApiController extends ApiController
{
    private DocumentRepository $repository;

    public function __construct(\App\Core\Application $app)
    {
        parent::__construct($app);
        $this->repository = new DocumentRepository($this->app->database());
    }

    /**
     * GET /api/v1/documents/my
     * Returns the authenticated employee's own documents (read-only, employee visibility scope).
     */
    public function my(Request $request): never
    {
        $user       = $this->app->auth()->user() ?? [];
        $employeeId = (int) ($user['employee_id'] ?? 0);
        $roleCode   = (string) ($user['role_code'] ?? 'employee');

        if ($employeeId <= 0) {
            $this->error('No employee record linked to this user account.', 422);
        }

        try {
            $documents = $this->repository->employeeDocuments($employeeId, $roleCode);
        } catch (Throwable $e) {
            $this->error('Failed to retrieve documents: ' . $e->getMessage(), 500);
        }

        // Strip file paths before exposing to API clients
        $safe = array_map(static function (array $doc): array {
            unset($doc['stored_file_name'], $doc['file_path']);
            return $doc;
        }, $documents);

        $this->success($safe);
    }
}
