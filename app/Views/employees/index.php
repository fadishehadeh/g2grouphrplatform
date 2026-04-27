<?php declare(strict_types=1); ?>
<?php
$pageHeaderTitle = 'Employee Directory';
$pageHeaderDescription = 'Browse, search, and maintain employee records from one consistent workspace.';
$pageHeaderChips = [
    ['label' => (int) ($total ?? count($employees ?? [])) . ' employees', 'tone' => 'neutral'],
    ['label' => ($search ?? '') !== '' ? 'Filtered results' : 'All records', 'tone' => ($search ?? '') !== '' ? 'brand' : 'calm'],
];
$pageHeaderActions = [
    ['label' => 'Export Excel', 'href' => url('/employees/export-excel'), 'class' => 'btn btn-outline-secondary', 'icon' => 'bi-file-earmark-excel'],
    ['label' => 'Export PDF', 'href' => url('/employees/export-pdf'), 'class' => 'btn btn-outline-secondary', 'icon' => 'bi-file-earmark-pdf'],
];
if (can('employee.create')) {
    $pageHeaderActions[] = ['label' => 'Import', 'href' => url('/employees/import'), 'class' => 'btn btn-outline-secondary', 'icon' => 'bi-upload'];
    $pageHeaderActions[] = ['label' => 'Add Employee', 'href' => url('/employees/create'), 'class' => 'btn btn-primary', 'icon' => 'bi-plus-lg'];
}
require base_path('app/Views/partials/page-header.php');
?>
<div class="card content-card">
    <div class="card-body p-4">
        <div class="employee-filter-bar mb-4">
            <form method="get" action="<?= e(url('/employees')); ?>" class="row g-2 align-items-end">
                <div class="col-lg-6">
                    <label class="form-label">Search Employees</label>
                    <input type="text" name="q" class="form-control" placeholder="Search by name, code, email, department..." value="<?= e((string) ($search ?? '')); ?>">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-secondary">Search</button>
                </div>
                <?php if (($search ?? '') !== ''): ?>
                    <div class="col-auto">
                        <a href="<?= e(url('/employees')); ?>" class="btn btn-light">Clear</a>
                    </div>
                <?php endif; ?>
            </form>
        </div>

        <?php if (($employees ?? []) === []): ?>
            <div class="empty-state">No employee records found for the current search.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Code</th><th>Name</th><th>Work Email</th><th>Department</th><th>Job Title</th><th>Line Manager</th><th>Status</th><th>Joining Date</th><th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($employees as $employee): ?>
                        <tr>
                            <td><?= e((string) $employee['employee_code']); ?></td>
                            <td><?= e((string) $employee['full_name']); ?></td>
                            <td><?= e((string) $employee['work_email']); ?></td>
                            <td><?= e((string) ($employee['department_name'] ?? '—')); ?></td>
                            <td><?= e((string) ($employee['job_title_name'] ?? '—')); ?></td>
                            <td><?= e((string) ($employee['manager_name'] ?? '—')); ?></td>
                            <td><span class="badge <?= ($employee['employee_status'] ?? '') === 'active' ? 'text-bg-success' : 'text-bg-secondary'; ?>"><?= e((string) $employee['employee_status']); ?></span></td>
                            <td><?= e((string) (($employee['joining_date'] ?? '') !== '' ? $employee['joining_date'] : '—')); ?></td>
                            <td class="text-end">
                                <a href="<?= e(url('/employees/' . $employee['id'])); ?>" class="btn btn-sm btn-outline-primary">View</a>
                                <?php if (can('employee.edit')): ?><a href="<?= e(url('/employees/' . $employee['id'] . '/edit')); ?>" class="btn btn-sm btn-outline-secondary ms-1" title="Edit"><i class="bi bi-pencil-square"></i></a><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php
                $page       = (int) ($page ?? 1);
                $totalPages = (int) ($totalPages ?? 1);
                $total      = (int) ($total ?? 0);
                $perPage    = (int) ($perPage ?? 25);
                $search     = (string) ($search ?? '');
                $showing    = min($perPage, $total - ($page - 1) * $perPage);
                $from       = $total > 0 ? ($page - 1) * $perPage + 1 : 0;
            ?>
            <?php if ($totalPages > 1): ?>
            <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                <small class="text-muted">Showing <?= $from ?>–<?= $from + $showing - 1 ?> of <?= $total ?> employees</small>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?= e(url('/employees?page=' . ($page - 1) . ($search !== '' ? '&q=' . urlencode($search) : ''))); ?>">‹</a>
                        </li>
                        <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                            <li class="page-item <?= $p === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="<?= e(url('/employees?page=' . $p . ($search !== '' ? '&q=' . urlencode($search) : ''))); ?>"><?= $p; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?= e(url('/employees?page=' . ($page + 1) . ($search !== '' ? '&q=' . urlencode($search) : ''))); ?>">›</a>
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
