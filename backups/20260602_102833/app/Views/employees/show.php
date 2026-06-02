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
$display = static function (mixed $value): string {
    $value = trim((string) $value);
    return $value !== '' ? $value : '-';
};
$employeeName = trim((string) (($employee['first_name'] ?? '') . ' ' . ($employee['middle_name'] ?? '') . ' ' . ($employee['last_name'] ?? '')));
$employeeName = $employeeName !== '' ? $employeeName : 'Employee';
$employeeInitials = strtoupper(substr((string) ($employee['first_name'] ?? 'E'), 0, 1) . substr((string) ($employee['last_name'] ?? ''), 0, 1));
$employeeInitials = trim($employeeInitials) !== '' ? $employeeInitials : 'E';
?>

<div class="row g-4 mb-4 profile-stat-row">
    <div class="col-md-4">
        <div class="profile-stat">
            <span class="profile-stat-icon"><i class="bi bi-folder2-open"></i></span>
            <div><div class="metric-label">Current Documents</div><h3 class="mb-0"><?= e((string) ($stats['documents'] ?? 0)); ?></h3></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="profile-stat">
            <span class="profile-stat-icon"><i class="bi bi-calendar-check"></i></span>
            <div><div class="metric-label">Pending Leave Requests</div><h3 class="mb-0"><?= e((string) ($stats['pending_leave'] ?? 0)); ?></h3></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="profile-stat">
            <span class="profile-stat-icon"><i class="bi bi-hourglass-split"></i></span>
            <div><div class="metric-label">Leave Balance</div><h3 class="mb-0"><?= e((string) ($stats['leave_balance'] ?? 0)); ?></h3></div>
        </div>
    </div>
</div>

