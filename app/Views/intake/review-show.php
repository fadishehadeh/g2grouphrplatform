<?php declare(strict_types=1);
$s          = $submission;
$isPending  = $s['status'] === 'pending';
$isApproved = $s['status'] === 'approved';
$isRejected = $s['status'] === 'rejected';

$statusClass = $isPending ? 'warning' : ($isApproved ? 'success' : 'danger');
$statusIcon  = $isPending ? 'clock'   : ($isApproved ? 'check-circle-fill' : 'x-circle-fill');
?>

<!-- ── Page header ─────────────────────────────────────────────────────────── -->
<div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
    <a href="<?= e(url('/employee-registration/review')); ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Back
    </a>

    <div class="flex-grow-1">
        <h4 class="mb-0 fw-bold"><?= e(trim($s['first_name'] . ' ' . ($s['middle_name'] ? $s['middle_name'] . ' ' : '') . $s['last_name'])); ?></h4>
        <span class="text-muted small">
            <i class="bi bi-clock me-1"></i>Submitted <?= e(date('d M Y, H:i', strtotime($s['submitted_at']))); ?>
        </span>
    </div>

    <span class="badge rounded-pill bg-<?= $statusClass; ?> fs-6 px-3 py-2 <?= $isPending ? 'text-dark' : ''; ?>">
        <i class="bi bi-<?= $statusIcon; ?> me-1"></i><?= ucfirst($s['status']); ?>
    </span>

    <?php if ($isPending): ?>
    <div class="d-flex gap-2">
        <a href="#approvePanel" class="btn btn-success btn-sm">
            <i class="bi bi-check-circle me-1"></i> Approve
        </a>
        <a href="#rejectPanel" class="btn btn-outline-danger btn-sm">
            <i class="bi bi-x-circle me-1"></i> Reject
        </a>
    </div>
    <?php elseif ($isApproved && !empty($s['approved_employee_id'])): ?>
    <a href="<?= e(url('/employees/' . $s['approved_employee_id'])); ?>" class="btn btn-sm btn-success">
        <i class="bi bi-person-badge me-1"></i> View Employee
    </a>
    <?php endif; ?>
</div>

<!-- ── Status banner for processed submissions ───────────────────────────── -->
<?php if ($isApproved): ?>
<div class="alert alert-success d-flex align-items-center gap-3 mb-4">
    <i class="bi bi-check-circle-fill fs-4"></i>
    <div>
        <strong>Approved</strong> — reviewed by <?= e($s['reviewer_name'] ?? 'HR'); ?>
        on <?= e(date('d M Y', strtotime($s['reviewed_at']))); ?>.
        <?php if (!empty($s['approved_employee_id'])): ?>
        <a href="<?= e(url('/employees/' . $s['approved_employee_id'])); ?>" class="ms-2 fw-semibold">View employee profile →</a>
        <?php endif; ?>
    </div>
</div>
<?php elseif ($isRejected): ?>
<div class="alert alert-danger d-flex align-items-center gap-3 mb-4">
    <i class="bi bi-x-circle-fill fs-4"></i>
    <div>
        <strong>Rejected</strong> — reviewed by <?= e($s['reviewer_name'] ?? 'HR'); ?>
        on <?= e(date('d M Y', strtotime($s['reviewed_at']))); ?>.
        <?php if (!empty($s['rejection_reason'])): ?>
        <br><span class="mt-1 d-inline-block"><strong>Reason:</strong> <?= e($s['rejection_reason']); ?></span>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="row g-4">

<!-- ══════════════════════════════════════════════════════════════════════════
     LEFT COLUMN — Submitted data
