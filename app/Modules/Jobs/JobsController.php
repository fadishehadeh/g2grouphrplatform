<?php

declare(strict_types=1);

namespace App\Modules\Jobs;

use App\Core\Application;
use App\Core\Controller;
use App\Core\Request;
use App\Support\CareersDatabase;
use Throwable;

final class JobsController extends Controller
{
    private JobsRepository $repo;

    public function __construct(Application $app)
    {
        parent::__construct($app);
        $this->repo = new JobsRepository(CareersDatabase::get());
    }

    // ------------------------------------------------------------------ //
    //  Dashboard / Overview
    // ------------------------------------------------------------------ //

    public function index(Request $request): void
    {
        $filters = [
            'q'           => trim((string) $request->input('q', '')),
            'status'      => trim((string) $request->input('status', '')),
            'category_id' => trim((string) $request->input('category_id', '')),
            'job_type'    => trim((string) $request->input('job_type', '')),
        ];

        $jobs       = $this->repo->listJobs($filters);
        $categories = $this->repo->allCategories();
        $counts     = $this->repo->summaryCounts();

        $this->render('jobs.index', [
            'title'      => 'Jobs Management',
            'jobs'       => $jobs,
            'categories' => $categories,
            'filters'    => $filters,
            'counts'     => $counts,
        ]);
    }

    // ------------------------------------------------------------------ //
    //  Create / Edit form
    // ------------------------------------------------------------------ //

    public function create(Request $request): void
    {
        $this->render('jobs.form', [
            'title'      => 'Post a New Job',
            'job'        => null,
            'categories' => $this->repo->allCategories(),
        ]);
    }

    public function store(Request $request): void
    {
        $this->validateCsrf('/admin/jobs/create');
        $data = $this->buildJobData($request);

        if ($this->repo->slugExists($data['slug'])) {
            $data['slug'] = $data['slug'] . '-' . substr(bin2hex(random_bytes(3)), 0, 5);
        }

        try {
            $id = $this->repo->createJob($data);
            $this->app->session()->flash('success', 'Job posted successfully.');
            $this->redirect('/admin/jobs/' . $id);
        } catch (Throwable $e) {
            $this->app->session()->flash('error', 'Could not create job: ' . $e->getMessage());
            $this->app->session()->flash('old_input', $data);
            $this->redirect('/admin/jobs/create');
        }
    }

    public function edit(Request $request, string $id): void
    {
        $job = $this->findOrFail((int) $id);

        if (is_string($job['skills_required'])) {
            $job['skills_required'] = json_decode($job['skills_required'], true) ?? [];
        }

        $this->render('jobs.form', [
            'title'      => 'Edit Job',
            'job'        => $job,
            'categories' => $this->repo->allCategories(),
        ]);
    }

    public function update(Request $request, string $id): void
    {
        $jobId = (int) $id;
        $this->validateCsrf('/admin/jobs/' . $jobId . '/edit');

        $data = $this->buildJobData($request);

        if ($this->repo->slugExists($data['slug'], $jobId)) {
            $data['slug'] = $data['slug'] . '-' . substr(bin2hex(random_bytes(3)), 0, 5);
        }

        try {
            $this->repo->updateJob($jobId, $data);
            $this->app->session()->flash('success', 'Job updated.');
        } catch (Throwable $e) {
            $this->app->session()->flash('error', 'Could not update job: ' . $e->getMessage());
        }

        $this->redirect('/admin/jobs/' . $jobId);
    }

    public function show(Request $request, string $id): void
    {
        $job          = $this->findOrFail((int) $id);
        $applications = $this->repo->listApplications(['job_id' => (string) $id]);

        $this->render('jobs.show', [
            'title'        => $job['title'],
            'job'          => $job,
            'applications' => $applications,
        ]);
    }

    public function updateStatus(Request $request, string $id): void
    {
        $this->validateCsrf('/admin/jobs/' . $id . '/status');
        $status   = trim((string) $request->input('status', ''));
        $allowed  = ['draft', 'open', 'closed', 'paused'];

        if (!in_array($status, $allowed, true)) {
            $this->app->session()->flash('error', 'Invalid status.');
            $this->redirect('/admin/jobs/' . $id);
        }

        try {
            $this->repo->updateJobStatus((int) $id, $status);
            $this->app->session()->flash('success', 'Job status updated to ' . $status . '.');
        } catch (Throwable $e) {
            $this->app->session()->flash('error', 'Could not update status.');
        }

        $this->redirect('/admin/jobs/' . $id);
    }

    public function destroy(Request $request, string $id): void
    {
        $this->validateCsrf('/admin/jobs/' . $id . '/delete');

        try {
            $this->repo->deleteJob((int) $id);
            $this->app->session()->flash('success', 'Job deleted.');
        } catch (Throwable $e) {
            $this->app->session()->flash('error', 'Could not delete job.');
        }

        $this->redirect('/admin/jobs');
    }

    // ------------------------------------------------------------------ //
    //  Job Categories
    // ------------------------------------------------------------------ //

    public function categories(Request $request): void
    {
        $this->render('jobs.categories', [
            'title'      => 'Job Categories',
            'categories' => $this->repo->allCategories(),
        ]);
    }

