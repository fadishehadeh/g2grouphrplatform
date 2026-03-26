<?php declare(strict_types=1); ?>
<div class="card content-card">
    <div class="card-body p-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div>
                <h5 class="mb-1">Employee Directory</h5>
                <p class="text-muted mb-0">Browse and maintain the core employee master records.</p>
            </div>
            <div class="d-flex gap-2">
                <form method="get" action="<?= e(url('/employees')); ?>" class="d-flex gap-2">
                    <input type="text" name="q" class="form-control" placeholder="Search employees..." value="<?= e((string) ($search ?? '')); ?>">
                    <button type="submit" class="btn btn-outline-secondary">Search</button>
                </form>
                <a href="<?= e(url('/employees/export-excel')); ?>" class="btn btn-outline-success" title="Export Excel"><i class="bi bi-file-earmark-excel"></i> Excel</a>
                <a href="<?= e(url('/employees/export-pdf')); ?>" class="btn btn-outline-danger" title="Export PDF"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
                <?php if (can('employee.create')): ?>
                    <a href="<?= e(url('/employees/import')); ?>" class="btn btn-outline-primary" title="Import Employees"><i class="bi bi-upload"></i> Import</a>
                    <a href="<?= e(url('/employees/create')); ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add Employee</a>
                <?php endif; ?>
            </div>
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
        <?php endif; ?>
    </div>
</div>