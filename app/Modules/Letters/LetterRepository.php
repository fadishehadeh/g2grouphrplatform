<?php

declare(strict_types=1);

namespace App\Modules\Letters;

use App\Core\Database;

final class LetterRepository
{
    private Database $database;

    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    public function hrAdminEmails(): array
    {
        return $this->database->fetchAll(
            "SELECT u.email, u.first_name, u.last_name
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE r.code IN ('super_admin','hr_only') AND u.status = 'active' AND u.email IS NOT NULL AND u.email != ''
             ORDER BY r.code ASC"
        );
    }

    public function employeeEmail(int $employeeId): ?string
    {
        $val = $this->database->fetchValue(
            'SELECT work_email FROM employees WHERE id = :id LIMIT 1',
            ['id' => $employeeId]
        );
        return ($val !== null && (string) $val !== '') ? (string) $val : null;
    }

    public function companyLogoPath(int $employeeId): ?string
    {
        $val = $this->database->fetchValue(
            'SELECT c.logo_path FROM employees e INNER JOIN companies c ON c.id = e.company_id WHERE e.id = :id LIMIT 1',
            ['id' => $employeeId]
        );
        return ($val !== null && (string) $val !== '') ? (string) $val : null;
    }

    public function createRequest(array $data, int $employeeId, int $requestedBy): int
    {
        $this->database->execute(
            'INSERT INTO letter_requests (employee_id, letter_type, purpose, notes, status, requested_by, created_at, updated_at)
             VALUES (:employee_id, :letter_type, :purpose, :notes, \'pending\', :requested_by, NOW(), NOW())',
            [
                'employee_id'  => $employeeId,
                'letter_type'  => $data['letter_type'],
                'purpose'      => ($data['purpose'] ?? '') !== '' ? $data['purpose'] : null,
                'notes'        => ($data['notes'] ?? '') !== '' ? $data['notes'] : null,
                'requested_by' => $requestedBy,
            ]
        );

        return (int) $this->database->fetchValue('SELECT LAST_INSERT_ID()');
    }

    public function myRequests(int $employeeId): array
    {
        return $this->database->fetchAll(
            'SELECT lr.id, lr.letter_type, lr.purpose, lr.notes, lr.status, lr.rejection_reason,
                    lr.generated_at, lr.created_at,
                    CONCAT(u.first_name, \' \', u.last_name) AS generated_by_name
             FROM letter_requests lr
             LEFT JOIN users u ON u.id = lr.generated_by
             WHERE lr.employee_id = :employee_id
             ORDER BY lr.created_at DESC',
            ['employee_id' => $employeeId]
        );
    }

    public function allRequests(string $status = '', string $letterType = ''): array
    {
        $where = ['1=1'];
        $params = [];

        if ($status !== '') {
            $where[] = 'lr.status = :status';
            $params['status'] = $status;
        }

        if ($letterType !== '') {
            $where[] = 'lr.letter_type = :letter_type';
            $params['letter_type'] = $letterType;
        }

        $sql = 'SELECT lr.id, lr.letter_type, lr.purpose, lr.notes, lr.status, lr.rejection_reason,
                       lr.salary_amount, lr.generated_at, lr.created_at,
                       CONCAT(e.first_name, \' \', e.last_name) AS employee_name,
                       e.employee_code,
                       COALESCE(jt.name, \'\') AS job_title_name,
                       COALESCE(d.name, \'\') AS department_name,
                       COALESCE(c.name, \'\') AS company_name,
                       CONCAT(u.first_name, \' \', u.last_name) AS generated_by_name
                FROM letter_requests lr
                INNER JOIN employees e ON e.id = lr.employee_id
                INNER JOIN companies c ON c.id = e.company_id
                LEFT JOIN job_titles jt ON jt.id = e.job_title_id
                LEFT JOIN departments d ON d.id = e.department_id
                LEFT JOIN users u ON u.id = lr.generated_by
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY lr.created_at DESC';

        return $this->database->fetchAll($sql, $params);
    }

    public function findRequest(int $id): ?array
    {
        return $this->database->fetch(
            'SELECT lr.id, lr.employee_id, lr.letter_type, lr.purpose, lr.notes, lr.status,
                    lr.rejection_reason, lr.salary_amount, lr.additional_info, lr.letter_content,
                    lr.generated_at, lr.requested_by, lr.created_at,
                    CONCAT(e.first_name, \' \', e.last_name) AS employee_name,
                    e.employee_code, e.joining_date, e.employment_type, e.contract_type,
                    e.nationality, e.work_email,
                    COALESCE(jt.name, \'\') AS job_title_name,
                    COALESCE(d.name, \'\') AS department_name,
                    COALESCE(c.name, \'\') AS company_name,
                    COALESCE(b.name, \'\') AS branch_name,
                    CONCAT(gb.first_name, \' \', gb.last_name) AS generated_by_name
             FROM letter_requests lr
             INNER JOIN employees e ON e.id = lr.employee_id
             INNER JOIN companies c ON c.id = e.company_id
             LEFT JOIN branches b ON b.id = e.branch_id
             LEFT JOIN job_titles jt ON jt.id = e.job_title_id
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN users gb ON gb.id = lr.generated_by
             WHERE lr.id = :id',
            ['id' => $id]
        ) ?: null;
    }

    public function generateLetter(int $id, string $letterContent, ?float $salaryAmount, ?string $additionalInfo, int $generatedBy): void
    {
        $this->database->execute(
            'UPDATE letter_requests
             SET status = \'approved\', letter_content = :letter_content,
                 salary_amount = :salary_amount, additional_info = :additional_info,
                 generated_by = :generated_by, generated_at = NOW(), updated_at = NOW()
             WHERE id = :id',
            [
                'id'              => $id,
                'letter_content'  => $letterContent,
                'salary_amount'   => $salaryAmount,
                'additional_info' => $additionalInfo,
                'generated_by'    => $generatedBy,
            ]
        );
    }

    public function rejectRequest(int $id, string $reason): void
    {
        $this->database->execute(
            'UPDATE letter_requests SET status = \'rejected\', rejection_reason = :reason, updated_at = NOW() WHERE id = :id',
            ['id' => $id, 'reason' => $reason]
        );
    }

    public function updateStatus(int $id, string $status): void
    {
        $allowed = ['pending', 'approved', 'rejected', 'cancelled'];
        if (!in_array($status, $allowed, true)) {
            throw new \InvalidArgumentException('Invalid status: ' . $status);
        }
        $this->database->execute(
            'UPDATE letter_requests SET status = :status, updated_at = NOW() WHERE id = :id',
            ['id' => $id, 'status' => $status]
        );
    }

    public function buildLetterContent(array $request, string $generatedByName): string
    {
        $employeeName   = htmlspecialchars((string) $request['employee_name'], ENT_QUOTES, 'UTF-8');
        $employeeCode   = htmlspecialchars((string) $request['employee_code'], ENT_QUOTES, 'UTF-8');
        $jobTitle       = htmlspecialchars((string) $request['job_title_name'], ENT_QUOTES, 'UTF-8');
        $department     = htmlspecialchars((string) $request['department_name'], ENT_QUOTES, 'UTF-8');
        $company        = htmlspecialchars((string) $request['company_name'], ENT_QUOTES, 'UTF-8');
        $branch         = htmlspecialchars((string) $request['branch_name'], ENT_QUOTES, 'UTF-8');
        $joiningDate    = !empty($request['joining_date'])
            ? date('d F Y', strtotime((string) $request['joining_date']))
            : 'N/A';
        $purpose        = !empty($request['purpose'])
            ? htmlspecialchars((string) $request['purpose'], ENT_QUOTES, 'UTF-8')
            : 'personal use';
        $employmentType = ucwords(str_replace('_', ' ', (string) ($request['employment_type'] ?? 'full_time')));
        $today          = date('d F Y');
        $signerName     = htmlspecialchars($generatedByName, ENT_QUOTES, 'UTF-8');
        $refNumber      = 'LTR-' . str_pad((string) $request['id'], 5, '0', STR_PAD_LEFT) . '-' . date('Y');
        $additionalInfo = !empty($request['additional_info'])
            ? '<p>' . nl2br(htmlspecialchars((string) $request['additional_info'], ENT_QUOTES, 'UTF-8')) . '</p>'
            : '';

        $letterType = (string) $request['letter_type'];

        $subject = match ($letterType) {
            'salary_certificate'      => 'SALARY CERTIFICATE',
            'employment_certificate'  => 'EMPLOYMENT CERTIFICATE',
            'experience_letter'       => 'EXPERIENCE LETTER',
            'noc'                     => 'NO OBJECTION CERTIFICATE',
            'bank_letter'             => 'BANK CONFIRMATION LETTER',
            default                   => 'LETTER',
        };

        $salaryFormatted = ($request['salary_amount'] ?? null) !== null && (float) $request['salary_amount'] > 0
            ? number_format((float) $request['salary_amount'], 2)
            : 'N/A';

        $savedTemplate = $this->getTemplate($letterType);
        $bodyTemplate  = $savedTemplate ?? self::defaultBody($letterType);

        $body = $this->applyPlaceholders($bodyTemplate, [
            'employee_name'   => $employeeName,
            'employee_code'   => $employeeCode,
            'job_title'       => $jobTitle,
            'department'      => $department,
            'company_name'    => $company,
            'branch'          => $branch,
            'joining_date'    => $joiningDate,
            'employment_type' => $employmentType,
            'purpose'         => $purpose,
            'salary_amount'   => $salaryFormatted,
            'additional_info' => $additionalInfo,
            'ref_number'      => $refNumber,
            'date'            => $today,
            'signer_name'     => $signerName,
        ]);

        // Company logo (base64 embed so it works in PDF too)
        $logoHtml = '';
        $logoPath = ($request['logo_path'] ?? '') !== '' ? (string) $request['logo_path'] : null;
        if ($logoPath !== null) {
            $absLogo = base_path('public-hr/' . ltrim($logoPath, '/'));
            if (is_file($absLogo)) {
                $mime = mime_content_type($absLogo) ?: 'image/png';
                $b64  = base64_encode((string) file_get_contents($absLogo));
                $logoHtml = '<img src="data:' . $mime . ';base64,' . $b64 . '" alt="Logo" style="max-height:60px;max-width:180px;object-fit:contain;">';
            }
        }

        return '
<div class="letter-paper">
    <div class="letter-header">
        <div class="letter-company">
            ' . ($logoHtml !== '' ? '<div class="mb-2">' . $logoHtml . '</div>' : '') . '
            ' . ($logoHtml === '' ? '<h2>' . $company . '</h2>' : '') . '
            ' . ($branch !== '' ? '<div class="text-muted">' . $branch . '</div>' : '') . '
        </div>
        <div class="letter-meta text-end">
            <div><strong>Ref:</strong> ' . $refNumber . '</div>
            <div><strong>Date:</strong> ' . $today . '</div>
        </div>
    </div>
    <hr class="letter-divider">
    <div class="letter-subject">
        <strong>Re: ' . $subject . '</strong>
    </div>
    <div class="letter-salutation">To Whom It May Concern,</div>
    <div class="letter-body">
        ' . $body . '
    </div>
    <div class="letter-closing">
        <p>Yours sincerely,</p>
        <div class="letter-signature">
            <div class="signature-line"></div>
            <div><strong>' . $signerName . '</strong></div>
            <div class="text-muted">Human Resources Department</div>
            <div class="text-muted">' . $company . '</div>
        </div>
    </div>
    <div class="letter-footer text-muted">
        <small>This letter is computer-generated and is valid without a physical signature unless stated otherwise.</small>
    </div>
</div>';
    }

    private function salaryBody(
        string $name, string $code, string $title, string $dept,
        string $joining, string $empType, string $purpose,
        array $request, string $extra
    ): string {
        $salary = ($request['salary_amount'] ?? null) !== null && (float) $request['salary_amount'] > 0
            ? 'a monthly basic salary of <strong>' . number_format((float) $request['salary_amount'], 2) . '</strong>'
            : 'a competitive salary package';

        return '
        <p>This is to certify that <strong>' . $name . '</strong> (Employee Code: ' . $code . ') is currently
        employed at our organisation as <strong>' . $title . '</strong> in the <strong>' . $dept . '</strong>
        department on a <strong>' . $empType . '</strong> basis.</p>
        <p>Their employment commenced on <strong>' . $joining . '</strong> and they currently receive ' . $salary . '.</p>
        ' . $extra . '
        <p>This certificate is issued upon the employee\'s request for the purpose of <strong>' . $purpose . '</strong>.</p>';
    }

    private function employmentBody(
        string $name, string $code, string $title, string $dept,
        string $joining, string $empType, string $purpose, string $extra
    ): string {
        return '
        <p>This is to certify that <strong>' . $name . '</strong> (Employee Code: ' . $code . ') is currently
        employed with our organisation as <strong>' . $title . '</strong> in the <strong>' . $dept . '</strong>
        department.</p>
        <p>Their employment commenced on <strong>' . $joining . '</strong> on a <strong>' . $empType . '</strong>
        basis and they remain an active member of our team.</p>
        ' . $extra . '
        <p>This certificate is issued upon the employee\'s request for the purpose of <strong>' . $purpose . '</strong>.</p>';
    }

    private function experienceBody(
        string $name, string $code, string $title, string $dept,
        string $joining, string $purpose, string $extra
    ): string {
        return '
        <p>This is to certify that <strong>' . $name . '</strong> (Employee Code: ' . $code . ') has been
        employed with our organisation from <strong>' . $joining . '</strong> to date, holding the position of
        <strong>' . $title . '</strong> in the <strong>' . $dept . '</strong> department.</p>
        <p>During their tenure, they have demonstrated professionalism, dedication, and a strong commitment to
        their responsibilities.</p>
        ' . $extra . '
        <p>We wish them continued success in all their future endeavours.</p>
        <p>This letter is issued at the employee\'s request for <strong>' . $purpose . '</strong>.</p>';
    }

    private function nocBody(
        string $name, string $title, string $joining, string $purpose, string $extra
    ): string {
        return '
        <p>This is to confirm that our organisation has <strong>no objection</strong> to
        <strong>' . $name . '</strong>, currently serving as <strong>' . $title . '</strong>
        with us since <strong>' . $joining . '</strong>, for the purpose of
        <strong>' . $purpose . '</strong>.</p>
        <p>Their employment status is currently active and in good standing.</p>
        ' . $extra . '
        <p>This No Objection Certificate is issued upon the employee\'s request.</p>';
    }

    private function bankBody(
        string $name, string $code, string $title, string $dept,
        string $joining, array $request, string $extra
    ): string {
        $salary = ($request['salary_amount'] ?? null) !== null && (float) $request['salary_amount'] > 0
            ? '<p>Their current monthly basic salary is <strong>' . number_format((float) $request['salary_amount'], 2) . '</strong>.</p>'
            : '';

        return '
        <p>This is to confirm that <strong>' . $name . '</strong> (Employee Code: ' . $code . ') is a current
        employee of our organisation, holding the position of <strong>' . $title . '</strong> in the
        <strong>' . $dept . '</strong> department.</p>
        <p>Their employment commenced on <strong>' . $joining . '</strong> and they remain an active employee
        in good standing.</p>
        ' . $salary . '
        ' . $extra . '
        <p>We request you to kindly extend all due courtesies to the bearer of this letter.</p>';
    }

    // -------------------------------------------------------------------------
    // Letter Templates
    // -------------------------------------------------------------------------

    public function getTemplate(string $letterType): ?string
    {
        $row = $this->database->fetch(
            'SELECT body_content FROM letter_templates WHERE letter_type = :type LIMIT 1',
            ['type' => $letterType]
        );
        return $row !== null ? (string) $row['body_content'] : null;
    }

    public function saveTemplate(string $letterType, string $bodyContent, int $updatedBy): void
    {
        $exists = $this->database->fetchValue(
            'SELECT id FROM letter_templates WHERE letter_type = :type LIMIT 1',
            ['type' => $letterType]
        );

        if ($exists !== null) {
            $this->database->execute(
                'UPDATE letter_templates SET body_content = :body, updated_by = :by, updated_at = NOW() WHERE letter_type = :type',
                ['body' => $bodyContent, 'by' => $updatedBy, 'type' => $letterType]
            );
        } else {
            $this->database->execute(
                'INSERT INTO letter_templates (letter_type, body_content, updated_by, updated_at) VALUES (:type, :body, :by, NOW())',
                ['type' => $letterType, 'body' => $bodyContent, 'by' => $updatedBy]
            );
        }
    }

    public static function defaultBody(string $letterType): string
    {
        return match ($letterType) {
            'salary_certificate' =>
                "<p>This is to certify that <strong>{{employee_name}}</strong> (Employee Code: {{employee_code}}) is currently employed at our organisation as <strong>{{job_title}}</strong> in the <strong>{{department}}</strong> department on a <strong>{{employment_type}}</strong> basis.</p>\n" .
                "<p>Their employment commenced on <strong>{{joining_date}}</strong> and they currently receive a monthly basic salary of <strong>{{salary_amount}}</strong>.</p>\n" .
                "{{additional_info}}\n" .
                "<p>This certificate is issued upon the employee's request for the purpose of <strong>{{purpose}}</strong>.</p>",

            'employment_certificate' =>
                "<p>This is to certify that <strong>{{employee_name}}</strong> (Employee Code: {{employee_code}}) is currently employed with our organisation as <strong>{{job_title}}</strong> in the <strong>{{department}}</strong> department.</p>\n" .
                "<p>Their employment commenced on <strong>{{joining_date}}</strong> on a <strong>{{employment_type}}</strong> basis and they remain an active member of our team.</p>\n" .
                "{{additional_info}}\n" .
                "<p>This certificate is issued upon the employee's request for the purpose of <strong>{{purpose}}</strong>.</p>",

            'experience_letter' =>
                "<p>This is to certify that <strong>{{employee_name}}</strong> (Employee Code: {{employee_code}}) has been employed with our organisation from <strong>{{joining_date}}</strong> to date, holding the position of <strong>{{job_title}}</strong> in the <strong>{{department}}</strong> department.</p>\n" .
                "<p>During their tenure, they have demonstrated professionalism, dedication, and a strong commitment to their responsibilities.</p>\n" .
                "{{additional_info}}\n" .
                "<p>We wish them continued success in all their future endeavours.</p>\n" .
                "<p>This letter is issued at the employee's request for <strong>{{purpose}}</strong>.</p>",

            'noc' =>
                "<p>This is to confirm that our organisation has <strong>no objection</strong> to <strong>{{employee_name}}</strong>, currently serving as <strong>{{job_title}}</strong> with us since <strong>{{joining_date}}</strong>, for the purpose of <strong>{{purpose}}</strong>.</p>\n" .
                "<p>Their employment status is currently active and in good standing.</p>\n" .
                "{{additional_info}}\n" .
                "<p>This No Objection Certificate is issued upon the employee's request.</p>",

            'bank_letter' =>
                "<p>This is to confirm that <strong>{{employee_name}}</strong> (Employee Code: {{employee_code}}) is a current employee of our organisation, holding the position of <strong>{{job_title}}</strong> in the <strong>{{department}}</strong> department.</p>\n" .
                "<p>Their employment commenced on <strong>{{joining_date}}</strong> and they remain an active employee in good standing.</p>\n" .
                "<p>Their current monthly basic salary is <strong>{{salary_amount}}</strong>.</p>\n" .
                "{{additional_info}}\n" .
                "<p>We request you to kindly extend all due courtesies to the bearer of this letter.</p>",

            default => "<p>This letter is issued to confirm the employment of <strong>{{employee_name}}</strong> at <strong>{{company_name}}</strong>.</p>",
        };
    }

    private function applyPlaceholders(string $template, array $vars): string
    {
        $search  = array_map(fn($k) => '{{' . $k . '}}', array_keys($vars));
        $replace = array_values($vars);
        return str_replace($search, $replace, $template);
    }

    public static function letterTypeLabel(string $type): string
    {
        return match ($type) {
            'salary_certificate'     => 'Salary Certificate',
            'employment_certificate' => 'Employment Certificate',
            'experience_letter'      => 'Experience Letter',
            'noc'                    => 'No Objection Certificate',
            'bank_letter'            => 'Bank Confirmation Letter',
            default                  => ucwords(str_replace('_', ' ', $type)),
        };
    }
}
