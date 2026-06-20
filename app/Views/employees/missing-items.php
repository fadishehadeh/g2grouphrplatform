<?php declare(strict_types=1); ?>
<?php
$hasAdvancedFilters = !empty(array_filter($filters ?? [], fn($value) => (string) $value !== '' && (string) $value !== '0' && (string) $value !== 'all'))
    || !($onlyMissing ?? true);
$pageHeaderTitle = 'Missing Items Audit';
$pageHeaderDescription = 'Review missing profile fields and missing active document types across employee records.';
$pageHeaderChips = [
    ['label' => (int) ($total ?? count($rows ?? [])) . ' employees', 'tone' => 'neutral'],
    ['label' => ($onlyMissing ?? true) ? 'Only missing records' : 'All filtered records', 'tone' => ($onlyMissing ?? true) ? 'warning' : 'calm'],
];
$pageHeaderActions = [
    ['label' => 'Back to Directory', 'href' => url('/employees'), 'class' => 'btn btn-outline-secondary', 'icon' => 'bi-arrow-left'],
];
require base_path('app/Views/partials/page-header.php');
?>

<div class="card content-card mb-4">
    <div class="card-body p-4">
        <form method="get" action="<?= e(url('/employees/missing-items')); ?>" class="row g-3 align-items-end">
            <div class="col-12 col-xl-4">
                <label class="form-label">Search</label>
                <input type="text" name="q" class="form-control" placeholder="Name, code, email, company, manager..." value="<?= e((string) ($search ?? '')); ?>">
            </div>
            <div class="col-12 col-xl-8">
                <div class="d-flex justify-content-xl-end gap-2">
                    <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#missingItemsFilters" aria-expanded="<?= $hasAdvancedFilters ? 'true' : 'false'; ?>" aria-controls="missingItemsFilters">
                        <i class="bi bi-funnel"></i> <?= $hasAdvancedFilters ? 'Hide Filters' : 'Show Filters'; ?>
                    </button>
                </div>
            </div>
            <div class="col-12 collapse <?= $hasAdvancedFilters ? 'show' : ''; ?>" id="missingItemsFilters">
                <div class="row g-3">
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
                        <label class="form-label">Department</label>
                        <select name="department_id" class="form-select">
                            <option value="0">All departments</option>
                            <?php foreach (($filterOptions['departments'] ?? []) as $value => $label): ?>
                                <option value="<?= e((string) $value); ?>" <?= (string) (($filters['department_id'] ?? 0)) === (string) $value ? 'selected' : ''; ?>><?= e((string) $label); ?></option>
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
                        <label class="form-label">Access Account</label>
                        <select name="has_user_account" class="form-select">
                            <option value="all">All access states</option>
                            <option value="yes" <?= (string) (($filters['has_user_account'] ?? 'all')) === 'yes' ? 'selected' : ''; ?>>Has account</option>
                            <option value="no" <?= (string) (($filters['has_user_account'] ?? 'all')) === 'no' ? 'selected' : ''; ?>>No account</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-4 col-xl-2">
                        <label class="form-label">Joining From</label>
                        <input type="date" name="joining_date_from" class="form-control" value="<?= e((string) (($filters['joining_date_from'] ?? ''))); ?>">
                    </div>
                    <div class="col-12 col-md-4 col-xl-2">
                        <label class="form-label">Joining To</label>
                        <input type="date" name="joining_date_to" class="form-control" value="<?= e((string) (($filters['joining_date_to'] ?? ''))); ?>">
                    </div>
                    <div class="col-12 col-md-4 col-xl-3">
                        <div class="form-check mt-4 pt-2">
                            <input type="hidden" name="only_missing" value="0">
                            <input class="form-check-input" type="checkbox" name="only_missing" id="only_missing" value="1" <?= ($onlyMissing ?? true) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="only_missing">Only employees with gaps</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-auto d-flex gap-2">
                <button type="submit" class="btn btn-outline-secondary">Filter</button>
                <a href="<?= e(url('/employees/missing-items')); ?>" class="btn btn-light">Reset</a>
            </div>
        </form>
        <div class="small text-muted mt-3">Missing documents are checked against the active document types configured in Document Types. Existing legacy documents are also matched by title when possible.</div>
    </div>
</div>

