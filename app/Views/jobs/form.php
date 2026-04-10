<?php declare(strict_types=1);
$isEdit = $job !== null;
$action = $isEdit ? url('/admin/jobs/' . $job['id'] . '/edit') : url('/admin/jobs');
$skills = is_array($job['skills_required'] ?? null) ? implode(', ', $job['skills_required']) : ($job['skills_required'] ?? '');
?>
<div class="row g-4">
    <div class="col-12">
        <div class="card content-card">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="mb-0"><?= $isEdit ? 'Edit Job' : 'Post New Job'; ?></h5>
                    <a href="<?= e(url('/admin/jobs')); ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
                </div>

                <form method="post" action="<?= e($action); ?>">
                    <?= csrf_field(); ?>

                    <!-- Basic Info -->
                    <h6 class="fw-bold text-danger border-bottom pb-2 mb-3"><i class="bi bi-briefcase me-2"></i>Basic Information</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Job Title *</label>
                            <input type="text" name="title" class="form-control" value="<?= e((string)($job['title'] ?? old('title'))); ?>"
                                   required oninput="autoSlug(this.value)">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">URL Slug *</label>
                            <input type="text" name="slug" id="slugField" class="form-control" value="<?= e((string)($job['slug'] ?? old('slug'))); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Category</label>
                            <select name="job_category_id" class="form-select">
                                <option value="">No Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id']; ?>" <?= ($job['job_category_id'] ?? '') == $cat['id'] ? 'selected' : ''; ?>><?= e((string)$cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Job Type *</label>
                            <select name="job_type" class="form-select" required>
                                <?php foreach (['full_time'=>'Full-Time','part_time'=>'Part-Time','contract'=>'Contract','internship'=>'Internship','freelance'=>'Freelance'] as $v=>$l): ?>
                                    <option value="<?= $v; ?>" <?= ($job['job_type'] ?? 'full_time') === $v ? 'selected' : ''; ?>><?= $l; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Experience Level *</label>
                            <select name="experience_level" class="form-select" required>
                                <?php foreach (['entry'=>'Entry Level','junior'=>'Junior','mid'=>'Mid Level','senior'=>'Senior','lead'=>'Lead','executive'=>'Executive'] as $v=>$l): ?>
                                    <option value="<?= $v; ?>" <?= ($job['experience_level'] ?? 'mid') === $v ? 'selected' : ''; ?>><?= $l; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="status" class="form-select">
                                <?php foreach (['draft'=>'Draft','open'=>'Open (Published)','paused'=>'Paused','closed'=>'Closed'] as $v=>$l): ?>
                                    <option value="<?= $v; ?>" <?= ($job['status'] ?? 'draft') === $v ? 'selected' : ''; ?>><?= $l; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Open Positions</label>
                            <input type="number" name="positions_count" class="form-control" min="1" value="<?= e((string)($job['positions_count'] ?? 1)); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Application Deadline</label>
                            <input type="date" name="deadline" class="form-control" value="<?= e((string)($job['deadline'] ?? '')); ?>">
                        </div>
                        <div class="col-md-4 d-flex align-items-end gap-4">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="is_featured" id="isFeatured"
                                       <?= ($job['is_featured'] ?? 0) ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-semibold" for="isFeatured">Featured Job</label>
                            </div>
                        </div>
                    </div>

                    <!-- Location -->
                    <h6 class="fw-bold text-danger border-bottom pb-2 mb-3"><i class="bi bi-geo-alt me-2"></i>Location & Company</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4"><label class="form-label fw-semibold">Company Name</label><input type="text" name="company_name" class="form-control" value="<?= e((string)($job['company_name'] ?? '')); ?>"></div>
                        <div class="col-md-4"><label class="form-label fw-semibold">Branch</label><input type="text" name="branch_name" class="form-control" value="<?= e((string)($job['branch_name'] ?? '')); ?>"></div>
                        <div class="col-md-4"><label class="form-label fw-semibold">Department</label><input type="text" name="department_name" class="form-control" value="<?= e((string)($job['department_name'] ?? '')); ?>"></div>
                        <div class="col-md-4"><label class="form-label fw-semibold">City</label><input type="text" name="location_city" class="form-control" value="<?= e((string)($job['location_city'] ?? '')); ?>"></div>
                        <div class="col-md-4"><label class="form-label fw-semibold">Country</label><input type="text" name="location_country" class="form-control" value="<?= e((string)($job['location_country'] ?? '')); ?>"></div>
                    </div>

                    <!-- Experience & Salary -->
                    <h6 class="fw-bold text-danger border-bottom pb-2 mb-3"><i class="bi bi-cash me-2"></i>Experience & Compensation</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-3"><label class="form-label fw-semibold">Min Experience (yrs)</label><input type="number" name="min_experience_years" class="form-control" min="0" value="<?= e((string)($job['min_experience_years'] ?? '')); ?>"></div>
                        <div class="col-md-3"><label class="form-label fw-semibold">Max Experience (yrs)</label><input type="number" name="max_experience_years" class="form-control" min="0" value="<?= e((string)($job['max_experience_years'] ?? '')); ?>"></div>
                        <div class="col-md-2"><label class="form-label fw-semibold">Min Salary</label><input type="number" name="min_salary" class="form-control" min="0" value="<?= e((string)($job['min_salary'] ?? '')); ?>"></div>
                        <div class="col-md-2"><label class="form-label fw-semibold">Max Salary</label><input type="number" name="max_salary" class="form-control" min="0" value="<?= e((string)($job['max_salary'] ?? '')); ?>"></div>
                        <div class="col-md-2"><label class="form-label fw-semibold">Currency</label>
                            <select name="salary_currency" class="form-select">
                                <?php foreach (['USD','EUR','GBP','AED','SAR','QAR','KWD'] as $c): ?>
                                    <option value="<?= $c; ?>" <?= ($job['salary_currency'] ?? 'USD') === $c ? 'selected' : ''; ?>><?= $c; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="salary_visible" id="salaryVisible"
                                       <?= ($job['salary_visible'] ?? 0) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="salaryVisible">Show salary range to applicants</label>
                            </div>
                        </div>
                    </div>

                    <!-- Content -->
                    <h6 class="fw-bold text-danger border-bottom pb-2 mb-3"><i class="bi bi-file-text me-2"></i>Job Content</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-12"><label class="form-label fw-semibold">Job Description *</label><textarea name="description" class="form-control" rows="6" required><?= e((string)($job['description'] ?? '')); ?></textarea></div>
                        <div class="col-12"><label class="form-label fw-semibold">Responsibilities</label><textarea name="responsibilities" class="form-control" rows="5"><?= e((string)($job['responsibilities'] ?? '')); ?></textarea><div class="form-text">Use a new line per bullet point.</div></div>
                        <div class="col-12"><label class="form-label fw-semibold">Requirements</label><textarea name="requirements" class="form-control" rows="5"><?= e((string)($job['requirements'] ?? '')); ?></textarea></div>
                        <div class="col-12"><label class="form-label fw-semibold">Benefits & Perks</label><textarea name="benefits" class="form-control" rows="4"><?= e((string)($job['benefits'] ?? '')); ?></textarea></div>
                        <div class="col-md-8"><label class="form-label fw-semibold">Required Skills</label><input type="text" name="skills_required" class="form-control" value="<?= e($skills); ?>" placeholder="e.g. PHP, Laravel, MySQL (comma separated)"></div>
                        <div class="col-md-4"><label class="form-label fw-semibold">Education Required</label><input type="text" name="education_required" class="form-control" value="<?= e((string)($job['education_required'] ?? '')); ?>" placeholder="e.g. Bachelor's degree"></div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-danger px-4"><?= $isEdit ? 'Update Job' : 'Post Job'; ?></button>
                        <a href="<?= e(url('/admin/jobs')); ?>" class="btn btn-outline-secondary">Cancel</a>
                        <?php if ($isEdit): ?>
                            <form method="post" action="<?= e(url('/admin/jobs/' . $job['id'] . '/delete')); ?>"
                                  onsubmit="return confirm('Delete this job and all its applications?')" class="ms-auto">
                                <?= csrf_field(); ?>
                                <button class="btn btn-outline-danger">Delete Job</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function autoSlug(title) {
    const field = document.getElementById('slugField');
    if (field && !field.dataset.manual) {
        field.value = title.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    }
}
document.getElementById('slugField')?.addEventListener('input', function() {
    this.dataset.manual = '1';
});
</script>
