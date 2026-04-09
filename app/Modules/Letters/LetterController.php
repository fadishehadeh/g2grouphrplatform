<?php

declare(strict_types=1);

namespace App\Modules\Letters;

use App\Core\Application;
use App\Core\Controller;
use App\Core\Request;
use App\Support\Mailer;
use Throwable;

final class LetterController extends Controller
{
    private LetterRepository $repository;

    public function __construct(Application $app)
    {
        parent::__construct($app);
        $this->repository = new LetterRepository($this->app->database());
    }

    /** Employee: list own letter requests */
    public function myLetters(Request $request): void
    {
        $employeeId = $this->requireEmployeeProfile();
        $letters = [];

        try {
            $letters = $this->repository->myRequests($employeeId);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load letter requests: ' . $throwable->getMessage());
        }

        $this->render('letters.index', [
            'title'     => 'My Letters',
            'pageTitle' => 'My Letters',
            'letters'   => $letters,
            'isAdmin'   => false,
        ]);
    }

    /** HR Admin: list all letter requests */
    public function adminLetters(Request $request): void
    {
        $status     = trim((string) ($request->input('status') ?? ''));
        $letterType = trim((string) ($request->input('letter_type') ?? ''));
        $letters    = [];

        try {
            $letters = $this->repository->allRequests($status, $letterType);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load letter requests: ' . $throwable->getMessage());
        }

        $this->render('letters.index', [
            'title'      => 'Letter Requests',
            'pageTitle'  => 'Letter Requests',
            'letters'    => $letters,
            'isAdmin'    => true,
            'filterStatus'     => $status,
            'filterLetterType' => $letterType,
        ]);
    }

    /** Employee: show request form */
    public function requestForm(Request $request): void
    {
        $this->requireEmployeeProfile();

        $this->render('letters.request', [
            'title'     => 'Request a Letter',
            'pageTitle' => 'Request a Letter',
        ]);
    }

    /** Employee: submit letter request */
    public function submitRequest(Request $request): void
    {
        $this->validateCsrf($request, '/letters/request');
        $employeeId = $this->requireEmployeeProfile();
        $data = $this->sanitized($request);

        $validTypes = ['salary_certificate', 'employment_certificate', 'experience_letter', 'noc', 'bank_letter'];

        if (empty($data['letter_type']) || !in_array($data['letter_type'], $validTypes, true)) {
            $this->invalid('/letters/request', $data, 'Please select a valid letter type.');
        }

        try {
            $requestId = $this->repository->createRequest($data, $employeeId, (int) ($this->app->auth()->user()['id'] ?? 0));
            $this->notifyHrOfNewRequest($requestId, $data);
            $this->app->session()->flash('success', 'Your letter request has been submitted. HR will process it shortly.');
            $this->redirect('/letters/my');
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to submit request: ' . $throwable->getMessage());
            $this->app->session()->flash('old_input', $data);
            $this->redirect('/letters/request');
        }
    }

    /** HR Admin: show generate/review form */
    public function showRequest(Request $request, string $id): void
    {
        $letter = $this->repository->findRequest((int) $id);

        if ($letter === null) {
            $this->app->session()->flash('error', 'Letter request not found.');
            $this->redirect('/letters/admin');
        }

        $isAdmin = $this->app->auth()->hasPermission('letters.manage');

        $this->render('letters.generate', [
            'title'     => 'Letter Request #' . $id,
            'pageTitle' => 'Letter Request',
            'letter'    => $letter,
            'isAdmin'   => $isAdmin,
        ]);
    }

    /** HR Admin: generate the letter */
    public function generate(Request $request, string $id): void
    {
        $this->validateCsrf($request, '/letters/' . $id);
        $data = $this->sanitized($request);

        $letter = $this->repository->findRequest((int) $id);

        if ($letter === null) {
            $this->app->session()->flash('error', 'Letter request not found.');
            $this->redirect('/letters/admin');
        }

        if ((string) ($letter['status'] ?? '') === 'approved') {
            $this->app->session()->flash('error', 'This letter has already been generated.');
            $this->redirect('/letters/' . $id);
        }

        try {
            $user            = $this->app->auth()->user() ?? [];
            $generatedByName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
            $salaryAmount    = ($data['salary_amount'] ?? '') !== '' ? (float) $data['salary_amount'] : null;
            $additionalInfo  = ($data['additional_info'] ?? '') !== '' ? $data['additional_info'] : null;

            // Fetch logo path for this employee's company
            $logoPath = $this->repository->companyLogoPath((int) $letter['employee_id']);

            $letterData = array_merge($letter, [
                'salary_amount'   => $salaryAmount,
                'additional_info' => $additionalInfo,
                'logo_path'       => $logoPath,
            ]);

            $letterContent = $this->repository->buildLetterContent($letterData, $generatedByName);

            $this->repository->generateLetter(
                (int) $id,
                $letterContent,
                $salaryAmount,
                $additionalInfo,
                (int) ($user['id'] ?? 0)
            );

            // Email PDF copy to employee
            $this->emailPdfToEmployee($letterData, $letterContent);

            $this->app->session()->flash('success', 'Letter generated and emailed to the employee.');
            $this->redirect('/letters/' . $id . '/view');
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to generate letter: ' . $throwable->getMessage());
            $this->redirect('/letters/' . $id);
        }
    }

