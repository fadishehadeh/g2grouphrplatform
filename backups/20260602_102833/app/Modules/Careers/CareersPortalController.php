<?php

declare(strict_types=1);

namespace App\Modules\Careers;

use App\Core\Application;
use App\Core\Controller;
use App\Core\Request;
use App\Support\CareersAuth;
use App\Support\CareersDatabase;
use Throwable;

final class CareersPortalController extends Controller
{
    private CareersRepository $repo;
    private CareersAuth $careersAuth;

    public function __construct(Application $app)
    {
        parent::__construct($app);
        $this->repo        = new CareersRepository(CareersDatabase::get());
        $this->careersAuth = new CareersAuth($app->session());
    }

    // ------------------------------------------------------------------ //
    //  Public job board (no login required)
    // ------------------------------------------------------------------ //

    public function board(Request $request): void
    {
        $filters = [
            'q'                => trim((string) $request->input('q', '')),
            'category'         => trim((string) $request->input('category', '')),
            'job_type'         => trim((string) $request->input('job_type', '')),
            'experience_level' => trim((string) $request->input('experience_level', '')),
            'country'          => trim((string) $request->input('country', '')),
        ];

        $jobs       = $this->repo->listOpenJobs($filters);
        $categories = $this->repo->listCategories();

        $this->render('careers.jobs.index', [
            'title'      => 'Job Openings',
            'jobs'       => $jobs,
            'categories' => $categories,
            'filters'    => $filters,
            'seeker'     => $this->careersAuth->user(),
        ], 'careers');
    }

    public function showJob(Request $request, string $slug): void
    {
        $job = $this->repo->findJobBySlug($slug);

        if ($job === null || $job['status'] !== 'open') {
            $this->app->session()->flash('error', 'Job not found or no longer available.');
            $this->redirect('/careers');
        }

        $this->repo->incrementJobViews((int) $job['id']);

        $hasApplied = false;
        if ($this->careersAuth->check()) {
            $hasApplied = $this->repo->hasApplied($this->careersAuth->id(), (int) $job['id']);
        }

        $this->render('careers.jobs.show', [
            'title'      => $job['title'] . ' — Careers',
            'job'        => $job,
            'hasApplied' => $hasApplied,
            'seeker'     => $this->careersAuth->user(),
        ], 'careers');
    }

    // ------------------------------------------------------------------ //
    //  Dashboard (authenticated)
    // ------------------------------------------------------------------ //

    public function dashboard(Request $request): void
    {
        $seekerId    = $this->requireSeeker();
        $profile     = $this->repo->getProfile($seekerId) ?? [];
        $sections    = $this->repo->getAllSections($seekerId);
        $score       = $this->repo->computeCompleteness($profile, $sections);
        $applications = $this->repo->getSeekerApplications($seekerId);
        $featuredJobs = $this->repo->listOpenJobs(['q' => '']);

        // Limit featured to 6
        $featuredJobs = array_slice($featuredJobs, 0, 6);

        $this->render('careers.dashboard', [
            'title'        => 'My Dashboard',
            'profile'      => $profile,
            'sections'     => $sections,
            'score'        => $score,
            'applications' => $applications,
            'featuredJobs' => $featuredJobs,
            'seeker'       => $this->careersAuth->user(),
        ], 'careers');
    }

    // ------------------------------------------------------------------ //
    //  Apply to specific job
    // ------------------------------------------------------------------ //

    public function showApply(Request $request, string $jobId): void
    {
        $seekerId = $this->requireSeeker();
        $job      = $this->repo->findJob((int) $jobId);

        if ($job === null || $job['status'] !== 'open') {
            $this->app->session()->flash('error', 'Job not found or no longer accepting applications.');
            $this->redirect('/careers');
        }

        if ($this->repo->hasApplied($seekerId, (int) $jobId)) {
            $this->app->session()->flash('info', 'You have already applied to this job.');
            $this->redirect('/careers/my-applications');
        }

        $profile  = $this->repo->getProfile($seekerId) ?? [];
        $sections = $this->repo->getAllSections($seekerId);

        $this->render('careers.jobs.apply', [
            'title'    => 'Apply — ' . $job['title'],
            'job'      => $job,
            'profile'  => $profile,
            'sections' => $sections,
            'seeker'   => $this->careersAuth->user(),
        ], 'careers');
    }

