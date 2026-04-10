<?php declare(strict_types=1); ?>
<?php require base_path('app/Views/partials/structure-nav.php'); ?>
<?php $c = $company ?? []; $cId = (int) ($c['id'] ?? 0); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-building"></i> <?= e((string) ($c['name'] ?? 'Company')); ?></h4>
    <a href="<?= e(url('/admin/companies')); ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to Companies</a>
</div>

<!-- Company Details Card -->
<div class="card content-card mb-4">
    <div class="card-body p-4">
        <h5 class="mb-3"><i class="bi bi-pencil-square"></i> Company Details</h5>
        <form method="post" action="<?= e(url('/admin/companies/' . $cId)); ?>" enctype="multipart/form-data">
            <?= csrf_field(); ?>
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label">Company Name *</label><input type="text" name="name" class="form-control" value="<?= e(old('name', (string) ($c['name'] ?? ''))); ?>" required></div>
                <div class="col-md-4"><label class="form-label">Legal Name</label><input type="text" name="legal_name" class="form-control" value="<?= e(old('legal_name', (string) ($c['legal_name'] ?? ''))); ?>"></div>
                <div class="col-md-2"><label class="form-label">Code *</label><input type="text" name="code" class="form-control" value="<?= e(old('code', (string) ($c['code'] ?? ''))); ?>" required></div>
                <div class="col-md-3"><label class="form-label">Registration Number</label><input type="text" name="registration_number" class="form-control" value="<?= e(old('registration_number', (string) ($c['registration_number'] ?? ''))); ?>"></div>
                <div class="col-md-3"><label class="form-label">Tax Number</label><input type="text" name="tax_number" class="form-control" value="<?= e(old('tax_number', (string) ($c['tax_number'] ?? ''))); ?>"></div>
                <div class="col-md-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= e(old('email', (string) ($c['email'] ?? ''))); ?>"></div>
                <div class="col-md-3"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" value="<?= e(old('phone', (string) ($c['phone'] ?? ''))); ?>"></div>
                <div class="col-md-6"><label class="form-label">Address Line 1</label><input type="text" name="address_line_1" class="form-control" value="<?= e(old('address_line_1', (string) ($c['address_line_1'] ?? ''))); ?>"></div>
                <div class="col-md-6"><label class="form-label">Address Line 2</label><input type="text" name="address_line_2" class="form-control" value="<?= e(old('address_line_2', (string) ($c['address_line_2'] ?? ''))); ?>"></div>
                <div class="col-md-3"><label class="form-label">City</label><input type="text" name="city" class="form-control" value="<?= e(old('city', (string) ($c['city'] ?? ''))); ?>"></div>
                <div class="col-md-3"><label class="form-label">State</label><input type="text" name="state" class="form-control" value="<?= e(old('state', (string) ($c['state'] ?? ''))); ?>"></div>
                <div class="col-md-2"><label class="form-label">Country</label><input type="text" name="country" class="form-control" value="<?= e(old('country', (string) ($c['country'] ?? ''))); ?>"></div>
                <div class="col-md-2"><label class="form-label">Postal Code</label><input type="text" name="postal_code" class="form-control" value="<?= e(old('postal_code', (string) ($c['postal_code'] ?? ''))); ?>"></div>
                <div class="col-md-2"><label class="form-label">Timezone *</label><input type="text" name="timezone" class="form-control" value="<?= e(old('timezone', (string) ($c['timezone'] ?? 'UTC'))); ?>" required></div>
                <div class="col-md-2"><label class="form-label">Status *</label><select name="status" class="form-select" required><option value="active" <?= (old('status', (string) ($c['status'] ?? 'active'))) === 'active' ? 'selected' : ''; ?>>Active</option><option value="inactive" <?= (old('status', (string) ($c['status'] ?? 'active'))) === 'inactive' ? 'selected' : ''; ?>>Inactive</option></select></div>
                <div class="col-md-4">
                    <label class="form-label">Company Logo</label>
                    <?php if (!empty($c['logo_path']) && is_file(base_path((string) $c['logo_path']))): ?>
                        <div class="mb-2"><img src="<?= e(url('/' . ltrim((string) $c['logo_path'], '/'))); ?>" alt="Logo" style="max-height:60px;max-width:180px;object-fit:contain;border:1px solid #dee2e6;border-radius:4px;padding:4px;background:#fff;"></div>
                    <?php endif; ?>
                    <input type="file" name="logo_file" class="form-control form-control-sm" accept="image/png,image/jpeg,image/svg+xml,image/gif">
                    <div class="form-text">PNG, JPG, SVG — max 2 MB. Used on generated letters.</div>
                </div>
                <div class="col-12"><button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Update Company</button></div>
            </div>
        </form>
    </div>
