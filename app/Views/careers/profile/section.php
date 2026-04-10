<?php declare(strict_types=1);

// Section-specific field definitions
$fieldDefs = [
    'experience' => [
        'dateRange' => true,
        'isCurrent' => true,
        'titleLabel' => 'Job Title',
        'subtitleLabel' => 'Company / Employer',
        'extra' => [
            ['name' => 'employment_type', 'label' => 'Employment Type', 'type' => 'select',
             'options' => ['full_time'=>'Full-Time','part_time'=>'Part-Time','contract'=>'Contract','freelance'=>'Freelance','internship'=>'Internship']],
            ['name' => 'location',        'label' => 'Location',          'type' => 'text'],
            ['name' => 'description',     'label' => 'Description / Responsibilities', 'type' => 'textarea'],
        ],
    ],
    'education' => [
        'dateRange' => true,
        'isCurrent' => true,
        'titleLabel' => 'School / University',
        'subtitleLabel' => 'Degree / Certification',
        'extra' => [
            ['name' => 'field_of_study', 'label' => 'Field of Study',  'type' => 'text'],
            ['name' => 'grade',          'label' => 'Grade / GPA',      'type' => 'text'],
            ['name' => 'description',    'label' => 'Activities / Notes','type' => 'textarea'],
        ],
    ],
    'skill' => [
        'dateRange' => false,
        'isCurrent' => false,
        'titleLabel' => 'Skill Name',
        'subtitleLabel' => 'Category (e.g. Programming, Design)',
        'extra' => [
            ['name' => 'level', 'label' => 'Proficiency Level', 'type' => 'select',
             'options' => ['beginner'=>'Beginner','intermediate'=>'Intermediate','advanced'=>'Advanced','expert'=>'Expert']],
        ],
    ],
    'language' => [
        'dateRange' => false,
        'isCurrent' => false,
        'titleLabel' => 'Language',
        'subtitleLabel' => '',
        'extra' => [
            ['name' => 'proficiency', 'label' => 'Proficiency', 'type' => 'select',
             'options' => ['basic'=>'Basic','conversational'=>'Conversational','professional'=>'Professional','fluent'=>'Fluent','native'=>'Native']],
        ],
    ],
    'certification' => [
        'dateRange' => true,
        'isCurrent' => false,
        'titleLabel' => 'Certification Name',
        'subtitleLabel' => 'Issuing Organisation',
        'extra' => [
            ['name' => 'credential_id',  'label' => 'Credential ID',  'type' => 'text'],
            ['name' => 'credential_url', 'label' => 'Credential URL', 'type' => 'url'],
            ['name' => 'expiry_date',    'label' => 'Expiry Date',    'type' => 'date'],
        ],
    ],
    'project' => [
        'dateRange' => true,
        'isCurrent' => true,
        'titleLabel' => 'Project Name',
        'subtitleLabel' => 'Role / Your Contribution',
        'extra' => [
            ['name' => 'url',          'label' => 'Project URL',         'type' => 'url'],
            ['name' => 'technologies', 'label' => 'Technologies Used',   'type' => 'text'],
            ['name' => 'description',  'label' => 'Description',         'type' => 'textarea'],
        ],
    ],
    'award' => [
        'dateRange' => false,
        'isCurrent' => false,
        'titleLabel' => 'Award / Honour',
        'subtitleLabel' => '',
        'extra' => [
            ['name' => 'issuer',      'label' => 'Issuer / Organisation', 'type' => 'text'],
            ['name' => 'description', 'label' => 'Description',           'type' => 'textarea'],
        ],
    ],
    'volunteer' => [
        'dateRange' => true,
        'isCurrent' => true,
        'titleLabel' => 'Role / Position',
        'subtitleLabel' => 'Organisation',
        'extra' => [
            ['name' => 'organization', 'label' => 'Organisation Name', 'type' => 'text'],
            ['name' => 'cause',        'label' => 'Cause / Field',     'type' => 'text'],
            ['name' => 'description',  'label' => 'Description',       'type' => 'textarea'],
        ],
    ],
    'reference' => [
        'dateRange' => false,
        'isCurrent' => false,
        'titleLabel' => "Reference's Full Name",
        'subtitleLabel' => "Reference's Company",
        'extra' => [
            ['name' => 'ref_title',    'label' => 'Job Title',      'type' => 'text'],
            ['name' => 'ref_company',  'label' => 'Company',        'type' => 'text'],
            ['name' => 'ref_email',    'label' => 'Email',          'type' => 'email'],
            ['name' => 'ref_phone',    'label' => 'Phone',          'type' => 'tel'],
            ['name' => 'relationship', 'label' => 'Your Relationship', 'type' => 'text'],
        ],
    ],
    'publication' => [
        'dateRange' => true,
        'isCurrent' => false,
        'titleLabel' => 'Publication Title',
        'subtitleLabel' => 'Authors / Co-Authors',
        'extra' => [
            ['name' => 'publisher',   'label' => 'Publisher / Journal', 'type' => 'text'],
            ['name' => 'url',         'label' => 'URL / DOI',           'type' => 'url'],
            ['name' => 'description', 'label' => 'Description',         'type' => 'textarea'],
        ],
    ],
];

