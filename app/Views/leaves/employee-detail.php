<?php declare(strict_types=1); ?>
<?php require base_path('app/Views/partials/leave-nav.php'); ?>
<?php
$editing = is_array($editLeaveRequest ?? null) && $editLeaveRequest !== [];
$formAction = $editing
    ? url('/admin/leave/requests/' . (int) ($editLeaveRequest['id'] ?? 0) . '/edit')
    : url('/admin/leave/create');
$selectedLeaveTypeId = (string) old('leave_type_id', $editing ? (string) ($editLeaveRequest['leave_type_id'] ?? '') : '');
$selectedStartSession = (string) old('start_session', $editing ? (string) ($editLeaveRequest['start_session'] ?? 'full') : 'full');
$selectedEndSession = (string) old('end_session', $editing ? (string) ($editLeaveRequest['end_session'] ?? 'full') : 'full');
$existingAttachmentCount = count($editAttachments ?? []);
?>

<div class="card content-card mb-4">
    <div class="card-body p-4">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
            <div>
                <div class="small text-muted mb-2">Employee Leave Detail</div>
                <h4 class="mb-1"><?= e((string) ($employee['employee_name'] ?? '')); ?></h4>
                <div class="text-muted"><?= e((string) ($employee['employee_code'] ?? '')); ?> &middot; <?= e((string) ($employee['work_email'] ?? '')); ?></div>
            </div>
            <div class="text-lg-end small">
                <div><span class="text-muted">Status:</span> <span class="fw-semibold"><?= e((string) ($employee['employee_status'] ?? '')); ?></span></div>
                <div><span class="text-muted">Joining Date:</span> <span class="fw-semibold"><?= e((string) (($employee['joining_date'] ?? '') !== '' ? $employee['joining_date'] : '-')); ?></span></div>
                <div><span class="text-muted">Manager:</span> <span class="fw-semibold"><?= e((string) (($employee['manager_name'] ?? '') !== '' ? $employee['manager_name'] : '-')); ?></span></div>
            </div>
        </div>
        <div class="row g-3 mt-1 small">
            <div class="col-md-4"><span class="text-muted">Company:</span> <?= e((string) (($employee['company_name'] ?? '') !== '' ? $employee['company_name'] : '-')); ?></div>
            <div class="col-md-4"><span class="text-muted">Department:</span> <?= e((string) (($employee['department_name'] ?? '') !== '' ? $employee['department_name'] : '-')); ?></div>
            <div class="col-md-4"><span class="text-muted">Job Title:</span> <?= e((string) (($employee['job_title_name'] ?? '') !== '' ? $employee['job_title_name'] : '-')); ?></div>
            <div class="col-md-4"><span class="text-muted">Branch:</span> <?= e((string) (($employee['branch_name'] ?? '') !== '' ? $employee['branch_name'] : '-')); ?></div>
            <div class="col-md-4"><span class="text-muted">Team:</span> <?= e((string) (($employee['team_name'] ?? '') !== '' ? $employee['team_name'] : '-')); ?></div>
            <div class="col-md-4"><span class="text-muted">Year in View:</span> <?= e((string) ($year ?? date('Y'))); ?></div>
        </div>
        <div class="d-flex gap-2 flex-wrap mt-4">
            <a href="<?= e(url('/leave/balances?year=' . (int) ($year ?? date('Y')))); ?>" class="btn btn-outline-secondary">Back to Balances</a>
            <?php if ($editing): ?>
                <a href="<?= e(url('/admin/leave/employees/' . (int) ($employee['id'] ?? 0) . '?year=' . (int) ($year ?? date('Y')))); ?>" class="btn btn-outline-light border">Cancel Edit</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-7">
        <div class="card content-card h-100">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">Leave Balances</h5>
                    <span class="text-muted small"><?= e((string) ($year ?? date('Y'))); ?></span>
                </div>
                <?php if (($balances ?? []) === []): ?>
                    <div class="empty-state">No leave balances are available for this employee in the selected year.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Leave Type</th>
                                <th>Opening</th>
                                <th>Accrued</th>
                                <th>Carry Forward</th>
                                <th>Used</th>
                                <th>Adjusted</th>
                                <th>Available</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($balances as $balance): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= e((string) ($balance['leave_type_name'] ?? '')); ?></div>
                                        <?php if ((string) ($balance['leave_type_code'] ?? '') === 'annual_leave'): ?>
                                            <div class="small text-muted">
                                                Current year: <?= e(number_format((float) ($balance['current_year_entitlement'] ?? 0), 2)); ?>
                                                &middot; Carry available: <?= e(number_format((float) ($balance['carry_forward_available'] ?? 0), 2)); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e(number_format((float) ($balance['opening_balance'] ?? 0), 2)); ?></td>
                                    <td><?= e(number_format((float) ($balance['accrued'] ?? 0), 2)); ?></td>
                                    <td><?= e(number_format((float) ($balance['carry_forward_amount'] ?? 0), 2)); ?></td>
                                    <td><?= e(number_format((float) ($balance['used_amount'] ?? 0), 2)); ?></td>
                                    <td><?= e(number_format((float) ($balance['adjusted_amount'] ?? 0), 2)); ?></td>
                                    <td class="fw-semibold"><?= e(number_format((float) ($balance['closing_balance'] ?? 0), 2)); ?></td>
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
        <div class="card content-card">
            <div class="card-body p-4">
                <h5 class="mb-1"><?= $editing ? 'Edit Approved Leave' : 'Add Leave for Employee'; ?></h5>
                <p class="text-muted mb-4">
                    <?= $editing
                        ? 'Update the approved leave record. The system will reverse the old balance impact and apply the new dates automatically.'
                        : 'Create an official leave record directly as HR/admin. Days are calculated automatically from the leave dates and sessions.'; ?>
                </p>

                <form method="post" action="<?= e($formAction); ?>" enctype="multipart/form-data">
                    <?= csrf_field(); ?>
                    <input type="hidden" name="employee_id" value="<?= e((string) ($employee['id'] ?? 0)); ?>">
                    <input type="hidden" name="return_year" value="<?= e((string) ($year ?? date('Y'))); ?>">
                    <?php if (!$editing): ?>
                        <input type="hidden" name="return_to_employee" value="1">
                    <?php endif; ?>

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Leave Type *</label>
                            <select name="leave_type_id" id="employeeLeaveTypeSelect" class="form-select" required>
                                <option value="">Select leave type</option>
                                <?php foreach (($leaveTypes ?? []) as $leaveType): ?>
                                    <option
                                        value="<?= e((string) $leaveType['id']); ?>"
                                        data-requires-attachment="<?= (int) ($leaveType['requires_attachment'] ?? 0); ?>"
                                        <?= $selectedLeaveTypeId === (string) $leaveType['id'] ? 'selected' : ''; ?>
                                    >
                                        <?= e((string) $leaveType['name']); ?> (<?= e((string) $leaveType['code']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">From Date *</label>
                            <input type="date" name="start_date" class="form-control" value="<?= e((string) old('start_date', $editing ? (string) ($editLeaveRequest['start_date'] ?? '') : '')); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">To Date *</label>
                            <input type="date" name="end_date" class="form-control" value="<?= e((string) old('end_date', $editing ? (string) ($editLeaveRequest['end_date'] ?? '') : '')); ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Start Session</label>
                            <select name="start_session" class="form-select">
                                <?php foreach (['full' => 'Full Day', 'first_half' => 'First Half', 'second_half' => 'Second Half'] as $value => $label): ?>
                                    <option value="<?= e($value); ?>" <?= $selectedStartSession === $value ? 'selected' : ''; ?>><?= e($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">End Session</label>
                            <select name="end_session" class="form-select">
                                <?php foreach (['full' => 'Full Day', 'first_half' => 'First Half', 'second_half' => 'Second Half'] as $value => $label): ?>
                                    <option value="<?= e($value); ?>" <?= $selectedEndSession === $value ? 'selected' : ''; ?>><?= e($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Reason *</label>
                            <textarea name="reason" class="form-control" rows="3" required><?= e((string) old('reason', $editing ? (string) ($editLeaveRequest['reason'] ?? '') : '')); ?></textarea>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Admin Note</label>
                            <textarea name="admin_note" class="form-control" rows="3"><?= e((string) old('admin_note', '')); ?></textarea>
                            <div class="form-text">Stored on the approval trail when provided.</div>
                        </div>

                        <div class="col-12" id="employeeAttachmentField" style="display:none;">
                            <label class="form-label">Supporting Document / Attachment *</label>
                            <input type="file" name="attachment" id="employeeAttachmentInput" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                            <div class="form-text">Accepted formats: PDF, JPG, PNG, DOC, DOCX (Max 10 MB)</div>
                            <?php if ($editing && $existingAttachmentCount > 0): ?>
                                <div class="small text-muted mt-2">Existing attachments on file: <?= e((string) $existingAttachmentCount); ?>. Upload a new file only if you want to add another document.</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-primary"><?= $editing ? 'Update Approved Leave' : 'Save Official Leave'; ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card content-card">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Leave History</h5>
            <span class="text-muted small"><?= e((string) count($leaveHistory ?? [])); ?> record(s)</span>
        </div>
        <?php if (($leaveHistory ?? []) === []): ?>
            <div class="empty-state">No leave records were found for this employee.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Leave Type</th>
                        <th>Dates</th>
                        <th>Days</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($leaveHistory as $leaveRow): ?>
                        <?php $statusValue = (string) ($leaveRow['status'] ?? ''); ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= e((string) ($leaveRow['leave_type_name'] ?? '')); ?></div>
                                <div class="small text-muted"><?= e((string) ($leaveRow['reason'] ?? '')); ?></div>
                            </td>
                            <td>
                                <div><?= e((string) ($leaveRow['start_date'] ?? '')); ?> &rarr; <?= e((string) ($leaveRow['end_date'] ?? '')); ?></div>
                                <div class="small text-muted"><?= e(ucwords(str_replace('_', ' ', (string) ($leaveRow['start_session'] ?? 'full')))); ?> / <?= e(ucwords(str_replace('_', ' ', (string) ($leaveRow['end_session'] ?? 'full')))); ?></div>
                            </td>
                            <td class="fw-semibold"><?= e(number_format((float) ($leaveRow['days_requested'] ?? 0), 2)); ?></td>
                            <td><span class="badge <?= $statusValue === 'approved' ? 'text-bg-success' : (in_array($statusValue, ['rejected', 'cancelled', 'withdrawn'], true) ? 'text-bg-danger' : 'text-bg-warning'); ?>"><?= e(ucwords(str_replace('_', ' ', $statusValue))); ?></span></td>
                            <td><?= e((string) (($leaveRow['submitted_at'] ?? null) !== null ? $leaveRow['submitted_at'] : $leaveRow['created_at'])); ?></td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-2">
                                    <a href="<?= e(url('/leave/requests/' . (int) ($leaveRow['id'] ?? 0))); ?>" class="btn btn-outline-secondary btn-sm">View</a>
                                    <?php if ($statusValue === 'approved'): ?>
                                        <a href="<?= e(url('/admin/leave/requests/' . (int) ($leaveRow['id'] ?? 0) . '/edit?year=' . (int) ($year ?? date('Y')))); ?>" class="btn btn-outline-primary btn-sm">Edit</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    const leaveTypeSelect = document.getElementById('employeeLeaveTypeSelect');
    const attachmentField = document.getElementById('employeeAttachmentField');
    const attachmentInput = document.getElementById('employeeAttachmentInput');
    const hasExistingAttachment = <?= $existingAttachmentCount > 0 ? 'true' : 'false'; ?>;

    function updateAttachmentField() {
        if (!leaveTypeSelect || !attachmentField || !attachmentInput) {
            return;
        }

        const selectedOption = leaveTypeSelect.options[leaveTypeSelect.selectedIndex];
        const requiresAttachment = selectedOption && selectedOption.dataset.requiresAttachment === '1';
        attachmentField.style.display = requiresAttachment ? 'block' : 'none';
        attachmentInput.required = requiresAttachment && !hasExistingAttachment;
        if (!requiresAttachment) {
            attachmentInput.value = '';
        }
    }

    updateAttachmentField();
    leaveTypeSelect.addEventListener('change', updateAttachmentField);
}());
</script>
