<?php declare(strict_types=1); ?>
<?php require base_path('app/Views/partials/communications-nav.php'); ?>

<div class="card content-card mb-4">
    <div class="card-body p-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <h5 class="mb-1">Notification Center</h5>
            <p class="text-muted mb-0">Track system reminders, approvals, and communication updates tied to your account.</p>
        </div>
        <div class="mobile-action-group align-items-stretch">
            <span class="badge text-bg-danger fs-6 align-self-start align-self-md-center"><?= e((string) ($unreadCount ?? 0)); ?> unread</span>
            <form method="post" action="<?= e(url('/notifications/read-all')); ?>">
                <?= csrf_field(); ?>
                <button type="submit" class="btn btn-outline-secondary" <?= (int) ($unreadCount ?? 0) === 0 ? 'disabled' : ''; ?>>Mark All Read</button>
            </form>
        </div>
    </div>
</div>

<div class="card content-card">
    <div class="card-body p-4">
        <?php if (($notifications ?? []) === []): ?>
            <div class="empty-state">You do not have any notifications yet.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0 mobile-stack-table">
                    <thead>
                    <tr>
                        <th>Notification</th>
                        <th>Reference</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($notifications as $notification): ?>
                        <?php
                        $referenceType = ($notification['reference_type'] ?? null) !== null
                            ? ucwords(str_replace('_', ' ', (string) $notification['reference_type']))
                            : '-';
                        $referenceId = (($notification['reference_id'] ?? null) !== null && $notification['reference_id'] !== '')
                            ? ' #' . (string) $notification['reference_id']
                            : '';
                        ?>
                        <tr>
                            <td data-label="Notification">
                                <div class="fw-semibold"><?= e((string) $notification['title']); ?></div>
                                <div class="small text-muted"><?= e((string) $notification['message']); ?></div>
                                <div class="small text-muted mt-1">Type: <?= e(ucwords(str_replace('_', ' ', (string) $notification['notification_type']))); ?></div>
                            </td>
                            <td data-label="Reference"><?= e($referenceType); ?><?= e($referenceId); ?></td>
                            <td data-label="Status">
                                <span class="badge <?= (int) $notification['is_read'] === 1 ? 'text-bg-success' : 'text-bg-danger'; ?>">
                                    <?= e((string) ((int) $notification['is_read'] === 1 ? 'Read' : 'Unread')); ?>
                                </span>
                            </td>
                            <td data-label="Created"><?= e((string) $notification['created_at']); ?></td>
                            <td data-label="Actions" class="mobile-action-group">
                                <?php if (!empty($notification['action_url'])): ?>
                                    <a href="<?= e(url((string) $notification['action_url'])); ?>" class="btn btn-sm btn-outline-primary">Open</a>
                                <?php endif; ?>
                                <?php if ((int) $notification['is_read'] === 0): ?>
                                    <form method="post" action="<?= e(url('/notifications/' . $notification['id'] . '/read')); ?>">
                                        <?= csrf_field(); ?>
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">Mark Read</button>
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