</div>

<!-- Tabs for sub-entities -->
<ul class="nav nav-tabs mb-3" id="companyTabs" role="tablist">
    <li class="nav-item" role="presentation"><button class="nav-link active" id="branches-tab" data-bs-toggle="tab" data-bs-target="#branches-pane" type="button" role="tab">Branches <span class="badge text-bg-secondary"><?= count($branches ?? []); ?></span></button></li>
    <li class="nav-item" role="presentation"><button class="nav-link" id="departments-tab" data-bs-toggle="tab" data-bs-target="#departments-pane" type="button" role="tab">Departments <span class="badge text-bg-secondary"><?= count($departments ?? []); ?></span></button></li>
    <li class="nav-item" role="presentation"><button class="nav-link" id="jobtitles-tab" data-bs-toggle="tab" data-bs-target="#jobtitles-pane" type="button" role="tab">Job Titles <span class="badge text-bg-secondary"><?= count($jobTitles ?? []); ?></span></button></li>
    <li class="nav-item" role="presentation"><button class="nav-link" id="designations-tab" data-bs-toggle="tab" data-bs-target="#designations-pane" type="button" role="tab">Designations <span class="badge text-bg-secondary"><?= count($designations ?? []); ?></span></button></li>
</ul>
<div class="tab-content" id="companyTabContent">

<!-- Branches Tab -->
<div class="tab-pane fade show active" id="branches-pane" role="tabpanel">
    <div class="row g-4">
        <div class="col-xl-4">
            <div class="card content-card"><div class="card-body p-4">
                <h6 class="mb-3">Add Branch</h6>
                <form method="post" action="<?= e(url('/admin/companies/' . $cId . '/branches')); ?>">
                    <?= csrf_field(); ?>
                    <div class="mb-2"><label class="form-label">Name *</label><input type="text" name="name" class="form-control" required></div>
                    <div class="mb-2"><label class="form-label">Code *</label><input type="text" name="code" class="form-control" required></div>
                    <div class="mb-2"><label class="form-label">Email</label><input type="email" name="email" class="form-control"></div>
                    <div class="mb-2"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control"></div>
                    <div class="mb-2"><label class="form-label">City</label><input type="text" name="city" class="form-control"></div>
                    <div class="mb-2"><label class="form-label">Country</label><input type="text" name="country" class="form-control"></div>
                    <div class="mb-3"><label class="form-label">Status</label><select name="status" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
                    <button type="submit" class="btn btn-primary btn-sm w-100">Add Branch</button>
                </form>
            </div></div>
        </div>
        <div class="col-xl-8">
            <div class="card content-card"><div class="card-body p-4">
                <?php if (($branches ?? []) === []): ?>
                    <div class="empty-state">No branches yet. Add one using the form.</div>
                <?php else: ?>
                    <div class="table-responsive"><table class="table align-middle mb-0">
                        <thead><tr><th>Name</th><th>Code</th><th>City</th><th>Country</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($branches as $b): ?>
                            <tr><td><?= e((string) $b['name']); ?></td><td><?= e((string) $b['code']); ?></td><td><?= e((string) ($b['city'] ?? '')); ?></td><td><?= e((string) ($b['country'] ?? '')); ?></td><td><span class="badge <?= ($b['status'] ?? '') === 'active' ? 'text-bg-success' : 'text-bg-secondary'; ?>"><?= e((string) ($b['status'] ?? '')); ?></span></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table></div>
                <?php endif; ?>
            </div></div>
        </div>
    </div>
</div>

<!-- Departments Tab -->
<div class="tab-pane fade" id="departments-pane" role="tabpanel">
    <div class="row g-4">
        <div class="col-xl-4">
            <div class="card content-card"><div class="card-body p-4">
                <h6 class="mb-3">Add Department</h6>
                <form method="post" action="<?= e(url('/admin/companies/' . $cId . '/departments')); ?>">
                    <?= csrf_field(); ?>
                    <div class="mb-2"><label class="form-label">Name *</label><input type="text" name="name" class="form-control" required></div>
                    <div class="mb-2"><label class="form-label">Code *</label><input type="text" name="code" class="form-control" required></div>
                    <div class="mb-2"><label class="form-label">Branch</label><select name="branch_id" class="form-select"><option value="">No branch</option><?php foreach (($branchOptions ?? []) as $bId => $bName): ?><option value="<?= e((string) $bId); ?>"><?= e($bName); ?></option><?php endforeach; ?></select></div>
                    <div class="mb-2"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
                    <div class="mb-3"><label class="form-label">Status</label><select name="status" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
                    <button type="submit" class="btn btn-primary btn-sm w-100">Add Department</button>
                </form>
            </div></div>
        </div>
        <div class="col-xl-8">
            <div class="card content-card"><div class="card-body p-4">
                <?php if (($departments ?? []) === []): ?>
                    <div class="empty-state">No departments yet. Add one using the form.</div>
                <?php else: ?>
                    <div class="table-responsive"><table class="table align-middle mb-0">
                        <thead><tr><th>Name</th><th>Code</th><th>Branch</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($departments as $d): ?>
                            <tr><td><?= e((string) $d['name']); ?></td><td><?= e((string) $d['code']); ?></td><td><?= e((string) ($d['branch_name'] ?? '—')); ?></td><td><span class="badge <?= ($d['status'] ?? '') === 'active' ? 'text-bg-success' : 'text-bg-secondary'; ?>"><?= e((string) ($d['status'] ?? '')); ?></span></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table></div>
                <?php endif; ?>
            </div></div>
        </div>
    </div>