<div class="card content-card">
    <div class="card-body p-4">
        <?php if (($rows ?? []) === []): ?>
            <div class="empty-state">No employees matched the current audit filters.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0 mobile-stack-table">
                    <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Structure</th>
                        <th>Missing Fields</th>
                        <th>Missing Documents</th>
                        <th>Total Gaps</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php $audit = $row['missing_items'] ?? []; ?>
                        <tr>
                            <td data-label="Employee">
                                <div class="fw-semibold"><?= e((string) $row['full_name']); ?></div>
                                <div class="small text-muted"><?= e((string) ($row['employee_code'] ?? '')); ?> · <?= e((string) ($row['work_email'] ?? '')); ?></div>
                                <div class="small text-muted"><?= e(ucwords(str_replace('_', ' ', (string) ($row['employee_status'] ?? 'draft')))); ?></div>
                            </td>
                            <td data-label="Structure">
                                <div><?= e((string) ($row['company_name'] ?? '-')); ?></div>
                                <div class="small text-muted"><?= e((string) ($row['department_name'] ?? '-')); ?> · <?= e((string) ($row['job_title_name'] ?? '-')); ?></div>
                            </td>
                            <td data-label="Missing Fields">
                                <?php if (($audit['missing_fields'] ?? []) === []): ?>
                                    <span class="badge text-bg-success">None</span>
                                <?php else: ?>
                                    <div class="d-flex flex-wrap gap-1">
                                        <?php foreach (array_slice($audit['missing_fields'], 0, 6) as $item): ?>
                                            <span class="badge text-bg-light border"><?= e((string) $item); ?></span>
                                        <?php endforeach; ?>
                                        <?php if (count($audit['missing_fields']) > 6): ?>
                                            <span class="badge text-bg-secondary">+<?= e((string) (count($audit['missing_fields']) - 6)); ?> more</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td data-label="Missing Documents">
                                <?php if (($audit['missing_documents'] ?? []) === []): ?>
                                    <span class="badge text-bg-success">None</span>
                                <?php else: ?>
                                    <div class="d-flex flex-wrap gap-1">
                                        <?php foreach (array_slice($audit['missing_documents'], 0, 6) as $item): ?>
                                            <span class="badge text-bg-light border"><?= e((string) $item); ?></span>
                                        <?php endforeach; ?>
                                        <?php if (count($audit['missing_documents']) > 6): ?>
                                            <span class="badge text-bg-secondary">+<?= e((string) (count($audit['missing_documents']) - 6)); ?> more</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td data-label="Total Gaps">
                                <span class="badge <?= (int) ($audit['total_missing_count'] ?? 0) > 0 ? 'text-bg-warning' : 'text-bg-success'; ?>">
                                    <?= e((string) ($audit['total_missing_count'] ?? 0)); ?>
                                </span>
                            </td>
                            <td data-label="Actions" class="text-end">
                                <a href="<?= e(url('/employees/' . $row['id'])); ?>" class="btn btn-sm btn-outline-primary">View</a>
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
                $query = array_filter([
                    'q' => $search ?? '',
                    'status' => $filters['status'] ?? 'all',
                    'company_id' => $filters['company_id'] ?? 0,
                    'department_id' => $filters['department_id'] ?? 0,
                    'job_title_id' => $filters['job_title_id'] ?? 0,
                    'manager_employee_id' => $filters['manager_employee_id'] ?? 0,
                    'employment_type' => $filters['employment_type'] ?? 'all',
                    'has_user_account' => $filters['has_user_account'] ?? 'all',
                    'joining_date_from' => $filters['joining_date_from'] ?? '',
                    'joining_date_to' => $filters['joining_date_to'] ?? '',
                    'only_missing' => ($onlyMissing ?? true) ? '1' : '0',
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
                                <a class="page-link" href="<?= e(url('/employees/missing-items?' . http_build_query(array_merge($query, ['page' => $page - 1])))); ?>">&lsaquo;</a>
                            </li>
                            <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                                <li class="page-item <?= $p === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="<?= e(url('/employees/missing-items?' . http_build_query(array_merge($query, ['page' => $p])))); ?>"><?= $p; ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?= e(url('/employees/missing-items?' . http_build_query(array_merge($query, ['page' => $page + 1])))); ?>">&rsaquo;</a>
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
