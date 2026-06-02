<?php

declare(strict_types=1);

namespace App\Core;

final class View
{
    public static function render(string $template, array $data = [], ?string $layout = 'app'): void
    {
        $viewFile = base_path('app/Views/' . str_replace('.', '/', $template) . '.php');

        if (!is_file($viewFile)) {
            throw new \RuntimeException(sprintf('View [%s] not found.', $template));
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $viewFile;
        $content = (string) ob_get_clean();

        if ($layout === null) {
            echo $content;

            return;
        }

        $layoutFile = base_path('app/Views/layouts/' . $layout . '.php');

        if (!is_file($layoutFile)) {
            throw new \RuntimeException(sprintf('Layout [%s] not found.', $layout));
        }

        require $layoutFile;
    }
}