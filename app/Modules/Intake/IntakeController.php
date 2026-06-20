<?php

declare(strict_types=1);

namespace App\Modules\Intake;

use App\Core\Application;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Documents\DocumentRepository;
use App\Modules\Employees\EmployeeRepository;
use RuntimeException;
use Throwable;

final class IntakeController extends Controller
{
    private const MAX_FILE_SIZE        = 5242880; // 5 MB — matches DocumentController
    private const MAX_PHOTO_SIZE       = 2097152; // 2 MB for profile photos
    private const ALLOWED_EXTENSIONS   = ['pdf', 'png', 'jpg', 'jpeg', 'doc', 'docx'];
    private const ALLOWED_PHOTO_EXTS   = ['jpg', 'jpeg', 'png'];

    private IntakeRepository   $repository;
    private EmployeeRepository $employees;
    private DocumentRepository $documents;

    public function __construct(Application $app)
    {
        parent::__construct($app);
        $this->repository = new IntakeRepository($this->app->database());
        $this->employees  = new EmployeeRepository($this->app->database());
        $this->documents  = new DocumentRepository($this->app->database());
    }

    // ─── Public: wizard form ──────────────────────────────────────────────────

    public function form(Request $request): void
    {
        $documentTypes = $this->app->database()->fetchAll(
            "SELECT dt.id, dt.name, dt.requires_expiry, dc.id AS category_id, dc.name AS category_name
             FROM document_types dt
             JOIN document_categories dc ON dc.id = dt.category_id
             WHERE dt.is_active = 1 AND dc.is_active = 1
             ORDER BY dc.name ASC, dt.sort_order ASC, dt.name ASC"
        );

        $companies = $this->app->database()->fetchAll(
            "SELECT id, name FROM companies WHERE status = 'active' ORDER BY name ASC"
        );

        $identityDocTypes = $this->app->database()->fetchAll(
            "SELECT dt.id, dt.name, dt.requires_expiry
             FROM document_types dt
             JOIN document_categories dc ON dc.id = dt.category_id
             WHERE dc.code = 'IDENTITY' AND dt.is_active = 1
             ORDER BY dt.sort_order ASC, dt.name ASC"
        );

        $identificationTypes = $this->repository->getIdentificationTypes();

        $this->render('intake/form', [
            'documentTypes'       => $documentTypes,
            'companies'           => $companies,
            'identityDocTypes'    => $identityDocTypes,
            'identificationTypes' => $identificationTypes,
        ], 'intake');
    }

    // ─── Public: process submission ───────────────────────────────────────────

