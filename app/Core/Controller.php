<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    protected function render(string $template, array $data = [], ?string $layout = 'app'): void
    {
        View::render($template, $data, $layout);
    }

    protected function redirect(string $path): never
    {
        Response::redirect($path);
    }
}