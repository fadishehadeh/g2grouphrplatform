<?php declare(strict_types=1); ?>
<?php
$hasAdvancedFilters = !empty(array_filter($filters ?? [], fn($value) => (string) $value !== '' && (string) $value !== '0' && (string) $value !== 'all'));
$pageHeaderTitle = 'Employee Directory';
$pageHeaderDescription = 'Browse, search, and maintain employee records from one consistent workspace.';
$pageHeaderChips = [
    ['label' => (int) ($total ?? count($employees ?? [])) . ' employees', 'tone' => 'neutral'],
    ['label' => (($search ?? '') !== '' || $hasAdvancedFilters) ? 'Filtered results' : 'All records', 'tone' => (($search ?? '') !== '' || $hasAdvancedFilters) ? 'brand' : 'calm'],
];
$pageHeaderActions = [
    ['label' => 'Export Excel', 'href' => url('/employees/export-excel'), 'class' => 'btn btn-outline-secondary', 'icon' => 'bi-file-earmark-excel'],
    ['label' => 'Export PDF', 'href' => url('/employees/export-pdf'), 'class' => 'btn btn-outline-secondary', 'icon' => 'bi-file-earmark-pdf'],
];
if (can('employee.create')) {
    $pageHeaderActions[] = ['label' => 'Import', 'href' => url('/employees/import'), 'class' => 'btn btn-outline-secondary', 'icon' => 'bi-upload'];
    $pageHeaderActions[] = ['label' => 'Add Employee', 'href' => url('/employees/create'), 'class' => 'btn btn-primary', 'icon' => 'bi-plus-lg'];
}
if (can('employee.view_all')) {
    $pageHeaderActions[] = ['label' => 'Missing Items Audit', 'href' => url('/employees/missing-items'), 'class' => 'btn btn-outline-secondary', 'icon' => 'bi-clipboard2-pulse'];
}
require base_path('app/Views/partials/page-header.php');
?>
<div class="card content-card">
    <div class="card-body p-4">
        <div class="employee-filter-bar mb-4">
            <form method="get" action="<?= e(url('/employees')); ?>" class="row g-3 align-items-end">
                <div class="col-12 col-xl-4">
                    <label class="form-label">Search Employees</label>
                    <input type="text" name="q" class="form-control" placeholder="Name, code, email, company, manager..." value="<?= e((string) ($search ?? '')); ?>">
                </div>
                <div class="col-12 col-xl-8">
                    <div class="d-flex justify-content-xl-end gap-2">
                        <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#employeeDirectoryFilters" aria-expanded="<?= $hasAdvancedFilters ? 'true' : 'false'; ?>" aria-controls="employeeDirectoryFilters">
                            <i class="bi bi-funnel"></i> <?= $hasAdvancedFilters ? 'Hide Filters' : 'Show Filters'; ?>
                        </button>
                    </div>
                </div>
                <div class="col-12 collapse <?= $hasAdvancedFilters ? 'show' : ''; ?>" id="employeeDirectoryFilters">
                    <div class="row g-3">
                        <div class="col-12 col-md-4 col-xl-2">
                            <label class="form-label">Code</label>
                            <input type="text" name="employee_code" class="form-control" placeholder="Employee code" value="<?= e((string) (($filters['employee_code'] ?? ''))); ?>">
                        </div>
                        <div class="col-12 col-md-4 col-xl-2">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="all">All statuses</option>
                                <?php foreach (['draft' => 'Draft', 'active' => 'Active', 'on_leave' => 'On Leave', 'inactive' => 'Inactive', 'resigned' => 'Resigned', 'terminated' => 'Terminated', 'archived' => 'Archived'] as $value => $label): ?>
                                    <option value="<?= e($value); ?>" <?= (string) (($filters['status'] ?? 'all')) === $value ? 'selected' : ''; ?>><?= e($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-4 col-xl-2">
                            <label class="form-label">Company</label>
                            <select name="company_id" class="form-select">
                                <option value="0">All companies</option>
                                <?php foreach (($filterOptions['companies'] ?? []) as $value => $label): ?>
                                    <option value="<?= e((string) $value); ?>" <?= (string) (($filters['company_id'] ?? 0)) === (string) $value ? 'selected' : ''; ?>><?= e((string) $label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-4 col-xl-2">
                            <label class="form-label">Branch</label>
                            <select name="branch_id" class="form-select">
                                <option value="0">All branches</option>
                                <?php foreach (($filterOptions['branches'] ?? []) as $value => $label): ?>
                                    <option value="<?= e((string) $value); ?>" <?= (string) (($filters['branch_id'] ?? 0)) === (string) $value ? 'selected' : ''; ?>><?= e((string) $label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-4 col-xl-2">
                            <label class="form-label">Department</label>
                            <select name="department_id" class="form-select">
                                <option value="0">All departments</option>
                                <?php foreach (($filterOptions['departments'] ?? []) as $value => $label): ?>
                                    <option value="<?= e((string) $value); ?>" <?= (string) (($filters['department_id'] ?? 0)) === (string) $value ? 'selected' : ''; ?>><?= e((string) $label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-4 col-xl-2">
                            <label class="form-label">Team</label>
                            <select name="team_id" class="form-select">
                                <option value="0">All teams</option>
                                <?php foreach (($filterOptions['teams'] ?? []) as $value => $label): ?>
                                    <option value="<?= e((string) $value); ?>" <?= (string) (($filters['team_id'] ?? 0)) === (string) $value ? 'selected' : ''; ?>><?= e((string) $label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-4 col-xl-2">
                            <label class="form-label">Job Title</label>
                            <select name="job_title_id" class="form-select">
                                <option value="0">All job titles</option>
                                <?php foreach (($filterOptions['job_titles'] ?? []) as $value => $label): ?>
                                    <option value="<?= e((string) $value); ?>" <?= (string) (($filters['job_title_id'] ?? 0)) === (string) $value ? 'selected' : ''; ?>><?= e((string) $label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-4 col-xl-2">
                            <label class="form-label">Designation</label>
                            <select name="designation_id" class="form-select">
                                <option value="0">All designations</option>
                                <?php foreach (($filterOptions['designations'] ?? []) as $value => $label): ?>
                                    <option value="<?= e((string) $value); ?>" <?= (string) (($filters['designation_id'] ?? 0)) === (string) $value ? 'selected' : ''; ?>><?= e((string) $label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-4 col-xl-2">
                            <label class="form-label">Manager</label>
                            <select name="manager_employee_id" class="form-select">
                                <option value="0">All managers</option>
                                <?php foreach (($filterOptions['managers'] ?? []) as $value => $label): ?>
                                    <option value="<?= e((string) $value); ?>" <?= (string) (($filters['manager_employee_id'] ?? 0)) === (string) $value ? 'selected' : ''; ?>><?= e((string) $label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-4 col-xl-2">
                            <label class="form-label">Employment Type</label>
                            <select name="employment_type" class="form-select">
                                <option value="all">All employment types</option>
                                <?php foreach (['full_time' => 'Full Time', 'part_time' => 'Part Time', 'contract' => 'Contract', 'intern' => 'Intern', 'temporary' => 'Temporary'] as $value => $label): ?>
                                    <option value="<?= e($value); ?>" <?= (string) (($filters['employment_type'] ?? 'all')) === $value ? 'selected' : ''; ?>><?= e($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-4 col-xl-2">
                            <label class="form-label">Contract Type</label>
                            <select name="contract_type" class="form-select">
                                <option value="all">All contract types</option>
                                <option value="__blank__" <?= (string) (($filters['contract_type'] ?? 'all')) === '__blank__' ? 'selected' : ''; ?>>Blank / not set</option>
                                <?php foreach (($filterOptions['contract_types'] ?? []) as $value => $label): ?>
                                    <option value="<?= e((string) $value); ?>" <?= (string) (($filters['contract_type'] ?? 'all')) === (string) $value ? 'selected' : ''; ?>><?= e((string) $label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-4 col-xl-2">
                            <label class="form-label">Access Account</label>
                            <select name="has_user_account" class="form-select">
                                <option value="all">All access states</option>
                                <option value="yes" <?= (string) (($filters['has_user_account'] ?? 'all')) === 'yes' ? 'selected' : ''; ?>>Has account</option>
                                <option value="no" <?= (string) (($filters['has_user_account'] ?? 'all')) === 'no' ? 'selected' : ''; ?>>No account</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-4 col-xl-2">
                            <label class="form-label">Joining Date</label>
                            <input type="date" name="joining_date" class="form-control" value="<?= e((string) (($filters['joining_date'] ?? ''))); ?>">
                        </div>
                        <div class="col-12 col-md-4 col-xl-2">
                            <label class="form-label">Joining From</label>
                            <input type="date" name="joining_date_from" class="form-control" value="<?= e((string) (($filters['joining_date_from'] ?? ''))); ?>">
                        </div>
                        <div class="col-12 col-md-4 col-xl-2">
                            <label class="form-label">Joining To</label>
                            <input type="date" name="joining_date_to" class="form-control" value="<?= e((string) (($filters['joining_date_to'] ?? ''))); ?>">
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-auto d-flex gap-2">
                    <button type="submit" class="btn btn-outline-secondary">Filter</button>
                    <?php if (($search ?? '') !== '' || $hasAdvancedFilters): ?>
                        <a href="<?= e(url('/employees')); ?>" class="btn btn-light">Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <?php if (($employees ?? []) === []): ?>
            <div class="empty-state">No employee records found for the current search.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0 mobile-stack-table">
                    <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Work Email</th>
                        <th>Department</th>
                        <th>Job Title</th>
                        <th>Line Manager</th>
                        <th>Status</th>
                        <th>Joining Date</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($employees as $employee): ?>
                        <tr>
                            <td data-label="Code"><?= e((string) $employee['employee_code']); ?></td>
                            <td data-label="Name">
                                <?php if (can('employee.edit')): ?>
                                    <a href="<?= e(url('/employees/' . $employee['id'] . '/edit')); ?>" class="fw-semibold text-decoration-none">
                                        <?= e((string) $employee['full_name']); ?>
                                    </a>
                                <?php else: ?>
                                    <?= e((string) $employee['full_name']); ?>
                                <?php endif; ?>
                            </td>
                            <td data-label="Work Email"><?= e((string) $employee['work_email']); ?></td>
                            <td data-label="Department"><?= e((string) ($employee['department_name'] ?? '-')); ?></td>
                            <td data-label="Job Title"><?= e((string) ($employee['job_title_name'] ?? '-')); ?></td>
                            <td data-label="Line Manager"><?= e((string) ($employee['manager_name'] ?? '-')); ?></td>
                            <td data-label="Status">
                                <span class="badge <?= ($employee['employee_status'] ?? '') === 'active' ? 'text-bg-success' : 'text-bg-secondary'; ?>">
                                    <?= e((string) $employee['employee_status']); ?>
                                </span>
                            </td>
                            <td data-label="Joining Date"><?= e((string) (($employee['joining_date'] ?? '') !== '' ? $employee['joining_date'] : '-')); ?></td>
                            <td data-label="Actions" class="text-end">
                                <div class="d-inline-flex align-items-center justify-content-end gap-2 flex-wrap">
                                    <a href="<?= e(url('/employees/' . $employee['id'])); ?>" class="btn btn-sm btn-outline-primary">View</a>
                                    <?php if (can('employee.edit')): ?>
                                        <a href="<?= e(url('/employees/' . $employee['id'] . '/edit')); ?>" class="btn btn-sm btn-outline-secondary" title="Edit">
                                            <i class="bi bi-pencil-square"></i> Edit
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php
                $page = (int) ($page ?? 1);
                $totalPages = (int) ($totalPages ?? 1);
                $total = (int) ($total ?? 0);
                $perPage = (int) ($perPage ?? 25);
                $search = (string) ($search ?? '');
                $filters = is_array($filters ?? null) ? $filters : [];
                $query = array_filter([
                    'q' => $search,
                    'status' => $filters['status'] ?? 'all',
                    'company_id' => $filters['company_id'] ?? 0,
                    'branch_id' => $filters['branch_id'] ?? 0,
                    'department_id' => $filters['department_id'] ?? 0,
                    'team_id' => $filters['team_id'] ?? 0,
                    'job_title_id' => $filters['job_title_id'] ?? 0,
                    'designation_id' => $filters['designation_id'] ?? 0,
                    'manager_employee_id' => $filters['manager_employee_id'] ?? 0,
                    'employment_type' => $filters['employment_type'] ?? 'all',
                    'contract_type' => $filters['contract_type'] ?? 'all',
                    'has_user_account' => $filters['has_user_account'] ?? 'all',
                    'employee_code' => $filters['employee_code'] ?? '',
                    'joining_date' => $filters['joining_date'] ?? '',
                    'joining_date_from' => $filters['joining_date_from'] ?? '',
                    'joining_date_to' => $filters['joining_date_to'] ?? '',
                ], fn($value) => (string) $value !== '' && (string) $value !== '0' && (string) $value !== 'all');
                $showing = min($perPage, $total - ($page - 1) * $perPage);
                $from = $total > 0 ? ($page - 1) * $perPage + 1 : 0;
            ?>
            <?php if ($totalPages > 1): ?>
            <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                <small class="text-muted">Showing <?= $from ?>-<?= $from + $showing - 1 ?> of <?= $total ?> employees</small>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?= e(url('/employees?' . http_build_query(array_merge($query, ['page' => $page - 1])))); ?>">&lsaquo;</a>
                        </li>
                        <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                            <li class="page-item <?= $p === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="<?= e(url('/employees?' . http_build_query(array_merge($query, ['page' => $p])))); ?>"><?= $p; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?= e(url('/employees?' . http_build_query(array_merge($query, ['page' => $page + 1])))); ?>">&rsaquo;</a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php else: ?>
            <div class="mt-3"><small class="text-muted"><?= $total ?> employee<?= $total !== 1 ? 's' : ''; ?></small></div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
