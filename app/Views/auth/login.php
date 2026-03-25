<?php declare(strict_types=1); ?>
<div class="card border-0 shadow-sm auth-form-card">
    <div class="card-body p-4 p-md-5">
        <h2 class="fw-bold mb-2"><?= e($pageTitle ?? 'Login'); ?></h2>
        <p class="text-muted mb-4">Use your assigned username or work email to access the system.</p>
        <form method="post" action="<?= e(url('/login')); ?>" novalidate>
            <?= csrf_field(); ?>
            <div class="mb-3">
                <label class="form-label">Username or Email</label>
                <input type="text" name="login" class="form-control" value="<?= e((string) old('login')); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="form-text">Secure session with role-based access control.</div>
                <a href="<?= e(url('/forgot-password')); ?>" class="small">Forgot password?</a>
            </div>
            <button type="submit" class="btn btn-primary w-100">Sign In</button>
        </form>
    </div>
</div>