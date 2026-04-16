<?php declare(strict_types=1); ?>
<?php require base_path('app/Views/partials/workflow-nav.php'); ?>
<?php $statusClasses = ['pending' => 'text-bg-secondary', 'in_progress' => 'text-bg-primary', 'completed' => 'text-bg-success', 'cancelled' => 'text-bg-danger', 'waived' => 'text-bg-dark']; ?>

<div class="card content-card mb-4">
    <div class="card-body p-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-start gap-3">
            <div>
                <h5 class="mb-1"><?= e((string) $record['employee_name']); ?></h5>
                <div class="text-muted mb-2"><?= e((string) $record['employee_code']); ?> · <?= e((string) (($record['template_name'] ?? '') !== '' ? $record['template_name'] : 'Manual onboarding')); ?></div>
                <div class="small text-muted">Started <?= e((string) (($record['start_date'] ?? '') !== '' ? $record['start_date'] : 'Not set')); ?> · Due <?= e((string) (($record['due_date'] ?? '') !== '' ? $record['due_date'] : 'Not set')); ?></div>
            </div>
            <div class="text-md-end">
                <div class="mb-2"><span class="badge <?= e($statusClasses[(string) $record['status']] ?? 'text-bg-secondary'); ?>"><?= e(ucwords(str_replace('_', ' ', (string) $record['status']))); ?></span></div>
                <div class="fw-semibold"><?= e(number_format((float) ($record['progress_percent'] ?? 0), 0)); ?>% complete</div>
            </div>
        </div>
    </div>
</div>

<div class="card content-card">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h5 class="mb-1">Checklist Tasks</h5>
                <p class="text-muted mb-0">Update each onboarding step as the employee progresses through induction.</p>
            </div>
            <a href="<?= e(url('/employees/' . $record['employee_id'])); ?>" class="btn btn-outline-secondary">Employee Profile</a>
        </div>

        <?php if (($tasks ?? []) === []): ?>
            <div class="empty-state">No onboarding tasks were generated for this record.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Task</th><th>Assigned</th><th>Due</th><th>Status</th><th style="min-width: 280px;">Update</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($tasks as $task): ?>
                        <?php
                            $taskMeta = [];
                            if (!empty($task['meta_fields'])) {
                                $decoded = json_decode((string) $task['meta_fields'], true);
                                if (is_array($decoded)) { $taskMeta = $decoded; }
                            }
                            $taskMetaValues = [];
                            if (!empty($task['meta_values'])) {
                                $decoded = json_decode((string) $task['meta_values'], true);
                                if (is_array($decoded)) { $taskMetaValues = $decoded; }
                            }
                        ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= e((string) $task['task_name']); ?></div>
                                <div class="small text-muted"><?= e((string) (($task['description'] ?? '') !== '' ? $task['description'] : 'No description')); ?></div>
                                <?php if (!empty($taskMeta) && !empty($taskMetaValues)): ?>
                                    <div class="small mt-1">
                                        <?php foreach ($taskMeta as $field): ?>
                                            <?php $val = $taskMetaValues[$field['key']] ?? ''; if ($val !== ''): ?>
                                            <span class="text-muted"><?= e((string) $field['label']); ?>:</span> <strong><?= e($val); ?></strong> &nbsp;
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><div><?= e((string) (($task['assigned_to_name'] ?? '') !== '' ? $task['assigned_to_name'] : 'Unassigned')); ?></div><?php if (($task['completed_by_name'] ?? '') !== ''): ?><div class="small text-muted">Completed by <?= e((string) $task['completed_by_name']); ?></div><?php endif; ?></td>
                            <td><?= e((string) (($task['due_date'] ?? '') !== '' ? $task['due_date'] : '—')); ?></td>
                            <td><span class="badge <?= e($statusClasses[(string) $task['status']] ?? 'text-bg-secondary'); ?>"><?= e(ucwords(str_replace('_', ' ', (string) $task['status']))); ?></span></td>
                            <td>
                                <form method="post" action="<?= e(url('/onboarding/tasks/' . $task['id'] . '/update')); ?>" class="row g-2">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="onboarding_id" value="<?= e((string) $record['id']); ?>">
                                    <div class="col-md-4"><select name="status" class="form-select form-select-sm"><?php foreach (['pending' => 'Pending', 'in_progress' => 'In Progress', 'completed' => 'Completed', 'waived' => 'Waived'] as $value => $label): ?><option value="<?= e($value); ?>" <?= (string) $task['status'] === $value ? 'selected' : ''; ?>><?= e($label); ?></option><?php endforeach; ?></select></div>
                                    <div class="col-md-5"><input type="text" name="remarks" class="form-control form-control-sm" placeholder="Remarks" value="<?= e((string) ($task['remarks'] ?? '')); ?>"></div>
                                    <div class="col-md-3 d-grid"><button type="submit" class="btn btn-sm btn-outline-primary">Save</button></div>
                                    <?php if (!empty($taskMeta)): ?>
                                        <div class="col-12"><hr class="my-1"><div class="small text-muted fw-semibold mb-1">Additional Information</div><div class="row g-2">
                                        <?php foreach ($taskMeta as $field): ?>
                                            <div class="col-md-6">
                                                <label class="form-label small mb-1"><?= e((string) $field['label']); ?><?= !empty($field['required']) ? ' *' : ''; ?></label>
                                                <?php if (($field['type'] ?? 'text') === 'textarea'): ?>
                                                    <textarea name="meta_value_<?= e((string) $field['key']); ?>" class="form-control form-control-sm" rows="2"><?= e((string) ($taskMetaValues[$field['key']] ?? '')); ?></textarea>
                                                <?php else: ?>
                                                    <input type="<?= e((string) ($field['type'] ?? 'text')); ?>" name="meta_value_<?= e((string) $field['key']); ?>" class="form-control form-control-sm" value="<?= e((string) ($taskMetaValues[$field['key']] ?? '')); ?>" <?= !empty($field['required']) ? 'required' : ''; ?>>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                        </div></div>
                                    <?php endif; ?>
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