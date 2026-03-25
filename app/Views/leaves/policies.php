<?php declare(strict_types=1); ?>
<?php require base_path('app/Views/partials/leave-nav.php'); ?>
<div class="row g-4">
    <div class="col-xl-4">
        <div class="card content-card mb-4">
            <div class="card-body p-4">
                <h5 class="mb-2">Add Leave Policy</h5>
                <p class="text-muted small mb-4">Create a reusable base policy with company scope, accrual frequency, and activation status.</p>
                <form method="post" action="<?= e(url('/admin/leave/policies')); ?>">
                    <?= csrf_field(); ?>
                    <div class="mb-3">
                        <label class="form-label">Policy Name *</label>
                        <input type="text" name="name" class="form-control" value="<?= e((string) old('name', '')); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Company Scope</label>
                        <select name="company_id" class="form-select">
                            <option value="">All companies</option>
                            <?php foreach (($companies ?? []) as $company): ?>
                                <option value="<?= e((string) $company['id']); ?>" <?= (string) old('company_id', '') === (string) $company['id'] ? 'selected' : ''; ?>><?= e((string) $company['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3"><?= e((string) old('description', '')); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Accrual Frequency *</label>
                        <select name="accrual_frequency" class="form-select" required>
                            <?php foreach (($accrualFrequencies ?? []) as $value => $label): ?>
                                <option value="<?= e((string) $value); ?>" <?= (string) old('accrual_frequency', 'yearly') === (string) $value ? 'selected' : ''; ?>><?= e((string) $label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Status *</label>
                        <select name="is_active" class="form-select" required>
                            <?php foreach ([1 => 'Active', 0 => 'Inactive'] as $value => $label): ?>
                                <option value="<?= e((string) $value); ?>" <?= (string) old('is_active', '1') === (string) $value ? 'selected' : ''; ?>><?= e((string) $label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Save Leave Policy</button>
                </form>
            </div>
        </div>

        <div class="card content-card">
            <div class="card-body p-4">
                <h5 class="mb-2">Add Policy Rule</h5>
                <p class="text-muted small mb-4">Attach leave allocations to an existing policy. Use the optional filters to target specific employee groups.</p>
                <form method="post" action="<?= e(url('/admin/leave/policies/rules')); ?>">
                    <?= csrf_field(); ?>
                    <div class="mb-3">
                        <label class="form-label">Leave Policy *</label>
                        <select name="leave_policy_id" class="form-select" required>
                            <option value="">Select policy</option>
                            <?php foreach (($policyOptions ?? []) as $policyOption): ?>
                                <?php $policyLabel = (string) $policyOption['name'] . ' · ' . (string) (($policyOption['company_name'] ?? '') !== '' ? $policyOption['company_name'] : 'All companies') . ' · ' . ((int) ($policyOption['is_active'] ?? 0) === 1 ? 'Active' : 'Inactive'); ?>
                                <option value="<?= e((string) $policyOption['id']); ?>" <?= (string) old('leave_policy_id', '') === (string) $policyOption['id'] ? 'selected' : ''; ?>><?= e($policyLabel); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Leave Type *</label>
                        <select name="leave_type_id" class="form-select" required>
                            <option value="">Select leave type</option>
                            <?php foreach (($leaveTypeOptions ?? []) as $leaveTypeOption): ?>
                                <option value="<?= e((string) $leaveTypeOption['id']); ?>" <?= (string) old('leave_type_id', '') === (string) $leaveTypeOption['id'] ? 'selected' : ''; ?>><?= e((string) $leaveTypeOption['name'] . ' (' . $leaveTypeOption['code'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Department</label>
                        <select name="department_id" class="form-select">
                            <option value="">All departments</option>
                            <?php foreach (($departmentOptions ?? []) as $departmentOption): ?>
                                <option value="<?= e((string) $departmentOption['id']); ?>" <?= (string) old('department_id', '') === (string) $departmentOption['id'] ? 'selected' : ''; ?>><?= e((string) $departmentOption['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Job Title</label>
                        <select name="job_title_id" class="form-select">
                            <option value="">All job titles</option>
                            <?php foreach (($jobTitleOptions ?? []) as $jobTitleOption): ?>
                                <option value="<?= e((string) $jobTitleOption['id']); ?>" <?= (string) old('job_title_id', '') === (string) $jobTitleOption['id'] ? 'selected' : ''; ?>><?= e((string) $jobTitleOption['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Employment Type</label>
                        <select name="employment_type" class="form-select">
                            <option value="">All employment types</option>
                            <?php foreach (($employmentTypes ?? []) as $value => $label): ?>
                                <option value="<?= e((string) $value); ?>" <?= (string) old('employment_type', '') === (string) $value ? 'selected' : ''; ?>><?= e((string) $label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Annual Allocation *</label>
                        <input type="number" step="0.01" min="0" name="annual_allocation" class="form-control" value="<?= e((string) old('annual_allocation', '0')); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Monthly Accrual Rate *</label>
                        <input type="number" step="0.01" min="0" name="accrual_rate_monthly" class="form-control" value="<?= e((string) old('accrual_rate_monthly', '0')); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Carry Forward Limit *</label>
                        <input type="number" step="0.01" min="0" name="carry_forward_limit" class="form-control" value="<?= e((string) old('carry_forward_limit', '0')); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Max Consecutive Days</label>
                        <input type="number" step="0.01" min="0.01" name="max_consecutive_days" class="form-control" value="<?= e((string) old('max_consecutive_days', '')); ?>">
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Minimum Service Months *</label>
                        <input type="number" min="0" step="1" name="min_service_months" class="form-control" value="<?= e((string) old('min_service_months', '0')); ?>" required>
                    </div>
                    <button type="submit" class="btn btn-outline-primary w-100">Save Policy Rule</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="card content-card mb-4">
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                    <div>
                        <h5 class="mb-1">Leave Policy Directory</h5>
                        <p class="text-muted mb-0">Review leave policies, accrual frequency, and rule allocations already configured in the system.</p>
                    </div>
                    <form method="get" action="<?= e(url('/admin/leave/policies')); ?>" class="d-flex gap-2">
                        <input type="text" name="q" class="form-control" placeholder="Search policies..." value="<?= e((string) ($search ?? '')); ?>">
                        <button type="submit" class="btn btn-outline-secondary">Search</button>
                    </form>
                </div>

                <?php if (($policies ?? []) === []): ?>
                    <div class="empty-state">No leave policies found for the current search.</div>
                <?php else: ?>
                    <?php foreach ($policies as $policy): ?>
                        <?php $rules = $policyRules[(int) $policy['id']] ?? []; ?>
                        <div class="card border mb-4">
                            <div class="card-body p-4">
                                <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-3">
                                    <div>
                                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                            <h5 class="mb-0"><?= e((string) $policy['name']); ?></h5>
                                            <span class="badge <?= (int) ($policy['is_active'] ?? 0) === 1 ? 'text-bg-success' : 'text-bg-secondary'; ?>"><?= e((int) ($policy['is_active'] ?? 0) === 1 ? 'Active' : 'Inactive'); ?></span>
                                        </div>
                                        <p class="text-muted mb-0"><?= e((string) (($policy['description'] ?? '') !== '' ? $policy['description'] : 'No description provided.')); ?></p>
                                    </div>
                                    <div class="small text-muted">
                                        <div><strong>Company:</strong> <?= e((string) ($policy['company_name'] ?? 'All companies')); ?></div>
                                        <div><strong>Accrual:</strong> <?= e(ucfirst((string) $policy['accrual_frequency'])); ?></div>
                                        <div><strong>Rules:</strong> <?= e((string) ($policy['rules_count'] ?? 0)); ?></div>
                                    </div>
                                </div>

                                <?php if ($rules === []): ?>
                                    <div class="empty-state">No policy rules are configured for this leave policy yet.</div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table align-middle mb-0">
                                            <thead>
                                            <tr>
                                                <th>Leave Type</th><th>Department</th><th>Job Title</th><th>Employment</th><th>Annual</th><th>Monthly</th><th>Carry Forward</th><th>Max Consecutive</th><th>Min Service</th><th>Actions</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <?php foreach ($rules as $rule): ?>
                                                <tr>
                                                    <td><?= e((string) $rule['leave_type_name']); ?></td>
                                                    <td><?= e((string) (($rule['department_name'] ?? '') !== '' ? $rule['department_name'] : 'All')); ?></td>
                                                    <td><?= e((string) (($rule['job_title_name'] ?? '') !== '' ? $rule['job_title_name'] : 'All')); ?></td>
                                                    <td><?= e((string) (($rule['employment_type'] ?? '') !== '' ? ucwords(str_replace('_', ' ', (string) $rule['employment_type'])) : 'All')); ?></td>
                                                    <td><?= e((string) $rule['annual_allocation']); ?></td>
                                                    <td><?= e((string) $rule['accrual_rate_monthly']); ?></td>
                                                    <td><?= e((string) $rule['carry_forward_limit']); ?></td>
                                                    <td><?= e((string) (($rule['max_consecutive_days'] ?? null) !== null ? $rule['max_consecutive_days'] : '—')); ?></td>
                                                    <td><?= e((string) $rule['min_service_months']); ?> months</td>
                                                    <td class="text-nowrap">
                                                        <details>
                                                            <summary class="btn btn-link btn-sm px-0">Edit Rule</summary>
                                                            <form method="post" action="<?= e(url('/admin/leave/policies/rules/' . $rule['id'] . '/update')); ?>" class="mt-3" style="min-width: 320px;">
                                                                <?= csrf_field(); ?>
                                                                <input type="hidden" name="leave_policy_id" value="<?= e((string) $rule['leave_policy_id']); ?>">
                                                                <div class="mb-2">
                                                                    <label class="form-label small mb-1">Leave Type</label>
                                                                    <select name="leave_type_id" class="form-select form-select-sm" required>
                                                                        <?php foreach (($leaveTypeOptions ?? []) as $leaveTypeOption): ?>
                                                                            <option value="<?= e((string) $leaveTypeOption['id']); ?>" <?= (int) $rule['leave_type_id'] === (int) $leaveTypeOption['id'] ? 'selected' : ''; ?>><?= e((string) $leaveTypeOption['name'] . ' (' . $leaveTypeOption['code'] . ')'); ?></option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                </div>
                                                                <div class="mb-2">
                                                                    <label class="form-label small mb-1">Department</label>
                                                                    <select name="department_id" class="form-select form-select-sm">
                                                                        <option value="">All departments</option>
                                                                        <?php foreach (($departmentOptions ?? []) as $departmentOption): ?>
                                                                            <option value="<?= e((string) $departmentOption['id']); ?>" <?= (int) ($rule['department_id'] ?? 0) === (int) $departmentOption['id'] ? 'selected' : ''; ?>><?= e((string) $departmentOption['name']); ?></option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                </div>
                                                                <div class="mb-2">
                                                                    <label class="form-label small mb-1">Job Title</label>
                                                                    <select name="job_title_id" class="form-select form-select-sm">
                                                                        <option value="">All job titles</option>
                                                                        <?php foreach (($jobTitleOptions ?? []) as $jobTitleOption): ?>
                                                                            <option value="<?= e((string) $jobTitleOption['id']); ?>" <?= (int) ($rule['job_title_id'] ?? 0) === (int) $jobTitleOption['id'] ? 'selected' : ''; ?>><?= e((string) $jobTitleOption['name']); ?></option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                </div>
                                                                <div class="mb-2">
                                                                    <label class="form-label small mb-1">Employment Type</label>
                                                                    <select name="employment_type" class="form-select form-select-sm">
                                                                        <option value="">All employment types</option>
                                                                        <?php foreach (($employmentTypes ?? []) as $value => $label): ?>
                                                                            <option value="<?= e((string) $value); ?>" <?= (string) ($rule['employment_type'] ?? '') === (string) $value ? 'selected' : ''; ?>><?= e((string) $label); ?></option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                </div>
                                                                <div class="row g-2">
                                                                    <div class="col-6"><label class="form-label small mb-1">Annual</label><input type="number" step="0.01" min="0" name="annual_allocation" class="form-control form-control-sm" value="<?= e((string) $rule['annual_allocation']); ?>" required></div>
                                                                    <div class="col-6"><label class="form-label small mb-1">Monthly</label><input type="number" step="0.01" min="0" name="accrual_rate_monthly" class="form-control form-control-sm" value="<?= e((string) $rule['accrual_rate_monthly']); ?>" required></div>
                                                                    <div class="col-6"><label class="form-label small mb-1">Carry Forward</label><input type="number" step="0.01" min="0" name="carry_forward_limit" class="form-control form-control-sm" value="<?= e((string) $rule['carry_forward_limit']); ?>" required></div>
                                                                    <div class="col-6"><label class="form-label small mb-1">Max Consecutive</label><input type="number" step="0.01" min="0.01" name="max_consecutive_days" class="form-control form-control-sm" value="<?= e((string) (($rule['max_consecutive_days'] ?? null) !== null ? $rule['max_consecutive_days'] : '')); ?>"></div>
                                                                    <div class="col-12"><label class="form-label small mb-1">Min Service Months</label><input type="number" step="1" min="0" name="min_service_months" class="form-control form-control-sm" value="<?= e((string) $rule['min_service_months']); ?>" required></div>
                                                                </div>
                                                                <button type="submit" class="btn btn-outline-primary btn-sm w-100 mt-3">Update Rule</button>
                                                            </form>
                                                        </details>
                                                        <form method="post" action="<?= e(url('/admin/leave/policies/rules/' . $rule['id'] . '/delete')); ?>" class="mt-2" onsubmit="return confirm('Delete this leave policy rule?');">
                                                            <?= csrf_field(); ?>
                                                            <button type="submit" class="btn btn-outline-danger btn-sm w-100">Delete</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>