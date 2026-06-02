<?php

declare(strict_types=1);

namespace App\Modules\Announcements;

use App\Core\Application;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Notifications\NotificationRepository;
use DateTimeImmutable;
use Throwable;

final class AnnouncementController extends Controller
{
    private AnnouncementRepository $repository;

    public function __construct(Application $app)
    {
        parent::__construct($app);
        $this->repository = new AnnouncementRepository($this->app->database());
    }

    public function index(Request $request): void
    {
        $canManage = $this->app->auth()->hasPermission('announcements.manage');
        $search = trim((string) $request->input('q', ''));
        $status = (string) $request->input('status', $canManage ? 'all' : 'published');

        if (!in_array($status, ['all', 'draft', 'published', 'archived'], true)) {
            $status = $canManage ? 'all' : 'published';
        }

        $announcements = [];
        $targetOptions = ['roles' => [], 'branches' => [], 'departments' => [], 'employees' => []];

        try {
            $announcements = $this->repository->listAnnouncements(
                $this->app->auth()->user() ?? [],
                $search,
                $status,
                $canManage
            );

            if ($canManage) {
                $targetOptions = $this->repository->targetOptions();
            }
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load announcements: ' . $throwable->getMessage());
        }

        $this->render('announcements.index', [
            'title' => 'Announcements',
            'pageTitle' => 'Announcements',
            'announcements' => $announcements,
            'targetOptions' => $targetOptions,
            'search' => $search,
            'status' => $status,
            'canManage' => $canManage,
        ]);
    }

    public function show(Request $request, string $id): void
    {
        $announcementId = (int) $id;
        $canManage = $this->app->auth()->hasPermission('announcements.manage');

        try {
            $announcement = $this->repository->findAnnouncement($announcementId, $this->app->auth()->user() ?? [], $canManage);

            if ($announcement === null) {
                Response::abort(404, 'Announcement not found.');
            }

            $userId = $this->app->auth()->id();

            if ($userId !== null && (int) ($announcement['is_read'] ?? 0) === 0) {
                $this->repository->markRead($announcementId, $userId);
                $announcement['is_read'] = 1;
            }
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load announcement: ' . $throwable->getMessage());
            $this->redirect('/announcements');
        }

        $links = [];
        $attachments = [];

        try {
            $links = $this->repository->announcementLinks($announcementId);
            $attachments = $this->repository->announcementAttachments($announcementId);
        } catch (Throwable $ignored) {
        }

        $emailsAlreadySent = false;

        try {
            $emailsAlreadySent = $this->repository->emailsAlreadySent($announcementId);
        } catch (Throwable $ignored) {
        }

        $this->render('announcements.show', [
            'title' => 'Announcement Details',
            'pageTitle' => 'Announcement Details',
            'announcement' => $announcement,
            'canManage' => $canManage,
            'links' => $links,
            'attachments' => $attachments,
            'emailsAlreadySent' => $emailsAlreadySent,
        ]);
    }

