<?php declare(strict_types=1); ?>
<?php $previewLink = flash('reset_preview_link'); ?>
<div class="card border-0 shadow-sm auth-form-card">
    <div class="card-body p-4 p-md-5">
        <h2 class="fw-bold mb-2"><?= e($pageTitle ?? 'Forgot Password'); ?></h2>
        <p class="text-muted mb-4">Submit your work email and we’ll send a password reset link. On local XAMPP, a preview link is shown below when mail delivery is not configured.</p>
        <form method="post" action="<?= e(url('/forgot-password')); ?>" novalidate>
            <?= csrf_field(); ?>
            <div class="mb-4">
                <label class="form-label">Work Email</label>
                <input type="email" name="email" class="form-control" value="<?= e((string) old('email')); ?>" required>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= e(url('/login')); ?>" class="btn btn-light w-50">Back to Login</a>
                <button type="submit" class="btn btn-primary w-50">Send Reset Link</button>
            </div>
        </form>
        <?php if ($previewLink !== null): ?>
            <div class="alert alert-warning mt-4 mb-0" role="alert">
                <div class="fw-semibold mb-1">Local reset link preview</div>
                <a href="<?= e((string) $previewLink); ?>" class="alert-link"><?= e((string) $previewLink); ?></a>
            </div>
        <?php endif; ?>
    </div>
</div>