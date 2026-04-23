<?php

declare(strict_types=1);

namespace App\Modules\Employees;

use App\Core\Application;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Support\Branding;
use App\Support\EmailTemplate;
use App\Support\Mailer;
use Throwable;

final class EmployeeController extends Controller
{
    private EmployeeRepository $repository;

    public function __construct(Application $app)
    {
        parent::__construct($app);
        $this->repository = new EmployeeRepository($this->app->database());
    }

    public function index(Request $request): void
    {
        $search  = trim((string) $request->input('q', ''));
        $page    = max(1, (int) $request->input('page', 1));
        $perPage = 25;

        $employees  = [];
        $total      = 0;
        $totalPages = 1;

        try {
            $total      = $this->repository->countEmployees($search);
            $totalPages = (int) ceil($total / $perPage);
            $page       = min($page, max(1, $totalPages));
            $employees  = $this->repository->listEmployees($search, $page, $perPage);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load employees: ' . $throwable->getMessage());
        }

        $this->render('employees.index', [
            'title'      => 'Employees',
            'pageTitle'  => 'Employees',
            'employees'  => $employees,
            'search'     => $search,
            'page'       => $page,
            'perPage'    => $perPage,
            'total'      => $total,
            'totalPages' => $totalPages,
        ]);
    }

    public function create(Request $request): void
    {
        try {
            $options = $this->repository->formOptions();
            $defaults = ['employee_code' => $this->repository->nextEmployeeCode(), 'employment_type' => 'full_time', 'employee_status' => 'draft'];
        } catch (Throwable $throwable) {
            $options = $this->emptyOptions();
            $defaults = ['employee_code' => 'EMP-0001', 'employment_type' => 'full_time', 'employee_status' => 'draft'];
            $this->app->session()->flash('error', 'Unable to load employee setup lists: ' . $throwable->getMessage());
        }

        $this->render('employees.form', [
            'title' => 'Add Employee',
            'pageTitle' => 'Add Employee',
            'employee' => $defaults,
            'options' => $options,
            'formAction' => '/employees/create',
            'submitLabel' => 'Create Employee',
            'isEdit' => false,
        ]);
    }

    public function store(Request $request): void
    {
        $this->validateCsrf($request, '/employees/create');
        $data = $this->sanitized($request);

        if (($data['employee_code'] ?? '') === '') {
            $data['employee_code'] = $this->repository->nextEmployeeCode();
        }

        if (!$this->validate($data, '/employees/create')) {
            return;
        }

        try {
            $employeeId = $this->repository->createEmployee($data, $this->app->auth()->id());

            $photoPath = $this->handlePhotoUpload($request, $employeeId);
            if ($photoPath !== null) {
                $this->repository->updatePhotoPath($employeeId, $photoPath);
            }

            $contacts = $request->input('emergency_contacts', []);
            if (is_array($contacts) && count($contacts) > 0) {
                $this->repository->saveEmergencyContacts($employeeId, $contacts);
            }

            $this->app->session()->flash('success', 'Employee created successfully.');
            $this->redirect('/employees/' . $employeeId);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to save employee: ' . $throwable->getMessage());
            $this->app->session()->flash('old_input', $data);
            $this->redirect('/employees/create');
        }
    }

    public function show(Request $request, string $id): void
    {
        $employeeId = (int) $id;

        if (!$this->canView($employeeId)) {
            Response::abort(403, 'You do not have access to this employee profile.');
        }

        try {
            $employee = $this->repository->findEmployee($employeeId);

            if ($employee === null) {
                Response::abort(404, 'Employee not found.');
            }

            $contacts  = $this->repository->emergencyContacts($employeeId);
            $stats     = $this->repository->profileStats($employeeId);
            $insurance = $this->repository->findInsurance($employeeId);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load employee profile: ' . $throwable->getMessage());
            $this->redirect('/dashboard');
        }

        $this->auditLog('employees', 'employee', $employeeId, 'view', $request->ip(), $request->userAgent());

        $this->render('employees.show', [
            'title'     => 'Employee Profile',
            'pageTitle' => 'Employee Profile',
            'employee'  => $employee,
            'contacts'  => $contacts,
            'stats'     => $stats,
            'insurance' => $insurance ?? null,
        ]);
    }