    public function store(Request $request): void
    {
        $redirectPath = '/announcements';
        $this->validateCsrf($request, $redirectPath);
        $data = $this->sanitized($request);

        if (($data['title'] ?? '') === '' || ($data['content'] ?? '') === '') {
            $this->invalid($redirectPath, $data, 'Please provide both a title and announcement content.');
        }

        if (!in_array((string) ($data['priority'] ?? ''), ['low', 'normal', 'high', 'urgent'], true)) {
            $this->invalid($redirectPath, $data, 'Please choose a valid announcement priority.');
        }

        if (!in_array((string) ($data['status'] ?? ''), ['draft', 'published', 'archived'], true)) {
            $this->invalid($redirectPath, $data, 'Please choose a valid announcement status.');
        }

        $targetType = (string) ($data['target_type'] ?? 'all');

        if (!in_array($targetType, ['all', 'role', 'department', 'branch', 'employee'], true)) {
            $this->invalid($redirectPath, $data, 'Please choose a valid announcement audience.');
        }

        $data['target_id'] = $this->selectedTargetId($targetType, $data);

        if ($targetType !== 'all' && (int) ($data['target_id'] ?? 0) <= 0) {
            $this->invalid($redirectPath, $data, 'Please choose the matching audience item for the selected target type.');
        }

        $startsAt = $this->validatedDateTime($data['starts_at'] ?? null, 'start date/time', $redirectPath, $data, false);
        $endsAt = $this->validatedDateTime($data['ends_at'] ?? null, 'end date/time', $redirectPath, $data, false);
        $emailSendAt = $this->validatedDateTime($data['email_send_at'] ?? null, 'email send time', $redirectPath, $data, false);

        if ($startsAt !== null && $endsAt !== null && $endsAt < $startsAt) {
            $this->invalid($redirectPath, $data, 'End date/time cannot be earlier than the start date/time.');
        }

        $data['starts_at'] = $startsAt?->format('Y-m-d H:i:s');
        $data['ends_at'] = $endsAt?->format('Y-m-d H:i:s');
        $data['email_send_at'] = $emailSendAt?->format('Y-m-d H:i:s');

        try {
            $announcementId = $this->repository->createAnnouncement($data, $this->app->auth()->id());

            // Save links
            $linkLabels = $request->input('link_label');
            $linkUrls = $request->input('link_url');
            if (is_array($linkLabels) && is_array($linkUrls)) {
                foreach ($linkLabels as $i => $label) {
                    $label = trim((string) $label);
                    $linkUrl = trim((string) ($linkUrls[$i] ?? ''));
                    if ($label !== '' && $linkUrl !== '') {
                        $this->repository->addLink($announcementId, $label, $linkUrl, $i);
                    }
                }
            }

            // Save uploaded attachments
            $this->handleAttachmentUploads($announcementId);

            // Send/queue emails when published — immediately if no schedule, or deferred if future schedule
            if (($data['status'] ?? '') === 'published' && (bool) config('app.mail.enabled', false)) {
                $sendNow = $emailSendAt === null || $emailSendAt <= new DateTimeImmutable();
                $this->notifyAnnouncementRecipients($announcementId, $data, $sendNow);
            }

            $this->app->session()->flash('success', 'Announcement saved successfully.');
            $this->redirect('/announcements/' . $announcementId);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to save the announcement right now. ' . $throwable->getMessage());
            $this->app->session()->flash('old_input', $data);
            $this->redirect($redirectPath);
        }
    }

    public function edit(Request $request, string $id): void
    {
        $announcementId = (int) $id;
        $canManage = $this->app->auth()->hasPermission('announcements.manage');

        if (!$canManage) {
            Response::abort(403, 'You do not have permission to edit announcements.');
        }

        try {
            $announcement = $this->repository->findAnnouncement($announcementId, $this->app->auth()->user() ?? [], true);

            if ($announcement === null) {
                Response::abort(404, 'Announcement not found.');
            }
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to load announcement: ' . $throwable->getMessage());
            $this->redirect('/announcements');
        }

        $targetOptions = ['roles' => [], 'branches' => [], 'departments' => [], 'employees' => []];
        $currentTarget = null;

        try {
            $targetOptions = $this->repository->targetOptions();
            $currentTarget = $this->repository->announcementFirstTarget($announcementId);
        } catch (Throwable $ignored) {
        }

        $this->render('announcements.edit', [
            'title' => 'Edit Announcement',
            'pageTitle' => 'Edit Announcement',
            'announcement' => $announcement,
            'targetOptions' => $targetOptions,
            'currentTarget' => $currentTarget,
        ]);
    }

