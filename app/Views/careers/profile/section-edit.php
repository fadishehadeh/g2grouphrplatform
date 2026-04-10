<?php declare(strict_types=1);
$d = is_array($item['data']) ? $item['data'] : [];

$fieldDefs = [
    'experience' => ['dateRange'=>true,'isCurrent'=>true,'titleLabel'=>'Job Title','subtitleLabel'=>'Company / Employer',
        'extra' => [['name'=>'employment_type','label'=>'Employment Type','type'=>'select','options'=>['full_time'=>'Full-Time','part_time'=>'Part-Time','contract'=>'Contract','freelance'=>'Freelance','internship'=>'Internship']],['name'=>'location','label'=>'Location','type'=>'text'],['name'=>'description','label'=>'Description','type'=>'textarea']]],
    'education' => ['dateRange'=>true,'isCurrent'=>true,'titleLabel'=>'School / University','subtitleLabel'=>'Degree',
        'extra' => [['name'=>'field_of_study','label'=>'Field of Study','type'=>'text'],['name'=>'grade','label'=>'Grade / GPA','type'=>'text'],['name'=>'description','label'=>'Notes','type'=>'textarea']]],
    'skill' => ['dateRange'=>false,'isCurrent'=>false,'titleLabel'=>'Skill Name','subtitleLabel'=>'Category',
        'extra' => [['name'=>'level','label'=>'Proficiency','type'=>'select','options'=>['beginner'=>'Beginner','intermediate'=>'Intermediate','advanced'=>'Advanced','expert'=>'Expert']]]],
    'language' => ['dateRange'=>false,'isCurrent'=>false,'titleLabel'=>'Language','subtitleLabel'=>'',
        'extra' => [['name'=>'proficiency','label'=>'Proficiency','type'=>'select','options'=>['basic'=>'Basic','conversational'=>'Conversational','professional'=>'Professional','fluent'=>'Fluent','native'=>'Native']]]],
    'certification' => ['dateRange'=>true,'isCurrent'=>false,'titleLabel'=>'Certification Name','subtitleLabel'=>'Issuing Organisation',
        'extra' => [['name'=>'credential_id','label'=>'Credential ID','type'=>'text'],['name'=>'credential_url','label'=>'Credential URL','type'=>'url'],['name'=>'expiry_date','label'=>'Expiry Date','type'=>'date']]],
    'project' => ['dateRange'=>true,'isCurrent'=>true,'titleLabel'=>'Project Name','subtitleLabel'=>'Your Role',
        'extra' => [['name'=>'url','label'=>'Project URL','type'=>'url'],['name'=>'technologies','label'=>'Technologies','type'=>'text'],['name'=>'description','label'=>'Description','type'=>'textarea']]],
    'award' => ['dateRange'=>false,'isCurrent'=>false,'titleLabel'=>'Award Name','subtitleLabel'=>'',
        'extra' => [['name'=>'issuer','label'=>'Issuer','type'=>'text'],['name'=>'description','label'=>'Description','type'=>'textarea']]],
    'volunteer' => ['dateRange'=>true,'isCurrent'=>true,'titleLabel'=>'Role','subtitleLabel'=>'Organisation',
        'extra' => [['name'=>'organization','label'=>'Organisation','type'=>'text'],['name'=>'cause','label'=>'Cause','type'=>'text'],['name'=>'description','label'=>'Description','type'=>'textarea']]],
    'reference' => ['dateRange'=>false,'isCurrent'=>false,'titleLabel'=>"Reference's Name",'subtitleLabel'=>"Company",
        'extra' => [['name'=>'ref_title','label'=>'Job Title','type'=>'text'],['name'=>'ref_company','label'=>'Company','type'=>'text'],['name'=>'ref_email','label'=>'Email','type'=>'email'],['name'=>'ref_phone','label'=>'Phone','type'=>'tel'],['name'=>'relationship','label'=>'Your Relationship','type'=>'text']]],
    'publication' => ['dateRange'=>true,'isCurrent'=>false,'titleLabel'=>'Publication Title','subtitleLabel'=>'Authors',
        'extra' => [['name'=>'publisher','label'=>'Publisher','type'=>'text'],['name'=>'url','label'=>'URL / DOI','type'=>'url'],['name'=>'description','label'=>'Description','type'=>'textarea']]],
];

