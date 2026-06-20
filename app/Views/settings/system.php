<?php declare(strict_types=1); ?>
<?php require base_path('app/Views/partials/settings-nav.php'); ?>

<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
    <div>
        <h5 class="mb-1">System Settings</h5>
        <p class="text-muted mb-0">Super admins can override operational runtime settings here without editing the server <code>.env</code> file.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <form method="post" action="<?= e(url('/settings/system/test-email')); ?>" class="d-inline">
            <?= csrf_field(); ?>
            <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-envelope-check"></i> Send Test Email</button>
        </form>
        <form method="post" action="<?= e(url('/settings/system/test-backups')); ?>" class="d-inline">
            <?= csrf_field(); ?>
            <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-hdd-stack"></i> Check Backup Config</button>
        </form>
        <form method="post" action="<?= e(url('/settings/system/test-b2')); ?>" class="d-inline">
            <?= csrf_field(); ?>
            <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-cloud-check"></i> Validate B2</button>
        </form>
    </div>
</div>

<form method="post" action="<?= e(url('/settings/system')); ?>" enctype="multipart/form-data">
    <?= csrf_field(); ?>

    <div class="card content-card mb-4">
        <div class="card-body p-4">
            <div class="mb-4">
                <h5 class="mb-1">Branding</h5>
                <p class="text-muted mb-0">These values replace the fallback brand defaults sitewide across the sidebar, login, careers, intake, and footer surfaces.</p>
            </div>

            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold required-label">Organisation Name</label>
                    <input type="text"
                           name="name"
                           class="form-control"
                           value="<?= e((string) ($tenant['name'] ?? \App\Support\Branding::name())); ?>"
                           maxlength="150"
                           required>
                    <div class="form-text">Displayed in the sidebar, page titles, and footers.</div>
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold">Tagline</label>
                    <input type="text"
                           name="tagline"
                           class="form-control"
                           value="<?= e((string) ($tenant['tagline'] ?? \App\Support\Branding::tagline())); ?>"
                           maxlength="255">
                    <div class="form-text">Short descriptor shown below the logo.</div>
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold">Primary Logo</label>
                    <?php if (!empty($tenant['logo_path']) && is_file(base_path('public-hr/' . ltrim((string) $tenant['logo_path'], '/')))): ?>
                    <div class="mb-2" id="logoPreviewWrap">
                        <img id="logoPreview"
                             src="<?= e(url('/' . ltrim((string) $tenant['logo_path'], '/'))); ?>"
                             alt="Current logo"
                             style="max-height:60px;max-width:200px;object-fit:contain;border:1px solid #dee2e6;border-radius:4px;padding:4px;background:#fff;">
                    </div>
                    <?php else: ?>
                    <div class="mb-2" id="logoPreviewWrap" style="display:none">
                        <img id="logoPreview" src="" alt="Logo preview" style="max-height:60px;max-width:200px;object-fit:contain;border:1px solid #dee2e6;border-radius:4px;padding:4px;background:#fff;">
                    </div>
                    <?php endif; ?>
                    <input type="file" name="logo_file" id="logoFileInput" class="form-control"
                           accept="image/png,image/jpeg,image/svg+xml,image/gif">
                    <div class="form-text">PNG, JPG, SVG, GIF. Maximum 2 MB.</div>
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold">White / Reversed Logo</label>
                    <?php if (!empty($tenant['logo_path_white']) && is_file(base_path('public-hr/' . ltrim((string) $tenant['logo_path_white'], '/')))): ?>
                    <div class="mb-2" id="logoWhitePreviewWrap" style="background:#1a1a2e;padding:6px;border-radius:4px;display:inline-block;">
                        <img id="logoWhitePreview"
                             src="<?= e(url('/' . ltrim((string) $tenant['logo_path_white'], '/'))); ?>"
                             alt="Current white logo"
                             style="max-height:50px;max-width:180px;object-fit:contain;">
                    </div>
                    <?php else: ?>
                    <div class="mb-2" id="logoWhitePreviewWrap" style="display:none;background:#1a1a2e;padding:6px;border-radius:4px;">
                        <img id="logoWhitePreview" src="" alt="White logo preview" style="max-height:50px;max-width:180px;object-fit:contain;">
                    </div>
                    <?php endif; ?>
                    <input type="file" name="logo_file_white" id="logoWhiteFileInput" class="form-control"
                           accept="image/png,image/jpeg,image/svg+xml,image/gif">
                    <div class="form-text">Used on the login page dark panel.</div>
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold">Brand Colour</label>
                    <div class="input-group">
                        <input type="color"
                               id="brandColorPicker"
                               value="<?= e((string) ($tenant['brand_color'] ?? \App\Support\Branding::brandColor())); ?>"
                               class="form-control form-control-color"
                               style="max-width:50px;">
                        <input type="text"
                               name="brand_color"
                               id="brandColorText"
                               class="form-control"
                               value="<?= e((string) ($tenant['brand_color'] ?? '')); ?>"
                               placeholder="#FF3D33"
                               maxlength="7"
                               pattern="^#[0-9a-fA-F]{6}$">
                    </div>
                    <div class="form-text">Leave blank to fall back to the default brand accent.</div>
                </div>
            </div>
        </div>
    </div>

    <?php foreach (($operationalSettings ?? []) as $section): ?>
    <div class="card content-card mb-4">
        <div class="card-body p-4">
            <div class="mb-4">
                <h5 class="mb-1"><?= e((string) ($section['title'] ?? 'Settings')); ?></h5>
                <p class="text-muted mb-0"><?= e((string) ($section['description'] ?? '')); ?></p>
            </div>

            <div class="row g-3">
                <?php foreach (($section['fields'] ?? []) as $field): ?>
                    <?php
                        $definition = $field['definition'];
                        $alias = (string) $field['alias'];
                        $inputType = (string) ($definition['input'] ?? 'text');
                        $isSecret = !empty($definition['secret']);
                        $effectiveValue = $field['effective_value'];
                        $displayValue = (string) ($field['display_value'] ?? '');
                        $colClass = $inputType === 'textarea' ? 'col-12' : 'col-12 col-md-6';
                    ?>
                    <div class="<?= e($colClass); ?>">
                        <label class="form-label fw-semibold"><?= e((string) $definition['label']); ?></label>

                        <?php if ($inputType === 'boolean'): ?>
                            <input type="hidden" name="settings[<?= e($alias); ?>]" value="0">
                            <div class="form-check form-switch">
                                <input class="form-check-input"
                                       type="checkbox"
                                       role="switch"
                                       name="settings[<?= e($alias); ?>]"
                                       id="setting_<?= e($alias); ?>"
                                       value="1"
                                       <?= !empty($effectiveValue) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="setting_<?= e($alias); ?>">
                                    <?= !empty($effectiveValue) ? 'Enabled' : 'Disabled'; ?>
                                </label>
                            </div>
                        <?php elseif ($inputType === 'select'): ?>
                            <select name="settings[<?= e($alias); ?>]" class="form-select">
                                <?php foreach (($definition['options'] ?? []) as $optionValue => $optionLabel): ?>
                                    <?php
                                        $selected = false;
                                        if ($optionValue === '__EMPTY__') {
                                            $selected = (string) $effectiveValue === '';
                                        } else {
                                            $selected = (string) $effectiveValue === (string) $optionValue;
                                        }
                                    ?>
                                    <option value="<?= e((string) $optionValue); ?>" <?= $selected ? 'selected' : ''; ?>>
                                        <?= e((string) $optionLabel); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif ($inputType === 'textarea'): ?>
                            <textarea name="settings[<?= e($alias); ?>]" class="form-control" rows="4" placeholder="<?= e((string) ($definition['placeholder'] ?? '')); ?>"><?= e($displayValue); ?></textarea>
                        <?php else: ?>
                            <input type="<?= e($isSecret ? 'password' : $inputType); ?>"
                                   name="settings[<?= e($alias); ?>]"
                                   class="form-control"
                                   value="<?= e($isSecret ? '' : $displayValue); ?>"
                                   placeholder="<?= e($isSecret && !empty($field['has_secret_value']) ? 'Saved value hidden. Enter a new value to replace it.' : (string) ($definition['placeholder'] ?? '')); ?>">
                        <?php endif; ?>

                        <div class="form-text"><?= e((string) ($definition['help'] ?? '')); ?></div>
                        <div class="d-flex flex-wrap gap-2 mt-2">
                            <span class="badge text-bg-<?= !empty($field['uses_override']) ? 'primary' : 'light'; ?>">
                                <?= !empty($field['uses_override']) ? 'DB override active' : '.env / config fallback'; ?>
                            </span>
                            <?php if ($isSecret && !empty($field['has_secret_value'])): ?>
                                <span class="badge text-bg-secondary">Secret stored / available</span>
                            <?php endif; ?>
                            <?php if (!empty($field['updated_at'])): ?>
                                <span class="small text-muted">Updated <?= e((string) $field['updated_at']); ?><?= !empty($field['updated_by_name']) ? ' by ' . e((string) $field['updated_by_name']) : ''; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="card content-card mb-4">
        <div class="card-body p-4">
            <div class="mb-4">
                <h5 class="mb-1">Module Toggles</h5>
                <p class="text-muted mb-0">Disabling a module hides its navigation items and blocks access to its routes. Existing data is preserved.</p>
            </div>

            <div class="form-check form-switch">
                <input class="form-check-input"
                       type="checkbox"
                       role="switch"
                       name="payroll_enabled"
                       id="payrollEnabledToggle"
                       value="1"
                       <?= ($payrollEnabled ?? false) ? 'checked' : ''; ?>>
                <label class="form-check-label fw-semibold" for="payrollEnabledToggle">
                    Payroll Module
                </label>
                <div class="form-text mt-1">When enabled, HR admins and super admins can access salary structures, payroll runs, and payslip downloads.</div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-primary">Save System Settings</button>
        <a href="<?= e(url('/settings')); ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

