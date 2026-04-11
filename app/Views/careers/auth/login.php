<?php declare(strict_types=1); ?>
<?php if (!empty($recaptchaSiteKey)): ?>
<script src="https://www.google.com/recaptcha/api.js?render=<?= e($recaptchaSiteKey); ?>"></script>
<?php endif; ?>
<div class="careers-hero">
    <div class="container" style="max-width:480px">
        <h1 class="mb-1"><i class="bi bi-box-arrow-in-right me-2"></i>Sign In</h1>
        <p class="mb-0 opacity-75">Access your profile and track your applications.</p>
    </div>
</div>

<div class="container py-5" style="max-width:480px">
    <div class="section-card p-4 p-md-5">
        <form method="post" action="<?= e(url('/careers/login')); ?>" id="careersLoginForm">
            <?= csrf_field(); ?>
            <?php if (!empty($recaptchaSiteKey)): ?>
                <input type="hidden" name="g-recaptcha-response" id="careersRecaptchaToken">
            <?php endif; ?>

            <div class="mb-3">
                <label class="form-label fw-semibold">Email Address *</label>
                <input type="email" name="email" class="form-control" value="<?= e((string) old('email')); ?>"
                       placeholder="your@email.com" autofocus required>
            </div>

            <div class="mb-4">
                <label class="form-label fw-semibold">Password *</label>
                <input type="password" name="password" class="form-control" placeholder="Your password" required>
            </div>

            <button type="submit" class="btn btn-danger w-100 fw-semibold py-2" id="careersLoginBtn">
                <i class="bi bi-shield-lock me-1"></i> Sign In &amp; Verify
            </button>
        </form>
        <p class="text-muted small text-center mt-3 mb-0">
            After entering your credentials, a 6-digit code will be sent to your email.
        </p>

        <hr class="my-4">
        <p class="text-center mb-0 text-muted small">
            New here? <a href="<?= e(url('/careers/register')); ?>">Create an account</a>
        </p>
    </div>
</div>
<?php if (!empty($recaptchaSiteKey)): ?>
<script>
document.getElementById('careersLoginForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var form = this;
    grecaptcha.ready(function() {
        grecaptcha.execute('<?= e($recaptchaSiteKey); ?>', {action: 'careers_login'}).then(function(token) {
            document.getElementById('careersRecaptchaToken').value = token;
            form.submit();
        });
    });
});
</script>
<?php endif; ?>
