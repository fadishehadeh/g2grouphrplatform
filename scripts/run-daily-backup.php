<?php
/**
 * Daily backup generator for the resilience pack.
 *
 * Run from CLI: php scripts/run-daily-backup.php
 * Cron example: 0 2 * * * php /path/to/scripts/run-daily-backup.php >> /path/to/logs/backup.log 2>&1
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/Support/helpers.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

$app = new App\Core\Application(BASE_PATH);
$service = new App\Modules\Resilience\BackupService($app);

echo '[' . date('Y-m-d H:i:s') . "] Daily backup started.\n";

try {
    $result = $service->runDailyBackup(null, 'daily');
    $run = $result['run'] ?? null;
    $runId = is_array($run) ? (int) ($run['id'] ?? 0) : 0;
    $status = is_array($run) ? (string) ($run['status'] ?? 'unknown') : 'unknown';
    echo '[' . date('Y-m-d H:i:s') . "] Daily backup finished. Run #{$runId} status: {$status}\n";
    exit(0);
} catch (Throwable $throwable) {
    fwrite(STDERR, '[' . date('Y-m-d H:i:s') . '] Daily backup failed: ' . $throwable->getMessage() . "\n");
    exit(1);
}