══════════════════════════════════════════════════════════════════════════ -->
<div class="col-xl-7 col-lg-6">

    <!-- Personal Info -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-primary bg-opacity-10 border-0 py-3">
            <h6 class="mb-0 fw-semibold text-primary">
                <i class="bi bi-person-fill me-2"></i>Personal Information
            </h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-sm-6">
                    <div class="text-muted small mb-1">First Name</div>
                    <div class="fw-semibold"><?= e($s['first_name']); ?></div>
                </div>
                <?php if (!empty($s['middle_name'])): ?>
                <div class="col-sm-6">
                    <div class="text-muted small mb-1">Middle Name</div>
                    <div class="fw-semibold"><?= e($s['middle_name']); ?></div>
                </div>
                <?php endif; ?>
                <div class="col-sm-6">
                    <div class="text-muted small mb-1">Last Name</div>
                    <div class="fw-semibold"><?= e($s['last_name']); ?></div>
                </div>
                <div class="col-sm-6">
                    <div class="text-muted small mb-1">Personal Email</div>
                    <div><?= e($s['personal_email'] ?? '—'); ?></div>
                </div>
                <div class="col-sm-6">
                    <div class="text-muted small mb-1">Phone</div>
                    <div><?= e($s['phone'] ?? '—'); ?></div>
                </div>
                <?php if (!empty($s['alternate_phone'])): ?>
                <div class="col-sm-6">
                    <div class="text-muted small mb-1">Alternate Phone</div>
                    <div><?= e($s['alternate_phone']); ?></div>
                </div>
                <?php endif; ?>
                <div class="col-sm-6">
                    <div class="text-muted small mb-1">Date of Birth</div>
                    <div><?= $s['date_of_birth'] ? e(date('d M Y', strtotime($s['date_of_birth']))) : '—'; ?></div>
                </div>
                <div class="col-sm-6">
                    <div class="text-muted small mb-1">Gender</div>
                    <div><?= e(ucfirst(str_replace('_', ' ', $s['gender'] ?? '')) ?: '—'); ?></div>
                </div>
                <div class="col-sm-6">
                    <div class="text-muted small mb-1">Marital Status</div>
                    <div><?= e(ucfirst(str_replace('_', ' ', $s['marital_status'] ?? '')) ?: '—'); ?></div>
                </div>
                <div class="col-sm-6">
                    <div class="text-muted small mb-1">Nationality</div>
                    <div><?= e($s['nationality'] ?? '—'); ?></div>
                </div>
                <?php if (!empty($s['second_nationality'])): ?>
                <div class="col-sm-6">
                    <div class="text-muted small mb-1">Second Nationality</div>
                    <div><?= e($s['second_nationality']); ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Address & Identity -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-info bg-opacity-10 border-0 py-3">
            <h6 class="mb-0 fw-semibold text-info">
                <i class="bi bi-geo-alt-fill me-2"></i>Address &amp; Identity
            </h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12">
                    <div class="text-muted small mb-1">Address</div>
                    <?php
                    $addr = array_filter([
                        $s['address_line_1'] ?? '', $s['address_line_2'] ?? '',
                        $s['city'] ?? '', $s['state'] ?? '',
                        $s['country'] ?? '', $s['postal_code'] ?? ''
                    ]);
                    ?>
                    <div><?= e(implode(', ', $addr) ?: '—'); ?></div>
                </div>
                <div class="col-sm-6">
                    <div class="text-muted small mb-1">National ID Number</div>
                    <div class="font-monospace fw-semibold"><?= e($s['id_number'] ?? '—'); ?></div>
                </div>
                <div class="col-sm-6">
                    <div class="text-muted small mb-1">Passport Number</div>
                    <div class="font-monospace fw-semibold"><?= e($s['passport_number'] ?? '—'); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Identifications (Multiple IDs) -->
    <?php if (!empty($identifications)): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-primary bg-opacity-10 border-0 py-3">
            <h6 class="mb-0 fw-semibold text-primary">
                <i class="bi bi-person-badge-fill me-2"></i>Identifications
            </h6>
        </div>
        <div class="card-body">
            <div class="list-group list-group-flush">
            <?php foreach ($identifications as $id): ?>
                <div class="list-group-item px-0 py-3 first:pt-0 border-0">
                    <div class="row g-2">
                        <div class="col-sm-6">
                            <div class="text-muted small mb-1"><?= e($id['type_name'] ?? 'ID Type'); ?></div>
                            <div class="font-monospace fw-semibold"><?= e(decrypt_field($id['id_number']) ?? '—'); ?></div>
                            <?php if ($id['is_primary']): ?>
                            <span class="badge bg-info text-dark small mt-1">Primary ID</span>
                            <?php endif; ?>
                        </div>
                        <div class="col-sm-6">
                            <div class="d-flex gap-2">
                                <?php if (!empty($id['issue_date'])): ?>
                                <div>
                                    <div class="text-muted small mb-1">Issue Date</div>
                                    <div class="small"><?= e(date('d M Y', strtotime($id['issue_date']))); ?></div>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($id['expiry_date'])): ?>
                                <div>
                                    <div class="text-muted small mb-1">Expiry Date</div>
                                    <div class="small"><?= e(date('d M Y', strtotime($id['expiry_date']))); ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Emergency Contacts -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-warning bg-opacity-10 border-0 py-3">
            <h6 class="mb-0 fw-semibold text-warning-emphasis">
                <i class="bi bi-telephone-plus-fill me-2"></i>Emergency Contacts
            </h6>
        </div>
        <?php if (empty($contacts)): ?>
        <div class="card-body text-muted small">No emergency contacts provided.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Relationship</th>
                        <th>Phone</th>
                        <th>Email</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($contacts as $c): ?>
                <tr>
                    <td>
                        <?= e($c['full_name']); ?>
                        <?php if ($c['is_primary']): ?>
                        <span class="badge bg-primary ms-1">Primary</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($c['relationship'] ?? '—'); ?></td>
                    <td><?= e($c['phone']); ?></td>
                    <td><?= e($c['email'] ?? '—'); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Documents -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-success bg-opacity-10 border-0 py-3">
            <h6 class="mb-0 fw-semibold text-success">
                <i class="bi bi-file-earmark-fill me-2"></i>Uploaded Documents
            </h6>
        </div>
        <?php if (empty($documents)): ?>
        <div class="card-body text-muted small">No documents uploaded.</div>
        <?php else: ?>
        <div class="list-group list-group-flush">
        <?php foreach ($documents as $doc):
            $ext = strtolower($doc['file_extension'] ?? '');
            $iconClass = in_array($ext, ['jpg','jpeg','png']) ? 'bi-file-image text-primary'
                       : ($ext === 'pdf' ? 'bi-file-earmark-pdf text-danger' : 'bi-file-earmark-text text-secondary');
            $previewable = in_array($ext, ['pdf', 'jpg', 'jpeg', 'png']);
            $downloadUrl = "/employee-registration/review/" . e($token) . "/document/" . $doc['id'] . "/download";
            $previewUrl = "/employee-registration/review/" . e($token) . "/document/" . $doc['id'] . "/preview";
        ?>
        <div class="list-group-item px-4 py-3">
            <div class="d-flex align-items-start gap-3">
                <i class="bi <?= $iconClass; ?> fs-3 mt-1"></i>
                <div class="flex-grow-1 min-w-0">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <div class="fw-semibold"><?= e($doc['title']); ?></div>
                        <div class="ms-auto d-flex gap-2 flex-wrap justify-content-end">
                            <?php if ($previewable): ?>
                            <a href="<?= $previewUrl; ?>" class="btn btn-sm btn-outline-primary" target="_blank" title="Preview document">
                                <i class="bi bi-eye me-1"></i>Preview
                            </a>
                            <?php endif; ?>
                            <a href="<?= $downloadUrl; ?>" class="btn btn-sm btn-outline-secondary" title="Download document">
                                <i class="bi bi-download me-1"></i>Download
                            </a>
                        </div>
                    </div>
                    <div class="text-muted small">
                        <?= e($doc['type_name'] ?? $doc['category_name'] ?? 'Document'); ?>
                        <?php if (!empty($doc['document_number'])): ?>
                        &nbsp;·&nbsp; <span class="font-monospace"><?= e($doc['document_number']); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex gap-3 mt-1 small text-muted">
                        <?php if (!empty($doc['issue_date'])): ?>
                        <span><i class="bi bi-calendar-check me-1"></i>Issued: <?= e(date('d M Y', strtotime($doc['issue_date']))); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($doc['expiry_date'])): ?>
                        <span><i class="bi bi-calendar-x me-1"></i>Expires: <?= e(date('d M Y', strtotime($doc['expiry_date']))); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="text-muted small mt-1">
                        <i class="bi bi-paperclip me-1"></i><?= e($doc['original_file_name']); ?>
                        <?php if (!empty($doc['file_size'])): ?>
                        <span class="ms-2">(<?= e(number_format($doc['file_size'] / 1024, 1)); ?> KB)</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

