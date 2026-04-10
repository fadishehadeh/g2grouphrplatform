<?php declare(strict_types=1);
$empTypes = ['full_time' => 'Full-Time', 'part_time' => 'Part-Time', 'contract' => 'Contract', 'freelance' => 'Freelance', 'internship' => 'Internship'];
$empPref  = is_string($profile['employment_type_preference'] ?? null)
    ? (json_decode((string)$profile['employment_type_preference'], true) ?? [])
    : ($profile['employment_type_preference'] ?? []);
$currencies = ['USD','EUR','GBP','AED','SAR','QAR','KWD','BHD','OMR','EGP','JOD','LBP'];
?>
<div class="careers-hero py-4">
    <div class="container-fluid px-3 px-lg-5">
        <nav aria-label="breadcrumb"><ol class="breadcrumb mb-2" style="--bs-breadcrumb-divider-color:rgba(255,255,255,.5)">
            <li class="breadcrumb-item"><a href="<?= e(url('/careers/profile')); ?>" class="text-white-50">Profile</a></li>
            <li class="breadcrumb-item active text-white">Professional</li>
        </ol></nav>
        <h1 class="fw-bold mb-0"><i class="bi bi-briefcase me-2"></i>Professional Information</h1>
    </div>
</div>

<div class="container-fluid px-3 px-lg-5 py-4">
    <div class="row g-4">
        <div class="col-lg-3"><?php require base_path('app/Views/careers/profile/_sidenav.php'); ?></div>
        <div class="col-lg-9">
            <div class="section-card p-4 p-md-5">
                <form method="post" action="<?= e(url('/careers/profile/professional')); ?>">
                    <?= csrf_field(); ?>

                    <h5 class="fw-bold mb-4 border-bottom pb-3 text-danger"><i class="bi bi-file-text me-2"></i>Professional Summary</h5>
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Summary / Bio</label>
                        <textarea name="professional_summary" class="form-control" rows="5"
                                  placeholder="Write a compelling summary of your professional background, skills, and career goals..."><?= e((string)($profile['professional_summary'] ?? '')); ?></textarea>
                        <div class="form-text">This is the first thing employers read. Make it count.</div>
                    </div>

                    <h5 class="fw-bold mb-4 border-bottom pb-3 text-danger"><i class="bi bi-buildings me-2"></i>Current Position</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Current Job Title</label>
                            <input type="text" name="current_job_title" class="form-control" value="<?= e((string)($profile['current_job_title'] ?? '')); ?>" placeholder="e.g. Senior Software Engineer">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Current Employer</label>
                            <input type="text" name="current_employer" class="form-control" value="<?= e((string)($profile['current_employer'] ?? '')); ?>" placeholder="Company name">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Years of Experience</label>
                            <input type="number" name="years_of_experience" class="form-control" step="0.5" min="0" max="60" value="<?= e((string)($profile['years_of_experience'] ?? '')); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Notice Period (Days)</label>
                            <input type="number" name="notice_period_days" class="form-control" min="0" value="<?= e((string)($profile['notice_period_days'] ?? '')); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Available From</label>
                            <input type="date" name="available_from" class="form-control" value="<?= e((string)($profile['available_from'] ?? '')); ?>">
                        </div>
                    </div>

                    <h5 class="fw-bold mb-4 border-bottom pb-3 text-danger"><i class="bi bi-currency-dollar me-2"></i>Salary Expectations</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Expected Salary</label>
                            <input type="number" name="expected_salary" class="form-control" min="0" step="100"
                                   value="<?= e((string)($profile['expected_salary'] ?? '')); ?>" placeholder="e.g. 5000">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Currency</label>
                            <select name="salary_currency" class="form-select">
                                <?php foreach ($currencies as $cur): ?>
                                    <option value="<?= e($cur); ?>" <?= ($profile['salary_currency'] ?? 'USD') === $cur ? 'selected' : ''; ?>><?= e($cur); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <h5 class="fw-bold mb-4 border-bottom pb-3 text-danger"><i class="bi bi-sliders me-2"></i>Preferences</h5>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Employment Type Preference</label>
                        <div class="d-flex flex-wrap gap-3">
                            <?php foreach ($empTypes as $val => $label): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="employment_type_preference[]"
                                           value="<?= e($val); ?>" id="emp_<?= e($val); ?>"
                                           <?= in_array($val, (array)$empPref, true) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="emp_<?= e($val); ?>"><?= e($label); ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="willing_to_relocate" id="relocate"
                                       <?= ($profile['willing_to_relocate'] ?? 0) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="relocate">Willing to Relocate</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="willing_to_travel" id="travel"
                                       <?= ($profile['willing_to_travel'] ?? 0) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="travel">Willing to Travel</label>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-danger px-4">Save Professional Info</button>
                        <a href="<?= e(url('/careers/profile')); ?>" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
