<?php declare(strict_types=1); ?>
<?php if (!empty($recaptchaSiteKey)): ?>
<script src="https://www.google.com/recaptcha/api.js?render=<?= e($recaptchaSiteKey); ?>"></script>
<?php endif; ?>
<div class="careers-hero">
    <div class="container" style="max-width:480px">
        <h1 class="mb-1"><i class="bi bi-person-plus me-2"></i>Create Account</h1>
        <p class="mb-0 opacity-75">Join our talent network and apply for exciting opportunities.</p>
    </div>
</div>

<div class="container py-5" style="max-width:480px">
    <div class="section-card p-4 p-md-5">
        <form method="post" action="<?= e(url('/careers/register')); ?>" id="careersRegisterForm">
            <?= csrf_field(); ?>
            <?php if (!empty($recaptchaSiteKey)): ?>
                <input type="hidden" name="g-recaptcha-response" id="careersRegisterRecaptchaToken">
            <?php endif; ?>

            <div class="mb-3">
                <label class="form-label fw-semibold">Username *</label>
                <input type="text" name="username" class="form-control" value="<?= e((string) old('username')); ?>"
                       placeholder="e.g. john_smith" pattern="[a-zA-Z0-9_]{3,40}" required>
                <div class="form-text">3–40 characters, letters/numbers/underscores only.</div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Email Address *</label>
                <input type="email" name="email" class="form-control" value="<?= e((string) old('email')); ?>"
                       placeholder="your@email.com" required>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Password *</label>
                <input type="password" name="password" class="form-control" minlength="8"
                       placeholder="Minimum 8 characters" required>
            </div>

            <div class="mb-4">
                <label class="form-label fw-semibold">Confirm Password *</label>
                <input type="password" name="password_confirmation" class="form-control" minlength="8"
                       placeholder="Repeat your password" required>
            </div>

            <button type="submit" class="btn btn-danger w-100 fw-semibold py-2">Create Account</button>
        </form>

        <hr class="my-4">
        <p class="text-center mb-0 text-muted small">
            Already have an account? <a href="<?= e(url('/careers/login')); ?>">Sign in</a>
        </p>
    </div>
</div>
<?php if (!empty($recaptchaSiteKey)): ?>
<script>
document.getElementById('careersRegisterForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var form = this;
    grecaptcha.ready(function() {
        grecaptcha.execute('<?= e($recaptchaSiteKey); ?>', {action: 'careers_register'}).then(function(token) {
            document.getElementById('careersRegisterRecaptchaToken').value = token;
            form.submit();
        });
    });
});
</script>
<?php endif; ?>
