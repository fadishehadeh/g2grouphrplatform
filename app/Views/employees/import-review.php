<?php declare(strict_types=1); ?>
<div class="card content-card mb-4">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center gap-3">
            <div>
                <h5 class="mb-1">Review Employee Import</h5>
                <p class="text-muted mb-0">Confirm how each imported row should be handled before any employee records are changed.</p>
            </div>
            <a href="<?= e(url('/employees/import')); ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Start Over</a>
        </div>
    </div>
</div>

<?php if (($importErrors ?? []) !== []): ?>
    <div class="alert alert-warning">
        <h6 class="alert-heading mb-2"><i class="bi bi-exclamation-triangle"></i> Rows Excluded Before Review</h6>
        <ul class="mb-0 small">
            <?php foreach (($importErrors ?? []) as $error): ?>
                <li><?= e((string) $error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" action="<?= e(url('/employees/import/confirm')); ?>">
    <?= csrf_field(); ?>
    <div class="card content-card">
        <div class="card-body p-4">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Row</th>
                            <th>Imported Employee</th>
                            <th>Generated Code</th>
                            <th>Match Status</th>
                            <th>Action</th>
                            <th>Target Employee</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($importRows ?? []) as $index => $row): ?>
                            <?php
                            $data = is_array($row['data'] ?? null) ? $row['data'] : [];
                            $candidates = is_array($row['candidates'] ?? null) ? $row['candidates'] : [];
                            $mode = (string) ($row['mode'] ?? 'create');
                            $defaultAction = $mode === 'create' ? 'create' : 'skip';
                            ?>
                            <tr>
                                <td class="fw-semibold"><?= e((string) ($row['row_number'] ?? '')); ?></td>
                                <td>
                                    <div class="fw-semibold"><?= e(trim((string) (($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '')))); ?></div>
                                    <div class="small text-muted"><?= e((string) ($data['employment_type'] ?? '')); ?><?= ($data['work_email'] ?? '') !== '' ? ' · ' . e((string) $data['work_email']) : ''; ?></div>
                                </td>
                                <td><?= e((string) (($row['generated_code'] ?? null) !== null ? $row['generated_code'] : ($data['employee_code'] ?? ''))); ?></td>
                                <td>
                                    <?php if ($mode === 'create'): ?>
                                        <span class="badge text-bg-success">New employee</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-warning"><?= e(ucwords(str_replace('_', ' ', $mode))); ?></span>
                                        <div class="small text-muted mt-1"><?= e((string) ($row['match_reason'] ?? 'Existing employee requires review')); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td style="min-width: 180px;">
                                    <select name="row_action[<?= e((string) $index); ?>]" class="form-select form-select-sm">
                                        <option value="create" <?= $defaultAction === 'create' ? 'selected' : ''; ?>>Create new</option>
                                        <option value="update" <?= $defaultAction === 'update' ? 'selected' : ''; ?>>Update existing</option>
                                        <option value="skip" <?= $defaultAction === 'skip' ? 'selected' : ''; ?>>Skip row</option>
                                        <?php if ($mode !== 'create'): ?>
                                            <option value="create_new">Create new anyway</option>
                                        <?php endif; ?>
                                    </select>
                                </td>
                                <td style="min-width: 280px;">
                                    <?php if ($candidates === []): ?>
                                        <span class="text-muted small">No existing match selected.</span>
                                    <?php else: ?>
                                        <select name="target_employee_id[<?= e((string) $index); ?>]" class="form-select form-select-sm">
                                            <?php foreach ($candidates as $candidate): ?>
                                                <option value="<?= e((string) ($candidate['id'] ?? 0)); ?>" <?= (int) ($candidate['id'] ?? 0) === (int) ($row['matched_employee_id'] ?? 0) ? 'selected' : ''; ?>>
                                                    <?= e((string) ($candidate['full_name'] ?? '')); ?> | <?= e((string) ($candidate['employee_code'] ?? '')); ?><?= ($candidate['work_email'] ?? '') !== '' ? ' | ' . e((string) $candidate['work_email']) : ''; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="small text-muted mt-1">Update will preserve existing values where the Excel cell is blank.</div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="d-flex gap-2 justify-content-end mt-4">
                <a href="<?= e(url('/employees/import')); ?>" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Confirm Import Actions</button>
            </div>
        </div>
    </div>
</form>
