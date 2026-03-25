<?php

declare(strict_types=1);

namespace App\Modules\Documents;

use App\Core\Application;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Employees\EmployeeRepository;
use DateTimeImmutable;
use RuntimeException;
use Throwable;

final class DocumentController extends Controller
{
    private const MAX_FILE_SIZE = 5242880;
    private const ALLOWED_EXTENSIONS = ['pdf', 'png', 'jpg', 'jpeg', 'doc', 'docx'];

    private DocumentRepository $repository;
    private EmployeeRepository $employees;

    public function __construct(Application $app)
    {
        parent::__construct($app);
        $this->repository = new DocumentRepository($this->app->database());
        $this->employees = new EmployeeRepository($this->app->database());
    }

    public function categories(Request $request): void
    {
        $search = trim((string) $request->input('q', ''));
        $categories = [];

        try {
            $categories = $this->repository->listCategories($search);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load document categories: ' . $throwable->getMessage());
        }

        $this->render('documents.categories', [
            'title' => 'Document Categories',
            'pageTitle' => 'Document Categories',
            'categories' => $categories,
            'search' => $search,
        ]);
    }

    public function storeCategory(Request $request): void
    {
        $this->validateCsrf($request, '/documents/categories');
        $data = $this->sanitized($request);

        foreach (['name', 'code'] as $field) {
            if (($data[$field] ?? '') === '') {
                $this->invalid('/documents/categories', $data, 'Please provide the required category fields.');
            }
        }

        $data['code'] = strtoupper(str_replace([' ', '-'], '_', (string) $data['code']));

        try {
            $this->repository->createCategory($data);
            $this->app->session()->flash('success', 'Document category created successfully.');
            $this->redirect('/documents/categories');
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to save document category: ' . $throwable->getMessage());
            $this->app->session()->flash('old_input', $data);
            $this->redirect('/documents/categories');
        }
    }

    public function index(Request $request): void
    {
        $search = trim((string) $request->input('q', ''));
        $expiryFilter = (string) $request->input('expiry', 'all');

        if (!in_array($expiryFilter, ['all', 'expiring', 'expired', 'missing_expiry'], true)) {
            $expiryFilter = 'all';
        }

        $documents = [];

        try {
            $documents = $this->repository->listDocuments($search, $expiryFilter);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load document center: ' . $throwable->getMessage());
        }

        $this->render('documents.index', [
            'title' => 'Documents',
            'pageTitle' => 'Document Center',
            'documents' => $documents,
            'search' => $search,
            'expiryFilter' => $expiryFilter,
        ]);
    }

    public function expiring(Request $request): void
    {
        $days = (int) $request->input('days', 30);

        if (!in_array($days, [7, 15, 30, 60, 90], true)) {
            $days = 30;
        }

        $documents = [];

        try {
            $documents = $this->repository->expiringDocuments($days);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load expiring-documents report: ' . $throwable->getMessage());
        }

        $this->render('documents.expiring', [
            'title' => 'Expiring Documents',
            'pageTitle' => 'Expiring Documents',
            'documents' => $documents,
            'days' => $days,
        ]);
    }

    public function upload(Request $request, string $id): void
    {
        $employeeId = (int) $id;

        if (!$this->canAccessEmployeeDocuments($employeeId)) {
            Response::abort(403, 'You do not have access to this employee document record.');
        }

        try {
            $employee = $this->employees->findEmployee($employeeId);

            if ($employee === null) {
                Response::abort(404, 'Employee not found.');
            }

            $categories = $this->repository->activeCategories();
            $documents = $this->repository->employeeDocuments($employeeId);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load employee documents: ' . $throwable->getMessage());
            $this->redirect($this->app->auth()->hasPermission('documents.manage_all') ? '/documents' : '/dashboard');
        }

        $this->render('documents.upload', [
            'title' => 'Employee Documents',
            'pageTitle' => 'Employee Documents',
            'employee' => $employee,
            'categories' => $categories,
            'documents' => $documents,
            'canUpload' => $this->canUploadForEmployee($employeeId),
        ]);
    }