$def = $fieldDefs[$type] ?? ['dateRange' => false, 'isCurrent' => false, 'titleLabel' => 'Title', 'subtitleLabel' => 'Subtitle', 'extra' => []];
?>
<div class="careers-hero py-4">
    <div class="container-fluid px-3 px-lg-5">
        <nav aria-label="breadcrumb"><ol class="breadcrumb mb-2" style="--bs-breadcrumb-divider-color:rgba(255,255,255,.5)">
            <li class="breadcrumb-item"><a href="<?= e(url('/careers/profile')); ?>" class="text-white-50">Profile</a></li>
            <li class="breadcrumb-item active text-white"><?= e($title); ?></li>
        </ol></nav>
        <h1 class="fw-bold mb-0"><?= e($title); ?></h1>
    </div>
</div>

<div class="container-fluid px-3 px-lg-5 py-4">
    <div class="row g-4">
        <div class="col-lg-3"><?php require base_path('app/Views/careers/profile/_sidenav.php'); ?></div>
        <div class="col-lg-9">

            <!-- Add new entry -->
            <div class="section-card p-4 mb-4">
                <h5 class="fw-bold mb-4 border-bottom pb-3 text-danger"><i class="bi bi-plus-circle me-2"></i>Add New Entry</h5>
                <form method="post" action="<?= e(url('/careers/profile/' . $type)); ?>">
                    <?= csrf_field(); ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold"><?= e($def['titleLabel']); ?> *</label>
                            <input type="text" name="title" class="form-control" required>
                        </div>
                        <?php if ($def['subtitleLabel']): ?>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold"><?= e($def['subtitleLabel']); ?></label>
                            <input type="text" name="subtitle" class="form-control">
                        </div>
                        <?php endif; ?>

                        <?php if ($def['dateRange']): ?>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Start Date</label>
                            <input type="date" name="start_date" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">End Date</label>
                            <input type="date" name="end_date" class="form-control" id="endDateAdd">
                        </div>
                        <?php if ($def['isCurrent']): ?>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="is_current" id="isCurrentAdd"
                                       onchange="document.getElementById('endDateAdd').disabled=this.checked">
                                <label class="form-check-label" for="isCurrentAdd">Currently here</label>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>

                        <?php foreach ($def['extra'] as $field): ?>
                        <div class="<?= in_array($field['type'], ['textarea'], true) ? 'col-12' : 'col-md-6'; ?>">
                            <label class="form-label fw-semibold"><?= e($field['label']); ?></label>
                            <?php if ($field['type'] === 'select'): ?>
                                <select name="<?= e($field['name']); ?>" class="form-select">
                                    <option value="">Select...</option>
                                    <?php foreach ($field['options'] as $v => $l): ?>
                                        <option value="<?= e($v); ?>"><?= e($l); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif ($field['type'] === 'textarea'): ?>
                                <textarea name="<?= e($field['name']); ?>" class="form-control" rows="3"></textarea>
                            <?php else: ?>
                                <input type="<?= e($field['type']); ?>" name="<?= e($field['name']); ?>" class="form-control">
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-danger">Add Entry</button>
                    </div>
                </form>
            </div>

            <!-- Existing entries -->
            <?php if (empty($items)): ?>
                <div class="section-card p-5 text-center text-muted">
                    <i class="bi bi-inbox fs-2 d-block mb-3 opacity-25"></i>
                    No entries yet. Use the form above to add your first one.
                </div>
            <?php else: ?>
            <div class="section-card p-4">
                <h5 class="fw-bold mb-4 border-bottom pb-3">Existing Entries <span class="badge text-bg-secondary ms-2"><?= count($items); ?></span></h5>
                <div class="d-flex flex-column gap-3">
                <?php foreach ($items as $item):
                    $d = is_string($item['data']) ? (json_decode($item['data'], true) ?? []) : ($item['data'] ?? []);
                ?>
                    <div class="border rounded p-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <div class="fw-bold"><?= e((string)$item['title']); ?></div>
                                <?php if (!empty($item['subtitle'])): ?><div class="text-muted small"><?= e((string)$item['subtitle']); ?></div><?php endif; ?>
                                <?php if (!empty($item['start_date'])): ?>
                                    <div class="text-muted small mt-1">
                                        <?= e(date('M Y', strtotime((string)$item['start_date']))); ?>
                                        <?= $item['is_current'] ? ' — <span class="text-success">Present</span>' : (!empty($item['end_date']) ? ' — ' . e(date('M Y', strtotime((string)$item['end_date']))) : ''); ?>
                                    </div>
                                <?php endif; ?>
                                <?php
                                $desc = $d['description'] ?? $d['issuer'] ?? $d['level'] ?? $d['proficiency'] ?? $d['credential_id'] ?? '';
                                if ($desc): ?><div class="small text-muted mt-1"><?= e(mb_strimwidth((string)$desc, 0, 120, '...')); ?></div><?php endif; ?>
                            </div>
                            <div class="d-flex gap-2 ms-3">
                                <a href="<?= e(url('/careers/profile/' . $type . '/' . $item['id'] . '/edit')); ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                                <form method="post" action="<?= e(url('/careers/profile/' . $type . '/' . $item['id'] . '/delete')); ?>"
                                      onsubmit="return confirm('Delete this entry?')">
                                    <?= csrf_field(); ?>
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>
