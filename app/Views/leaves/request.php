<?php declare(strict_types=1); ?>
<?php require base_path('app/Views/partials/leave-nav.php'); ?>
<form method="post" action="<?= e(url('/leave/request')); ?>">
    <?= csrf_field(); ?>
    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card content-card">
                <div class="card-body p-4">
                    <h5 class="mb-1">Submit Leave Request</h5>
                    <p class="text-muted mb-4">Choose the leave type, dates, and reason for your request.</p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Leave Type *</label>
                            <select name="leave_type_id" id="leaveTypeSelect" class="form-select" required>
                                <option value="">Select leave type</option>
                                <?php foreach (($leaveTypes ?? []) as $leaveType): ?>
                                    <option value="<?= e((string) $leaveType['id']); ?>" data-requires-attachment="<?= (int) ($leaveType['requires_attachment'] ?? 0); ?>" <?= (string) old('leave_type_id', '') === (string) $leaveType['id'] ? 'selected' : ''; ?>>
                                        <?= e((string) $leaveType['name']); ?> (<?= e((string) $leaveType['code']); ?>)<?= (int) ($leaveType['requires_attachment'] ?? 0) === 1 ? ' · Attachment required' : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Replacement Employee *</label>
                            <select name="replacement_employee_id" id="replacementEmployeeSelect" class="form-select" required>
                                <option value="">Select replacement</option>
                                <?php foreach (($replacementEmployeeOptions ?? []) as $value => $label): ?>
                                    <option value="<?= e((string) $value); ?>" <?= (string) old('replacement_employee_id', '') === (string) $value ? 'selected' : ''; ?>>
                                        <?= e((string) $label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12" id="attachmentField" style="display: none;">
                            <label class="form-label">Supporting Document / Attachment *</label>
                            <input type="file" name="attachment" id="attachmentInput" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" />
                            <div class="form-text">Accepted formats: PDF, JPG, PNG, DOC, DOCX (Max 10 MB)</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Start Date *</label>
                            <input type="date" name="start_date" class="form-control" value="<?= e((string) old('start_date', '')); ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">End Date *</label>
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
                            <textarea name="reason" class="form-control" rows="5" required><?= e((string) old('reason', '')); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card content-card mb-4">
                <div class="card-body p-4">
                    <h5 class="mb-2">Before you submit</h5>
                    <ul class="text-muted small mb-3 ps-3">
                        <li>Use the correct leave type for your request.</li>
                        <li>Half-day selections only work for eligible leave types.</li>
                        <li>Manager and/or HR approval may be required.</li>
                    </ul>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Submit Request</button>
                        <a href="<?= e(url('/leave/my')); ?>" class="btn btn-outline-secondary">Back to My Leave</a>
                    </div>
                </div>
            </div>
            <div class="card content-card">
                <div class="card-body p-4">
                    <h6 class="mb-3">Current Balance Snapshot</h6>
                    <?php if (($balances ?? []) === []): ?>
                        <div class="empty-state p-3">No balance records found for this year.</div>
                    <?php else: ?>
                        <div class="d-grid gap-3">
                            <?php foreach ($balances as $balance): ?>
                                <div class="border rounded-4 p-3">
                                    <div class="fw-semibold"><?= e((string) $balance['leave_type_name']); ?></div>
                                    <div class="text-muted small">Available: <?= e((string) $balance['closing_balance']); ?> days</div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
const leaveTypeSelect = document.getElementById('leaveTypeSelect');
const attachmentField = document.getElementById('attachmentField');
const attachmentInput = document.getElementById('attachmentInput');

function updateAttachmentField() {
    const selectedOption = leaveTypeSelect.options[leaveTypeSelect.selectedIndex];
    const requiresAttachment = selectedOption.dataset.requiresAttachment === '1';

    if (requiresAttachment) {
        attachmentField.style.display = 'block';
        attachmentInput.required = true;
    } else {
        attachmentField.style.display = 'none';
        attachmentInput.required = false;
        attachmentInput.value = '';
    }
}

leaveTypeSelect.addEventListener('change', updateAttachmentField);

// Initialize on page load
document.addEventListener('DOMContentLoaded', updateAttachmentField);

// Form submission validation
document.querySelector('form').addEventListener('submit', function(e) {
    const selectedOption = leaveTypeSelect.options[leaveTypeSelect.selectedIndex];
    const requiresAttachment = selectedOption.dataset.requiresAttachment === '1';

    if (requiresAttachment && !attachmentInput.files.length) {
        e.preventDefault();
        alert('Please upload a supporting document for this leave type.');
        return false;
    }

    // Validate file size (10 MB max)
    if (attachmentInput.files.length > 0) {
        const file = attachmentInput.files[0];
        const maxSize = 10 * 1024 * 1024; // 10 MB
        if (file.size > maxSize) {
            e.preventDefault();
            alert('File size exceeds 10 MB limit. Please upload a smaller file.');
            return false;
        }
    }

    // Validate replacement employee is not self
    const replacementSelect = document.getElementById('replacementEmployeeSelect');
    if (replacementSelect.value === '') {
        e.preventDefault();
        alert('Please select a replacement employee.');
        return false;
    }
});
</script>