    public function update(Request $request, string $id): void
    {
        $announcementId = (int) $id;
        $redirectPath = '/announcements/' . $announcementId . '/edit';
        $canManage = $this->app->auth()->hasPermission('announcements.manage');

        if (!$canManage) {
            Response::abort(403, 'You do not have permission to edit announcements.');
        }

        $this->validateCsrf($request, $redirectPath);
        $data = $this->sanitized($request);

        if (($data['title'] ?? '') === '' || ($data['content'] ?? '') === '') {
            $this->invalid($redirectPath, $data, 'Please provide both a title and announcement content.');
        }

        if (!in_array((string) ($data['priority'] ?? ''), ['low', 'normal', 'high', 'urgent'], true)) {
            $this->invalid($redirectPath, $data, 'Please choose a valid announcement priority.');
        }

        if (!in_array((string) ($data['status'] ?? ''), ['draft', 'published', 'archived'], true)) {
            $this->invalid($redirectPath, $data, 'Please choose a valid announcement status.');
        }

        $targetType = (string) ($data['target_type'] ?? 'all');

        if (!in_array($targetType, ['all', 'role', 'department', 'branch', 'employee'], true)) {
            $this->invalid($redirectPath, $data, 'Please choose a valid announcement audience.');
        }

        $data['target_id'] = $this->selectedTargetId($targetType, $data);

        if ($targetType !== 'all' && (int) ($data['target_id'] ?? 0) <= 0) {
            $this->invalid($redirectPath, $data, 'Please choose the matching audience item for the selected target type.');
        }

        $startsAt = $this->validatedDateTime($data['starts_at'] ?? null, 'start date/time', $redirectPath, $data, false);
        $endsAt = $this->validatedDateTime($data['ends_at'] ?? null, 'end date/time', $redirectPath, $data, false);
        $emailSendAt = $this->validatedDateTime($data['email_send_at'] ?? null, 'email send time', $redirectPath, $data, false);

        if ($startsAt !== null && $endsAt !== null && $endsAt < $startsAt) {
            $this->invalid($redirectPath, $data, 'End date/time cannot be earlier than the start date/time.');
        }

        $data['starts_at'] = $startsAt?->format('Y-m-d H:i:s');
        $data['ends_at'] = $endsAt?->format('Y-m-d H:i:s');
        $data['email_send_at'] = $emailSendAt?->format('Y-m-d H:i:s');

        try {
            $existing = $this->repository->findAnnouncement($announcementId, $this->app->auth()->user() ?? [], true);

            if ($existing === null) {
                Response::abort(404, 'Announcement not found.');
            }

            $emailsAlreadySent = $this->repository->emailsAlreadySent($announcementId);

            $this->repository->updateAnnouncement($announcementId, $data, $this->app->auth()->id());

            // Send emails if now published AND emails not yet sent
            $isNowPublished = ($data['status'] ?? '') === 'published';
            if ($isNowPublished && !$emailsAlreadySent && (bool) config('app.mail.enabled', false)) {
                $sendNow = $emailSendAt === null || $emailSendAt <= new DateTimeImmutable();
                $this->notifyAnnouncementRecipients($announcementId, $data, $sendNow);
            }

            $this->app->session()->flash('success', 'Announcement updated successfully.');
            $this->redirect('/announcements/' . $announcementId);
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to update the announcement. ' . $throwable->getMessage());
            $this->app->session()->flash('old_input', $data);
            $this->redirect($redirectPath);
        }
    }

    public function sendEmails(Request $request, string $id): void
    {
        $announcementId = (int) $id;
        $canManage = $this->app->auth()->hasPermission('announcements.manage');

        if (!$canManage) {
            Response::abort(403, 'You do not have permission to send announcement emails.');
        }

        if (!$this->app->csrf()->validate((string) $request->input('_token'))) {
            $this->app->session()->flash('error', 'Invalid form submission token.');
            $this->redirect('/announcements/' . $announcementId);
        }

        try {
            $announcement = $this->repository->findAnnouncement($announcementId, $this->app->auth()->user() ?? [], true);

            if ($announcement === null) {
                Response::abort(404, 'Announcement not found.');
            }

            $this->notifyAnnouncementRecipients($announcementId, $announcement, true);
            $this->app->session()->flash('success', 'Emails have been sent to all targeted recipients.');
        } catch (Throwable $throwable) {
            $this->app->session()->flash('error', 'Unable to send emails: ' . $throwable->getMessage());
        }

        $this->redirect('/announcements/' . $announcementId);
    }

