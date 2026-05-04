<?php declare(strict_types=1);
$s       = $submission;
$isPending  = $s['status'] === 'pending';
$isApproved = $s['status'] === 'approved';
?>
<div class="d-flex align-items-center gap-2 mb-4">
    <a href="<?= e(url('/employee-registration/review')); ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Back
    </a>
    <div>
        <h4 class="mb-0 fw-semibold"><?= e($s['first_name'] . ' ' . $s['last_name']); ?></h4>
        <span class="small text-muted">Submitted <?= e(date('d M Y, H:i', strtotime($s['submitted_at']))); ?></span>
    </div>
    <span class="ms-2 badge fs-6 <?= $s['status'] === 'pending' ? 'bg-warning text-dark' : ($isApproved ? 'bg-success' : 'bg-danger'); ?>">
        <?= ucfirst($s['status']); ?>
    </span>
</div>

<div class="row g-4">

    <!-- ── Left: Submitted data ──────────────────────────────────────────── -->
    <div class="col-lg-7">

        <!-- Personal Info -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-person me-2 text-primary"></i>Personal Information</div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-sm-4 text-muted">Full Name</dt>
                    <dd class="col-sm-8"><?= e(trim($s['first_name'] . ' ' . ($s['middle_name'] ?? '') . ' ' . $s['last_name'])); ?></dd>

                    <dt class="col-sm-4 text-muted">Personal Email</dt>
                    <dd class="col-sm-8"><?= e($s['personal_email'] ?? '—'); ?></dd>

                    <dt class="col-sm-4 text-muted">Phone</dt>
                    <dd class="col-sm-8"><?= e($s['phone'] ?? '—'); ?></dd>

                    <dt class="col-sm-4 text-muted">Alternate Phone</dt>
                    <dd class="col-sm-8"><?= e($s['alternate_phone'] ?? '—'); ?></dd>

                    <dt class="col-sm-4 text-muted">Date of Birth</dt>
                    <dd class="col-sm-8"><?= e($s['date_of_birth'] ?? '—'); ?></dd>

                    <dt class="col-sm-4 text-muted">Gender</dt>
                    <dd class="col-sm-8"><?= e(ucfirst(str_replace('_', ' ', $s['gender'] ?? '')) ?: '—'); ?></dd>

                    <dt class="col-sm-4 text-muted">Marital Status</dt>
                    <dd class="col-sm-8"><?= e(ucfirst($s['marital_status'] ?? '') ?: '—'); ?></dd>

                    <dt class="col-sm-4 text-muted">Nationality</dt>
                    <dd class="col-sm-8"><?= e($s['nationality'] ?? '—'); ?></dd>

                    <dt class="col-sm-4 text-muted">Second Nationality</dt>
                    <dd class="col-sm-8"><?= e($s['second_nationality'] ?? '—'); ?></dd>
                </dl>
            </div>
        </div>

        <!-- Address & Identity -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-house me-2 text-primary"></i>Address &amp; Identity</div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-sm-4 text-muted">Address</dt>
                    <dd class="col-sm-8">
                        <?php
                        $addr = array_filter([$s['address_line_1'] ?? '', $s['address_line_2'] ?? '', $s['city'] ?? '', $s['state'] ?? '', $s['country'] ?? '', $s['postal_code'] ?? '']);
                        echo e(implode(', ', $addr) ?: '—');
                        ?>
                    </dd>

                    <dt class="col-sm-4 text-muted">National ID</dt>
                    <dd class="col-sm-8">
                        <?php if (!empty($s['id_number'])): ?>
                        <span class="font-monospace"><?= e($s['id_number']); ?></span>
                        <?php else: ?>—<?php endif; ?>
                    </dd>

                    <dt class="col-sm-4 text-muted">Passport Number</dt>
                    <dd class="col-sm-8">
                        <?php if (!empty($s['passport_number'])): ?>
                        <span class="font-monospace"><?= e($s['passport_number']); ?></span>
                        <?php else: ?>—<?php endif; ?>
                    </dd>
                </dl>
            </div>
        </div>

        <!-- Emergency Contacts -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-telephone-plus me-2 text-primary"></i>Emergency Contacts</div>
            <div class="card-body p-0">
                <?php if (empty($contacts)): ?>
                <p class="text-muted small p-3 mb-0">No emergency contacts provided.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr><th>Name</th><th>Relationship</th><th>Phone</th><th>Email</th><th></th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($contacts as $c): ?>
                        <tr>
                            <td><?= e($c['full_name']); ?></td>
                            <td><?= e($c['relationship']); ?></td>
                            <td><?= e($c['phone']); ?></td>
                            <td><?= e($c['email'] ?? '—'); ?></td>
                            <td><?php if ($c['is_primary']): ?><span class="badge bg-primary">Primary</span><?php endif; ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Documents -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-file-earmark me-2 text-primary"></i>Uploaded Documents</div>
            <div class="card-body p-0">
                <?php if (empty($documents)): ?>
                <p class="text-muted small p-3 mb-0">No documents uploaded.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr><th>Type</th><th>Title</th><th>Doc #</th><th>Expiry</th><th>File</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($documents as $doc): ?>
                        <tr>
                            <td class="small"><?= e($doc['type_name'] ?? $doc['category_name'] ?? '—'); ?></td>
                            <td><?= e($doc['title']); ?></td>
                            <td class="small font-monospace"><?= e($doc['document_number'] ?? '—'); ?></td>
                            <td class="small"><?= $doc['expiry_date'] ? e(date('d M Y', strtotime($doc['expiry_date']))) : '—'; ?></td>
                            <td class="small text-muted"><?= e($doc['original_file_name']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- /col-left -->

    <!-- ── Right: HR action panel ────────────────────────────────────────── -->
    <div class="col-lg-5">

        <?php if ($isApproved): ?>
        <div class="card border-0 shadow-sm border-start border-success border-4 mb-4">
            <div class="card-body">
                <h6 class="fw-semibold text-success mb-1"><i class="bi bi-check-circle-fill me-1"></i>Approved</h6>
                <p class="small text-muted mb-2">Reviewed by <?= e($s['reviewer_name'] ?? 'HR'); ?> on <?= e(date('d M Y', strtotime($s['reviewed_at']))); ?>.</p>
                <a href="<?= e(url('/employees/' . $s['approved_employee_id'])); ?>" class="btn btn-sm btn-success">
                    <i class="bi bi-person-badge me-1"></i> View Employee Profile
                </a>
            </div>
        </div>

        <?php elseif ($s['status'] === 'rejected'): ?>
        <div class="card border-0 shadow-sm border-start border-danger border-4 mb-4">
            <div class="card-body">
                <h6 class="fw-semibold text-danger mb-1"><i class="bi bi-x-circle-fill me-1"></i>Rejected</h6>
                <p class="small text-muted mb-1">Reviewed by <?= e($s['reviewer_name'] ?? 'HR'); ?> on <?= e(date('d M Y', strtotime($s['reviewed_at']))); ?>.</p>
                <?php if (!empty($s['rejection_reason'])): ?>
                <p class="small mb-0"><strong>Reason:</strong> <?= e($s['rejection_reason']); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <?php else: /* pending */ ?>

        <!-- Approve form -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-person-check me-2 text-success"></i>Approve &amp; Create Employee</div>
            <div class="card-body">
                <p class="small text-muted mb-3">Fill in the employment details below, then click Approve. This will create the employee record immediately.</p>

                <form method="post" action="<?= e(url('/employee-registration/review/' . e($s['token']) . '/approve')); ?>">
                    <?= csrf_field(); ?>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Work Email <span class="text-danger">*</span></label>
                        <input type="email" name="work_email" class="form-control" required
                               placeholder="employee@company.com">
                        <div class="form-text">The official company email address assigned to this employee.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Company <span class="text-danger">*</span></label>
                        <select name="company_id" class="form-select" required>
                            <option value="">— Select company —</option>
                            <?php foreach ($formOptions['companies'] as $opt): ?>
                            <option value="<?= e((string) $opt['id']); ?>"><?= e($opt['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">Branch</label>
                            <select name="branch_id" class="form-select form-select-sm">
                                <option value="">None</option>
                                <?php foreach ($formOptions['branches'] as $opt): ?>
                                <option value="<?= e((string) $opt['id']); ?>"><?= e($opt['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Department</label>
                            <select name="department_id" class="form-select form-select-sm">
                                <option value="">None</option>
                                <?php foreach ($formOptions['departments'] as $opt): ?>
                                <option value="<?= e((string) $opt['id']); ?>"><?= e($opt['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Team</label>
                            <select name="team_id" class="form-select form-select-sm">
                                <option value="">None</option>
                                <?php foreach ($formOptions['teams'] as $opt): ?>
                                <option value="<?= e((string) $opt['id']); ?>"><?= e($opt['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Job Title</label>
                            <select name="job_title_id" class="form-select form-select-sm">
                                <option value="">None</option>
                                <?php foreach ($formOptions['job_titles'] as $opt): ?>
                                <option value="<?= e((string) $opt['id']); ?>"><?= e($opt['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Designation</label>
                            <select name="designation_id" class="form-select form-select-sm">
                                <option value="">None</option>
                                <?php foreach ($formOptions['designations'] as $opt): ?>
                                <option value="<?= e((string) $opt['id']); ?>"><?= e($opt['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Manager</label>
                            <select name="manager_employee_id" class="form-select form-select-sm">
                                <option value="">None</option>
                                <?php foreach ($formOptions['managers'] as $opt): ?>
                                <option value="<?= e((string) $opt['id']); ?>"><?= e($opt['full_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">Employment Type <span class="text-danger">*</span></label>
                            <select name="employment_type" class="form-select form-select-sm" required>
                                <option value="">— Select —</option>
                                <option value="full_time">Full Time</option>
                                <option value="part_time">Part Time</option>
                                <option value="contract">Contract</option>
                                <option value="intern">Intern</option>
                                <option value="temporary">Temporary</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Employee Status</label>
                            <select name="employee_status" class="form-select form-select-sm">
                                <option value="active" selected>Active</option>
                                <option value="draft">Draft</option>
                                <option value="on_leave">On Leave</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Joining Date <span class="text-danger">*</span></label>
                            <input type="date" name="joining_date" class="form-control form-control-sm" required>
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-success"
                                onclick="return confirm('Approve this submission and create the employee record?')">
                            <i class="bi bi-check-circle me-2"></i>Approve &amp; Create Employee
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Reject form -->
        <div class="card border-0 shadow-sm border-start border-danger border-3">
            <div class="card-header bg-white fw-semibold text-danger"><i class="bi bi-x-circle me-2"></i>Reject Submission</div>
            <div class="card-body">
                <form method="post" action="<?= e(url('/employee-registration/review/' . e($s['token']) . '/reject')); ?>">
                    <?= csrf_field(); ?>
                    <div class="mb-3">
                        <label class="form-label small">Reason (optional)</label>
                        <textarea name="rejection_reason" class="form-control form-control-sm" rows="3"
                                  placeholder="Explain why this submission is being rejected…"></textarea>
                    </div>
                    <button type="submit" class="btn btn-outline-danger btn-sm w-100"
                            onclick="return confirm('Reject this submission?')">
                        <i class="bi bi-x me-1"></i>Reject
                    </button>
                </form>
            </div>
        </div>

        <?php endif; ?>
    </div><!-- /col-right -->

</div>