    public function submitApply(Request $request, string $jobId): void
    {
        $seekerId = $this->requireSeeker();

        if (!$this->app->csrf()->validate((string) ($_POST['_token'] ?? ''))) {
            $this->app->session()->flash('error', 'Invalid form token.');
            $this->redirect('/careers/apply/' . $jobId);
        }

        $job = $this->repo->findJob((int) $jobId);

        if ($job === null || $job['status'] !== 'open') {
            $this->app->session()->flash('error', 'Job not found or closed.');
            $this->redirect('/careers');
        }

        if ($this->repo->hasApplied($seekerId, (int) $jobId)) {
            $this->app->session()->flash('info', 'You have already applied.');
            $this->redirect('/careers/my-applications');
        }

        $coverLetter = trim((string) $request->input('cover_letter', ''));

        try {
            $this->repo->createApplication($seekerId, (int) $jobId, $coverLetter);
            $this->app->session()->flash('success', 'Application submitted successfully!');
            $this->redirect('/careers/my-applications');
        } catch (Throwable $e) {
            $this->app->session()->flash('error', 'Could not submit application: ' . $e->getMessage());
            $this->redirect('/careers/apply/' . $jobId);
        }
    }

    // ------------------------------------------------------------------ //
    //  General job bank submission
    // ------------------------------------------------------------------ //

    public function submitToJobBank(Request $request): void
    {
        $seekerId = $this->requireSeeker();

        if (!$this->app->csrf()->validate((string) ($_POST['_token'] ?? ''))) {
            $this->app->session()->flash('error', 'Invalid form token.');
            $this->redirect('/careers/dashboard');
        }

        if ($this->repo->hasApplied($seekerId, null)) {
            $this->app->session()->flash('info', 'Your profile is already in our job bank.');
            $this->redirect('/careers/dashboard');
        }

        $coverLetter = trim((string) $request->input('cover_letter', ''));

        try {
            $this->repo->createApplication($seekerId, null, $coverLetter);
            $this->app->session()->flash('success', 'Your profile has been added to our job bank!');
        } catch (Throwable $e) {
            $this->app->session()->flash('error', 'Could not submit to job bank.');
        }

        $this->redirect('/careers/dashboard');
    }

    // ------------------------------------------------------------------ //
    //  My applications
    // ------------------------------------------------------------------ //

    public function myApplications(Request $request): void
    {
        $seekerId     = $this->requireSeeker();
        $applications = $this->repo->getSeekerApplications($seekerId);

        $this->render('careers.my-applications', [
            'title'        => 'My Applications',
            'applications' => $applications,
            'seeker'       => $this->careersAuth->user(),
        ], 'careers');
    }

    public function withdrawApplication(Request $request, string $id): void
    {
        $seekerId = $this->requireSeeker();

        if (!$this->app->csrf()->validate((string) ($_POST['_token'] ?? ''))) {
            $this->redirect('/careers/my-applications');
        }

        try {
            $this->repo->withdrawApplication((int) $id, $seekerId);
            $this->app->session()->flash('success', 'Application withdrawn.');
        } catch (Throwable $e) {
            $this->app->session()->flash('error', 'Could not withdraw application.');
        }

        $this->redirect('/careers/my-applications');
    }

    // ------------------------------------------------------------------ //
    //  Helper
    // ------------------------------------------------------------------ //

    private function requireSeeker(): int
    {
        $id = $this->careersAuth->id();
        if ($id === null) {
            $this->app->session()->flash('error', 'Please log in to continue.');
            $this->redirect('/careers/login');
        }
        return $id;
    }
}
