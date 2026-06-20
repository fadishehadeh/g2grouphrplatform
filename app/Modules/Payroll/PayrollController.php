<?php

declare(strict_types=1);

namespace App\Modules\Payroll;

use App\Core\Application;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Support\Branding;
use Throwable;

final class PayrollController extends Controller
{
    private PayrollRepository $repository;

    public function __construct(Application $app)
    {
        parent::__construct($app);
        $this->repository = new PayrollRepository($this->app->database());
    }

    // ------------------------------------------------------------------
    // Salary structures
    // ------------------------------------------------------------------

    public function salaryStructures(Request $request): void
    {
        $companyId = (int) $request->input('company_id', 0);
        $search    = trim((string) $request->input('q', ''));

        try {
            $companies = $this->repository->companyOptions();
            if ($companyId === 0 && !empty($companies)) {
                $companyId = (int) $companies[0]['id'];
            }
            $employees = $companyId > 0 ? $this->repository->employeeSalaryList($companyId, $search) : [];
        } catch (Throwable $e) {
            $this->app->session()->flash('error', 'Unable to load salary data: ' . $e->getMessage());
            $companies = [];
            $employees = [];
        }

        $this->render('payroll.salary-structures', [
            'title'     => 'Salary Structures',
            'pageTitle' => 'Salary Structures',
            'companies' => $companies,
            'employees' => $employees,
            'companyId' => $companyId,
            'search'    => $search,
        ]);
    }

    public function editSalaryStructure(Request $request, string $id): void
    {
        $employeeId = (int) $id;

        try {
            $employee  = $this->repository->findEmployee($employeeId);
            if ($employee === null) {
                $this->app->session()->flash('error', 'Employee not found.');
                $this->redirect('/payroll/salary-structures');
            }
            $current = $this->repository->activeSalaryStructure($employeeId);
            $history = $this->repository->salaryStructureHistory($employeeId);
        } catch (Throwable $e) {
            $this->app->session()->flash('error', 'Unable to load salary structure: ' . $e->getMessage());
            $this->redirect('/payroll/salary-structures');
        }

        $this->render('payroll.salary-structure-edit', [
            'title'      => 'Edit Salary Structure',
            'pageTitle'  => 'Edit Salary Structure',
            'employee'   => $employee,
            'current'    => $current ?? null,
            'history'    => $history ?? [],
        ]);
    }

    public function saveSalaryStructure(Request $request, string $id): void
    {
        $employeeId   = (int) $id;
        $redirectPath = '/payroll/salary-structures/' . $employeeId . '/edit';
        $this->validateCsrf($request, $redirectPath);

        $data = [
            'employee_id'         => $employeeId,
            'basic_salary'        => $request->input('basic_salary', ''),
            'housing_allowance'   => $request->input('housing_allowance', ''),
            'transport_allowance' => $request->input('transport_allowance', ''),
            'other_allowances'    => $request->input('other_allowances', ''),
            'currency'            => trim((string) $request->input('currency', 'QAR')),
            'effective_from'      => trim((string) $request->input('effective_from', '')),
        ];

        if ($data['basic_salary'] === '' || !is_numeric($data['basic_salary'])) {
            $this->app->session()->flash('error', 'Basic salary is required and must be a number.');
            $this->redirect($redirectPath);
        }

        if ($data['effective_from'] === '') {
            $this->app->session()->flash('error', 'Effective from date is required.');
            $this->redirect($redirectPath);
        }

        try {
            $employee = $this->repository->findEmployee($employeeId);
            if ($employee === null) {
                $this->app->session()->flash('error', 'Employee not found.');
                $this->redirect('/payroll/salary-structures');
            }

            $this->repository->saveSalaryStructure($data, $this->app->auth()->id());

            $this->auditLog('payroll', 'salary_structure', $employeeId, 'created');
            $this->app->session()->flash('success', 'Salary structure saved successfully.');
        } catch (Throwable $e) {
            $this->app->session()->flash('error', 'Unable to save salary structure: ' . $e->getMessage());
        }

        $this->redirect($redirectPath);
    }

    // ------------------------------------------------------------------
    // Payroll runs
    // ------------------------------------------------------------------

