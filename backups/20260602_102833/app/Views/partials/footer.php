<?php declare(strict_types=1); ?>
<footer class="app-footer">
    <span>&copy; <?= e(date('Y')); ?> <?= e((string) config('app.brand.display_name', config('app.name'))); ?></span>
    <span class="text-muted">Secure HR operations portal</span>
</footer>