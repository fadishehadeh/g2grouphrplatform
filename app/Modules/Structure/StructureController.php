<?php

declare(strict_types=1);

namespace App\Modules\Structure;

use App\Core\Application;
use App\Core\Controller;
use App\Core\Request;
use Throwable;

final class StructureController extends Controller
{
    private MasterDataRepository $repository;

    public function __construct(Application $app)
    {
        parent::__construct($app);
        $this->repository = new MasterDataRepository($this->app->database());
    }

    public function index(Request $request): void
    {
        $counts = ['companies' => 0, 'branches' => 0, 'departments' => 0, 'teams' => 0, 'job_titles' => 0, 'designations' => 0, 'reporting_lines' => 0];

        try {
            $counts = $this->repository->summaryCounts();
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load structure metrics: ' . $throwable->getMessage());
        }

        $this->render('structure.index', [
            'title' => 'Company Structure',
            'pageTitle' => 'Company Structure',
            'activeSection' => 'overview',
            'counts' => $counts,
        ]);
    }

    public function companies(Request $request): void
    {
        $this->renderManagePage(
            $request,
            'companies',
            'Companies',
            'Manage legal entities, contact details, and timezone defaults used across the HR system.',
            ['name' => 'Company', 'code' => 'Code', 'email' => 'Email', 'phone' => 'Phone', 'city' => 'City', 'country' => 'Country', 'status' => 'Status'],
            [
                ['name' => 'name', 'label' => 'Company Name', 'type' => 'text', 'required' => true],
                ['name' => 'code', 'label' => 'Code', 'type' => 'text', 'required' => true],
                ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
                ['name' => 'phone', 'label' => 'Phone', 'type' => 'text'],
                ['name' => 'address_line_1', 'label' => 'Address Line 1', 'type' => 'text'],
                ['name' => 'address_line_2', 'label' => 'Address Line 2', 'type' => 'text'],
                ['name' => 'city', 'label' => 'City', 'type' => 'text'],
                ['name' => 'state', 'label' => 'State / Province', 'type' => 'text'],
                ['name' => 'country', 'label' => 'Country', 'type' => 'text'],
                ['name' => 'postal_code', 'label' => 'Postal Code', 'type' => 'text'],
                ['name' => 'timezone', 'label' => 'Timezone', 'type' => 'text', 'required' => true, 'value' => 'UTC'],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'required' => true, 'options' => ['active' => 'Active', 'inactive' => 'Inactive'], 'value' => 'active'],
            ],
            fn (string $search): array => $this->repository->listCompanies($search)
        );
    }

    public function storeCompany(Request $request): void
    {
        if (!$this->app->csrf()->validate((string) $request->input('_token'))) {
            $this->app->session()->flash('error', 'Invalid form submission token.');
            $this->redirect('/admin/companies');
        }

        $data = $this->trimmedInput($request);

        foreach (['name', 'code', 'timezone'] as $field) {
            if (($data[$field] ?? '') === '') {
                $this->app->session()->flash('error', 'Please complete all required fields.');
                $this->app->session()->flash('old_input', $data);
                $this->redirect('/admin/companies');
            }
        }

        $this->validateCompanyPayload($data, '/admin/companies');

        try {
            $companyId = $this->repository->createCompany([
                'name' => $data['name'],
                'code' => strtoupper($data['code']),
                'email' => $data['email'] ?: null,
                'phone' => $data['phone'] ?: null,
                'address_line_1' => $data['address_line_1'] ?: null,
                'address_line_2' => $data['address_line_2'] ?: null,
                'city' => $data['city'] ?: null,
                'state' => $data['state'] ?: null,
                'country' => $data['country'] ?: null,
                'postal_code' => $data['postal_code'] ?: null,
                'timezone' => $data['timezone'],
                'status' => $data['status'] ?: 'active',
            ]);
            $this->app->session()->flash('success', 'Company created successfully.');
            $this->redirect('/admin/companies/' . $companyId);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to save company: ' . $throwable->getMessage());
            $this->app->session()->flash('old_input', $data);
            $this->redirect('/admin/companies');
        }
    }

    public function editCompany(Request $request, string $id): void
    {
        $companyId = (int) $id;

        try {
            $company = $this->repository->findCompany($companyId);

            if ($company === null) {
                $this->app->session()->flash('error', 'Company not found.');
                $this->redirect('/admin/companies');
            }

            $branches = $this->repository->companyBranches($companyId);
            $departments = $this->repository->companyDepartments($companyId);
            $jobTitles = $this->repository->companyJobTitles();
            $designations = $this->repository->companyDesignations();
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load company details: ' . $throwable->getMessage());
            $this->redirect('/admin/companies');
            return;
        }

        $this->render('structure.company-detail', [
            'title' => 'Manage ' . ($company['name'] ?? 'Company'),
            'pageTitle' => $company['name'] ?? 'Company',
            'activeSection' => 'companies',
            'company' => $company,
            'branches' => $branches,
            'departments' => $departments,
            'jobTitles' => $jobTitles,
            'designations' => $designations,
            'branchOptions' => $this->options(array_map(fn ($b) => ['id' => $b['id'], 'name' => $b['name']], $branches)),
        ]);
    }

    public function updateCompany(Request $request, string $id): void
    {
        $companyId = (int) $id;
        $redirect = '/admin/companies/' . $companyId;

        if (!$this->app->csrf()->validate((string) $request->input('_token'))) {
            $this->app->session()->flash('error', 'Invalid form submission token.');
            $this->redirect($redirect);
        }

        $data = $this->trimmedInput($request);

        foreach (['name', 'code', 'timezone'] as $field) {
            if (($data[$field] ?? '') === '') {
                $this->app->session()->flash('error', 'Please complete all required fields.');
                $this->app->session()->flash('old_input', $data);
                $this->redirect($redirect);
            }
        }

        $this->validateCompanyPayload($data, $redirect);

        try {
            $this->repository->updateCompany($companyId, [
                'name' => $data['name'],
                'code' => strtoupper($data['code']),
                'email' => $data['email'] ?: null,
                'phone' => $data['phone'] ?: null,
                'address_line_1' => $data['address_line_1'] ?: null,
                'address_line_2' => $data['address_line_2'] ?: null,
                'city' => $data['city'] ?: null,
                'state' => $data['state'] ?: null,
                'country' => $data['country'] ?: null,
                'postal_code' => $data['postal_code'] ?: null,
                'timezone' => $data['timezone'],
                'status' => $data['status'] ?: 'active',
            ]);
            $this->app->session()->flash('success', 'Company updated successfully.');
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to update company: ' . $throwable->getMessage());
        }

        $this->redirect($redirect);
    }

    public function storeCompanyBranch(Request $request, string $id): void
    {
        $companyId = (int) $id;
        $redirect = '/admin/companies/' . $companyId;

        $this->persist($request, $redirect, ['name', 'code'], function (array $data) use ($companyId): void {
            $this->repository->createBranch([
                'company_id' => $companyId, 'name' => $data['name'], 'code' => strtoupper($data['code']),
                'email' => $data['email'] ?: null, 'phone' => $data['phone'] ?: null,
                'city' => $data['city'] ?: null, 'country' => $data['country'] ?: null,
                'status' => $data['status'] ?: 'active',
            ]);
        }, 'Branch added successfully.');
    }

    public function storeCompanyDepartment(Request $request, string $id): void
    {
        $companyId = (int) $id;
        $redirect = '/admin/companies/' . $companyId;

        $this->persist($request, $redirect, ['name', 'code'], function (array $data) use ($companyId): void {
            $this->repository->createDepartment([
                'company_id' => $companyId, 'branch_id' => ($data['branch_id'] ?? '') !== '' ? (int) $data['branch_id'] : null,
                'parent_department_id' => null, 'name' => $data['name'], 'code' => strtoupper($data['code']),
                'description' => $data['description'] ?: null, 'status' => $data['status'] ?: 'active',
            ]);
        }, 'Department added successfully.');
    }

    public function storeCompanyJobTitle(Request $request, string $id): void
    {
        $redirect = '/admin/companies/' . (int) $id;

        $this->persist($request, $redirect, ['name', 'code'], function (array $data): void {
            $this->repository->createJobTitle([
                'name' => $data['name'], 'code' => strtoupper($data['code']),
                'level_rank' => (int) ($data['level_rank'] ?? 0),
                'description' => $data['description'] ?: null, 'status' => $data['status'] ?: 'active',
            ]);
        }, 'Job title added successfully.');
    }

    public function storeCompanyDesignation(Request $request, string $id): void
    {
        $redirect = '/admin/companies/' . (int) $id;

        $this->persist($request, $redirect, ['name', 'code'], function (array $data): void {
            $this->repository->createDesignation([
                'name' => $data['name'], 'code' => strtoupper($data['code']),
                'description' => $data['description'] ?: null, 'status' => $data['status'] ?: 'active',
            ]);
        }, 'Designation added successfully.');
    }

    public function branches(Request $request): void
    {
        $this->renderManagePage(
            $request,
            'branches',
            'Branches',
            'Manage company branches and office locations.',
            ['company_id' => 'Company', 'name' => 'Branch', 'code' => 'Code', 'company_name' => 'Company', 'city' => 'City', 'status' => 'Status'],
            [
                ['name' => 'company_id', 'label' => 'Company', 'type' => 'select', 'required' => true, 'options' => $this->options($this->repository->companies())],
                ['name' => 'name', 'label' => 'Branch Name', 'type' => 'text', 'required' => true],
                ['name' => 'code', 'label' => 'Code', 'type' => 'text', 'required' => true],
                ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
                ['name' => 'phone', 'label' => 'Phone', 'type' => 'text'],
                ['name' => 'city', 'label' => 'City', 'type' => 'text'],
                ['name' => 'country', 'label' => 'Country', 'type' => 'text'],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'required' => true, 'options' => ['active' => 'Active', 'inactive' => 'Inactive'], 'value' => 'active'],
            ],
            fn (string $search): array => $this->repository->listBranches($search)
        );
    }

    public function storeBranch(Request $request): void
    {
        $this->persist($request, '/admin/branches', ['company_id', 'name', 'code'], function (array $data): void {
            $this->repository->createBranch([
                'company_id' => (int) $data['company_id'], 'name' => $data['name'], 'code' => strtoupper($data['code']),
                'email' => $data['email'] ?: null, 'phone' => $data['phone'] ?: null, 'city' => $data['city'] ?: null,
                'country' => $data['country'] ?: null, 'status' => $data['status'] ?: 'active',
            ]);
        }, 'Branch created successfully.');
    }

    public function departments(Request $request): void
    {
        $this->renderManagePage(
            $request,
            'departments',
            'Departments',
            'Create and organize departments by company and branch.',
            ['name' => 'Department', 'code' => 'Code', 'company_name' => 'Company', 'branch_name' => 'Branch', 'status' => 'Status'],
            [
                ['name' => 'company_id', 'label' => 'Company', 'type' => 'select', 'required' => true, 'options' => $this->options($this->repository->companies())],
                ['name' => 'branch_id', 'label' => 'Branch', 'type' => 'select', 'options' => ['' => 'No branch'] + $this->options($this->repository->branchesOptions())],
                ['name' => 'parent_department_id', 'label' => 'Parent Department', 'type' => 'select', 'options' => ['' => 'No parent'] + $this->options($this->repository->departmentsOptions())],
                ['name' => 'name', 'label' => 'Department Name', 'type' => 'text', 'required' => true],
                ['name' => 'code', 'label' => 'Code', 'type' => 'text', 'required' => true],
                ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'required' => true, 'options' => ['active' => 'Active', 'inactive' => 'Inactive'], 'value' => 'active'],
            ],
            fn (string $search): array => $this->repository->listDepartments($search)
        );
    }

    public function storeDepartment(Request $request): void
    {
        $this->persist($request, '/admin/departments', ['company_id', 'name', 'code'], function (array $data): void {
            $this->repository->createDepartment([
                'company_id' => (int) $data['company_id'], 'branch_id' => $data['branch_id'] !== '' ? (int) $data['branch_id'] : null,
                'parent_department_id' => $data['parent_department_id'] !== '' ? (int) $data['parent_department_id'] : null,
                'name' => $data['name'], 'code' => strtoupper($data['code']), 'description' => $data['description'] ?: null,
                'status' => $data['status'] ?: 'active',
            ]);
        }, 'Department created successfully.');
    }

    public function teams(Request $request): void
    {
        $this->renderManagePage(
            $request,
            'teams',
            'Teams',
            'Group employees into functional teams within departments.',
            ['name' => 'Team', 'code' => 'Code', 'department_name' => 'Department', 'status' => 'Status'],
            [
                ['name' => 'department_id', 'label' => 'Department', 'type' => 'select', 'required' => true, 'options' => $this->options($this->repository->departmentsOptions())],
                ['name' => 'name', 'label' => 'Team Name', 'type' => 'text', 'required' => true],
                ['name' => 'code', 'label' => 'Code', 'type' => 'text', 'required' => true],
                ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'required' => true, 'options' => ['active' => 'Active', 'inactive' => 'Inactive'], 'value' => 'active'],
            ],
            fn (string $search): array => $this->repository->listTeams($search)
        );
    }

    public function storeTeam(Request $request): void
    {
        $this->persist($request, '/admin/teams', ['department_id', 'name', 'code'], function (array $data): void {
            $this->repository->createTeam([
                'department_id' => (int) $data['department_id'], 'name' => $data['name'], 'code' => strtoupper($data['code']),
                'description' => $data['description'] ?: null, 'status' => $data['status'] ?: 'active',
            ]);
        }, 'Team created successfully.');
    }

    public function jobTitles(Request $request): void
    {
        $this->renderManagePage(
            $request,
            'job_titles',
            'Job Titles',
            'Maintain the official job title catalog used for employees.',
            ['name' => 'Job Title', 'code' => 'Code', 'level_rank' => 'Level', 'status' => 'Status'],
            [
                ['name' => 'name', 'label' => 'Job Title', 'type' => 'text', 'required' => true],
                ['name' => 'code', 'label' => 'Code', 'type' => 'text', 'required' => true],
                ['name' => 'level_rank', 'label' => 'Level Rank', 'type' => 'number', 'required' => true, 'value' => '1'],
                ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'required' => true, 'options' => ['active' => 'Active', 'inactive' => 'Inactive'], 'value' => 'active'],
            ],
            fn (string $search): array => $this->repository->listJobTitles($search)
        );
    }

    public function storeJobTitle(Request $request): void
    {
        $this->persist($request, '/admin/job-titles', ['name', 'code', 'level_rank'], function (array $data): void {
            $this->repository->createJobTitle([
                'name' => $data['name'], 'code' => strtoupper($data['code']), 'level_rank' => (int) $data['level_rank'],
                'description' => $data['description'] ?: null, 'status' => $data['status'] ?: 'active',
            ]);
        }, 'Job title created successfully.');
    }

    public function designations(Request $request): void
    {
        $this->renderManagePage(
            $request,
            'designations',
            'Designations',
            'Maintain designation labels for employee records and reporting.',
            ['name' => 'Designation', 'code' => 'Code', 'status' => 'Status'],
            [
                ['name' => 'name', 'label' => 'Designation', 'type' => 'text', 'required' => true],
                ['name' => 'code', 'label' => 'Code', 'type' => 'text', 'required' => true],
                ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'required' => true, 'options' => ['active' => 'Active', 'inactive' => 'Inactive'], 'value' => 'active'],
            ],
            fn (string $search): array => $this->repository->listDesignations($search)
        );
    }

    public function storeDesignation(Request $request): void
    {
        $this->persist($request, '/admin/designations', ['name', 'code'], function (array $data): void {
            $this->repository->createDesignation([
                'name' => $data['name'], 'code' => strtoupper($data['code']), 'description' => $data['description'] ?: null,
                'status' => $data['status'] ?: 'active',
            ]);
        }, 'Designation created successfully.');
    }

    public function reportingLines(Request $request): void
    {
        $search = trim((string) $request->input('q', ''));
        $relationshipType = trim((string) $request->input('relationship_type', 'all'));
        $filterOptions = $this->relationshipFilterOptions();

        if (!array_key_exists($relationshipType, $filterOptions)) {
            $relationshipType = 'all';
        }

        $items = [];
        $employeeOptions = [];

        try {
            $items = $this->repository->listReportingLines($search, $relationshipType);
            $employeeOptions = $this->options($this->repository->employeeOptions());
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load reporting line records: ' . $throwable->getMessage());
        }

        $this->render('structure.manage', [
            'title' => 'Reporting Lines',
            'pageTitle' => 'Reporting Lines',
            'activeSection' => 'reporting_lines',
            'description' => 'Map primary managers, dotted-line relationships, and leave approvers for employees.',
            'search' => $search,
            'items' => $items,
            'columns' => [
                'employee_name' => 'Employee',
                'manager_name' => 'Manager / Approver',
                'relationship_type' => 'Relationship',
                'priority_order' => 'Priority',
                'effective_from' => 'Effective From',
                'effective_to' => 'Effective To',
                'status' => 'Status',
            ],
            'formFields' => [
                ['name' => 'employee_id', 'label' => 'Employee', 'type' => 'select', 'required' => true, 'options' => $employeeOptions],
                ['name' => 'manager_employee_id', 'label' => 'Manager / Approver', 'type' => 'select', 'required' => true, 'options' => $employeeOptions],
                ['name' => 'relationship_type', 'label' => 'Relationship Type', 'type' => 'select', 'required' => true, 'options' => $this->relationshipTypeOptions(), 'value' => 'line_manager'],
                ['name' => 'priority_order', 'label' => 'Priority Order', 'type' => 'number', 'required' => true, 'value' => '1'],
                ['name' => 'effective_from', 'label' => 'Effective From', 'type' => 'date', 'required' => true, 'value' => date('Y-m-d')],
                ['name' => 'effective_to', 'label' => 'Effective To', 'type' => 'date'],
                ['name' => 'is_active', 'label' => 'Status', 'type' => 'select', 'required' => true, 'options' => ['1' => 'Active', '0' => 'Inactive'], 'value' => '1'],
            ],
            'formAction' => '/admin/reporting-lines',
            'filters' => [
                ['name' => 'relationship_type', 'label' => 'Relationship Type', 'type' => 'select', 'options' => $filterOptions, 'value' => $relationshipType],
            ],
        ]);
    }

    public function storeReportingLine(Request $request): void
    {
        $this->persist(
            $request,
            '/admin/reporting-lines',
            ['employee_id', 'manager_employee_id', 'relationship_type', 'priority_order', 'effective_from'],
            function (array $data): void {
                $this->repository->createReportingLine([
                    'employee_id' => $data['employee_id'],
                    'manager_employee_id' => $data['manager_employee_id'],
                    'relationship_type' => $data['relationship_type'],
                    'priority_order' => $data['priority_order'],
                    'is_active' => $data['is_active'] ?? '1',
                    'effective_from' => $data['effective_from'],
                    'effective_to' => $data['effective_to'] ?? '',
                ], $this->app->auth()->id());
            },
            'Reporting line created successfully.',
            fn (array $data) => $this->validateReportingLinePayload($data, '/admin/reporting-lines')
        );
    }

    private function renderManagePage(Request $request, string $section, string $title, string $description, array $columns, array $formFields, callable $loader): void
    {
        $search = trim((string) $request->input('q', ''));

        try {
            $items = $loader($search);
        } catch (Throwable $throwable) {
            $items = [];
            $this->app->session()->flash('error', 'Unable to load records: ' . $throwable->getMessage());
        }

        $this->render('structure.manage', [
            'title' => $title,
            'pageTitle' => $title,
            'activeSection' => $section,
            'description' => $description,
            'search' => $search,
            'items' => $items,
            'columns' => $columns,
            'formFields' => $formFields,
            'formAction' => '/admin/' . str_replace('_', '-', $section),
        ]);
    }

    private function persist(Request $request, string $redirect, array $required, callable $callback, string $successMessage, ?callable $validator = null): void
    {
        if (!$this->app->csrf()->validate((string) $request->input('_token'))) {
            $this->app->session()->flash('error', 'Invalid form submission token.');
            $this->redirect($redirect);
        }

        $data = [];
        foreach ($request->all() as $key => $value) {
            $data[$key] = is_string($value) ? trim($value) : $value;
        }

        foreach ($required as $field) {
            if (($data[$field] ?? '') === '') {
                $this->app->session()->flash('error', 'Please complete all required fields.');
                $this->app->session()->flash('old_input', $data);
                $this->redirect($redirect);
            }
        }

        if ($validator !== null) {
            $validator($data);
        }

        try {
            $callback($data);
            $this->app->session()->flash('success', $successMessage);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to save record: ' . $throwable->getMessage());
            $this->app->session()->flash('old_input', $data);
        }

        $this->redirect($redirect);
    }

    private function options(array $rows): array
    {
        $options = [];

        foreach ($rows as $row) {
            $options[(string) $row['id']] = $row['name'];
        }

        return $options;
    }

    private function validateCompanyPayload(array $data, string $redirect): void
    {
        if (($data['email'] ?? '') !== '' && !filter_var((string) $data['email'], FILTER_VALIDATE_EMAIL)) {
            $this->app->session()->flash('error', 'Please provide a valid company email address.');
            $this->app->session()->flash('old_input', $data);
            $this->redirect($redirect);
        }

        if (!in_array((string) ($data['status'] ?? 'active'), ['active', 'inactive'], true)) {
            $this->app->session()->flash('error', 'Please select a valid company status.');
            $this->app->session()->flash('old_input', $data);
            $this->redirect($redirect);
        }
    }

    private function validateReportingLinePayload(array $data, string $redirect): void
    {
        if (!ctype_digit((string) ($data['employee_id'] ?? '')) || !ctype_digit((string) ($data['manager_employee_id'] ?? ''))) {
            $this->app->session()->flash('error', 'Please select valid employee records for the reporting line.');
            $this->app->session()->flash('old_input', $data);
            $this->redirect($redirect);
        }

        if ((string) $data['employee_id'] === (string) $data['manager_employee_id']) {
            $this->app->session()->flash('error', 'An employee cannot be assigned as their own manager or approver.');
            $this->app->session()->flash('old_input', $data);
            $this->redirect($redirect);
        }

        if (!array_key_exists((string) ($data['relationship_type'] ?? ''), $this->relationshipTypeOptions())) {
            $this->app->session()->flash('error', 'Please choose a valid reporting relationship type.');
            $this->app->session()->flash('old_input', $data);
            $this->redirect($redirect);
        }

        if (!ctype_digit((string) ($data['priority_order'] ?? '')) || (int) $data['priority_order'] < 1) {
            $this->app->session()->flash('error', 'Priority order must be a positive number.');
            $this->app->session()->flash('old_input', $data);
            $this->redirect($redirect);
        }

        if (!in_array((string) ($data['is_active'] ?? '1'), ['0', '1'], true)) {
            $this->app->session()->flash('error', 'Please select a valid reporting line status.');
            $this->app->session()->flash('old_input', $data);
            $this->redirect($redirect);
        }

        if (!$this->isValidDate((string) $data['effective_from'])) {
            $this->app->session()->flash('error', 'Please provide a valid effective start date.');
            $this->app->session()->flash('old_input', $data);
            $this->redirect($redirect);
        }

        if (($data['effective_to'] ?? '') !== '') {
            if (!$this->isValidDate((string) $data['effective_to'])) {
                $this->app->session()->flash('error', 'Please provide a valid effective end date.');
                $this->app->session()->flash('old_input', $data);
                $this->redirect($redirect);
            }

            if ((string) $data['effective_to'] < (string) $data['effective_from']) {
                $this->app->session()->flash('error', 'Effective end date cannot be earlier than the start date.');
                $this->app->session()->flash('old_input', $data);
                $this->redirect($redirect);
            }
        }
    }

    private function relationshipTypeOptions(): array
    {
        return [
            'line_manager' => 'Line Manager',
            'dotted_line' => 'Dotted Line',
            'leave_approver' => 'Leave Approver',
        ];
    }

    private function relationshipFilterOptions(): array
    {
        return ['all' => 'All Relationship Types'] + $this->relationshipTypeOptions();
    }

    private function isValidDate(string $value): bool
    {
        $date = \DateTime::createFromFormat('Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function trimmedInput(Request $request): array
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
}