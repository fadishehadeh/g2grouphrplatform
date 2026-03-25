<?php declare(strict_types=1); ?>
<?php require base_path('app/Views/partials/workflow-nav.php'); ?>
<?php $statusClasses = ['draft' => 'text-bg-secondary', 'pending' => 'text-bg-secondary', 'in_progress' => 'text-bg-primary', 'completed' => 'text-bg-success', 'cancelled' => 'text-bg-danger', 'waived' => 'text-bg-dark']; ?>
<?php $assetClasses = ['pending' => 'text-bg-secondary', 'returned' => 'text-bg-success', 'missing' => 'text-bg-danger', 'waived' => 'text-bg-dark']; ?>
<?php $clearanceClasses = ['pending' => 'text-bg-secondary', 'partial' => 'text-bg-warning', 'cleared' => 'text-bg-success']; ?>

<div class="card content-card mb-4">
    <div class="card-body p-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-start gap-3">
            <div>
                <h5 class="mb-1"><?= e((string) $record['employee_name']); ?></h5>
                <div class="text-muted mb-2"><?= e((string) $record['employee_code']); ?> · <?= e(ucwords(str_replace('_', ' ', (string) $record['record_type']))); ?></div>
                <div class="small text-muted">Exit <?= e((string) $record['exit_date']); ?> · Last working day <?= e((string) (($record['last_working_date'] ?? '') !== '' ? $record['last_working_date'] : '—')); ?></div>
            </div>
            <div class="text-md-end">
                <div class="mb-2"><span class="badge <?= e($statusClasses[(string) $record['status']] ?? 'text-bg-secondary'); ?>"><?= e(ucwords(str_replace('_', ' ', (string) $record['status']))); ?></span></div>
                <div><span class="badge <?= e($clearanceClasses[(string) $record['clearance_status']] ?? 'text-bg-secondary'); ?>"><?= e(ucwords((string) $record['clearance_status'])); ?></span></div>
            </div>
        </div>
        <div class="mt-3 text-muted"><?= nl2br(e((string) (($record['remarks'] ?? '') !== '' ? $record['remarks'] : 'No remarks added for this offboarding record.'))); ?></div>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-7">
        <div class="card content-card h-100">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center gap-3 mb-4">
                    <div>
                        <h5 class="mb-1">Offboarding Checklist</h5>
                        <p class="text-muted mb-0">Coordinate closure actions across departments and record their completion.</p>
                    </div>
                    <a href="<?= e(url('/employees/' . $record['employee_id'])); ?>" class="btn btn-outline-secondary">Employee Profile</a>
                </div>
                <form method="post" action="<?= e(url('/offboarding/' . $record['id'] . '/tasks')); ?>" class="border rounded-4 p-3 bg-light-subtle mb-4">
                    <?= csrf_field(); ?>
                    <div class="row g-3 align-items-end">
                        <div class="col-md-5"><label class="form-label">Task Name</label><input type="text" name="new_task_name" class="form-control" maxlength="150" value="<?= e((string) old('new_task_name', '')); ?>" placeholder="Collect laptop" required></div>
                        <div class="col-md-3"><label class="form-label">Department</label><select name="new_department_id" class="form-select"><option value="">No department</option><?php foreach (($options['departments'] ?? []) as $department): ?><option value="<?= e((string) $department['id']); ?>" <?= (string) old('new_department_id', '') === (string) $department['id'] ? 'selected' : ''; ?>><?= e((string) $department['name']); ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-4"><label class="form-label">Owner</label><select name="new_assigned_to_user_id" class="form-select"><option value="">Unassigned</option><?php foreach (($options['users'] ?? []) as $user): ?><option value="<?= e((string) $user['id']); ?>" <?= (string) old('new_assigned_to_user_id', '') === (string) $user['id'] ? 'selected' : ''; ?>><?= e((string) $user['name']); ?><?= ((string) ($user['username'] ?? '') !== '') ? ' (' . e((string) $user['username']) . ')' : ''; ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-3"><label class="form-label">Due Date</label><input type="date" name="new_due_date" class="form-control" value="<?= e((string) old('new_due_date', '')); ?>"></div>
                        <div class="col-md-3"><label class="form-label">Status</label><select name="new_status" class="form-select"><?php foreach (['pending' => 'Pending', 'in_progress' => 'In Progress', 'completed' => 'Completed', 'waived' => 'Waived'] as $value => $label): ?><option value="<?= e($value); ?>" <?= (string) old('new_status', 'pending') === $value ? 'selected' : ''; ?>><?= e($label); ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-4"><label class="form-label">Remarks</label><input type="text" name="new_remarks" class="form-control" maxlength="255" value="<?= e((string) old('new_remarks', '')); ?>" placeholder="Optional notes"></div>
                        <div class="col-md-2 d-grid"><button type="submit" class="btn btn-primary">Add Task</button></div>
                    </div>
                </form>
                <?php if (($tasks ?? []) === []): ?>
                    <div class="empty-state">No checklist tasks exist for this record.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Task</th><th>Owner</th><th>Due</th><th>Status</th><th style="min-width: 420px;">Update</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($tasks as $task): ?>
                                <tr>
                                    <td><div class="fw-semibold"><?= e((string) $task['task_name']); ?></div><div class="small text-muted"><?= e((string) (($task['department_name'] ?? '') !== '' ? $task['department_name'] : 'No department')); ?></div></td>
                                    <td><?= e((string) (($task['assigned_to_name'] ?? '') !== '' ? $task['assigned_to_name'] : 'Unassigned')); ?></td>
                                    <td><?= e((string) (($task['due_date'] ?? '') !== '' ? $task['due_date'] : '—')); ?></td>
                                    <td><span class="badge <?= e($statusClasses[(string) $task['status']] ?? 'text-bg-secondary'); ?>"><?= e(ucwords(str_replace('_', ' ', (string) $task['status']))); ?></span></td>
                                    <td>
                                        <form method="post" action="<?= e(url('/offboarding/tasks/' . $task['id'] . '/update')); ?>" class="row g-2">
                                            <?= csrf_field(); ?>
                                            <input type="hidden" name="offboarding_id" value="<?= e((string) $record['id']); ?>">
                                            <div class="col-12"><input type="text" name="task_name" class="form-control form-control-sm" maxlength="150" value="<?= e((string) $task['task_name']); ?>" required></div>
                                            <div class="col-md-6"><select name="department_id" class="form-select form-select-sm"><option value="">No department</option><?php foreach (($options['departments'] ?? []) as $department): ?><option value="<?= e((string) $department['id']); ?>" <?= (string) ($task['department_id'] ?? '') === (string) $department['id'] ? 'selected' : ''; ?>><?= e((string) $department['name']); ?></option><?php endforeach; ?></select></div>
                                            <div class="col-md-6"><select name="assigned_to_user_id" class="form-select form-select-sm"><option value="">Unassigned</option><?php foreach (($options['users'] ?? []) as $user): ?><option value="<?= e((string) $user['id']); ?>" <?= (string) ($task['assigned_to_user_id'] ?? '') === (string) $user['id'] ? 'selected' : ''; ?>><?= e((string) $user['name']); ?><?= ((string) ($user['username'] ?? '') !== '') ? ' (' . e((string) $user['username']) . ')' : ''; ?></option><?php endforeach; ?></select></div>
                                            <div class="col-md-4"><input type="date" name="due_date" class="form-control form-control-sm" value="<?= e((string) ($task['due_date'] ?? '')); ?>"></div>
                                            <div class="col-md-4"><select name="status" class="form-select form-select-sm"><?php foreach (['pending' => 'Pending', 'in_progress' => 'In Progress', 'completed' => 'Completed', 'waived' => 'Waived'] as $value => $label): ?><option value="<?= e($value); ?>" <?= (string) $task['status'] === $value ? 'selected' : ''; ?>><?= e($label); ?></option><?php endforeach; ?></select></div>
                                            <div class="col-md-4 d-grid"><button type="submit" class="btn btn-sm btn-outline-primary">Save</button></div>
                                            <div class="col-12"><input type="text" name="remarks" class="form-control form-control-sm" maxlength="255" placeholder="Remarks" value="<?= e((string) ($task['remarks'] ?? '')); ?>"></div>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card content-card h-100">
            <div class="card-body p-4">
                <h5 class="mb-1">Asset Returns</h5>
                <p class="text-muted mb-4">Track issued assets and capture their return status before clearance closes.</p>
                <form method="post" action="<?= e(url('/offboarding/' . $record['id'] . '/assets')); ?>" class="border rounded-4 p-3 bg-light-subtle mb-4">
                    <?= csrf_field(); ?>
                    <div class="row g-3 align-items-end">
                        <div class="col-md-5"><label class="form-label">Asset Name</label><input type="text" name="new_asset_name" class="form-control" maxlength="150" value="<?= e((string) old('new_asset_name', '')); ?>" placeholder="Laptop" required></div>
                        <div class="col-md-3"><label class="form-label">Asset Code</label><input type="text" name="new_asset_code" class="form-control" maxlength="50" value="<?= e((string) old('new_asset_code', '')); ?>" placeholder="IT-001"></div>
                        <div class="col-md-2"><label class="form-label">Qty</label><input type="number" min="1" step="1" name="new_quantity" class="form-control" value="<?= e((string) old('new_quantity', '1')); ?>" required></div>
                        <div class="col-md-2"><label class="form-label">Status</label><select name="new_return_status" class="form-select"><?php foreach (['pending' => 'Pending', 'returned' => 'Returned', 'missing' => 'Missing', 'waived' => 'Waived'] as $value => $label): ?><option value="<?= e($value); ?>" <?= (string) old('new_return_status', 'pending') === $value ? 'selected' : ''; ?>><?= e($label); ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-9"><label class="form-label">Remarks</label><input type="text" name="new_remarks" class="form-control" maxlength="255" value="<?= e((string) old('new_remarks', '')); ?>" placeholder="Optional notes"></div>
                        <div class="col-md-3 d-grid"><button type="submit" class="btn btn-primary">Add Asset</button></div>
                    </div>
                </form>
                <?php if (($assets ?? []) === []): ?>
                    <div class="empty-state">No asset return items were added to this record.</div>
                <?php else: ?>
                    <div class="d-grid gap-3">
                        <?php foreach ($assets as $asset): ?>
                            <div class="border rounded-4 p-3">
                                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                    <div>
                                        <div class="fw-semibold"><?= e((string) $asset['asset_name']); ?></div>
                                        <div class="small text-muted">Qty <?= e((string) ($asset['quantity'] ?? 1)); ?><?= (($asset['asset_code'] ?? '') !== '') ? ' · ' . e((string) $asset['asset_code']) : ''; ?></div>
                                        <?php if (($asset['checked_by_name'] ?? '') !== ''): ?><div class="small text-muted">Checked by <?= e((string) $asset['checked_by_name']); ?><?= (($asset['checked_at'] ?? '') !== '') ? ' · ' . e((string) $asset['checked_at']) : ''; ?></div><?php endif; ?>
                                    </div>
                                    <span class="badge <?= e($assetClasses[(string) $asset['return_status']] ?? 'text-bg-secondary'); ?>"><?= e(ucwords((string) $asset['return_status'])); ?></span>
                                </div>
                                <form method="post" action="<?= e(url('/offboarding/assets/' . $asset['id'] . '/update')); ?>" class="row g-2">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="offboarding_id" value="<?= e((string) $record['id']); ?>">
                                    <div class="col-md-5"><input type="text" name="asset_name" class="form-control form-control-sm" maxlength="150" value="<?= e((string) $asset['asset_name']); ?>" required></div>
                                    <div class="col-md-3"><input type="text" name="asset_code" class="form-control form-control-sm" maxlength="50" value="<?= e((string) ($asset['asset_code'] ?? '')); ?>" placeholder="Asset code"></div>
                                    <div class="col-md-2"><input type="number" min="1" step="1" name="quantity" class="form-control form-control-sm" value="<?= e((string) ($asset['quantity'] ?? 1)); ?>" required></div>
                                    <div class="col-md-2"><select name="return_status" class="form-select form-select-sm"><?php foreach (['pending' => 'Pending', 'returned' => 'Returned', 'missing' => 'Missing', 'waived' => 'Waived'] as $value => $label): ?><option value="<?= e($value); ?>" <?= (string) $asset['return_status'] === $value ? 'selected' : ''; ?>><?= e($label); ?></option><?php endforeach; ?></select></div>
                                    <div class="col-md-9"><input type="text" name="remarks" class="form-control form-control-sm" maxlength="255" placeholder="Remarks" value="<?= e((string) ($asset['remarks'] ?? '')); ?>"></div>
                                    <div class="col-md-3 d-grid"><button type="submit" class="btn btn-sm btn-outline-primary">Save</button></div>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>