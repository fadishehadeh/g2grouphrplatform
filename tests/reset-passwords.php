<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/Support/helpers.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) return;
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) require $file;
});

$dbConfig = require BASE_PATH . '/config/database.php';
$db = new App\Core\Database($dbConfig);
$pw = 'admin@123';
$hash = password_hash($pw, PASSWORD_DEFAULT);

foreach (['admin', 'fadi.chehade', 'shehryar.masoom'] as $user) {
    $db->execute(
        'UPDATE users SET password_hash = :h, must_change_password = 0 WHERE username = :u',
        ['h' => $hash, 'u' => $user]
    );
    echo "Reset password for {$user}\n";
}

echo "All passwords set to: {$pw}\n";
echo "Hash: {$hash}\n";