    public function edit(Request $request, string $id): void
    {
        $employeeId = (int) $id;

        try {
            $employee = $this->repository->findEmployee($employeeId);

            if ($employee === null) {
                Response::abort(404, 'Employee not found.');
            }

            $options = $this->repository->formOptions($employeeId);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load employee details: ' . $throwable->getMessage());
            $this->redirect('/employees');
        }

        $emergencyContacts = $this->repository->emergencyContacts($employeeId);

        $this->render('employees.form', [
            'title'             => 'Edit Employee',
            'pageTitle'         => 'Edit Employee',
            'employee'          => $employee,
            'options'           => $options,
            'formAction'        => '/employees/' . $employeeId . '/edit',
            'submitLabel'       => 'Save Changes',
            'isEdit'            => true,
            'emergencyContacts' => $emergencyContacts,
        ]);
    }

    public function archive(Request $request, string $id): void
    {
        $employeeId = (int) $id;

        try {
            $employee = $this->repository->findEmployee($employeeId);

            if ($employee === null) {
                Response::abort(404, 'Employee not found.');
            }
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load employee archive details: ' . $throwable->getMessage());
            $this->redirect('/employees');
        }

        $this->render('employees.archive', [
            'title' => 'Archive Employee',
            'pageTitle' => 'Archive Employee',
            'employee' => $employee,
        ]);
    }

    public function update(Request $request, string $id): void
    {
        $employeeId = (int) $id;
        $this->validateCsrf($request, '/employees/' . $employeeId . '/edit');
        $data = $this->sanitized($request);

        if (!$this->validate($data, '/employees/' . $employeeId . '/edit')) {
            return;
        }

        try {
            $existing = $this->repository->findEmployee($employeeId);

            if ($existing === null) {
                Response::abort(404, 'Employee not found.');
            }

            $this->repository->updateEmployee($employeeId, $data, $this->app->auth()->id());

            $photoPath = $this->handlePhotoUpload($request, $employeeId);
            if ($photoPath !== null) {
                $this->repository->updatePhotoPath($employeeId, $photoPath);
            }

            $contacts = $request->input('emergency_contacts', []);
            if (is_array($contacts)) {
                $this->repository->saveEmergencyContacts($employeeId, $contacts);
            }

            $this->app->session()->flash('success', 'Employee updated successfully.');
            $this->redirect('/employees/' . $employeeId);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to update employee: ' . $throwable->getMessage());
            $this->app->session()->flash('old_input', $data);
            $this->redirect('/employees/' . $employeeId . '/edit');
        }
    }

    public function storeArchive(Request $request, string $id): void
    {
        $employeeId = (int) $id;
        $redirectPath = '/employees/' . $employeeId . '/archive';
        $this->validateCsrf($request, $redirectPath);
        $data = $this->sanitized($request);
        $remarks = trim((string) ($data['remarks'] ?? ''));

        if (strlen($remarks) > 255) {
            $this->app->session()->flash('error', 'Archive remarks must be 255 characters or fewer.');
            $this->app->session()->flash('old_input', $data);
            $this->redirect($redirectPath);
        }

        try {
            $employee = $this->repository->findEmployee($employeeId);

            if ($employee === null) {
                Response::abort(404, 'Employee not found.');
            }

            $archived = $this->repository->archiveEmployee($employeeId, $remarks, $this->app->auth()->id());

            if (!$archived) {
                $this->app->session()->flash('success', 'Employee is already archived.');
                $this->redirect('/employees/' . $employeeId . '/history');
            }

            $this->app->session()->flash('success', 'Employee archived successfully.');
            $this->redirect('/employees/' . $employeeId . '/history');
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to archive employee: ' . $throwable->getMessage());
            $this->app->session()->flash('old_input', $data);
            $this->redirect($redirectPath);
        }
    }

    public function history(Request $request, string $id): void
    {
        $employeeId = (int) $id;

        if (!$this->canView($employeeId)) {
            Response::abort(403, 'You do not have access to this employee history.');
        }

        try {
            $employee = $this->repository->findEmployee($employeeId);

            if ($employee === null) {
                Response::abort(404, 'Employee not found.');
            }

            $statusHistory = $this->repository->statusHistory($employeeId);
            $historyLogs = $this->repository->historyLogs($employeeId);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load employee history: ' . $throwable->getMessage());
            $this->redirect('/employees/' . $employeeId);
        }

        $this->render('employees.history', [
            'title' => 'Employee History',
            'pageTitle' => 'Employee History',
            'employee' => $employee,
            'statusHistory' => $statusHistory,
            'historyLogs' => $historyLogs,
        ]);
    }

