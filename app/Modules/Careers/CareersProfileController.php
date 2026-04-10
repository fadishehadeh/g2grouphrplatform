<?php

declare(strict_types=1);

namespace App\Modules\Careers;

use App\Core\Application;
use App\Core\Controller;
use App\Core\Request;
use App\Support\CareersAuth;
use App\Support\CareersDatabase;
use Throwable;

final class CareersProfileController extends Controller
{
    private CareersRepository $repo;
    private CareersAuth $careersAuth;

    private const SECTION_TYPES = [
        'experience', 'education', 'skill', 'language',
        'certification', 'project', 'award', 'volunteer',
        'reference', 'publication',
    ];

    public function __construct(Application $app)
    {
        parent::__construct($app);
        $this->repo        = new CareersRepository(CareersDatabase::get());
        $this->careersAuth = new CareersAuth($app->session());
    }

    // ------------------------------------------------------------------ //
    //  Profile overview (tabbed)
    // ------------------------------------------------------------------ //

    public function index(Request $request): void
    {
        $seekerId = $this->requireSeeker();
        $profile  = $this->repo->getProfile($seekerId) ?? [];
        $sections = $this->repo->getAllSections($seekerId);
        $score    = $this->repo->computeCompleteness($profile, $sections);

        $this->render('careers.profile.index', [
            'title'    => 'My Profile',
            'profile'  => $profile,
            'sections' => $sections,
            'score'    => $score,
            'seeker'   => $this->careersAuth->user(),
        ], 'careers');
    }

    // ------------------------------------------------------------------ //
    //  Personal & contact info
    // ------------------------------------------------------------------ //

    public function showPersonal(Request $request): void
    {
        $seekerId = $this->requireSeeker();
        $profile  = $this->repo->getProfile($seekerId) ?? [];

        $this->render('careers.profile.personal', [
            'title'   => 'Personal Information',
            'profile' => $profile,
            'seeker'  => $this->careersAuth->user(),
        ], 'careers');
    }

    public function savePersonal(Request $request): void
    {
        $seekerId = $this->requireSeeker();
        $this->validateCsrf('/careers/profile/personal');

        $data = [
            'first_name'         => $this->t($request, 'first_name'),
            'last_name'          => $this->t($request, 'last_name'),
            'middle_name'        => $this->t($request, 'middle_name'),
            'date_of_birth'      => $this->t($request, 'date_of_birth') ?: null,
            'gender'             => $this->t($request, 'gender') ?: null,
            'nationality'        => $this->t($request, 'nationality'),
            'second_nationality' => $this->t($request, 'second_nationality'),
            'phone'              => $this->t($request, 'phone'),
            'mobile'             => $this->t($request, 'mobile'),
            'whatsapp_number'    => $this->t($request, 'whatsapp_number'),
            'address_line_1'     => $this->t($request, 'address_line_1'),
            'address_line_2'     => $this->t($request, 'address_line_2'),
            'city'               => $this->t($request, 'city'),
            'state'              => $this->t($request, 'state'),
            'country'            => $this->t($request, 'country'),
            'postal_code'        => $this->t($request, 'postal_code'),
            'linkedin_url'       => $this->t($request, 'linkedin_url'),
            'portfolio_url'      => $this->t($request, 'portfolio_url'),
            'github_url'         => $this->t($request, 'github_url'),
            'website_url'        => $this->t($request, 'website_url'),
        ];

        try {
            $this->repo->updatePersonal($seekerId, $data);
            $this->app->session()->flash('success', 'Personal information updated.');
        } catch (Throwable $e) {
            $this->app->session()->flash('error', 'Could not save: ' . $e->getMessage());
        }

        $this->redirect('/careers/profile/personal');
    }

    // ------------------------------------------------------------------ //
    //  Professional info
    // ------------------------------------------------------------------ //

    public function showProfessional(Request $request): void
    {
        $seekerId = $this->requireSeeker();
        $profile  = $this->repo->getProfile($seekerId) ?? [];

        $this->render('careers.profile.professional', [
            'title'   => 'Professional Information',
            'profile' => $profile,
            'seeker'  => $this->careersAuth->user(),
        ], 'careers');
    }

