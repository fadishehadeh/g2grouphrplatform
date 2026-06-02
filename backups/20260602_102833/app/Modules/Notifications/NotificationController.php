<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Core\Application;
use App\Core\Controller;
use App\Core\Request;
use Throwable;

final class NotificationController extends Controller
{
    private NotificationRepository $repository;

    public function __construct(Application $app)
    {
        parent::__construct($app);
        $this->repository = new NotificationRepository($this->app->database());
    }

    public function index(Request $request): void
    {
        $notifications = [];
        $unreadCount = 0;
        $userId = (int) ($this->app->auth()->id() ?? 0);

        try {
            $notifications = $this->repository->listForUser($userId);
            $unreadCount = $this->repository->unreadCount($userId);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load notifications: ' . $throwable->getMessage());
        }

        $this->render('notifications.index', [
            'title' => 'Notifications',
            'pageTitle' => 'Notifications',
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
        ]);
    }

    public function markRead(Request $request, string $id): void
    {
        $redirectPath = '/notifications';
        $this->validateCsrf($request, $redirectPath);

        try {
            $this->repository->markRead((int) $id, (int) ($this->app->auth()->id() ?? 0));
            $this->app->session()->flash('success', 'Notification marked as read.');
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to update notification: ' . $throwable->getMessage());
        }

        $this->redirect($redirectPath);
    }

    public function markAll(Request $request): void
    {
        $redirectPath = '/notifications';
        $this->validateCsrf($request, $redirectPath);

        try {
            $this->repository->markAllRead((int) ($this->app->auth()->id() ?? 0));
            $this->app->session()->flash('success', 'All notifications marked as read.');
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to update notifications: ' . $throwable->getMessage());
        }

        $this->redirect($redirectPath);
    }

    private function validateCsrf(Request $request, string $redirectPath): void
    {
        if (!$this->app->csrf()->validate((string) $request->input('_token'))) {
            $this->app->session()->flash('error', 'Invalid form submission token.');
            $this->redirect($redirectPath);
        }
    }
}