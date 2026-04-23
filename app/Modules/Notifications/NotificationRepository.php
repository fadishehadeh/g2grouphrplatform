<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Core\Database;
use App\Support\Branding;
use App\Support\EmailTemplate;
use App\Support\Mailer;
use Throwable;

final class NotificationRepository
{
    private Database $database;

    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    public function listForUser(int $userId): array
    {
        return $this->database->fetchAll(
            'SELECT id, notification_type, title, message, reference_type, reference_id, action_url, is_read, read_at, created_at
             FROM notifications
             WHERE user_id = :user_id
             ORDER BY is_read ASC, created_at DESC',
            ['user_id' => $userId]
        );
    }

    public function unreadCount(int $userId): int
    {
        return (int) ($this->database->fetchValue(
            'SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0',
            ['user_id' => $userId]
        ) ?? 0);
    }

    public function markRead(int $notificationId, int $userId): void
    {
        $this->database->execute(
            'UPDATE notifications
             SET is_read = 1, read_at = NOW()
             WHERE id = :notification_id AND user_id = :user_id',
            [
                'notification_id' => $notificationId,
                'user_id' => $userId,
            ]
        );
    }

    public function markAllRead(int $userId): void
    {
        $this->database->execute(
            'UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = :user_id AND is_read = 0',
            ['user_id' => $userId]
        );
    }

    /**
     * Create an in-app notification.
     */
    public function create(
        int $userId,
        string $type,
        string $title,
        string $message,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $actionUrl = null
    ): void {
        $this->database->execute(
            'INSERT INTO notifications (user_id, notification_type, title, message, reference_type, reference_id, action_url)
             VALUES (:user_id, :notification_type, :title, :message, :reference_type, :reference_id, :action_url)',
            [
                'user_id' => $userId,
                'notification_type' => $type,
                'title' => $title,
                'message' => $message,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'action_url' => $actionUrl,
            ]
        );
    }

    /**
     * Queue an email for later delivery.
     */
    public function queueEmail(
        string $toEmail,
        string $subject,
        string $bodyHtml,
        ?string $bodyText = null,
        ?int $userId = null,
        ?string $relatedType = null,
        ?int $relatedId = null
    ): void {
        $bodyHtml = EmailTemplate::notification(
            $subject,
            $bodyHtml,
            null,
            null,
            Branding::companyLogoUrlForUser($userId, $toEmail)
        );
        $bodyText ??= trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $bodyHtml)));

        $this->database->execute(
            'INSERT INTO email_queue (user_id, to_email, subject, body_html, body_text, related_type, related_id, scheduled_at)
             VALUES (:user_id, :to_email, :subject, :body_html, :body_text, :related_type, :related_id, NOW())',
            [
                'user_id' => $userId,
                'to_email' => $toEmail,
                'subject' => $subject,
                'body_html' => $bodyHtml,
                'body_text' => $bodyText,
                'related_type' => $relatedType,
                'related_id' => $relatedId,
            ]
        );
    }

    /**
     * Queue an email and try to send it immediately.
     */
    public function deliverEmail(
        string $toEmail,
        string $subject,
        string $bodyHtml,
        ?string $bodyText = null,
        ?int $userId = null,
        ?string $relatedType = null,
        ?int $relatedId = null
    ): void {
        $bodyHtml = EmailTemplate::notification(
            $subject,
            $bodyHtml,
            null,
            null,
            Branding::companyLogoUrlForUser($userId, $toEmail)
        );
        $bodyText ??= trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $bodyHtml)));

        $this->queueEmail($toEmail, $subject, $bodyHtml, $bodyText, $userId, $relatedType, $relatedId);

        $emailQueueId = (int) $this->database->lastInsertId();
        $mailConfig = (array) config('app.mail', []);

        if (!($mailConfig['enabled'] ?? false)) {
            return;
        }

        try {
            (new Mailer($mailConfig))->send($toEmail, $subject, $bodyHtml, $bodyText);

            $this->database->execute(
                'UPDATE email_queue
                 SET status = :status, sent_at = NOW(), attempts = attempts + 1, last_error = NULL
                 WHERE id = :id',
                ['status' => 'sent', 'id' => $emailQueueId]
            );
        } catch (Throwable $throwable) {
            $this->database->execute(
                'UPDATE email_queue
                 SET last_error = :last_error
                 WHERE id = :id',
                [
                    'last_error' => substr($throwable->getMessage(), 0, 500),
                    'id' => $emailQueueId,
                ]
            );
        }
    }

    /**
     * Look up the user's email address by user ID.
     */
    public function userEmail(int $userId): ?string
    {
        $email = $this->database->fetchValue(
            'SELECT email FROM users WHERE id = :id AND status = :status LIMIT 1',
            ['id' => $userId, 'status' => 'active']
        );

        return $email !== false && $email !== null ? (string) $email : null;
    }

    /**
     * Get all user IDs that hold a given role.
     */
    public function userIdsByRole(int $roleId): array
    {
        $rows = $this->database->fetchAll(
            'SELECT id FROM users WHERE role_id = :role_id AND status = :status',
            ['role_id' => $roleId, 'status' => 'active']
        );

        return array_column($rows, 'id');
    }
}