    public function runs(Request $request): void
    {
        $companyId = (int) $request->input('company_id', 0);

        try {
            $companies = $this->repository->companyOptions();
            if ($companyId === 0 && !empty($companies)) {
                $companyId = (int) $companies[0]['id'];
            }
            $runs = $companyId > 0 ? $this->repository->listRuns($companyId) : [];
        } catch (Throwable $e) {
            $this->app->session()->flash('error', 'Unable to load payroll runs: ' . $e->getMessage());
            $companies = [];
            $runs      = [];
        }

        $this->render('payroll.runs', [
            'title'     => 'Payroll Runs',
            'pageTitle' => 'Payroll Runs',
            'companies' => $companies,
            'runs'      => $runs,
            'companyId' => $companyId,
        ]);
    }

    public function createRunForm(Request $request): void
    {
        try {
            $companies = $this->repository->companyOptions();
        } catch (Throwable $e) {
            $this->app->session()->flash('error', 'Unable to load companies: ' . $e->getMessage());
            $companies = [];
        }

        $this->render('payroll.run-create', [
            'title'     => 'Create Payroll Run',
            'pageTitle' => 'Create Payroll Run',
            'companies' => $companies,
        ]);
    }

    public function createRun(Request $request): void
    {
        $redirectPath = '/payroll/runs/create';
        $this->validateCsrf($request, $redirectPath);

        $companyId = (int) $request->input('company_id', 0);
        $month     = (int) $request->input('month', 0);
        $year      = (int) $request->input('year', 0);

        if ($companyId === 0) {
            $this->app->session()->flash('error', 'Please select a company.');
            $this->redirect($redirectPath);
        }

        if ($month < 1 || $month > 12) {
            $this->app->session()->flash('error', 'Please select a valid month.');
            $this->redirect($redirectPath);
        }

        if ($year < 2020 || $year > 2100) {
            $this->app->session()->flash('error', 'Please enter a valid year.');
            $this->redirect($redirectPath);
        }

        try {
            $runId = $this->repository->createDraftRun($companyId, $month, $year, $this->app->auth()->id());
            $this->auditLog('payroll', 'payroll_run', $runId, 'created');
            $this->app->session()->flash('success', 'Payroll run created successfully.');
            $this->redirect('/payroll/runs/' . $runId);
        } catch (Throwable $e) {
            $this->app->session()->flash('error', 'Unable to create payroll run: ' . $e->getMessage());
            $this->redirect($redirectPath);
        }
    }

    public function runDetail(Request $request, string $id): void
    {
        $runId = (int) $id;

        try {
            $data = $this->repository->findRunWithItems($runId);
            if (empty($data)) {
                $this->app->session()->flash('error', 'Payroll run not found.');
                $this->redirect('/payroll/runs');
            }
        } catch (Throwable $e) {
            $this->app->session()->flash('error', 'Unable to load payroll run: ' . $e->getMessage());
            $this->redirect('/payroll/runs');
        }

        $this->render('payroll.run-detail', [
            'title'     => 'Payroll Run — ' . $this->periodLabel((int) $data['run']['period_month'], (int) $data['run']['period_year']),
            'pageTitle' => 'Payroll Run',
            'run'       => $data['run'],
            'items'     => $data['items'],
        ]);
    }

    public function updateRunItem(Request $request, string $id): void
    {
        $itemId       = (int) $id;
        $redirectPath = '/payroll/runs';
        $this->validateCsrf($request, $redirectPath);

        $fields = [
            'basic_salary'        => $request->input('basic_salary'),
            'housing_allowance'   => $request->input('housing_allowance'),
            'transport_allowance' => $request->input('transport_allowance'),
            'other_allowances'    => $request->input('other_allowances'),
            'deductions'          => $request->input('deductions'),
            'notes'               => $request->input('notes'),
        ];

        try {
            $item = $this->repository->findRunItem($itemId);
            if ($item === null) {
                $this->app->session()->flash('error', 'Line item not found.');
                $this->redirect($redirectPath);
            }

            $redirectPath = '/payroll/runs/' . $item['payroll_run_id'];
            $this->repository->updateRunItem($itemId, $fields, $this->app->auth()->id());
            $this->auditLog('payroll', 'payroll_run_item', $itemId, 'adjusted');
            $this->app->session()->flash('success', 'Line item updated.');
        } catch (Throwable $e) {
            $this->app->session()->flash('error', 'Unable to update line item: ' . $e->getMessage());
        }

        $this->redirect($redirectPath);
    }