    public function sendAccess(Request $request, string $id): void
    {
        $employeeId = (int) $id;
        $this->validateCsrf($request, '/employees/' . $employeeId);

        try {
            $employee = $this->repository->findEmployee($employeeId);

            if ($employee === null) {
                Response::abort(404, 'Employee not found.');
            }

            $email = (string) ($employee['work_email'] ?? '');

            if ($email === '') {
                $this->app->session()->flash('error', 'Employee has no work email address. Please add one before sending access.');
                $this->redirect('/employees/' . $employeeId);
            }

            $db = $this->app->database();
            $userId = !empty($employee['user_id']) ? (int) $employee['user_id'] : null;

            if ($userId === null) {
                // Create user account — username = email, password locked until they set it via link
                $employeeRole = $db->fetch("SELECT id FROM roles WHERE code = 'employee' LIMIT 1");
                $roleId = $employeeRole !== null ? (int) $employeeRole['id'] : 4;

                $db->execute(
                    'INSERT INTO users (role_id, username, email, password_hash, first_name, last_name, status, must_change_password, last_password_change_at)
                     VALUES (:role_id, :username, :email, :password_hash, :first_name, :last_name, :status, 1, :now)',
                    [
                        'role_id' => $roleId,
                        'username' => $email,
                        'email' => $email,
                        'password_hash' => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
                        'first_name' => (string) ($employee['first_name'] ?? ''),
                        'last_name' => (string) ($employee['last_name'] ?? ''),
                        'status' => 'active',
                        'now' => date('Y-m-d H:i:s'),
                    ]
                );

                $userId = (int) $db->lastInsertId();

                $db->execute(
                    'UPDATE employees SET user_id = :uid, updated_by = :actor WHERE id = :eid',
                    ['uid' => $userId, 'actor' => $this->app->auth()->id(), 'eid' => $employeeId]
                );
            } else {
                // Ensure username is synced to email
                $db->execute(
                    'UPDATE users SET username = :email WHERE id = :id',
                    ['email' => $email, 'id' => $userId]
                );
            }

            // Generate a set-password token (72h expiry)
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+72 hours'));
            $now = date('Y-m-d H:i:s');

            $db->execute(
                'UPDATE password_resets SET used_at = :now WHERE user_id = :uid AND used_at IS NULL',
                ['now' => $now, 'uid' => $userId]
            );
            $db->execute(
                'INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (:uid, :hash, :expires)',
                ['uid' => $userId, 'hash' => password_hash($token, PASSWORD_DEFAULT), 'expires' => $expiresAt]
            );

            $setPasswordLink = url('/reset-password/' . $token);
            $appName = Branding::appName();
            $firstName = (string) ($employee['first_name'] ?? 'Employee');

            $bodyHtml = EmailTemplate::accessInvitation(
                $firstName,
                $email,
                $setPasswordLink,
                '72 hours',
                Branding::companyLogoUrlForEmployee($employee)
            );

            $bodyText = "Welcome to {$appName}\n\nHello {$firstName},\n\n"
                . "Your account has been created. Set your password here:\n{$setPasswordLink}\n\n"
                . "Your login email: {$email}\n"
                . "This link expires in 72 hours.\n\nBest regards,\n{$appName}";

            $mailer = new Mailer((array) config('app.mail', []));
            $mailer->send($email, "Set up your {$appName} account", $bodyHtml, $bodyText);

            $this->app->session()->flash('success', 'Access invitation sent to ' . $email . '. Employee will set their own password via the link.');
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to send access credentials: ' . $throwable->getMessage());
        }

        $this->redirect('/employees/' . $employeeId);
    }

    public function orgChart(Request $request): void
    {
        $employees = [];

        try {
            $employees = $this->repository->orgChartData();
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load org chart data: ' . $throwable->getMessage());
        }

        // Build flat list for JS — resolve photo URLs here in PHP
        $nodes = [];
        foreach ($employees as $emp) {
            $photo = !empty($emp['profile_photo']) && is_file(base_path((string) $emp['profile_photo']))
                ? url('/' . ltrim((string) $emp['profile_photo'], '/'))
                : null;

            $nodes[] = [
                'id'         => (int) $emp['id'],
                'pid'        => $emp['manager_employee_id'] !== null ? (int) $emp['manager_employee_id'] : null,
                'name'       => (string) $emp['full_name'],
                'title'      => (string) ($emp['job_title'] ?? ''),
                'department' => (string) ($emp['department'] ?? ''),
                'status'     => (string) ($emp['employee_status'] ?? 'active'),
                'photo'      => $photo,
                'profileUrl' => url('/employees/' . (int) $emp['id']),
            ];
        }

        $this->render('employees.org-chart', [
            'title'     => 'Org Chart',
            'pageTitle' => 'Organisational Chart',
            'nodes'     => $nodes,
        ]);
    }

