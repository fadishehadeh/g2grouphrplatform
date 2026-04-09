<?php declare(strict_types=1); ?>
<?php require base_path('app/Views/partials/letters-nav.php'); ?>

<div class="row justify-content-center">
    <div class="col-xl-7 col-lg-9">
        <div class="card content-card">
            <div class="card-body p-4">
                <h5 class="mb-4">Request a Letter</h5>
                <form method="post" action="<?= e(url('/letters/request')); ?>">
                    <?= csrf_field(); ?>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="letter_type">Letter Type <span class="text-danger">*</span></label>
                        <select name="letter_type" id="letter_type" class="form-select" required>
                            <option value="">— Select a letter type —</option>
                            <option value="salary_certificate"     <?= old('letter_type', '') === 'salary_certificate'     ? 'selected' : ''; ?>>Salary Certificate</option>
                            <option value="employment_certificate" <?= old('letter_type', '') === 'employment_certificate' ? 'selected' : ''; ?>>Employment Certificate</option>
                            <option value="experience_letter"      <?= old('letter_type', '') === 'experience_letter'      ? 'selected' : ''; ?>>Experience Letter</option>
                            <option value="noc"                    <?= old('letter_type', '') === 'noc'                    ? 'selected' : ''; ?>>No Objection Certificate (NOC)</option>
                            <option value="bank_letter"            <?= old('letter_type', '') === 'bank_letter'            ? 'selected' : ''; ?>>Bank Confirmation Letter</option>
                        </select>
                        <div class="form-text" id="letter_type_hint"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="purpose">Purpose / Addressed To</label>
                        <input type="text" name="purpose" id="purpose" class="form-control"
                               value="<?= e((string) old('purpose', '')); ?>"
                               placeholder="e.g. visa application, bank loan, embassy, etc.">
                        <div class="form-text">Help HR understand what the letter will be used for.</div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold" for="notes">Additional Notes</label>
                        <textarea name="notes" id="notes" class="form-control" rows="3"
                                  placeholder="Any specific details you need included in the letter..."><?= e((string) old('notes', '')); ?></textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i> Submit Request</button>
                        <a href="<?= e(url('/letters/my')); ?>" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card content-card mt-3">
            <div class="card-body p-4">
                <h6 class="mb-3">Available Letter Types</h6>
                <div class="row g-3 small">
                    <div class="col-md-6">
                        <div class="border rounded-3 p-3">
                            <div class="fw-semibold mb-1"><i class="bi bi-currency-dollar text-success"></i> Salary Certificate</div>
                            <div class="text-muted">Confirms your employment and monthly salary. Commonly used for visa or bank applications.</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded-3 p-3">
                            <div class="fw-semibold mb-1"><i class="bi bi-person-badge text-primary"></i> Employment Certificate</div>
                            <div class="text-muted">Confirms your current employment status, position, and start date.</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded-3 p-3">
                            <div class="fw-semibold mb-1"><i class="bi bi-award text-warning"></i> Experience Letter</div>
                            <div class="text-muted">Certifies your work tenure and role. Useful when transitioning to a new job.</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded-3 p-3">
                            <div class="fw-semibold mb-1"><i class="bi bi-check-circle text-info"></i> No Objection Certificate</div>
                            <div class="text-muted">States that the company has no objection to your specific request (travel, study, etc.).</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded-3 p-3">
                            <div class="fw-semibold mb-1"><i class="bi bi-bank text-secondary"></i> Bank Confirmation Letter</div>
                            <div class="text-muted">Addressed to a bank to confirm employment and salary details for account or loan purposes.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const hints = {
    salary_certificate:     'HR will verify your salary details before generating this letter.',
    employment_certificate: 'Confirms your current employment, title, and joining date.',
    experience_letter:      'Summarises your work tenure at the organisation.',
    noc:                    'Please specify the purpose clearly so HR can include it in the certificate.',
    bank_letter:            'Addressed to your bank. You may mention the bank name in the notes.',
};
document.getElementById('letter_type').addEventListener('change', function () {
    document.getElementById('letter_type_hint').textContent = hints[this.value] || '';
});
</script>
