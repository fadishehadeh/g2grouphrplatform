<?php declare(strict_types=1); ?>
<div class="careers-hero">
    <div class="container" style="max-width:480px">
        <h1 class="mb-1"><i class="bi bi-shield-check me-2"></i>Verify Your Identity</h1>
        <p class="mb-0 opacity-75">We sent a 6-digit code to your email — enter it below.</p>
    </div>
</div>

<div class="container py-5" style="max-width:480px">
    <div class="section-card p-4 p-md-5">

        <div class="alert alert-info d-flex align-items-start gap-2 mb-4">
            <i class="bi bi-info-circle-fill mt-1"></i>
            <div>
                Check your inbox for a <strong>6-digit OTP code</strong>. It expires in <strong>10 minutes</strong>.
                If you don't see it, check your spam folder.
            </div>
        </div>

        <form method="post" action="<?= e(url('/careers/otp')); ?>">
            <?= csrf_field(); ?>
            <div class="mb-4">
                <label class="form-label fw-semibold">Enter 6-Digit Code *</label>
                <input type="text" name="otp" class="form-control form-control-lg text-center fw-bold"
                       placeholder="000000" maxlength="6" pattern="\d{6}" inputmode="numeric" autofocus required
                       style="letter-spacing: 8px; font-size: 1.6rem;">
            </div>
            <button type="submit" class="btn btn-danger w-100 fw-semibold py-2">Verify Code</button>
        </form>

        <hr class="my-4">

        <form method="post" action="<?= e(url('/careers/otp/resend')); ?>">
            <?= csrf_field(); ?>
            <p class="text-center text-muted small mb-2">Didn't receive the code?</p>
            <button type="submit" class="btn btn-outline-secondary w-100 btn-sm">
                <i class="bi bi-arrow-clockwise me-1"></i> Resend Code (60s cooldown)
            </button>
        </form>

        <p class="text-center mt-3 mb-0 text-muted small">
            <a href="<?= e(url('/careers/login')); ?>">← Back to login</a>
        </p>
    </div>
</div>