    public function destroy(Request $request, string $id): void
    {
        $employeeId = (int) $id;
        $this->validateCsrf($request, '/employees/' . $employeeId);

        if (!$this->app->auth()->hasPermission('employee.delete')) {
            Response::abort(403, 'You do not have permission to permanently delete employees.');
        }

        try {
            $employee = $this->repository->findEmployee($employeeId);
            if ($employee === null) {
                $this->app->session()->flash('error', 'Employee not found.');
                $this->redirect('/employees');
            }

            $expectedName = $this->employeeDisplayName($employee);
            $confirmedName = preg_replace('/\s+/', ' ', trim((string) $request->input('confirm_employee_name', '')));

            if ($confirmedName === '' || $confirmedName !== $expectedName) {
                $this->app->session()->flash('error', 'Delete confirmation failed. Please type the employee name exactly to continue.');
                $this->redirect('/employees/' . $employeeId);
            }

            $this->repository->deleteEmployee($employeeId);
            $this->app->session()->flash('success', 'Employee permanently deleted.');
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to delete employee: ' . $throwable->getMessage());
            $this->redirect('/employees/' . $employeeId);
        }

        $this->redirect('/employees');
    }

    public function saveInsurance(Request $request, string $id): void
    {
        $employeeId = (int) $id;
        $this->validateCsrf($request, '/employees/' . $employeeId);

        if (!$this->app->auth()->hasPermission('employee.edit')) {
            Response::abort(403, 'You do not have permission to update insurance records.');
        }

        try {
            $data = [];
            foreach ($request->all() as $key => $value) {
                if ($key === '_token') continue;
                $data[$key] = is_string($value) ? trim($value) : $value;
            }
            $this->repository->saveInsurance($employeeId, $data, $this->app->auth()->id());
            $this->app->session()->flash('success', 'Insurance details updated.');
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to save insurance: ' . $throwable->getMessage());
        }

        $this->redirect('/employees/' . $employeeId);
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

    private function validate(array $data, string $redirectPath): bool
    {
        foreach (['company_id', 'first_name', 'last_name', 'work_email', 'employment_type'] as $field) {
            if (($data[$field] ?? '') === '') {
                $this->app->session()->flash('error', 'Please complete all required employee fields.');
                $this->app->session()->flash('old_input', $data);
                $this->redirect($redirectPath);
            }
        }

        if (!filter_var((string) $data['work_email'], FILTER_VALIDATE_EMAIL)) {
            $this->app->session()->flash('error', 'Please provide a valid work email address.');
            $this->app->session()->flash('old_input', $data);
            $this->redirect($redirectPath);
        }

        if (($data['personal_email'] ?? '') !== '' && !filter_var((string) $data['personal_email'], FILTER_VALIDATE_EMAIL)) {
            $this->app->session()->flash('error', 'Please provide a valid personal email address.');
            $this->app->session()->flash('old_input', $data);
            $this->redirect($redirectPath);
        }

        return true;
    }

    private function canView(int $employeeId): bool
    {
        if ($this->app->auth()->hasPermission('employee.view_all')) {
            return true;
        }

        $user = $this->app->auth()->user() ?? [];

        return $this->app->auth()->hasPermission('employee.view_self')
            && (int) ($user['employee_id'] ?? 0) === $employeeId;
    }

    private function employeeDisplayName(array $employee): string
    {
        return preg_replace(
            '/\s+/',
            ' ',
            trim((string) (($employee['first_name'] ?? '') . ' ' . ($employee['middle_name'] ?? '') . ' ' . ($employee['last_name'] ?? '')))
        ) ?? '';
    }

    public function exportExcel(Request $request): void
    {
        try {
            $employees = $this->repository->exportEmployees();
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $spreadsheet->getProperties()
                ->setCreator(Branding::appName())
                ->setTitle('Employee Directory')
                ->setSubject('Employee Directory Export');

            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Employees');

            $headers = ['Employee Code', 'First Name', 'Middle Name', 'Last Name', 'Work Email', 'Personal Email',
                'Phone', 'Department', 'Job Title', 'Designation', 'Line Manager', 'Company', 'Branch', 'Team',
                'Employment Type', 'Employee Status', 'Nationality', 'Second Nationality', 'Gender',
                'Date of Birth', 'Joining Date', 'Marital Status', 'Notes'];

            $sheet->mergeCells('A1:W1');
            $sheet->mergeCells('A2:W2');
            $sheet->mergeCells('A3:W3');
            $sheet->setCellValue('A1', 'Employee Directory');
            $sheet->setCellValue('A2', Branding::appName() . ' | Generated ' . date('d M Y, H:i'));
            $sheet->setCellValue('A3', 'Total employees: ' . count($employees));
            $sheet->fromArray($headers, null, 'A5');

            $sheet->getRowDimension(1)->setRowHeight(28);
            $sheet->getRowDimension(2)->setRowHeight(20);
            $sheet->getRowDimension(3)->setRowHeight(20);
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(18)->getColor()->setRGB('111827');
            $sheet->getStyle('A2:A3')->getFont()->setSize(10)->getColor()->setRGB('64748B');
            $sheet->getStyle('A1:A3')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);

            $headerStyle = $sheet->getStyle('A5:W5');
            $headerStyle->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $headerStyle->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('FF3D33');
            $headerStyle->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
            $sheet->getRowDimension(5)->setRowHeight(24);

            $row = 6;
            foreach ($employees as $emp) {
                $sheet->fromArray([
                    $emp['employee_code'], $emp['first_name'], $emp['middle_name'] ?? '', $emp['last_name'],
                    $emp['work_email'], $emp['personal_email'] ?? '', $emp['phone'] ?? '',
                    $emp['department_name'] ?? '', $emp['job_title_name'] ?? '', $emp['designation_name'] ?? '',
                    $emp['manager_name'] ?? '', $emp['company_name'] ?? '', $emp['branch_name'] ?? '',
                    $emp['team_name'] ?? '', $emp['employment_type'] ?? '', $emp['employee_status'] ?? '',
                    $emp['nationality'] ?? '', $emp['second_nationality'] ?? '', $emp['gender'] ?? '',
                    $emp['date_of_birth'] ?? '', $emp['joining_date'] ?? '', $emp['marital_status'] ?? '',
                    $emp['notes'] ?? '',
                ], null, 'A' . $row);

                if ($row % 2 === 0) {
                    $sheet->getStyle('A' . $row . ':W' . $row)
                        ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('F8FAFC');
                }

                $status = strtolower((string) ($emp['employee_status'] ?? ''));
                $statusColor = match ($status) {
                    'active' => 'DCFCE7',
                    'draft' => 'F1F5F9',
                    'on_leave' => 'FEF3C7',
                    'terminated', 'resigned' => 'FEE2E2',
                    default => 'FFFFFF',
                };
                $sheet->getStyle('P' . $row)->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB($statusColor);
                $row++;
            }

            $lastRow = max(6, $row - 1);
            $sheet->freezePane('A6');
            $sheet->setAutoFilter('A5:W' . $lastRow);
            $sheet->getStyle('A5:W' . $lastRow)->getBorders()->getAllBorders()
                ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)
                ->getColor()->setRGB('E2E8F0');
            $sheet->getStyle('A6:W' . $lastRow)->getAlignment()
                ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP)
                ->setWrapText(true);