    public function storeUpload(Request $request, string $id): void
    {
        $employeeId = (int) $id;
        $redirectPath = '/employees/' . $employeeId . '/documents/upload';

        if (!$this->canUploadForEmployee($employeeId)) {
            Response::abort(403, 'You do not have permission to upload documents for this employee.');
        }

        $this->validateCsrf($request, $redirectPath);
        $data = $this->sanitized($request);

        try {
            $employee = $this->employees->findEmployee($employeeId);

            if ($employee === null) {
                Response::abort(404, 'Employee not found.');
            }

            $category = $this->repository->findCategory((int) ($data['category_id'] ?? 0));

            if ($category === null || (int) ($category['is_active'] ?? 0) !== 1) {
                $this->invalid($redirectPath, $data, 'Please select a valid active document category.');
            }

            $this->validateUploadData($data, $category, $redirectPath);
            $fileMeta = $this->storeUploadedFile($request->file('document_file'), $employeeId, $data, $redirectPath);

            try {
                $this->repository->createDocument($employeeId, $data, $fileMeta, $this->app->auth()->id());
            } catch (Throwable $throwable) {
                $this->removeStoredFile((string) $fileMeta['absolute_path']);
                throw $throwable;
            }

            $this->app->session()->flash('success', 'Document uploaded successfully.');
            $this->redirect($redirectPath);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to upload document: ' . $throwable->getMessage());
            $this->app->session()->flash('old_input', $data);
            $this->redirect($redirectPath);
        }
    }

    public function download(Request $request, string $id): void
    {
        $documentId = (int) $id;

        if ($documentId <= 0) {
            Response::abort(404, 'Document not found.');
        }

        try {
            $document = $this->repository->findDocument($documentId);

            if ($document === null) {
                Response::abort(404, 'Document not found.');
            }

            if (!$this->canAccessEmployeeDocuments((int) ($document['employee_id'] ?? 0))) {
                Response::abort(403, 'You do not have access to this document.');
            }

            $absolutePath = $this->absoluteDocumentPath((string) ($document['file_path'] ?? ''));

            if ($absolutePath === null || !is_file($absolutePath) || !is_readable($absolutePath)) {
                Response::abort(404, 'Document file not found.');
            }

            $mimeType = (string) ($document['mime_type'] ?? 'application/octet-stream');
            $fileName = $this->downloadFileName((string) ($document['original_file_name'] ?? 'document'));
            $contentLength = (int) ($document['file_size'] ?? 0);

            if ($contentLength <= 0) {
                $detectedSize = filesize($absolutePath);
                $contentLength = $detectedSize !== false ? (int) $detectedSize : 0;
            }

            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            header('Content-Type: ' . $mimeType);
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: private, max-age=0, must-revalidate');
            header(
                'Content-Disposition: ' . ($this->shouldDisplayInline($document) ? 'inline' : 'attachment')
                . '; filename="' . $fileName . '"; filename*=UTF-8\'\'' . rawurlencode($fileName)
            );

            if ($contentLength > 0) {
                header('Content-Length: ' . (string) $contentLength);
            }

            readfile($absolutePath);
            exit;
        } catch (Throwable $throwable) {
            Response::abort(500, 'Unable to open document: ' . $throwable->getMessage());
        }
    }

    private function validateCsrf(Request $request, string $redirectPath): void
    {
        if (!$this->app->csrf()->validate((string) $request->input('_token'))) {
            $this->app->session()->flash('error', 'Invalid form submission token.');
            $this->redirect($redirectPath);
        }
    }

    private function sanitized(Request $request): array
    {
        $data = [];

        foreach ($request->all() as $key => $value) {
            if ($key === '_token') {
                continue;
            }

            $data[$key] = is_string($value) ? trim($value) : $value;
        }

        foreach (['requires_expiry', 'is_active'] as $toggle) {
            if (array_key_exists($toggle, $data)) {
                $data[$toggle] = (int) $data[$toggle];
            }
        }

        return $data;
    }

    private function validateUploadData(array $data, array $category, string $redirectPath): void
    {
        foreach (['category_id', 'title', 'visibility_scope'] as $field) {
            if (($data[$field] ?? '') === '') {
                $this->invalid($redirectPath, $data, 'Please complete the required document fields.');
            }
        }

        if (!in_array((string) $data['visibility_scope'], ['employee', 'manager', 'hr', 'admin'], true)) {
            $this->invalid($redirectPath, $data, 'Please select a valid document visibility scope.');
        }

        $issueDate = $this->validatedDate($data['issue_date'] ?? null, 'issue date', $redirectPath, $data);
        $expiryDate = $this->validatedDate($data['expiry_date'] ?? null, 'expiry date', $redirectPath, $data);

        if ((int) ($category['requires_expiry'] ?? 0) === 1 && $expiryDate === null) {
            $this->invalid($redirectPath, $data, 'This document category requires an expiry date.');
        }

        if ($issueDate instanceof DateTimeImmutable && $expiryDate instanceof DateTimeImmutable && $expiryDate < $issueDate) {
            $this->invalid($redirectPath, $data, 'Expiry date cannot be earlier than the issue date.');
        }
    }

    private function validatedDate(mixed $value, string $label, string $redirectPath, array $data): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d', (string) $value);