    /** View the generated letter (employee + admin) */
    public function viewLetter(Request $request, string $id): void
    {
        $letter = $this->repository->findRequest((int) $id);

        if ($letter === null) {
            $this->app->session()->flash('error', 'Letter not found.');
            $this->redirect('/letters/my');
        }

        // Employees can only view their own letters
        if (!$this->app->auth()->hasPermission('letters.manage')) {
            $employeeId = (int) ($this->app->auth()->user()['employee_id'] ?? 0);
            if ((int) $letter['employee_id'] !== $employeeId) {
                $this->app->session()->flash('error', 'Access denied.');
                $this->redirect('/letters/my');
            }
        }

        if ((string) ($letter['status'] ?? '') !== 'approved' || empty($letter['letter_content'])) {
            $this->app->session()->flash('error', 'This letter has not been generated yet.');
            $isAdmin = $this->app->auth()->hasPermission('letters.manage');
            $this->redirect($isAdmin ? '/letters/' . $id : '/letters/my');
        }

        $this->render('letters.view', [
            'title'     => LetterRepository::letterTypeLabel((string) $letter['letter_type']),
            'pageTitle' => LetterRepository::letterTypeLabel((string) $letter['letter_type']),
            'letter'    => $letter,
        ]);
    }

    /** HR Admin: reject a letter request */
    public function reject(Request $request, string $id): void
    {
        $this->validateCsrf($request, '/letters/' . $id);
        $data = $this->sanitized($request);

        $reason = trim((string) ($data['reason'] ?? ''));

        if ($reason === '') {
            $this->app->session()->flash('error', 'Please provide a rejection reason.');
            $this->redirect('/letters/' . $id);
        }

        try {
            $this->repository->rejectRequest((int) $id, $reason);
            $this->app->session()->flash('success', 'Letter request rejected.');
            $this->redirect('/letters/admin');
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to reject request: ' . $throwable->getMessage());
            $this->redirect('/letters/' . $id);
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function notifyHrOfNewRequest(int $requestId, array $data): void
    {
        $mailer = new Mailer((array) config('app.mail', []));
        if (!$mailer->isEnabled()) {
            return;
        }

        $hrUsers   = $this->repository->hrAdminEmails();
        $user      = $this->app->auth()->user() ?? [];
        $empName   = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        $typeLabel = LetterRepository::letterTypeLabel((string) ($data['letter_type'] ?? ''));
        $purpose   = ($data['purpose'] ?? '') !== '' ? ' for <em>' . htmlspecialchars((string) $data['purpose'], ENT_QUOTES, 'UTF-8') . '</em>' : '';
        $reviewUrl = url('/letters/' . $requestId);
        $appName   = (string) config('app.brand.display_name', config('app.name', 'HR System'));

        $subject = '[HR] Letter Request: ' . $typeLabel . ' from ' . $empName;
        $body    = '<div style="font-family:Arial,sans-serif;max-width:600px">'
            . '<h3 style="color:#0d6efd">New Letter Request</h3>'
            . '<p><strong>' . htmlspecialchars($empName, ENT_QUOTES, 'UTF-8') . '</strong> has requested a <strong>'
            . htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8') . '</strong>' . $purpose . '.</p>'
            . '<p style="margin:20px 0"><a href="' . htmlspecialchars($reviewUrl, ENT_QUOTES, 'UTF-8')
            . '" style="background:#0d6efd;color:#fff;padding:10px 24px;border-radius:5px;text-decoration:none;font-weight:bold">Review &amp; Generate Letter</a></p>'
            . '<p style="color:#888;font-size:12px">This is an automated notification from ' . htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') . '.</p>'
            . '</div>';

        foreach ($hrUsers as $hr) {
            $email = (string) ($hr['email'] ?? '');
            if ($email === '') continue;
            try {
                $mailer->send($email, $subject, $body);
            } catch (Throwable) {
                // Non-fatal — letter still submitted
            }
        }
    }

    private function emailPdfToEmployee(array $letterData, string $letterContent): void
    {
        $employeeEmail = $this->repository->employeeEmail((int) $letterData['employee_id']);
        if ($employeeEmail === null) {
            return;
        }

        $mailer = new Mailer((array) config('app.mail', []));
        if (!$mailer->isEnabled()) {
            return;
        }

        $typeLabel = LetterRepository::letterTypeLabel((string) ($letterData['letter_type'] ?? ''));
        $empName   = htmlspecialchars((string) ($letterData['employee_name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $company   = htmlspecialchars((string) ($letterData['company_name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $appName   = (string) config('app.brand.display_name', config('app.name', 'HR System'));

        // Generate PDF using TCPDF
        $pdfContent = $this->buildPdf($letterContent, $typeLabel, $company);

        // Build multipart email with PDF attachment
        $boundary  = '----=_Part_' . bin2hex(random_bytes(8));
        $htmlBody  = '<div style="font-family:Arial,sans-serif;max-width:600px">'
            . '<p>Dear ' . $empName . ',</p>'
            . '<p>Please find attached your <strong>' . htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8') . '</strong> as requested.</p>'
            . '<p>Best regards,<br>Human Resources<br>' . $company . '</p>'
            . '<p style="color:#888;font-size:12px">This is an automated message from ' . htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') . '.</p>'
            . '</div>';

        $pdfB64   = base64_encode($pdfContent);
        $fileName = strtolower(str_replace(' ', '_', $typeLabel)) . '_' . date('Ymd') . '.pdf';

        $rawMessage = "MIME-Version: 1.0\r\n"
            . "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: quoted-printable\r\n\r\n"
            . quoted_printable_encode($htmlBody) . "\r\n\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: application/pdf; name=\"{$fileName}\"\r\n"
            . "Content-Transfer-Encoding: base64\r\n"
            . "Content-Disposition: attachment; filename=\"{$fileName}\"\r\n\r\n"
            . chunk_split($pdfB64) . "\r\n"
            . "--{$boundary}--";

        try {
            $mailer->sendRaw($employeeEmail, $typeLabel . ' — ' . $company, $rawMessage);
        } catch (Throwable) {
            // Non-fatal — letter still approved
        }
    }

    private function buildPdf(string $letterHtml, string $title, string $company): string
    {
        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator($company);
        $pdf->SetTitle($title);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(20, 20, 20);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->AddPage();

        // Strip Bootstrap classes; TCPDF uses inline HTML
        $css = '<style>
            body{font-family:helvetica;font-size:11pt;color:#212529;line-height:1.7}
            h2{font-size:16pt;color:#0d6efd;margin:0 0 4px}
            .letter-header{margin-bottom:20px}
            .letter-divider{border-top:2px solid #0d6efd;margin:8px 0 16px}
            .letter-subject{text-align:center;font-size:13pt;text-decoration:underline;margin-bottom:14px}
            .letter-body p{margin-bottom:12px;text-align:justify}
            .letter-closing{margin-top:24px}
            .signature-line{border-top:1px solid #212529;width:140px;margin-bottom:6px}
            .letter-footer{margin-top:32px;font-size:9pt;color:#888;border-top:1px solid #dee2e6;padding-top:10px}
            .text-muted{color:#6c757d}
            .text-end{text-align:right}
        </style>';

        $pdf->writeHTML($css . $letterHtml, true, false, true, false, '');

        return $pdf->Output('', 'S');
    }

    private function requireEmployeeProfile(): int
    {
        $user       = $this->app->auth()->user() ?? [];
        $employeeId = (int) ($user['employee_id'] ?? 0);

        if ($employeeId <= 0) {
            $this->app->session()->flash('error', 'Your account is not linked to an employee profile yet.');
            $this->redirect('/dashboard');
        }

        return $employeeId;
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

        return $data;
    }

    private function invalid(string $redirectPath, array $data, string $message): never
    {
        $this->app->session()->flash('error', $message);
        $this->app->session()->flash('old_input', $data);
        $this->redirect($redirectPath);
    }
}
