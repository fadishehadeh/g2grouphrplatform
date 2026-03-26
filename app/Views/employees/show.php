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
                <div>
                    <h4 class="mb-1"><?= e(trim(($employee['first_name'] ?? '') . ' ' . ($employee['middle_name'] ?? '') . ' ' . ($employee['last_name'] ?? ''))); ?><?php if (can('employee.edit')): ?> <a href="<?= e(url('/employees/' . $employee['id'] . '/edit')); ?>" class="text-primary ms-1" title="Edit Employee"><i class="bi bi-pencil-square" style="font-size:.75em"></i></a><?php endif; ?></h4>
                    <div class="text-muted"><?= e((string) ($employee['employee_code'] ?? '')); ?> · <?= e((string) ($employee['job_title_name'] ?? 'Unassigned')); ?></div>
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
                    <?php if (!empty($employee['user_id']) && has_role(['super_admin', 'hr_admin'])): ?>
                        <form method="post" action="<?= e(url('/admin/users/' . (int) $employee['user_id'] . '/welcome-email')); ?>" class="d-inline" onsubmit="return confirm('This will reset the password and email new credentials. Continue?');"><?= csrf_field(); ?><button type="submit" class="btn btn-outline-success"><i class="bi bi-envelope-paper"></i> Send Welcome Email</button></form>
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
        <div class="card content-card"><div class="card-body p-4">
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
    </div>
</div>