    public function storeCategory(Request $request): void
    {
        $this->validateCsrf('/admin/jobs/categories');

        $name  = trim((string) $request->input('name', ''));
        $slug  = $this->makeSlug(trim((string) $request->input('slug', $name)));
        $icon  = trim((string) $request->input('icon', 'bi-briefcase'));
        $sort  = (int) $request->input('sort_order', 0);
        $desc  = trim((string) $request->input('description', ''));

        if ($name === '') {
            $this->app->session()->flash('error', 'Category name is required.');
            $this->redirect('/admin/jobs/categories');
        }

        try {
            $this->repo->createCategory([
                'name'        => $name,
                'slug'        => $slug,
                'description' => $desc ?: null,
                'icon'        => $icon ?: 'bi-briefcase',
                'is_active'   => 1,
                'sort_order'  => $sort,
            ]);
            $this->app->session()->flash('success', 'Category added.');
        } catch (Throwable $e) {
            $this->app->session()->flash('error', 'Could not create category: ' . $e->getMessage());
        }

        $this->redirect('/admin/jobs/categories');
    }

    public function updateCategory(Request $request, string $id): void
    {
        $this->validateCsrf('/admin/jobs/categories/' . $id . '/edit');

        $name  = trim((string) $request->input('name', ''));
        $slug  = $this->makeSlug(trim((string) $request->input('slug', $name)));
        $icon  = trim((string) $request->input('icon', 'bi-briefcase'));
        $sort  = (int) $request->input('sort_order', 0);
        $desc  = trim((string) $request->input('description', ''));
        $active = $request->input('is_active') ? 1 : 0;

        try {
            $this->repo->updateCategory((int) $id, [
                'name'        => $name,
                'slug'        => $slug,
                'description' => $desc ?: null,
                'icon'        => $icon ?: 'bi-briefcase',
                'is_active'   => $active,
                'sort_order'  => $sort,
            ]);
            $this->app->session()->flash('success', 'Category updated.');
        } catch (Throwable $e) {
            $this->app->session()->flash('error', 'Could not update category.');
        }

        $this->redirect('/admin/jobs/categories');
    }

    public function destroyCategory(Request $request, string $id): void
    {
        $this->validateCsrf('/admin/jobs/categories/' . $id . '/delete');

        try {
            $this->repo->deleteCategory((int) $id);
            $this->app->session()->flash('success', 'Category deleted.');
        } catch (Throwable $e) {
            $this->app->session()->flash('error', 'Cannot delete: category may have linked jobs.');
        }

        $this->redirect('/admin/jobs/categories');
    }

    // ------------------------------------------------------------------ //
    //  Helpers
    // ------------------------------------------------------------------ //

    private function findOrFail(int $id): array
    {
        $job = $this->repo->findJob($id);
        if ($job === null) {
            $this->app->session()->flash('error', 'Job not found.');
            $this->redirect('/admin/jobs');
        }
        return $job;
    }

    private function validateCsrf(string $redirect): void
    {
        $token = $_POST['_token'] ?? '';
        if (!$this->app->csrf()->validate($token)) {
            $this->app->session()->flash('error', 'Invalid form token.');
            $this->redirect($redirect);
        }
    }

    private function buildJobData(Request $request): array
    {
        $title  = trim((string) $request->input('title', ''));
        $slug   = $this->makeSlug(trim((string) $request->input('slug', $title)));
        $status = trim((string) $request->input('status', 'draft'));

        $skills = array_filter(array_map('trim', explode(',', (string) $request->input('skills_required', ''))));

        return [
            'job_category_id'       => $request->input('job_category_id') ?: null,
            'title'                 => $title,
            'slug'                  => $slug,
            'company_name'          => trim((string) $request->input('company_name', '')),
            'branch_name'           => trim((string) $request->input('branch_name', '')),
            'department_name'       => trim((string) $request->input('department_name', '')),
            'location_city'         => trim((string) $request->input('location_city', '')),
            'location_country'      => trim((string) $request->input('location_country', '')),
            'job_type'              => trim((string) $request->input('job_type', 'full_time')),
            'experience_level'      => trim((string) $request->input('experience_level', 'mid')),
            'min_experience_years'  => $request->input('min_experience_years') !== '' ? (int) $request->input('min_experience_years') : null,
            'max_experience_years'  => $request->input('max_experience_years') !== '' ? (int) $request->input('max_experience_years') : null,
            'min_salary'            => $request->input('min_salary') !== '' ? (float) $request->input('min_salary') : null,
            'max_salary'            => $request->input('max_salary') !== '' ? (float) $request->input('max_salary') : null,
            'salary_currency'       => trim((string) $request->input('salary_currency', 'USD')),
            'salary_visible'        => $request->input('salary_visible') ? 1 : 0,
            'description'           => trim((string) $request->input('description', '')),
            'requirements'          => trim((string) $request->input('requirements', '')),
            'responsibilities'      => trim((string) $request->input('responsibilities', '')),
            'benefits'              => trim((string) $request->input('benefits', '')),
            'skills_required'       => json_encode(array_values($skills)),
            'education_required'    => trim((string) $request->input('education_required', '')),
            'positions_count'       => max(1, (int) $request->input('positions_count', 1)),
            'deadline'              => trim((string) $request->input('deadline', '')) ?: null,
            'status'                => $status,
            'is_featured'           => $request->input('is_featured') ? 1 : 0,
            'created_by_hr_user_id' => $this->app->auth()->id(),
            'published_at'          => $status === 'open' ? date('Y-m-d H:i:s') : null,
        ];
    }

    private function makeSlug(string $text): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $text), '-'));
        return $slug !== '' ? $slug : 'job-' . time();
    }
}