            foreach (range('A', 'W') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="employees_' . date('Y-m-d') . '.xlsx"');
            header('Cache-Control: max-age=0');
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
            exit;
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Export failed: ' . $throwable->getMessage());
            $this->redirect('/employees');
        }
    }

    public function exportPdf(Request $request): void
    {
        try {
            $employees = $this->repository->exportEmployees();
            $pdf = new \TCPDF('L', 'mm', 'A3', true, 'UTF-8', false);
            $pdf->SetCreator('HR System');
            $pdf->SetTitle('Employee Directory');
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(true);
            $pdf->SetMargins(12, 14, 12);
            $pdf->SetAutoPageBreak(true, 15);
            $pdf->AddPage();

            $logoPath = Branding::defaultLogoPath();
            if ($logoPath !== null && strtolower(pathinfo($logoPath, PATHINFO_EXTENSION)) !== 'svg') {
                $pdf->Image($logoPath, 14, 13, 26, 0, '', '', '', false, 300);
            } else {
                $logoPath = null;
            }

            $pdf->SetFont('helvetica', 'B', 16);
            $pdf->SetTextColor(17, 24, 39);
            $pdf->SetXY($logoPath !== null ? 44 : 14, 14);
            $pdf->Cell(0, 7, 'Employee Directory', 0, 1, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->SetTextColor(100, 116, 139);
            $pdf->SetX($logoPath !== null ? 44 : 14);
            $pdf->Cell(0, 6, Branding::appName() . ' | Generated ' . date('d M Y, H:i') . ' | Total employees: ' . count($employees), 0, 1, 'L');
            $pdf->SetDrawColor(255, 61, 51);
            $pdf->SetLineWidth(0.8);
            $pdf->Line(14, 33, 406, 33);
            $pdf->Ln(10);

            $html = '<style>
                table.employee-export { border-collapse: collapse; font-size: 8px; color: #111827; }
                table.employee-export th { background-color: #ff3d33; color: #ffffff; font-weight: bold; border: 1px solid #ff3d33; }
                table.employee-export td { border: 1px solid #e2e8f0; }
                tr.alt td { background-color: #f8fafc; }
            </style>
            <table class="employee-export" cellpadding="5" cellspacing="0">
            <thead><tr>
                <th>Code</th><th>Name</th><th>Work Email</th><th>Department</th><th>Job Title</th>
                <th>Line Manager</th><th>Status</th><th>Joining Date</th><th>Phone</th><th>Nationality</th>
            </tr></thead><tbody>';

            $i = 0;
            foreach ($employees as $emp) {
                $name = trim(($emp['first_name'] ?? '') . ' ' . ($emp['middle_name'] ?? '') . ' ' . ($emp['last_name'] ?? ''));
                $status = strtolower((string) ($emp['employee_status'] ?? ''));
                $statusBg = match ($status) {
                    'active' => '#dcfce7',
                    'draft' => '#f1f5f9',
                    'on_leave' => '#fef3c7',
                    'terminated', 'resigned' => '#fee2e2',
                    default => '#ffffff',
                };
                $html .= '<tr' . ($i % 2 === 1 ? ' class="alt"' : '') . '>
                    <td>' . htmlspecialchars($emp['employee_code'] ?? '') . '</td>
                    <td>' . htmlspecialchars($name) . '</td>
                    <td>' . htmlspecialchars($emp['work_email'] ?? '') . '</td>
                    <td>' . htmlspecialchars($emp['department_name'] ?? '—') . '</td>
                    <td>' . htmlspecialchars($emp['job_title_name'] ?? '—') . '</td>
                    <td>' . htmlspecialchars($emp['manager_name'] ?? '—') . '</td>
                    <td style="background-color:' . $statusBg . ';">' . htmlspecialchars($emp['employee_status'] ?? '') . '</td>
                    <td>' . htmlspecialchars($emp['joining_date'] ?? '—') . '</td>
                    <td>' . htmlspecialchars($emp['phone'] ?? '—') . '</td>
                    <td>' . htmlspecialchars($emp['nationality'] ?? '—') . '</td>
                </tr>';
                $i++;
            }
            $html .= '</tbody></table>';

            $pdf->SetFont('helvetica', '', 8);
            $pdf->writeHTML($html, true, false, true, false, '');

            $pdf->Output('employees_' . date('Y-m-d') . '.pdf', 'D');
            exit;
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'PDF export failed: ' . $throwable->getMessage());
            $this->redirect('/employees');
        }
    }

    public function importForm(Request $request): void
    {
        $this->render('employees.import', [
            'title' => 'Import Employees',
            'pageTitle' => 'Import Employees',
        ]);
    }

    public function downloadTemplate(Request $request): void
    {
        try {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Employee Import Template');

            $headers = ['employee_code', 'first_name', 'middle_name', 'last_name', 'work_email', 'personal_email',
                'phone', 'department', 'job_title', 'designation', 'manager_employee_code', 'company', 'branch', 'team',
                'employment_type', 'employee_status', 'nationality', 'second_nationality', 'gender',
                'date_of_birth', 'joining_date', 'marital_status', 'notes'];

            $sheet->fromArray($headers, null, 'A1');
            $headerStyle = $sheet->getStyle('A1:W1');
            $headerStyle->getFont()->setBold(true);
            $headerStyle->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('FF3D33');
            $headerStyle->getFont()->getColor()->setRGB('FFFFFF');

            // Add sample row
            $sheet->fromArray(['EMP-0001', 'John', '', 'Doe', 'john.doe@company.com', '', '+971501234567',
                'IT', 'Software Engineer', '', '', 'My Company', '', '', 'full_time', 'active',
                'United Arab Emirates', '', 'male', '1990-01-15', '2025-01-01', 'single', ''], null, 'A2');

            // Add a guide sheet
            $guide = $spreadsheet->createSheet();
            $guide->setTitle('Guide');
            $guide->setCellValue('A1', 'Field');
            $guide->setCellValue('B1', 'Required');
            $guide->setCellValue('C1', 'Description');
            $guide->getStyle('A1:C1')->getFont()->setBold(true);

            $guideData = [
                ['employee_code', 'YES', 'Unique code e.g. EMP-0001. Must not already exist.'],
                ['first_name', 'YES', 'Employee first name'],
                ['middle_name', 'No', 'Employee middle name (optional)'],
                ['last_name', 'YES', 'Employee last name'],
                ['work_email', 'YES', 'Unique work email address'],
                ['personal_email', 'No', 'Personal email (optional)'],
                ['phone', 'No', 'Phone number (optional)'],
                ['department', 'No', 'Department name — must match an existing department exactly'],
                ['job_title', 'No', 'Job title name — must match an existing job title exactly'],
                ['designation', 'No', 'Designation name — must match an existing designation exactly'],
                ['manager_employee_code', 'No', 'Employee code of the line manager (e.g. EMP-0001)'],
                ['company', 'YES', 'Company name — must match an existing company exactly'],
                ['branch', 'No', 'Branch name — must match an existing branch exactly'],
                ['team', 'No', 'Team name — must match an existing team exactly'],
                ['employment_type', 'YES', 'full_time, part_time, contract, intern, temporary'],
                ['employee_status', 'No', 'draft, active, on_leave, terminated, resigned (default: draft)'],
                ['nationality', 'No', 'Country name e.g. United Arab Emirates'],
                ['second_nationality', 'No', 'Second nationality (optional)'],
                ['gender', 'No', 'male, female, other'],
                ['date_of_birth', 'No', 'Format: YYYY-MM-DD'],
                ['joining_date', 'No', 'Format: YYYY-MM-DD'],
                ['marital_status', 'No', 'single, married, divorced, widowed'],
                ['notes', 'No', 'Any additional notes'],
            ];
            $guide->fromArray($guideData, null, 'A2');
            foreach (['A', 'B', 'C'] as $col) {
                $guide->getColumnDimension($col)->setAutoSize(true);
            }

            $spreadsheet->setActiveSheetIndex(0);

            foreach (range('A', 'W') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="employee_import_template.xlsx"');
            header('Cache-Control: max-age=0');
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
            exit;
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Template download failed: ' . $throwable->getMessage());
            $this->redirect('/employees/import');
        }
    }

    public function import(Request $request): void
    {
        $this->validateCsrf($request, '/employees/import');

        $file = $request->file('import_file');

        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->app->session()->flash('error', 'Please select a valid Excel file to upload.');
            $this->redirect('/employees/import');
        }

        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xls'], true)) {
            $this->app->session()->flash('error', 'Only .xlsx and .xls files are supported.');
            $this->redirect('/employees/import');
        }

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file['tmp_name']);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);

            if (count($rows) < 2) {
                $this->app->session()->flash('error', 'The file appears to be empty. Please add employee data below the header row.');
                $this->redirect('/employees/import');
            }

            // Parse header row
            $headerRow = array_map(fn($v) => strtolower(trim((string) $v)), $rows[1] ?? []);
            unset($rows[1]);

            // Build lookup maps
            $lookups = $this->repository->importLookups();

            $errors = [];
            $created = 0;
            $rowNum = 1;

            foreach ($rows as $row) {
                $rowNum++;
                $mapped = [];
                foreach ($headerRow as $col => $field) {
                    $mapped[$field] = trim((string) ($row[$col] ?? ''));
                }

                // Validate required fields
                $reqErrors = [];
                foreach (['employee_code', 'first_name', 'last_name', 'work_email', 'company', 'employment_type'] as $req) {
                    if (($mapped[$req] ?? '') === '') {
                        $reqErrors[] = $req;
                    }
                }
                if ($reqErrors !== []) {
                    $errors[] = "Row {$rowNum}: Missing required fields: " . implode(', ', $reqErrors);
                    continue;
                }

                // Resolve lookups
                $companyId = $lookups['companies'][strtolower($mapped['company'])] ?? null;
                if ($companyId === null) {
                    $errors[] = "Row {$rowNum}: Company \"{$mapped['company']}\" not found.";
                    continue;
                }

                $data = [
                    'employee_code' => $mapped['employee_code'],
                    'company_id' => $companyId,
                    'branch_id' => $lookups['branches'][strtolower($mapped['branch'] ?? '')] ?? null,
                    'department_id' => $lookups['departments'][strtolower($mapped['department'] ?? '')] ?? null,
                    'team_id' => $lookups['teams'][strtolower($mapped['team'] ?? '')] ?? null,
                    'job_title_id' => $lookups['job_titles'][strtolower($mapped['job_title'] ?? '')] ?? null,
                    'designation_id' => $lookups['designations'][strtolower($mapped['designation'] ?? '')] ?? null,
                    'manager_employee_id' => $lookups['employees'][strtoupper($mapped['manager_employee_code'] ?? '')] ?? null,
                    'first_name' => $mapped['first_name'],
                    'middle_name' => $mapped['middle_name'] ?? '',
                    'last_name' => $mapped['last_name'],
                    'work_email' => $mapped['work_email'],
                    'personal_email' => $mapped['personal_email'] ?? '',
                    'phone' => $mapped['phone'] ?? '',
                    'alternate_phone' => '',
                    'date_of_birth' => $mapped['date_of_birth'] ?? '',
                    'gender' => $mapped['gender'] ?? '',
                    'marital_status' => $mapped['marital_status'] ?? '',
                    'nationality' => $mapped['nationality'] ?? '',
                    'second_nationality' => $mapped['second_nationality'] ?? '',
                    'employment_type' => $mapped['employment_type'],
                    'contract_type' => '',
                    'joining_date' => $mapped['joining_date'] ?? '',
                    'probation_start_date' => '',
                    'probation_end_date' => '',
                    'employee_status' => ($mapped['employee_status'] ?? '') !== '' ? $mapped['employee_status'] : 'draft',
                    'notes' => $mapped['notes'] ?? '',
                ];

                try {
                    $this->repository->createEmployee($data, $this->app->auth()->id());
                    $created++;
                } catch (Throwable $e) {
                    $errors[] = "Row {$rowNum}: " . $e->getMessage();
                }
            }

            if ($created > 0) {
                $this->app->session()->flash('success', "{$created} employee(s) imported successfully." . ($errors !== [] ? ' Some rows had errors.' : ''));
            }
            if ($errors !== []) {
                $this->app->session()->flash('import_errors', $errors);
                if ($created === 0) {
                    $this->app->session()->flash('error', 'No employees were imported. Please review the errors below.');
                }
            }

            $this->redirect($created > 0 && $errors === [] ? '/employees' : '/employees/import');
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Import failed: ' . $throwable->getMessage());
            $this->redirect('/employees/import');
        }
    }

    private function emptyOptions(): array
    {
        return [
            'companies' => [],
            'branches' => [],
            'departments' => [],
            'teams' => [],
            'job_titles' => [],
            'designations' => [],
            'managers' => [],
        ];
    }

    private function handlePhotoUpload(Request $request, int $employeeId): ?string
    {
        $file = $request->file('profile_photo');

        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }

        $allowedExt = ['png', 'jpg', 'jpeg', 'gif'];
        $ext = strtolower(pathinfo(basename((string) ($file['name'] ?? '')), PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExt, true)) {
            throw new \RuntimeException('Profile photo must be PNG or JPG.');
        }

        if ((int) ($file['size'] ?? 0) > 2 * 1024 * 1024) {
            throw new \RuntimeException('Profile photo must be 2 MB or smaller.');
        }

        $dir = base_path('storage/uploads/photos');
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Unable to create photo directory.');
        }

        $storedName = 'employee_' . $employeeId . '_' . date('YmdHis') . '.' . $ext;
        $relativePath = 'storage/uploads/photos/' . $storedName;

        if (!move_uploaded_file((string) ($file['tmp_name'] ?? ''), base_path($relativePath))) {
            throw new \RuntimeException('Unable to save profile photo.');
        }

        return $relativePath;
    }
}
