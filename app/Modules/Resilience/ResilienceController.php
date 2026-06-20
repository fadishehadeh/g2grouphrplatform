<?php

declare(strict_types=1);

namespace App\Modules\Resilience;

use App\Core\Application;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use Throwable;

final class ResilienceController extends Controller
{
    private BackupService $service;
    private BackupRepository $repository;

    public function __construct(Application $app)
    {
        parent::__construct($app);
        $this->service = new BackupService($app);
        $this->repository = $this->service->repository();
    }

    public function index(Request $request): void
    {
        try {
            $runs = $this->repository->listRecentRuns(20);
            $latestRun = $runs[0] ?? null;
        } catch (Throwable $throwable) {
            $runs = [];
            $latestRun = null;
            $this->app->session()->flash('error', 'Unable to load resilience console: ' . $throwable->getMessage());
        }

        $this->render('resilience.index', [
            'title' => 'Resilience Console',
            'pageTitle' => 'Resilience Console',
            'runs' => $runs,
            'latestRun' => $latestRun,
        ]);
    }

    public function runNow(Request $request): void
    {
        $this->validateCsrf($request, '/admin/resilience');

        try {
            $result = $this->service->runDailyBackup($this->app->auth()->id(), 'manual');
            $run = is_array($result['run'] ?? null) ? $result['run'] : null;
            $runId = (int) ($run['id'] ?? 0);
            $status = (string) ($run['status'] ?? 'unknown');

            $this->app->session()->flash(
                'success',
                'Manual backup run #' . $runId . ' completed with status: ' . ucfirst($status) . '.'
            );
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to run backup now: ' . $throwable->getMessage());
        }

        $this->redirect('/admin/resilience');
    }

    public function generateLinks(Request $request, string $runId): void
    {
        $numericRunId = (int) $runId;
        $this->validateCsrf($request, '/admin/resilience');

        try {
            $run = $this->repository->findRun($numericRunId);
            if ($run === null) {
                Response::abort(404, 'Backup run not found.');
            }

            $tokens = $this->service->generateLinksForRun($run, $this->app->auth()->id(), (string) $request->ip());
            if ($tokens === []) {
                $this->app->session()->flash('error', 'No successful backup artifacts were available for fresh download links.');
            } else {
                $this->app->session()->flash('success', 'Fresh secure backup links were generated for run #' . $numericRunId . '.');
            }
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to generate new download links: ' . $throwable->getMessage());
        }

        $this->redirect('/admin/resilience');
    }

    public function download(Request $request, string $token): void
    {
        if (strlen($token) !== 64 || !ctype_xdigit($token)) {
            Response::abort(404, 'Invalid or expired backup download link.');
        }

        try {
            $tokenRow = $this->repository->findValidDownloadToken($token);
            if ($tokenRow === null) {
                Response::abort(410, 'This backup download link has expired or is invalid.');
            }

            if (($tokenRow['artifact_status'] ?? '') !== 'success' || !empty($tokenRow['deleted_at'])) {
                Response::abort(410, 'This backup artifact is no longer available.');
            }

            $relativePath = (string) ($tokenRow['relative_path'] ?? '');
            if ($relativePath === '') {
                Response::abort(404, 'Backup file path is missing.');
            }

            $absolutePath = base_path($relativePath);
            if (!is_file($absolutePath) || !is_readable($absolutePath)) {
                Response::abort(404, 'Backup file not found on the server.');
            }

            $this->repository->markTokenDownloaded((int) $tokenRow['token_id']);

            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            $fileName = (string) ($tokenRow['file_name'] ?? basename($absolutePath));
            $mimeType = str_ends_with(strtolower($fileName), '.zip') ? 'application/zip' : 'application/sql';
            $size = filesize($absolutePath) ?: 0;

            header('Content-Type: ' . $mimeType);
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Content-Disposition: attachment; filename="' . $this->safeDownloadName($fileName) . '"; filename*=UTF-8\'\'' . rawurlencode($fileName));
            if ($size > 0) {
                header('Content-Length: ' . (string) $size);
            }

            readfile($absolutePath);
            exit;
        } catch (Throwable $throwable) {
            Response::abort(500, 'Unable to serve backup download: ' . $throwable->getMessage());
        }
    }

    private function validateCsrf(Request $request, string $redirectPath): void
    {
        if (!$this->app->csrf()->validate((string) $request->input('_token'))) {
            $this->app->session()->flash('error', 'Your session expired. Please try again.');
            $this->redirect($redirectPath);
        }
    }

    private function safeDownloadName(string $name): string
    {
        $clean = preg_replace('/[^\w.\-]+/u', '_', $name) ?? 'backup_file';
        return trim($clean, '_') !== '' ? trim($clean, '_') : 'backup_file';
    }
}