<div class="card content-card">
    <div class="card-body p-4">
        <div class="mb-3">
            <h5 class="mb-1">Recent Operational Changes</h5>
            <p class="text-muted mb-0">Recent runtime override edits saved by super admins.</p>
        </div>

        <?php if (empty($recentOperationalChanges)): ?>
            <div class="empty-state">No operational setting changes have been recorded yet.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Setting</th>
                            <th>Updated By</th>
                            <th>Updated At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentOperationalChanges as $change): ?>
                            <tr>
                                <td><?= e(ucwords(str_replace('_', ' ', (string) $change['category_name']))); ?></td>
                                <td><?= e((string) ($change['label'] ?? ucwords(str_replace('_', ' ', (string) $change['setting_key'])))); ?></td>
                                <td><?= e((string) ($change['updated_by_name'] ?? 'System')); ?></td>
                                <td><?= e((string) $change['updated_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    var input = document.getElementById('logoFileInput');
    var wrap  = document.getElementById('logoPreviewWrap');
    var img   = document.getElementById('logoPreview');
    if (!input || !wrap || !img) { return; }
    input.addEventListener('change', function () {
        var file = this.files[0];
        if (!file) { return; }
        var reader = new FileReader();
        reader.onload = function (e) {
            img.src = e.target.result;
            wrap.style.display = '';
        };
        reader.readAsDataURL(file);
    });
})();

(function () {
    var input = document.getElementById('logoWhiteFileInput');
    var wrap  = document.getElementById('logoWhitePreviewWrap');
    var img   = document.getElementById('logoWhitePreview');
    if (!input || !wrap || !img) { return; }
    input.addEventListener('change', function () {
        var file = this.files[0];
        if (!file) { return; }
        var reader = new FileReader();
        reader.onload = function (e) {
            img.src = e.target.result;
            wrap.style.display = 'inline-block';
        };
        reader.readAsDataURL(file);
    });
})();

(function () {
    var picker = document.getElementById('brandColorPicker');
    var text   = document.getElementById('brandColorText');
    if (!picker || !text) { return; }
    picker.addEventListener('input', function () { text.value = this.value; });
    text.addEventListener('input', function () {
        if (/^#[0-9a-fA-F]{6}$/.test(this.value)) {
            picker.value = this.value;
        }
    });
})();
</script>