    public function finalizeRunForm(Request $request, string $id): void
    {
        $runId = (int) $id;

        try {
            $data = $this->repository->findRunWithItems($runId);
            if (empty($data)) {
                $this->app->session()->flash('error', 'Payroll run not found.');
                $this->redirect('/payroll/runs');
            }
        } catch (Throwable $e) {
            $this->app->session()->flash('error', 'Unable to load payroll run: ' . $e->getMessage());
            $this->redirect('/payroll/runs');
        }

        if ($data['run']['status'] === 'finalized') {
            $this->app->session()->flash('error', 'This payroll run is already finalised.');
            $this->redirect('/payroll/runs/' . $runId);
        }

        $this->render('payroll.run-finalize', [
            'title'     => 'Finalise Payroll Run',
            'pageTitle' => 'Finalise Payroll Run',
            'run'       => $data['run'],
            'items'     => $data['items'],
        ]);
    }

    public function finalizeRun(Request $request, string $id): void
    {
        $runId        = (int) $id;
        $redirectPath = '/payroll/runs/' . $runId;
        $this->validateCsrf($request, $redirectPath);

        try {
            $this->repository->finalizeRun($runId, $this->app->auth()->id());
            $this->auditLog('payroll', 'payroll_run', $runId, 'finalized');
            $this->app->session()->flash('success', 'Payroll run has been finalised.');
        } catch (Throwable $e) {
            $this->app->session()->flash('error', 'Unable to finalise run: ' . $e->getMessage());
        }

        $this->redirect($redirectPath);
    }