</div><!-- /col-left -->

<!-- ══════════════════════════════════════════════════════════════════════════
     RIGHT COLUMN — HR Actions
══════════════════════════════════════════════════════════════════════════ -->
<div class="col-xl-5 col-lg-6">
<div class="sticky-top" style="top:80px">

    <?php if ($isApproved): ?>
    <!-- Already approved -->
    <div class="card border-0 shadow-sm border-start border-success border-4">
        <div class="card-body py-4 text-center">
            <div class="mb-3" style="width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,#d1fae5,#a7f3d0);display:inline-flex;align-items:center;justify-content:center">
                <i class="bi bi-check-lg text-success fs-3"></i>
            </div>
            <h6 class="fw-bold text-success mb-1">Submission Approved</h6>
            <p class="text-muted small mb-3">
                Employee record created by <?= e($s['reviewer_name'] ?? 'HR'); ?>
                on <?= e(date('d M Y', strtotime($s['reviewed_at']))); ?>.
            </p>
            <?php if (!empty($s['approved_employee_id'])): ?>
            <a href="<?= e(url('/employees/' . $s['approved_employee_id'])); ?>" class="btn btn-success">
                <i class="bi bi-person-badge me-2"></i>View Employee Profile
            </a>
            <?php endif; ?>
        </div>
    </div>

    <?php elseif ($isRejected): ?>
    <!-- Already rejected -->
    <div class="card border-0 shadow-sm border-start border-danger border-4">
        <div class="card-body py-4 text-center">
            <div class="mb-3" style="width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,#fee2e2,#fca5a5);display:inline-flex;align-items:center;justify-content:center">
                <i class="bi bi-x-lg text-danger fs-3"></i>
            </div>
            <h6 class="fw-bold text-danger mb-1">Submission Rejected</h6>
            <p class="text-muted small mb-0">
                Reviewed by <?= e($s['reviewer_name'] ?? 'HR'); ?>
                on <?= e(date('d M Y', strtotime($s['reviewed_at']))); ?>.
            </p>
            <?php if (!empty($s['rejection_reason'])): ?>
            <div class="alert alert-danger text-start mt-3 mb-0 small">
                <strong>Reason:</strong> <?= e($s['rejection_reason']); ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php else: /* pending */ ?>

    <!-- ── Approve Card ─────────────────────────────────────────────────── -->
    <div class="card border-0 shadow-sm mb-4" id="approvePanel">
        <div class="card-header border-0 py-3" style="background:linear-gradient(135deg,#ecfdf5,#d1fae5)">
            <h6 class="mb-0 fw-bold text-success">
                <i class="bi bi-person-check-fill me-2"></i>Approve &amp; Create Employee
            </h6>
            <p class="mb-0 small text-muted mt-1">Fill in employment details and click Approve.</p>
        </div>
        <div class="card-body pt-3">
            <form method="post" action="<?= e(url('/employee-registration/review/' . $s['token'] . '/approve')); ?>">
                <?= csrf_field(); ?>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Work Email <span class="text-danger">*</span></label>
                    <input type="email" name="work_email" class="form-control form-control-sm" required
                           placeholder="employee@company.com">
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Company <span class="text-danger">*</span></label>
                    <select name="company_id" class="form-select form-select-sm" required>
                        <option value="">— Select —</option>
                        <?php foreach ($formOptions['companies'] as $id => $name): ?>
                        <option value="<?= e((string) $id); ?>"
                            <?= (string)($s['company_id'] ?? '') === (string)$id ? 'selected' : ''; ?>>
                            <?= e($name); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Employment Type <span class="text-danger">*</span></label>
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
                        <label class="form-label small fw-semibold">Joining Date <span class="text-danger">*</span></label>
                        <input type="date" name="joining_date" class="form-control form-control-sm" required>
                    </div>
                </div>

                <hr class="my-3">
                <p class="text-muted small mb-2 fw-semibold">Optional Details</p>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small">Branch</label>
                        <select name="branch_id" class="form-select form-select-sm">
                            <option value="">None</option>
                            <?php foreach ($formOptions['branches'] as $id => $name): ?>
                            <option value="<?= e((string) $id); ?>"><?= e($name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small">Department</label>
                        <select name="department_id" class="form-select form-select-sm">
                            <option value="">None</option>
                            <?php foreach ($formOptions['departments'] as $id => $name): ?>
                            <option value="<?= e((string) $id); ?>"><?= e($name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small">Team</label>
                        <select name="team_id" class="form-select form-select-sm">
                            <option value="">None</option>
                            <?php foreach ($formOptions['teams'] as $id => $name): ?>
                            <option value="<?= e((string) $id); ?>"><?= e($name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small">Job Title</label>
                        <select name="job_title_id" class="form-select form-select-sm">
                            <option value="">None</option>
                            <?php foreach ($formOptions['job_titles'] as $id => $name): ?>
                            <option value="<?= e((string) $id); ?>"><?= e($name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small">Designation</label>
                        <select name="designation_id" class="form-select form-select-sm">
                            <option value="">None</option>
                            <?php foreach ($formOptions['designations'] as $id => $name): ?>
                            <option value="<?= e((string) $id); ?>"><?= e($name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small">Manager</label>
                        <select name="manager_employee_id" class="form-select form-select-sm">
                            <option value="">None</option>
                            <?php foreach ($formOptions['managers'] as $id => $name): ?>
                            <option value="<?= e((string) $id); ?>"><?= e($name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label small">Employee Status</label>
                        <select name="employee_status" class="form-select form-select-sm">
                            <option value="active" selected>Active</option>
                            <option value="draft">Draft</option>
                            <option value="on_leave">On Leave</option>
                        </select>
                    </div>
                </div>

                <button type="submit" class="btn btn-success w-100"
                        onclick="return confirm('Approve this submission and create the employee record?')">
                    <i class="bi bi-check-circle-fill me-2"></i>Approve &amp; Create Employee
                </button>
            </form>
        </div>
    </div>

    <!-- ── Reject Card ──────────────────────────────────────────────────── -->
    <div class="card border-0 shadow-sm" id="rejectPanel">
        <div class="card-header border-0 py-3" style="background:linear-gradient(135deg,#fff1f2,#ffe4e6)">
            <h6 class="mb-0 fw-bold text-danger">
                <i class="bi bi-x-circle-fill me-2"></i>Reject Submission
            </h6>
        </div>
        <div class="card-body">
            <form method="post" action="<?= e(url('/employee-registration/review/' . $s['token'] . '/reject')); ?>">
                <?= csrf_field(); ?>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Reason <span class="text-muted fw-normal">(optional)</span></label>
                    <textarea name="rejection_reason" class="form-control form-control-sm" rows="3"
                              placeholder="Explain why this submission is being rejected…"></textarea>
                </div>
                <button type="submit" class="btn btn-outline-danger w-100"
                        onclick="return confirm('Reject this submission? This cannot be undone.')">
                    <i class="bi bi-x-lg me-2"></i>Reject Submission
                </button>
            </form>
        </div>
    </div>

    <?php endif; ?>

</div><!-- /sticky-top -->
</div><!-- /col-right -->

</div><!-- /row -->