    private function validateCsrf(Request $request, string $redirectPath): void
    {
        if (!$this->app->csrf()->validate((string) $request->input('_token'))) {
            $this->app->session()->flash('error', 'Invalid form submission token.');
            $this->redirect($redirectPath);
        }
    }

    private function sanitized(Request $request): array
    {
        $data = [];

        foreach ($request->all() as $key => $value) {
            if ($key === '_token') {
                continue;
            }

            $data[$key] = is_string($value) ? trim($value) : $value;
        }

        return $data;
    }

    private function selectedTargetId(string $targetType, array $data): ?int
    {
        return match ($targetType) {
            'role' => $this->nullableInt($data['role_target_id'] ?? null),
            'department' => $this->nullableInt($data['department_target_id'] ?? null),
            'branch' => $this->nullableInt($data['branch_target_id'] ?? null),
            'employee' => $this->nullableInt($data['employee_target_id'] ?? null),
            default => null,
        };
    }

    private function validatedDateTime(mixed $value, string $label, string $redirectPath, array $data, bool $required): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            if ($required) {
                $this->invalid($redirectPath, $data, 'Please provide a valid ' . $label . '.');
            }

            return null;
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', (string) $value);

        if (!$date instanceof DateTimeImmutable || $date->format('Y-m-d\TH:i') !== (string) $value) {
            $this->invalid($redirectPath, $data, 'Please provide a valid ' . $label . '.');
        }

        return $date;
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private function invalid(string $redirectPath, array $data, string $message): never
    {
        $this->app->session()->flash('error', $message);
        $this->app->session()->flash('old_input', $data);
        $this->redirect($redirectPath);
    }

