<?php

declare(strict_types=1);

namespace App\Modules\Jobs;

use App\Core\Application;
use App\Core\Controller;
use App\Core\Request;
use App\Support\CareersDatabase;
use Throwable;

final class JobApplicantsController extends Controller
{
    private JobsRepository $repo;

    public function __construct(Application $app)
    {
        parent::__construct($app);
        $this->repo = new JobsRepository(CareersDatabase::get());
    }

    // ------------------------------------------------------------------ //
    //  All applicants with full filters (also job bank)
    // ------------------------------------------------------------------ //

    public function index(Request $request): void
    {
        $filters = $this->extractFilters($request);
        $applications = $this->repo->listApplications($filters);
        $categories   = $this->repo->allCategories();
        $jobs         = $this->repo->listJobs([]);

        $this->render('jobs.applicants.index', [
            'title'        => 'All Applicants',
            'applications' => $applications,
            'categories'   => $categories,
            'jobs'         => $jobs,
            'filters'      => $filters,
        ]);
    }

    public function jobBank(Request $request): void
    {
        $filters             = $this->extractFilters($request);
        $filters['job_id']   = 'bank';
        $applications = $this->repo->listApplications($filters);

        $this->render('jobs.applicants.index', [
            'title'        => 'Job Bank — General Submissions',
            'applications' => $applications,
            'categories'   => $this->repo->allCategories(),
            'jobs'         => [],
            'filters'      => $filters,
            'isBankView'   => true,
        ]);
    }

    // ------------------------------------------------------------------ //
    //  Single applicant full CV view
    // ------------------------------------------------------------------ //

    public function show(Request $request, string $id): void
    {
        $application = $this->repo->findApplication((int) $id);

        if ($application === null) {
            $this->app->session()->flash('error', 'Application not found.');
            $this->redirect('/admin/jobs/applicants');
        }

        $sections = $this->repo->getApplicantSections((int) $application['job_seeker_id']);
        $history  = $this->repo->getStatusHistory((int) $id);

        // Decode JSON fields
        if (is_string($application['employment_type_preference'])) {
            $application['employment_type_preference'] = json_decode($application['employment_type_preference'], true) ?? [];
        }
        foreach ($sections as $type => $items) {
            foreach ($items as &$item) {
                if (is_string($item['data'])) {
                    $item['data'] = json_decode($item['data'], true) ?? [];
                }
            }
            $sections[$type] = $items;
        }

        $this->render('jobs.applicants.show', [
            'title'       => 'Applicant: ' . ($application['first_name'] ?? '') . ' ' . ($application['last_name'] ?? ''),
            'application' => $application,
            'sections'    => $sections,
            'history'     => $history,
        ]);
    }

    // ------------------------------------------------------------------ //
    //  Update status + rating + notes
    // ------------------------------------------------------------------ //

    public function updateStatus(Request $request, string $id): void
    {
        $this->validateCsrf('/admin/jobs/applicants/' . $id);

        $status  = trim((string) $request->input('status', ''));
        $notes   = trim((string) $request->input('hr_notes', ''));
        $rating  = $request->input('hr_rating') !== '' ? (int) $request->input('hr_rating') : null;
        $allowed = ['new', 'reviewing', 'shortlisted', 'interviewed', 'offered', 'rejected', 'hired', 'withdrawn'];

        if (!in_array($status, $allowed, true)) {
            $this->app->session()->flash('error', 'Invalid status value.');
            $this->redirect('/admin/jobs/applicants/' . $id);
        }

        try {
            $this->repo->updateApplicationStatus(
                (int) $id,
                $status,
                (int) $this->app->auth()->id(),
                $notes,
                $rating
            );
            $this->app->session()->flash('success', 'Application updated.');
        } catch (Throwable $e) {
            $this->app->session()->flash('error', 'Could not update: ' . $e->getMessage());
        }

        $this->redirect('/admin/jobs/applicants/' . $id);
    }