    public function downloadPayslip(Request $request, string $runId, string $empId): void
    {
        $runIdInt = (int) $runId;
        $empIdInt = (int) $empId;

        try {
            $item = $this->repository->findRunItemByEmployeeAndRun($runIdInt, $empIdInt);
            if ($item === null) {
                Response::abort(404, 'Payslip not found.');
            }
        } catch (Throwable $e) {
            Response::abort(500, 'Unable to generate payslip: ' . $e->getMessage());
        }

        $month  = (int) $item['period_month'];
        $year   = (int) $item['period_year'];
        $period = $this->periodLabel($month, $year);

        $logoUrl     = Branding::logoUrl();
        $orgName     = e(Branding::name());
        $empName     = e((string) $item['employee_name']);
        $empCode     = e((string) $item['employee_code']);
        $department  = e((string) ($item['department_name'] ?? ''));
        $jobTitle    = e((string) ($item['job_title_name'] ?? ''));
        $company     = e((string) $item['company_name']);
        $periodLabel = e($period);
        $generated   = e(date('d M Y'));

        $basic     = number_format((float) $item['basic_salary'], 2);
        $housing   = number_format((float) $item['housing_allowance'], 2);
        $transport = number_format((float) $item['transport_allowance'], 2);
        $other     = number_format((float) $item['other_allowances'], 2);
        $deductions = number_format((float) $item['deductions'], 2);
        $gross     = number_format((float) $item['gross_total'], 2);
        $net       = number_format((float) $item['net_total'], 2);
        $currency  = 'QAR';

        $isAdjusted = (bool) $item['is_manually_adjusted'];
        $notes      = $item['notes'] ? e((string) $item['notes']) : '';
        $finalisedAt = !empty($item['finalized_at']) ? e(date('d M Y', strtotime((string) $item['finalized_at']))) : '';

        $html = <<<HTML
<style>
body{font-family:helvetica;font-size:10pt;color:#212529}
h1{font-size:18pt;color:#1a1a2e;margin:0 0 2px}
h2{font-size:12pt;color:#555;margin:0 0 12px;font-weight:normal}
table{width:100%;border-collapse:collapse;margin-top:8px}
th{background:#1a1a2e;color:#fff;padding:7px 10px;text-align:left;font-size:9pt}
td{padding:6px 10px;border-bottom:1px solid #e9ecef;font-size:9pt}
.total-row td{font-weight:bold;background:#f8f9fa}
.net-row td{font-weight:bold;background:#1a1a2e;color:#fff;font-size:10pt}
.section-header{background:#eef2ff;font-weight:bold}
.badge-adjusted{background:#fff3cd;color:#664d03;padding:2px 6px;border-radius:3px;font-size:8pt}
.footer{margin-top:20px;font-size:8pt;color:#888;border-top:1px solid #dee2e6;padding-top:8px}
</style>

<table style="margin-bottom:16px;border:none">
<tr>
<td style="border:none;width:60%;vertical-align:top">
<h1>PAYSLIP</h1>
<h2>{$orgName} &mdash; {$periodLabel}</h2>
</td>
<td style="border:none;width:40%;text-align:right;vertical-align:top">
<img src="{$logoUrl}" style="max-height:50px;max-width:160px;" alt="{$orgName}">
</td>
</tr>
</table>

<table style="margin-bottom:16px;border:none">
<tr>
<td style="border:none;width:50%;vertical-align:top">
<strong>Employee</strong><br>
{$empName}<br>
<small style="color:#666">Code: {$empCode}</small>
</td>
<td style="border:none;width:50%;vertical-align:top">
<strong>Department / Role</strong><br>
{$department}<br>
<small style="color:#666">{$jobTitle}</small>
</td>
</tr>
<tr>
<td style="border:none;padding-top:8px"><strong>Company</strong><br>{$company}</td>
<td style="border:none;padding-top:8px"><strong>Period</strong><br>{$periodLabel}</td>
</tr>
</table>

<table>
<tr><th>Component</th><th style="text-align:right">Amount ({$currency})</th></tr>
<tr class="section-header"><td colspan="2">Earnings</td></tr>
<tr><td>Basic Salary</td><td style="text-align:right">{$basic}</td></tr>
<tr><td>Housing Allowance</td><td style="text-align:right">{$housing}</td></tr>
<tr><td>Transport Allowance</td><td style="text-align:right">{$transport}</td></tr>
<tr><td>Other Allowances</td><td style="text-align:right">{$other}</td></tr>
<tr class="total-row"><td>Gross Total</td><td style="text-align:right">{$gross}</td></tr>
<tr class="section-header"><td colspan="2">Deductions</td></tr>
<tr><td>Unpaid Leave / Other Deductions</td><td style="text-align:right">{$deductions}</td></tr>
<tr class="net-row"><td>NET PAY</td><td style="text-align:right">{$net}</td></tr>
</table>
HTML;

        if ($isAdjusted) {
            $html .= '<p style="margin-top:10px;font-size:8.5pt;color:#664d03;background:#fff3cd;padding:6px 10px;border-radius:3px;">&#9888; This payslip was manually adjusted before finalisation.' . ($notes !== '' ? ' Note: ' . $notes : '') . '</p>';
        }

        if ($finalisedAt !== '') {
            $html .= '<p class="footer">Finalised: ' . $finalisedAt . ' &nbsp;|&nbsp; Generated: ' . $generated . ' &nbsp;|&nbsp; Confidential</p>';
        } else {
            $html .= '<p class="footer">Generated: ' . $generated . ' &nbsp;|&nbsp; Confidential</p>';
        }

        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator($orgName);
        $pdf->SetTitle('Payslip — ' . $empName . ' — ' . $period);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(20, 20, 20);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->AddPage();
        $pdf->writeHTML($html, true, false, true, false, '');

        $filename = 'payslip_' . preg_replace('/[^a-z0-9_-]/i', '_', $item['employee_code'] . '_' . $month . '_' . $year) . '.pdf';
        $pdf->Output($filename, 'D');
        exit;
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function periodLabel(int $month, int $year): string
    {
        return date('F', mktime(0, 0, 0, $month, 1)) . ' ' . $year;
    }

    private function validateCsrf(Request $request, string $redirectPath): void
    {
        if (!$this->app->csrf()->validate((string) $request->input('_token'))) {
            $this->app->session()->flash('error', 'Invalid form token. Please try again.');
            $this->redirect($redirectPath);
        }
    }
}