</div>

<!-- Job Titles Tab -->
<div class="tab-pane fade" id="jobtitles-pane" role="tabpanel">
    <div class="row g-4">
        <div class="col-xl-4">
            <div class="card content-card"><div class="card-body p-4">
                <h6 class="mb-3">Add Job Title</h6>
                <form method="post" action="<?= e(url('/admin/companies/' . $cId . '/job-titles')); ?>">
                    <?= csrf_field(); ?>
                    <div class="mb-2"><label class="form-label">Name *</label><input type="text" name="name" class="form-control" required></div>
                    <div class="mb-2"><label class="form-label">Code *</label><input type="text" name="code" class="form-control" required></div>
                    <div class="mb-2"><label class="form-label">Level Rank</label><input type="number" name="level_rank" class="form-control" value="0" min="0"></div>
                    <div class="mb-2"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
                    <div class="mb-3"><label class="form-label">Status</label><select name="status" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
                    <button type="submit" class="btn btn-primary btn-sm w-100">Add Job Title</button>
                </form>
            </div></div>
        </div>
        <div class="col-xl-8">
            <div class="card content-card"><div class="card-body p-4">
                <?php if (($jobTitles ?? []) === []): ?>
                    <div class="empty-state">No job titles yet. Add one using the form.</div>
                <?php else: ?>
                    <div class="table-responsive"><table class="table align-middle mb-0">
                        <thead><tr><th>Name</th><th>Code</th><th>Level</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($jobTitles as $jt): ?>
                            <tr><td><?= e((string) $jt['name']); ?></td><td><?= e((string) $jt['code']); ?></td><td><?= e((string) ($jt['level_rank'] ?? 0)); ?></td><td><span class="badge <?= ($jt['status'] ?? '') === 'active' ? 'text-bg-success' : 'text-bg-secondary'; ?>"><?= e((string) ($jt['status'] ?? '')); ?></span></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table></div>
                <?php endif; ?>
            </div></div>
        </div>
    </div>
</div>

<!-- Designations Tab -->
<div class="tab-pane fade" id="designations-pane" role="tabpanel">
    <div class="row g-4">
        <div class="col-xl-4">
            <div class="card content-card"><div class="card-body p-4">
                <h6 class="mb-3">Add Designation</h6>
                <form method="post" action="<?= e(url('/admin/companies/' . $cId . '/designations')); ?>">
                    <?= csrf_field(); ?>
                    <div class="mb-2"><label class="form-label">Name *</label><input type="text" name="name" class="form-control" required></div>
                    <div class="mb-2"><label class="form-label">Code *</label><input type="text" name="code" class="form-control" required></div>
                    <div class="mb-2"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
                    <div class="mb-3"><label class="form-label">Status</label><select name="status" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
                    <button type="submit" class="btn btn-primary btn-sm w-100">Add Designation</button>
                </form>
            </div></div>
        </div>
        <div class="col-xl-8">
            <div class="card content-card"><div class="card-body p-4">
                <?php if (($designations ?? []) === []): ?>
                    <div class="empty-state">No designations yet. Add one using the form.</div>
                <?php else: ?>
                    <div class="table-responsive"><table class="table align-middle mb-0">
                        <thead><tr><th>Name</th><th>Code</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($designations as $dg): ?>
                            <tr><td><?= e((string) $dg['name']); ?></td><td><?= e((string) $dg['code']); ?></td><td><span class="badge <?= ($dg['status'] ?? '') === 'active' ? 'text-bg-success' : 'text-bg-secondary'; ?>"><?= e((string) ($dg['status'] ?? '')); ?></span></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table></div>
                <?php endif; ?>
            </div></div>
        </div>
    </div>
</div>

</div>

