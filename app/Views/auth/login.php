<?php declare(strict_types=1); ?>
<?php if (!empty($recaptchaSiteKey)): ?>
<script src="https://www.google.com/recaptcha/api.js?render=<?= e($recaptchaSiteKey); ?>"></script>
<?php endif; ?>
<div class="card border-0 shadow-sm auth-form-card">
    <div class="card-body p-4 p-md-5">
        <h2 class="fw-bold mb-2"><?= e($pageTitle ?? 'Login'); ?></h2>
        <p class="text-muted mb-4">Use your work email or username to access the system.</p>
        <form method="post" action="<?= e(url('/login')); ?>" novalidate id="loginForm">
            <?= csrf_field(); ?>
            <?php if (!empty($recaptchaSiteKey)): ?>
                <input type="hidden" name="g-recaptcha-response" id="gRecaptchaResponse">
            <?php endif; ?>
            <div class="mb-3">
                <label class="form-label">Email or Username</label>
                <input type="text" name="login" class="form-control" value="<?= e((string) old('login')); ?>" autofocus required>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="form-text"><i class="bi bi-shield-check me-1"></i>Two-factor verification via email.</div>
                <a href="<?= e(url('/forgot-password')); ?>" class="small">Forgot password?</a>
            </div>
            <button type="submit" class="btn btn-primary w-100" id="loginBtn">Sign In &amp; Verify</button>
        </form>
    </div>
</div>
<?php if (!empty($recaptchaSiteKey)): ?>
<script>
document.getElementById('loginForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var form = this;
    grecaptcha.ready(function() {
        grecaptcha.execute('<?= e($recaptchaSiteKey); ?>', {action: 'login'}).then(function(token) {
            document.getElementById('gRecaptchaResponse').value = token;
            form.submit();
        });
    });
});
</script>
<?php endif; ?>
