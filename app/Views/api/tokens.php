<?php declare(strict_types=1); ?>

<?php $newToken = flash('new_api_token'); ?>
<?php if ($newToken): ?>
<div class="alert alert-success alert-dismissible" role="alert">
    <strong>New API token created.</strong> Copy it now — it will <strong>not</strong> be shown again.<br>
    <code class="user-select-all fs-6"><?= e($newToken); ?></code>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card content-card">
            <div class="card-body p-4">
                <h5 class="mb-3"><i class="bi bi-key"></i> Create Token</h5>
                <form method="post" action="<?= e(url('/profile/api-tokens')); ?>">
                    <?= csrf_field(); ?>
                    <div class="mb-3">
                        <label class="form-label">Token Name *</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Mobile App, Integration" required maxlength="100">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Expires in</label>
                        <select name="expires_days" class="form-select">
                            <option value="0">Never</option>
                            <option value="30">30 days</option>
                            <option value="90">90 days</option>
                            <option value="180">6 months</option>
                            <option value="365">1 year</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i> Generate Token</button>
                </form>
            </div>
        </div>

        <div class="card content-card mt-3">
            <div class="card-body p-4">
                <h6 class="mb-2"><i class="bi bi-info-circle"></i> Usage</h6>
                <p class="small text-muted mb-2">Include the token in the <code>Authorization</code> header of every API request:</p>
                <pre class="bg-light p-2 rounded small">Authorization: Bearer &lt;your-token&gt;</pre>
                <p class="small text-muted mb-0">Base URL: <code><?= e(url('/api/v1')); ?></code></p>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card content-card">
            <div class="card-body p-4">
                <h5 class="mb-3"><i class="bi bi-list-ul"></i> Your Tokens</h5>
                <?php if (($tokens ?? []) === []): ?>
                    <div class="empty-state">No API tokens yet. Create one using the form.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                            <tr><th>Name</th><th>Last Used</th><th>Expires</th><th>Status</th><th></th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($tokens as $token): ?>
                                <tr>
                                    <td><?= e((string) $token['name']); ?></td>
                                    <td class="text-muted small"><?= $token['last_used_at'] ? e((string) $token['last_used_at']) : '—'; ?></td>
                                    <td class="text-muted small"><?= $token['expires_at'] ? e((string) $token['expires_at']) : 'Never'; ?></td>
                                    <td>
                                        <?php if ((int) $token['is_active'] === 1): ?>
                                            <span class="badge text-bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge text-bg-secondary">Revoked</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if ((int) $token['is_active'] === 1): ?>
                                        <form method="post" action="<?= e(url('/profile/api-tokens/' . $token['id'] . '/revoke')); ?>" onsubmit="return confirm('Revoke this token? Apps using it will lose access immediately.');">
                                            <?= csrf_field(); ?>
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Revoke</button>
                                        </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