<div class="row g-4 employee-profile-layout">
    <div class="col-xl-8">
        <div class="card content-card employee-profile-card mb-4">
            <div class="card-body p-0">
                <div class="employee-profile-header">
                    <div class="employee-profile-identity">
                        <?php if (!empty($employee['profile_photo']) && is_file(base_path((string) $employee['profile_photo']))): ?>
                            <img src="<?= e(url('/' . ltrim((string) $employee['profile_photo'], '/'))); ?>" alt="Photo" class="employee-profile-avatar">
                        <?php else: ?>
                            <div class="employee-profile-avatar employee-profile-avatar-fallback"><?= e($employeeInitials); ?></div>
                        <?php endif; ?>
                        <div>
                            <h4 class="employee-profile-name">
                                <?= e($employeeName); ?>
                                <?php if (can('employee.edit')): ?>
                                    <a href="<?= e(url('/employees/' . $employee['id'] . '/edit')); ?>" class="employee-profile-edit" title="Edit Employee"><i class="bi bi-pencil-square"></i></a>
                                <?php endif; ?>
                            </h4>
                            <div class="employee-profile-subtitle"><?= e($display($employee['employee_code'] ?? '')); ?> <span>&middot;</span> <?= e($display($employee['job_title_name'] ?? 'Unassigned')); ?></div>
                        </div>
                    </div>

                    <div class="employee-profile-actions">
                        <div class="employee-profile-tabs">
                            <?php if (can('documents.manage_all') || ((can('documents.view_self') || can('documents.upload_self')) && (int) (auth()->user()['employee_id'] ?? 0) === (int) ($employee['id'] ?? 0))): ?>
                                <a href="<?= e(url('/employees/' . $employee['id'] . '/documents/upload')); ?>" class="btn btn-outline-secondary"><i class="bi bi-folder2-open"></i> Documents</a>
                            <?php endif; ?>
                            <?php if (can('onboarding.manage')): ?><a href="<?= e(url('/onboarding/create/' . $employee['id'])); ?>" class="btn btn-outline-secondary"><i class="bi bi-person-plus"></i> Onboarding</a><?php endif; ?>
                            <?php if (can('offboarding.manage')): ?><a href="<?= e(url('/offboarding/create/' . $employee['id'])); ?>" class="btn btn-outline-secondary"><i class="bi bi-box-arrow-right"></i> Offboarding</a><?php endif; ?>
                            <a href="<?= e(url('/employees/' . $employee['id'] . '/history')); ?>" class="btn btn-outline-secondary"><i class="bi bi-clock-history"></i> History</a>
                        </div>

                        <div class="employee-record-actions">
                            <?php if (can('employee.edit')): ?><a href="<?= e(url('/employees/' . $employee['id'] . '/edit')); ?>" class="btn btn-outline-primary"><i class="bi bi-pencil-square"></i> Edit</a><?php endif; ?>
                            <?php if (has_role(['super_admin', 'hr_only'])): ?>
                                <?php $accessLabel = empty($employee['user_id']) ? 'Send Access' : 'Resend Access'; ?>
                                <?php $accessConfirm = empty($employee['user_id']) ? 'This will create a login account and email credentials to the employee. Continue?' : 'This will reset the password and email new credentials to the employee. Continue?'; ?>
                                <form method="post" action="<?= e(url('/employees/' . (int) $employee['id'] . '/send-access')); ?>" class="d-inline" onsubmit="return confirm('<?= e($accessConfirm); ?>');"><?= csrf_field(); ?><button type="submit" class="btn btn-outline-success"><i class="bi bi-envelope-paper"></i> <?= e($accessLabel); ?></button></form>
                            <?php endif; ?>
                            <?php if (can('employee.archive') && !$isArchived): ?><a href="<?= e(url('/employees/' . $employee['id'] . '/archive')); ?>" class="btn btn-outline-danger"><i class="bi bi-archive"></i> Archive</a><?php endif; ?>
                            <?php if (can('employee.delete')): ?>
                                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteEmployeeModal"><i class="bi bi-trash"></i> Delete</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="profile-detail-grid">
                    <div class="profile-detail-item"><span>Work Email</span><strong><?= e($display($employee['work_email'] ?? '')); ?></strong></div>
                    <div class="profile-detail-item"><span>Personal Email</span><strong><?= e($display($employee['personal_email'] ?? '')); ?></strong></div>
                    <div class="profile-detail-item"><span>Phone</span><strong><?= e($display($employee['phone'] ?? '')); ?></strong></div>
                    <div class="profile-detail-item"><span>Status</span><strong><span class="badge <?= $statusBadge; ?>"><?= e(ucwords(str_replace('_', ' ', $statusValue))); ?></span><?php if ($isArchived && ($employee['archived_at'] ?? null) !== null): ?><div class="small text-muted mt-1">Archived on <?= e((string) $employee['archived_at']); ?></div><?php endif; ?></strong></div>
                    <div class="profile-detail-item"><span>Company</span><strong><?= e($display($employee['company_name'] ?? '')); ?></strong></div>
                    <div class="profile-detail-item"><span>Branch</span><strong><?= e($display($employee['branch_name'] ?? '')); ?></strong></div>
                    <div class="profile-detail-item"><span>Department</span><strong><?= e($display($employee['department_name'] ?? '')); ?></strong></div>
                    <div class="profile-detail-item"><span>Team</span><strong><?= e($display($employee['team_name'] ?? '')); ?></strong></div>
                    <div class="profile-detail-item"><span>Designation</span><strong><?= e($display($employee['designation_name'] ?? '')); ?></strong></div>
                    <div class="profile-detail-item"><span>Manager</span><strong><?= e($display($employee['manager_name'] ?? '')); ?></strong></div>
                    <div class="profile-detail-item"><span>Joining Date</span><strong><?= e($display($employee['joining_date'] ?? '')); ?></strong></div>
                    <div class="profile-detail-item"><span>Employment Type</span><strong><?= e($display($employee['employment_type'] ?? '')); ?></strong></div>
                    <div class="profile-detail-item"><span>Birth Date</span><strong><?= e($display($employee['date_of_birth'] ?? '')); ?></strong></div>
                    <div class="profile-detail-item"><span>Nationality</span><strong><?= e($display($employee['nationality'] ?? '')); ?></strong></div>
                    <div class="profile-detail-item"><span>Second Nationality</span><strong><?= e($display($employee['second_nationality'] ?? '')); ?></strong></div>
                    <div class="profile-detail-item profile-detail-item-wide"><span>Notes</span><strong><?= nl2br(e($display($employee['notes'] ?? 'No notes added.'))); ?></strong></div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4 employee-side-column">
        <div class="card content-card profile-side-card mb-4">
            <div class="card-body p-4">
                <h5 class="profile-side-title"><i class="bi bi-person-heart"></i> Emergency Contacts</h5>
                <?php if (($contacts ?? []) === []): ?>
                    <div class="empty-state profile-empty-state p-3"><i class="bi bi-person-lines-fill"></i><span>No emergency contacts recorded yet.</span></div>
                <?php else: ?>
                    <div class="d-grid gap-3">
                        <?php foreach ($contacts as $contact): ?>
                            <div class="profile-contact-card">
                                <div class="d-flex justify-content-between gap-2"><strong><?= e((string) $contact['full_name']); ?></strong><?php if ((int) ($contact['is_primary'] ?? 0) === 1): ?><span class="badge text-bg-primary">Primary</span><?php endif; ?></div>
                                <div class="text-muted small mb-2"><?= e((string) $contact['relationship']); ?></div>
                                <div><?= e((string) $contact['phone']); ?></div>
                                <?php if (($contact['alternate_phone'] ?? '') !== ''): ?><div><?= e((string) $contact['alternate_phone']); ?></div><?php endif; ?>
                                <?php if (($contact['email'] ?? '') !== ''): ?><div><?= e((string) $contact['email']); ?></div><?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php
        $ins = $insurance ?? null;
        $hasIns = (int) ($ins['has_insurance'] ?? 0) === 1;
        $insExpiry = ($ins['expiry_date'] ?? '') !== '' ? $ins['expiry_date'] : null;
        $insExpired = $insExpiry !== null && strtotime($insExpiry) < time();
        $insExpiringSoon = $insExpiry !== null && !$insExpired && strtotime($insExpiry) < strtotime('+30 days');
        ?>
        <div class="card content-card profile-side-card">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="profile-side-title mb-0"><i class="bi bi-shield-plus"></i> Insurance</h5>
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
                    <div class="empty-state profile-empty-state p-3 mb-3"><i class="bi bi-shield-x"></i><span>No insurance record added yet.</span></div>
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
            </div>
        </div>
    </div>
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
                <p>You are about to <strong>permanently delete</strong> <strong><?= e($employeeName); ?></strong> (<?= e((string)($employee['employee_code'] ?? '')); ?>).</p>
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
