<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/Support/helpers.php';

if (is_file(BASE_PATH . '/vendor/autoload.php')) {
    require BASE_PATH . '/vendor/autoload.php';
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

$app = new App\Core\Application(BASE_PATH);

require BASE_PATH . '/routes/auth.php';
require BASE_PATH . '/routes/web.php';
require BASE_PATH . '/routes/admin.php';
require BASE_PATH . '/routes/structure.php';
require BASE_PATH . '/routes/employees.php';
require BASE_PATH . '/routes/leaves.php';
require BASE_PATH . '/routes/documents.php';
require BASE_PATH . '/routes/onboarding.php';
require BASE_PATH . '/routes/offboarding.php';
require BASE_PATH . '/routes/announcements.php';
require BASE_PATH . '/routes/reports.php';
require BASE_PATH . '/routes/settings.php';
require BASE_PATH . '/routes/letters.php';
require BASE_PATH . '/routes/careers.php';
require BASE_PATH . '/routes/jobs.php';
require BASE_PATH . '/routes/api.php';

$app->run();