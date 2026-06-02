<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    public static function redirect(string $path, int $status = 302): never
    {
        header('Location: ' . url($path), true, $status);
        exit;
    }

    public static function abort(int $status, string $message = ''): never
    {
        http_response_code($status);

        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>'
            . $status
            . '</title><style>body{font-family:Arial,sans-serif;padding:40px;background:#f8fafc;color:#1e293b}h1{margin-bottom:8px}</style></head><body>';
        echo '<h1>' . e((string) $status) . '</h1>';
        echo '<p>' . e($message !== '' ? $message : 'An unexpected error occurred.') . '</p>';
        echo '</body></html>';
        exit;
    }
}