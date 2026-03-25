<?php declare(strict_types=1); ?>
<?php if (!empty($announcements)): ?>
<div class="card content-card mb-4">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0"><i class="bi bi-megaphone"></i> Latest Announcements</h5>
            <a href="<?= e(url('/announcements')); ?>" class="btn btn-outline-primary btn-sm">View All</a>
        </div>
        <div class="list-group list-group-flush">
            <?php foreach ($announcements as $ann): ?>
                <?php $pClass = match ((string) ($ann['priority'] ?? 'normal')) { 'urgent' => 'text-bg-danger', 'high' => 'text-bg-warning', 'low' => 'text-bg-secondary', default => 'text-bg-primary' }; ?>
                <a href="<?= e(url('/announcements/' . (int) $ann['id'])); ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-start py-3" style="text-decoration:none;">
                    <div class="me-auto">
                        <div class="fw-semibold"><?= e((string) $ann['title']); ?></div>
                        <small class="text-muted">By <?= e((string) ($ann['created_by_name'] ?? 'System')); ?> &bull; <?= e(date('M j, Y', strtotime((string) $ann['created_at']))); ?></small>
                    </div>
                    <span class="badge <?= e($pClass); ?> ms-2"><?= e(ucfirst((string) ($ann['priority'] ?? 'normal'))); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

