<?php declare(strict_types=1); ?>
<div class="card border-0 shadow-sm auth-form-card">
    <div class="card-body p-4 p-md-5">
        <h2 class="fw-bold mb-2"><?= e($pageTitle ?? 'Reset Password'); ?></h2>
        <p class="text-muted mb-4">Create a strong password to activate your account and sign in.</p>
        <form method="post" action="<?= e(url('/reset-password')); ?>" novalidate>
            <?= csrf_field(); ?>
            <input type="hidden" name="token" value="<?= e((string) ($resetToken ?? '')); ?>">
            <div class="mb-3">
                <label class="form-label">New Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Confirm New Password</label>
                <input type="password" name="password_confirmation" class="form-control" required>
            </div>
            <p class="text-muted small mb-4"><?= e((string) ($passwordPolicy ?? '')); ?></p>
            <div class="d-flex gap-2">
                <a href="<?= e(url('/login')); ?>" class="btn btn-light w-50">Back to Login</a>
                <button type="submit" class="btn btn-primary w-50">Reset Password</button>
            </div>
        </form>
    </div>
</div>