    public function saveProfessional(Request $request): void
    {
        $seekerId = $this->requireSeeker();
        $this->validateCsrf('/careers/profile/professional');

        $types = (array) $request->input('employment_type_preference', []);
        $allowed = ['full_time', 'part_time', 'contract', 'freelance', 'internship'];
        $types = array_values(array_intersect($types, $allowed));

        $data = [
            'professional_summary'       => $this->t($request, 'professional_summary'),
            'current_job_title'          => $this->t($request, 'current_job_title'),
            'current_employer'           => $this->t($request, 'current_employer'),
            'years_of_experience'        => $this->t($request, 'years_of_experience') ?: null,
            'expected_salary'            => $this->t($request, 'expected_salary') ?: null,
            'salary_currency'            => $this->t($request, 'salary_currency') ?: 'USD',
            'notice_period_days'         => $this->t($request, 'notice_period_days') ?: null,
            'available_from'             => $this->t($request, 'available_from') ?: null,
            'willing_to_relocate'        => $request->input('willing_to_relocate') ? 1 : 0,
            'willing_to_travel'          => $request->input('willing_to_travel') ? 1 : 0,
            'employment_type_preference' => json_encode($types),
        ];

        try {
            $this->repo->updateProfessional($seekerId, $data);
            $this->app->session()->flash('success', 'Professional information updated.');
        } catch (Throwable $e) {
            $this->app->session()->flash('error', 'Could not save: ' . $e->getMessage());
        }

        $this->redirect('/careers/profile/professional');
    }

    // ------------------------------------------------------------------ //
    //  Photo upload
    // ------------------------------------------------------------------ //

    public function uploadPhoto(Request $request): void
    {
        $seekerId = $this->requireSeeker();
        $this->validateCsrf('/careers/profile');

        $file = $request->file('photo');
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->app->session()->flash('error', 'No photo file received.');
            $this->redirect('/careers/profile');
        }

        $ext = strtolower(pathinfo(basename((string) ($file['name'] ?? '')), PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $this->app->session()->flash('error', 'Photo must be JPG, PNG, or WEBP.');
            $this->redirect('/careers/profile');
        }
        if ((int) ($file['size'] ?? 0) > 2 * 1024 * 1024) {
            $this->app->session()->flash('error', 'Photo must be 2 MB or smaller.');
            $this->redirect('/careers/profile');
        }

        $dir = base_path('storage/uploads/seekers/' . $seekerId);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            $this->app->session()->flash('error', 'Storage directory error.');
            $this->redirect('/careers/profile');
        }

        $name = 'photo_' . date('YmdHis') . '.' . $ext;
        $path = 'storage/uploads/seekers/' . $seekerId . '/' . $name;

        if (!move_uploaded_file((string) ($file['tmp_name'] ?? ''), base_path($path))) {
            $this->app->session()->flash('error', 'Upload failed.');
            $this->redirect('/careers/profile');
        }

        try {
            $this->repo->updatePhoto($seekerId, $path);
            $this->app->session()->flash('success', 'Photo updated.');
        } catch (Throwable $e) {
            $this->app->session()->flash('error', 'Could not save photo.');
        }