        if (!$date instanceof DateTimeImmutable || $date->format('Y-m-d') !== (string) $value) {
            $this->invalid($redirectPath, $data, 'Please provide a valid ' . $label . '.');
        }

        return $date;
    }

    private function storeUploadedFile(mixed $file, int $employeeId, array $data, string $redirectPath): array
    {
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->invalid($redirectPath, $data, 'Please choose a document file to upload.');
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        $originalFileName = basename((string) ($file['name'] ?? 'document'));
        $fileSize = (int) ($file['size'] ?? 0);

        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new RuntimeException('Uploaded file payload is invalid.');
        }

        if ($fileSize <= 0 || $fileSize > self::MAX_FILE_SIZE) {
            $this->invalid($redirectPath, $data, 'The uploaded file must be between 1 byte and 5 MB.');
        }

        $extension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));

        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            $this->invalid($redirectPath, $data, 'Only PDF, PNG, JPG, JPEG, DOC, and DOCX files are allowed.');
        }

        $storedFileName = date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $relativeDirectory = 'storage/uploads/documents/employee_' . $employeeId;
        $absoluteDirectory = base_path($relativeDirectory);

        if (!is_dir($absoluteDirectory) && !mkdir($absoluteDirectory, 0775, true) && !is_dir($absoluteDirectory)) {
            throw new RuntimeException('Unable to create the upload directory.');
        }

        $relativePath = $relativeDirectory . '/' . $storedFileName;
        $absolutePath = base_path($relativePath);

        if (!move_uploaded_file($tmpName, $absolutePath)) {
            throw new RuntimeException('Unable to move the uploaded file.');
        }

        $mimeType = function_exists('mime_content_type')
            ? (mime_content_type($absolutePath) ?: (string) ($file['type'] ?? 'application/octet-stream'))
            : (string) ($file['type'] ?? 'application/octet-stream');

        return [
            'original_file_name' => $originalFileName,
            'stored_file_name' => $storedFileName,
            'file_path' => $relativePath,
            'file_extension' => $extension,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'absolute_path' => $absolutePath,
        ];
    }

    private function removeStoredFile(string $absolutePath): void
    {
        if ($absolutePath !== '' && is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }

    private function absoluteDocumentPath(string $relativePath): ?string
    {
        if ($relativePath === '') {
            return null;
        }

        $documentRoot = realpath(base_path('storage/uploads/documents'));
        $absolutePath = realpath(base_path($relativePath));

        if ($documentRoot === false || $absolutePath === false) {
            return null;
        }

        $normalizedRoot = strtolower(str_replace('\\', '/', rtrim($documentRoot, DIRECTORY_SEPARATOR)));
        $normalizedPath = strtolower(str_replace('\\', '/', $absolutePath));

        if ($normalizedPath !== $normalizedRoot && strpos($normalizedPath, $normalizedRoot . '/') !== 0) {
            return null;
        }

        return $absolutePath;
    }

    private function downloadFileName(string $fileName): string
    {
        $sanitized = trim(str_replace(["\r", "\n", '"'], '', $fileName));

        return $sanitized !== '' ? $sanitized : 'document';
    }

    private function shouldDisplayInline(array $document): bool
    {
        $mimeType = strtolower((string) ($document['mime_type'] ?? ''));
        $extension = strtolower((string) ($document['file_extension'] ?? pathinfo((string) ($document['original_file_name'] ?? ''), PATHINFO_EXTENSION)));

        return in_array($mimeType, ['application/pdf', 'image/png', 'image/jpeg'], true)
            || in_array($extension, ['pdf', 'png', 'jpg', 'jpeg'], true);
    }

    private function canAccessEmployeeDocuments(int $employeeId): bool
    {
        if ($this->app->auth()->hasPermission('documents.manage_all')) {
            return true;
        }

        $currentEmployeeId = (int) ($this->app->auth()->user()['employee_id'] ?? 0);

        return $currentEmployeeId > 0
            && $currentEmployeeId === $employeeId
            && ($this->app->auth()->hasPermission('documents.view_self') || $this->app->auth()->hasPermission('documents.upload_self'));
    }

    private function canUploadForEmployee(int $employeeId): bool
    {
        if ($this->app->auth()->hasPermission('documents.manage_all')) {
            return true;
        }

        return (int) ($this->app->auth()->user()['employee_id'] ?? 0) === $employeeId
            && $this->app->auth()->hasPermission('documents.upload_self');
    }

    private function invalid(string $redirectPath, array $data, string $message): void
    {
        $this->app->session()->flash('error', $message);
        $this->app->session()->flash('old_input', $data);
        $this->redirect($redirectPath);
    }
}