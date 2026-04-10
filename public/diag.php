<?php
$results = [];

// Check DB
try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306', 'root', '');
    $dbs = $pdo->query('SHOW DATABASES')->fetchAll(PDO::FETCH_COLUMN);
    $results['databases'] = $dbs;
    $results['hr_system_exists'] = in_array('hr_system', $dbs);
    if ($results['hr_system_exists']) {
        $pdo2 = new PDO('mysql:host=127.0.0.1;port=3306;dbname=hr_system', 'root', '');
        $tables = $pdo2->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $results['tables_count'] = count($tables);
        $results['tables'] = $tables;
    }
} catch (Exception $e) {
    $results['db_error'] = $e->getMessage();
}

// Check APP_URL
$envFile = file_get_contents(__DIR__ . '/../.env');
preg_match('/APP_URL=(.+)/', $envFile, $m);
$results['app_url'] = trim($m[1] ?? 'not found');

// Check REQUEST_URI path stripping
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = rawurldecode(parse_url($uri, PHP_URL_PATH) ?: '/');
$basePath = rawurldecode(parse_url($results['app_url'], PHP_URL_PATH) ?: '');
$results['request_uri'] = $uri;
$results['decoded_path'] = $path;
$results['base_path'] = $basePath;
$results['starts_with'] = str_starts_with($path, $basePath);
$results['stripped_path'] = str_starts_with($path, $basePath) ? (substr($path, strlen($basePath)) ?: '/') : 'NOT STRIPPED';

// Write to file for easy reading
file_put_contents(__DIR__ . '/diag_output.txt', print_r($results, true));
header('Content-Type: text/plain');
print_r($results);
