<?php declare(strict_types=1); ?>
<div class="card border-0 shadow-sm auth-form-card">
    <div class="card-body p-4 p-md-5">
        <div class="text-center mb-4">
            <div class="mb-3">
                <span style="font-size:3rem;line-height:1">&#128274;</span>
            </div>
            <h2 class="fw-bold mb-1">Two-Factor Verification</h2>
            <p class="text-muted mb-0">A 6-digit code has been sent to your email address. Enter it below to complete sign-in.</p>
        </div>

        <form method="post" action="<?= e(url('/otp')); ?>" novalidate>
            <?= csrf_field(); ?>
            <div class="mb-4">
                <label class="form-label fw-semibold">Verification Code</label>
                <input type="text" name="otp" class="form-control form-control-lg text-center fw-bold"
                       inputmode="numeric" pattern="\d{6}" maxlength="6"
                       placeholder="000000" autofocus autocomplete="one-time-code" required
                       style="font-size:1.75rem;letter-spacing:.4em">
                <div class="form-text text-center mt-1">Code expires in 10 minutes.</div>
            </div>
            <button type="submit" class="btn btn-primary w-100">Verify &amp; Sign In</button>
        </form>

        <div class="text-center mt-3">
            <form method="post" action="<?= e(url('/otp/resend')); ?>" class="d-inline">
                <?= csrf_field(); ?>
                <button type="submit" class="btn btn-link btn-sm text-muted p-0">Didn't receive a code? Resend</button>
            </form>
        </div>

        <div class="text-center mt-2">
            <a href="<?= e(url('/login')); ?>" class="small text-muted">Back to login</a>
        </div>

    </div>
</div>
