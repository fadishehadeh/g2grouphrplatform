<?php declare(strict_types=1); ?>
<?php
$statusValue = (string) ($employee['employee_status'] ?? 'draft');
$statusBadge = match ($statusValue) {
    'active' => 'text-bg-success',
    'on_leave' => 'text-bg-warning',
    'terminated', 'resigned' => 'text-bg-danger',
    'archived' => 'text-bg-dark',
    default => 'text-bg-secondary',
};
$isArchived = (($employee['archived_at'] ?? null) !== null) || $statusValue === 'archived';
?>
<div class="row g-4 mb-4">
    <div class="col-md-4"><div class="profile-stat"><div class="metric-label">Current Documents</div><h3 class="mb-0"><?= e((string) ($stats['documents'] ?? 0)); ?></h3></div></div>
    <div class="col-md-4"><div class="profile-stat"><div class="metric-label">Pending Leave Requests</div><h3 class="mb-0"><?= e((string) ($stats['pending_leave'] ?? 0)); ?></h3></div></div>
    <div class="col-md-4"><div class="profile-stat"><div class="metric-label">Leave Balance</div><h3 class="mb-0"><?= e((string) ($stats['leave_balance'] ?? 0)); ?></h3></div></div>
</div>

<div class="row g-4">
    <div class="col-xl-8">
        <div class="card content-card mb-4"><div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
                <div class="d-flex align-items-center gap-3">
                    <?php if (!empty($employee['profile_photo']) && is_file(base_path((string) $employee['profile_photo']))): ?>
                        <img src="<?= e(url('/' . ltrim((string) $employee['profile_photo'], '/'))); ?>" alt="Photo"
                             style="width:72px;height:72px;object-fit:cover;border-radius:50%;border:3px solid #dee2e6;flex-shrink:0;">
                    <?php else: ?>
                        <div style="width:72px;height:72px;border-radius:50%;background:#e9ecef;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="bi bi-person fs-3 text-muted"></i>
                        </div>
                    <?php endif; ?>
                    <div>
                        <h4 class="mb-1"><?= e(trim(($employee['first_name'] ?? '') . ' ' . ($employee['middle_name'] ?? '') . ' ' . ($employee['last_name'] ?? ''))); ?><?php if (can('employee.edit')): ?> <a href="<?= e(url('/employees/' . $employee['id'] . '/edit')); ?>" class="text-primary ms-1" title="Edit Employee"><i class="bi bi-pencil-square" style="font-size:.75em"></i></a><?php endif; ?></h4>
                        <div class="text-muted"><?= e((string) ($employee['employee_code'] ?? '')); ?> · <?= e((string) ($employee['job_title_name'] ?? 'Unassigned')); ?></div>
                    </div>
                </div>
                <div class="d-flex gap-2 flex-wrap justify-content-end">
                    <?php if (can('documents.manage_all') || ((can('documents.view_self') || can('documents.upload_self')) && (int) (auth()->user()['employee_id'] ?? 0) === (int) ($employee['id'] ?? 0))): ?>
                        <a href="<?= e(url('/employees/' . $employee['id'] . '/documents/upload')); ?>" class="btn btn-outline-secondary">Documents</a>
                    <?php endif; ?>
                    <?php if (can('onboarding.manage')): ?><a href="<?= e(url('/onboarding/create/' . $employee['id'])); ?>" class="btn btn-outline-secondary">Onboarding</a><?php endif; ?>
                    <?php if (can('offboarding.manage')): ?><a href="<?= e(url('/offboarding/create/' . $employee['id'])); ?>" class="btn btn-outline-secondary">Offboarding</a><?php endif; ?>
                    <a href="<?= e(url('/employees/' . $employee['id'] . '/history')); ?>" class="btn btn-outline-secondary">History</a>
                    <?php if (can('employee.edit')): ?><a href="<?= e(url('/employees/' . $employee['id'] . '/edit')); ?>" class="btn btn-outline-primary">Edit</a><?php endif; ?>
                    <?php if (can('employee.archive') && !$isArchived): ?><a href="<?= e(url('/employees/' . $employee['id'] . '/archive')); ?>" class="btn btn-outline-danger">Archive</a><?php endif; ?>
                    <?php if (can('employee.delete')): ?>
                        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteEmployeeModal"><i class="bi bi-trash"></i> Delete</button>
                    <?php endif; ?>
                    <?php if (has_role(['super_admin', 'hr_only'])): ?>
                        <?php $accessLabel = empty($employee['user_id']) ? 'Send Access' : 'Resend Access'; ?>
                        <?php $accessConfirm = empty($employee['user_id']) ? 'This will create a login account and email credentials to the employee. Continue?' : 'This will reset the password and email new credentials to the employee. Continue?'; ?>
                        <form method="post" action="<?= e(url('/employees/' . (int) $employee['id'] . '/send-access')); ?>" class="d-inline" onsubmit="return confirm('<?= e($accessConfirm); ?>');"><?= csrf_field(); ?><button type="submit" class="btn btn-outline-success"><i class="bi bi-envelope-paper"></i> <?= e($accessLabel); ?></button></form>
                    <?php endif; ?>
                </div>
            </div>
            <div class="row g-3">
                <div class="col-md-6"><strong>Work Email:</strong><br><?= e((string) ($employee['work_email'] ?? '—')); ?></div>
                <div class="col-md-6"><strong>Personal Email:</strong><br><?= e((string) (($employee['personal_email'] ?? '') !== '' ? $employee['personal_email'] : '—')); ?></div>
                <div class="col-md-6"><strong>Phone:</strong><br><?= e((string) (($employee['phone'] ?? '') !== '' ? $employee['phone'] : '—')); ?></div>
                <div class="col-md-6"><strong>Status:</strong><br><span class="badge <?= $statusBadge; ?>"><?= e(ucwords(str_replace('_', ' ', $statusValue))); ?></span><?php if ($isArchived && ($employee['archived_at'] ?? null) !== null): ?><div class="small text-muted mt-1">Archived on <?= e((string) $employee['archived_at']); ?></div><?php endif; ?></div>
                <div class="col-md-6"><strong>Company:</strong><br><?= e((string) ($employee['company_name'] ?? '—')); ?></div>
                <div class="col-md-6"><strong>Branch:</strong><br><?= e((string) (($employee['branch_name'] ?? '') !== '' ? $employee['branch_name'] : '—')); ?></div>
                <div class="col-md-6"><strong>Department:</strong><br><?= e((string) (($employee['department_name'] ?? '') !== '' ? $employee['department_name'] : '—')); ?></div>
                <div class="col-md-6"><strong>Team:</strong><br><?= e((string) (($employee['team_name'] ?? '') !== '' ? $employee['team_name'] : '—')); ?></div>
                <div class="col-md-6"><strong>Designation:</strong><br><?= e((string) (($employee['designation_name'] ?? '') !== '' ? $employee['designation_name'] : '—')); ?></div>
                <div class="col-md-6"><strong>Manager:</strong><br><?= e((string) (($employee['manager_name'] ?? '') !== '' ? $employee['manager_name'] : '—')); ?></div>
                <div class="col-md-6"><strong>Joining Date:</strong><br><?= e((string) (($employee['joining_date'] ?? '') !== '' ? $employee['joining_date'] : '—')); ?></div>
                <div class="col-md-6"><strong>Employment Type:</strong><br><?= e((string) ($employee['employment_type'] ?? '—')); ?></div>
                <div class="col-md-6"><strong>Birth Date:</strong><br><?= e((string) (($employee['date_of_birth'] ?? '') !== '' ? $employee['date_of_birth'] : '—')); ?></div>
                <div class="col-md-6"><strong>Nationality:</strong><br><?= e((string) (($employee['nationality'] ?? '') !== '' ? $employee['nationality'] : '—')); ?></div>
                <div class="col-md-6"><strong>Second Nationality:</strong><br><?= e((string) (($employee['second_nationality'] ?? '') !== '' ? $employee['second_nationality'] : '—')); ?></div>
                <div class="col-12"><strong>Notes:</strong><br><?= nl2br(e((string) (($employee['notes'] ?? '') !== '' ? $employee['notes'] : 'No notes added.'))); ?></div>
            </div>
        </div></div>
    </div>
    <div class="col-xl-4">
        <div class="card content-card mb-4"><div class="card-body p-4">
            <h5 class="mb-3">Emergency Contacts</h5>
            <?php if (($contacts ?? []) === []): ?>
                <div class="empty-state p-3">No emergency contacts recorded yet.</div>
            <?php else: ?>
                <div class="d-grid gap-3">
                    <?php foreach ($contacts as $contact): ?>
                        <div class="border rounded-4 p-3">
                            <div class="d-flex justify-content-between gap-2"><strong><?= e((string) $contact['full_name']); ?></strong><?php if ((int) ($contact['is_primary'] ?? 0) === 1): ?><span class="badge text-bg-primary">Primary</span><?php endif; ?></div>
                            <div class="text-muted small mb-2"><?= e((string) $contact['relationship']); ?></div>
                            <div><?= e((string) $contact['phone']); ?></div>
                            <?php if (($contact['alternate_phone'] ?? '') !== ''): ?><div><?= e((string) $contact['alternate_phone']); ?></div><?php endif; ?>
                            <?php if (($contact['email'] ?? '') !== ''): ?><div><?= e((string) $contact['email']); ?></div><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div></div>

        <!-- Insurance Card -->
        <?php
        $ins = $insurance ?? null;
        $hasIns = (int) ($ins['has_insurance'] ?? 0) === 1;
        $insExpiry = ($ins['expiry_date'] ?? '') !== '' ? $ins['expiry_date'] : null;
        $insExpired = $insExpiry !== null && strtotime($insExpiry) < time();
        $insExpiringSoon = $insExpiry !== null && !$insExpired && strtotime($insExpiry) < strtotime('+30 days');
        ?>
        <div class="card content-card"><div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0"><i class="bi bi-shield-plus"></i> Insurance</h5>
                <?php if ($hasIns): ?>
                    <span class="badge <?= $insExpired ? 'text-bg-danger' : ($insExpiringSoon ? 'text-bg-warning' : 'text-bg-success'); ?>">
                        <?= $insExpired ? 'Expired' : ($insExpiringSoon ? 'Expiring Soon' : 'Active'); ?>
                    </span>
                <?php else: ?>
                    <span class="badge text-bg-secondary">No Insurance</span>
                <?php endif; ?>
            </div>

            <?php if ($hasIns && $ins !== null): ?>
                <div class="row g-2 small mb-3">
                    <?php if (!empty($ins['provider_name'])): ?><div class="col-12"><span class="text-muted">Provider:</span> <strong><?= e((string) $ins['provider_name']); ?></strong></div><?php endif; ?>
                    <?php if (!empty($ins['policy_number'])): ?><div class="col-6"><span class="text-muted">Policy #:</span><br><?= e((string) $ins['policy_number']); ?></div><?php endif; ?>
                    <?php if (!empty($ins['card_number'])): ?><div class="col-6"><span class="text-muted">Card #:</span><br><?= e((string) $ins['card_number']); ?></div><?php endif; ?>
                    <?php if (!empty($ins['member_id'])): ?><div class="col-6"><span class="text-muted">Member ID:</span><br><?= e((string) $ins['member_id']); ?></div><?php endif; ?>
                    <?php if (!empty($ins['coverage_type'])): ?><div class="col-6"><span class="text-muted">Coverage:</span><br><?= e((string) $ins['coverage_type']); ?></div><?php endif; ?>
                    <?php if (!empty($ins['start_date'])): ?><div class="col-6"><span class="text-muted">Start:</span><br><?= e((string) $ins['start_date']); ?></div><?php endif; ?>
                    <?php if ($insExpiry !== null): ?><div class="col-6"><span class="text-muted">Expiry:</span><br><span class="<?= $insExpired ? 'text-danger fw-semibold' : ($insExpiringSoon ? 'text-warning fw-semibold' : ''); ?>"><?= e($insExpiry); ?></span></div><?php endif; ?>
                    <?php if (!empty($ins['notes'])): ?><div class="col-12 mt-1"><span class="text-muted">Notes:</span><br><?= nl2br(e((string) $ins['notes'])); ?></div><?php endif; ?>
                </div>
            <?php elseif (!$hasIns && $ins !== null): ?>
                <p class="text-muted small mb-3">This employee is not enrolled in any insurance plan.</p>
            <?php else: ?>
                <p class="text-muted small mb-3">No insurance record added yet.</p>
            <?php endif; ?>

            <?php if (can('employee.edit')): ?>
            <hr>
            <form method="post" action="<?= e(url('/employees/' . (int) ($employee['id'] ?? 0) . '/insurance')); ?>">
                <?= csrf_field(); ?>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="has_insurance" id="has_insurance" value="1" onchange="toggleInsFields(this)" <?= $hasIns ? 'checked' : ''; ?>>
                    <label class="form-check-label small" for="has_insurance">Employee has insurance</label>
                </div>
                <div id="ins_fields" <?= !$hasIns ? 'style="display:none"' : ''; ?>>
                    <div class="mb-2"><input type="text" name="provider_name" class="form-control form-control-sm" placeholder="Provider Name" value="<?= e((string) ($ins['provider_name'] ?? '')); ?>"></div>
                    <div class="row g-2 mb-2">
                        <div class="col-6"><input type="text" name="policy_number" class="form-control form-control-sm" placeholder="Policy #" value="<?= e((string) ($ins['policy_number'] ?? '')); ?>"></div>
                        <div class="col-6"><input type="text" name="card_number" class="form-control form-control-sm" placeholder="Card #" value="<?= e((string) ($ins['card_number'] ?? '')); ?>"></div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6"><input type="text" name="member_id" class="form-control form-control-sm" placeholder="Member ID" value="<?= e((string) ($ins['member_id'] ?? '')); ?>"></div>
                        <div class="col-6"><input type="text" name="coverage_type" class="form-control form-control-sm" placeholder="Coverage Type" value="<?= e((string) ($ins['coverage_type'] ?? '')); ?>"></div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6"><label class="form-label small text-muted mb-1">Start Date</label><input type="date" name="start_date" class="form-control form-control-sm" value="<?= e((string) ($ins['start_date'] ?? '')); ?>"></div>
                        <div class="col-6"><label class="form-label small text-muted mb-1">Expiry Date</label><input type="date" name="expiry_date" class="form-control form-control-sm" value="<?= e((string) ($ins['expiry_date'] ?? '')); ?>"></div>
                    </div>
                    <div class="mb-3"><textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="Notes"><?= e((string) ($ins['notes'] ?? '')); ?></textarea></div>
                </div>
                <div class="d-grid"><button type="submit" class="btn btn-primary btn-sm">Save Insurance</button></div>
            </form>
            <script>function toggleInsFields(cb){document.getElementById('ins_fields').style.display=cb.checked?'':'none';}</script>
            <?php endif; ?>
        </div></div>
    </div>

<?php if (can('employee.delete')): ?>
<div class="modal fade" id="deleteEmployeeModal" tabindex="-1" aria-labelledby="deleteEmployeeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteEmployeeModalLabel"><i class="bi bi-exclamation-triangle-fill me-2"></i>Permanently Delete Employee</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>You are about to <strong>permanently delete</strong> <strong><?= e(trim((string)(($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? '')))); ?></strong> (<?= e((string)($employee['employee_code'] ?? '')); ?>).</p>
                <p class="text-danger mb-0"><strong>This action cannot be undone.</strong> All associated records (documents, leave history, onboarding, etc.) will also be deleted.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="post" action="<?= e(url('/employees/' . (int)$employee['id'] . '/delete')); ?>" class="d-inline">
                    <?= csrf_field(); ?>
                    <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1"></i>Delete Permanently</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
</div>