    // ------------------------------------------------------------------ //
    //  Download CV file
    // ------------------------------------------------------------------ //

    public function downloadCv(Request $request, string $id): void
    {
        $application = $this->repo->findApplication((int) $id);

        if ($application === null || empty($application['cv_file_path'])) {
            $this->app->session()->flash('error', 'CV file not found.');
            $this->redirect('/admin/jobs/applicants/' . $id);
        }

        $filePath = base_path((string) $application['cv_file_path']);

        if (!is_file($filePath)) {
            $this->app->session()->flash('error', 'CV file missing from storage.');
            $this->redirect('/admin/jobs/applicants/' . $id);
        }

        $originalName = $application['cv_original_name'] ?? basename($filePath);
        $mime = mime_content_type($filePath) ?: 'application/octet-stream';

        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . addslashes($originalName) . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: no-cache');
        readfile($filePath);
        exit;
    }

    // ------------------------------------------------------------------ //
    //  Export filtered applicants to Excel (CSV-based .xls)
    // ------------------------------------------------------------------ //

    public function export(Request $request): void
    {
        $filters      = $this->extractFilters($request);
        $applications = $this->repo->listApplications($filters);

        $filename = 'applicants_' . date('Ymd_His') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache');

        $out = fopen('php://output', 'w');
        // UTF-8 BOM for Excel
        fwrite($out, "\xEF\xBB\xBF");

        fputcsv($out, [
            'Application ID', 'Name', 'Email', 'Username', 'Job Title Applied',
            'Current Title', 'Nationality', 'Country', 'Years Exp',
            'Expected Salary', 'Currency', 'Willing to Relocate',
            'Status', 'HR Rating', 'Submitted At', 'Reviewed At',
        ]);

        foreach ($applications as $app) {
            fputcsv($out, [
                $app['id'],
                trim(($app['first_name'] ?? '') . ' ' . ($app['last_name'] ?? '')),
                $app['email'],
                $app['username'],
                $app['job_title'] ?? 'Job Bank',
                $app['current_job_title'] ?? '',
                $app['nationality'] ?? '',
                $app['country'] ?? '',
                $app['years_of_experience'] ?? '',
                $app['expected_salary'] ?? '',
                $app['salary_currency'] ?? '',
                ($app['willing_to_relocate'] ?? 0) ? 'Yes' : 'No',
                $app['status'],
                $app['hr_rating'] ?? '',
                $app['submitted_at'],
                $app['reviewed_at'] ?? '',
            ]);
        }

        fclose($out);
        exit;
    }

    // ------------------------------------------------------------------ //
    //  Helpers
    // ------------------------------------------------------------------ //

    private function extractFilters(Request $request): array
    {
        return [
            'q'                => trim((string) $request->input('q', '')),
            'job_id'           => trim((string) $request->input('job_id', '')),
            'status'           => trim((string) $request->input('status', '')),
            'rating'           => trim((string) $request->input('rating', '')),
            'nationality'      => trim((string) $request->input('nationality', '')),
            'country'          => trim((string) $request->input('country', '')),
            'relocate'         => trim((string) $request->input('relocate', '')),
            'min_exp'          => trim((string) $request->input('min_exp', '')),
            'max_exp'          => trim((string) $request->input('max_exp', '')),
            'date_from'        => trim((string) $request->input('date_from', '')),
            'date_to'          => trim((string) $request->input('date_to', '')),
            'category_id'      => trim((string) $request->input('category_id', '')),
            'job_type'         => trim((string) $request->input('job_type', '')),
            'experience_level' => trim((string) $request->input('experience_level', '')),
        ];
    }

    private function validateCsrf(string $redirect): void
    {
        $token = $_POST['_token'] ?? '';
        if (!$this->app->csrf()->validate($token)) {
            $this->app->session()->flash('error', 'Invalid form token.');
            $this->redirect($redirect);
        }
    }
}