    private function handleAttachmentUploads(int $announcementId): void
    {
        $files = $_FILES['attachments'] ?? null;
        if ($files === null || !is_array($files['name'] ?? null)) {
            return;
        }

        $uploadDir = base_path('storage/announcements');
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $allowedMimes = [
            'application/pdf', 'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'text/plain', 'text/csv',
            'application/zip', 'application/x-rar-compressed',
        ];

        $maxSize = 10 * 1024 * 1024; // 10 MB

        foreach ($files['name'] as $i => $originalName) {
            if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }

            $tmpPath = (string) ($files['tmp_name'][$i] ?? '');
            $originalName = (string) $originalName;
            $mimeType = (string) ($files['type'][$i] ?? 'application/octet-stream');
            $fileSize = (int) ($files['size'][$i] ?? 0);

            if ($fileSize > $maxSize || !in_array($mimeType, $allowedMimes, true)) {
                continue;
            }

            $ext = pathinfo($originalName, PATHINFO_EXTENSION);
            $storedName = 'ann_' . $announcementId . '_' . bin2hex(random_bytes(8)) . ($ext !== '' ? '.' . $ext : '');
            $destination = $uploadDir . '/' . $storedName;

            if (move_uploaded_file($tmpPath, $destination)) {
                $this->repository->addAttachment($announcementId, $originalName, $storedName, $mimeType, $fileSize);
            }
        }
    }

    public function downloadAttachment(Request $request, string $id): void
    {
        $attachmentId = (int) $id;

        try {
            $attachment = $this->repository->findAttachment($attachmentId);

            if ($attachment === null) {
                Response::abort(404, 'Attachment not found.');
            }

            $filePath = base_path('storage/announcements/' . $attachment['stored_name']);

            if (!is_file($filePath)) {
                Response::abort(404, 'File not found on disk.');
            }

            header('Content-Type: ' . ($attachment['mime_type'] ?? 'application/octet-stream'));
            header('Content-Disposition: attachment; filename="' . str_replace('"', '', (string) $attachment['original_name']) . '"');
            header('Content-Length: ' . filesize($filePath));
            readfile($filePath);
            exit;
        } catch (Throwable $throwable) {
            Response::abort(500, 'Unable to download attachment: ' . $throwable->getMessage());
        }
    }

    /**
     * Queue (and optionally send immediately) notification emails to all targeted recipients.
     *
     * @param bool $sendNow true = deliver immediately; false = queue only (deferred)
     */
    private function notifyAnnouncementRecipients(int $announcementId, array $data, bool $sendNow = true): void
    {
        try {
            $recipients = $this->repository->targetedRecipients($announcementId);

            if ($recipients === []) {
                return;
            }

            $notifications = new NotificationRepository($this->app->database());
            $appName = (string) config('app.name', 'HR Management System');
            $title = (string) ($data['title'] ?? 'New Announcement');
            $content = (string) ($data['content'] ?? '');
            $priority = ucfirst((string) ($data['priority'] ?? 'normal'));
            $appUrl = rtrim((string) config('app.url', ''), '/');

            $priorityColors = ['Low' => '#198754', 'Normal' => '#0d6efd', 'High' => '#fd7e14', 'Urgent' => '#dc3545'];
            $pColor = $priorityColors[$priority] ?? '#0d6efd';

            $bodyHtml = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:650px;margin:0 auto;color:#333;">';
            $bodyHtml .= '<div style="background:#ff3d33;color:#fff;padding:18px 24px;border-radius:6px 6px 0 0;">';
            $bodyHtml .= '<h2 style="margin:0;font-size:20px;">&#128226; ' . e($title) . '</h2></div>';
            $bodyHtml .= '<div style="padding:20px 24px;border:1px solid #e0e0e0;border-top:none;">';
            $bodyHtml .= '<p style="margin:0 0 8px;"><strong>Priority:</strong> <span style="color:' . $pColor . ';font-weight:600;">' . e($priority) . '</span></p>';
            $bodyHtml .= '<div style="background:#f8f9fa;padding:16px;border-radius:6px;margin:12px 0;font-size:14px;line-height:1.6;">' . nl2br(e($content)) . '</div>';
            $bodyHtml .= '<p style="margin:16px 0 0;"><a href="' . e($appUrl . '/announcements/' . $announcementId) . '" style="display:inline-block;padding:10px 24px;background:#ff3d33;color:#fff;text-decoration:none;border-radius:4px;font-weight:600;">View Announcement</a></p>';
            $bodyHtml .= '<p style="margin-top:20px;font-size:12px;color:#888;">This is an automated message from ' . e($appName) . '.</p>';
            $bodyHtml .= '</div></div>';

            $subject = '[Announcement] ' . $title;
            $bodyText = $title . "\n\nPriority: " . $priority . "\n\n" . $content;

            foreach ($recipients as $recipient) {
                $userId = isset($recipient['user_id']) ? (int) $recipient['user_id'] : null;
                $email = (string) ($recipient['email'] ?? '');

                if ($email === '') {
                    continue;
                }

                // In-app notification
                if ($userId !== null) {
                    $notifications->create($userId, 'announcement', $subject, mb_substr($content, 0, 200), 'announcement', $announcementId, '/announcements/' . $announcementId);
                }

                // Queue or deliver email
                if ($sendNow) {
                    $notifications->deliverEmail($email, $subject, $bodyHtml, $bodyText, $userId, 'announcement', $announcementId);
                } else {
                    $notifications->queueEmail($email, $subject, $bodyHtml, $bodyText, $userId, 'announcement', $announcementId);
                }
            }
        } catch (Throwable $ignored) {
            // Email notification failure should not block announcement creation
        }
    }
}