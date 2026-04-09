<?php declare(strict_types=1); ?>
<?php require base_path('app/Views/partials/communications-nav.php'); ?>
<?php
$ann = $announcement ?? [];
$ct  = $currentTarget ?? ['target_type' => 'all', 'target_id' => null];
$currentTargetType = (string) ($ct['target_type'] ?? 'all');
$currentTargetId   = $ct['target_id'] !== null ? (int) $ct['target_id'] : null;
// Format datetime-local values (strip seconds)
$fmtDt = static function (?string $v): string {
    if ($v === null || $v === '') return '';
    return substr($v, 0, 16); // "Y-m-d H:i"
};
?>
<div class="row g-4">
    <div class="col-xl-5">
        <div class="card content-card">
            <div class="card-body p-4">
                <h5 class="mb-4">Edit Announcement</h5>
                <form method="post" action="<?= e(url('/announcements/' . (int) ($ann['id'] ?? 0) . '/update')); ?>" enctype="multipart/form-data">
                    <?= csrf_field(); ?>
                    <div class="mb-3"><label class="form-label">Title *</label><input type="text" name="title" class="form-control" value="<?= e((string) ($ann['title'] ?? '')); ?>" required></div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6"><label class="form-label">Priority</label><select name="priority" class="form-select"><?php foreach (['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'] as $value => $label): ?><option value="<?= e($value); ?>" <?= (string) ($ann['priority'] ?? 'normal') === $value ? 'selected' : ''; ?>><?= e($label); ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-6"><label class="form-label">Status</label><select name="status" class="form-select"><?php foreach (['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'] as $value => $label): ?><option value="<?= e($value); ?>" <?= (string) ($ann['status'] ?? 'draft') === $value ? 'selected' : ''; ?>><?= e($label); ?></option><?php endforeach; ?></select></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Audience</label><select name="target_type" class="form-select"><?php foreach (['all' => 'All Employees', 'role' => 'Single Role', 'department' => 'Single Department', 'branch' => 'Single Branch', 'employee' => 'Single Employee'] as $value => $label): ?><option value="<?= e($value); ?>" <?= $currentTargetType === $value ? 'selected' : ''; ?>><?= e($label); ?></option><?php endforeach; ?></select></div>
                    <div class="mb-3"><label class="form-label">Role Target</label><select name="role_target_id" class="form-select"><option value="">Select role...</option><?php foreach (($targetOptions['roles'] ?? []) as $role): ?><option value="<?= e((string) $role['id']); ?>" <?= ($currentTargetType === 'role' && $currentTargetId === (int) $role['id']) ? 'selected' : ''; ?>><?= e((string) $role['name']); ?></option><?php endforeach; ?></select></div>
                    <div class="mb-3"><label class="form-label">Department Target</label><select name="department_target_id" class="form-select"><option value="">Select department...</option><?php foreach (($targetOptions['departments'] ?? []) as $department): ?><option value="<?= e((string) $department['id']); ?>" <?= ($currentTargetType === 'department' && $currentTargetId === (int) $department['id']) ? 'selected' : ''; ?>><?= e((string) $department['name']); ?></option><?php endforeach; ?></select></div>
                    <div class="mb-3"><label class="form-label">Branch Target</label><select name="branch_target_id" class="form-select"><option value="">Select branch...</option><?php foreach (($targetOptions['branches'] ?? []) as $branch): ?><option value="<?= e((string) $branch['id']); ?>" <?= ($currentTargetType === 'branch' && $currentTargetId === (int) $branch['id']) ? 'selected' : ''; ?>><?= e((string) $branch['name']); ?></option><?php endforeach; ?></select></div>
                    <div class="mb-3"><label class="form-label">Employee Target</label><select name="employee_target_id" class="form-select"><option value="">Select employee...</option><?php foreach (($targetOptions['employees'] ?? []) as $employee): ?><option value="<?= e((string) $employee['id']); ?>" <?= ($currentTargetType === 'employee' && $currentTargetId === (int) $employee['id']) ? 'selected' : ''; ?>><?= e((string) $employee['full_name']); ?> (<?= e((string) $employee['employee_code']); ?>)</option><?php endforeach; ?></select></div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6"><label class="form-label">Starts At</label><input type="datetime-local" name="starts_at" class="form-control" value="<?= e($fmtDt((string) ($ann['starts_at'] ?? ''))); ?>"></div>
                        <div class="col-md-6"><label class="form-label">Ends At</label><input type="datetime-local" name="ends_at" class="form-control" value="<?= e($fmtDt((string) ($ann['ends_at'] ?? ''))); ?>"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Send Emails At <span class="text-muted small">(optional — leave blank to send immediately on publish)</span></label>
                        <input type="datetime-local" name="email_send_at" class="form-control" value="<?= e($fmtDt((string) ($ann['email_send_at'] ?? ''))); ?>">
                        <div class="form-text">If set to a future time, emails will be queued but not delivered until you click <strong>Send Emails Now</strong> on the detail page.</div>
                    </div>
                    <div class="mb-3"><label class="form-label">Content *</label><textarea name="content" class="form-control" rows="8" required><?= e((string) ($ann['content'] ?? '')); ?></textarea></div>

                    <!-- Links -->
                    <div class="mb-3">
                        <label class="form-label"><i class="bi bi-link-45deg"></i> Links</label>
                        <div id="linksContainer">
                            <div class="row g-2 mb-2 link-row">
                                <div class="col-5"><input type="text" name="link_label[]" class="form-control form-control-sm" placeholder="Label"></div>
                                <div class="col-6"><input type="url" name="link_url[]" class="form-control form-control-sm" placeholder="https://..."></div>
                                <div class="col-1"><button type="button" class="btn btn-outline-danger btn-sm remove-link-btn" title="Remove"><i class="bi bi-x"></i></button></div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="addLinkBtn"><i class="bi bi-plus-lg"></i> Add Link</button>
                    </div>

                    <!-- Attachments -->
                    <div class="mb-3">
                        <label class="form-label"><i class="bi bi-paperclip"></i> Add Attachments</label>
                        <input type="file" name="attachments[]" class="form-control form-control-sm" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.webp,.txt,.csv,.zip,.rar">
                        <div class="form-text">Max 10 MB per file.</div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                        <a href="<?= e(url('/announcements/' . (int) ($ann['id'] ?? 0))); ?>" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-xl-7">
        <div class="card content-card">
            <div class="card-body p-4">
                <h6 class="mb-3 text-muted">Current Content Preview</h6>
                <div class="border rounded p-3 bg-light-subtle" style="white-space:pre-wrap;"><?= e((string) ($ann['content'] ?? '')); ?></div>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var addBtn = document.getElementById('addLinkBtn');
    var container = document.getElementById('linksContainer');
    if (addBtn && container) {
        addBtn.addEventListener('click', function() {
            var row = document.createElement('div');
            row.className = 'row g-2 mb-2 link-row';
            row.innerHTML = '<div class="col-5"><input type="text" name="link_label[]" class="form-control form-control-sm" placeholder="Label"></div><div class="col-6"><input type="url" name="link_url[]" class="form-control form-control-sm" placeholder="https://..."></div><div class="col-1"><button type="button" class="btn btn-outline-danger btn-sm remove-link-btn" title="Remove"><i class="bi bi-x"></i></button></div>';
            container.appendChild(row);
        });
        container.addEventListener('click', function(e) {
            var btn = e.target.closest('.remove-link-btn');
            if (btn) btn.closest('.link-row').remove();
        });
    }
});
</script>
