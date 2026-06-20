<?php declare(strict_types=1); ?>
<?php require base_path('app/Views/partials/leave-nav.php'); ?>

<form method="post" action="<?= e(url('/admin/leave/create')); ?>" enctype="multipart/form-data">
    <?= csrf_field(); ?>
    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card content-card">
                <div class="card-body p-4">
                    <h5 class="mb-1">Add Leave for Employee</h5>
                    <p class="text-muted mb-4">Create an official leave record directly as HR/admin. This entry is saved as approved immediately.</p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Employee *</label>
                            <select name="employee_id" class="form-select" required>
                                <option value="">Select employee</option>
                                <?php foreach (($employeeOptions ?? []) as $value => $label): ?>
                                    <option value="<?= e((string) $value); ?>" <?= (string) old('employee_id', '') === (string) $value ? 'selected' : ''; ?>>
                                        <?= e((string) $label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Leave Type *</label>
                            <select name="leave_type_id" id="adminLeaveTypeSelect" class="form-select" required>
                                <option value="">Select leave type</option>
                                <?php foreach (($leaveTypes ?? []) as $leaveType): ?>
                                    <option value="<?= e((string) $leaveType['id']); ?>" data-requires-attachment="<?= (int) ($leaveType['requires_attachment'] ?? 0); ?>" <?= (string) old('leave_type_id', '') === (string) $leaveType['id'] ? 'selected' : ''; ?>>
                                        <?= e((string) $leaveType['name']); ?> (<?= e((string) $leaveType['code']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12" id="adminAttachmentField" style="display:none;">
                            <label class="form-label">Supporting Document / Attachment *</label>
                            <input type="file" name="attachment" id="adminAttachmentInput" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                            <div class="form-text">Accepted formats: PDF, JPG, PNG, DOC, DOCX (Max 10 MB)</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">From Date *</label>
                            <input type="date" name="start_date" class="form-control" value="<?= e((string) old('start_date', '')); ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">To Date *</label>
                            <input type="date" name="end_date" class="form-control" value="<?= e((string) old('end_date', '')); ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Start Session</label>
                            <select name="start_session" class="form-select">
                                <?php foreach (['full' => 'Full Day', 'first_half' => 'First Half', 'second_half' => 'Second Half'] as $value => $label): ?>
                                    <option value="<?= e($value); ?>" <?= (string) old('start_session', 'full') === $value ? 'selected' : ''; ?>><?= e($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">End Session</label>
                            <select name="end_session" class="form-select">
                                <?php foreach (['full' => 'Full Day', 'first_half' => 'First Half', 'second_half' => 'Second Half'] as $value => $label): ?>
                                    <option value="<?= e($value); ?>" <?= (string) old('end_session', 'full') === $value ? 'selected' : ''; ?>><?= e($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Reason *</label>
                            <textarea name="reason" class="form-control" rows="4" required><?= e((string) old('reason', '')); ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Admin Note</label>
                            <textarea name="admin_note" class="form-control" rows="3"><?= e((string) old('admin_note', '')); ?></textarea>
                            <div class="form-text">Stored on the approval trail as the admin decision note.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card content-card mb-4">
                <div class="card-body p-4">
                    <h5 class="mb-2">Admin Entry Rules</h5>
                    <ul class="text-muted small mb-3 ps-3">
                        <li>This form creates the leave directly as approved.</li>
                        <li>Balances update immediately after save.</li>
                        <li>Use actual leave dates in the from/to fields.</li>
                        <li>Attachment rules still follow the selected leave type.</li>
                    </ul>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Save Official Leave</button>
                        <a href="<?= e(url('/leave/balances')); ?>" class="btn btn-outline-secondary">Back to Balances</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
(function () {
    const leaveTypeSelect = document.getElementById('adminLeaveTypeSelect');
    const attachmentField = document.getElementById('adminAttachmentField');
    const attachmentInput = document.getElementById('adminAttachmentInput');

    function updateAttachmentField() {
        const selectedOption = leaveTypeSelect.options[leaveTypeSelect.selectedIndex];
        const requiresAttachment = selectedOption && selectedOption.dataset.requiresAttachment === '1';
        attachmentField.style.display = requiresAttachment ? 'block' : 'none';
        attachmentInput.required = requiresAttachment;
        if (!requiresAttachment) {
            attachmentInput.value = '';
        }
    }

    leaveTypeSelect.addEventListener('change', updateAttachmentField);
    document.addEventListener('DOMContentLoaded', updateAttachmentField);
}());
</script>
