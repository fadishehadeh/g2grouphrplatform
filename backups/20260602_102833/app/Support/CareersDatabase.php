<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Database;

/**
 * Singleton wrapper that returns a Database instance connected to hr_careers.
 * Completely separate from the main HR Database object.
 */
final class CareersDatabase
{
    private static ?Database $instance = null;

    public static function get(): Database
    {
        if (self::$instance === null) {
            $config = require base_path('config/careers_db.php');
            self::$instance = new Database($config);
        }

        return self::$instance;
    }

    // Non-instantiable
    private function __construct() {}
}
