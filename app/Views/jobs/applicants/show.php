<?php declare(strict_types=1);
$fullName = trim(($application['first_name'] ?? '') . ' ' . ($application['last_name'] ?? ''));
$empPref  = is_string($application['employment_type_preference'] ?? null) ? (json_decode((string)$application['employment_type_preference'], true) ?? []) : ($application['employment_type_preference'] ?? []);
$statusColors = ['new'=>'primary','reviewing'=>'warning','shortlisted'=>'info','interviewed'=>'secondary','offered'=>'success','rejected'=>'danger','hired'=>'success','withdrawn'=>'light'];
$statusList = ['new','reviewing','shortlisted','interviewed','offered','rejected','hired','withdrawn'];
?>
<div class="row g-4">

    <!-- Header -->
    <div class="col-12">
        <div class="card content-card p-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
                <div class="d-flex align-items-center gap-3">
                    <?php if (!empty($application['photo_path']) && is_file(base_path((string)$application['photo_path']))): ?>
                        <img src="<?= e(url('/' . ltrim((string)$application['photo_path'], '/'))); ?>"
                             class="rounded-circle" style="width:72px;height:72px;object-fit:cover">
                    <?php else: ?>
                        <div class="rounded-circle bg-danger d-flex align-items-center justify-content-center text-white"
                             style="width:72px;height:72px;font-size:1.8rem;flex-shrink:0">
                            <?= strtoupper(substr($fullName !== '' ? $fullName : ($application['username'] ?? 'A'), 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                    <div>
                        <h4 class="fw-bold mb-1"><?= $fullName !== '' ? e($fullName) : e($application['username'] ?? ''); ?></h4>
                        <div class="text-muted small"><?= e((string)$application['email']); ?> &nbsp;·&nbsp; <?= e((string)($application['username'] ?? '')); ?></div>
                        <?php if (!empty($application['current_job_title'])): ?><div class="small"><?= e((string)$application['current_job_title']); ?><?= !empty($application['current_employer']) ? ' at ' . e((string)$application['current_employer']) : ''; ?></div><?php endif; ?>
                        <div class="mt-1">
                            <span class="badge text-bg-<?= $statusColors[$application['status']] ?? 'secondary'; ?> px-3 py-2">
                                <?= ucfirst($application['status']); ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <?php if (!empty($application['cv_file_path'])): ?>
                        <a href="<?= e(url('/admin/jobs/applicants/' . $application['id'] . '/cv')); ?>" class="btn btn-success btn-sm"><i class="bi bi-download me-1"></i>Download CV</a>
                    <?php endif; ?>
                    <a href="<?= e(url('/admin/jobs/applicants')); ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Left: Full CV -->
    <div class="col-lg-8">

        <!-- Applied for -->
        <div class="card content-card p-4 mb-4">
            <h6 class="fw-bold mb-3 text-danger"><i class="bi bi-briefcase me-2"></i>Applied For</h6>
            <?php if ($application['job_id']): ?>
                <div class="fw-semibold"><?= e((string)($application['job_title'] ?? '')); ?></div>
                <div class="small text-muted"><?= e((string)($application['job_type'] ?? '')); ?> · <?= e((string)($application['location_city'] ?? '')); ?></div>
                <a href="<?= e(url('/admin/jobs/' . $application['job_id'])); ?>" class="btn btn-sm btn-outline-primary mt-2">View Job Posting</a>
            <?php else: ?>
                <span class="badge text-bg-info fs-6 px-3 py-2"><i class="bi bi-bank me-2"></i>General Job Bank Submission</span>
            <?php endif; ?>
            <?php if (!empty($application['cover_letter'])): ?>
                <h6 class="fw-bold mt-4 mb-2">Cover Letter</h6>
                <div class="bg-light rounded p-3" style="white-space:pre-wrap;font-size:.9rem"><?= e((string)$application['cover_letter']); ?></div>
            <?php endif; ?>
        </div>

        <!-- Personal info -->
        <div class="card content-card p-4 mb-4">
            <h6 class="fw-bold mb-3 text-danger"><i class="bi bi-person me-2"></i>Personal Information</h6>
            <div class="row g-3 small">
                <div class="col-md-4"><span class="text-muted d-block">Date of Birth</span><?= e((string)($application['date_of_birth'] ?? '—')); ?></div>
                <div class="col-md-4"><span class="text-muted d-block">Gender</span><?= e(ucfirst((string)($application['gender'] ?? '—'))); ?></div>
                <div class="col-md-4"><span class="text-muted d-block">Nationality</span><?= e((string)($application['nationality'] ?? '—')); ?><?= !empty($application['second_nationality']) ? ' / ' . e((string)$application['second_nationality']) : ''; ?></div>
                <div class="col-md-4"><span class="text-muted d-block">Phone</span><?= e((string)($application['phone'] ?? '—')); ?></div>
                <div class="col-md-4"><span class="text-muted d-block">Mobile</span><?= e((string)($application['mobile'] ?? '—')); ?></div>
                <div class="col-md-4"><span class="text-muted d-block">WhatsApp</span><?= e((string)($application['whatsapp_number'] ?? '—')); ?></div>
                <div class="col-12"><span class="text-muted d-block">Address</span><?= e(implode(', ', array_filter([$application['address_line_1'] ?? '', $application['address_line_2'] ?? '', $application['city'] ?? '', $application['country'] ?? '']))); ?></div>
                <?php if (!empty($application['linkedin_url'])): ?><div class="col-md-6"><span class="text-muted d-block">LinkedIn</span><a href="<?= e((string)$application['linkedin_url']); ?>" target="_blank"><?= e((string)$application['linkedin_url']); ?></a></div><?php endif; ?>
                <?php if (!empty($application['portfolio_url'])): ?><div class="col-md-6"><span class="text-muted d-block">Portfolio</span><a href="<?= e((string)$application['portfolio_url']); ?>" target="_blank"><?= e((string)$application['portfolio_url']); ?></a></div><?php endif; ?>
                <?php if (!empty($application['github_url'])): ?><div class="col-md-6"><span class="text-muted d-block">GitHub</span><a href="<?= e((string)$application['github_url']); ?>" target="_blank"><?= e((string)$application['github_url']); ?></a></div><?php endif; ?>
            </div>
        </div>

        <!-- Professional info -->
        <div class="card content-card p-4 mb-4">
            <h6 class="fw-bold mb-3 text-danger"><i class="bi bi-briefcase me-2"></i>Professional</h6>
            <?php if (!empty($application['professional_summary'])): ?>
                <div class="mb-3 text-muted" style="white-space:pre-wrap"><?= e((string)$application['professional_summary']); ?></div>
            <?php endif; ?>
            <div class="row g-3 small">
                <div class="col-md-4"><span class="text-muted d-block">Years of Experience</span><?= e((string)($application['years_of_experience'] ?? '—')); ?></div>
                <div class="col-md-4"><span class="text-muted d-block">Expected Salary</span><?= !empty($application['expected_salary']) ? e((string)$application['salary_currency']) . ' ' . number_format((float)$application['expected_salary']) : '—'; ?></div>
                <div class="col-md-4"><span class="text-muted d-block">Notice Period</span><?= !empty($application['notice_period_days']) ? e((string)$application['notice_period_days']) . ' days' : '—'; ?></div>
                <div class="col-md-4"><span class="text-muted d-block">Available From</span><?= e((string)($application['available_from'] ?? '—')); ?></div>
                <div class="col-md-4"><span class="text-muted d-block">Relocate</span><?= ($application['willing_to_relocate'] ?? 0) ? '<span class="text-success">Yes</span>' : 'No'; ?></div>
                <div class="col-md-4"><span class="text-muted d-block">Travel</span><?= ($application['willing_to_travel'] ?? 0) ? '<span class="text-success">Yes</span>' : 'No'; ?></div>
                <?php if (!empty($empPref)): ?><div class="col-12"><span class="text-muted d-block">Employment Preference</span><?= e(implode(', ', array_map(fn($t) => ucwords(str_replace('_',' ',$t)), $empPref))); ?></div><?php endif; ?>
            </div>
        </div>

        <!-- CV Sections -->
        <?php
        $sectionConfig = [
            'experience'    => ['label' => 'Work Experience',   'icon' => 'bi-buildings'],
            'education'     => ['label' => 'Education',         'icon' => 'bi-mortarboard'],
            'skill'         => ['label' => 'Skills',            'icon' => 'bi-tools'],
            'language'      => ['label' => 'Languages',         'icon' => 'bi-translate'],
            'certification' => ['label' => 'Certifications',    'icon' => 'bi-patch-check'],
            'project'       => ['label' => 'Projects',          'icon' => 'bi-kanban'],
            'award'         => ['label' => 'Awards',            'icon' => 'bi-trophy'],
            'volunteer'     => ['label' => 'Volunteer',         'icon' => 'bi-heart'],
            'reference'     => ['label' => 'References',        'icon' => 'bi-people'],
            'publication'   => ['label' => 'Publications',      'icon' => 'bi-book'],
        ];
        foreach ($sectionConfig as $sType => $sCfg):
            $items = $sections[$sType] ?? [];
            if (empty($items)) continue;
        ?>
        <div class="card content-card p-4 mb-4">
            <h6 class="fw-bold mb-3 text-danger"><i class="<?= e($sCfg['icon']); ?> me-2"></i><?= e($sCfg['label']); ?></h6>
            <?php if ($sType === 'skill'): ?>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($items as $sk):
                        $d = is_array($sk['data']) ? $sk['data'] : [];
                        $level = $d['level'] ?? '';
                        $colors = ['beginner'=>'secondary','intermediate'=>'info','advanced'=>'primary','expert'=>'danger'];
                    ?>
                        <span class="badge text-bg-<?= $colors[$level] ?? 'light'; ?> px-3 py-2"><?= e((string)$sk['title']); ?><?= $level ? ' · ' . ucfirst($level) : ''; ?></span>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($sType === 'language'): ?>
                <div class="row g-2">
                    <?php foreach ($items as $lang):
                        $d = is_array($lang['data']) ? $lang['data'] : [];
                    ?>
                        <div class="col-md-3 text-center border rounded p-2">
                            <div class="fw-semibold"><?= e((string)$lang['title']); ?></div>
                            <div class="small text-muted"><?= e(ucfirst($d['proficiency'] ?? '')); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($items as $item):
                        $d = is_array($item['data']) ? $item['data'] : [];
                        $desc = $d['description'] ?? $d['issuer'] ?? '';
                    ?>
                    <div class="border-start border-danger border-3 ps-3">
                        <div class="fw-semibold"><?= e((string)$item['title']); ?></div>
                        <?php if (!empty($item['subtitle'])): ?><div class="text-muted small"><?= e((string)$item['subtitle']); ?></div><?php endif; ?>
                        <?php if (!empty($item['start_date'])): ?>
                            <div class="text-muted small">
                                <?= e(date('M Y', strtotime((string)$item['start_date']))); ?>
                                <?= $item['is_current'] ? ' — Present' : (!empty($item['end_date']) ? ' — ' . date('M Y', strtotime((string)$item['end_date'])) : ''); ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($desc): ?><p class="small mt-1 mb-0"><?= nl2br(e((string)$desc)); ?></p><?php endif; ?>
                        <?php
                        // Extra data fields
                        $skip = ['description','issuer'];
                        foreach ($d as $k => $v) {
                            if (in_array($k, $skip, true) || empty($v)) continue;
                            echo '<div class="small text-muted mt-1"><strong>' . e(ucwords(str_replace('_',' ',$k))) . ':</strong> ';
                            if (filter_var($v, FILTER_VALIDATE_URL)) echo '<a href="' . e($v) . '" target="_blank">' . e($v) . '</a>';
                            else echo e((string)$v);
                            echo '</div>';
                        }
                        ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

    </div>

    <!-- Right: HR Actions -->
    <div class="col-lg-4">

        <!-- Status update -->
        <div class="card content-card p-4 mb-4">
            <h6 class="fw-bold mb-3 text-danger"><i class="bi bi-pencil-square me-2"></i>Update Application</h6>
            <form method="post" action="<?= e(url('/admin/jobs/applicants/' . $application['id'] . '/status')); ?>">
                <?= csrf_field(); ?>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Status</label>
                    <select name="status" class="form-select">
                        <?php foreach ($statusList as $s): ?>
                            <option value="<?= $s; ?>" <?= $application['status'] === $s ? 'selected' : ''; ?>><?= ucfirst($s); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">HR Rating</label>
                    <select name="hr_rating" class="form-select">
                        <option value="">No rating</option>
                        <?php for ($i=1;$i<=5;$i++): ?>
                            <option value="<?= $i; ?>" <?= ($application['hr_rating'] ?? '') == $i ? 'selected' : ''; ?>><?= str_repeat('★',$i) . str_repeat('☆',5-$i); ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">HR Notes</label>
                    <textarea name="hr_notes" class="form-control" rows="4" placeholder="Internal notes (not visible to applicant)..."><?= e((string)($application['hr_notes'] ?? '')); ?></textarea>
                </div>
                <button type="submit" class="btn btn-danger w-100">Update Application</button>
            </form>
        </div>

        <!-- Quick info -->
        <div class="card content-card p-4 mb-4">
            <h6 class="fw-bold mb-3">Quick Info</h6>
            <dl class="small mb-0">
                <dt class="text-muted">Applied</dt><dd><?= e(date('d M Y H:i', strtotime((string)$application['submitted_at']))); ?></dd>
                <dt class="text-muted">Reviewed</dt><dd><?= !empty($application['reviewed_at']) ? e(date('d M Y', strtotime((string)$application['reviewed_at']))) : '—'; ?></dd>
                <dt class="text-muted">Member Since</dt><dd><?= e(date('d M Y', strtotime((string)$application['seeker_registered_at']))); ?></dd>
                <?php if (!empty($application['cv_file_path'])): ?>
                <dt class="text-muted">CV</dt>
                <dd><a href="<?= e(url('/admin/jobs/applicants/' . $application['id'] . '/cv')); ?>" class="btn btn-sm btn-outline-success w-100"><i class="bi bi-download me-1"></i><?= e((string)($application['cv_original_name'] ?? 'Download CV')); ?></a></dd>
                <?php endif; ?>
            </dl>
        </div>

        <!-- Status history -->
        <?php if (!empty($history)): ?>
        <div class="card content-card p-4">
            <h6 class="fw-bold mb-3"><i class="bi bi-clock-history me-2 text-danger"></i>Status History</h6>
            <div class="d-flex flex-column gap-2">
                <?php foreach ($history as $h): ?>
                <div class="small border-start border-2 ps-2">
                    <div>
                        <?php if ($h['old_status']): ?>
                            <span class="badge text-bg-light"><?= ucfirst((string)$h['old_status']); ?></span>
                            <i class="bi bi-arrow-right mx-1 text-muted"></i>
                        <?php endif; ?>
                        <span class="badge text-bg-<?= $statusColors[$h['new_status']] ?? 'secondary'; ?>"><?= ucfirst((string)$h['new_status']); ?></span>
                    </div>
                    <div class="text-muted" style="font-size:.75rem"><?= e(date('d M Y H:i', strtotime((string)$h['changed_at']))); ?></div>
                    <?php if (!empty($h['notes'])): ?><div class="text-muted mt-1 fst-italic"><?= e((string)$h['notes']); ?></div><?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>
