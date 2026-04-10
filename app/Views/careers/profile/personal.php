<?php declare(strict_types=1);
$genders = ['male' => 'Male', 'female' => 'Female', 'other' => 'Other', 'prefer_not_to_say' => 'Prefer not to say'];
?>
<div class="careers-hero py-4">
    <div class="container-fluid px-3 px-lg-5">
        <nav aria-label="breadcrumb"><ol class="breadcrumb mb-2" style="--bs-breadcrumb-divider-color:rgba(255,255,255,.5)">
            <li class="breadcrumb-item"><a href="<?= e(url('/careers/profile')); ?>" class="text-white-50">Profile</a></li>
            <li class="breadcrumb-item active text-white">Personal Information</li>
        </ol></nav>
        <h1 class="fw-bold mb-0"><i class="bi bi-person me-2"></i>Personal Information</h1>
    </div>
</div>

<div class="container-fluid px-3 px-lg-5 py-4">
    <div class="row g-4">
        <div class="col-lg-3">
            <?php require base_path('app/Views/careers/profile/_sidenav.php'); ?>
        </div>
        <div class="col-lg-9">
            <div class="section-card p-4 p-md-5">
                <form method="post" action="<?= e(url('/careers/profile/personal')); ?>">
                    <?= csrf_field(); ?>

                    <h5 class="fw-bold mb-4 border-bottom pb-3 text-danger"><i class="bi bi-person me-2"></i>Personal Details</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">First Name *</label>
                            <input type="text" name="first_name" class="form-control" value="<?= e((string)($profile['first_name'] ?? '')); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Middle Name</label>
                            <input type="text" name="middle_name" class="form-control" value="<?= e((string)($profile['middle_name'] ?? '')); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Last Name *</label>
                            <input type="text" name="last_name" class="form-control" value="<?= e((string)($profile['last_name'] ?? '')); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Date of Birth</label>
                            <input type="date" name="date_of_birth" class="form-control" value="<?= e((string)($profile['date_of_birth'] ?? '')); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Gender</label>
                            <select name="gender" class="form-select">
                                <option value="">Select gender</option>
                                <?php foreach ($genders as $val => $label): ?>
                                    <option value="<?= e($val); ?>" <?= ($profile['gender'] ?? '') === $val ? 'selected' : ''; ?>><?= e($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Nationality</label>
                            <input type="text" name="nationality" class="form-control" value="<?= e((string)($profile['nationality'] ?? '')); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Second Nationality</label>
                            <input type="text" name="second_nationality" class="form-control" value="<?= e((string)($profile['second_nationality'] ?? '')); ?>">
                        </div>
                    </div>

                    <h5 class="fw-bold mb-4 border-bottom pb-3 text-danger"><i class="bi bi-telephone me-2"></i>Contact Details</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Phone</label>
                            <input type="tel" name="phone" class="form-control" value="<?= e((string)($profile['phone'] ?? '')); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Mobile</label>
                            <input type="tel" name="mobile" class="form-control" value="<?= e((string)($profile['mobile'] ?? '')); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">WhatsApp</label>
                            <input type="tel" name="whatsapp_number" class="form-control" value="<?= e((string)($profile['whatsapp_number'] ?? '')); ?>">
                        </div>
                    </div>

                    <h5 class="fw-bold mb-4 border-bottom pb-3 text-danger"><i class="bi bi-geo-alt me-2"></i>Address</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Address Line 1</label>
                            <input type="text" name="address_line_1" class="form-control" value="<?= e((string)($profile['address_line_1'] ?? '')); ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Address Line 2</label>
                            <input type="text" name="address_line_2" class="form-control" value="<?= e((string)($profile['address_line_2'] ?? '')); ?>">
                        </div>
                        <div class="col-md-3"><label class="form-label fw-semibold">City</label><input type="text" name="city" class="form-control" value="<?= e((string)($profile['city'] ?? '')); ?>"></div>
                        <div class="col-md-3"><label class="form-label fw-semibold">State / Province</label><input type="text" name="state" class="form-control" value="<?= e((string)($profile['state'] ?? '')); ?>"></div>
                        <div class="col-md-4"><label class="form-label fw-semibold">Country</label><input type="text" name="country" class="form-control" value="<?= e((string)($profile['country'] ?? '')); ?>"></div>
                        <div class="col-md-2"><label class="form-label fw-semibold">Postal Code</label><input type="text" name="postal_code" class="form-control" value="<?= e((string)($profile['postal_code'] ?? '')); ?>"></div>
                    </div>

                    <h5 class="fw-bold mb-4 border-bottom pb-3 text-danger"><i class="bi bi-link me-2"></i>Online Presence</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6"><label class="form-label fw-semibold"><i class="bi bi-linkedin me-1"></i>LinkedIn URL</label><input type="url" name="linkedin_url" class="form-control" placeholder="https://linkedin.com/in/yourname" value="<?= e((string)($profile['linkedin_url'] ?? '')); ?>"></div>
                        <div class="col-md-6"><label class="form-label fw-semibold"><i class="bi bi-globe me-1"></i>Portfolio / Website</label><input type="url" name="portfolio_url" class="form-control" placeholder="https://yoursite.com" value="<?= e((string)($profile['portfolio_url'] ?? '')); ?>"></div>
                        <div class="col-md-6"><label class="form-label fw-semibold"><i class="bi bi-github me-1"></i>GitHub URL</label><input type="url" name="github_url" class="form-control" placeholder="https://github.com/yourname" value="<?= e((string)($profile['github_url'] ?? '')); ?>"></div>
                        <div class="col-md-6"><label class="form-label fw-semibold"><i class="bi bi-link-45deg me-1"></i>Other Website</label><input type="url" name="website_url" class="form-control" placeholder="https://..." value="<?= e((string)($profile['website_url'] ?? '')); ?>"></div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-danger px-4">Save Personal Info</button>
                        <a href="<?= e(url('/careers/profile')); ?>" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
