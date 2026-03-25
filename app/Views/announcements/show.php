<?php declare(strict_types=1); ?>
<?php require base_path('app/Views/partials/communications-nav.php'); ?>
<?php $priorityClass = match ((string) ($announcement['priority'] ?? 'normal')) { 'urgent' => 'text-bg-danger', 'high' => 'text-bg-warning', 'low' => 'text-bg-secondary', default => 'text-bg-primary' }; $statusClass = match ((string) ($announcement['status'] ?? 'draft')) { 'published' => 'text-bg-success', 'archived' => 'text-bg-dark', default => 'text-bg-secondary' }; ?>
<div class="card content-card">
    <div class="card-body p-4">
        <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-4">
            <div>
                <div class="d-flex flex-wrap gap-2 mb-2">
                    <span class="badge <?= e($priorityClass); ?>"><?= e(ucfirst((string) ($announcement['priority'] ?? 'normal'))); ?></span>
                    <span class="badge <?= e($statusClass); ?>"><?= e(ucfirst((string) ($announcement['status'] ?? 'draft'))); ?></span>
                    <span class="badge <?= (int) ($announcement['is_read'] ?? 0) === 1 ? 'text-bg-success' : 'text-bg-light'; ?>"><?= e((string) ((int) ($announcement['is_read'] ?? 0) === 1 ? 'Marked Read' : 'Unread')); ?></span>
                </div>
                <h4 class="mb-1"><?= e((string) ($announcement['title'] ?? 'Announcement')); ?></h4>
                <p class="text-muted mb-0">Published by <?= e((string) ($announcement['created_by_name'] ?? 'System')); ?> • <?= e((string) ($announcement['created_at'] ?? '')); ?></p>
            </div>
            <div class="text-md-end small text-muted">
                <div>Audience: <?= e((string) ($announcement['target_summary'] ?? 'All Employees')); ?></div>
                <div>Starts: <?= e((string) (($announcement['starts_at'] ?? null) !== null ? $announcement['starts_at'] : 'Immediate')); ?></div>
                <div>Ends: <?= e((string) (($announcement['ends_at'] ?? null) !== null ? $announcement['ends_at'] : 'Open ended')); ?></div>
            </div>
        </div>
        <div class="border rounded p-4 bg-light-subtle">
            <?= nl2br(e((string) ($announcement['content'] ?? ''))); ?>
        </div>

        <?php if (!empty($links)): ?>
        <div class="mt-4">
            <h6><i class="bi bi-link-45deg"></i> Links</h6>
            <ul class="list-unstyled mb-0">
                <?php foreach ($links as $link): ?>
                    <li class="mb-1"><a href="<?= e((string) $link['url']); ?>" target="_blank" rel="noopener noreferrer"><i class="bi bi-box-arrow-up-right"></i> <?= e((string) $link['label']); ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if (!empty($attachments)): ?>
        <div class="mt-4">
            <h6><i class="bi bi-paperclip"></i> Attachments</h6>
            <ul class="list-unstyled mb-0">
                <?php foreach ($attachments as $att): ?>
                    <li class="mb-1">
                        <a href="<?= e(url('/announcements/attachments/' . (int) $att['id'])); ?>"><i class="bi bi-download"></i> <?= e((string) $att['original_name']); ?></a>
                        <span class="text-muted small">(<?= e(number_format(((int) ($att['file_size'] ?? 0)) / 1024, 1)); ?> KB)</span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <div class="mt-4 d-flex gap-2">
            <a href="<?= e(url('/announcements')); ?>" class="btn btn-outline-secondary">Back to Announcements</a>
            <?php if (($canManage ?? false) === true): ?><span class="text-muted small align-self-center">This record is visible according to its publication status, schedule window, and audience target.</span><?php endif; ?>
        </div>
    </div>
</div>