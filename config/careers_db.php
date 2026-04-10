<?php

declare(strict_types=1);

return [
    'driver'    => 'mysql',
    'host'      => env('CAREERS_DB_HOST',      env('DB_HOST',     '127.0.0.1')),
    'port'      => env('CAREERS_DB_PORT',      env('DB_PORT',     '3306')),
    'database'  => env('CAREERS_DB_DATABASE',  'hr_careers'),
    'username'  => env('CAREERS_DB_USERNAME',  env('DB_USERNAME', 'root')),
    'password'  => env('CAREERS_DB_PASSWORD',  env('DB_PASSWORD', '')),
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
];
