<?php declare(strict_types=1); ?>
<?php $employee = $employee ?? []; $options = $options ?? []; ?>
<?php $countries = ['Afghanistan','Albania','Algeria','Andorra','Angola','Antigua and Barbuda','Argentina','Armenia','Australia','Austria','Azerbaijan','Bahamas','Bahrain','Bangladesh','Barbados','Belarus','Belgium','Belize','Benin','Bhutan','Bolivia','Bosnia and Herzegovina','Botswana','Brazil','Brunei','Bulgaria','Burkina Faso','Burundi','Cabo Verde','Cambodia','Cameroon','Canada','Central African Republic','Chad','Chile','China','Colombia','Comoros','Congo','Costa Rica','Croatia','Cuba','Cyprus','Czech Republic','Denmark','Djibouti','Dominica','Dominican Republic','Ecuador','Egypt','El Salvador','Equatorial Guinea','Eritrea','Estonia','Eswatini','Ethiopia','Fiji','Finland','France','Gabon','Gambia','Georgia','Germany','Ghana','Greece','Grenada','Guatemala','Guinea','Guinea-Bissau','Guyana','Haiti','Honduras','Hungary','Iceland','India','Indonesia','Iran','Iraq','Ireland','Israel','Italy','Ivory Coast','Jamaica','Japan','Jordan','Kazakhstan','Kenya','Kiribati','Kosovo','Kuwait','Kyrgyzstan','Laos','Latvia','Lebanon','Lesotho','Liberia','Libya','Liechtenstein','Lithuania','Luxembourg','Madagascar','Malawi','Malaysia','Maldives','Mali','Malta','Marshall Islands','Mauritania','Mauritius','Mexico','Micronesia','Moldova','Monaco','Mongolia','Montenegro','Morocco','Mozambique','Myanmar','Namibia','Nauru','Nepal','Netherlands','New Zealand','Nicaragua','Niger','Nigeria','North Korea','North Macedonia','Norway','Oman','Pakistan','Palau','Palestine','Panama','Papua New Guinea','Paraguay','Peru','Philippines','Poland','Portugal','Qatar','Romania','Russia','Rwanda','Saint Kitts and Nevis','Saint Lucia','Saint Vincent and the Grenadines','Samoa','San Marino','Sao Tome and Principe','Saudi Arabia','Senegal','Serbia','Seychelles','Sierra Leone','Singapore','Slovakia','Slovenia','Solomon Islands','Somalia','South Africa','South Korea','South Sudan','Spain','Sri Lanka','Sudan','Suriname','Sweden','Switzerland','Syria','Taiwan','Tajikistan','Tanzania','Thailand','Timor-Leste','Togo','Tonga','Trinidad and Tobago','Tunisia','Turkey','Turkmenistan','Tuvalu','Uganda','Ukraine','United Arab Emirates','United Kingdom','United States','Uruguay','Uzbekistan','Vanuatu','Vatican City','Venezuela','Vietnam','Yemen','Zambia','Zimbabwe']; ?>
<form method="post" action="<?= e(url($formAction)); ?>" enctype="multipart/form-data">
    <?= csrf_field(); ?>
    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card content-card mb-4"><div class="card-body p-4">
                <div class="form-section-title">Personal Information</div>
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label">Employee Code</label><input type="text" name="employee_code" class="form-control" value="<?= e((string) old('employee_code', $isEdit ? ($employee['employee_code'] ?? '') : '')); ?>" placeholder="<?= $isEdit ? '' : e((string) ($employee['employee_code'] ?? 'Auto-generated')); ?>"><div class="form-text">Leave blank to auto-generate.</div></div>
                    <div class="col-md-4"><label class="form-label">First Name *</label><input type="text" name="first_name" class="form-control" value="<?= e((string) old('first_name', $employee['first_name'] ?? '')); ?>" required></div>
                    <div class="col-md-4"><label class="form-label">Middle Name</label><input type="text" name="middle_name" class="form-control" value="<?= e((string) old('middle_name', $employee['middle_name'] ?? '')); ?>"></div>
                    <div class="col-md-4"><label class="form-label">Last Name *</label><input type="text" name="last_name" class="form-control" value="<?= e((string) old('last_name', $employee['last_name'] ?? '')); ?>" required></div>
                    <div class="col-md-4"><label class="form-label">Work Email *</label><input type="email" name="work_email" class="form-control" value="<?= e((string) old('work_email', $employee['work_email'] ?? '')); ?>" required></div>
                    <div class="col-md-4"><label class="form-label">Personal Email</label><input type="email" name="personal_email" class="form-control" value="<?= e((string) old('personal_email', $employee['personal_email'] ?? '')); ?>"></div>
                    <div class="col-md-4"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" value="<?= e((string) old('phone', $employee['phone'] ?? '')); ?>"></div>
                    <div class="col-md-4"><label class="form-label">Alternate Phone</label><input type="text" name="alternate_phone" class="form-control" value="<?= e((string) old('alternate_phone', $employee['alternate_phone'] ?? '')); ?>"></div>
                    <div class="col-md-4"><label class="form-label">Date of Birth</label><input type="date" name="date_of_birth" class="form-control" value="<?= e((string) old('date_of_birth', $employee['date_of_birth'] ?? '')); ?>"></div>
                    <div class="col-md-4"><label class="form-label">Gender</label><select name="gender" class="form-select"><option value="">Select gender</option><?php foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other', 'prefer_not_to_say' => 'Prefer not to say'] as $value => $label): ?><option value="<?= e($value); ?>" <?= (string) old('gender', $employee['gender'] ?? '') === $value ? 'selected' : ''; ?>><?= e($label); ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-4"><label class="form-label">Marital Status</label><select name="marital_status" class="form-select"><option value="">Select status</option><?php foreach (['single' => 'Single', 'married' => 'Married', 'divorced' => 'Divorced', 'widowed' => 'Widowed', 'other' => 'Other'] as $value => $label): ?><option value="<?= e($value); ?>" <?= (string) old('marital_status', $employee['marital_status'] ?? '') === $value ? 'selected' : ''; ?>><?= e($label); ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-4"><label class="form-label">Nationality</label><select name="nationality" class="form-select"><option value="">Select country</option><?php foreach ($countries as $c): ?><option value="<?= e($c); ?>" <?= (string) old('nationality', $employee['nationality'] ?? '') === $c ? 'selected' : ''; ?>><?= e($c); ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-4"><label class="form-label">Second Nationality</label><select name="second_nationality" class="form-select"><option value="">None</option><?php foreach ($countries as $c): ?><option value="<?= e($c); ?>" <?= (string) old('second_nationality', $employee['second_nationality'] ?? '') === $c ? 'selected' : ''; ?>><?= e($c); ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-4"><label class="form-label">ID / National Number</label><input type="text" name="id_number" class="form-control" value="<?= e((string) old('id_number', $employee['id_number'] ?? '')); ?>" autocomplete="off"></div>
                    <div class="col-md-4"><label class="form-label">Passport Number</label><input type="text" name="passport_number" class="form-control" value="<?= e((string) old('passport_number', $employee['passport_number'] ?? '')); ?>" autocomplete="off"></div>
                </div>
            </div></div>

            <div class="card content-card"><div class="card-body p-4">
                <div class="form-section-title">Employment Details</div>
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label">Company *</label><select name="company_id" class="form-select" required><option value="">Select company</option><?php foreach (($options['companies'] ?? []) as $value => $label): ?><option value="<?= e((string) $value); ?>" <?= (string) old('company_id', $employee['company_id'] ?? '') === (string) $value ? 'selected' : ''; ?>><?= e($label); ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-4"><label class="form-label">Branch</label><select name="branch_id" class="form-select"><option value="">Select branch</option><?php foreach (($options['branches'] ?? []) as $value => $label): ?><option value="<?= e((string) $value); ?>" <?= (string) old('branch_id', $employee['branch_id'] ?? '') === (string) $value ? 'selected' : ''; ?>><?= e($label); ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-4"><label class="form-label">Department</label><select name="department_id" class="form-select"><option value="">Select department</option><?php foreach (($options['departments'] ?? []) as $value => $label): ?><option value="<?= e((string) $value); ?>" <?= (string) old('department_id', $employee['department_id'] ?? '') === (string) $value ? 'selected' : ''; ?>><?= e($label); ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-4"><label class="form-label">Team</label><select name="team_id" class="form-select"><option value="">Select team</option><?php foreach (($options['teams'] ?? []) as $value => $label): ?><option value="<?= e((string) $value); ?>" <?= (string) old('team_id', $employee['team_id'] ?? '') === (string) $value ? 'selected' : ''; ?>><?= e($label); ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-4"><label class="form-label">Job Title</label><select name="job_title_id" class="form-select"><option value="">Select job title</option><?php foreach (($options['job_titles'] ?? []) as $value => $label): ?><option value="<?= e((string) $value); ?>" <?= (string) old('job_title_id', $employee['job_title_id'] ?? '') === (string) $value ? 'selected' : ''; ?>><?= e($label); ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-4"><label class="form-label">Designation</label><select name="designation_id" class="form-select"><option value="">Select designation</option><?php foreach (($options['designations'] ?? []) as $value => $label): ?><option value="<?= e((string) $value); ?>" <?= (string) old('designation_id', $employee['designation_id'] ?? '') === (string) $value ? 'selected' : ''; ?>><?= e($label); ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-4"><label class="form-label">Manager</label><select name="manager_employee_id" class="form-select"><option value="">Select manager</option><?php foreach (($options['managers'] ?? []) as $value => $label): ?><option value="<?= e((string) $value); ?>" <?= (string) old('manager_employee_id', $employee['manager_employee_id'] ?? '') === (string) $value ? 'selected' : ''; ?>><?= e($label); ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-4"><label class="form-label">Employment Type *</label><select name="employment_type" class="form-select" required><option value="">Select type</option><?php foreach (['full_time' => 'Full Time', 'part_time' => 'Part Time', 'contract' => 'Contract', 'intern' => 'Intern', 'temporary' => 'Temporary'] as $value => $label): ?><option value="<?= e($value); ?>" <?= (string) old('employment_type', $employee['employment_type'] ?? '') === $value ? 'selected' : ''; ?>><?= e($label); ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-4"><label class="form-label">Contract Type</label><input type="text" name="contract_type" class="form-control" value="<?= e((string) old('contract_type', $employee['contract_type'] ?? '')); ?>"></div>
                    <div class="col-md-4"><label class="form-label">Employee Status</label><select name="employee_status" class="form-select"><?php foreach (['draft' => 'Draft', 'active' => 'Active', 'on_leave' => 'On Leave', 'inactive' => 'Inactive', 'resigned' => 'Resigned', 'terminated' => 'Terminated', 'archived' => 'Archived'] as $value => $label): ?><option value="<?= e($value); ?>" <?= (string) old('employee_status', $employee['employee_status'] ?? '') === $value ? 'selected' : ''; ?>><?= e($label); ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-4"><label class="form-label">Joining Date</label><input type="date" name="joining_date" class="form-control" value="<?= e((string) old('joining_date', $employee['joining_date'] ?? '')); ?>"></div>
                    <div class="col-md-4"><label class="form-label">Probation Start</label><input type="date" name="probation_start_date" class="form-control" value="<?= e((string) old('probation_start_date', $employee['probation_start_date'] ?? '')); ?>"></div>
                    <div class="col-md-4"><label class="form-label">Probation End</label><input type="date" name="probation_end_date" class="form-control" value="<?= e((string) old('probation_end_date', $employee['probation_end_date'] ?? '')); ?>"></div>
                    <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="4"><?= e((string) old('notes', $employee['notes'] ?? '')); ?></textarea></div>
                </div>
            </div></div>

            <div class="card content-card mt-4"><div class="card-body p-4">
                <div class="form-section-title">Emergency Contacts</div>
                <div id="ec-wrapper">
                <?php
                $ecList = $emergencyContacts ?? [];
                if (empty($ecList)) {
                    $ecList = [['full_name'=>'','relationship'=>'','phone'=>'','alternate_phone'=>'','email'=>'','is_primary'=>0]];
                }
                foreach ($ecList as $ecIdx => $ec):
                ?>
                <div class="ec-row border rounded p-3 mb-3 position-relative">
                    <?php if ($ecIdx > 0): ?>
                    <button type="button" class="btn-close position-absolute top-0 end-0 m-2 ec-remove" aria-label="Remove"></button>
                    <?php endif; ?>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="emergency_contacts[<?= $ecIdx; ?>][full_name]" class="form-control" value="<?= e((string)($ec['full_name']??'')); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Relationship</label>
                            <input type="text" name="emergency_contacts[<?= $ecIdx; ?>][relationship]" class="form-control" value="<?= e((string)($ec['relationship']??'')); ?>" placeholder="e.g. Spouse, Parent">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Phone</label>
                            <input type="text" name="emergency_contacts[<?= $ecIdx; ?>][phone]" class="form-control" value="<?= e((string)($ec['phone']??'')); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Alternate Phone</label>
                            <input type="text" name="emergency_contacts[<?= $ecIdx; ?>][alternate_phone]" class="form-control" value="<?= e((string)($ec['alternate_phone']??'')); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Email</label>
                            <input type="email" name="emergency_contacts[<?= $ecIdx; ?>][email]" class="form-control" value="<?= e((string)($ec['email']??'')); ?>">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="emergency_contacts[<?= $ecIdx; ?>][is_primary]" value="1" id="ec_primary_<?= $ecIdx; ?>" <?= !empty($ec['is_primary']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="ec_primary_<?= $ecIdx; ?>">Primary contact</label>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                </div>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="ec-add-btn">+ Add Another Contact</button>
            </div></div>
        </div>
        <div class="col-xl-4">
            <div class="card content-card mb-4"><div class="card-body p-4">
                <div class="form-section-title">Profile Photo</div>
                <?php if (!empty($employee['profile_photo']) && is_file(base_path((string) $employee['profile_photo']))): ?>
                    <div class="text-center mb-3">
                        <img src="<?= e(url('/' . ltrim((string) $employee['profile_photo'], '/'))); ?>" alt="Photo"
                             style="width:100px;height:100px;object-fit:cover;border-radius:50%;border:3px solid #dee2e6;">
                    </div>
                <?php endif; ?>
                <input type="file" name="profile_photo" class="form-control form-control-sm" accept="image/png,image/jpeg,image/jpg,image/gif">
                <div class="form-text">PNG or JPG — max 2 MB.</div>
            </div></div>
            <div class="card content-card"><div class="card-body p-4">
                <h5 class="mb-2"><?= e($isEdit ? 'Update employee record' : 'Create employee record'); ?></h5>
                <p class="text-muted small mb-4">Fill in the details below and add emergency contacts in the section on the left. Documents and leave history are managed from the profile page after saving.</p>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary"><?= e($submitLabel); ?></button>
                    <a href="<?= e(url($isEdit ? '/employees/' . ($employee['id'] ?? '') : '/employees')); ?>" class="btn btn-outline-secondary"><?= e($isEdit ? 'Back to Profile' : 'Back to Directory'); ?></a>
                </div>
            </div></div>
        </div>
    </div>
</form>
<script>
(function () {
    var wrapper = document.getElementById('ec-wrapper');
    var addBtn  = document.getElementById('ec-add-btn');
    if (!addBtn) return;

    function rowCount() { return wrapper.querySelectorAll('.ec-row').length; }

    function buildRow(idx) {
        var div = document.createElement('div');
        div.className = 'ec-row border rounded p-3 mb-3 position-relative';
        div.innerHTML =
            '<button type="button" class="btn-close position-absolute top-0 end-0 m-2 ec-remove" aria-label="Remove"></button>' +
            '<div class="row g-3">' +
                '<div class="col-md-4"><label class="form-label">Full Name</label>' +
                '<input type="text" name="emergency_contacts[' + idx + '][full_name]" class="form-control"></div>' +
                '<div class="col-md-4"><label class="form-label">Relationship</label>' +
                '<input type="text" name="emergency_contacts[' + idx + '][relationship]" class="form-control" placeholder="e.g. Spouse, Parent"></div>' +
                '<div class="col-md-4"><label class="form-label">Phone</label>' +
                '<input type="text" name="emergency_contacts[' + idx + '][phone]" class="form-control"></div>' +
                '<div class="col-md-4"><label class="form-label">Alternate Phone</label>' +
                '<input type="text" name="emergency_contacts[' + idx + '][alternate_phone]" class="form-control"></div>' +
                '<div class="col-md-4"><label class="form-label">Email</label>' +
                '<input type="email" name="emergency_contacts[' + idx + '][email]" class="form-control"></div>' +
                '<div class="col-md-4 d-flex align-items-end"><div class="form-check mb-2">' +
                '<input class="form-check-input" type="checkbox" name="emergency_contacts[' + idx + '][is_primary]" value="1" id="ec_primary_' + idx + '">' +
                '<label class="form-check-label" for="ec_primary_' + idx + '">Primary contact</label>' +
                '</div></div>' +
            '</div>';
        return div;
    }

    function reindex() {
        wrapper.querySelectorAll('.ec-row').forEach(function (row, i) {
            row.querySelectorAll('[name]').forEach(function (el) {
                el.name = el.name.replace(/emergency_contacts\[\d+\]/, 'emergency_contacts[' + i + ']');
            });
            var cb = row.querySelector('[type=checkbox]');
            var lbl = row.querySelector('label[for^="ec_primary_"]');
            if (cb)  { cb.id  = 'ec_primary_' + i; }
            if (lbl) { lbl.setAttribute('for', 'ec_primary_' + i); }
        });
    }

    addBtn.addEventListener('click', function () {
        var idx = rowCount();
        wrapper.appendChild(buildRow(idx));
    });

    wrapper.addEventListener('click', function (e) {
        if (!e.target.classList.contains('ec-remove')) return;
        e.target.closest('.ec-row').remove();
        reindex();
    });
}());
</script>