        $this->redirect('/careers/profile');
    }

    // ------------------------------------------------------------------ //
    //  CV file upload
    // ------------------------------------------------------------------ //

    public function uploadCv(Request $request): void
    {
        $seekerId = $this->requireSeeker();
        $this->validateCsrf('/careers/profile');

        $file = $request->file('cv_file');
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->app->session()->flash('error', 'No CV file received.');
            $this->redirect('/careers/profile');
        }

        $ext = strtolower(pathinfo(basename((string) ($file['name'] ?? '')), PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf', 'doc', 'docx'], true)) {
            $this->app->session()->flash('error', 'CV must be PDF, DOC, or DOCX.');
            $this->redirect('/careers/profile');
        }
        if ((int) ($file['size'] ?? 0) > 10 * 1024 * 1024) {
            $this->app->session()->flash('error', 'CV must be 10 MB or smaller.');
            $this->redirect('/careers/profile');
        }

        $dir = base_path('storage/uploads/seekers/' . $seekerId);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            $this->app->session()->flash('error', 'Storage directory error.');
            $this->redirect('/careers/profile');
        }

        $originalName = basename((string) ($file['name'] ?? 'cv.' . $ext));
        $storedName   = 'cv_' . date('YmdHis') . '.' . $ext;
        $path         = 'storage/uploads/seekers/' . $seekerId . '/' . $storedName;

        if (!move_uploaded_file((string) ($file['tmp_name'] ?? ''), base_path($path))) {
            $this->app->session()->flash('error', 'Upload failed.');
            $this->redirect('/careers/profile');
        }

        try {
            $this->repo->updateCv($seekerId, $path, $originalName);
            $this->app->session()->flash('success', 'CV uploaded successfully.');
        } catch (Throwable $e) {
            $this->app->session()->flash('error', 'Could not save CV record.');
        }

        $this->redirect('/careers/profile');
    }

    // ------------------------------------------------------------------ //
    //  CV Sections (experience, education, skill, etc.)
    // ------------------------------------------------------------------ //

    public function showSection(Request $request, string $type): void
    {
        if (!in_array($type, self::SECTION_TYPES, true)) {
            $this->redirect('/careers/profile');
        }
        $seekerId = $this->requireSeeker();
        $items    = $this->repo->getSections($seekerId, $type);

        $this->render('careers.profile.section', [
            'title'   => $this->sectionLabel($type),
            'type'    => $type,
            'items'   => $items,
            'seeker'  => $this->careersAuth->user(),
        ], 'careers');
    }

    public function addSection(Request $request, string $type): void
    {
        if (!in_array($type, self::SECTION_TYPES, true)) {
            $this->redirect('/careers/profile');
        }
        $seekerId = $this->requireSeeker();
        $this->validateCsrf('/careers/profile/' . $type);

        $data = $this->buildSectionData($request, $type);

        try {
            $this->repo->addSection($seekerId, $type, $data);
            $this->app->session()->flash('success', $this->sectionLabel($type) . ' entry added.');
        } catch (Throwable $e) {
            $this->app->session()->flash('error', 'Could not save: ' . $e->getMessage());
        }

        $this->redirect('/careers/profile/' . $type);
    }

    public function editSectionForm(Request $request, string $type, string $id): void
    {
        if (!in_array($type, self::SECTION_TYPES, true)) {
            $this->redirect('/careers/profile');
        }
        $seekerId = $this->requireSeeker();
        $item     = $this->repo->findSection((int) $id, $seekerId);

        if ($item === null) {
            $this->app->session()->flash('error', 'Entry not found.');
            $this->redirect('/careers/profile/' . $type);
        }

        // Decode JSON data field for the view
        if (is_string($item['data'])) {
            $item['data'] = json_decode($item['data'], true) ?? [];
        }

        $this->render('careers.profile.section-edit', [
            'title'  => 'Edit ' . $this->sectionLabel($type),
            'type'   => $type,
            'item'   => $item,
            'seeker' => $this->careersAuth->user(),
        ], 'careers');
    }

    public function updateSection(Request $request, string $type, string $id): void
    {
        if (!in_array($type, self::SECTION_TYPES, true)) {
            $this->redirect('/careers/profile');
        }
        $seekerId = $this->requireSeeker();
        $this->validateCsrf('/careers/profile/' . $type . '/' . $id . '/edit');

        $item = $this->repo->findSection((int) $id, $seekerId);
        if ($item === null) {
            $this->app->session()->flash('error', 'Entry not found.');
            $this->redirect('/careers/profile/' . $type);
        }

        $data = $this->buildSectionData($request, $type);

        try {
            $this->repo->updateSection((int) $id, $seekerId, $data);
            $this->app->session()->flash('success', $this->sectionLabel($type) . ' entry updated.');
        } catch (Throwable $e) {
            $this->app->session()->flash('error', 'Could not save: ' . $e->getMessage());
        }

        $this->redirect('/careers/profile/' . $type);
    }

    public function deleteSection(Request $request, string $type, string $id): void
    {
        if (!in_array($type, self::SECTION_TYPES, true)) {
            $this->redirect('/careers/profile');
        }
        $seekerId = $this->requireSeeker();
        $this->validateCsrf('/careers/profile/' . $type . '/' . $id . '/delete');

        try {
            $this->repo->deleteSection((int) $id, $seekerId);
            $this->app->session()->flash('success', 'Entry deleted.');
        } catch (Throwable $e) {
            $this->app->session()->flash('error', 'Could not delete.');
        }

        $this->redirect('/careers/profile/' . $type);
    }

    public function reorderSections(Request $request, string $type): void
    {
        if (!in_array($type, self::SECTION_TYPES, true)) {
            $this->redirect('/careers/profile');
        }
        $seekerId   = $this->requireSeeker();
        $orderedIds = (array) $request->input('order', []);

        try {
            $this->repo->reorderSections($seekerId, $type, $orderedIds);
        } catch (Throwable) {
            // Non-critical
        }

        $this->redirect('/careers/profile/' . $type);
    }

    // ------------------------------------------------------------------ //
    //  Helpers
    // ------------------------------------------------------------------ //

    private function requireSeeker(): int
    {
        $id = $this->careersAuth->id();
        if ($id === null) {
            $this->redirect('/careers/login');
        }
        return $id;
    }

    private function validateCsrf(string $redirect): void
    {
        $token = $_POST['_token'] ?? '';
        if (!$this->app->csrf()->validate($token)) {
            $this->app->session()->flash('error', 'Invalid form token.');
            $this->redirect($redirect);
        }
    }

    private function t(Request $request, string $key): string
    {
        return trim((string) $request->input($key, ''));
    }

    private function buildSectionData(Request $request, string $type): array
    {
        $base = [
            'title'      => $this->t($request, 'title'),
            'subtitle'   => $this->t($request, 'subtitle'),
            'start_date' => $this->t($request, 'start_date'),
            'end_date'   => $this->t($request, 'end_date'),
            'is_current' => $request->input('is_current') ? 1 : 0,
        ];

        $extra = match ($type) {
            'experience'    => ['employment_type' => $this->t($request, 'employment_type'),
                                'location'        => $this->t($request, 'location'),
                                'description'     => $this->t($request, 'description')],
            'education'     => ['degree'          => $this->t($request, 'degree'),
                                'field_of_study'  => $this->t($request, 'field_of_study'),
                                'grade'           => $this->t($request, 'grade'),
                                'description'     => $this->t($request, 'description')],
            'skill'         => ['level'           => $this->t($request, 'level')],
            'language'      => ['proficiency'     => $this->t($request, 'proficiency')],
            'certification' => ['credential_id'   => $this->t($request, 'credential_id'),
                                'credential_url'  => $this->t($request, 'credential_url'),
                                'expiry_date'     => $this->t($request, 'expiry_date')],
            'project'       => ['url'             => $this->t($request, 'url'),
                                'technologies'    => $this->t($request, 'technologies'),
                                'description'     => $this->t($request, 'description')],
            'award'         => ['issuer'          => $this->t($request, 'issuer'),
                                'description'     => $this->t($request, 'description')],
            'volunteer'     => ['organization'    => $this->t($request, 'organization'),
                                'cause'           => $this->t($request, 'cause'),
                                'description'     => $this->t($request, 'description')],
            'reference'     => ['name'            => $this->t($request, 'ref_name'),
                                'title'           => $this->t($request, 'ref_title'),
                                'company'         => $this->t($request, 'ref_company'),
                                'email'           => $this->t($request, 'ref_email'),
                                'phone'           => $this->t($request, 'ref_phone'),
                                'relationship'    => $this->t($request, 'relationship')],
            'publication'   => ['publisher'       => $this->t($request, 'publisher'),
                                'url'             => $this->t($request, 'url'),
                                'description'     => $this->t($request, 'description')],
            default         => [],
        };

        $base['data'] = $extra;
        return $base;
    }

    private function sectionLabel(string $type): string
    {
        return match ($type) {
            'experience'    => 'Work Experience',
            'education'     => 'Education',
            'skill'         => 'Skills',
            'language'      => 'Languages',
            'certification' => 'Certifications',
            'project'       => 'Projects',
            'award'         => 'Awards',
            'volunteer'     => 'Volunteer Work',
            'reference'     => 'References',
            'publication'   => 'Publications',
            default         => ucfirst($type),
        };
    }
}
