<?php declare(strict_types=1); ?>
<div class="row justify-content-center">
    <div class="col-lg-6">

        <div class="card content-card mb-4">
            <div class="card-body p-4">
                <h5 class="mb-1">Account Information</h5>
                <p class="text-muted mb-4">Your account details and role.</p>
                <table class="table table-borderless mb-0">
                    <tbody>
                        <tr>
                            <th class="text-muted fw-normal ps-0" style="width:40%">Name</th>
                            <td><?= e(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')); ?></td>
                        </tr>
                        <tr>
                            <th class="text-muted fw-normal ps-0">Username</th>
                            <td><?= e($user['username'] ?? ''); ?></td>
                        </tr>
                        <tr>
                            <th class="text-muted fw-normal ps-0">Email</th>
                            <td><?= e($user['email'] ?? ''); ?></td>
                        </tr>
                        <tr>
                            <th class="text-muted fw-normal ps-0">Role</th>
                            <td><span class="badge text-bg-primary"><?= e($user['role_name'] ?? ''); ?></span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card content-card">
            <div class="card-body p-4">
                <h5 class="mb-1">Change Password</h5>
                <p class="text-muted mb-4"><?= e($passwordPolicy); ?></p>
                <form method="post" action="<?= e(url('/profile/change-password')); ?>">
                    <?= csrf_field(); ?>
                    <div class="mb-3">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-control" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="new_password_confirmation" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Update Password</button>
                </form>
            </div>
        </div>

    </div>
</div>
