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

    /**
     * Write a row to audit_logs. Non-fatal — exceptions are silently swallowed
     * so a logging failure never breaks the user-facing action.
     */
    protected function auditLog(
        string $module,
        string $entityType,
        ?int $entityId,
        string $action,
        string $ipAddress = '',
        string $userAgent = '',
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        try {
            $this->app->database()->execute(
                'INSERT INTO audit_logs
                    (user_id, module_name, entity_type, entity_id, action_name, old_values, new_values, ip_address, user_agent)
                 VALUES
                    (:user_id, :module_name, :entity_type, :entity_id, :action_name, :old_values, :new_values, :ip_address, :user_agent)',
                [
                    'user_id'      => $this->app->auth()->id(),
                    'module_name'  => $module,
                    'entity_type'  => $entityType,
                    'entity_id'    => $entityId,
                    'action_name'  => $action,
                    'old_values'   => $oldValues !== null ? json_encode($oldValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                    'new_values'   => $newValues !== null ? json_encode($newValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                    'ip_address'   => $ipAddress !== '' ? $ipAddress : null,
                    'user_agent'   => $userAgent !== '' ? substr($userAgent, 0, 255) : null,
                ]
            );
        } catch (\Throwable) {
            // never block the user-facing flow for a logging failure
        }
    }
}