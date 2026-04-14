<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/Support/helpers.php';

if (is_file(BASE_PATH . '/vendor/autoload.php')) {
    require BASE_PATH . '/vendor/autoload.php';
}

// Override per-subdomain settings after .env is loaded
$_ENV['APP_URL']      = 'https://careers.greydoha.com';
$_SERVER['APP_URL']   = 'https://careers.greydoha.com';
putenv('APP_URL=https://careers.greydoha.com');

$_ENV['SESSION_NAME']    = 'careers_session';
$_SERVER['SESSION_NAME'] = 'careers_session';
putenv('SESSION_NAME=careers_session');

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

require BASE_PATH . '/routes/careers.php';

$app->run();
