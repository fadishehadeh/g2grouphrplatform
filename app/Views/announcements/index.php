<?php declare(strict_types=1); ?>
<?php require base_path('app/Views/partials/communications-nav.php'); ?>
<div class="row g-4">
    <?php if (($canManage ?? false) === true): ?>
        <div class="col-xl-4">
            <div class="card content-card h-100">
                <div class="card-body p-4">
                    <h5 class="mb-2">Publish Announcement</h5>
                    <p class="text-muted small mb-4">Create targeted internal updates for the whole company, a role, a branch, a department, or a specific employee.</p>
                    <form method="post" action="<?= e(url('/announcements')); ?>" enctype="multipart/form-data">
                        <?= csrf_field(); ?>
                        <div class="mb-3"><label class="form-label">Title *</label><input type="text" name="title" class="form-control" value="<?= e((string) old('title', '')); ?>" required></div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6"><label class="form-label">Priority</label><select name="priority" class="form-select"><?php foreach (['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'] as $value => $label): ?><option value="<?= e($value); ?>" <?= (string) old('priority', 'normal') === $value ? 'selected' : ''; ?>><?= e($label); ?></option><?php endforeach; ?></select></div>
                            <div class="col-md-6"><label class="form-label">Status</label><select name="status" class="form-select"><?php foreach (['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'] as $value => $label): ?><option value="<?= e($value); ?>" <?= (string) old('status', 'published') === $value ? 'selected' : ''; ?>><?= e($label); ?></option><?php endforeach; ?></select></div>
                        </div>
                        <div class="mb-3"><label class="form-label">Audience</label><select name="target_type" class="form-select"><?php foreach (['all' => 'All Employees', 'role' => 'Single Role', 'department' => 'Single Department', 'branch' => 'Single Branch', 'employee' => 'Single Employee'] as $value => $label): ?><option value="<?= e($value); ?>" <?= (string) old('target_type', 'all') === $value ? 'selected' : ''; ?>><?= e($label); ?></option><?php endforeach; ?></select></div>
                        <div class="mb-3"><label class="form-label">Role Target</label><select name="role_target_id" class="form-select"><option value="">Select role...</option><?php foreach (($targetOptions['roles'] ?? []) as $role): ?><option value="<?= e((string) $role['id']); ?>" <?= (string) old('role_target_id', '') === (string) $role['id'] ? 'selected' : ''; ?>><?= e((string) $role['name']); ?></option><?php endforeach; ?></select></div>
                        <div class="mb-3"><label class="form-label">Department Target</label><select name="department_target_id" class="form-select"><option value="">Select department...</option><?php foreach (($targetOptions['departments'] ?? []) as $department): ?><option value="<?= e((string) $department['id']); ?>" <?= (string) old('department_target_id', '') === (string) $department['id'] ? 'selected' : ''; ?>><?= e((string) $department['name']); ?></option><?php endforeach; ?></select></div>
                        <div class="mb-3"><label class="form-label">Branch Target</label><select name="branch_target_id" class="form-select"><option value="">Select branch...</option><?php foreach (($targetOptions['branches'] ?? []) as $branch): ?><option value="<?= e((string) $branch['id']); ?>" <?= (string) old('branch_target_id', '') === (string) $branch['id'] ? 'selected' : ''; ?>><?= e((string) $branch['name']); ?></option><?php endforeach; ?></select></div>
                        <div class="mb-3"><label class="form-label">Employee Target</label><select name="employee_target_id" class="form-select"><option value="">Select employee...</option><?php foreach (($targetOptions['employees'] ?? []) as $employee): ?><option value="<?= e((string) $employee['id']); ?>" <?= (string) old('employee_target_id', '') === (string) $employee['id'] ? 'selected' : ''; ?>><?= e((string) $employee['full_name']); ?> (<?= e((string) $employee['employee_code']); ?>)</option><?php endforeach; ?></select></div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6"><label class="form-label">Starts At</label><input type="datetime-local" name="starts_at" class="form-control" value="<?= e((string) old('starts_at', '')); ?>"></div>
                            <div class="col-md-6"><label class="form-label">Ends At</label><input type="datetime-local" name="ends_at" class="form-control" value="<?= e((string) old('ends_at', '')); ?>"></div>
                        </div>
                        <div class="mb-3"><label class="form-label">Content *</label><textarea name="content" class="form-control" rows="6" required><?= e((string) old('content', '')); ?></textarea></div>

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
                            <label class="form-label"><i class="bi bi-paperclip"></i> Attachments</label>
                            <input type="file" name="attachments[]" class="form-control form-control-sm" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.webp,.txt,.csv,.zip,.rar">
                            <div class="form-text">Max 10 MB per file. Allowed: PDF, Office docs, images, text, archives.</div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Save Announcement</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-xl-8">
    <?php else: ?>
        <div class="col-12">
    <?php endif; ?>
            <div class="card content-card">
                <div class="card-body p-4">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                        <div>
                            <h5 class="mb-1"><?= ($canManage ?? false) ? 'Announcement Center' : 'Published Announcements'; ?></h5>
                            <p class="text-muted mb-0"><?= ($canManage ?? false) ? 'Review drafts, schedules, publication status, and employee read activity at a glance.' : 'Read current internal updates targeted to you or your team.'; ?></p>
                        </div>
                        <form method="get" action="<?= e(url('/announcements')); ?>" class="d-flex flex-column flex-md-row gap-2">
                            <input type="text" name="q" class="form-control" placeholder="Search announcements..." value="<?= e((string) ($search ?? '')); ?>">
                            <?php if (($canManage ?? false) === true): ?><select name="status" class="form-select"><?php foreach (['all' => 'All statuses', 'draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'] as $value => $label): ?><option value="<?= e($value); ?>" <?= (string) ($status ?? 'all') === $value ? 'selected' : ''; ?>><?= e($label); ?></option><?php endforeach; ?></select><?php endif; ?>
                            <button type="submit" class="btn btn-outline-secondary">Filter</button>
                        </form>
                    </div>
                    <?php if (($announcements ?? []) === []): ?>
                        <div class="empty-state">No announcements matched the current filters.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                <tr>
                                    <th>Announcement</th><th>Audience</th><th>Window</th><th>Status</th><th>Read</th><th></th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($announcements as $announcement): ?>
                                    <?php $priorityClass = match ((string) $announcement['priority']) { 'urgent' => 'text-bg-danger', 'high' => 'text-bg-warning', 'low' => 'text-bg-secondary', default => 'text-bg-primary' }; $statusClass = match ((string) $announcement['status']) { 'published' => 'text-bg-success', 'archived' => 'text-bg-dark', default => 'text-bg-secondary' }; ?>
                                    <tr>
                                        <td><div class="fw-semibold"><?= e((string) $announcement['title']); ?></div><div class="small text-muted">By <?= e((string) $announcement['created_by_name']); ?> • <?= e((string) $announcement['created_at']); ?></div><div class="mt-1"><span class="badge <?= e($priorityClass); ?>"><?= e(ucfirst((string) $announcement['priority'])); ?></span></div></td>
                                        <td><?= e((string) $announcement['target_summary']); ?></td>
                                        <td><div><?= e((string) (($announcement['starts_at'] ?? null) !== null ? $announcement['starts_at'] : 'Immediate')); ?></div><div class="small text-muted">Until <?= e((string) (($announcement['ends_at'] ?? null) !== null ? $announcement['ends_at'] : 'Open ended')); ?></div></td>
                                        <td><span class="badge <?= e($statusClass); ?>"><?= e(ucfirst((string) $announcement['status'])); ?></span></td>
                                        <td><span class="badge <?= (int) $announcement['is_read'] === 1 ? 'text-bg-success' : 'text-bg-light'; ?>"><?= e((string) ((int) $announcement['is_read'] === 1 ? 'Read' : 'Unread')); ?></span></td>
                                        <td><a href="<?= e(url('/announcements/' . $announcement['id'])); ?>" class="btn btn-sm btn-outline-primary">View</a></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
</div>
<?php if (($canManage ?? false) === true): ?>
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
<?php endif; ?>