<?php

declare(strict_types=1);

namespace App\Modules\Documents;

use App\Core\Application;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Employees\EmployeeRepository;
use App\Modules\Notifications\NotificationRepository;
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

    public function sendExpiryAlerts(Request $request): void
    {
        if (!$this->app->auth()->hasPermission('documents.manage_all')) {
            Response::abort(403, 'You do not have permission to send document expiry alerts.');
        }

        if (!$this->app->csrf()->validate((string) $request->input('_token'))) {
            $this->app->session()->flash('error', 'Invalid form submission token.');
            $this->redirect('/documents/expiring');
        }

        $alertCount = 0;

        try {
            $documents = $this->repository->documentsNeedingAlerts(30);
            $recipients = $this->repository->hrAndAdminRecipients();

            if ($documents !== [] && $recipients !== []) {
                $notifications = new NotificationRepository($this->app->database());
                $appName = (string) config('app.name', 'HR Management System');
                $appUrl = rtrim((string) config('app.url', ''), '/');

                foreach ($documents as $doc) {
                    $docId = (int) $doc['id'];
                    $employeeName = (string) ($doc['employee_name'] ?? '');
                    $docTitle = (string) ($doc['title'] ?? '');
                    $categoryName = (string) ($doc['category_name'] ?? '');
                    $expiryDate = (string) ($doc['expiry_date'] ?? '');
                    $daysLeft = (int) ($doc['days_until_expiry'] ?? 0);
                    $docUrl = $appUrl . '/documents?q=' . urlencode($employeeName);

                    $subject = '[Document Expiry] ' . $docTitle . ' — expires in ' . $daysLeft . ' day' . ($daysLeft !== 1 ? 's' : '');

                    $bodyHtml = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:650px;margin:0 auto;color:#333;">';
                    $bodyHtml .= '<div style="background:#c0392b;color:#fff;padding:18px 24px;border-radius:6px 6px 0 0;">';
                    $bodyHtml .= '<h2 style="margin:0;font-size:20px;">&#9888; Document Expiry Alert</h2></div>';
                    $bodyHtml .= '<div style="padding:20px 24px;border:1px solid #e0e0e0;border-top:none;">';
                    $bodyHtml .= '<p>The following employee document is expiring within <strong>' . $daysLeft . ' day' . ($daysLeft !== 1 ? 's' : '') . '</strong>.</p>';
                    $bodyHtml .= '<table style="border-collapse:collapse;width:100%;font-size:14px;">';
                    $bodyHtml .= '<tr><td style="padding:8px;border:1px solid #ddd;background:#f9f9f9;font-weight:600;">Employee</td><td style="padding:8px;border:1px solid #ddd;">' . e($employeeName) . ' (' . e((string) ($doc['employee_code'] ?? '')) . ')</td></tr>';
                    $bodyHtml .= '<tr><td style="padding:8px;border:1px solid #ddd;background:#f9f9f9;font-weight:600;">Document</td><td style="padding:8px;border:1px solid #ddd;">' . e($docTitle) . '</td></tr>';
                    $bodyHtml .= '<tr><td style="padding:8px;border:1px solid #ddd;background:#f9f9f9;font-weight:600;">Category</td><td style="padding:8px;border:1px solid #ddd;">' . e($categoryName) . '</td></tr>';
                    $bodyHtml .= '<tr><td style="padding:8px;border:1px solid #ddd;background:#f9f9f9;font-weight:600;">Expiry Date</td><td style="padding:8px;border:1px solid #ddd;color:#c0392b;font-weight:600;">' . e($expiryDate) . '</td></tr>';
                    $bodyHtml .= '</table>';
                    $bodyHtml .= '<p style="margin:16px 0;"><a href="' . e($docUrl) . '" style="display:inline-block;padding:10px 24px;background:#c0392b;color:#fff;text-decoration:none;border-radius:4px;font-weight:600;">View in Document Center</a></p>';
                    $bodyHtml .= '<p style="font-size:12px;color:#888;">This is an automated alert from ' . e($appName) . '.</p>';
                    $bodyHtml .= '</div></div>';

                    $bodyText = 'Document Expiry Alert: ' . $docTitle . "\nEmployee: " . $employeeName . "\nExpiry: " . $expiryDate . " (" . $daysLeft . " days left)";

                    foreach ($recipients as $recipient) {
                        $recipientEmail = (string) ($recipient['email'] ?? '');
                        $recipientUserId = isset($recipient['user_id']) ? (int) $recipient['user_id'] : null;

                        if ($recipientEmail === '') {
                            continue;
                        }

                        $notifications->deliverEmail($recipientEmail, $subject, $bodyHtml, $bodyText, $recipientUserId, 'employee_document', $docId);

                        if ($recipientUserId !== null) {
                            $notifications->create($recipientUserId, 'document_expiry', $subject, $bodyText, 'employee_document', $docId, '/documents/expiring');
                            $this->repository->recordAlertSent($docId, '30_days', $recipientUserId);
                        }
                    }

                    $alertCount++;
                }
            }

            if ($alertCount === 0) {
                $this->app->session()->flash('info', 'No new documents require 30-day expiry alerts at this time.');
            } else {
                $this->app->session()->flash('success', 'Expiry alerts sent for ' . $alertCount . ' document' . ($alertCount !== 1 ? 's' : '') . '.');
            }
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to send expiry alerts: ' . $throwable->getMessage());
        }

        $this->redirect('/documents/expiring');
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

            $viewerRoleCode = (string) ($this->app->auth()->user()['role_code'] ?? 'employee');
            $categories = $this->repository->activeCategories();
            $documents = $this->repository->employeeDocuments($employeeId, $viewerRoleCode);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load employee documents: ' . $throwable->getMessage());
            $this->redirect($this->app->auth()->hasPermission('documents.manage_all') ? '/documents' : '/dashboard');
        }

        // Log a 'view' event for each document visible on this page
        foreach ($documents as $doc) {
            try {
                $this->repository->logAccess(
                    (int) $doc['id'],
                    $employeeId,
                    $this->app->auth()->id(),
                    'view',
                    $request->ip(),
                    $request->userAgent()
                );
            } catch (Throwable) {
                // non-fatal
            }
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

    /**
     * Issue a short-lived (15 min) signed download token for a document.
     * Returns a redirect to the token URL so the download starts automatically.
     * POST /documents/{id}/token
     */
    public function issueToken(Request $request, string $id): void
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

            if (!$this->canAccessDocumentByScope($document)) {
                Response::abort(403, 'This document is not visible to your access level.');
            }

            $token = $this->repository->createAccessToken(
                $documentId,
                $this->app->auth()->id(),
                $request->ip()
            );

            $this->redirect('/documents/dl/' . $token);
        } catch (Throwable $throwable) {
            Response::abort(500, 'Unable to generate download token: ' . $throwable->getMessage());
        }
    }

    /**
     * Serve a document using a signed, expiring token.
     * GET /documents/dl/{token}
     * No auth check needed — the token IS the proof of authorisation.
     */
    public function downloadViaToken(Request $request, string $token): void
    {
        if (strlen($token) !== 64 || !ctype_xdigit($token)) {
            Response::abort(404, 'Invalid or expired download link.');
        }

        try {
            $documentId = $this->repository->consumeAccessToken($token);

            if ($documentId === null) {
                Response::abort(410, 'This download link has expired or has already been used.');
            }

            $document = $this->repository->findDocument($documentId);

            if ($document === null) {
                Response::abort(404, 'Document not found.');
            }

            $absolutePath = $this->absoluteDocumentPath((string) ($document['file_path'] ?? ''));

            if ($absolutePath === null || !is_file($absolutePath) || !is_readable($absolutePath)) {
                Response::abort(404, 'Document file not found.');
            }

            // Log the download
            try {
                $this->repository->logAccess(
                    $documentId,
                    (int) ($document['employee_id'] ?? 0),
                    $this->app->auth()->id(),
                    'download',
                    $request->ip(),
                    $request->userAgent()
                );
            } catch (Throwable) {
                // non-fatal
            }

            $mimeType      = (string) ($document['mime_type'] ?? 'application/octet-stream');
            $fileName      = $this->downloadFileName((string) ($document['original_file_name'] ?? 'document'));
            $contentLength = (int) ($document['file_size'] ?? 0);

            if ($contentLength <= 0) {
                $detected      = filesize($absolutePath);
                $contentLength = $detected !== false ? (int) $detected : 0;
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
            Response::abort(500, 'Unable to serve document: ' . $throwable->getMessage());
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

            if (!$this->canAccessDocumentByScope($document)) {
                Response::abort(403, 'This document is not visible to your access level.');
            }

            $absolutePath = $this->absoluteDocumentPath((string) ($document['file_path'] ?? ''));

            if ($absolutePath === null || !is_file($absolutePath) || !is_readable($absolutePath)) {
                Response::abort(404, 'Document file not found.');
            }

            // Log the download event
            try {
                $this->repository->logAccess(
                    $documentId,
                    (int) ($document['employee_id'] ?? 0),
                    $this->app->auth()->id(),
                    'download',
                    $request->ip(),
                    $request->userAgent()
                );
            } catch (Throwable) {
                // non-fatal — don't block the download if logging fails
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

    /**
     * Determine if the current user can access a specific document based on its visibility_scope.
     * admin   → super_admin/hr_admin only
     * hr      → super_admin/hr_admin
     * manager → super_admin/hr_admin/manager (or manage_all)
     * employee → anyone with access to the employee's documents
     */
    private function canAccessDocumentByScope(array $document): bool
    {
        if ($this->app->auth()->hasPermission('documents.manage_all')) {
            return true;
        }

        $scope = (string) ($document['visibility_scope'] ?? 'employee');
        $user = $this->app->auth()->user() ?? [];
        $roleCode = (string) ($user['role_code'] ?? '');

        if (in_array($roleCode, ['super_admin', 'hr_admin'], true)) {
            return true;
        }

        if ($scope === 'admin') {
            return false;
        }

        if ($scope === 'hr') {
            return false;
        }

        if ($scope === 'manager') {
            return $roleCode === 'manager';
        }

        // scope = 'employee' — allowed if they own the document
        $currentEmployeeId = (int) ($user['employee_id'] ?? 0);
        $documentEmployeeId = (int) ($document['employee_id'] ?? 0);

        return $currentEmployeeId > 0 && $currentEmployeeId === $documentEmployeeId;
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