    public function submit(Request $request): void
    {
        // Dev mode: bypass validation with ?dev=1 query parameter
        $isDevMode = (string) $request->input('dev') === '1';

        if (!$isDevMode && !$this->app->csrf()->validate((string) $request->input('_token'))) {
            $this->failSubmit($request, 'Security token expired. Please try again.', 1);
        }

        $data = $this->trimmed($request);
        $passportPost = (array) ($request->input('passport_doc') ?? []);
        $idPost       = (array) ($request->input('id_doc') ?? []);
        $identifications = $this->normalizeIdentifications((array) ($request->input('identifications') ?? []));

        if ($data['passport_number'] === '' && !empty($passportPost['document_number'])) {
            $data['passport_number'] = trim((string) $passportPost['document_number']);
        }
        if ($data['id_number'] === '' && !empty($idPost['document_number'])) {
            $data['id_number'] = trim((string) $idPost['document_number']);
        }

        if (!$isDevMode) {
            $errors = [];
            if (empty($data['first_name']))   $errors[] = 'First name is required.';
            if (empty($data['last_name']))    $errors[] = 'Last name is required.';
            if (empty($data['personal_email']) || !filter_var($data['personal_email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid personal email is required.';
            if (empty($data['phone']))        $errors[] = 'Phone number is required.';
            if (empty($data['company_id']))   $errors[] = 'Please select a company.';
            if (empty($data['date_of_birth'])) $errors[] = 'Date of birth is required.';
            if (empty($data['gender']))       $errors[] = 'Gender is required.';
            if (empty($data['marital_status'])) $errors[] = 'Marital status is required.';
            if (empty($data['nationality']))  $errors[] = 'Nationality is required.';
            if (empty($data['address_line_1'])) $errors[] = 'Address line 1 is required.';
            if (empty($data['city']))         $errors[] = 'City is required.';
            if (empty($data['country']))      $errors[] = 'Country is required.';

            // Validate identifications (at least one ID required)
            if (empty($identifications)) {
                $errors[] = 'At least one form of identification is required.';
            } else {
                foreach ($identifications as $idx => $id) {
                    $typeId = (int) ($id['type_id'] ?? 0);
                    $number = trim((string) ($id['number'] ?? ''));
                    if ($typeId === 0 || empty($number)) {
                        $errors[] = 'Identification ' . ($idx + 1) . ': Please select a type and enter an ID number.';
                    }
                }
            }

            if ($errors) {
                $this->failSubmit($request, implode(' ', $errors), 1);
            }
        }

        // Validate profile photo (optional, images only)
        $photoFile      = $request->file('profile_photo') ?? [];
        $validatedPhoto = null;
        if (!$isDevMode && ($photoFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $validatedPhoto = $this->validatePhoto($photoFile);
            } catch (RuntimeException $e) {
                $this->failSubmit($request, 'Profile photo: ' . $e->getMessage(), 1);
            }
        } elseif ($isDevMode && ($photoFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            // Still try to validate in dev mode if file is provided
            try {
                $validatedPhoto = $this->validatePhoto($photoFile);
            } catch (RuntimeException $e) {
                $validatedPhoto = null;
            }
        }

        // Validate required passport document (skip in dev mode)
        $passportFile = $request->file('passport_file') ?? [];
        $validatedPassportFile = null;
        if (!$isDevMode) {
            if (($passportFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE || empty($passportFile['name'])) {
                $this->failSubmit($request, 'Passport document file is required.', 4);
            }
            try {
                $validatedPassportFile = $this->validateFile($passportFile);
            } catch (RuntimeException $e) {
                $this->failSubmit($request, 'Passport document: ' . $e->getMessage(), 4);
            }
        } elseif ($isDevMode && ($passportFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            // Still try to validate in dev mode if file is provided
            try {
                $validatedPassportFile = $this->validateFile($passportFile);
            } catch (RuntimeException $e) {
                $validatedPassportFile = null;
            }
        }

        if (empty($passportPost['document_number'])) {
            $this->failSubmit($request, 'Passport document number is required.', 4);
        }
        if (empty($passportPost['issue_date'])) {
            $this->failSubmit($request, 'Passport issue date is required.', 4);
        }
        if (empty($passportPost['expiry_date'])) {
            $this->failSubmit($request, 'Passport expiry date is required.', 4);
        }

        // Validate required ID document
        // Validate required ID document (skip in dev mode)
        $idFile = $request->file('id_file') ?? [];
        $validatedIdFile = null;
        if (!$isDevMode) {
            if (($idFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE || empty($idFile['name'])) {
                $this->failSubmit($request, 'National ID document file is required.', 4);
            }
            try {
                $validatedIdFile = $this->validateFile($idFile);
            } catch (RuntimeException $e) {
                $this->failSubmit($request, 'National ID document: ' . $e->getMessage(), 4);
            }

            if (empty($idPost['id_type_name'])) {
                $this->failSubmit($request, 'National ID type is required.', 4);
            }
            if (empty($idPost['document_number'])) {
                $this->failSubmit($request, 'National ID document number is required.', 4);
            }
            if (empty($idPost['issue_date'])) {
                $this->failSubmit($request, 'National ID issue date is required.', 4);
            }
            if (empty($idPost['expiry_date'])) {
                $this->failSubmit($request, 'National ID expiry date is required.', 4);
            }
        } elseif ($isDevMode && ($idFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            // Still try to validate in dev mode if file is provided
            try {
                $validatedIdFile = $this->validateFile($idFile);
            } catch (RuntimeException $e) {
                $validatedIdFile = null;
            }
        }

        $idPost = (array) ($request->input('id_doc') ?? []);

        // Validate optional additional document uploads
        $rawFiles    = $request->file('documents') ?? [];
        $fileInputs  = $this->normalizeMultiFileInput($rawFiles);
        $docsPost    = $request->input('documents') ?? [];
        $validatedFiles = [];

        foreach ($fileInputs as $idx => $file) {
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            try {
                $validatedFiles[$idx] = $this->validateFile($file);
            } catch (RuntimeException $e) {
                $this->failSubmit($request, 'Document ' . ($idx + 1) . ': ' . $e->getMessage(), 4);
            }
        }

        // Parse emergency contacts
        $contacts = [];
        foreach ((array) ($request->input('emergency_contacts') ?? []) as $c) {
            $name  = trim((string) ($c['full_name'] ?? ''));
            $phone = trim((string) ($c['phone'] ?? ''));
            if ($name === '' && $phone === '') {
                continue;
            }
            $contacts[] = $c;
        }

        // INSERT submission header + contacts to get $submissionId
        try {
            $submissionId = $this->repository->createSubmission(
                $data,
                $contacts,
                [], // document rows inserted after file move
                (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
                (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')
            );
        } catch (Throwable $e) {
            $this->failSubmit($request, 'Submission failed: ' . $e->getMessage(), 5);
        }

        // Save identifications to intake submission
        try {
            $this->repository->saveIdentifications($submissionId, $identifications);
        } catch (Throwable $e) {
            $this->app->database()->execute('DELETE FROM employee_intake_submissions WHERE id = :id', ['id' => $submissionId]);
            $this->failSubmit($request, 'Failed to save identifications: ' . $e->getMessage(), 2);
        }

        // Move files and collect doc metadata for DB insert
        $docsMeta    = [];
        $storedPaths = [];

        // Move profile photo if provided
        if ($validatedPhoto !== null) {
            try {
                $photoMeta = $this->storeIntakeFile($validatedPhoto, $submissionId);
                $this->repository->updatePhotoPath($submissionId, $photoMeta['file_path']);
                $storedPaths[] = $photoMeta['absolute_path'];
            } catch (Throwable $e) {
                // Non-fatal — photo failure doesn't block submission
            }
        }

        // Helper closure to resolve category_id from a document type
        $resolveCategoryId = function (?int $typeId): ?int {
            if ($typeId === null) return null;
            return (int) ($this->app->database()->fetchValue(
                'SELECT category_id FROM document_types WHERE id = :id', ['id' => $typeId]
            ) ?? 0) ?: null;
        };

        $resolveIdentityCategoryId = function () use ($resolveCategoryId, $passportPost): ?int {
            $passportTypeId = ($passportPost['document_type_id'] ?? '') !== '' ? (int) $passportPost['document_type_id'] : null;
            $categoryId = $resolveCategoryId($passportTypeId);
            if ($categoryId !== null) {
                return $categoryId;
            }

            return (int) ($this->app->database()->fetchValue(
                "SELECT id
                 FROM document_categories
                 WHERE is_active = 1
                   AND (code = 'IDENTITY' OR name LIKE '%Identity%')
                 ORDER BY id ASC
                 LIMIT 1"
            ) ?? 0) ?: null;
        };

        // Store required Passport document
        try {
            $passportMeta = $this->storeIntakeFile($validatedPassportFile, $submissionId);
            $storedPaths[] = $passportMeta['absolute_path'];
            $passportTypeId = ($passportPost['document_type_id'] ?? '') !== '' ? (int) $passportPost['document_type_id'] : null;
            $docsMeta[] = array_merge($passportMeta, [
                'document_type_id' => $passportTypeId,
                'category_id'      => $resolveCategoryId($passportTypeId),
                'title'            => trim((string) ($passportPost['title'] ?? 'Passport')),
                'document_number'  => trim((string) ($passportPost['document_number'] ?? '')),
                'issue_date'       => trim((string) ($passportPost['issue_date'] ?? '')) ?: null,
                'expiry_date'      => trim((string) ($passportPost['expiry_date'] ?? '')) ?: null,
            ]);
        } catch (Throwable $e) {
            $this->cleanupIntakeFiles($storedPaths);
            $this->app->database()->execute('DELETE FROM employee_intake_submissions WHERE id = :id', ['id' => $submissionId]);
            $this->failSubmit($request, 'Passport document upload failed: ' . $e->getMessage(), 4);
        }

        // Store required National ID document
        try {
            $idMeta = $this->storeIntakeFile($validatedIdFile, $submissionId);
            $storedPaths[] = $idMeta['absolute_path'];
            $docsMeta[] = array_merge($idMeta, [
                'document_type_id' => null,
                'category_id'      => $resolveIdentityCategoryId(),
                'title'            => trim((string) ($idPost['id_type_name'] ?? 'National ID')),
                'document_number'  => trim((string) ($idPost['document_number'] ?? '')),
                'issue_date'       => trim((string) ($idPost['issue_date'] ?? '')) ?: null,
                'expiry_date'      => trim((string) ($idPost['expiry_date'] ?? '')) ?: null,
            ]);
        } catch (Throwable $e) {
            $this->cleanupIntakeFiles($storedPaths);
            $this->app->database()->execute('DELETE FROM employee_intake_submissions WHERE id = :id', ['id' => $submissionId]);
            $this->failSubmit($request, 'National ID document upload failed: ' . $e->getMessage(), 4);
        }

        foreach ($validatedFiles as $idx => $fileInfo) {
            try {
                $meta          = $this->storeIntakeFile($fileInfo, $submissionId);
                $storedPaths[] = $meta['absolute_path'];

                $postDoc   = (array) ($docsPost[$idx] ?? []);
                $typeId    = ($postDoc['document_type_id'] ?? '') !== '' ? (int) $postDoc['document_type_id'] : null;
                $categoryId = null;

                if ($typeId !== null) {
                    $categoryId = (int) ($this->app->database()->fetchValue(
                        'SELECT category_id FROM document_types WHERE id = :id',
                        ['id' => $typeId]
                    ) ?? 0) ?: null;
                }

                $docsMeta[] = array_merge($meta, [
                    'document_type_id' => $typeId,
                    'category_id'      => $categoryId,
                    'title'            => trim((string) ($postDoc['title'] ?? '')),
                    'document_number'  => trim((string) ($postDoc['document_number'] ?? '')) ?: null,
                    'issue_date'       => trim((string) ($postDoc['issue_date'] ?? '')) ?: null,
                    'expiry_date'      => trim((string) ($postDoc['expiry_date'] ?? '')) ?: null,
                ]);
            } catch (Throwable $e) {
                $this->cleanupIntakeFiles($storedPaths);
                $this->app->database()->execute(
                    'DELETE FROM employee_intake_submissions WHERE id = :id',
                    ['id' => $submissionId]
                );
                $this->failSubmit($request, 'File upload failed. Please try again.', 4);
            }
        }

        // Insert document rows now that files are on disk
        if ($docsMeta) {
            try {
                foreach ($docsMeta as $doc) {
                    $this->app->database()->execute(
                        'INSERT INTO employee_intake_documents
                         (submission_id, document_type_id, category_id, title, document_number,
                          original_file_name, stored_file_name, file_path,
                          file_extension, mime_type, file_size, issue_date, expiry_date)
                         VALUES
                         (:sid, :type_id, :cat_id, :title, :doc_num,
                          :orig, :stored, :path,
                          :ext, :mime, :size, :issue, :expiry)',
                        [
                            'sid'     => $submissionId,
                            'type_id' => $doc['document_type_id'],
                            'cat_id'  => $doc['category_id'],
                            'title'   => $doc['title'],
                            'doc_num' => $doc['document_number'],
                            'orig'    => $doc['original_file_name'],
                            'stored'  => $doc['stored_file_name'],
                            'path'    => $doc['file_path'],
                            'ext'     => $doc['file_extension'],
                            'mime'    => $doc['mime_type'],
                            'size'    => $doc['file_size'],
                            'issue'   => $doc['issue_date'],
                            'expiry'  => $doc['expiry_date'],
                        ]
                    );
                }
            } catch (Throwable $e) {
                $this->cleanupIntakeFiles($storedPaths);
                $this->app->database()->execute(
                    'DELETE FROM employee_intake_submissions WHERE id = :id',
                    ['id' => $submissionId]
                );
                $this->failSubmit($request, 'Submission failed while saving documents. Please try again.', 5);
            }
        }

        $this->redirect('/employee-registration/success');
    }

    public function success(Request $request): void
    {
        $this->render('intake/success', [], 'intake');
    }

    // ─── HR: list submissions ─────────────────────────────────────────────────

    public function reviewList(Request $request): void
    {
        $status  = in_array($request->input('status'), ['pending', 'approved', 'rejected', 'all'], true)
            ? (string) $request->input('status')
            : 'pending';
        $search  = trim((string) ($request->input('q') ?? ''));
        $page    = max(1, (int) ($request->input('page') ?? 1));
        $perPage = 25;

        $submissions = $this->repository->listSubmissions($status, $search, $page, $perPage);
        $total       = $this->repository->countSubmissions($status, $search);
        $counts      = $this->repository->statusCounts();

        $this->render('intake/review-list', [
            'submissions' => $submissions,
            'statusFilter'=> $status,
            'search'      => $search,
            'page'        => $page,
            'perPage'     => $perPage,
            'total'       => $total,
            'totalPages'  => (int) ceil($total / $perPage),
            'counts'      => $counts,
        ]);
    }

    // ─── HR: view single submission ───────────────────────────────────────────

    public function reviewShow(Request $request, string $token): void
    {
        $submission = $this->repository->findByToken($token);

        if ($submission === null) {
            Response::abort(404, 'Submission not found.');
        }

        $submission = $this->decryptSubmission($submission);
        $contacts   = $this->repository->contactsBySubmission((int) $submission['id']);
        $documents  = $this->repository->documentsBySubmission((int) $submission['id']);
        $identifications = $this->repository->getIdentifications((int) $submission['id']);
        $formOptions = $this->employees->formOptions();

        $this->render('intake/review-show', [
            'submission'       => $submission,
            'contacts'         => $contacts,
            'documents'        => $documents,
            'identifications'  => $identifications,
            'formOptions'      => $formOptions,
            'token'            => $token,
        ]);
    }

    // ─── HR: approve ─────────────────────────────────────────────────────────

    public function approve(Request $request, string $token): void
    {
        if (!$this->app->csrf()->validate((string) $request->input('_token'))) {
            flash('error', 'Security token expired.');
            $this->redirect('/employee-registration/review/' . $token);
        }

        $errors = [];
        if (empty($request->input('work_email')) || !filter_var($request->input('work_email'), FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid work email is required.';
        }
        if (empty($request->input('company_id'))) {
            $errors[] = 'Company is required.';
        }
        if (empty($request->input('employment_type'))) {
            $errors[] = 'Employment type is required.';
        }
        if (empty($request->input('joining_date'))) {
            $errors[] = 'Joining date is required.';
        }

        if ($errors) {
            flash('error', implode(' ', $errors));
            $this->redirect('/employee-registration/review/' . $token);
        }

        $employmentData = [
            'work_email'          => strtolower(trim((string) $request->input('work_email'))),
            'company_id'          => (int) $request->input('company_id'),
            'branch_id'           => $request->input('branch_id') ?: null,
            'department_id'       => $request->input('department_id') ?: null,
            'team_id'             => $request->input('team_id') ?: null,
            'job_title_id'        => $request->input('job_title_id') ?: null,
            'designation_id'      => $request->input('designation_id') ?: null,
            'manager_employee_id' => $request->input('manager_employee_id') ?: null,
            'employment_type'     => (string) $request->input('employment_type'),
            'joining_date'        => (string) $request->input('joining_date'),
            'employee_status'     => (string) ($request->input('employee_status') ?? 'active'),
        ];

        try {
            $employeeId = $this->repository->approveSubmission(
                $token,
                $employmentData,
                (int) $this->app->auth()->id(),
                $this->employees,
                $this->documents
            );

            $this->auditLog(
                'intake',
                'employee_intake_submissions',
                null,
                'approved',
                (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
                (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
                null,
                ['employee_id' => $employeeId]
            );

            flash('success', 'Submission approved. Employee record created successfully.');
        } catch (Throwable $e) {
            flash('error', 'Approval failed: ' . $e->getMessage());
        }

        $this->redirect('/employee-registration/review/' . $token);
    }

    // ─── HR: reject ───────────────────────────────────────────────────────────

    public function reject(Request $request, string $token): void
    {
        if (!$this->app->csrf()->validate((string) $request->input('_token'))) {
            flash('error', 'Security token expired.');
            $this->redirect('/employee-registration/review/' . $token);
        }

        $reason = trim((string) ($request->input('rejection_reason') ?? ''));

        $this->repository->rejectSubmission($token, $reason, (int) $this->app->auth()->id());

        $this->auditLog(
            'intake',
            'employee_intake_submissions',
            null,
            'rejected',
            (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')
        );

        flash('success', 'Submission rejected.');
        $this->redirect('/employee-registration/review/' . $token);
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function validatePhoto(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload error code ' . $file['error'] . '.');
        }

        if (($file['size'] ?? 0) > self::MAX_PHOTO_SIZE) {
            throw new RuntimeException('Photo exceeds 2 MB limit.');
        }

        $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));

        if (!in_array($ext, self::ALLOWED_PHOTO_EXTS, true)) {
            throw new RuntimeException('Only JPG and PNG files are allowed for the profile photo.');
        }

        return array_merge($file, ['extension' => $ext]);
    }

    private function validateFile(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload error code ' . $file['error'] . '.');
        }

        if (($file['size'] ?? 0) > self::MAX_FILE_SIZE) {
            throw new RuntimeException('File exceeds 5 MB limit.');
        }

        $originalName = (string) ($file['name'] ?? '');
        $ext          = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            throw new RuntimeException('File type ".' . $ext . '" is not allowed.');
        }

        return array_merge($file, ['extension' => $ext]);
    }

    private function storeIntakeFile(array $fileInfo, int $submissionId): array
    {
        $dir = base_path('storage/uploads/intake/' . $submissionId);

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $ext         = $fileInfo['extension'];
        $storedName  = date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $relPath     = 'storage/uploads/intake/' . $submissionId . '/' . $storedName;
        $absPath     = base_path($relPath);

        if (!move_uploaded_file((string) $fileInfo['tmp_name'], $absPath)) {
            throw new RuntimeException('Could not save uploaded file.');
        }

        $mime = mime_content_type($absPath) ?: 'application/octet-stream';

        return [
            'original_file_name' => (string) $fileInfo['name'],
            'stored_file_name'   => $storedName,
            'file_path'          => $relPath,
            'file_extension'     => $ext,
            'mime_type'          => $mime,
            'file_size'          => (int) filesize($absPath),
            'absolute_path'      => $absPath,
        ];
    }

    private function cleanupIntakeFiles(array $absolutePaths): void
    {
        foreach ($absolutePaths as $path) {
            @unlink($path);
        }
    }

    private function failSubmit(Request $request, string $message, int $step = 1): never
    {
        $this->app->session()->flash('error', $message);
        $this->app->session()->flash('old_input', $this->sanitizeOldInput($request->all()));
        $this->app->session()->flash('intake_form_step', max(1, min(5, $step)));
        $this->redirect('/employee-registration');
    }

    private function sanitizeOldInput(array $input): array
    {
        unset($input['_token']);

        if (isset($input['identifications']) && is_array($input['identifications'])) {
            $input['identifications'] = $this->normalizeIdentifications($input['identifications']);
        }

        return $input;
    }

    private function normalizeIdentifications(array $identifications): array
    {
        $normalized = [];

        foreach ($identifications as $id) {
            if (!is_array($id)) {
                continue;
            }

            $typeId = (int) ($id['type_id'] ?? 0);
            $number = trim((string) ($id['number'] ?? ''));
            $issueDate = trim((string) ($id['issue_date'] ?? ''));
            $expiryDate = trim((string) ($id['expiry_date'] ?? ''));
            $isPrimary = isset($id['is_primary']) && (string) $id['is_primary'] === '1';

            if ($typeId === 0 && $number === '' && $issueDate === '' && $expiryDate === '' && !$isPrimary) {
                continue;
            }

            $normalized[] = [
                'type_id'    => $typeId,
                'number'     => $number,
                'issue_date' => $issueDate,
                'expiry_date'=> $expiryDate,
                'is_primary' => $isPrimary ? '1' : '0',
            ];
        }

        return $normalized;
    }

    private function normalizeMultiFileInput(array $rawFiles): array
    {
        $normalized = [];

        foreach (array_keys($rawFiles['name'] ?? []) as $idx) {
            $normalized[$idx] = [
                'name'     => $rawFiles['name'][$idx] ?? '',
                'type'     => $rawFiles['type'][$idx] ?? '',
                'tmp_name' => $rawFiles['tmp_name'][$idx] ?? '',
                'error'    => $rawFiles['error'][$idx] ?? UPLOAD_ERR_NO_FILE,
                'size'     => $rawFiles['size'][$idx] ?? 0,
            ];
        }

        return $normalized;
    }

    private function decryptSubmission(array $row): array
    {
        foreach (['personal_email', 'phone', 'alternate_phone', 'date_of_birth', 'id_number', 'passport_number'] as $field) {
            if (isset($row[$field])) {
                $row[$field] = decrypt_field($row[$field]);
            }
        }

        return $row;
    }

    private function trimmed(Request $request): array
    {
        $result = [];

        foreach ([
            'first_name', 'middle_name', 'last_name', 'personal_email', 'phone', 'alternate_phone',
            'date_of_birth', 'gender', 'marital_status', 'nationality', 'second_nationality',
            'company_id',
            'address_line_1', 'address_line_2', 'city', 'state', 'country', 'postal_code',
            'id_number', 'passport_number',
        ] as $field) {
            $result[$field] = trim((string) ($request->input($field) ?? ''));
        }

        return $result;
    }

    public function downloadDocument(Request $request, string $token, int $docId): void
    {
        // Verify token and get submission
        $submission = $this->app->database()->fetch(
            'SELECT id FROM employee_intake_submissions WHERE review_token = :token',
            ['token' => $token]
        );

        if (!$submission) {
            Response::abort(404, 'Submission not found.');
        }

        $submissionId = (int) $submission['id'];

        // Get document
        $doc = $this->app->database()->fetch(
            'SELECT * FROM employee_intake_documents WHERE id = :id AND submission_id = :sid',
            ['id' => $docId, 'sid' => $submissionId]
        );

        if (!$doc) {
            Response::abort(404, 'Document not found.');
        }

        // Build absolute path and verify it exists
        $absPath = base_path($doc['file_path']);
        if (!file_exists($absPath) || !is_file($absPath)) {
            Response::abort(404, 'File not found on server.');
        }

        // Serve file
        header('Content-Type: ' . ($doc['mime_type'] ?? 'application/octet-stream'));
        header('Content-Length: ' . filesize($absPath));
        header('Content-Disposition: attachment; filename="' . $doc['original_file_name'] . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        readfile($absPath);
        exit;
    }

    public function previewDocument(Request $request, string $token, int $docId): void
    {
        // Verify token and get submission
        $submission = $this->app->database()->fetch(
            'SELECT id FROM employee_intake_submissions WHERE review_token = :token',
            ['token' => $token]
        );

        if (!$submission) {
            Response::abort(404, 'Submission not found.');
        }

        $submissionId = (int) $submission['id'];

        // Get document
        $doc = $this->app->database()->fetch(
            'SELECT * FROM employee_intake_documents WHERE id = :id AND submission_id = :sid',
            ['id' => $docId, 'sid' => $submissionId]
        );

        if (!$doc) {
            Response::abort(404, 'Document not found.');
        }

        $absPath = base_path($doc['file_path']);
        if (!file_exists($absPath) || !is_file($absPath)) {
            Response::abort(404, 'File not found on server.');
        }

        // Serve inline for preview
        header('Content-Type: ' . ($doc['mime_type'] ?? 'application/octet-stream'));
        header('Content-Length: ' . filesize($absPath));
        header('Content-Disposition: inline; filename="' . $doc['original_file_name'] . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        readfile($absPath);
        exit;
    }
}