$def = $fieldDefs[$type] ?? ['dateRange'=>false,'isCurrent'=>false,'titleLabel'=>'Title','subtitleLabel'=>'Subtitle','extra'=>[]];
?>
<div class="careers-hero py-4">
    <div class="container-fluid px-3 px-lg-5">
        <nav aria-label="breadcrumb"><ol class="breadcrumb mb-2" style="--bs-breadcrumb-divider-color:rgba(255,255,255,.5)">
            <li class="breadcrumb-item"><a href="<?= e(url('/careers/profile')); ?>" class="text-white-50">Profile</a></li>
            <li class="breadcrumb-item"><a href="<?= e(url('/careers/profile/' . $type)); ?>" class="text-white-50"><?= e($title); ?></a></li>
            <li class="breadcrumb-item active text-white">Edit Entry</li>
        </ol></nav>
        <h1 class="fw-bold mb-0">Edit <?= e($title); ?></h1>
    </div>
</div>

<div class="container-fluid px-3 px-lg-5 py-4">
    <div class="row g-4">
        <div class="col-lg-3"><?php require base_path('app/Views/careers/profile/_sidenav.php'); ?></div>
        <div class="col-lg-9">
            <div class="section-card p-4 p-md-5">
                <form method="post" action="<?= e(url('/careers/profile/' . $type . '/' . $item['id'] . '/edit')); ?>">
                    <?= csrf_field(); ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold"><?= e($def['titleLabel']); ?> *</label>
                            <input type="text" name="title" class="form-control" value="<?= e((string)$item['title']); ?>" required>
                        </div>
                        <?php if ($def['subtitleLabel']): ?>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold"><?= e($def['subtitleLabel']); ?></label>
                            <input type="text" name="subtitle" class="form-control" value="<?= e((string)($item['subtitle'] ?? '')); ?>">
                        </div>
                        <?php endif; ?>

                        <?php if ($def['dateRange']): ?>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Start Date</label>
                            <input type="date" name="start_date" class="form-control" value="<?= e((string)($item['start_date'] ?? '')); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">End Date</label>
                            <input type="date" name="end_date" class="form-control" id="endDateEdit" value="<?= e((string)($item['end_date'] ?? '')); ?>"
                                   <?= $item['is_current'] ? 'disabled' : ''; ?>>
                        </div>
                        <?php if ($def['isCurrent']): ?>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="is_current" id="isCurrentEdit"
                                       <?= $item['is_current'] ? 'checked' : ''; ?>
                                       onchange="document.getElementById('endDateEdit').disabled=this.checked">
                                <label class="form-check-label" for="isCurrentEdit">Currently here</label>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>

                        <?php foreach ($def['extra'] as $field):
                            $fieldName = $field['name'];
                            // Map ref_ prefix back to data keys
                            $dataKey = str_replace('ref_', '', $fieldName);
                            $val = $d[$dataKey] ?? $d[$fieldName] ?? '';
                        ?>
                        <div class="<?= in_array($field['type'], ['textarea'], true) ? 'col-12' : 'col-md-6'; ?>">
                            <label class="form-label fw-semibold"><?= e($field['label']); ?></label>
                            <?php if ($field['type'] === 'select'): ?>
                                <select name="<?= e($fieldName); ?>" class="form-select">
                                    <option value="">Select...</option>
                                    <?php foreach ($field['options'] as $v => $l): ?>
                                        <option value="<?= e($v); ?>" <?= $val === $v ? 'selected' : ''; ?>><?= e($l); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif ($field['type'] === 'textarea'): ?>
                                <textarea name="<?= e($fieldName); ?>" class="form-control" rows="4"><?= e((string)$val); ?></textarea>
                            <?php else: ?>
                                <input type="<?= e($field['type']); ?>" name="<?= e($fieldName); ?>" class="form-control" value="<?= e((string)$val); ?>">
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-danger px-4">Save Changes</button>
                        <a href="<?= e(url('/careers/profile/' . $type)); ?>" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
