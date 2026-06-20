<?php

declare(strict_types=1);

namespace App\Modules\Api;

use App\Core\Request;
use App\Modules\Letters\LetterRepository;
use Throwable;

final class LetterApiController extends ApiController
{
    private LetterRepository $repository;

    private const VALID_TYPES = [
        'salary_certificate',
        'employment_certificate',
        'experience_letter',
        'noc',
        'bank_letter',
    ];

    public function __construct(\App\Core\Application $app)
    {
        parent::__construct($app);
        $this->repository = new LetterRepository($this->app->database());
    }

    /**
     * GET /api/v1/letters/my
     * Returns the authenticated employee's own letter requests.
     */
    public function my(Request $request): never
    {
        $user       = $this->app->auth()->user() ?? [];
        $employeeId = (int) ($user['employee_id'] ?? 0);

        if ($employeeId <= 0) {
            $this->error('No employee record linked to this user account.', 422);
        }

        try {
            $letters = $this->repository->myRequests($employeeId);
        } catch (Throwable $e) {
            $this->error('Failed to retrieve letter requests: ' . $e->getMessage(), 500);
        }

        $this->success($letters);
    }

    /**
     * POST /api/v1/letters/request
     * Submit a new letter request.
     *
     * Required JSON: { "letter_type": "salary_certificate|..." }
     * Optional JSON: { "purpose": "...", "notes": "..." }
     */
    public function request(Request $request): never
    {
        $user       = $this->app->auth()->user() ?? [];
        $employeeId = (int) ($user['employee_id'] ?? 0);
        $userId     = (int) ($user['id'] ?? 0);

        if ($employeeId <= 0) {
            $this->error('No employee record linked to this user account.', 422);
        }

        $body       = $this->jsonBody($request);
        $letterType = trim((string) ($body['letter_type'] ?? ''));
        $purpose    = trim((string) ($body['purpose'] ?? ''));
        $notes      = trim((string) ($body['notes'] ?? ''));

        if (!in_array($letterType, self::VALID_TYPES, true)) {
            $this->error('Invalid letter_type. Valid types: ' . implode(', ', self::VALID_TYPES), 422);
        }

        try {
            $requestId = $this->repository->createRequest(
                [
                    'letter_type' => $letterType,
                    'purpose'     => $purpose !== '' ? $purpose : null,
                    'notes'       => $notes !== '' ? $notes : null,
                ],
                $employeeId,
                $userId
            );
        } catch (Throwable $e) {
            $this->error('Failed to submit letter request: ' . $e->getMessage(), 500);
        }

        $this->success(['id' => $requestId, 'message' => 'Letter request submitted. HR will process it shortly.'], 201);
    }

    /**
     * GET /api/v1/letters/{id}/download
     * Returns the letter content for an approved, generated letter.
     * Employees can only access their own letters.
     */
    public function download(Request $request, string $id): never
    {
        $user       = $this->app->auth()->user() ?? [];
        $employeeId = (int) ($user['employee_id'] ?? 0);
        $letterId   = (int) $id;

        try {
            $letter = $this->repository->findRequest($letterId);
        } catch (Throwable $e) {
            $this->error('Failed to load letter: ' . $e->getMessage(), 500);
        }

        if ($letter === null) {
            $this->notFound('Letter not found.');
        }

        // Permission: HR/admins can access any letter; employees only their own
        if (!$this->app->auth()->hasPermission('letters.manage')) {
            if ((int) ($letter['employee_id'] ?? 0) !== $employeeId) {
                $this->forbidden('Access denied.');
            }
        }

        if ((string) ($letter['status'] ?? '') !== 'approved' || empty($letter['letter_content'])) {
            $this->error('This letter has not been generated yet.', 409);
        }

        $this->success([
            'id'             => $letterId,
            'letter_type'    => $letter['letter_type'] ?? null,
            'letter_content' => $letter['letter_content'],
            'generated_at'   => $letter['generated_at'] ?? null,
        ]);
    }

    private function jsonBody(Request $request): array
    {
        $contentType = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
        if (str_contains($contentType, 'application/json')) {
            $raw     = (string) file_get_contents('php://input');
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }
        $result = [];
        foreach (['letter_type', 'purpose', 'notes'] as $key) {
            $val = $request->input($key);
            if ($val !== null) {
                $result[$key] = $val;
            }
        }
        return $result;
    }
}
