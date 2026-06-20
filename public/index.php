<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/Support/helpers.php';

// Set APP_URL dynamically for local development
(static function () {
    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir    = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
    // Encode each path segment so spaces become %20 (keeps slashes intact)
    $dir    = implode('/', array_map('rawurlencode', explode('/', $dir)));
    $url    = $scheme . '://' . $host . $dir;
    $_ENV['APP_URL']    = $url;
    $_SERVER['APP_URL'] = $url;
    putenv('APP_URL=' . $url);
})();

require BASE_PATH . '/vendor/autoload.php';

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
require BASE_PATH . '/routes/intake.php';
require BASE_PATH . '/routes/api.php';
require BASE_PATH . '/routes/resilience.php';
require BASE_PATH . '/routes/payroll.php';

$app->run();
